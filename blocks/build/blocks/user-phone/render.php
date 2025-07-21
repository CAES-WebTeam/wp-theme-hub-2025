<?php

/**
 * Format phone number to 000-000-0000 format
 * @param string $phone_number Raw phone number
 * @return string|false Formatted phone number or false if invalid
 */
function format_phone_number($phone_number) {
    // Remove all non-numeric characters
    $cleaned = preg_replace('/[^0-9]/', '', $phone_number);
    
    // Check if we have exactly 10 digits (US phone number)
    if (strlen($cleaned) === 10) {
        // Format as 000-000-0000
        return substr($cleaned, 0, 3) . '-' . substr($cleaned, 3, 3) . '-' . substr($cleaned, 6, 4);
    }
    
    // Handle 11 digits (with country code 1)
    if (strlen($cleaned) === 11 && substr($cleaned, 0, 1) === '1') {
        // Remove the leading 1 and format
        $cleaned = substr($cleaned, 1);
        return substr($cleaned, 0, 3) . '-' . substr($cleaned, 3, 3) . '-' . substr($cleaned, 6, 4);
    }
    
    // Return false for invalid phone numbers
    return false;
}

// Get author ID
$author_id = isset($GLOBALS['caes_current_user_id']) ? $GLOBALS['caes_current_user_id'] : get_queried_object_id();

// Get phone number
$phone_number = get_user_meta($author_id, 'phone_number', true);

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

// Format and echo the phone number
if ($phone_number) {
    $formatted_phone = format_phone_number($phone_number);
    
    if ($formatted_phone) {
        // Use the raw cleaned number for the tel: link (no dashes)
        $cleaned_for_tel = preg_replace('/[^0-9]/', '', $phone_number);
        echo '<a ' . $attrs . ' href="tel:' . esc_html($cleaned_for_tel) . '"><span>' . esc_html($formatted_phone) . '</span></a>';
    } else {
        // If formatting fails, you might want to display the original or show an error
        // Option 1: Display original
        echo '<span ' . $attrs . '>' . esc_html($phone_number) . '</span>';
        
        // Option 2: Don't display anything
        // (just comment out the echo above)
    }
}

?>