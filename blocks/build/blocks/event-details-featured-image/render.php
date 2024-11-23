<?php
// Get the current post ID
$post_id = get_the_ID();

if( !empty(get_field('featured_image', $post_id)) ):
	$featured_image = get_field('featured_image', $post_id);
endif; 
?>



<div class="event-featured-image" style="background:#f2f2f2;">
	<canvas width="800" height="550"></canvas>
	<?php if( !empty($featured_image) ): ?><img src="<?php echo $featured_image['url']; ?>" alt="<?php echo $featured_image['alt']; ?>" /><?php endif; ?>
</div>

