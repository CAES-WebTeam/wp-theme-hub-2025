<?php

/**
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */
?>

<?php
$version =  $attributes['version'];
$customWidth =  $attributes['customWidth'];
?>

<div <?php echo get_block_wrapper_attributes(); ?> style="width: <?php echo esc_attr($customWidth); ?>;">
	<?php 
	$image = '';
	if ($version === 'dark') {
		$image = get_template_directory_uri() . '/assets/images/caes-logo-horizontal.png';
	} elseif ($version === 'light') {
		$image = get_template_directory_uri() . '/assets/images/caes-logo-horizontal-cw.png';
	}
	if ($image): ?>
		<img loading="lazy" class="caes-hub-content-logo" src="<?php echo esc_url($image); ?>" alt="UGA College of Agricultural &amp; Environmental Sciences" />
	<?php endif; ?>
</div>