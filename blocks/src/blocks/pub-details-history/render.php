<?php

// Get the current post ID
$post_id = get_the_ID();

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

// Get the type from ACF fields
$history = get_field('history', $post_id);


// Check if history is set
if ( $history ) {
    echo '<div ' . $attrs . '>';
    echo '<p><strong>Status and Revision History</strong></p>';

	// Loop through history
    foreach ( $history as $item ) {
        $status = $item['status'];
        $date = $item['date']; //date field is pre-formatted F j, Y
        echo '<div>';
        echo $status.' on '.$date;
        echo '</div>';
    }
    echo '</div>';
}
?>
