<?php


// Get author ID
$author_id = isset($GLOBALS['caes_current_user_id']) ? $GLOBALS['caes_current_user_id'] : get_queried_object_id();

// Get first and last name
$phone_number = get_user_meta($author_id, 'phone_number', true);

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

// Echo the phone number
if ($phone_number) {
    echo '<a ' . $attrs . ' href="tel:' . esc_html($phone_number) . '"><span>' . esc_html($phone_number) . '</span></a>';
}

// Get all user meta fields
// $user_meta = get_user_meta($author_id);
// echo '<h3>All User Meta:</h3><pre>';
// print_r($user_meta);
// echo '</pre>';
