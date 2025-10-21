<?php

// DEBUG: Log all search attempts
if (isset($_GET['s'])) {
    error_log('=== SEARCH DEBUG ===');
    error_log('Original $_GET[s]: "' . $_GET['s'] . '"');
    
    $test_normalize = caes_hub_normalize_search_query($_GET['s']);
    error_log('After normalization: "' . $test_normalize . '"');
    error_log('Function exists: ' . (function_exists('caes_hub_normalize_search_query') ? 'YES' : 'NO'));
}

/**
 * PHP: Server-side rendering for the Relevanssi Search Filters block.
 *
 * @package caes-hub
 */

// Get block attributes.
$show_date_sort        = $attributes['showDateSort'] ?? true;
$show_post_type_filter = $attributes['showPostTypeFilter'] ?? true;
$show_topic_filter     = $attributes['showTopicFilter'] ?? true;
$show_author_filter    = $attributes['showAuthorFilter'] ?? true;
$show_language_filter  = $attributes['showLanguageFilter'] ?? false;
$show_heading          = $attributes['showHeading'] ?? true;
$show_button           = $attributes['showButton'] ?? false;
$button_text           = $attributes['buttonText'] ?? '';
$button_url            = $attributes['buttonUrl'] ?? '';
$allowed_post_types    = $attributes['postTypes'] ?? array();
$taxonomy_slug         = $attributes['taxonomySlug'] ?? 'category';
$results_page_url      = $attributes['resultsPageUrl'] ?? '';

// Determine if this is a search-only block (has resultsPageUrl) or a full results block
$is_search_only_block = !empty($results_page_url);

// Get current search query and filter parameters from the URL.
// Use $_GET directly since get_search_query() might be empty due to our 404 fix
$current_search_query = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : get_search_query();
$current_search_query = caes_hub_normalize_search_query($current_search_query);
$current_orderby      = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : '';
$current_order        = isset($_GET['order']) ? sanitize_text_field(wp_unslash($_GET['order'])) : '';

// For topics, handle multiple selections. The URL parameter will be comma-separated.
$current_topic_terms = isset($_GET[$taxonomy_slug]) ? explode(',', sanitize_text_field(wp_unslash($_GET[$taxonomy_slug]))) : array();

// For language, handle single selection from select dropdown
$current_language = isset($_GET['language']) ? sanitize_text_field(wp_unslash($_GET['language'])) : '';

// Convert pretty language slug to database ID for processing
$current_language_for_query = $current_language;
if (!empty($current_language)) {
	$language_slug_to_id = array(
		'english' => '1',
		'spanish' => '2',
		'chinese' => '3',
		'other' => '4'
	);

	if (isset($language_slug_to_id[$current_language])) {
		$current_language_for_query = $language_slug_to_id[$current_language];
	}
}

// For authors, handle multiple selections using SLUGS instead of IDs for security
$current_author_slugs = array();
if ($show_author_filter && isset($_GET['author_slug'])) {
	$current_author_slugs = explode(',', sanitize_text_field(wp_unslash($_GET['author_slug'])));
	// Validate author slugs
	$current_author_slugs = array_filter($current_author_slugs, function ($slug) {
		return preg_match('/^[a-zA-Z0-9\-_]+$/', $slug);
	});
}

// Convert author slugs to IDs for internal processing
$current_author_ids = array();
if (!empty($current_author_slugs)) {
	foreach ($current_author_slugs as $slug) {
		$user = get_user_by('slug', $slug);
		if ($user) {
			$current_author_ids[] = $user->ID;
		}
	}
}

// Handle post_type parameter - ignore 'post' if it's from a default WordPress search
$current_post_type = isset($_GET['post_type']) ? sanitize_text_field(wp_unslash($_GET['post_type'])) : '';
// If post_type is 'post' and no other filters are active, treat it as "All Content Types"
$active_filters_exist = !empty($current_orderby) || !empty($current_topic_terms) || ($show_author_filter && !empty($current_author_ids)) || !empty($current_language);
if ($current_post_type === 'post' && !$active_filters_exist) {
	$current_post_type = '';
}

// Check if publications post type is relevant for language filter
$is_publications_relevant = ($current_post_type === 'publications' || $current_post_type === 'publication') ||
	(empty($current_post_type) && (in_array('publications', $allowed_post_types) || in_array('publication', $allowed_post_types)));

// Determine if this is an AJAX request for search results.
$is_ajax_request = defined('DOING_AJAX') && DOING_AJAX;

// Check if we should show search results
// Show results if: NOT a search-only block OR there are search parameters in the URL
$should_show_results = !$is_search_only_block || !empty($current_search_query) || $active_filters_exist;

// Build the block's HTML attributes.
$wrapper_classes = ['caes-hub-relevanssi-search'];

// Add special class when it's search-only with button enabled
if ($is_search_only_block && $show_button && !empty($button_text) && !empty($button_url)) {
	$wrapper_classes[] = 'search-only-with-button';
}

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => implode(' ', $wrapper_classes),
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
	data-allowed-post-types="<?php echo esc_attr(json_encode($allowed_post_types)); ?>"
	data-show-language-filter="<?php echo esc_attr($show_language_filter ? 'true' : 'false'); ?>"
	data-show-heading="<?php echo esc_attr($show_heading ? 'true' : 'false'); ?>"
	data-custom-heading="<?php echo esc_attr($attributes['customHeading'] ?? ''); ?>"
	data-results-page-url="<?php echo esc_attr($results_page_url); ?>">
	<script>
		window.caesHubAjax = {
			ajaxurl: '<?php echo admin_url('admin-ajax.php'); ?>'
		};
	</script>
	<?php if ($show_heading) : ?>
		<?php
		// H1 is always just the base heading (no "results for" part)
		$base_heading = !empty($attributes['customHeading']) ? $attributes['customHeading'] : esc_html__('Search', 'caes-hub');
		?>
		<h1 class="search-results-title" style="<?php
												$heading_styles = array();
												if (!empty($attributes['headingColor'])) {
													$heading_styles[] = 'color: ' . esc_attr($attributes['headingColor']);
												}
												if (!empty($attributes['headingAlignment'])) {
													$heading_styles[] = 'text-align: ' . esc_attr($attributes['headingAlignment']);
												}
												echo implode('; ', $heading_styles);
												?>"><?php echo esc_html($base_heading); ?></h1>
	<?php endif; ?>
	
	<?php
	// Determine form action: if resultsPageUrl is specified, use it; otherwise use home URL
	// $form_action = $is_search_only_block ? esc_url($results_page_url) : esc_url(home_url('/'));
	$form_action = $is_search_only_block ? esc_url($results_page_url) : '';
	?>
	<form role="search" method="get" class="relevanssi-search-form" action="<?php echo $form_action; ?>">
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

		<?php if (!$is_search_only_block) : ?>
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
						<label for="relevanssi-post-type-filter" class="sr-only"><?php esc_html_e('Filter by Content', 'caes-hub'); ?></label>
						<select name="post_type" id="relevanssi-post-type-filter">
							<option value="" <?php selected($current_post_type, ''); ?>><?php esc_html_e('All Content', 'caes-hub'); ?></option>
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

				<?php if ($show_language_filter && $is_publications_relevant) : ?>
					<div class="filter-item language-filter">
						<label for="relevanssi-language-filter" class="sr-only"><?php esc_html_e('Filter by Language', 'caes-hub'); ?></label>
						<select name="language" id="relevanssi-language-filter">
							<option value="" <?php selected($current_language, ''); ?>><?php esc_html_e('All Languages', 'caes-hub'); ?></option>
							<?php
							// Language mapping: ID => Label
							$language_id_to_label = array(
								'1' => 'English',
								'2' => 'Spanish',
								'3' => 'Chinese',
								'4' => 'Other'
							);

							// Pretty URL mapping: slug => ID
							$language_slug_to_id = array(
								'english' => '1',
								'spanish' => '2',
								'chinese' => '3',
								'other' => '4'
							);

							// Reverse mapping: ID => slug
							$language_id_to_slug = array_flip($language_slug_to_id);

							// Convert current language from pretty slug to ID if needed
							$current_language_id = $current_language;
							if (isset($language_slug_to_id[$current_language])) {
								$current_language_id = $language_slug_to_id[$current_language];
							}

							// Get available languages from ACF field values for publications
							global $wpdb;
							$language_query = $wpdb->prepare("
								SELECT DISTINCT meta_value 
								FROM {$wpdb->postmeta} pm
								INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
								WHERE pm.meta_key = %s 
								AND pm.meta_value != ''
								AND p.post_status = 'publish'
								AND p.post_type IN ('publications', 'publication')
								ORDER BY pm.meta_value ASC
							", 'language');

							$languages = $wpdb->get_col($language_query);

							if (!empty($languages)) {
								foreach ($languages as $language_id) {
									$language_id = trim($language_id);
									if (!empty($language_id) && isset($language_id_to_label[$language_id])) {
										$language_label = $language_id_to_label[$language_id];
										$language_slug = $language_id_to_slug[$language_id];

										// Use pretty slug as value, but check against current selection
										$is_selected = ($current_language === $language_slug) || ($current_language_id === $language_id);

										printf(
											'<option value="%s" %s>%s</option>',
											esc_attr($language_slug),
											$is_selected ? 'selected' : '',
											esc_html($language_label)
										);
									}
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

				<?php if ($show_author_filter) : ?>
					<div class="filter-item author-filter">
						<button type="button" class="open-authors-modal" aria-haspopup="dialog" aria-controls="authors-modal" aria-label="<?php esc_attr_e('Open Authors Filter', 'caes-hub'); ?>">
							<?php esc_html_e('Filter by Authors', 'caes-hub'); ?>
						</button>

						<div id="authors-modal" class="authors-modal-overlay" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="authors-modal-title">
							<div class="authors-modal-content">
								<div class="authors-modal-header">
									<h2 id="authors-modal-title"><?php esc_html_e('Select Authors', 'caes-hub'); ?></h2>
									<button type="button" class="authors-modal-close" aria-label="<?php esc_attr_e('Close Authors Filter', 'caes-hub'); ?>">
										<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" fill="currentColor" width="20" height="20">
											<path d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z" />
										</svg>
									</button>
								</div>
								<div class="authors-modal-body">
									<label for="authors-modal-search-input" class="sr-only"><?php esc_html_e('Search Authors', 'caes-hub'); ?></label>
									<input type="search" id="authors-modal-search-input" class="authors-modal-search-input" placeholder="<?php esc_attr_e('Search authors...', 'caes-hub'); ?>" aria-controls="authors-modal-checkboxes-list">
									<div class="authors-modal-checkboxes" id="authors-modal-checkboxes-list">
										<?php
										// EMERGENCY FIX: More efficient author loading
										$author_user_ids = [];

										// Use direct SQL to get author IDs without loading full post data
										global $wpdb;
										$author_meta_query = $wpdb->prepare("
				                            SELECT DISTINCT meta_value 
				                            FROM {$wpdb->postmeta} pm
				                            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
				                            WHERE pm.meta_key LIKE %s 
				                            AND pm.meta_value REGEXP '^[0-9]+$'
				                            AND p.post_status = 'publish'
				                            AND p.post_type IN ('" . implode("','", array_map('esc_sql', $allowed_post_types)) . "')
				                            LIMIT 500
				                        ", 'authors_%_user');

										$author_ids_from_db = $wpdb->get_col($author_meta_query);
										if ($author_ids_from_db) {
											$author_user_ids = array_map('intval', $author_ids_from_db);
										}

										// Get unique user IDs and fetch user data
										$unique_author_ids = array_unique($author_user_ids);
										$authors = [];

										foreach ($unique_author_ids as $user_id) {
											$user = get_userdata($user_id);
											if ($user) {
												$authors[] = $user;
											}
										}

										// Sort authors by last name, fallback to display name
										usort($authors, function ($a, $b) {
											$a_name = !empty($a->last_name) ? $a->last_name : $a->display_name;
											$b_name = !empty($b->last_name) ? $b->last_name : $b->display_name;
											return strcasecmp($a_name, $b_name);
										});

										if (! empty($authors)) {
											// Add an "All Authors" option.
											printf(
												'<label><input type="checkbox" name="author_slug[]" value="" %s> %s</label>',
												empty($current_author_slugs) ? 'checked' : '',
												esc_html__('All Authors', 'caes-hub')
											);

											foreach ($authors as $author) {
												// Display name with preference for first_name last_name format
												$display_name = $author->display_name;
												if (!empty($author->first_name) && !empty($author->last_name)) {
													$display_name = $author->first_name . ' ' . $author->last_name;
												}

												// Use user_nicename (slug) instead of ID for security
												printf(
													'<label><input type="checkbox" name="author_slug[]" value="%1$s" %2$s> %3$s</label>',
													esc_attr($author->user_nicename),
													in_array($author->user_nicename, $current_author_slugs, true) ? 'checked' : '',
													esc_html($display_name)
												);
											}
										} else {
											echo '<p>' . esc_html__('No authors found.', 'caes-hub') . '</p>';
										}
										?>
									</div>
								</div>
							</div>
						</div>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</form>
	
	<!-- Custom Button (appears on both search-only and full results blocks) -->
	<?php if ($show_button && !empty($button_text) && !empty($button_url)) : ?>
		<div class="search-button-container">
			<a href="<?php echo esc_url($button_url); ?>" class="search-custom-button" role="button">
				<?php echo esc_html($button_text); ?>
			</a>
		</div>
	<?php endif; ?>
	
	<?php if (!$is_search_only_block) : ?>
		<div class="selected-topic-filters" role="region" aria-label="Selected filters" aria-live="polite">
		</div>

		<?php // This is where the initial search results will load and where AJAX will inject new results.
		?>
		<div class="relevanssi-ajax-search-results-container">
			<?php
			// Only render initial search results if we should show results
			if ($should_show_results) {
				// Render initial search results when the page loads.
				// The function is now defined in functions.php.
				// Author IDs are already converted from slugs above
				echo caes_hub_render_relevanssi_search_results($current_search_query, $current_orderby, $current_order, $current_post_type, $taxonomy_slug, $current_topic_terms, 1, $allowed_post_types, $current_author_ids, $current_language_for_query);
			}
			?>
		</div>
	<?php endif; ?>

</div>