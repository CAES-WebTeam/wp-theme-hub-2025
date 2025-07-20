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
$show_author_filter    = $attributes['showAuthorFilter'] ?? true;
$allowed_post_types    = $attributes['postTypes'] ?? array();
$taxonomy_slug         = $attributes['taxonomySlug'] ?? 'category';

// Get current search query and filter parameters from the URL.
$current_search_query = get_search_query();
$current_orderby      = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : '';
$current_order        = isset($_GET['order']) ? sanitize_text_field(wp_unslash($_GET['order'])) : '';

// For topics, handle multiple selections. The URL parameter will be comma-separated.
$current_topic_terms = isset($_GET[$taxonomy_slug]) ? explode(',', sanitize_text_field(wp_unslash($_GET[$taxonomy_slug]))) : array();

// For authors, handle multiple selections using SLUGS instead of IDs for security
$current_author_slugs = array();
if ($show_author_filter && isset($_GET['author_slug'])) {
	$current_author_slugs = explode(',', sanitize_text_field(wp_unslash($_GET['author_slug'])));
	// Validate author slugs
	$current_author_slugs = array_filter($current_author_slugs, function($slug) {
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
$active_filters_exist = !empty($current_orderby) || !empty($current_topic_terms) || ($show_author_filter && !empty($current_author_ids));
if ($current_post_type === 'post' && !$active_filters_exist) {
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
									// Get unique authors from ACF 'authors' repeater field across all allowed post types
									$author_user_ids = [];

									// Get all published posts from allowed post types
									$posts = get_posts([
										'post_type'      => $allowed_post_types,
										'posts_per_page' => -1,
										'post_status'    => 'publish',
										'fields'         => 'ids',
									]);

									foreach ($posts as $post_id) {
										if (have_rows('authors', $post_id)) {
											while (have_rows('authors', $post_id)) {
												the_row();
												$user = get_sub_field('user');

												if (is_array($user) && isset($user['ID'])) {
													$author_user_ids[] = $user['ID'];
												} elseif (is_numeric($user)) {
													$author_user_ids[] = (int) $user;
												}
											}
										}
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
											empty($current_author_slugs) ? 'checked' : '', // Check "All Authors" if no specific authors are selected.
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
												esc_attr($author->user_nicename), // Use slug instead of ID
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
	</form>
	<div class="selected-topic-filters" role="region" aria-label="Selected filters" aria-live="polite">
	</div>

	<?php // This is where the initial search results will load and where AJAX will inject new results.
	?>
	<div class="relevanssi-ajax-search-results-container">
		<?php
		// Render initial search results when the page loads.
		// The function is now defined in functions.php.
		// Author IDs are already converted from slugs above

		echo caes_hub_render_relevanssi_search_results($current_search_query, $current_orderby, $current_order, $current_post_type, $taxonomy_slug, $current_topic_terms, 1, $allowed_post_types, $current_author_ids);
		?>
	</div>

</div>