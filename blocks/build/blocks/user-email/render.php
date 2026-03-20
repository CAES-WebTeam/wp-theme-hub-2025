<?php

// Get author ID
$author_id = isset($GLOBALS['caes_current_user_id']) ? $GLOBALS['caes_current_user_id'] : get_queried_object_id();

// Try CPT post first, fall back to user meta
$person_post_id = function_exists('resolve_person_post_id') ? resolve_person_post_id($author_id) : null;

if ($person_post_id) {
    $email = get_post_meta($person_post_id, 'uga_email', true);
} else {
    $email = get_field('field_uga_email_custom', 'user_' . $author_id);
    if (empty($email)) {
        $email = get_the_author_meta('user_email', $author_id);
    }
}

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

// Check if email is valid and doesn't contain problematic strings
if ($email && !strpos($email, 'placeholder')) {
    // Check if email domain contains "spoofed"
    $email_parts = explode('@', $email);
    if (count($email_parts) === 2) {
        $domain = $email_parts[1];
        // If domain doesn't contain "spoofed", display the email
        if (strpos($domain, 'spoofed') === false) {
            echo '<a ' . $attrs . ' href="mailto:' . esc_html($email) . '"><span>' . esc_html($email) . '</span></a>';
        }
    }
}

?>