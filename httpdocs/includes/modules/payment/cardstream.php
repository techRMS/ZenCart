<?php
// File protection as per Zen-Cart suggestions
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}
$_SESSION['payment_attempt'] = 0;
require(__DIR__ . '/cardstream/StageOrder.php');
class cardstream {

    public $code, $version, $title, $description, $enabled, $order_status;
    private $secret;

    function __construct() {
        global $order, $db;
        $this->code = 'cardstream';
        $this->version = MODULE_PAYMENT_CARDSTREAM_ADMIN_TITLE;
        $this->description = MODULE_PAYMENT_CARDSTREAM_ADMIN_DESCRIPTION;
        $this->secret = MODULE_PAYMENT_CARDSTREAM_MERCHANT_SECRET ?: 'Circle4Take40Idea';

        // set zen-cart payment form action
        $this->form_action_url = $this->form_url();
        // perform checks and disable module if required config is missing
        $this->enabled = $this->valid_setup();
        // set display title for admin or customer
        $this->title = $this->module_title();

        if (IS_ADMIN_FLAG === true && !$this->enabled && MODULE_PAYMENT_CARDSTREAM_CAPTURE_TYPE == 'Direct'){
            $warning = MODULE_PAYMENT_CARDSTREAM_ADMIN_WARNING;
            $this->title .= "<span class=\"alert\" title=\"$warning\">" . substr($warning, 0, 32) . "...</span>";
        }

        // set zen-cart display order
        $this->sort_order = MODULE_PAYMENT_CARDSTREAM_SORT_ORDER;

        if ((int)MODULE_PAYMENT_CARDSTREAM_ORDER_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_CARDSTREAM_ORDER_STATUS_ID;
        }

        if (is_object($order)) {
            $this->update_status();
        }
    }
    /**
     * Is the server running securely?
     * Either check that we are running SSL with the setting defined
     * as ENABLE_SSL or eitherway if it's currently running at all
     */
    function is_https() {
        return (defined('ENABLE_SSL') && strtolower(ENABLE_SSL) == 'true') || ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on')|| (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443') || (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] == 'https'));
    }
    /**
     * Return the normal keys we get in a response
     */
    public static function get_response_template(){
        return array(
            'orderRef',
            'signature',
            'responseCode',
            'transactionUnique',
            'responseMessage',
            'action'
        );
    }

    function update_status() {
        global $order, $db;

        if (($this->enabled == true) && ((int)MODULE_PAYMENT_CARDSTREAM_ZONE > 0)) {
            $check_flag = false;
            $check = $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_CARDSTREAM_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");
            while (!$check->EOF) {
                if ($check->fields['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($check->fields['zone_id'] == $order->billing['zone_id']) {
                    $check_flag = true;
                    break;
                }
                $check->MoveNext();
            }

            if ($check_flag == false) {
                $this->enabled = false;
            }
        }
    }

    function javascript_validation() {
        return false;
    }

    function selection() {
        if (MODULE_PAYMENT_CARDSTREAM_CAPTURE_TYPE == 'Direct') {
            return $this->draw_direct_form();

        } else {
            return array('id' => $this->code,
                'module' => $this->title);
        }
    }
    /**
     * Check card details before sending them preemtively
     */
    function pre_confirmation_check() {
        if (MODULE_PAYMENT_CARDSTREAM_CAPTURE_TYPE == 'Direct') {
            return $this->card_data_check();
        }
        return false;
    }
    /**
     * Check anything before confirming to the next page
     */
    function confirmation() {
        return false;
    }

    /**
     * Modifies the process payment button, used for Hosted Integration
     */
    function process_button() {
        // if hosted form then modify the payment button to have the needed data for hosted form
        if (MODULE_PAYMENT_CARDSTREAM_CAPTURE_TYPE == 'Hosted') {
            return $this->draw_hosted_form_button();
        }elseif(MODULE_PAYMENT_CARDSTREAM_CAPTURE_TYPE == 'Direct'){
            return $this->draw_direct_form_button();
        }
        return false;
    }
    /**
     * Create a request array for direct implementation
     * Used to send before a curl-request to the server
     */
    public function create_direct_request() {
        global $order;
        return array(
            "merchantID"         => MODULE_PAYMENT_CARDSTREAM_MERCHANT_ID,
            "action"             => "SALE",
            "type"               => 1,
            "transactionUnique"  => uniqid(),
            "currencyCode"       => MODULE_PAYMENT_CARDSTREAM_CURRENCY_ID,
            "amount"             => intval($order->info['total'] * 100),
            "orderRef"           => strftime("%d/%m/%y %H:%M") . " - " . self::generate_random_string(),
            "cardNumber"         => $_POST['cardstream_card_number'],
            "cardExpiryMonth"    => $_POST['cardstream_card_expires_month'],
            "cardExpiryYear"     => $_POST['cardstream_card_expires_year'],
            "cardCVV"            => $_POST['cardstream_card_cvv'],
            "customerName"       => $_POST['cardstream_card_holder'],
            "customerEmail"      => $order->customer['email_address'],
            "customerPhone"      => $order->customer['telephone'],
            "customerAddress"    => $order->billing['street_address'] . "\n" . $order->billing['suburb'] . "\n" . $order->billing['city'] . "\n" . $order->billing['state'],
            "countryCode"        => MODULE_PAYMENT_CARDSTREAM_COUNTRY_ID,
            "returnInternalData" => "Y",
            //"threeDSRequired"    => MODULE_PAYMENT_CARDSTREAM_3DS == 'True' ? 'Y' : 'N',
            "customerPostCode"   => $order->billing['postcode'],
            "threeDSMD"          => (isset($_REQUEST['MD']) ? $_REQUEST['MD'] : null),
            "threeDSPaRes"       => (isset($_REQUEST['PaRes']) ? $_REQUEST['PaRes'] : null),
            "threeDSPaReq"       => (isset($_REQUEST['PaReq']) ? $_REQUEST['PaReq'] : null)
        );
    }
    /**
     * Create a request array for hosted implementation
     * Creates an array for hidden input fields to submit to
     * Cardstream to start the payment process
     */
    public function create_hosted_request() {
        global $order, $db;
        $ref = strftime("%d/%m/%y %H:%M") . " - " . self::generate_random_string();
        $session = $_SESSION;
        unset($session['navigation']);
        $session = addslashes(json_encode($session));
        //Delete any old sessions when creating any new one
        $db->Execute("DELETE FROM cardstream_temp_carts WHERE cardstream_cdate <= NOW() - INTERVAL 2 HOUR");
        //Upload session that contains their cart to table called `cardstream_temp_carts`
        $db->Execute("INSERT INTO cardstream_temp_carts (`cardstream_orderRef`, `cardstream_session`, `cardstream_orderID`) VALUES (\"$ref\", \"$session\", NULL)");
        return array(
            "merchantID"        => MODULE_PAYMENT_CARDSTREAM_MERCHANT_ID,
            "amount"            => intval($order->info['total'] * 100),
            "countryCode"       => MODULE_PAYMENT_CARDSTREAM_COUNTRY_ID,
            "currencyCode"      => MODULE_PAYMENT_CARDSTREAM_CURRENCY_ID,
            "transactionUnique" => uniqid(),
            "orderRef"          => $ref,
            "redirectURL"       => str_replace('&amp;', '&', zen_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL', true)),
            "callbackURL"       => ($this->is_https() ? HTTPS_SERVER . DIR_WS_HTTPS_CATALOG : HTTP_SERVER . DIR_WS_CATALOG) . 'cardstream_callback.php',
            "customerName"      => $order->billing['firstname'] . ' ' . $order->billing['lastname'],
            "customerAddress"   => $order->billing['street_address'] . "\n" . $order->billing['suburb'] . "\n" . $order->billing['city'],
            "customerPostCode"  => $order->billing['postcode'],
            "customerEmail"     => $order->customer['email_address'],
            "customerPhone"     => $order->customer['telephone'],
            'securityToken'     => $_SESSION['securityToken'],
            "formResponsive"    => MODULE_PAYMENT_CARDSTREAM_RESPONSIVE_TYPE == 'True' ? 'Y' : 'N'
        );
    }
    /**
     * Before the order is created we need to make sure we
     * have either have paid from hosted integration from a
     * callback or from a redirect (if callback ever fails). Direct
     * implementation just processes the payment with a curl request.
     * Both implementations must process responses (from $_POST or curl)
     * before creating an order. Preventing an order being created, one
     * must add an error to messageStack and redirect back to the payment
     * page.
     *
     * We must also prevent any additional duplicate orders from being created
     * by using a spoofed class that will simply return the ID of the already
     * created order (e.g. from a callback).
     */
    function before_process() {
        global $db;
        $_POST = (isset($_SESSION['CARDSTREAM_CALLBACK']) ? $_SESSION['CARDSTREAM_CALLBACK'] : $_POST);
        //Implement behaviour for the two modules
        if (MODULE_PAYMENT_CARDSTREAM_CAPTURE_TYPE == 'Hosted') {
            if (self::has_keys($_POST, self::get_response_template())) {
                //Respond to the users redirect request the same way as a callback
                $this->res = $_POST;
                //Check if we came back via the redirect (CARDSTREAM_CALLBACK WILL NOT BE SET!)
                if (!defined('CARDSTREAM_CALLBACK')) {
                    //Get the status of the callback
                    $results = $db->Execute("SELECT * FROM cardstream_temp_carts WHERE cardstream_orderRef = \"" . $this->res['orderRef'] . "\"");
                    if ($results->fields['cardstream_orderID'] !== null) {
                        //Make sure to prevent any duplicates...
                        $id = intval($results->fields['cardstream_orderID']);
                        define('CARDSTREAM_CALLBACK_ID', $id);
                        $this->stage_order = new StageOrder($id);
                        $this->stage_order->stage();
                    }
                }
            }
        } else {
            $req = $this->create_direct_request();
            $this->res = $this->make_request(MODULE_PAYMENT_CARDSTREAM_DIRECT_URL, $req);
        }
        $this->process_all();
    }
    /**
     * Process all responses from any integration
     * NOTE: hosted will not provide a 65802 response
     * as this is done automatically.
     */
    function process_all() {
        global $messageStack;
        //Start processing responses
        if ($this->res['responseCode'] == 65802) {
            // Send details to 3D Secure ACS and the return here to repeat request
            $pageUrl = ($this->is_https() ? HTTP_SERVER : HTTPS_SERVER) . $_SERVER["REQUEST_URI"];
            die("
                <script>document.onreadystatechange = function() { document.getElementById('3ds').submit(); }</script>
                <form id='3ds' action=\"" . htmlentities($this->res['threeDSACSURL']) . "\" method=\"post\">
                    <input type=\"hidden\" name=\"MD\" value=\"" . htmlentities($this->res['threeDSMD']) . "\">
                    <input type=\"hidden\" name=\"PaReq\" value=\"" . htmlentities($this->res['threeDSPaReq']) . "\">
                    <input type=\"hidden\" name=\"TermUrl\" value=\"" . htmlentities($pageUrl) . "\">
                </form>
            ");
        } else {
            $error = null;
            if (isset($this->res['signature'])){
                $sig = $this->res['signature'];
                unset($this->res['signature']);
                if($sig != $this->create_signature($this->res, $this->secret)){
                    $error = MODULE_PAYMENT_CARDSTREAM_VERIFY_ERROR;
                    error_log($error);
                } elseif (intval($this->res['responseCode']) != 0) {
                    $error = sprintf(MODULE_PAYMENT_CARDSTREAM_RESPONSE_ERROR, htmlentities($this->res['responseMessage']));
                }
            } else {
                $error = MODULE_PAYMENT_CARDSTREAM_VERIFY_ERROR;
                error_log($error);
            }
            if (!is_null($error)){
                $messageStack->add_session('checkout_payment', $error, 'error');
                zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
            }
        }
    }
    /*
     * A function called after the order is created (placed)
     * Here we want to stop any spoofs that we may have set
     * up earlier and to upload the responses to the database
     * NOTE: Only successful responses will be saved in an order
     * otherwise an order would never have been created if payment
     * has failed
     */
    function after_order_create($zf_order_id) {
        global $db;
        $db->Execute("UPDATE " . TABLE_ORDERS . " SET " .
            "cardstream_xref = \"" . $this->res['xref'] . "\", " .
            "cardstream_transactionUnique = \"" . $this->res['transactionUnique'] . "\", " .
            "cardstream_amount_received = " . floatval($this->res['amountReceived']) / 100 . ", " .
            "cardstream_authorisationCode = \"" . $this->res['authorisationCode'] . "\", " .
            "cardstream_responseMessage = \"" . $this->res['responseMessage'] . "\", " .
            "cardstream_lastAction = \"" . $this->res['action'] . "\", " .
            "orders_status = " . MODULE_PAYMENT_CARDSTREAM_ORDER_STATUS_ID . " " .
            "WHERE orders_id = $zf_order_id"
        );
         if(defined('CARDSTREAM_CALLBACK')) {
            $db->Execute("UPDATE cardstream_temp_carts
                SET cardstream_orderID = $zf_order_id
                WHERE cardstream_orderRef = \"{$this->res['orderRef']}\""
            );
        } else {
            //Remove incomplete payments and remove finished carts
            $db->Execute("DELETE FROM cardstream_temp_carts WHERE cardstream_orderRef = \"" . $this->res["orderRef"] . "\" OR cardstream_cdate <= NOW() - INTERVAL 2 HOUR");
        }
    }
    /*
     * Returns what the module is called
     */
    function module_title() {
        // Set the title and description based on the mode the module is in: Admin or Catalog
        if ((defined('IS_ADMIN_FLAG') && IS_ADMIN_FLAG === true) || (!isset($_GET['main_page']) || $_GET['main_page'] == '')) {
            // In Admin mode
            return MODULE_PAYMENT_CARDSTREAM_ADMIN_TITLE;
        } else {
            // In Catalog mode
            return MODULE_PAYMENT_CARDSTREAM_CATALOG_TEXT_TITLE;
        }
    }

    /**
     * performs checks to make sure we have everything needed for payments
     *
     * @return bool
     */
    function valid_setup() {
        $isEnabled = MODULE_PAYMENT_CARDSTREAM_STATUS == 'True';
        //Make sure that the Cardstream module is enable and that we're running HTTPS on a direct capture type
        return ($isEnabled && MODULE_PAYMENT_CARDSTREAM_CAPTURE_TYPE === 'Direct' && $this->is_https() || $isEnabled && MODULE_PAYMENT_CARDSTREAM_CAPTURE_TYPE === 'Hosted');
    }
    /*
     * Returns different URL's for each integration for
     * the continue to payment button
     */
    function form_url() {
        if (MODULE_PAYMENT_CARDSTREAM_CAPTURE_TYPE == 'Hosted') {
            return MODULE_PAYMENT_CARDSTREAM_FORM_URL;
        } else {
            return zen_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL', true);
        }
    }
    /*
     * Draws input fields for card details when using direct integration
     */
    function draw_direct_form() {
        global $order;

        for ($i = 1; $i < 13; $i++) {
            $expires_month[] = array('id' => sprintf('%02d', $i), 'text' => strftime('%B - (%m)', mktime(0, 0, 0, $i, 1, 2000)));
        }

        $today = getdate();
        for ($i = $today['year']; $i < $today['year'] + 15; $i++) {
            $expires_year[] = array('id' => strftime('%y', mktime(0, 0, 0, 1, 1, $i)), 'text' => strftime('%Y', mktime(0, 0, 0, 1, 1, $i)));
        }

        $onFocus = ' onfocus="methodSelect(\'pmt-' . $this->code . '\')"';

        $selection = array(
            'id'     => 'cardstream',
            'module' => $this->module_title(),
            'fields' => array(
                array(
                    'title' => MODULE_PAYMENT_CARDSTREAM_CARD_HOLDER,
                    'field' => zen_draw_input_field('cardstream_card_holder', $order->billing['firstname'] . ' ' . $order->billing['lastname'], 'id="cardstream-cc-owner"' . $onFocus . ' autocomplete="off"'),
                    'tag'   => 'cardstream-cc-owner'
                ),
                array(
                    'title' => MODULE_PAYMENT_CARDSTREAM_CARD_NUMBER,
                    'field' => zen_draw_input_field('cardstream_card_number', $ccnum, 'id="cardstream-cc-number"' . $onFocus . ' autocomplete="off"'),
                    'tag'   => $this->code . '-cc-number'
                ),
                array(
                    'title' => MODULE_PAYMENT_CARDSTREAM_CARD_EXPIRE,
                    'field' => zen_draw_pull_down_menu('cardstream_card_expires_month', $expires_month, strftime('%m'), 'id="cardstream-cc-expires-month"' . $onFocus) . '&nbsp;' . zen_draw_pull_down_menu('cardstream_card_expires_year', $expires_year, '', 'id="cardstream-cc-expires-year"' . $onFocus),
                    'tag'   => 'cardstream-card-expires-month'
                ),
                array(
                    'title' => MODULE_PAYMENT_CARDSTREAM_CARD_CVV,
                    'field' => zen_draw_input_field('cardstream_card_cvv', '', 'size="4" maxlength="4"' . ' id="cardstream-cc-cvv"' . $onFocus . ' autocomplete="off"') . ' ' . '<a href="javascript:popupWindow(\'' . zen_href_link(FILENAME_POPUP_CVV_HELP) . '\')">' . MODULE_PAYMENT_CARDSTREAM_CARD_CVV_HELP . '</a>',
                    'tag'   => 'cardstream-card-cvv'
                )
            )
        );

        return $selection;
    }
    /*
     * Draws hidden input fields for the checkout confirmation page
     * when using direct integration
     */
    function draw_direct_form_button() {
        $process_button_string = zen_draw_hidden_field('cardstream_card_holder', $_POST['cardstream_card_holder']) .
            zen_draw_hidden_field('cardstream_card_expires', $this->card['expiry_month'] . substr($this->card['expiry_year'], -2)) .
            zen_draw_hidden_field('cardstream_card_expires_month', $this->card['expiry_month']) .
            zen_draw_hidden_field('cardstream_card_expires_year', substr($this->card['expiry_year'], -2)) .
            zen_draw_hidden_field('cardstream_card_type', $this->card['card_type']) .
            zen_draw_hidden_field('cardstream_card_number', $this->card['card_number']) .
            zen_draw_hidden_field('cardstream_card_cvv', $_POST['cardstream_card_cvv']);
        $process_button_string .= zen_draw_hidden_field(zen_session_name(), zen_session_id());
        return $process_button_string;
    }
    /*
     * Draws hidden input fields for the checkout confirmation page
     * when using hosted integration
     */
    function draw_hosted_form_button() {
        $fields = $this->create_hosted_request();
        ksort($fields);
        $fields['signature'] = $this->create_signature($fields, $this->secret);
        $button_string = "";
        foreach (array_keys($fields) as $field) {
            $button_string .= zen_draw_hidden_field($field, $fields[$field]) . "\n";
        }
        return $button_string;
    }
    /*
     * Perform a basic check on card details provided to solve most
     * user errors rather than sending any invalid data
     */
    function card_data_check() {
        global $db, $messageStack;

        include(DIR_WS_CLASSES . 'cc_validation.php');

        $cc_validation = new cc_validation();
        $result        = $cc_validation->validate($_POST['cardstream_card_number'], $_POST['cardstream_card_expires_month'], $_POST['cardstream_card_expires_year']);
        $error         = '';
        switch ($result) {
            case -1:
                $error = sprintf(TEXT_CCVAL_ERROR_UNKNOWN_CARD, substr($cc_validation->cc_number, 0, 4));
                break;
            case -2:
            case -3:
            case -4:
                $error = TEXT_CCVAL_ERROR_INVALID_DATE;
                break;
            case false:
                $error = TEXT_CCVAL_ERROR_INVALID_NUMBER;
                break;
        }

        if (($result == false) || ($result < 1)) {
            $payment_error_return = 'payment_error=cardstream';
            $error_info2          = '&error=' . urlencode($error) . '&cardstream_card_holder=' . urlencode($_POST['cardstream_card_holder']) . '&cardstream_card_expires_month=' . $_POST['cardstream_card_expires_month'] . '&cardstream_card_expires_year=' . $_POST['cardstream_card_expires_year'];
            $messageStack->add_session('checkout_payment', $error . '<!-- [cardstream] -->', 'error');
            zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
        }

        // if no error, continue with validated data:
        $this->card['card_type']    = $cc_validation->cc_type;
        $this->card['card_number']  = $cc_validation->cc_number;
        $this->card['expiry_month'] = $cc_validation->cc_expiry_month;
        $this->card['expiry_year']  = $cc_validation->cc_expiry_year;

    }
    /*
     * Make a curl request for direct integration
     * Returns array of response data
     */
    function make_request($url, $req) {
        $req['signature'] = $this->create_signature($req, $this->secret);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($req));
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        parse_str(curl_exec($ch), $res);
        curl_close($ch);
        return $res;
    }
    /*
     * Create a signature from the array and key provided
     */
    function create_signature(array $data, $key) {
        if (!$key || !is_string($key) || $key === '' || !$data || !is_array($data)) {
                return null;
        }

        ksort($data);

        // Create the URL encoded signature string
        $ret = http_build_query($data, '', '&');

        // Normalise all line endings (CRNL|NLCR|NL|CR) to just NL (%0A)
        $ret = preg_replace('/%0D%0A|%0A%0D|%0A|%0D/i', '%0A', $ret);

        // Hash the signature string and the key together
        return hash('SHA512', $ret . $key);
    }
    /*
     * Generate a random string with an optional length specifed
     * (or otherwise 10 characters)
     */
    public static function generate_random_string($length = 10) {
        //Generate a random string of uppercase and lowercase letters including numbers 0-9.
        //Can be used to create seeds/ID"s etc etc
        $characters = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $randomString = "";
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }
    /*
     * Returns a boolean value on whether an array has the keys
     * wanted from the $keys array
     */
    public static function has_keys($array, $keys) {
        $checkKeys = array_keys($array);
        $str = '';
        foreach ($keys as $key){
            if(!array_key_exists($key, $array)) {
                return false;
            }
        }
        return true;
    }
    /*
     * Import a zen cart session
     */
    public static function import_session($session) {
        //Try to get the session back as best as possible
        unset($session['navigation']);
        unset($session['securityToken']);
        foreach(array_keys($session) as $key){
            $_SESSION[$key] = $session[$key];
        }
        $cart = $_SESSION['cart'];
        $_SESSION['cart'] = new shoppingCart();
        foreach(array_keys($cart) as $cartItem){
            $_SESSION['cart']->$cartItem = $cart[$cartItem];
        }
    }

    function admin_notification($zf_order_id)
    {
        global $db;

        $sql = "SELECT * FROM " . TABLE_ORDERS . " WHERE orders_id = $zf_order_id";

        $form_transaction_info = $db->Execute($sql);

        require(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/cardstream/cardstream_admin_notification.php');

        return $output;
    }
    /*
     * Do something after the payment and order process is complete
     */
    function after_process()
    {
        //Remove?
    }

    function _doRefund($oID, $amount = 'Full', $note = '')
    {

        global $db, $messageStack, $order;

        $transaction_info = $db->Execute("SELECT * FROM " . TABLE_ORDERS . " WHERE zen_order_id = '$oID'");

        if ($transaction_info->RecordCount() < 1) {
            $messageStack->add_session(MODULE_PAYMENT_CARDSTREAM_TEXT_NO_MATCHING_ORDER_FOUND, 'error');
            $proceedToRefund = false;
        }

        // check user ticked the confirm box
        if (isset($_POST['refconfirm']) && $_POST['refconfirm'] != 'on') {
            $messageStack->add_session(MODULE_PAYMENT_CARDSTREAM_TEXT_REFUND_CONFIRM_ERROR, 'error');
            $proceedToRefund = false;
        }
        // check user gave a valid refund amount
        if (isset($_POST['refamt']) && (float)$_POST['refamt'] == 0) {
            $messageStack->add_session(MODULE_PAYMENT_CARDSTREAM_TEXT_INVALID_REFUND_AMOUNT, 'error');
            $proceedToRefund = false;
        }

        if(!$proceedToRefund){
            return false;
        }

        $req = array(
            "merchantID" => MODULE_PAYMENT_CARDSTREAM_MERCHANT_ID,
            "action" => "REFUND",
            "type" => 1,
            "amount" => (float)$_POST['refamt'] * 100,
            'xref' => $transaction_info->fields['cardstream_xref'],
            'merchantData' => $this->version

        );

        $res = $this->make_request(MODULE_PAYMENT_CARDSTREAM_DIRECT_URL,$req);

        if(isset($res['responseCode']) && $res['responseCode'] == 0){

            if($_POST['refamt'] == $order->info['total']){
                $new_order_status = MODULE_PAYMENT_CARDSTREAM_REFUNDED_STATUS_ID;
            }elseif($order->info['total'] > $_POST['refamt']){
                $new_order_status = MODULE_PAYMENT_CARDSTREAM_PART_REFUNDED_STATUS_ID;
            }else{
                $new_order_status = MODULE_PAYMENT_CARDSTREAM_REFUNDED_STATUS_ID;
            }

            $sql_data_array= array(array('fieldName'=>'orders_id', 'value' => $oID, 'type'=>'integer'),
                array('fieldName'=>'orders_status_id', 'value' => MODULE_PAYMENT_CARDSTREAM_REFUNDED_STATUS_ID, 'type'=>'integer'),
                array('fieldName'=>'date_added', 'value' => 'now()', 'type'=>'noquotestring'),
                array('fieldName'=>'comments', 'value' => MODULE_PAYMENT_CARDSTREAM_REFUND_DEFAULT_MESSAGE." {$_POST['refamt']}", 'type'=>'string'),
                array('fieldName'=>'customer_notified', 'value' => 0, 'type'=>'integer'));
            $db->perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
            $db->Execute("update " . TABLE_ORDERS  . "
                  set orders_status = '" . (int)$new_order_status . "'
                  where orders_id = '" . (int)$oID . "'");

            $messageStack->add_session(sprintf(MODULE_PAYMENT_CARDSTREAM_TEXT_REFUND_INITIATED, $res['transactionID'], $oID), 'success');

            return true;
        }else{
            $messageStack->add_session(MODULE_PAYMENT_CARDSTREAM_TEXT_INVALID_REFUND_AMOUNT, 'error');
            return false;
        }
    }
    /*
     * Used to check an install of the configuration in the Database
     */
    function check()
    {
        global $db;
        if (!isset($this->_check)) {
            $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_CARDSTREAM_STATUS'");
            $this->_check = $check_query->RecordCount();
        }
        return $this->_check;
    }
    /*
     * Setup process of the module
     * Making sure that all tables and settings are updated.
     */
    function install()
    {
        global $db;
        // General Config Options
        $background_colour = '#d0d0d0';
        $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable CardStream Module', 'MODULE_PAYMENT_CARDSTREAM_STATUS', 'True', 'Do you want to accept CardStream payments?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Select Integration Method', 'MODULE_PAYMENT_CARDSTREAM_CAPTURE_TYPE', 'Hosted', 'Do you want to use Direct (SSL Required) or Hosted ', '6', '2', 'zen_cfg_select_option(array(\'Hosted\', \'Direct\'), ', now())");
        $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Merchant ID', 'MODULE_PAYMENT_CARDSTREAM_MERCHANT_ID', 'TEST', 'Merchant ID set in your mms', '6', '3', now())");
        $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Merchant Secret', 'MODULE_PAYMENT_CARDSTREAM_MERCHANT_SECRET', 'TEST', 'Merchant signature secret as set in mms', '6', '4', now())");
        $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Payment Name.', 'MODULE_PAYMENT_CARDSTREAM_CATALOG_TEXT_TITLE', 'Card Payment', 'Name of payment method shown to customer', '6', '5', now())");
        $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Display Order.', 'MODULE_PAYMENT_CARDSTREAM_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '6', now())");
        $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Currency ID.', 'MODULE_PAYMENT_CARDSTREAM_CURRENCY_ID', '826', 'ISO currency number', '6', '7', now())");
        $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Country ID.', 'MODULE_PAYMENT_CARDSTREAM_COUNTRY_ID', '826', 'ISO currency number', '6', '8', now())");
        $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Responsive Hosted Layout', 'MODULE_PAYMENT_CARDSTREAM_RESPONSIVE_TYPE', 'True', 'Use responsive layout on a hosted form?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");

        $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_CARDSTREAM_ORDER_STATUS_ID', '2', 'Set the status of orders paid with this payment module to this value. <br /><strong>Recommended: Processing[2]</strong>', '6', '25', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Unpaid Order Status', 'MODULE_PAYMENT_CARDSTREAM_ORDER_PENDING_STATUS_ID', '1', 'Set the status of unpaid orders made with this payment module to this value. <br /><strong>Recommended: Pending[1]</strong>', '6', '25', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Refund Order Status', 'MODULE_PAYMENT_CARDSTREAM_REFUNDED_STATUS_ID', '1', 'Set the status of refunded orders to this value. <br /><strong>Recommended: Pending[1]</strong>', '6', '25', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("REPLACE INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Partial Refund Order Status', 'MODULE_PAYMENT_CARDSTREAM_PART_REFUNDED_STATUS_ID', '2', 'Set the status of partially refunded orders to this value. <br /><strong>Recommended: Processing[2]</strong>', '6', '25', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $result = $db->Execute("
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = '" . TABLE_ORDERS . "'
            AND TABLE_SCHEMA = '" . DB_DATABASE . "'
            AND COLUMN_NAME IN ('cardstream_xref', 'cardstream_transactionUnique', 'cardstream_amount_received', 'cardstream_authorisationCode', 'cardstream_responseMessage', 'cardstream_lastAction')
        ");
        if (intval($result->fields['COUNT(*)']) < 1) {
            $db->Execute("ALTER TABLE " . TABLE_ORDERS . "
                ADD COLUMN `cardstream_xref` VARCHAR(128) NULL,
                ADD COLUMN `cardstream_transactionUnique` VARCHAR(128) NULL,
                ADD COLUMN `cardstream_amount_received` FLOAT NOT NULL DEFAULT '0.0',
                ADD COLUMN `cardstream_authorisationCode` VARCHAR(128) NULL,
                ADD COLUMN `cardstream_responseMessage` TEXT NULL,
                ADD COLUMN `cardstream_lastAction` VARCHAR(32) NULL
            ");
        }
        $db->Execute("CREATE TABLE IF NOT EXISTS cardstream_temp_carts (cardstream_orderRef VARCHAR(64) NOT NULL, cardstream_session TEXT NOT NULL, cardstream_cdate TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, cardstream_orderID int NULL)");
        $background_colour = '#eee';
    }
    /*
     * Uninstallation process of the module
     */
    function remove()
    {
        global $db;
        $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }
    /*
     * The settings that this module provides
     */
    function keys()
    {
        return array(
            'MODULE_PAYMENT_CARDSTREAM_STATUS',
            'MODULE_PAYMENT_CARDSTREAM_CAPTURE_TYPE',
            'MODULE_PAYMENT_CARDSTREAM_RESPONSIVE_TYPE',
            'MODULE_PAYMENT_CARDSTREAM_MERCHANT_ID',
            'MODULE_PAYMENT_CARDSTREAM_MERCHANT_SECRET',
            'MODULE_PAYMENT_CARDSTREAM_CATALOG_TEXT_TITLE',
            'MODULE_PAYMENT_CARDSTREAM_CURRENCY_ID',
            'MODULE_PAYMENT_CARDSTREAM_COUNTRY_ID',
            'MODULE_PAYMENT_CARDSTREAM_SORT_ORDER',
            'MODULE_PAYMENT_CARDSTREAM_ORDER_STATUS_ID',
            'MODULE_PAYMENT_CARDSTREAM_ORDER_PENDING_STATUS_ID',
            'MODULE_PAYMENT_CARDSTREAM_REFUNDED_STATUS_ID',
            'MODULE_PAYMENT_CARDSTREAM_PART_REFUNDED_STATUS_ID'
        );
    }

}