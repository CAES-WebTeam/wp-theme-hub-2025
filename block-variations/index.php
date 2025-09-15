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

    // Add chronological ordering for events using ACF start_date field
    $args['meta_key'] = 'start_date';
    $args['orderby'] = 'meta_value';
    $args['order'] = 'ASC';
    $args['meta_type'] = 'DATE';

    return $args;
}
add_filter('rest_events_query', 'rest_event_type', 10, 2);

/*** END EVENTS */

/*** START PUBLICATIONS */
// Backend filter for the editor
function rest_pub_filters($args, $request)
{
    // Handle language filter
    $lang = $request->get_param('language');
    if ($lang) {
        $args['meta_key'] = 'language';
        $args['meta_value'] = absint($lang);
    }

    // Handle exclude by 'publication_category'
    $exclude_terms = $request->get_param('taxQueryExcludePubs');
    if (!empty($exclude_terms)) {
        $tax_query = $args['tax_query'] ?? [];
        $tax_query[] = [
            'taxonomy' => 'publication_category',
            'field'    => 'term_id',
            'terms'    => $exclude_terms,
            'operator' => 'NOT IN',
        ];
        $args['tax_query'] = $tax_query;
    }

    if (isset($request['orderByLatestPublishDate']) && $request['orderByLatestPublishDate'] == true) {
        $args['meta_key'] = '_publication_latest_publish_date';
        $args['orderby'] = 'meta_value_num';
        $args['order'] = 'DESC';
    } elseif (isset($request['orderByLatestUpdate']) && $request['orderByLatestUpdate'] == true) {
        $args['meta_key'] = '_publication_latest_revision_date';
        $args['orderby'] = 'meta_value_num';
        $args['order'] = 'DESC';
    }


    return $args;
}
add_filter('rest_publications_query', 'rest_pub_filters', 10, 2);

/*** END PUBLICATIONS */


/*** FRONT END */

// Store parsed blocks for reference in the filter
$parsed_blocks_for_filtering = [];

function variations_pre_render_block($pre_render, $parsed_block)
{
    global $parsed_blocks_for_filtering;

    // Store blocks that need filtering
    if (isset($parsed_block['attrs']['queryId'])) {
        $query_id = $parsed_block['attrs']['queryId'];
        $parsed_blocks_for_filtering[$query_id] = $parsed_block;
    }

    return $pre_render;
}

// Global variable to store debug info
global $debug_stories_feed;
$debug_stories_feed = [];

// Consolidated filter function for all query modifications
function variations_query_filter($query, $block)
{
    global $parsed_blocks_for_filtering, $debug_stories_feed;

    // Get block query ID
    $block_query_id = null;
    if (isset($block->parsed_block['attrs']['queryId'])) {
        $block_query_id = $block->parsed_block['attrs']['queryId'];
    } elseif (isset($block->attributes['queryId'])) {
        $block_query_id = $block->attributes['queryId'];
    } elseif (isset($block->context['queryId'])) {
        $block_query_id = $block->context['queryId'];
    }

    // Find matching parsed block
    if (!$block_query_id || !isset($parsed_blocks_for_filtering[$block_query_id])) {
        return $query;
    }

    $parsed_block = $parsed_blocks_for_filtering[$block_query_id];

    $query['post_status'] = 'publish';

    $meta_query = $query['meta_query'] ?? [];
    $tax_query = $query['tax_query'] ?? [];

    // Handle blocks WITH namespace (your variations)
    if (isset($parsed_block['attrs']['namespace'])) {
        $namespace = $parsed_block['attrs']['namespace'];

        // Inside the variations_query_filter function in index.php

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

            // Exclude by 'publication_category'
            $exclude_terms = $parsed_block['attrs']['query']['taxQueryExcludePubs'] ?? [];
            if (!empty($exclude_terms)) {
                $tax_query[] = [
                    'taxonomy' => 'publication_category',
                    'field'    => 'term_id',
                    'terms'    => array_map('absint', $exclude_terms),
                    'operator' => 'NOT IN',
                ];
            }

            // --- ADD THIS BLOCK ---
            // Sorting Logic for Publications Feed
            if (!empty($parsed_block['attrs']['query']['orderByLatestPublishDate'])) {
                $query['meta_key'] = '_publication_latest_publish_date';
                $query['orderby'] = 'meta_value_num';
                $query['order'] = 'DESC';
            } elseif (!empty($parsed_block['attrs']['query']['orderByLatestUpdate'])) {
                $query['meta_key'] = '_publication_latest_revision_date';
                $query['orderby'] = 'meta_value_num';
                $query['order'] = 'DESC';
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
        }

        // For upcoming-events blocks
        if ('upcoming-events' === $namespace) {
            // Debug: Store what's already in the query
            global $debug_events_query;
            $debug_events_query[] = [
                'step' => 'Before custom filter',
                'query' => $query,
                'parsed_block_attrs' => $parsed_block['attrs'] ?? 'No attrs'
            ];

            // Filter by event type if specified
            if (!empty($parsed_block['attrs']['query']['event_type'])) {
                $event_type = sanitize_text_field($parsed_block['attrs']['query']['event_type']);
                $meta_query[] = array(
                    'key' => 'event_type',
                    'value' => $event_type,
                    'compare' => '='
                );

                $debug_events_query[] = [
                    'step' => 'Added event_type meta query',
                    'event_type' => $event_type,
                    'meta_query' => $meta_query
                ];
            }

            // Set chronological ordering for events using ACF start_date field
            $query['meta_key'] = 'start_date';
            $query['orderby'] = 'meta_value';
            $query['order'] = 'ASC'; // Chronological order (earliest first)
            $query['meta_type'] = 'DATE'; // Since ACF stores dates in Ymd format
        }

        // For stories-feed blocks using custom author field
        if ('stories-feed' === $namespace) {
            $query['post_type'] = ['post', 'shorthand_story'];
            unset($query['postType']);

            // Filter by author (expert OR author) if on author archive
            if (is_author()) {
                $author_id = get_queried_object_id();

                // Create an OR condition to check both expert and author fields
                $meta_query[] = array(
                    'relation' => 'OR',
                    array(
                        'key' => 'all_expert_ids',
                        'value' => 'i:' . $author_id . ';',
                        'compare' => 'LIKE'
                    ),
                    array(
                        'key' => 'all_author_ids',
                        'value' => 'i:' . $author_id . ';',
                        'compare' => 'LIKE'
                    )
                );
            }
            // Handle taxonomy archives (categories, tags, topics, etc.)
            elseif (is_category() || is_tag() || is_tax()) {
                $queried_object = get_queried_object();

                if ($queried_object && isset($queried_object->taxonomy) && isset($queried_object->term_id)) {
                    $tax_query[] = array(
                        'taxonomy' => $queried_object->taxonomy,
                        'field'    => 'term_id',
                        'terms'    => $queried_object->term_id,
                    );
                }
            }
            // If NOT on author or taxonomy archive, no additional filtering - just show all posts from both post types
        }
    }

    // Apply meta query if we have conditions
    if (!empty($meta_query)) {
        if (!isset($query['meta_query'])) {
            $query['meta_query'] = [];
        }
        $query['meta_query'] = array_merge($query['meta_query'], $meta_query);
        if (count($query['meta_query']) > 1 && !isset($query['meta_query']['relation'])) {
            $query['meta_query']['relation'] = 'AND';
        }
    }

    // Apply tax query if we have conditions
    if (!empty($tax_query)) {
        if (!isset($query['tax_query'])) {
            $query['tax_query'] = [];
        }
        $query['tax_query'] = array_merge($query['tax_query'], $tax_query);
        if (count($query['tax_query']) > 1 && !isset($query['tax_query']['relation'])) {
            $query['tax_query']['relation'] = 'AND';
        }
    }

    return $query;
}


add_filter('pre_render_block', 'variations_pre_render_block', 10, 2);
add_filter('query_loop_block_query_vars', 'variations_query_filter', 99, 2);

add_filter('render_block', function ($block_content, $block) {
    // Only apply to core/query blocks on author archive pages that use our variations
    if (
        is_author() &&
        $block['blockName'] === 'core/query' &&
        isset($block['attrs']['namespace'])
    ) {
        $namespace = $block['attrs']['namespace'];
        $heading = '';
        $anchor_id = ''; // Anchor ID for pagination

        // Determine heading and anchor based on namespace
        if ($namespace === 'pubs-feed') {
            $heading = '<h2 class="expert-advice-heading is-style-caes-hub-section-heading">Expert Resources</h2>'; // Changed heading
            $anchor_id = 'expert-resources'; // Changed anchor ID
        } elseif ($namespace === 'stories-feed') {
            $heading = '<h2 class="stories-heading is-style-caes-hub-section-heading">Stories</h2>';
            $anchor_id = 'stories';
        }

        // Only proceed if we have a valid namespace for our logic
        if (!empty($heading)) {
            // Step 1: Add anchor to pagination links if the block has a queryId
            if (isset($block['attrs']['queryId'])) {
                $query_id_pattern = 'query-' . $block['attrs']['queryId'] . '-page=';
                $block_content = preg_replace_callback(
                    '/<a\s([^>]*href="[^"]*\?' . preg_quote($query_id_pattern, '/') . '\d+[^"]*")([^>]*)>/i',
                    function ($matches) use ($anchor_id) {
                        $href = $matches[1];
                        // Append #anchor-id just before the final quote if it's not already there
                        $updated_href = preg_replace('/(")$/', '#' . $anchor_id . '$1', $href);
                        return '<a ' . $updated_href . $matches[2] . '>';
                    },
                    $block_content
                );
            }


            // Step 2: Conditionally prepend heading if there are results
            // We need to re-run the query with the current filters to check for posts
            $query_args = isset($block['attrs']['query']) ? $block['attrs']['query'] : [];
            // Apply all existing query filters to get the exact query vars that would be used
            $query_args = apply_filters('query_loop_block_query_vars', $query_args, (object)['parsed_block' => $block]);

            if (isset($block['attrs']['query']['postType'])) {
                $query_args['post_type'] = $block['attrs']['query']['postType'];
            }

            // Ensure 'paged' is set correctly for the internal check, respecting pagination
            $paged = get_query_var('paged') ?: (get_query_var('page') ?: 1);
            $query_args['paged'] = $paged;

            // Important: Do not limit posts_per_page here for the count, or if it's already set by the block, use it.
            // If the block itself sets perPage, this will be in $query_args.
            // If we're just checking existence, ensure it gets a result if there are any.
            // For a simple have_posts check, you often don't need to explicitly set posts_per_page to -1
            // if the block's query already has a reasonable limit, but for total count, you might.
            // For this specific case of checking if 'have_posts()', the existing 'perPage' from the block is fine.

            $query = new WP_Query($query_args);

            if ($query->have_posts()) {
                // Step 3: Wrap the entire rendered query block in an anchor target, AFTER the heading
                $block_content = $heading . '<div id="' . esc_attr($anchor_id) . '">' . $block_content . '</div>';
            }
            wp_reset_postdata(); // Always reset post data after a custom WP_Query

            return $block_content;
        }
    }

    return $block_content;
}, 10, 2); // Priority 10, 2 arguments (block_content, block)

// Add the parameter to the posts collection endpoint
function add_custom_query_vars($valid_vars)
{
    $valid_vars = array_merge($valid_vars, array('hasExternalPublishers'));
    return $valid_vars;
}
add_filter('rest_query_vars', 'add_custom_query_vars');
