<?php
$version =  $attributes['version'];
$customWidth =  $attributes['customWidth'];
?>

<div <?php echo get_block_wrapper_attributes(); ?> style="width: <?php echo esc_attr($customWidth); ?>;">
	<?php
	$image = get_template_directory_uri() . '/assets/images/expert-mark.png';

	if ($image): ?>
		<img loading="lazy" src="<?php echo esc_url($image); ?>" alt="Written and Reviewed by Experts" />
	<?php endif; ?>
</div>