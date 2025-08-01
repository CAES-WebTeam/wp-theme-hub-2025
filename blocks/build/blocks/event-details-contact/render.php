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
            $user_email = $user['user_email'];
            $user_phone = get_user_meta( $user['ID'], 'phone', true ); // Assuming phone is stored in user meta

            // Display the default contact information
            echo '<div class="event-details-content">';
            echo esc_html( $user_name ) . '<br>';
            if ($user_phone) {
                echo esc_html( $user_phone ) . '<br>';
            }
            if ($user_email) {
                echo '<a href="mailto:' . esc_attr( $user_email ) . '">' . esc_html( $user_email ) . '</a>';
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
            echo esc_html( $custom_phone ) . '<br>';
            echo '<a href="mailto:' . esc_attr( $custom_email ) . '">' . esc_html( $custom_email ) . '</a>';
            echo '</div>';
        }
    }
}
echo '</div>';
?>