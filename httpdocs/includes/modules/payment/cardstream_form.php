<?php
// File protection as per Zen-Cart suggestions
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}
error_reporting(-1);

//EOF: File protection

class cardstream_form
{

    var $code;
    var $version;
    var $title;
    var $description;
    var $enabled;
    var $order_status = 0;
    var $cardstream_return_values;

    // class constructor
    function cardstream_form()
    {

        global $order, $db;

        $this->code = 'cardstream_form';
        $this->version = MODULE_PAYMENT_CARDSTREAM_ADMIN_TITLE;
        $this->description = MODULE_PAYMENT_CARDSTREAM_ADMIN_DESCRIPTION;
        // Perform error checking of module's configuration ////////////////////////////////////////
        $critical_config_problem = false;

        $this->form_action_url = "https://gateway.cardstream.com/hosted/";


        // Set the title and description based on the mode the module is in: Admin or Catalog
        if ((defined('IS_ADMIN_FLAG') && IS_ADMIN_FLAG === true) || (!isset($_GET['main_page']) ||
                $_GET['main_page'] == '')
        ) {
            // In Admin mode
            $this->title = $this->version;
            //   $this->description = $cardstream_form_config_messages;
        }


        $this->enabled = ((MODULE_PAYMENT_CARDSTREAM_FORM_STATUS == 'True') ? true : false);
        $this->sort_order = MODULE_PAYMENT_CARDSTREAM_FORM_SORT_ORDER;

        if ((int)MODULE_PAYMENT_CARDSTREAM_FORM_ORDER_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_CARDSTREAM_FORM_ORDER_STATUS_ID;
        }

        if (is_object($order)) {
            $this->update_status();
        }

    }

    function update_status()
    {
        global $order, $db;

        if (($this->enabled == true) && ((int)MODULE_PAYMENT_CARDSTREAM_FORM_ZONE > 0)) {
            $check_flag = false;
            $check = $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_CARDSTREAM_FORM_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");
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
        return array('id' => $this->code,
            'module' => $this->title);
    }

    function pre_confirmation_check()
    {
        return false;
    }

    function confirmation()
    {
        return false;
    }

    function process_button()
    {
        global $order, $currencies, $currency, $customer_id, $cart, $products, $contents;


//var_dump($order);
        if (substr_count($order->info['total'], ".") == 1) {

            $exploded = explode(".", $order->info['total']);
            if (strlen($exploded[1]) == 1) {
                $amount = str_replace(".", "", $order->info['total']) * 10;
            } else {
                $amount = str_replace(".", "", $order->info['total']);
            }
        } else {
            $amount = $order->info['total'] * 100;
        }

        //We're gonna need to zen_draw_hidden_field for EVERY FIELD.
        $process_button_string = zen_draw_hidden_field('redirectURL', str_replace('&amp;', '&', zen_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL', true)) . '&') .
            zen_draw_hidden_field('transactionUnique', md5(mktime())) .
            zen_draw_hidden_field('action', 'SALE') .
            zen_draw_hidden_field('type', '1') .
            zen_draw_hidden_field('amount', $amount) .
            zen_draw_hidden_field('customerName', $order->billing['firstname'] . ' ' . $order->billing['lastname']) .
            zen_draw_hidden_field('customerAddress', $order->billing['street_address'] . "\n" . $order->billing['suburb'] . "\n" . $order->billing['city']) .
            zen_draw_hidden_field('customerPostCode', $order->billing['postcode']) .
            zen_draw_hidden_field('customerEmail', $order->customer['email_address']) .
            zen_draw_hidden_field('customerPhone', $order->customer['telephone']) .
            zen_draw_hidden_field('merchantID', MODULE_PAYMENT_CARDSTREAM_FORM_MERCHANT_ID) .
            zen_draw_hidden_field('countryCode', MODULE_PAYMENT_CARDSTREAM_FORM_COUNTRY_ID) .
            zen_draw_hidden_field('currencyCode', MODULE_PAYMENT_CARDSTREAM_FORM_CURRENCY_ID);
        return $process_button_string;
    }

    function after_order_create($zf_order_id)
    {
        global $order, $currencies, $currency, $customer_id, $cart, $products, $contents;
        // Save response from cardstream in the database
        $cardstream_form_response_array = array(
            'transid' => $_POST['transactionUnique'],
            'zen_order_id' => $zf_order_id,
            'received' => $_POST['amountReceived'],
        );
        zen_db_perform("cardstream_form", $cardstream_form_response_array);
    }

    function admin_notification($zf_order_id)
    {
        global $db;

        $sql = "
			SELECT
				*
			FROM
				cardstream_form
			WHERE
				zen_order_id = '" . $zf_order_id . "'";

        $cardstream_form_transaction_info = $db->Execute($sql);

        require(DIR_FS_CATALOG . DIR_WS_MODULES .
            'payment/cardstream_form/cardstream_form_admin_notification.php');

        return $output;
    }

    function before_process()
    {
        global $_GET, $messageStack, $order;

        if (substr_count($order->info['total'], ".") == 1) {

            $exploded = explode(".", $order->info['total']);
            if (strlen($exploded[1]) == 1) {
                $amount = str_replace(".", "", $order->info['total']) * 10;
            } else {
                $amount = str_replace(".", "", $order->info['total']);
            }
        } else {
            $amount = $order->info['total'] * 100;
        }

        if (($_POST["responseCode"] != "0") || ($_POST["amountReceived"] != $amount)) {

            $payment_error_return = 'ERROR ' . strtolower($_POST["responseMessage"]);

            $messageStack->add_session('checkout_payment', $payment_error_return, 'error');

            //  die(var_dump($_GET, $_POST));
            zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
        } else {
            return true;
        }

    }

    function after_process()
    {
        return false;
    }

    function check()
    {
        global $db;
        if (!isset($this->_check)) {
            $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_CARDSTREAM_FORM_STATUS'");
            $this->_check = $check_query->RecordCount();
        }
        return $this->_check;
    }

    function install()
    {
        global $db;
        // General Config Options
        $background_colour = '#d0d0d0';
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('</b><fieldset style=\"background: " . $background_colour . "; margin-bottom: 1.5em;\"><legend style=\"font-size: 1.4em; font-weight: bold\">General Config</legend><b>Enable CardStream Module', 'MODULE_PAYMENT_CARDSTREAM_FORM_STATUS', 'True', 'Do you want to accept CardStream payments?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Merchant ID', 'MODULE_PAYMENT_CARDSTREAM_FORM_MERCHANT_ID', '', '', '2', '1', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Front End Name', 'MODULE_PAYMENT_CARDSTREAM_FORM_CATALOG_TEXT_TITLE', '', '', '3', '1', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Currency ID', 'MODULE_PAYMENT_CARDSTREAM_FORM_CURRENCY_ID', '', '', '4', '1', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Country ID', 'MODULE_PAYMENT_CARDSTREAM_FORM_COUNTRY_ID', '', '', '5', '1', now())");
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
            'MODULE_PAYMENT_CARDSTREAM_FORM_MERCHANT_ID',
            'MODULE_PAYMENT_CARDSTREAM_FORM_CURRENCY_ID',
            'MODULE_PAYMENT_CARDSTREAM_FORM_CATALOG_TEXT_TITLE',
            'MODULE_PAYMENT_CARDSTREAM_FORM_COUNTRY_ID',
            'MODULE_PAYMENT_CARDSTREAM_FORM_STATUS'
        );
    }

}

