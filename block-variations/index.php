<?php

// Load the block variations
function variation_assets()
{
    $theme_directory = get_template_directory(); // Absolute file path
    $theme_url = get_template_directory_uri(); // URL

    $asset_file = "{$theme_directory}/block-variations/build/index.asset.php";

    if (file_exists($asset_file)) {
        $asset = include $asset_file;

        wp_enqueue_script(
            'theme-variations',
            "{$theme_url}/block-variations/build/index.js",
            $asset['dependencies'],
            $asset['version'],
            true
        );
    }
}
add_action('enqueue_block_editor_assets', 'variation_assets');


/*** START EVENTS */
// Backend
function rest_event_type($args, $request)
{
    $event_type = $request->get_param('event_type');

    // Debug: Log the event_type param to confirm it's coming in
    error_log('Received event_type: ' . print_r($event_type, true));

    // Sanitize and confirm the sanitized value
    $sanitized_event_type = sanitize_text_field($event_type);
    error_log('Sanitized: ' . print_r($sanitized_event_type, true));

    if (!empty($sanitized_event_type) && $sanitized_event_type !== 'All') {
        $args['meta_key'] = 'event_type';
        $args['meta_value'] = $sanitized_event_type;
        $args['meta_compare'] = '='; // Explicitly set for exact matching
    } else {
        // When "All" is selected, remove the filter
        error_log('Event Type is "All" - Removing Meta Query Filter');
        unset($args['meta_key'], $args['meta_value'], $args['meta_compare']);
    }

    // Debug: Log the modified query args before returning
    error_log('Modified query args: ' . print_r($args, true));

    return $args;
}
add_filter('rest_events_query', 'rest_event_type', 10, 2);

/*** END EVENTS */

/*** START PUBLICATONS */
// Backend
function rest_pub_language_orderby($args, $request)
{
    // error_log('rest_pub_language_orderby activated');

    // Handle language filter
    $lang = $request->get_param('language');
    if ($lang) {
        $args['meta_key'] = 'language';
        $args['meta_value'] = absint($lang);
    }

    // Handle order by
    // $order_by = $request->get_param('pubOrderBy');

    // if (in_array($order_by, ['recently_published', 'recently_revised'])) {
    //     // Get the latest 100 posts before filtering
    //     $args['posts_per_page'] = 100;
    //     $args['orderby'] = 'date';
    //     $args['order'] = 'DESC';

    //     add_filter('rest_publications_query_results', function ($posts) use ($order_by) {
    //         $filtered_posts = [];

    //         foreach ($posts as $post) {
    //             $history = get_field('history', $post['id']);

    //             if (empty($history) || !is_array($history)) {
    //                 continue;
    //             }

    //             // Sort history entries by date (latest first)
    //             usort($history, function ($a, $b) {
    //                 return (int)$b['date'] - (int)$a['date'];
    //             });

    //             // Get latest history entry
    //             $latest_entry = reset($history);
    //             $latest_status = isset($latest_entry['status']) ? (int)$latest_entry['status'] : null;
    //             $latest_date = isset($latest_entry['date']) ? (int)$latest_entry['date'] : 0;

    //             // Log debug info
    //             error_log("Post ID: {$post['id']} | Latest Status: $latest_status | Latest Date: $latest_date");

    //             // Filter posts based on history status
    //             if (
    //                 ($order_by === 'recently_published' && $latest_status === 2) ||
    //                 ($order_by === 'recently_revised' && in_array($latest_status, [4, 5, 6]))
    //             ) {
    //                 // Store latest date for sorting
    //                 $post['latest_history_date'] = $latest_date;
    //                 $filtered_posts[] = $post;
    //             }
    //         }

    //         // Sort filtered posts by latest history date (newest first)
    //         usort($filtered_posts, function ($a, $b) {
    //             return $b['latest_history_date'] - $a['latest_history_date'];
    //         });

    //         return $filtered_posts;
    //     });  
    // if ($order_by) {
    //     // Default sorting logic (date/title)
    //     switch ($order_by) {
    //         case 'date_desc':
    //             $args['orderby'] = 'date';
    //             $args['order'] = 'DESC';
    //             break;
    //         case 'date_asc':
    //             $args['orderby'] = 'date';
    //             $args['order'] = 'ASC';
    //             break;
    //         case 'title_asc':
    //             $args['orderby'] = 'title';
    //             $args['order'] = 'ASC';
    //             break;
    //         case 'title_desc':
    //             $args['orderby'] = 'title';
    //             $args['order'] = 'DESC';
    //             break;
    //     }
    // }

    return $args;
}
add_filter('rest_publications_query', 'rest_pub_language_orderby', 10, 2);
/*** END PUBLICATONS */

/*** FRONT END */
function variations_pre_render_block($pre_render, $parsed_block)
{
    if (isset($parsed_block['attrs']['namespace'])) {
        $namespace = $parsed_block['attrs']['namespace'];

        $filter_function = function ($query, $block) use ($parsed_block, $namespace) {
            $meta_query = [];

            // For pubs-feed blocks
            if ('pubs-feed' === $namespace) {
                // Filter by language
                if (!empty($parsed_block['attrs']['query']['language'])) {
                    $language_id = absint($parsed_block['attrs']['query']['language']);
                    $meta_query[] = array(
                        'key' => 'language',
                        'value' => $language_id,
                        'compare' => '='
                    );
                }

                // Filter by author if on author archive
                if (is_author()) {
                    $author_id = get_queried_object_id();
                    $meta_query[] = array(
                        'key' => 'all_author_ids',
                        'value' => 'i:' . $author_id . ';',
                        'compare' => 'LIKE'
                    );
                }                

                if (!empty($meta_query)) {
                    $query['meta_query'] = $meta_query;
                }
            }

            // For upcoming-events blocks
            if ('upcoming-events' === $namespace) {
                if (!empty($parsed_block['attrs']['query']['event_type'])) {
                    $event_type = sanitize_text_field($parsed_block['attrs']['query']['event_type']);
                    $query['meta_query'] = array(
                        array(
                            'key' => 'event_type',
                            'value' => $event_type,
                            'compare' => '='
                        )
                    );
                }
            }

            return $query;
        };

        add_filter('query_loop_block_query_vars', $filter_function, 10, 2);

        add_action('loop_end', function () use ($filter_function) {
            remove_filter('query_loop_block_query_vars', $filter_function, 10, 2);
        });
    }

    return $pre_render;
}
add_filter('pre_render_block', 'variations_pre_render_block', 10, 2);
add_filter('render_block', function ($block_content, $block) {
    // Only apply to pubs-feed query loop on author archive pages
    if (
        is_author() &&
        $block['blockName'] === 'core/query' &&
        isset($block['attrs']['namespace']) &&
        $block['attrs']['namespace'] === 'pubs-feed'
    ) {
        // Step 1: Add anchor to pagination links like ?query-1-page=2
        $block_content = preg_replace_callback(
            '/<a\s([^>]*href="[^"]*\?[^"]*query-[0-9]+-page=\d+[^"]*")([^>]*)>/i',
            function ($matches) {
                $href = $matches[1];
                // Append #expert-advice just before the final quote
                $updated_href = preg_replace('/(")$/', '#expert-advice$1', $href);
                return '<a ' . $updated_href . $matches[2] . '>';
            },
            $block_content
        );

        // Step 2: Wrap the entire rendered query block in an anchor target
        $block_content = '<div id="expert-advice">' . $block_content . '</div>';

        // Step 3: Conditionally prepend heading if there are results
        $query_args = isset($block['attrs']['query']) ? $block['attrs']['query'] : [];
        $query_args = apply_filters('query_loop_block_query_vars', $query_args, $block);
        $paged = get_query_var('paged') ?: (get_query_var('page') ?: 1);
        $query_args['paged'] = $paged;

        $query = new WP_Query($query_args);

        if ($query->have_posts()) {
            $heading = '<h2 class="expert-advice-heading is-style-caes-hub-section-heading">Expert Advice</h2>';
            $block_content = $heading . $block_content;
        }
        wp_reset_postdata();

        return $block_content;
    }

    return $block_content;
}, 10, 2);



/** END FRONT END */
