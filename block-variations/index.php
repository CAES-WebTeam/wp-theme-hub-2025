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

// 1. DEBUG: Check ACF field structure
function debug_acf_author_fields() {
    if (!is_author() || !current_user_can('edit_posts')) {
        return;
    }
    
    $author_id = get_queried_object_id();
    
    echo '<div style="background: #fff3cd; padding: 20px; margin: 20px 0; border: 2px solid #856404; border-radius: 5px;">';
    echo '<h3>🔧 ACF AUTHOR FIELDS DEBUG</h3>';
    echo '<p>Looking for author ID: ' . $author_id . '</p>';
    
    // Get some sample posts to check
    $sample_posts = get_posts(array(
        'post_type' => 'post',
        'posts_per_page' => 5,
        'post_status' => 'publish'
    ));
    
    foreach ($sample_posts as $post) {
        echo '<div style="background: #f8f9fa; padding: 15px; margin: 10px 0; border: 1px solid #dee2e6;">';
        echo '<h4>' . esc_html($post->post_title) . ' (ID: ' . $post->ID . ')</h4>';
        
        // Check raw ACF fields
        $authors = get_field('authors', $post->ID);
        $experts = get_field('experts', $post->ID);
        
        echo '<h5>Authors Repeater Field:</h5>';
        if ($authors) {
            echo '<pre style="background: #fff; padding: 10px; font-size: 12px; overflow: auto;">';
            print_r($authors);
            echo '</pre>';
            
            echo '<p><strong>Processing each author row:</strong></p>';
            foreach ($authors as $index => $author) {
                echo '<div style="background: #e9ecef; padding: 5px; margin: 2px 0;">';
                echo "Row {$index}: ";
                if (isset($author['user'])) {
                    echo "✅ 'user' field found = " . $author['user'];
                    if (is_object($author['user'])) {
                        echo " (OBJECT - ID: " . $author['user']->ID . ")";
                    }
                } else {
                    echo "❌ 'user' field missing. Available keys: " . implode(', ', array_keys($author));
                }
                echo '</div>';
            }
        } else {
            echo '<p>No authors repeater data</p>';
        }
        
        echo '<h5>Experts Repeater Field:</h5>';
        if ($experts) {
            echo '<pre style="background: #fff; padding: 10px; font-size: 12px; overflow: auto;">';
            print_r($experts);
            echo '</pre>';
        } else {
            echo '<p>No experts repeater data</p>';
        }
        
        // Check the flattened meta fields
        $all_author_ids = get_post_meta($post->ID, 'all_author_ids', true);
        $all_expert_ids = get_post_meta($post->ID, 'all_expert_ids', true);
        
        echo '<h5>Flattened Meta Fields:</h5>';
        echo '<p><strong>all_author_ids:</strong> ';
        if ($all_author_ids) {
            echo '<code>' . print_r($all_author_ids, true) . '</code>';
            if (in_array($author_id, $all_author_ids)) {
                echo ' ✅ CONTAINS CURRENT AUTHOR!';
            }
        } else {
            echo 'empty';
        }
        echo '</p>';
        
        echo '<p><strong>all_expert_ids:</strong> ';
        if ($all_expert_ids) {
            echo '<code>' . print_r($all_expert_ids, true) . '</code>';
            if (in_array($author_id, $all_expert_ids)) {
                echo ' ✅ CONTAINS CURRENT AUTHOR!';
            }
        } else {
            echo 'empty';
        }
        echo '</p>';
        
        echo '</div>';
    }
    
    echo '</div>';
}
add_action('wp_head', 'debug_acf_author_fields');

// 2. IMPROVED: Update the story feed filter to handle arrays correctly
function variations_query_filter_fixed($query, $block)
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

    // Handle blocks WITH namespace (your variations)
    if (isset($parsed_block['attrs']['namespace'])) {
        $namespace = $parsed_block['attrs']['namespace'];

        // For stories-feed blocks using ARRAY format (not serialized string)
        if ('stories-feed' === $namespace) {
            // Filter by author (expert OR author) if on author archive
            if (is_author()) {
                $author_id = get_queried_object_id();
                
                // Create an OR condition to check both expert and author fields
                // Using LIKE with serialized array format that WordPress creates
                $meta_query[] = array(
                    'relation' => 'OR',
                    array(
                        'key' => 'all_expert_ids',
                        'value' => serialize(strval($author_id)), // Check for string version
                        'compare' => 'LIKE'
                    ),
                    array(
                        'key' => 'all_expert_ids', 
                        'value' => serialize(intval($author_id)), // Check for integer version
                        'compare' => 'LIKE'
                    ),
                    array(
                        'key' => 'all_author_ids',
                        'value' => serialize(strval($author_id)), // Check for string version
                        'compare' => 'LIKE'
                    ),
                    array(
                        'key' => 'all_author_ids',
                        'value' => serialize(intval($author_id)), // Check for integer version
                        'compare' => 'LIKE'
                    )
                );
            }
        }

        // For pubs-feed blocks (keep existing logic)
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

            // Filter by author if on author archive (assuming same array format)
            if (is_author()) {
                $author_id = get_queried_object_id();
                $meta_query[] = array(
                    'relation' => 'OR',
                    array(
                        'key' => 'all_author_ids',
                        'value' => serialize(strval($author_id)),
                        'compare' => 'LIKE'
                    ),
                    array(
                        'key' => 'all_author_ids',
                        'value' => serialize(intval($author_id)),
                        'compare' => 'LIKE'
                    )
                );
            }
        }

        // For upcoming-events blocks (keep existing)
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

    return $query;
}

// Replace the old filter with this new one
// remove_filter('query_loop_block_query_vars', 'variations_query_filter', 10, 2);
// add_filter('query_loop_block_query_vars', 'variations_query_filter_fixed', 10, 2);

// 3. FIX: Improved ACF save function to handle different user field formats
function update_flat_author_ids_meta_improved($post_id)
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!in_array(get_post_type($post_id), ['publications', 'post'])) return;

    // Get ACF repeater field called 'authors'
    $authors = get_field('authors', $post_id);

    if (!$authors || !is_array($authors)) {
        delete_post_meta($post_id, 'all_author_ids');
        return;
    }

    $author_ids = [];

    foreach ($authors as $author) {
        error_log("🔍 Full author array: " . print_r($author, true));
        
        // Handle different possible field structures
        if (!empty($author['user'])) {
            $user_value = $author['user'];
            
            // If it's a user object (from user field)
            if (is_object($user_value) && isset($user_value->ID)) {
                $author_ids[] = (int) $user_value->ID;
                error_log("✅ Found user object ID: " . $user_value->ID);
            }
            // If it's already a numeric ID
            elseif (is_numeric($user_value)) {
                $author_ids[] = (int) $user_value;
                error_log("✅ Found numeric user ID: " . $user_value);
            }
            // If it's an array with ID (some ACF formats)
            elseif (is_array($user_value) && isset($user_value['ID'])) {
                $author_ids[] = (int) $user_value['ID'];
                error_log("✅ Found user array ID: " . $user_value['ID']);
            }
            else {
                error_log("⚠️ Unknown user field format: " . gettype($user_value) . " = " . print_r($user_value, true));
            }
        } else {
            error_log("⚠️ No 'user' field found. Available keys: " . implode(', ', array_keys($author)));
        }
    }

    if (!empty($author_ids)) {
        update_post_meta($post_id, 'all_author_ids', $author_ids);
        error_log("✅ Updated all_author_ids for post {$post_id}: " . implode(', ', $author_ids));
    } else {
        delete_post_meta($post_id, 'all_author_ids');
        error_log("❌ No valid author IDs found for post {$post_id}");
    }
}

// Temporarily replace the function to test
// remove_action('acf/save_post', 'update_flat_author_ids_meta', 20);
// add_action('acf/save_post', 'update_flat_author_ids_meta_improved', 20);