<?php

/**
 * Format phone number to 000-000-0000 format
 * Handles formats like: 0000000000, (000) 000-0000, 000.000.0000, etc.
 * @param string $phone_number Phone number in various formats
 * @return string Formatted phone number as 000-000-0000
 */
if (!function_exists('format_phone_number')) {
    function format_phone_number($phone_number) {
        // Remove all non-numeric characters (parentheses, spaces, dashes, dots, etc.)
        $cleaned = preg_replace('/[^0-9]/', '', $phone_number);
        
        // Check if we have exactly 10 digits
        if (strlen($cleaned) === 10) {
            return substr($cleaned, 0, 3) . '-' . substr($cleaned, 3, 3) . '-' . substr($cleaned, 6, 4);
        }
        
        // Handle 11 digits starting with 1 (US country code)
        if (strlen($cleaned) === 11 && substr($cleaned, 0, 1) === '1') {
            $cleaned = substr($cleaned, 1); // Remove the leading 1
            return substr($cleaned, 0, 3) . '-' . substr($cleaned, 3, 3) . '-' . substr($cleaned, 6, 4);
        }
        
        // Return original if we can't format it (invalid length)
        return $phone_number;
    }
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
    
    // Clean the number for the tel: link (remove all non-numeric characters)
    $cleaned_for_tel = preg_replace('/[^0-9]/', '', $phone_number);
    
    echo '<a ' . $attrs . ' href="tel:' . esc_html($cleaned_for_tel) . '"><span>' . esc_html($formatted_phone) . '</span></a>';
}

?>