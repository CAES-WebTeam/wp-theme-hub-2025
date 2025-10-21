<?php

/**
 * Resolves the URL conflict for the custom publications search page.
 * This prevents a 404 error by telling the main WordPress query to ignore the 's'
 * parameter, allowing the /publications/search/ page to load correctly.
 */
add_action('pre_get_posts', 'caes_hub_resolve_publication_search_conflict');
function caes_hub_resolve_publication_search_conflict($query)
{
    // We only want to modify the main query on the front-end of the site.
    if (! is_admin() && $query->is_main_query()) {

        // Check if the 'pagename' query variable is exactly 'publications/search'.
        // This is the condition we confirmed with the debug log.
        if (isset($query->query_vars['pagename']) && $query->query_vars['pagename'] == 'publications/search') {

            // Unset the search parameter from the main query.
            // This resolves the conflict and prevents the 404.
            $query->set('s', null);

            // Also explicitly tell WordPress this is not a search page context.
            $query->set('is_search', false);
        }
    }
}

/**
 * Normalize search queries for known short acronyms.
 * Converts variations without hyphens/proper casing to standard format.
 */
function caes_hub_normalize_search_query($query)
{
    $query = trim($query);

    // Map of search variations to standardized format
    $normalizations = array(
        '4h'  => '4-H',
        '4-h' => '4-H',
        '4 h' => '4-H',
        // Add more as needed
    );

    $query_lower = strtolower($query);
    if (isset($normalizations[$query_lower])) {
        return $normalizations[$query_lower];
    }

    return $query;
}

/** * Renders Relevanssi search results HTML using block syntax. 
 */
if (! function_exists('caes_hub_render_relevanssi_search_results')) {
    function caes_hub_render_relevanssi_search_results($search_query, $orderby, $order, $post_type, $taxonomy_slug, $topic_terms, $paged = 1, $allowed_post_types_from_block = array(), $author_ids = array(), $language = '') // Add language parameter
    {

        $args = array(
            's'              => $search_query,
            'posts_per_page' => 10,
            'paged'          => $paged,
            'post_status'    => 'publish'
        );

        // Handle post_type: if empty, search all allowed post types instead of defaulting to 'post' 
        if (! empty($post_type)) {
            // Special case: when "Stories" is selected (post_type = 'post'), include both post and shorthand_story
            if ($post_type === 'post') {
                $args['post_type'] = array('post', 'shorthand_story');
            } else {
                $args['post_type'] = $post_type;
            }
        } else {
            // When no specific post type is selected, search all configured post types 
            $all_allowed_post_types_for_query = empty($allowed_post_types_from_block) ? array('post', 'page') : $allowed_post_types_from_block;
            $args['post_type'] = $all_allowed_post_types_for_query; // Use this variable instead
        }

        if ($orderby === 'post_date') {
            $args['orderby'] = 'date';
            $args['order']   = $order;
        } else {
            $args['orderby'] = 'relevance';
        }

        // Initialize meta_query array
        $meta_query = array();

        // Handle language filtering using ACF custom field
        if (!empty($language)) {
            // error_log('RENDER: Processing language filter with value: ' . $language);

            // For AJAX requests, language is already converted to ID
            // For URL requests, convert pretty slug to ID if needed
            $language_id = $language;

            // Only do slug-to-ID conversion if it looks like a slug (not a number)
            if (!is_numeric($language)) {
                $language_slug_to_id = array(
                    'english' => '1',
                    'spanish' => '2',
                    'chinese' => '3',
                    'other' => '4'
                );

                $language_id = isset($language_slug_to_id[$language]) ? $language_slug_to_id[$language] : $language;
                // error_log('RENDER: Converted language slug "' . $language . '" to ID "' . $language_id . '"');
            } else {
                // error_log('RENDER: Language is already numeric ID: ' . $language_id);
            }

            $meta_query[] = array(
                'key'     => 'language',
                'value'   => $language_id,
                'compare' => '='
            );

            // error_log('RENDER: Added language meta_query: key=language, value=' . $language_id . ', compare==');
        }

        // Initialize tax_query to handle multiple conditions.
        $tax_query = array(
            'relation' => 'AND',
        );

        // Exclude posts from the "Feed the Future Peanut Lab" topic from all search results.
        $peanut_lab_term = get_term_by('name', 'Feed the Future Peanut Lab', 'topics');
        if ($peanut_lab_term && !is_wp_error($peanut_lab_term)) {
            $tax_query[] = array(
                'taxonomy' => 'topics',
                'field'    => 'term_id',
                'terms'    => array($peanut_lab_term->term_id),
                'operator' => 'NOT IN',
            );
        }

        if (! empty($topic_terms) && $topic_terms[0] !== '') {
            $tax_query[] = array(
                'taxonomy' => $taxonomy_slug,
                'field'    => 'slug',
                'terms'    => $topic_terms,
                'operator' => 'IN',
            );
        }

        // Add the tax_query to the main arguments array if there are any conditions.
        if (count($tax_query) > 1) {
            $args['tax_query'] = $tax_query;
        }

        // Handle author filtering using ACF fields (WordPress-native optimized approach)
        // Note: author_ids will be empty if showAuthorFilter toggle is disabled
        if (!empty($author_ids)) {
            // Use a single meta_query with IN comparison for all author positions
            $meta_query_or_conditions = array('relation' => 'OR');

            // Instead of checking each author separately, check all positions for all authors
            for ($i = 0; $i <= 9; $i++) {
                $meta_query_or_conditions[] = array(
                    'key' => "authors_{$i}_user",
                    'value' => $author_ids, // Pass the whole array
                    'compare' => 'IN'       // Use IN instead of multiple = comparisons
                );
            }

            // Single query to get all posts with any of the selected authors
            $author_posts_query = new WP_Query(array(
                'post_type' => !empty($post_type) ?
                    ($post_type === 'post' ? array('post', 'shorthand_story') : array($post_type)) : (empty($allowed_post_types_from_block) ? array('post', 'page') : $allowed_post_types_from_block),
                'posts_per_page' => -1,
                'fields' => 'ids',
                'post_status' => 'publish',
                'meta_query' => $meta_query_or_conditions,
                'no_found_rows' => true, // Skip pagination counting for performance
                'update_post_meta_cache' => false, // Skip meta cache
                'update_post_term_cache' => false  // Skip term cache
            ));

            $author_post_ids = $author_posts_query->posts;

            // Clean up
            wp_reset_postdata();

            if (!empty($author_post_ids)) {
                $args['post__in'] = $author_post_ids;
            } else {
                // No posts found with selected authors - force no results
                $args['post__in'] = array(-999999); // Non-existent post ID
                $meta_query[] = array(
                    'key' => 'force_no_results_dummy_key',
                    'value' => 'force_no_results_dummy_value',
                    'compare' => '='
                );
            }
        }

        // Add meta_query to args if we have any meta queries
        if (!empty($meta_query)) {
            if (count($meta_query) > 1) {
                $meta_query['relation'] = 'AND';
            }
            $args['meta_query'] = $meta_query;
            // error_log('RENDER: Final meta_query: ' . print_r($meta_query, true));
        }

        // error_log('RENDER: Final WP_Query Args: ' . print_r($args, true));

        if (function_exists('relevanssi_do_query')) {
            $query = new WP_Query($args);
            relevanssi_do_query($query);
        } else {
            $query = new WP_Query($args);
        }

        // error_log('RENDER: Query found_posts: ' . $query->found_posts);

        // Store the global $wp_query to restore later 
        global $wp_query;
        $original_query = $wp_query;

        // Temporarily replace the global $wp_query with our custom query 
        $wp_query = $query;

        ob_start();

        // Define the block markup template for publication search results
        $publication_search_result_template = '
<!-- wp:group {"className":"caes-hub-post-list-grid-item caes-hub-post-list-grid-horizontal","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group caes-hub-post-list-grid-item caes-hub-post-list-grid-horizontal"><!-- wp:post-featured-image {"aspectRatio":"3/2","metadata":{"name":"caes-hub-post-list-img-container"},"className":"caes-hub-post-list-img-container"} /-->
<!-- wp:group {"metadata":{"name":"caes-hub-post-list-grid-info"},"className":"caes-hub-post-list-grid-info","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|60","right":"var:preset|spacing|60"}}},"backgroundColor":"base","layout":{"type":"flex","orientation":"vertical","justifyContent":"left","verticalAlignment":"space-between"}} -->
<div class="wp-block-group caes-hub-post-list-grid-info has-base-background-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--60)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group"><!-- wp:caes-hub/primary-topic {"showCategoryIcon":true,"enableLinks":false,"name":"caes-hub/primary-topic","mode":"preview","className":"is-style-caes-hub-oswald-uppercase","style":{"border":{"right":{"color":"var:preset|color|contrast","width":"1px"}},"spacing":{"padding":{"right":"var:preset|spacing|30"}}}} /-->
<!-- wp:post-date {"format":"M j, Y","style":{"typography":{"fontStyle":"light","fontWeight":"300","textTransform":"uppercase"}},"fontFamily":"oswald"} /--></div>
<!-- /wp:group -->
<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:group {"className":"caes-hub-post-list-mobile-column","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"top"}} -->
<div class="wp-block-group caes-hub-post-list-mobile-column"><!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:caes-hub/pub-details-number {"fontSize":"small"} /-->
<!-- wp:post-title {"isLink":true,"className":"caes-hub-post-list-grid-title","style":{"elements":{"link":{"color":{"text":"var:preset|color|contrast"}}}},"textColor":"contrast","fontSize":"large"} /--></div>
<!-- /wp:group -->
<!-- wp:caes-hub/pub-details-status /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- wp:caes-hub/pub-details-summary {"wordLimit":50} /--></div>
<!-- /wp:group -->
<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group"><!-- wp:caes-hub/pub-details-authors {"displayVersion":"names-only","showHeading":false,"grid":false} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->';

        // Define the block markup template for non-publication search results
        $general_search_result_template = '
<!-- wp:group {"className":"caes-hub-post-list-grid-item caes-hub-post-list-grid-horizontal","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group caes-hub-post-list-grid-item caes-hub-post-list-grid-horizontal"><!-- wp:post-featured-image {"aspectRatio":"3/2","metadata":{"name":"caes-hub-post-list-img-container"},"className":"caes-hub-post-list-img-container"} /-->
<!-- wp:group {"metadata":{"name":"caes-hub-post-list-grid-info"},"className":"caes-hub-post-list-grid-info","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|60","right":"var:preset|spacing|60"}}},"backgroundColor":"base","layout":{"type":"flex","orientation":"vertical","justifyContent":"left","verticalAlignment":"space-between"}} -->
<div class="wp-block-group caes-hub-post-list-grid-info has-base-background-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--60)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group"><!-- wp:caes-hub/primary-topic {"showCategoryIcon":true,"enableLinks":false,"name":"caes-hub/primary-topic","mode":"preview","className":"is-style-caes-hub-oswald-uppercase","style":{"border":{"right":{"color":"var:preset|color|contrast","width":"1px"}},"spacing":{"padding":{"right":"var:preset|spacing|30"}}}} /-->
<!-- wp:post-date {"format":"M j, Y","style":{"typography":{"fontStyle":"light","fontWeight":"300","textTransform":"uppercase"}},"fontFamily":"oswald"} /--></div>
<!-- /wp:group -->
<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:group {"className":"caes-hub-post-list-mobile-column","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"top"}} -->
<div class="wp-block-group caes-hub-post-list-mobile-column"><!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:post-title {"isLink":true,"className":"caes-hub-post-list-grid-title","style":{"elements":{"link":{"color":{"text":"var:preset|color|contrast"}}}},"textColor":"contrast","fontSize":"large"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- wp:post-excerpt {"excerptLength":50} /--></div>
<!-- /wp:group -->
<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group"><!-- wp:caes-hub/pub-details-authors {"displayVersion":"names-only","showHeading":false,"grid":false} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->';
?>
        <div class="relevanssi-search-results" data-results-count="<?php echo esc_attr($query->found_posts); ?>">
            <?php
            if (have_posts()) {
                $post_count = 0;

                // Create query loop structure like post-template
                echo '<div class="wp-block-query caes-hub-post-list-grid">';
                echo '<ul class="wp-block-post-template" style="gap: var(--wp--preset--spacing--50);padding: 0;display: flex;flex-direction: column;">';

                while (have_posts()) {
                    the_post();
                    $post_count++;

                    // Determine which template to use based on post type
                    $current_post_type = get_post_type();
                    $is_publication = ($current_post_type === 'publication'); // Adjust this condition based on your publication post type

                    // Select appropriate template
                    $template_to_use = $is_publication ? $publication_search_result_template : $general_search_result_template;

                    // Parse the selected block template
                    $parsed_blocks = parse_blocks($template_to_use);

                    // Each post wrapped in <li> like post-template does
                    echo '<li class="wp-block-post post-' . get_the_ID() . ' ' . implode(' ', get_post_class()) . '">';

                    // Render the blocks for each post
                    foreach ($parsed_blocks as $block) {
                        echo render_block($block);
                    }

                    echo '</li>';
                }

                echo '</ul>';
                echo '</div>';
                // error_log('RENDER: Results found: ' . $post_count . ' posts.');
                the_posts_pagination(
                    array(
                        'prev_text'          => __('Previous', 'caes-hub'),
                        'next_text'          => __('Next', 'caes-hub'),
                        'screen_reader_text' => __('Posts navigation', 'caes-hub'),
                    )
                );
            } else {
                // error_log('RENDER: No results found by WP_Query/Relevanssi.');
            ?>
                <p><?php esc_html_e('No results found.', 'caes-hub'); ?></p>
            <?php
            }

            // Restore the original global $wp_query 
            $wp_query = $original_query;
            wp_reset_postdata();
            // error_log('RENDER: wp_reset_postdata() called and global $wp_query restored.');
            ?>
        </div>
<?php
        return ob_get_clean();
    }
}

/** * Register AJAX handler for Relevanssi Search. 
 */
function caes_hub_register_relevanssi_ajax_action()
{
    add_action('wp_ajax_caes_hub_search_results', 'caes_hub_handle_relevanssi_ajax_search');
    add_action('wp_ajax_nopriv_caes_hub_search_results', 'caes_hub_handle_relevanssi_ajax_search');
}
add_action('init', 'caes_hub_register_relevanssi_ajax_action');

/** * Handles Relevanssi AJAX search requests. 
 */
function caes_hub_handle_relevanssi_ajax_search()
{
    // error_log('AJAX: caes_hub_handle_relevanssi_ajax_search function called.');

    if (! defined('DOING_AJAX') || ! DOING_AJAX) {
        // error_log('AJAX: Not an AJAX request. Exiting.');
        wp_die('Not an AJAX request.', 403);
    }

    // error_log('AJAX: $_POST Data: ' . print_r($_POST, true));

    $taxonomy_slug = 'category';
    if (isset($_POST['taxonomySlug'])) {
        $taxonomy_slug = sanitize_text_field(wp_unslash($_POST['taxonomySlug']));
    }
    // error_log('AJAX: Determined taxonomy_slug: ' . $taxonomy_slug);

    // Get filter toggle states from AJAX request
    $show_author_filter = isset($_POST['showAuthorFilter']) && $_POST['showAuthorFilter'] === 'true';
    $show_topic_filter = isset($_POST['showTopicFilter']) && $_POST['showTopicFilter'] === 'true';
    $show_language_filter = isset($_POST['showLanguageFilter']) && $_POST['showLanguageFilter'] === 'true';

    // error_log('AJAX: show_language_filter: ' . ($show_language_filter ? 'true' : 'false'));

    $ajax_s = isset($_POST['s']) ? sanitize_text_field(wp_unslash($_POST['s'])) : '';
    $ajax_s = caes_hub_normalize_search_query($ajax_s); // Normalize known acronyms
    $ajax_orderby     = isset($_POST['orderby']) ? sanitize_text_field(wp_unslash($_POST['orderby'])) : '';
    $ajax_order       = isset($_POST['order']) ? sanitize_text_field(wp_unslash($_POST['order'])) : '';
    $ajax_post_type   = isset($_POST['post_type']) ? sanitize_text_field(wp_unslash($_POST['post_type'])) : '';

    // Only process topic terms if topic filter is enabled
    $ajax_topic_terms = array();
    if ($show_topic_filter && isset($_POST[$taxonomy_slug])) {
        $ajax_topic_terms = array_map('sanitize_text_field', wp_unslash($_POST[$taxonomy_slug]));
    }

    // Only process language if language filter is enabled
    $ajax_language = '';
    if ($show_language_filter && isset($_POST['language'])) {
        $raw_language = sanitize_text_field(wp_unslash($_POST['language']));
        // error_log('AJAX: Raw language from POST: ' . $raw_language);

        // Convert pretty language slug to database ID for AJAX requests
        if (!empty($raw_language)) {
            $language_slug_to_id = array(
                'english' => '1',
                'spanish' => '2',
                'chinese' => '3',
                'other' => '4'
            );

            // If it's a pretty slug, convert to database ID
            if (isset($language_slug_to_id[$raw_language])) {
                $ajax_language = $language_slug_to_id[$raw_language];
                // error_log('AJAX: Converted language slug "' . $raw_language . '" to ID "' . $ajax_language . '"');
            } else {
                $ajax_language = $raw_language; // Keep as-is for backward compatibility
                // error_log('AJAX: Language not found in mapping, keeping as-is: ' . $ajax_language);
            }
        }
    } else {
        // error_log('AJAX: Language not processed. show_language_filter=' . ($show_language_filter ? 'true' : 'false') . ', language_posted=' . (isset($_POST['language']) ? 'yes' : 'no'));
    }

    $ajax_paged       = isset($_POST['paged']) ? intval(wp_unslash($_POST['paged'])) : 1;

    // Only process author slugs if author filter is enabled - SECURITY: using slugs instead of IDs
    $ajax_author_ids = array();
    if ($show_author_filter && isset($_POST['author_slug']) && is_array($_POST['author_slug'])) {
        $ajax_author_slugs = array_map('sanitize_text_field', wp_unslash($_POST['author_slug']));
        $ajax_author_slugs = array_filter($ajax_author_slugs); // Remove any empty values

        // Validate author slugs for security
        $ajax_author_slugs = array_filter($ajax_author_slugs, function ($slug) {
            return preg_match('/^[a-zA-Z0-9\-_]+$/', $slug);
        });

        // Convert slugs back to IDs for internal processing
        foreach ($ajax_author_slugs as $slug) {
            $user = get_user_by('slug', $slug);
            if ($user) {
                $ajax_author_ids[] = $user->ID;
            }
        }
    }

    // Get allowed_post_types from the AJAX request if passed, or default.
    // You'll need to pass this from view.js in the AJAX request.
    $ajax_allowed_post_types = array();
    if (isset($_POST['allowedPostTypes'])) {
        $decoded_post_types = json_decode(wp_unslash($_POST['allowedPostTypes']), true); // Decode JSON string to an array
        if (is_array($decoded_post_types)) {
            $ajax_allowed_post_types = array_map('sanitize_text_field', $decoded_post_types); // Sanitize each element of the array
        }
    }

    // error_log('AJAX: Sanitized Query Params for render function:');
    // error_log('  s: ' . $ajax_s);
    // error_log('  orderby: ' . $ajax_orderby);
    // error_log('  order: ' . $ajax_order);
    // error_log('  post_type: ' . $ajax_post_type);
    // error_log('  topic_terms: ' . print_r($ajax_topic_terms, true));
    // error_log('  author_ids: ' . print_r($ajax_author_ids, true));
    // error_log('  language: ' . $ajax_language);
    // error_log('  paged: ' . $ajax_paged);
    // error_log('  allowedPostTypes: ' . print_r($ajax_allowed_post_types, true));

    // Pass the allowed post types, author IDs, and language to the render function for AJAX requests
    echo caes_hub_render_relevanssi_search_results($ajax_s, $ajax_orderby, $ajax_order, $ajax_post_type, $taxonomy_slug, $ajax_topic_terms, $ajax_paged, $ajax_allowed_post_types, $ajax_author_ids, $ajax_language);

    // error_log('AJAX: wp_die() called.');
    wp_die();
}

/** * Enqueue script with AJAX URL. 
 */
function caes_hub_enqueue_ajax_url()
{
    // Do nothing - we'll add the AJAX URL directly in render.php 
}
add_action('wp_enqueue_scripts', 'caes_hub_enqueue_ajax_url');

/**
 * Add author names from ACF repeater to Relevanssi searchable content.
 * This allows searching by author name without Relevanssi Premium.
 */
add_filter('relevanssi_content_to_index', 'caes_hub_add_authors_to_relevanssi_index', 10, 2);
function caes_hub_add_authors_to_relevanssi_index($content, $post)
{
    $post_types_with_authors = array('post', 'shorthand_story', 'publications', 'page');
    if (!in_array($post->post_type, $post_types_with_authors)) {
        return $content;
    }

    $author_names = array();

    for ($i = 0; $i <= 9; $i++) {
        $author_user_id = get_post_meta($post->ID, "authors_{$i}_user", true);

        if ($author_user_id) {
            $user = get_userdata($author_user_id);
            if ($user) {
                $author_names[] = $user->display_name;
                $author_names[] = trim($user->first_name . ' ' . $user->last_name);
            }
        }
    }

    if (!empty($author_names)) {
        $author_names = array_filter(array_unique($author_names));
        $content .= ' ' . implode(' ', $author_names);
    }

    return $content;
}

/**
 * Prioritize posts where the searched name matches an actual author.
 * Optimized version with caching to reduce database queries.
 */
add_filter('relevanssi_hits_filter', 'caes_hub_prioritize_author_results', 20);
function caes_hub_prioritize_author_results($hits)
{
    $search_query = '';

    if (isset($_GET['s']) && !empty($_GET['s'])) {
        $search_query = sanitize_text_field(wp_unslash($_GET['s']));
    } elseif (isset($_POST['s']) && !empty($_POST['s'])) {
        $search_query = sanitize_text_field(wp_unslash($_POST['s']));
    }

    if (empty($search_query)) {
        return $hits;
    }

    $search_query = strtolower($search_query);

    // Check if search looks like a person's name (2-3 words)
    $words = explode(' ', trim($search_query));
    if (count($words) < 2 || count($words) > 3) {
        return $hits;
    }

    // Extract all post IDs to batch-fetch author data
    $post_ids = array_map(function ($hit) {
        return $hit->ID;
    }, $hits[0]);

    // Batch-fetch all author meta for all posts at once
    $author_cache = array();
    foreach ($post_ids as $post_id) {
        for ($i = 0; $i <= 9; $i++) {
            $author_user_id = get_post_meta($post_id, "authors_{$i}_user", true);
            if ($author_user_id) {
                if (!isset($author_cache[$author_user_id])) {
                    $user = get_userdata($author_user_id);
                    if ($user) {
                        $author_cache[$author_user_id] = array(
                            'display_name' => strtolower($user->display_name),
                            'full_name' => strtolower(trim($user->first_name . ' ' . $user->last_name))
                        );
                    }
                }
            }
        }
    }

    // Now check each hit using the cached author data
    $authored_posts = array();
    $other_posts = array();

    foreach ($hits[0] as $hit) {
        $post_id = $hit->ID;
        $is_authored = false;

        for ($i = 0; $i <= 9; $i++) {
            $author_user_id = get_post_meta($post_id, "authors_{$i}_user", true);

            if ($author_user_id && isset($author_cache[$author_user_id])) {
                $author_data = $author_cache[$author_user_id];

                if (
                    stripos($search_query, $author_data['display_name']) !== false ||
                    stripos($author_data['display_name'], $search_query) !== false ||
                    stripos($search_query, $author_data['full_name']) !== false ||
                    stripos($author_data['full_name'], $search_query) !== false
                ) {
                    $is_authored = true;
                    break;
                }
            }
        }

        if ($is_authored) {
            $authored_posts[] = $hit;
        } else {
            $other_posts[] = $hit;
        }
    }

    // Put authored posts first, then other matches
    $reordered = array_merge($authored_posts, $other_posts);

    return array($reordered);
}

/**
 * Prioritize publications that belong to the searched publication series.
 * Pushes publications in the matching series to the top of search results.
 */
add_filter('relevanssi_hits_filter', 'caes_hub_prioritize_series_results', 30);
function caes_hub_prioritize_series_results($hits)
{
    // Get search query from REQUEST
    $search_query = '';

    if (isset($_GET['s']) && !empty($_GET['s'])) {
        $search_query = sanitize_text_field(wp_unslash($_GET['s']));
    } elseif (isset($_POST['s']) && !empty($_POST['s'])) {
        $search_query = sanitize_text_field(wp_unslash($_POST['s']));
    }

    if (empty($search_query)) {
        return $hits;
    }

    // Search for publication_series terms that match the query
    $matching_terms = get_terms(array(
        'taxonomy' => 'publication_series',
        'hide_empty' => true,
        'search' => $search_query,
    ));

    // If no matching series found, don't modify results
    if (empty($matching_terms) || is_wp_error($matching_terms)) {
        return $hits;
    }

    // Get the IDs of matching terms
    $matching_term_ids = array_map(function ($term) {
        return $term->term_id;
    }, $matching_terms);

    // Separate results into series publications vs. others
    $series_posts = array();
    $other_posts = array();

    foreach ($hits[0] as $hit) {
        $post_id = $hit->ID;
        $is_in_series = false;

        // Check if this post has any of the matching series terms
        $post_terms = wp_get_post_terms($post_id, 'publication_series', array('fields' => 'ids'));

        if (!is_wp_error($post_terms) && !empty($post_terms)) {
            // Check if any of the post's terms match our search
            foreach ($matching_term_ids as $term_id) {
                if (in_array($term_id, $post_terms)) {
                    $is_in_series = true;
                    break;
                }
            }
        }

        if ($is_in_series) {
            $series_posts[] = $hit;
        } else {
            $other_posts[] = $hit;
        }
    }

    // Put series publications first, then other matches
    $reordered = array_merge($series_posts, $other_posts);

    return array($reordered);
}
