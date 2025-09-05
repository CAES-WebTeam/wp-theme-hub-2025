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
        // This form includes hidden fields to pass default search parameters.
        $search_page_url = esc_url(site_url('/search/'));
        ?>
        <form role="search" method="get" class="search-form-header" action="<?php echo $search_page_url; ?>">
            <label for="header-search-input-with-params">
                <span class="screen-reader-text">Search for:</span>
            </label>
            
            <input type="search" id="header-search-input-with-params" class="search-field" placeholder="Search …" value="<?php echo get_search_query(); ?>" name="s" />

            <input type="hidden" name="paged" value="1">

            <button type="submit" class="search-submit">
                <span class="screen-reader-text">Search</span>
                <?php // SVG icon can go here ?>
            </button>
        </form>
        <?php // --- END: CUSTOM SEARCH FORM --- ?>
	</div>
</div>