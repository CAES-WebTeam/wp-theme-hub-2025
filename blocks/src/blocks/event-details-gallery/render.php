<?php
// Get the current post ID
$post_id = get_the_ID();

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

if (!empty(get_field('parking_info', $post_id))):
	$parking = get_field('parking_info', $post_id);
endif;
?>

<h3>Parking Info</h3>

<?php echo !empty($parking) ? '<div ' . $attrs . '>' . $parking . '</div>' : ''; ?>