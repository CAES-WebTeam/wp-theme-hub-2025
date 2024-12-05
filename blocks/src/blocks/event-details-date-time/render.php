<?php
// Get the current post ID
$post_id = get_the_ID();

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

// Set Start Date
if( !empty(get_field('start_date', $post_id)) ):
	$date_object = DateTime::createFromFormat('Ymd', get_field('start_date', $post_id));
	$formatted_date = $date_object ? $date_object->format('F j, Y') : 'Invalid date format';
	$date = $formatted_date;
endif;

// Set End Date
if( !empty(get_field('end_date', $post_id)) ):
	$date_object = DateTime::createFromFormat('Ymd', get_field('end_date', $post_id));
	$formatted_date = $date_object ? $date_object->format('F j, Y') : 'Invalid date format';
	$date = $date.'-'.$formatted_date;
endif;

// Set Start Time
if( !empty(get_field('start_time', $post_id)) ):
	$time = get_field('start_time', $post_id);
endif;

// Set End Time
if( !empty(get_field('end_time', $post_id)) ):
	$time = $time.'-'.get_field('end_time', $post_id);
endif;
?>


<?php 
if (!empty($date) || !empty($time)) { 
    echo '<div ' . $attrs . '>';
    echo '<h3 class="event-details-title">Date & Time</h3>';
    echo '<div class="event-details-content">';
    if (!empty($date)) {
        echo $date;
    }
    if (!empty($date) && !empty($time)) {
        echo '<br />';
    }
    if (!empty($time)) {
        echo $time;
    }
    echo '</div>';
    echo '</div>';
} 
?>