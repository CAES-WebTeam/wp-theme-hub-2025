<?php
// Get the current post ID
$post_id = get_the_ID();


// Check and set location if event type is CAES
if( !empty(get_field('location_caes_room', $post_id)) AND get_field('event_type', $post_id) == 'CAES' ):
	$location = get_field('location_caes_room', $post_id);
endif;

// Check and set location if event type is Extension
if( !empty(get_field('location_county_office', $post_id)) AND get_field('event_type', $post_id) == 'Extension' ):
	$location = get_field('location_county_office', $post_id);
endif;

// Check and set location details
if( !empty(get_field('location_details', $post_id)) ):
	$details = get_field('location_details', $post_id);
endif;
?>


<h3>Location</h3>
<?php echo !empty($location) ? '<div class="event-detail-location">' . $location . '</div>' : ''; ?>
<?php echo !empty($details) ? '<div class="event-detail-location-details">' . $details . '</div>' : ''; ?>