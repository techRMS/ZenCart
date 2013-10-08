<?php
// File protection as per Zen-Cart suggestions
	if (!defined('IS_ADMIN_FLAG')) {
	  die('Illegal Access');
	}
// EOF: File protection

define('MODULE_PAYMENT_CARDSTREAM_ADMIN_TITLE', 'CardStream Integration');
define('MODULE_PAYMENT_CARDSTREAM_ADMIN_DESCRIPTION', '<a target=\"_blank\" href=\"https://www.cardstream.com?ref=zen-cart\"><img style=\"float:right;margin-right:8px;\" src=\"https://www.cardstream.com/images/logo.png?ref=zen-cart\"/></a> <br/><a target="_blank" href="https://www.cardstream.com/signup?ref=zen-cart">Click Here to Sign Up for an Account</a><br /><br /><a target="_blank" href="https://mms.cardstream.com/admin?ref=zen-cart">Login to the CardStream Merchant Area</a><br /><br /><strong>Requirements:</strong><br /><hr />*<strong>CardStream Account</strong> (see link above to signup)<br />*<strong>CardStream MerchantID</strong> available from your Merchant Area<br/> *<strong>CardStream Merchant Password</strong> set in mms &amp; required for zen-cart');
define('MODULE_PAYMENT_CARDSTREAM_CARD_HOLDER', 'Name as shown on card');
define('MODULE_PAYMENT_CARDSTREAM_CARD_NUMBER', 'Card Number');
define('MODULE_PAYMENT_CARDSTREAM_CARD_EXPIRE', 'Card Expires');
define('MODULE_PAYMENT_CARDSTREAM_CARD_CVV', 'Card Verification Number');
define('MODULE_PAYMENT_CARDSTREAM_CARD_CVV_HELP', 'What is a CVV?');
