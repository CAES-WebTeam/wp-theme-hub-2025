<?php
// Get the current post ID
$post_id = get_the_ID();

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

if( !empty(get_field('description', $post_id)) ):
	$description = get_field('description', $post_id);
endif; 
?>

<?php echo !empty($description) ? '<div ' . $attrs . '>' . $description . '</div>' : ''; ?>
