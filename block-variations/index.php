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

/*** START PUBLICATIONS */
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

// Consolidated filter function for all query modifications
function variations_query_filter($query, $block)
{
    global $parsed_blocks_for_filtering;
    
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
    $meta_query = [];
    $tax_query = [];

    // Handle blocks WITH namespace (your variations)
    if (isset($parsed_block['attrs']['namespace'])) {
        $namespace = $parsed_block['attrs']['namespace'];

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
        }

        // For upcoming-events blocks
        if ('upcoming-events' === $namespace) {
            if (!empty($parsed_block['attrs']['query']['event_type'])) {
                $event_type = sanitize_text_field($parsed_block['attrs']['query']['event_type']);
                $meta_query[] = array(
                    'key' => 'event_type',
                    'value' => $event_type,
                    'compare' => '='
                );
            }
        }

        // For stories-feed blocks using custom author field
        if ('stories-feed' === $namespace) {
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
        }

    }

    // Apply meta query if we have conditions
    if (!empty($meta_query)) {
        if (count($meta_query) > 1) {
            $meta_query['relation'] = 'AND';
        }
        // Merge with existing meta_query if it exists
        if (isset($query['meta_query'])) {
            $query['meta_query'] = array_merge($query['meta_query'], $meta_query);
            if (count($query['meta_query']) > 1 && !isset($query['meta_query']['relation'])) {
                $query['meta_query']['relation'] = 'AND';
            }
        } else {
            $query['meta_query'] = $meta_query;
        }
    }

    // Apply tax query if we have conditions - IMPROVED HANDLING
    if (!empty($tax_query)) {
        // Handle existing tax_query properly
        if (isset($query['tax_query']) && is_array($query['tax_query'])) {
            // If there's already a tax_query, merge them
            $existing_tax_query = $query['tax_query'];
            
            // Remove relation if it exists to add it back properly
            if (isset($existing_tax_query['relation'])) {
                $relation = $existing_tax_query['relation'];
                unset($existing_tax_query['relation']);
            } else {
                $relation = 'AND';
            }
            
            // Merge the arrays
            $merged_tax_query = array_merge($existing_tax_query, $tax_query);
            
            // Add relation if we have multiple conditions
            if (count($merged_tax_query) > 1) {
                $merged_tax_query['relation'] = $relation;
            }
            
            $query['tax_query'] = $merged_tax_query;
        } else {
            // No existing tax_query, just set ours
            if (count($tax_query) > 1) {
                $tax_query['relation'] = 'AND';
            }
            $query['tax_query'] = $tax_query;
        }
    }

    return $query;
}

add_filter('pre_render_block', 'variations_pre_render_block', 10, 2);
add_filter('query_loop_block_query_vars', 'variations_query_filter', 10, 2);

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

/** END FRONT END */


// Add this to functions.php temporarily - focused debug for author stories

function debug_author_stories_specifically() {
    // Only run on author pages for logged-in users who can edit posts
    if (!is_author() || !current_user_can('edit_posts')) {
        return;
    }
    
    $author_id = get_queried_object_id();
    $author = get_userdata($author_id);
    
    echo '<div style="background: #e3f2fd; padding: 20px; margin: 20px 0; border: 2px solid #1976d2; border-radius: 5px;">';
    echo '<h3>🔍 DEBUG: Stories for Author "' . esc_html($author->display_name) . '" (ID: ' . $author_id . ')</h3>';
    
    // Test 1: Direct meta query to find posts with this author ID
    echo '<h4>Test 1: Direct Meta Query Search</h4>';
    
    $direct_query = new WP_Query(array(
        'post_type' => 'post', // Change to 'stories' if that's your post type
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => array(
            array(
                'key' => 'all_author_ids',
                'value' => 'i:' . $author_id . ';',
                'compare' => 'LIKE'
            )
        )
    ));
    
    echo '<p><strong>Query found ' . $direct_query->found_posts . ' posts with author ID in all_author_ids</strong></p>';
    
    if ($direct_query->have_posts()) {
        echo '<ul>';
        while ($direct_query->have_posts()) {
            $direct_query->the_post();
            $meta_value = get_post_meta(get_the_ID(), 'all_author_ids', true);
            echo '<li><strong>' . get_the_title() . '</strong> (ID: ' . get_the_ID() . ')<br>';
            echo 'Meta value: <code>' . esc_html($meta_value) . '</code></li>';
        }
        echo '</ul>';
        wp_reset_postdata();
    } else {
        echo '<p style="color: red;">❌ No posts found with this author ID in all_author_ids field</p>';
    }
    
    // Test 2: Check ALL posts to see what's actually stored
    echo '<h4>Test 2: Check All Posts for Author Meta</h4>';
    
    $all_posts = get_posts(array(
        'post_type' => 'post', // Change to 'stories' if needed
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ));
    
    $found_matches = array();
    $all_author_fields = array();
    
    foreach ($all_posts as $post) {
        $author_ids_meta = get_post_meta($post->ID, 'all_author_ids', true);
        if (!empty($author_ids_meta)) {
            $all_author_fields[] = array(
                'post_id' => $post->ID,
                'title' => $post->post_title,
                'meta_value' => $author_ids_meta
            );
            
            // Check various possible formats
            if (
                strpos($author_ids_meta, 'i:' . $author_id . ';') !== false ||
                strpos($author_ids_meta, '"' . $author_id . '"') !== false ||
                strpos($author_ids_meta, $author_id) !== false
            ) {
                $found_matches[] = array(
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'meta_value' => $author_ids_meta
                );
            }
        }
    }
    
    echo '<p><strong>Posts with ANY all_author_ids data:</strong> ' . count($all_author_fields) . '</p>';
    echo '<p><strong>Posts that contain author ID ' . $author_id . ' in any format:</strong> ' . count($found_matches) . '</p>';
    
    if (!empty($found_matches)) {
        echo '<div style="background: #d4edda; padding: 10px; margin: 10px 0; border: 1px solid #c3e6cb;">';
        echo '<h5>✅ Found matches:</h5>';
        foreach ($found_matches as $match) {
            echo '<p><strong>' . esc_html($match['title']) . '</strong><br>';
            echo 'Raw meta: <code>' . esc_html($match['meta_value']) . '</code></p>';
        }
        echo '</div>';
    }
    
    // Test 3: Show some examples of actual meta values to understand format
    echo '<h4>Test 3: Sample Meta Values (to understand format)</h4>';
    echo '<table style="border-collapse: collapse; font-size: 12px;">';
    echo '<tr style="background: #f8f9fa;"><th style="border: 1px solid #ddd; padding: 5px;">Post</th><th style="border: 1px solid #ddd; padding: 5px;">all_author_ids Value</th></tr>';
    
    $sample_count = 0;
    foreach ($all_author_fields as $field) {
        if ($sample_count >= 10) break; // Limit to first 10 for readability
        echo '<tr>';
        echo '<td style="border: 1px solid #ddd; padding: 5px;">' . esc_html($field['title']) . '</td>';
        echo '<td style="border: 1px solid #ddd; padding: 5px; font-family: monospace; word-break: break-all;">' . esc_html($field['meta_value']) . '</td>';
        echo '</tr>';
        $sample_count++;
    }
    echo '</table>';
    
    // Test 4: Try different query patterns
    echo '<h4>Test 4: Try Different Search Patterns</h4>';
    $patterns_to_test = array(
        'i:' . $author_id . ';',
        '"' . $author_id . '"',
        $author_id,
        ':' . $author_id . '',
        'i:' . $author_id . '}'
    );
    
    foreach ($patterns_to_test as $pattern) {
        $test_query = new WP_Query(array(
            'post_type' => 'post',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => 'all_author_ids',
                    'value' => $pattern,
                    'compare' => 'LIKE'
                )
            )
        ));
        
        echo '<p>Pattern "<code>' . esc_html($pattern) . '</code>": <strong>' . $test_query->found_posts . ' results</strong></p>';
        wp_reset_postdata();
    }
    
    echo '</div>';
}

// Hook it to display
add_action('wp_head', 'debug_author_stories_specifically');

// Also add a shortcode version for easy testing
function debug_author_stories_shortcode($atts) {
    $atts = shortcode_atts(array(
        'author_id' => get_queried_object_id()
    ), $atts);
    
    if (!current_user_can('edit_posts')) {
        return 'Debug only available for editors';
    }
    
    ob_start();
    debug_author_stories_specifically();
    return ob_get_clean();
}
add_shortcode('debug_author_stories', 'debug_author_stories_shortcode');