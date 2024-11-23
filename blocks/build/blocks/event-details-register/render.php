<?php
// Get the current post ID
$post_id = get_the_ID();

// Get the current date in 'd/m/Y' format
$today = date('d/m/Y');

// Get the registration start and end dates from ACF fields
$registration_start = get_field('registration_start_date', $post_id);
$registration_end = get_field('registration_end_date', $post_id);

// Convert the dates to DateTime objects for comparison
$start_date = DateTime::createFromFormat('d/m/Y', $registration_start);
$end_date = DateTime::createFromFormat('d/m/Y', $registration_end);
$current_date = DateTime::createFromFormat('d/m/Y', $today);

// Check if today's date is between the start and end dates
if ( $start_date && $end_date && $current_date >= $start_date && $current_date <= $end_date ) {

    // Get the registration link from ACF
    $registration_link = get_field('registration_link', $post_id);

    // Display the button if the registration link is set
    if ( $registration_link ) {
        echo '<a href="' . esc_url( $registration_link ) . '" class="event-registration-button" target="outside">Register Now</a>';
    }
}
?>
