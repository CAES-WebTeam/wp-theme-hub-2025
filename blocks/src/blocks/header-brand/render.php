<?php

/**
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */
?>

<div <?php echo get_block_wrapper_attributes(); ?>>
	<a href="/">
		<?php echo '<img loading="lazy" class="caes-hub-header-brand-logo" src="' . esc_url(get_template_directory_uri() . '/assets/images/caes-logo.png') . '" alt="UGA College of Agricultural &amp; Environmental Sciences" /> ' ?>
		<span class="caes-hub-header-brand-text">Field Report</span>
	</a>
	<div class="caes-hub-header-search caes-hub-header-search--desktop">
		<?php get_search_form(); ?>
	</div>
</div>