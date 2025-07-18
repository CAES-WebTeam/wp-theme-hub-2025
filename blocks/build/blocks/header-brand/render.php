<?php

function caes_hub_get_search_form_without_live() {
    // Disable Relevanssi live search hijack filter just for this call
    add_filter( 'relevanssi_live_search_hijack_get_search_form', '__return_false' );
    get_search_form();
    remove_filter( 'relevanssi_live_search_hijack_get_search_form', '__return_false' );
}

?>

<div <?php echo get_block_wrapper_attributes(); ?>>
	<a href="/">
		<?php echo '<img loading="lazy" class="caes-hub-header-brand-logo" src="' . esc_url(get_template_directory_uri() . '/assets/images/caes-logo.png') . '" alt="UGA College of Agricultural &amp; Environmental Sciences" /> ' ?>
		<span class="caes-hub-header-brand-text">Field Report</span>
	</a>
	<div class="caes-hub-header-search caes-hub-header-search--desktop">
		<?php caes_hub_get_search_form_without_live(); ?>
	</div>
</div>
