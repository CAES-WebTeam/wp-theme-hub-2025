<?php
// Get the current post ID
$post_id = get_the_ID();

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

if( !empty(get_field('featured_image', $post_id)) ):
	$featured_image = get_field('featured_image', $post_id);
endif; 
?>



<?php echo '<div ' . $attrs . '>';?>
	<?php if( !empty($featured_image) ): ?><img src="<?php echo $featured_image['url']; ?>" alt="<?php echo $featured_image['alt']; ?>" /><?php endif; ?>
<?php echo '</div>'; ?>

