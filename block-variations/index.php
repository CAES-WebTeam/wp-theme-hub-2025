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

    // Sanitize and confirm the sanitized value
    $sanitized_event_type = sanitize_text_field($event_type);

    if (!empty($sanitized_event_type) && $sanitized_event_type !== 'All') {
        $args['meta_key'] = 'event_type';
        $args['meta_value'] = $sanitized_event_type;
        $args['meta_compare'] = '='; // Explicitly set for exact matching
    } else {
        // When "All" is selected, remove the filter
        unset($args['meta_key'], $args['meta_value'], $args['meta_compare']);
    }

    return $args;
}
add_filter('rest_events_query', 'rest_event_type', 10, 2);

/*** END EVENTS */

/*** START PUBLICATONS */
// Backend
function rest_pub_language_orderby($args, $request)
{

    // Handle language filter
    $lang = $request->get_param('language');
    if ($lang) {
        $args['meta_key'] = 'language';
        $args['meta_value'] = absint($lang);
    }

    return $args;
}
add_filter('rest_publications_query', 'rest_pub_language_orderby', 10, 2);
/*** END PUBLICATONS */

/*** START POSTS */
// Backend
function rest_posts_external_publishers($args, $request)
{
    $hasExternalPublishers = $request->get_param('hasExternalPublishers');

    // Handle both boolean true and string 'true'
    if ($hasExternalPublishers === true || $hasExternalPublishers === 'true') {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'external_publisher',
                'operator' => 'EXISTS'
            )
        );
    }

    return $args;
}
add_filter('rest_post_query', 'rest_posts_external_publishers', 10, 2);
/*** END POSTS */

/*** FRONT END */

function variations_pre_render_block($pre_render, $parsed_block)
{
    // Handle blocks WITH namespace (your variations)
    if (isset($parsed_block['attrs']['namespace'])) {
        $namespace = $parsed_block['attrs']['namespace'];

        $filter_function = function ($query, $block) use ($parsed_block, $namespace) {
            // Get block attributes from WP_Block object
            $block_attrs = $block->parsed_block['attrs'] ?? [];

            // Only apply to the exact same block by comparing queryId
            if (
                !isset($block_attrs['queryId']) ||
                !isset($parsed_block['attrs']['queryId']) ||
                $block_attrs['queryId'] !== $parsed_block['attrs']['queryId']
            ) {
                return $query;
            }

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
    }

    // Handle posts query blocks separately
    if (
        $parsed_block['blockName'] === 'core/query' &&
        isset($parsed_block['attrs']['query']['postType']) &&
        $parsed_block['attrs']['query']['postType'] === 'post'
    ) {
        $filter_function = function ($query, $block) use ($parsed_block) {

            // Try multiple ways to get the queryId
            $block_query_id = null;

            if (isset($block->parsed_block['attrs']['queryId'])) {
                $block_query_id = $block->parsed_block['attrs']['queryId'];
            }

            if (isset($block->attributes['queryId'])) {
                $block_query_id = $block->attributes['queryId'];
            }

            if (isset($block->context['queryId'])) {
                $block_query_id = $block->context['queryId'];
            }

            // Only proceed if we have a matching queryId
            if ($block_query_id == $parsed_block['attrs']['queryId']) {

                $hasExternalPublishers = $parsed_block['attrs']['query']['hasExternalPublishers'] ?? false;

                if ($hasExternalPublishers == 1 || $hasExternalPublishers === true || $hasExternalPublishers === 'true') {
                    $query['tax_query'] = array(
                        array(
                            'taxonomy' => 'external_publisher',
                            'operator' => 'EXISTS'
                        )
                    );
                }
            }

            return $query;
        };

        add_filter('query_loop_block_query_vars', $filter_function, 10, 2);
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

// Register the custom REST parameter for posts
function register_posts_rest_fields()
{
    register_rest_field('post', 'hasExternalPublishers', array(
        'get_callback' => null, // We don't need to return this field
        'update_callback' => null,
        'schema' => array(
            'description' => 'Filter posts by external publishers',
            'type' => 'boolean',
        ),
    ));
}
add_action('rest_api_init', 'register_posts_rest_fields');

// Add the parameter to the posts collection endpoint
function add_custom_query_vars($valid_vars)
{
    $valid_vars = array_merge($valid_vars, array('hasExternalPublishers'));
    return $valid_vars;
}
add_filter('rest_query_vars', 'add_custom_query_vars');

/** END FRONT END */
