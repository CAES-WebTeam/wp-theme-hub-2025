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





// Debug function to check meta values for stories
function debug_story_meta_fields() {
    // Only run on author pages for logged-in users who can edit posts
    if (!is_author() || !current_user_can('edit_posts')) {
        return;
    }
    
    $author_id = get_queried_object_id();
    echo '<div style="background: #f0f0f0; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">';
    echo '<h3>DEBUG: Story Feed Meta Fields</h3>';
    echo '<p><strong>Current Author ID:</strong> ' . $author_id . '</p>';
    
    // Get all stories (adjust post type if needed)
    $stories = get_posts(array(
        'post_type' => 'post', // Change to 'stories' if that's your post type
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ));
    
    echo '<h4>Stories and their meta values:</h4>';
    echo '<table style="border-collapse: collapse; width: 100%;">';
    echo '<tr style="background: #ddd;">
            <th style="border: 1px solid #999; padding: 8px;">Story Title</th>
            <th style="border: 1px solid #999; padding: 8px;">all_author_ids</th>
            <th style="border: 1px solid #999; padding: 8px;">all_expert_ids</th>
            <th style="border: 1px solid #999; padding: 8px;">Matches Current Author?</th>
          </tr>';
    
    foreach ($stories as $story) {
        $author_ids = get_post_meta($story->ID, 'all_author_ids', true);
        $expert_ids = get_post_meta($story->ID, 'all_expert_ids', true);
        
        // Check if current author is in either field
        $author_match = strpos($author_ids, 'i:' . $author_id . ';') !== false;
        $expert_match = strpos($expert_ids, 'i:' . $author_id . ';') !== false;
        $matches = $author_match || $expert_match;
        
        $row_style = $matches ? 'background: #d4edda;' : '';
        
        echo '<tr style="' . $row_style . '">';
        echo '<td style="border: 1px solid #999; padding: 8px;">' . esc_html($story->post_title) . '</td>';
        echo '<td style="border: 1px solid #999; padding: 8px; font-family: monospace;">' . esc_html($author_ids ?: 'empty') . '</td>';
        echo '<td style="border: 1px solid #999; padding: 8px; font-family: monospace;">' . esc_html($expert_ids ?: 'empty') . '</td>';
        echo '<td style="border: 1px solid #999; padding: 8px;">';
        if ($author_match) echo 'AUTHOR ✓ ';
        if ($expert_match) echo 'EXPERT ✓ ';
        if (!$matches) echo 'NO MATCH';
        echo '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    
    // Show what the query should look like
    echo '<h4>Expected Query Meta:</h4>';
    echo '<pre style="background: #fff; padding: 10px; border: 1px solid #999;">';
    echo "Looking for: 'i:" . $author_id . ";' in either field\n";
    echo "Meta Query should be:\n";
    print_r(array(
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
    ));
    echo '</pre>';
    
    echo '</div>';
}

// Hook it to run early on author pages
add_action('wp_head', 'debug_story_meta_fields');

// Alternative: Add to the beginning of content for easier viewing
function debug_story_meta_in_content($content) {
    if (is_author() && current_user_can('edit_posts') && is_main_query()) {
        ob_start();
        debug_story_meta_fields();
        $debug_output = ob_get_clean();
        return $debug_output . $content;
    }
    return $content;
}
// Uncomment this line if you want it in the content area instead:
// add_filter('the_content', 'debug_story_meta_in_content');

// Debug the actual query being run
function debug_story_query($query_vars, $block) {
    if (!is_author() || !current_user_can('edit_posts')) {
        return $query_vars;
    }
    
    // Check if this is a stories-feed block
    if (isset($block->parsed_block['attrs']['namespace']) && 
        $block->parsed_block['attrs']['namespace'] === 'stories-feed') {
        
        echo '<div style="background: #fff3cd; padding: 15px; margin: 10px 0; border: 1px solid #ffeaa7;">';
        echo '<h4>DEBUG: Stories Query Variables</h4>';
        echo '<pre style="background: #fff; padding: 10px; border: 1px solid #ddd; overflow: auto;">';
        print_r($query_vars);
        echo '</pre>';
        echo '</div>';
    }
    
    return $query_vars;
}
add_filter('query_loop_block_query_vars', 'debug_story_query', 999, 2);

// Quick function to check a specific post's meta
function check_specific_post_meta($post_id) {
    if (!current_user_can('edit_posts')) return;
    
    $author_ids = get_post_meta($post_id, 'all_author_ids', true);
    $expert_ids = get_post_meta($post_id, 'all_expert_ids', true);
    
    echo "<div style='background: #f8f9fa; padding: 10px; margin: 10px; border: 1px solid #dee2e6;'>";
    echo "<strong>Post ID {$post_id}:</strong><br>";
    echo "all_author_ids: " . ($author_ids ?: 'empty') . "<br>";
    echo "all_expert_ids: " . ($expert_ids ?: 'empty') . "<br>";
    echo "</div>";
}

// Usage: add this anywhere in a template to check specific posts
// check_specific_post_meta(123); // Replace 123 with actual post ID