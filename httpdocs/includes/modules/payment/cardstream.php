<?php
// File protection as per Zen-Cart suggestions
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}
error_reporting(9);

//EOF: File protection

class cardstream
{

    /**
     * @var string
     */
    public $code;

    /**
     * @var string
     */
    public $version;

    /**
     * @var
     */
    public $title;

    /**
     * @var string
     */
    public $description;

    /**
     * @var bool
     */
    public $enabled;

    /**
     * @var int
     */
    public $order_status = 0;

    /**
     * holds the cardstream lib
     *
     * @var cardstream_lib
     */
    protected $cs;


    // class constructor
    function __construct()
    {

        global $order;

        // include cardstream lib
        require(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/cardstream/cardstream.lib.php');
        $this->cs = new cardstream_lib();

        $this->code = 'cardstream';
        $this->version = MODULE_PAYMENT_CARDSTREAM_ADMIN_TITLE;
        $this->description = MODULE_PAYMENT_CARDSTREAM_ADMIN_DESCRIPTION;
        // set zen-cart payment form action
        $this->form_action_url = $this->cs->form_url();

        // set display title for admin or customer
        $this->title = $this->cs->module_title();

        // perform checks and disable module if required config is missing
        $this->enabled = $this->cs->valid_setup();

        // set zen-cart display order
        $this->sort_order = MODULE_PAYMENT_CARDSTREAM_SORT_ORDER;

        if ((int)MODULE_PAYMENT_CARDSTREAM_ORDER_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_CARDSTREAM_ORDER_STATUS_ID;
        }

        if (is_object($order)) {
            $this->update_status();
        }

    }

    function update_status()
    {
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

    function javascript_validation()
    {
        return false;
    }

    function selection()
    {

        if (MODULE_PAYMENT_CARDSTREAM_CAPTURE_TYPE == 'Direct') {

            return $this->cs->draw_direct_form();

        } else {
            return array('id' => $this->code,
                'module' => $this->title);
        }

    }

    function pre_confirmation_check()
    {
        if (MODULE_PAYMENT_CARDSTREAM_CAPTURE_TYPE == 'Direct') {
            return $this->cs->card_data_check();
        }
        return false;
    }

    function confirmation()
    {
        return false;
    }

    /**
     * mods the zen-cart process payment button, used for Hosted Integration
     */
    function process_button()
    {

        // if hosted form then modify the payment button to have the needed data for hosted form
        if (MODULE_PAYMENT_CARDSTREAM_CAPTURE_TYPE == 'Hosted') {
         return $this->cs->draw_hosted_form();
        }elseif(MODULE_PAYMENT_CARDSTREAM_CAPTURE_TYPE == 'Direct'){
            return $this->cs->draw_direct_form_button();
        }

        return false;
    }

    function after_order_create($zf_order_id)
    {
        global $order, $currencies, $currency, $customer_id, $cart, $products, $contents;
        // Save response from gateway in the database
        $form_response_array = array(
            'transid' => $_POST['transactionUnique'],
            'zen_order_id' => $zf_order_id,
            'received' => $_POST['amountReceived'],
            'xref' => $_POST['xref'],
            'authorisationCode' => $_POST['authorisationCode'],
            'action' => $_POST['action'],
            'responseMessage' => $_POST['responseMessage']
        );

       // die(var_dump($_POST));
        zen_db_perform('cardstream', $form_response_array);
    }

    function admin_notification($zf_order_id)
    {
        global $db;

        $sql = "
			SELECT
				*
			FROM
				cardstream
			WHERE
				zen_order_id = '" . $zf_order_id . "'";

        $form_transaction_info = $db->Execute($sql);

        require(DIR_FS_CATALOG . DIR_WS_MODULES .
            'payment/cardstream/cardstream_admin_notification.php');

        return $output;
    }

    function before_process()
    {

        if (MODULE_PAYMENT_CARDSTREAM_CAPTURE_TYPE == 'Hosted') {
            $this->cs->process_hosted();
        }else{
            $this->cs->process_direct();
        }

    }

    function after_process()
    {

        global $db, $insert_id;

        $sql = "insert into " . TABLE_ORDERS_STATUS_HISTORY . " (comments, orders_id, orders_status_id, customer_notified, date_added) values (:orderComments, :orderID, :orderStatus, -1, now() )";
        $sql = $db->bindVars($sql, ':orderComments', 'Credit Card payment', 'string');
        $sql = $db->bindVars($sql, ':orderID', $insert_id, 'integer');
        $sql = $db->bindVars($sql, ':orderStatus', MODULE_PAYMENT_CARDSTREAM_ORDER_STATUS_ID, 'integer');
        $db->Execute($sql);
        return false;

        return false;
    }

    function _doRefund(){
        return true;
    }


    function check()
    {
        global $db;
        if (!isset($this->_check)) {
            $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_CARDSTREAM_STATUS'");
            $this->_check = $check_query->RecordCount();
        }
        return $this->_check;
    }

    function install()
    {
        global $db;
        // General Config Options
        $background_colour = '#d0d0d0';
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable CardStream Module', 'MODULE_PAYMENT_CARDSTREAM_STATUS', 'True', 'Do you want to accept CardStream payments?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Select Integration Method', 'MODULE_PAYMENT_CARDSTREAM_CAPTURE_TYPE', 'Hosted', 'Do you want to use Direct (SSL Required) or Hosted ', '6', '2', 'zen_cfg_select_option(array(\'Hosted\', \'Direct\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Merchant ID', 'MODULE_PAYMENT_CARDSTREAM_MERCHANT_ID', 'TEST', 'Merchant ID set in your mms', '6', '3', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Merchant Password', 'MODULE_PAYMENT_CARDSTREAM_MERCHANT_PASSWORD', 'TEST', 'Merchant password set in your mms', '6', '4', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Payment Name.', 'MODULE_PAYMENT_CARDSTREAM_CATALOG_TEXT_TITLE', 'Card Payment', 'Name of payment method shown to customer', '6', '5', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Display Order.', 'MODULE_PAYMENT_CARDSTREAM_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '6', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Currency ID.', 'MODULE_PAYMENT_CARDSTREAM_CURRENCY_ID', '826', 'ISO currency number', '6', '7', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Country ID.', 'MODULE_PAYMENT_CARDSTREAM_COUNTRY_ID', '826', 'ISO currency number', '6', '8', now())");


        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_CARDSTREAM_ORDER_STATUS_ID', '2', 'Set the status of orders paid with this payment module to this value. <br /><strong>Recommended: Processing[2]</strong>', '6', '25', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Unpaid Order Status', 'MODULE_PAYMENT_CARDSTREAM_ORDER_PENDING_STATUS_ID', '1', 'Set the status of unpaid orders made with this payment module to this value. <br /><strong>Recommended: Pending[1]</strong>', '6', '25', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Refund Order Status', 'MODULE_PAYMENT_CARDSTREAM_REFUNDED_STATUS_ID', '1', 'Set the status of refunded orders to this value. <br /><strong>Recommended: Pending[1]</strong>', '6', '25', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $background_colour = '#eee';
    }

    function remove()
    {
        global $db;
        $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys()
    {
        return array(
            'MODULE_PAYMENT_CARDSTREAM_STATUS',
            'MODULE_PAYMENT_CARDSTREAM_CAPTURE_TYPE',
            'MODULE_PAYMENT_CARDSTREAM_MERCHANT_ID',
            'MODULE_PAYMENT_CARDSTREAM_MERCHANT_PASSWORD',
            'MODULE_PAYMENT_CARDSTREAM_CATALOG_TEXT_TITLE',
            'MODULE_PAYMENT_CARDSTREAM_CURRENCY_ID',
            'MODULE_PAYMENT_CARDSTREAM_COUNTRY_ID',
            'MODULE_PAYMENT_CARDSTREAM_SORT_ORDER',
            'MODULE_PAYMENT_CARDSTREAM_ORDER_STATUS_ID',
            'MODULE_PAYMENT_CARDSTREAM_ORDER_PENDING_STATUS_ID',
            'MODULE_PAYMENT_CARDSTREAM_REFUNDED_STATUS_ID'
        );
    }

}

