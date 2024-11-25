<?php
// Get the current post ID
$post_id = get_the_ID();

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
?>

<?php echo !empty($date) ? '<div class="event-detail-date">' . $date . '</div>' : ''; ?>