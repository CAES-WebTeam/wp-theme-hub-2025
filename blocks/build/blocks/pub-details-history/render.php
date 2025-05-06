<?php

// Get the current post ID
$post_id = get_the_ID();

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

// Get the type from ACF fields
$history = get_field('history', $post_id);

// Status Labels

// Define status labels
$status_labels = [
    1 => 'Unpublished/Removed',
    2 => 'Published',
    4 => 'Published with Minor Revisions',
    5 => 'Published with Major Revisions',
    6 => 'Published with Full Review',
    7 => 'Historic/Archived',
    8 => 'In Review for Minor Revisions',
    9 => 'In Review for Major Revisions',
    10 => 'In Review'
];


// Check if history is set
if ($history) {
    echo '<div ' . $attrs . '>';
    echo '<h2 class="wp-block-heading is-style-caes-hub-section-heading has-x-large-font-size">Status and Revision History</h2>';

    echo '<ul class="is-style-caes-hub-list-none">';
    // Loop through history
    foreach ($history as $item) {
        $status = $item['status'];
        $status_label = $status_labels[$status] ?? '';
        $date = $item['date']; //date field is pre-formatted F j, Y

        // Set status label
        echo '<li>';
        echo $status_label . ' on ' . $date;
        echo '</li>';
    }
    echo '</ul>';
    echo '</div>';
}
