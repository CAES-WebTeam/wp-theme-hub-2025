<div <?php echo get_block_wrapper_attributes(); ?>>
	<div class="logo-wrapper">
		<?php echo '<a href="https://www.caes.uga.edu"><img loading="lazy" class="caes-hub-header-brand-logo" src="' . esc_url(get_template_directory_uri() . '/assets/images/caes-logo.png') . '" alt="UGA College of Agricultural &amp; Environmental Sciences" /></a> ' ?>

		<?php
		// Check if the current page is the homepage
		if (is_front_page() || is_home()) {
			echo '<a href="/"><h1 style="margin:0" class="caes-hub-header-brand-text">Field Report</h1></a>';
		} else {
			echo '<a href="/"><span class="caes-hub-header-brand-text">Field Report</span</a>';
		}
		?>
	</div>
	<div class="caes-hub-header-search caes-hub-header-search--desktop">
		<?php get_search_form(); ?>
	</div>
</div>