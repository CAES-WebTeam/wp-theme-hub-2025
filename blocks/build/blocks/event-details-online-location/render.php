<?php
// Get the current post ID
$post_id = get_the_ID();

if( !empty(get_field('location_virtual', $post_id)) ):
	$virtual = get_field('location_virtual', $post_id);
endif; 
?>

<h3>Online Location</h3>

<?php echo !empty($virtual) ? '<div class="event-detail-virtual">' . $virtual . '</div>' : ''; ?>
