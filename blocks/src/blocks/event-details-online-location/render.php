<?php
// Get the current post ID
$post_id = get_the_ID();
// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

if( !empty(get_field('location_virtual', $post_id)) ):
	$virtual = get_field('location_virtual', $post_id);
endif; 
?>

<?php 
if (!empty($virtual)) {
    echo '<div ' . $attrs . '>';
    echo '<h3 class="event-details-title">Online Location</h3>';
    echo '<div class="event-details-content">';
    echo $virtual;
    echo '</div>'; // Close event-details-content
    echo '</div>'; // Close wrapper
}
?>