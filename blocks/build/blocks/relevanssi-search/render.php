<?php

/**
 * PHP: Server-side rendering for the Relevanssi Search Filters block.
 *
 * @package caes-hub
 */

// Get block attributes.
$show_date_sort        = $attributes['showDateSort'] ?? true;
$show_post_type_filter = $attributes['showPostTypeFilter'] ?? true;
$show_topic_filter     = $attributes['showTopicFilter'] ?? true;
$allowed_post_types    = $attributes['postTypes'] ?? array();
$taxonomy_slug         = $attributes['taxonomySlug'] ?? 'category';

// Get current search query and filter parameters from the URL.
$current_search_query = get_search_query();
$current_orderby      = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : '';
$current_order        = isset($_GET['order']) ? sanitize_text_field(wp_unslash($_GET['order'])) : '';

// For topics, handle multiple selections. The URL parameter will be comma-separated.
$current_topic_terms = isset($_GET[$taxonomy_slug]) ? explode(',', sanitize_text_field(wp_unslash($_GET[$taxonomy_slug]))) : array();

// Handle post_type parameter - ignore 'post' if it's from a default WordPress search
$current_post_type = isset($_GET['post_type']) ? sanitize_text_field(wp_unslash($_GET['post_type'])) : '';
// If post_type is 'post' and no other filters are active, treat it as "All Content Types"
if ($current_post_type === 'post' && empty($current_orderby) && empty($current_topic_terms)) {
	$current_post_type = '';
}

// Determine if this is an AJAX request for search results.
$is_ajax_request = defined('DOING_AJAX') && DOING_AJAX;

// Build the block's HTML attributes.
$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'caes-hub-relevanssi-search',
	)
);

// If it's an AJAX request, just output the results and exit.
// The `caes_hub_render_relevanssi_search_results` function is now in functions.php.
if ($is_ajax_request && isset($_POST['action']) && $_POST['action'] === 'caes_hub_search_results') {
	// This block is now largely redundant as the AJAX handling is in functions.php.
	// However, if you had other block-specific AJAX logic here, it would remain.
	// For this setup, the AJAX request is fully handled by the `caes_hub_handle_relevanssi_ajax_search` function.
	// This `if` block can be removed if `render.php` is only for initial render.
	// Keeping it for now to show the original intent, but the actual work is in plugin-overrides/relevanssi-search.php.
	wp_die(); // Exit to prevent further block rendering on AJAX calls.
}

// For regular page load, render the full block.
?>

<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
	data-taxonomy-slug="<?php echo esc_attr($taxonomy_slug); ?>"
	data-allowed-post-types="<?php echo esc_attr(json_encode($allowed_post_types)); ?>">
	<script>
		window.caesHubAjax = {
			ajaxurl: '<?php echo admin_url('admin-ajax.php'); ?>'
		};
	</script>
	<?php if (! empty($current_search_query)) : ?>
		<h1 class="search-results-title"><?php printf(esc_html__('Search results for: %s', 'caes-hub'), esc_html($current_search_query)); ?></h1>
	<?php endif; ?>
	<form role="search" method="get" class="relevanssi-search-form" action="<?php echo esc_url(home_url('/')); ?>">
		<div class="search-input-group">
			<label for="relevanssi-search-input" class="sr-only"><?php esc_html_e('Search', 'caes-hub'); ?></label>
			<input type="search" id="relevanssi-search-input" class="search-field" placeholder="<?php esc_attr_e('Search...', 'caes-hub'); ?>" value="<?php echo esc_attr($current_search_query); ?>" name="s" />
			<button type="submit" class="search-submit">
				<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20">
					<circle cx="11" cy="11" r="8" />
					<path d="m21 21-4.35-4.35" />
				</svg>
				<span class="sr-only"><?php esc_html_e('Search', 'caes-hub'); ?></span>
			</button>
		</div>

		<div class="search-filters-group">
			<?php if ($show_date_sort) : ?>
				<div class="filter-item date-sort-filter">
					<label for="relevanssi-sort-by-date" class="sr-only"><?php esc_html_e('Sort by Date', 'caes-hub'); ?></label>
					<select name="orderby" id="relevanssi-sort-by-date">
						<option value="relevance" <?php echo ($current_orderby === 'relevance' || (empty($current_orderby) && empty($current_order))) ? 'selected' : ''; ?>><?php esc_html_e('Sort by Relevance', 'caes-hub'); ?></option>
						<option value="post_date_desc" <?php echo ($current_orderby === 'post_date' && $current_order === 'desc') ? 'selected' : ''; ?>><?php esc_html_e('Newest First', 'caes-hub'); ?></option>
						<option value="post_date_asc" <?php echo ($current_orderby === 'post_date' && $current_order === 'asc') ? 'selected' : ''; ?>><?php esc_html_e('Oldest First', 'caes-hub'); ?></option>
					</select>
				</div>
			<?php endif; ?>

			<?php if ($show_post_type_filter && ! empty($allowed_post_types)) : ?>
				<div class="filter-item post-type-filter">
					<label for="relevanssi-post-type-filter" class="sr-only"><?php esc_html_e('Filter by Post Type', 'caes-hub'); ?></label>
					<select name="post_type" id="relevanssi-post-type-filter">
						<option value="" <?php selected($current_post_type, ''); ?>><?php esc_html_e('All', 'caes-hub'); ?></option>
						<?php
						foreach ($allowed_post_types as $pt_slug) {
							// Skip "Shorthand Story" post type entirely
							if ($pt_slug === 'shorthand_story') {
								continue;
							}

							$post_type_obj = get_post_type_object($pt_slug);
							if ($post_type_obj && $post_type_obj->public) {
								// Custom labels for specific post types
								$display_label = $post_type_obj->labels->singular_name;
								if ($pt_slug === 'publications' || $pt_slug === 'publication') {
									$display_label = 'Expert Resources';
								} elseif ($pt_slug === 'post') {
									$display_label = 'Stories';
								}

								printf(
									'<option value="%s" %s>%s</option>',
									esc_attr($pt_slug),
									selected($current_post_type, $pt_slug, false),
									esc_html($display_label)
								);
							}
						}
						?>
					</select>
				</div>
			<?php endif; ?>

			<?php if ($show_topic_filter) : ?>
				<div class="filter-item topic-filter">
					<button type="button" class="open-topics-modal" aria-haspopup="dialog" aria-controls="topics-modal" aria-label="<?php esc_attr_e('Open Topics Filter', 'caes-hub'); ?>">
						<?php esc_html_e('Filter by Topics', 'caes-hub'); ?>
					</button>

					<div id="topics-modal" class="topics-modal-overlay" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="topics-modal-title">
						<div class="topics-modal-content">
							<div class="topics-modal-header">
								<h2 id="topics-modal-title"><?php esc_html_e('Select Topics', 'caes-hub'); ?></h2>
								<button type="button" class="topics-modal-close" aria-label="<?php esc_attr_e('Close Topics Filter', 'caes-hub'); ?>">
									<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" fill="currentColor" width="20" height="20">
										<path d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z" />
									</svg>
								</button>
							</div>
							<div class="topics-modal-body">
								<label for="topics-modal-search-input" class="sr-only"><?php esc_html_e('Search Topics', 'caes-hub'); ?></label>
								<input type="search" id="topics-modal-search-input" class="topics-modal-search-input" placeholder="<?php esc_attr_e('Search topics...', 'caes-hub'); ?>" aria-controls="topics-modal-checkboxes-list">
								<div class="topics-modal-checkboxes" id="topics-modal-checkboxes-list">
									<?php
									$terms = get_terms(
										array(
											'taxonomy'   => $taxonomy_slug,
											'hide_empty' => true,
										)
									);

									if (! empty($terms) && ! is_wp_error($terms)) {
										// Add an "All Topics" option.
										printf(
											'<label><input type="checkbox" name="%1$s[]" value="" %2$s> %3$s</label>',
											esc_attr($taxonomy_slug),
											empty($current_topic_terms) ? 'checked' : '', // Check "All Topics" if no specific topics are selected.
											esc_html__('All Topics', 'caes-hub')
										);

										foreach ($terms as $term) {
											printf(
												'<label><input type="checkbox" name="%1$s[]" value="%2$s" %3$s> %4$s</label>',
												esc_attr($taxonomy_slug),
												esc_attr($term->slug),
												in_array($term->slug, $current_topic_terms, true) ? 'checked' : '',
												esc_html($term->name)
											);
										}
									} else {
										echo '<p>' . esc_html__('No topics found.', 'caes-hub') . '</p>';
									}
									?>
								</div>
							</div>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</form>
	<div class="selected-topic-filters" role="region" aria-label="Selected filters" aria-live="polite">
	</div>

	<?php // This is where the initial search results will load and where AJAX will inject new results.
	?>
	<div class="relevanssi-ajax-search-results-container">
		<?php
		// Render initial search results when the page loads.
		// The function is now defined in functions.php.
		// Add this temporarily to debug
		// error_log('DEBUG: current_post_type = ' . $current_post_type);
		// error_log('DEBUG: allowed_post_types = ' . print_r($allowed_post_types, true));

		echo caes_hub_render_relevanssi_search_results($current_search_query, $current_orderby, $current_order, $current_post_type, $taxonomy_slug, $current_topic_terms, 1, $allowed_post_types); // Pass allowed_post_types
		?>
	</div>

</div>