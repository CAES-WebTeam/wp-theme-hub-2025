<?php
// Get the current post ID
$post_id = get_the_ID();
$fontSize = isset($block['headingFontSize']) && !empty($block['headingFontSize']) ? esc_attr($block['headingFontSize']) : '';
$fontUnit = isset($block['headingFontUnit']) ? esc_attr($block['headingFontUnit']) : 'px';

// Generate inline style if font size is set
$style = $fontSize ? ' style="font-size: ' . $fontSize . $fontUnit . ';"' : '';

echo '<div ' . get_block_wrapper_attributes() . '>';
echo '<h3 class="event-details-title"' . $style . '>Contact</h3>';

// Check if contact_type exists
$contact_type = get_field( 'contact_type', $post_id );

if ( $contact_type ) {

    // Check if contact type is 'default' or 'custom'
    if ( $contact_type === 'default' ) {

        // Get the user field (ACF user field returns an array)
        $user = get_field( 'contact', $post_id );

        // Ensure a user is selected
        if ( $user ) {
            $user_name  = $user['display_name'];
            
            // Try to get phone from ACF field first
            $user_phone = get_field('field_67d99c97cfca5', 'user_' . $user['ID']);
            
            // If ACF field is empty, fall back to user meta
            if (empty($user_phone)) {
                $user_phone = get_user_meta( $user['ID'], 'phone', true );
            }
            
            // Try to get email from ACF field first
            $user_email = get_field('field_uga_email_custom', 'user_' . $user['ID']);
            
            // If ACF field is empty, fall back to user email
            if (empty($user_email)) {
                $user_email = $user['user_email'];
            }

            // Display the default contact information
            echo '<div class="event-details-content">';
            echo esc_html( $user_name ) . '<br>';
            
            // Check if phone is valid and doesn't contain problematic strings
            if ($user_phone && !strpos($user_phone, 'placeholder')) {
                echo esc_html( $user_phone ) . '<br>';
            }
            
            // Check if email is valid and doesn't contain problematic strings
            if ($user_email && !strpos($user_email, 'placeholder')) {
                // Check if email domain contains "spoofed"
                $email_parts = explode('@', $user_email);
                if (count($email_parts) === 2) {
                    $domain = $email_parts[1];
                    // If domain doesn't contain "spoofed", display the email
                    if (strpos($domain, 'spoofed') === false) {
                        echo '<a href="mailto:' . esc_attr( $user_email ) . '">' . esc_html( $user_email ) . '</a>';
                    }
                }
            }
            echo '</div>';
        }

    } elseif ( $contact_type === 'custom' ) {

        // Get custom contact fields
        $custom_contact = get_field( 'custom_contact', $post_id );

        // Ensure custom contact fields are set
        if ( $custom_contact ) {
            $custom_name  = $custom_contact['contact_name'];
            $custom_email = $custom_contact['contact_email'];
            $custom_phone = $custom_contact['contact_phone'];

            // Display the custom contact information
            echo '<div class="event-details-content">';
            echo esc_html( $custom_name ) . '<br>';
            
            // Check if phone is valid and doesn't contain problematic strings
            if ($custom_phone && !strpos($custom_phone, 'placeholder')) {
                echo esc_html( $custom_phone ) . '<br>';
            }
            
            // Check if email is valid and doesn't contain problematic strings
            if ($custom_email && !strpos($custom_email, 'placeholder')) {
                // Check if email domain contains "spoofed"
                $email_parts = explode('@', $custom_email);
                if (count($email_parts) === 2) {
                    $domain = $email_parts[1];
                    // If domain doesn't contain "spoofed", display the email
                    if (strpos($domain, 'spoofed') === false) {
                        echo '<a href="mailto:' . esc_attr( $custom_email ) . '">' . esc_html( $custom_email ) . '</a>';
                    }
                }
            }
            echo '</div>';
        }
    }
}
echo '</div>';
?>