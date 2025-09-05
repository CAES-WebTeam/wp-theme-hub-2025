<div <?php echo get_block_wrapper_attributes(); ?>>
	<div class="logo-wrapper">
		<?php echo '<a href="https://www.caes.uga.edu"><img loading="lazy" class="caes-hub-header-brand-logo" src="' . esc_url(get_template_directory_uri() . '/assets/images/caes-logo.png') . '" alt="UGA College of Agricultural &amp; Environmental Sciences" /></a> '; ?>

		<?php
		// Check if the current page is the homepage
		if (is_front_page() || is_home()) {
			echo '<a href="/"><h1 style="margin:0" class="caes-hub-header-brand-text">Field Report</h1></a>';
		} else {
			echo '<a href="/"><span class="caes-hub-header-brand-text">Field Report</span></a>';
		}
		?>
	</div>
	<div class="caes-hub-header-search caes-hub-header-search--desktop">
		<?php
        // --- START: CUSTOM SEARCH FORM ---
        // This form's action is set to the site's root URL to produce the correct search URL structure.
        $search_submit_url = esc_url(home_url('/'));
        ?>
        <form role="search" method="get" class="caes-hub-form__input-button-container" action="<?php echo $search_submit_url; ?>">
            <label for="header-search-input-final">
                <span class="screen-reader-text">Search for:</span>
            </label>
            
            <input type="search" id="header-search-input-final" class="caes-hub-form__input" placeholder="Search …" value="<?php echo get_search_query(); ?>" name="s" />

            <input type="hidden" name="paged" value="1">
        </form>
        <?php // --- END: CUSTOM SEARCH FORM --- ?>
	</div>
</div>