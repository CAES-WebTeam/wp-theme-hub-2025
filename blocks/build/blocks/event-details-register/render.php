<?php

// Get the current post ID
$post_id = get_the_ID();
// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

// Get the current date in 'd/m/Y' format
$today = date('d/m/Y');

// Get the registration start and end dates from ACF fields
$registration_start = get_field('registration_start_date', $post_id);
$registration_end = get_field('registration_end_date', $post_id);
$registration_link = get_field('registration_link', $post_id);

// Convert the dates to DateTime objects for comparison (only if they exist)
$start_date = $registration_start ? DateTime::createFromFormat('d/m/Y', $registration_start) : null;
$end_date = $registration_end ? DateTime::createFromFormat('d/m/Y', $registration_end) : null;
$current_date = DateTime::createFromFormat('d/m/Y', $today);

// Check if today's date is between the start and end dates, OR if no dates are set
$show_registration = false;

if ( !$registration_start && !$registration_end ) {
    // No dates set - assume registration is always open
    $show_registration = true;
} elseif ( $start_date && $end_date && $current_date >= $start_date && $current_date <= $end_date ) {
    // Within date range
    $show_registration = true;
}

if ( $show_registration ) {
    // Display the button if the registration link is set
    if ( $registration_link ) {
        echo '<div ' . $attrs . '>';
        echo '<a href="' . esc_url( $registration_link ) . '" class="event-registration-button" target="outside">Register</a>';
        echo '</div>';
    }
}
?>