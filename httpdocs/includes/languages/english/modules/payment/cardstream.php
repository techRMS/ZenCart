<?php
// File protection as per Zen-Cart suggestions
	if (!defined('IS_ADMIN_FLAG')) {
	  die('Illegal Access');
	}
// EOF: File protection


define('MODULE_PAYMENT_CARDSTREAM_DIRECT_URL','https://gateway.cardstream.com/direct/');
define('MODULE_PAYMENT_CARDSTREAM_FORM_URL', 'https://gateway.cardstream.com/hosted/');

define('MODULE_PAYMENT_CARDSTREAM_ADMIN_TITLE', 'CardStream Integration');
define('MODULE_PAYMENT_CARDSTREAM_ADMIN_DESCRIPTION', '<a target=\"_blank\" href=\"https://www.cardstream.com?ref=zen-cart\"><img style=\"float:right;margin-right:8px;\" src=\"https://www.cardstream.com/images/logo.png?ref=zen-cart\"/></a> <br/><a target="_blank" href="https://www.cardstream.com/signup?ref=zen-cart">Click Here to Sign Up for an Account</a><br /><br /><a target="_blank" href="https://mms.cardstream.com/admin?ref=zen-cart">Login to the CardStream Merchant Area</a><br /><br /><strong>Requirements:</strong><br /><hr />*<strong>CardStream Account</strong> (see link above to signup)<br />*<strong>CardStream MerchantID</strong> available from your Merchant Area<br/> *<strong>CardStream Merchant Password</strong> set in mms &amp; required for zen-cart');
define('MODULE_PAYMENT_CARDSTREAM_ADMIN_WARNING', 'Warning! Direct Cardstream integration must use HTTPS to follow PCI DSS compliancy. Get a certificate from a trused authority and host your website securely to continue using the Direct integration method.');
define('MODULE_PAYMENT_CARDSTREAM_CARD_HOLDER', 'Name as shown on card');
define('MODULE_PAYMENT_CARDSTREAM_CARD_NUMBER', 'Card Number');
define('MODULE_PAYMENT_CARDSTREAM_CARD_EXPIRE', 'Card Expires');
define('MODULE_PAYMENT_CARDSTREAM_CARD_CVV', 'Card Verification Number');
define('MODULE_PAYMENT_CARDSTREAM_CARD_CVV_HELP', 'What is a CVV?');
define('MODULE_PAYMENT_CARDSTREAM_REFUND_TITLE', '<strong>Refund Transactions</strong>');

define('MODULE_PAYMENT_CARDSTREAM_CALLBACK_DUPLICATE_LOG', 'ERROR! The payment callback was requested to complete an order but the order has already been created');
define('MODULE_PAYMENT_CARDSTREAM_CALLBACK_INVALID_RESPONSE_LOG', 'ERROR! The payment callback was called but the response did not contain all valid keys');
define('MODULE_PAYMENT_CARDSTREAM_VERIFY_ERROR_LOG', 'ERROR! A transaction could not be completed because the response returned did not match the signature');
define('MODULE_PAYMENT_CARDSTREAM_VERIFY_ERROR', 'Sorry but we could not verify this transaction. Please refer this error the merchant or administrator');
define('MODULE_PAYMENT_CARDSTREAM_RESPONSE_ERROR', 'Sorry but we could not take this payment (reason: %s)');
define('MODULE_PAYMENT_CARDSTREAM_TEXT_NO_MATCHING_ORDER_FOUND', 'Error: Could not find transaction details for the record specified.');
define('MODULE_PAYMENT_CARDSTREAM_REFUND_BUTTON_TEXT', 'Do Refund');
define('MODULE_PAYMENT_CARDSTREAM_TEXT_REFUND_CONFIRM_ERROR', 'Error: You requested to do a refund but did not check the Confirmation box.');
define('MODULE_PAYMENT_CARDSTREAM_TEXT_INVALID_REFUND_AMOUNT', 'Error: You requested a refund but entered an invalid amount.');

define('MODULE_PAYMENT_CARDSTREAM_TEXT_REFUND_INITIATED', 'Refund Initiated. Transaction ID: %s - Order ID: %s');

define('MODULE_PAYMENT_CARDSTREAM_REFUND', 'You may refund money to the customer\'s original credit card here.');
define('MODULE_PAYMENT_CARDSTREAM_REFUND_CONFIRM_CHECK', 'Check this box to confirm your intent: ');
define('MODULE_PAYMENT_CARDSTREAM_REFUND_AMOUNT_TEXT', 'Enter the amount you wish to refund');
define('MODULE_PAYMENT_CARDSTREAM_REFUND_DEFAULT_TEXT', 'enter Trans.ID');
define('MODULE_PAYMENT_CARDSTREAM_REFUND_CC_NUM_TEXT', 'Enter the last 4 digits of the Credit Card you are refunding.');
define('MODULE_PAYMENT_CARDSTREAM_REFUND_TRANS_ID', 'Enter the original Transaction ID <em>(which usually looks like this: <strong>1193684363</strong>)</em>:');
define('MODULE_PAYMENT_CARDSTREAM_REFUND_TEXT_COMMENTS', 'Notes (will show on Order History):');
define('MODULE_PAYMENT_CARDSTREAM_REFUND_DEFAULT_MESSAGE', 'Refund Issued');
define('MODULE_PAYMENT_CARDSTREAM_REFUND_SUFFIX', 'You may refund an order up to the amount already captured.<br />Refunds cannot be issued if the card has expired. To refund an expired card, issue a credit using the merchant terminal instead.');



define('MODULE_PAYMENT_CARDSTREAM_CAPTURE_CONFIRM_ERROR', 'Error: You requested to do a capture but did not check the Confirmation box.');
define('MODULE_PAYMENT_CARDSTREAM_CAPTURE_ERROR', 'Error: You requested to do a capture but we had a unexpected failure.');
define('MODULE_PAYMENT_CARDSTREAM_CAPTURE_BUTTON_TEXT', 'Do Capture');

define('MODULE_PAYMENT_CARDSTREAM_INVALID_CAPTURE_AMOUNT', 'Error: You requested a capture but need to enter an amount.');

define('MODULE_PAYMENT_CARDSTREAM_CAPT_INITIATED', 'Funds Capture initiated. Amount: %s.  Transaction ID: %s - AuthCode: %s');

define('MODULE_PAYMENT_CARDSTREAM_CAPTURE_TITLE', '<strong>Capture Transactions</strong>');
define('MODULE_PAYMENT_CARDSTREAM_CAPTURE', 'You may capture previously-authorized funds here:');
define('MODULE_PAYMENT_CARDSTREAM_CAPTURE_AMOUNT_TEXT', 'Enter the amount to Capture: ');
define('MODULE_PAYMENT_CARDSTREAM_CAPTURE_CONFIRM_CHECK', 'Check this box to confirm your intent: ');
define('MODULE_PAYMENT_CARDSTREAM_CAPTURE_TRANS_ID', 'Enter the original Order Number <em>(ie: <strong>5138-i4wcYM</strong>)</em> : ');
define('MODULE_PAYMENT_CARDSTREAM_CAPTURE_DEFAULT_TEXT', 'enter Order Number');
define('MODULE_PAYMENT_CARDSTREAM_CAPTURE_TEXT_COMMENTS', 'Notes (will show on Order History):');
define('MODULE_PAYMENT_CARDSTREAM_CAPTURE_DEFAULT_MESSAGE', 'Increased charged amount to reflect additional products added to order.');


