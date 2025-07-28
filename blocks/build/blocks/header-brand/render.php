<div <?php echo get_block_wrapper_attributes(); ?>>
	<a href="/">
		<?php echo '<img loading="lazy" class="caes-hub-header-brand-logo" src="' . esc_url(get_template_directory_uri() . '/assets/images/caes-logo.png') . '" alt="UGA College of Agricultural &amp; Environmental Sciences" /> ' ?>
		<?php
		// Check if the current page is the homepage
		if (is_front_page() || is_home()) {
			echo '<h1 style="margin:0" class="caes-hub-header-brand-text">Field Report</h1>';
		} else {
			echo '<span class="caes-hub-header-brand-text">Field Report</span>';
		}
		?>
	</a>
	<div class="caes-hub-header-search caes-hub-header-search--desktop">
		<?php get_search_form(); ?>
	</div>
</div>