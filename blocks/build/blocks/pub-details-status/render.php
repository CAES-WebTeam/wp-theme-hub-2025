<?php

// Get the current post ID
$post_id = get_the_ID();

// Get the history field from ACF
$history = get_field('history', $post_id);

// Define status categories
$published_statuses = [2]; // Published
$revised_statuses = [4, 5]; // Revised
$renewed_statuses = [6]; // Renewed

// Check if history is set and not empty
if ($history) {
    $latest_item = null;
    $latest_timestamp = 0;
    $two_weeks_ago = strtotime('-14 days'); // Timestamp for two weeks ago

    foreach ($history as $item) {
        // Convert date to timestamp
        $date_timestamp = strtotime($item['date']); // ACF stores dates as "F j, Y"

        // Check if date is within the last two weeks
        if ($date_timestamp >= $two_weeks_ago) {
            // Track the latest valid entry
            if ($date_timestamp > $latest_timestamp) {
                $latest_timestamp = $date_timestamp;
                $latest_item = $item;
            }
        }
    }

    // If we found a valid latest item, display different content based on status
    if ($latest_item) {
        // Extract status
        $status = $latest_item['status'];
        $status_class = '';

        if (in_array($status, $published_statuses)) {
            $status_class = 'new';
            $message = "<span>New</span>";
        } elseif (in_array($status, $revised_statuses)) {
            $status_class = 'revised';
            $message = "<span>Revised</span>";
        } elseif (in_array($status, $renewed_statuses)) {
            $status_class = 'renewed';
            $message = "<span>Renewed</span>";
        }

        // Append class to wrapper attributes
        $attrs = $is_preview ? ' ' : get_block_wrapper_attributes(['class' => $status_class]);

        echo '<div ' . $attrs . '>';
        echo $message;
        echo '</div>';
    }
}
?>
