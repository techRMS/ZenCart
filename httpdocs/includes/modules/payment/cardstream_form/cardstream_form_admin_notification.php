<?php
// File protection as per Zen-Cart suggestions
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}
// EOF: File protection

// Only output the CardStream Transaction information if it was recorded for this order!
if (isset($cardstream_form_transaction_info->fields)) {

// Strip slashes in case they were added to handle apostrophes:
    foreach ($cardstream_form_transaction_info->fields as $key => $value) {
        $cardstream_form_transaction_info->fields[$key] = stripslashes($value);
    }

// Display all CardStream Form status fields (in admin Orders page):
    $output = '<td><table>' . "\n";
    $output .= '<tr style="background-color : #cccccc; border-style : dotted;">' . "\n";
    $output .= '<td valign="top"><strong>CardStream Info:</strong><table>' . "\n";

    $output .= '<tr><td class="main">' . "\n";
    $output .= "Transaction Unique Code\n";
    $output .= '</td><td class="main">' . "\n";
    $output .= $cardstream_form_transaction_info->fields['transid'] . "\n";
    $output .= '</td></tr>' . "\n";

    $output .= '<tr><td class="main">' . "\n";
    $output .= "Amount Received\n";
    $output .= '</td><td class="main">' . "\n";
    $output .= substr($cardstream_form_transaction_info->fields['received'], 0, -2) . "." . substr($cardstream_form_transaction_info->fields['received'], strlen($cardstream_form_transaction_info->fields['received']) - 2, strlen($cardstream_form_transaction_info->fields['received'])) . "\n";
    $output .= '</td></tr>' . "\n";

    $output .= '</table></td>' . "\n";

    $output .= '</tr>' . "\n";
    $output .= '</table></td>' . "\n";
}
