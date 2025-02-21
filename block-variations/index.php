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

// Backend
function rest_pub_language_orderby($args, $request) {
    error_log('rest_pub_language_orderby activated');

    // Handle language filter
    $lang = $request->get_param('language');
    if ($lang) {
        $args['meta_key'] = 'language';
        $args['meta_value'] = absint($lang);
    }

    // Handle order by
    $order_by = $request->get_param('pubOrderBy');

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
    if ($order_by) {
        // Default sorting logic (date/title)
        switch ($order_by) {
            case 'date_desc':
                $args['orderby'] = 'date';
                $args['order'] = 'DESC';
                break;
            case 'date_asc':
                $args['orderby'] = 'date';
                $args['order'] = 'ASC';
                break;
            case 'title_asc':
                $args['orderby'] = 'title';
                $args['order'] = 'ASC';
                break;
            case 'title_desc':
                $args['orderby'] = 'title';
                $args['order'] = 'DESC';
                break;
        }
    }

    return $args;
}
add_filter('rest_publications_query', 'rest_pub_language_orderby', 10, 2);


// Front end
function variations_pre_render_block($pre_render, $parsed_block)
{
    // Check if 'attrs' and 'namespace' keys exist
    if (isset($parsed_block['attrs']) && isset($parsed_block['attrs']['namespace']) && 'pubs-feed' === $parsed_block['attrs']['namespace']) {

        // Define the filter function
        $filter_function = function ($query, $block) use ($parsed_block) {
            // Add rating meta key/value pair if queried and not empty.
            if (isset($parsed_block['attrs']['query']['language']) && $parsed_block['attrs']['query']['language'] !== '') {
                $query['meta_key'] = 'language';
                $query['meta_value'] = absint($parsed_block['attrs']['query']['language']);
            }
            return $query;
        };

        // Add the filter
        add_filter('query_loop_block_query_vars', $filter_function, 10, 2);

        // Remove the filter after it has been applied
        add_action('loop_end', function() use ($filter_function) {
            remove_filter('query_loop_block_query_vars', $filter_function, 10, 2);
        });
    }

    return $pre_render;
}
add_filter('pre_render_block', 'variations_pre_render_block', 10, 2);
