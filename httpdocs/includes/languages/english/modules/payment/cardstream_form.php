<?php
// File protection as per Zen-Cart suggestions
	if (!defined('IS_ADMIN_FLAG')) {
	  die('Illegal Access');
	}
// EOF: File protection

define('MODULE_PAYMENT_CARDSTREAM_ADMIN_TITLE', 'CardStream Hosted Form V1');
define('MODULE_PAYMENT_CARDSTREAM_ADMIN_DESCRIPTION', '<a target="_blank" href="https://www.cardstream.com/signup?ref=zen-cart">Click Here to Sign Up for an Account</a><br /><br /><a target="_blank" href="https://mms.cardstream.com/admin">Click to Login to the CardStream Merchant Area</a><br /><br /><strong>Requirements:</strong><br /><hr />*<strong>CardStream Account</strong> (see link above to signup)<br />*<strong>CardStream MerchantID</strong> available from your Merchant Area');