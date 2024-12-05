<?php
// Get the current post ID
$post_id = get_the_ID();

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

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

<?php 
if (!empty($location) || !empty($details)) {
    echo '<div ' . $attrs . '>';
    echo '<h3 class="event-details-title">Location</h3>';
    echo '<div class="event-details-content">';
    if (!empty($location)) {
        echo $location;
    }
    if (!empty($location) && !empty($details)) {
        echo '<br />';
    }
    if (!empty($details)) {
        echo $details;
    }
    echo '</div>'; // Close event-details-content
    echo '</div>'; // Close wrapper
}
?>