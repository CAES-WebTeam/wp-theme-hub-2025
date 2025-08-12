<?php
/**
 * Custom Date Sorting and Display for ACF Fields
 * File: /inc/acf-custom-date-sorting.php
 * 
 * Handles custom date sorting for posts (using release_date_new ACF field)
 * and publications (using latest publish date from history repeater field)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// 1. Helper function to get the custom date for any post
function get_custom_post_date($post_id = null, $format = 'Y-m-d H:i:s') {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $post_type = get_post_type($post_id);
    
    if ($post_type === 'publications') {
        return get_publications_latest_publish_date($post_id, $format);
    } elseif ($post_type === 'post') {
        $release_date = get_field('release_date_new', $post_id);
        if ($release_date) {
            // ACF returns dates in Y-m-d format by default
            $timestamp = strtotime($release_date);
            return date($format, $timestamp);
        }
    }
    
    // Fallback to WordPress publish date (directly from post object to avoid loops)
    $post = get_post($post_id);
    if ($post) {
        return date($format, strtotime($post->post_date));
    }
    
    return date($format); // Ultimate fallback to current time
}

// 2. Helper function for publications latest publish date
function get_publications_latest_publish_date($post_id, $format = 'Y-m-d H:i:s') {
    $history = get_field('history', $post_id);
    
    if (!$history || !is_array($history)) {
        // Fallback to WordPress publish date (directly from post object)
        $post = get_post($post_id);
        return $post ? date($format, strtotime($post->post_date)) : date($format);
    }
    
    $latest_publish_date = null;
    $latest_timestamp = 0;
    
    foreach ($history as $entry) {
        $status = $entry['status'] ?? '';
        $date = $entry['date'] ?? '';
        
        // Check if status contains "publish" (case insensitive)
        if (stripos($status, 'publish') !== false && !empty($date)) {
            $timestamp = strtotime($date);
            if ($timestamp > $latest_timestamp) {
                $latest_timestamp = $timestamp;
                $latest_publish_date = $date;
            }
        }
    }
    
    if ($latest_publish_date) {
        return date($format, strtotime($latest_publish_date));
    }
    
    // Fallback to WordPress publish date (directly from post object)
    $post = get_post($post_id);
    return $post ? date($format, strtotime($post->post_date)) : date($format);
}

// 3. Create/update a meta field for publications to store computed date (for better performance)
function update_publications_computed_date($post_id) {
    if (get_post_type($post_id) !== 'publications') {
        return;
    }
    
    $custom_date = get_publications_latest_publish_date($post_id, 'Y-m-d H:i:s');
    update_post_meta($post_id, '_computed_publish_date', $custom_date);
}

// Hook to update computed date when publication is saved
add_action('acf/save_post', 'update_publications_computed_date', 20);

// 4. Modify queries to sort by custom dates
function modify_query_for_custom_dates($query) {
    // Only modify main queries and Query Loop blocks
    if (is_admin() || (!$query->is_main_query() && !isset($query->query_vars['block_query']))) {
        return;
    }
    
    // Check if we're dealing with posts or publications
    $post_types = $query->get('post_type');
    if (empty($post_types)) {
        $post_types = ['post']; // Default
    }
    
    if (!is_array($post_types)) {
        $post_types = [$post_types];
    }
    
    $has_custom_date_types = array_intersect($post_types, ['post', 'publications']);
    
    if (!empty($has_custom_date_types) && $query->get('orderby') === 'date') {
        // For mixed post types, we need a custom approach
        if (in_array('publications', $post_types) && in_array('post', $post_types)) {
            $query->set('orderby', 'meta_value');
            $query->set('meta_key', '_computed_date_for_sorting');
            $query->set('meta_type', 'DATETIME');
        }
        // For publications only
        elseif (in_array('publications', $post_types)) {
            $query->set('orderby', 'meta_value');
            $query->set('meta_key', '_computed_publish_date');
            $query->set('meta_type', 'DATETIME');
        }
        // For posts only
        elseif (in_array('post', $post_types)) {
            $query->set('orderby', 'meta_value');
            $query->set('meta_key', 'release_date_new');
            $query->set('meta_type', 'DATE');
        }
    }
}
add_action('pre_get_posts', 'modify_query_for_custom_dates');

// 5. Handle Query Loop blocks specifically
function modify_query_loop_for_custom_dates($query_vars, $block, $page) {
    $post_types = $query_vars['post_type'] ?? ['post'];
    
    if (!is_array($post_types)) {
        $post_types = [$post_types];
    }
    
    $has_custom_date_types = array_intersect($post_types, ['post', 'publications']);
    
    if (!empty($has_custom_date_types) && isset($query_vars['orderby']) && $query_vars['orderby'] === 'date') {
        // Add a flag to identify this as a block query
        $query_vars['block_query'] = true;
        
        // Set up meta query for custom date sorting
        if (in_array('publications', $post_types) && in_array('post', $post_types)) {
            $query_vars['orderby'] = 'meta_value';
            $query_vars['meta_key'] = '_computed_date_for_sorting';
            $query_vars['meta_type'] = 'DATETIME';
        }
        elseif (in_array('publications', $post_types)) {
            $query_vars['orderby'] = 'meta_value';
            $query_vars['meta_key'] = '_computed_publish_date';
            $query_vars['meta_type'] = 'DATETIME';
        }
        elseif (in_array('post', $post_types)) {
            $query_vars['orderby'] = 'meta_value';
            $query_vars['meta_key'] = 'release_date_new';
            $query_vars['meta_type'] = 'DATE';
        }
    }
    
    return $query_vars;
}
add_filter('query_loop_block_query_vars', 'modify_query_loop_for_custom_dates', 10, 3);

// 6. Update computed date for posts when saved
function update_post_computed_date($post_id) {
    if (get_post_type($post_id) === 'post') {
        $release_date = get_field('release_date_new', $post_id);
        if ($release_date) {
            $formatted_date = date('Y-m-d H:i:s', strtotime($release_date));
            update_post_meta($post_id, '_computed_date_for_sorting', $formatted_date);
        }
    }
}
add_action('acf/save_post', 'update_post_computed_date', 20);

// 7. Filter the displayed date in date blocks and get_the_date() calls
// TEMPORARILY DISABLED to prevent infinite loops - will fix after testing queries
/*
function filter_displayed_date($date, $format, $post) {
    // Prevent infinite loops
    static $processing = false;
    if ($processing || !$post) {
        return $date;
    }
    
    $processing = true;
    
    $post_type = get_post_type($post->ID);
    
    if ($post_type === 'publications') {
        $custom_date = get_publications_latest_publish_date($post->ID, $format);
        $original_date = get_post_field('post_date', $post->ID);
        $original_formatted = date($format, strtotime($original_date));
        
        if ($custom_date && $custom_date !== $original_formatted) {
            $processing = false;
            return $custom_date;
        }
    } elseif ($post_type === 'post') {
        $release_date = get_field('release_date_new', $post->ID);
        if ($release_date) {
            $custom_formatted = date($format, strtotime($release_date));
            $processing = false;
            return $custom_formatted;
        }
    }
    
    $processing = false;
    return $date;
}
add_filter('get_the_date', 'filter_displayed_date', 10, 3);
*/

// 8. Also filter get_the_time for time displays  
// TEMPORARILY DISABLED - will re-enable after testing
/*
function filter_displayed_time($time, $format, $post) {
    // Similar logic as above but for time formats
    return $time;
}
add_filter('get_the_time', 'filter_displayed_time', 10, 3);
*/

// 9. Populate computed dates for existing posts (run once)
function populate_existing_computed_dates() {
    // Uncomment and run this once to populate existing posts
    /*
    $posts = get_posts([
        'post_type' => ['post', 'publications'],
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ]);
    
    foreach ($posts as $post) {
        if ($post->post_type === 'publications') {
            update_publications_computed_date($post->ID);
        } elseif ($post->post_type === 'post') {
            update_post_computed_date($post->ID);
        }
    }
    */
}

// 10. TEMPORARY ADMIN TOOL - Date Sorting Preview
function add_acf_date_sorting_admin_tool() {
    add_submenu_page(
        'caes-tools',  // Parent slug (assumes caes-tools menu exists)
        'ACF Date Sorting Preview',
        'Date Sorting Preview',
        'manage_options',
        'acf-date-sorting-preview',
        'render_acf_date_sorting_preview_page'
    );
}
add_action('admin_menu', 'add_acf_date_sorting_admin_tool');

function render_acf_date_sorting_preview_page() {
    // Get sample posts and publications
    $posts = get_posts([
        'post_type' => 'post',
        'posts_per_page' => 10,
        'post_status' => 'publish'
    ]);
    
    $publications = get_posts([
        'post_type' => 'publications', 
        'posts_per_page' => 10,
        'post_status' => 'publish'
    ]);
    
    ?>
    <div class="wrap">
        <h1>ACF Date Sorting Preview</h1>
        <p><strong>This is a dry run preview.</strong> No data will be saved. This shows what computed dates would be created.</p>
        
        <?php if (!empty($posts)): ?>
        <h2>Posts (using release_date_new field)</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Post Title</th>
                    <th>WordPress Publish Date</th>
                    <th>ACF release_date_new</th>
                    <th>Computed Date (What would be saved)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $post): ?>
                <tr>
                    <td><strong><?php echo esc_html($post->post_title); ?></strong></td>
                    <td><?php echo get_post_field('post_date', $post->ID); ?></td>
                    <td>
                        <?php 
                        $release_date = get_field('release_date_new', $post->ID);
                        echo $release_date ? esc_html($release_date) : '<em>No release_date_new field</em>';
                        ?>
                    </td>
                    <td>
                        <?php 
                        $computed_date = get_custom_post_date($post->ID, 'Y-m-d H:i:s');
                        echo esc_html($computed_date);
                        ?>
                    </td>
                    <td>
                        <?php 
                        if ($release_date) {
                            $release_timestamp = strtotime($release_date);
                            $publish_timestamp = strtotime($post->post_date);
                            
                            if ($release_timestamp > $publish_timestamp) {
                                echo '<span style="color: orange;">Release date is AFTER publish date</span>';
                            } elseif ($release_timestamp < $publish_timestamp) {
                                echo '<span style="color: blue;">Release date is BEFORE publish date</span>';
                            } else {
                                echo '<span style="color: green;">Dates match</span>';
                            }
                        } else {
                            echo '<span style="color: red;">No custom date - using publish date</span>';
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p><em>No posts found.</em></p>
        <?php endif; ?>
        
        <?php if (!empty($publications)): ?>
        <h2>Publications (using latest publish date from history repeater)</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Publication Title</th>
                    <th>WordPress Publish Date</th>
                    <th>History Field Data</th>
                    <th>Latest Publish Date Found</th>
                    <th>Computed Date (What would be saved)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($publications as $publication): ?>
                <tr>
                    <td><strong><?php echo esc_html($publication->post_title); ?></strong></td>
                    <td><?php echo get_post_field('post_date', $publication->ID); ?></td>
                    <td>
                        <?php 
                        $history = get_field('history', $publication->ID);
                        if ($history && is_array($history)) {
                            echo '<ul style="margin: 0; padding-left: 20px;">';
                            foreach ($history as $entry) {
                                $status = $entry['status'] ?? 'No status';
                                $date = $entry['date'] ?? 'No date';
                                $is_publish = stripos($status, 'publish') !== false;
                                
                                echo '<li>';
                                if ($is_publish) {
                                    echo '<strong style="color: green;">' . esc_html($status) . '</strong>';
                                } else {
                                    echo esc_html($status);
                                }
                                echo ': ' . esc_html($date);
                                echo '</li>';
                            }
                            echo '</ul>';
                        } else {
                            echo '<em>No history field data</em>';
                        }
                        ?>
                    </td>
                    <td>
                        <?php 
                        $latest_publish_date = get_publications_latest_publish_date($publication->ID, 'Y-m-d');
                        $wp_publish_date = get_post_field('post_date', $publication->ID);
                        $is_fallback = ($latest_publish_date === date('Y-m-d', strtotime($wp_publish_date)));
                        
                        if ($is_fallback) {
                            echo '<em style="color: red;">No publish dates found - using fallback</em>';
                        } else {
                            echo '<strong style="color: green;">' . esc_html($latest_publish_date) . '</strong>';
                        }
                        ?>
                    </td>
                    <td>
                        <?php 
                        $computed_date = get_custom_post_date($publication->ID, 'Y-m-d H:i:s');
                        echo esc_html($computed_date);
                        ?>
                    </td>
                    <td>
                        <?php 
                        if ($is_fallback) {
                            echo '<span style="color: red;">Using WordPress publish date (no publish status found)</span>';
                        } else {
                            $wp_publish_date = get_post_field('post_date', $publication->ID);
                            $publish_timestamp = strtotime($wp_publish_date);
                            $latest_timestamp = strtotime($latest_publish_date);
                            
                            if ($latest_timestamp > $publish_timestamp) {
                                echo '<span style="color: orange;">Latest publish date is AFTER WordPress publish date</span>';
                            } elseif ($latest_timestamp < $publish_timestamp) {
                                echo '<span style="color: blue;">Latest publish date is BEFORE WordPress publish date</span>';
                            } else {
                                echo '<span style="color: green;">Dates match</span>';
                            }
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p><em>No publications found.</em></p>
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding: 20px; background: #f0f8ff; border-left: 4px solid #0073aa;">
            <h3>Next Steps:</h3>
            <p>If the computed dates look correct above, you can run the actual population by:</p>
            <ol>
                <li>Uncommenting the code in the <code>populate_existing_computed_dates()</code> function</li>
                <li>Calling that function once (you can add a temporary admin page or run it via WP-CLI)</li>
                <li>Removing this preview tool once you're done</li>
            </ol>
            <p><strong>Note:</strong> This preview shows a maximum of 10 posts and 10 publications. The actual populate function will process all published posts.</p>
        </div>
        
        <div style="margin-top: 20px;">
            <h3>Legend:</h3>
            <ul>
                <li><span style="color: green;">●</span> <strong>Green:</strong> Dates match or publish status found</li>
                <li><span style="color: orange;">●</span> <strong>Orange:</strong> Custom date is after WordPress publish date</li>
                <li><span style="color: blue;">●</span> <strong>Blue:</strong> Custom date is before WordPress publish date</li>
                <li><span style="color: red;">●</span> <strong>Red:</strong> No custom date found, using fallback</li>
            </ul>
        </div>
    </div>
    
    <style>
    .wp-list-table td {
        vertical-align: top;
    }
    .wp-list-table ul {
        font-size: 12px;
    }
    </style>
    <?php
}

// 11. Custom query function for hardcoded PHP queries
function get_posts_with_custom_date_sorting($args = []) {
    $defaults = [
        'post_type' => ['post', 'publications'],
        'posts_per_page' => 10,
        'orderby' => 'meta_value',
        'meta_key' => '_computed_date_for_sorting',
        'meta_type' => 'DATETIME',
        'order' => 'DESC'
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    // If only one post type, use specific meta key
    if (is_array($args['post_type']) && count($args['post_type']) === 1) {
        if ($args['post_type'][0] === 'publications') {
            $args['meta_key'] = '_computed_publish_date';
        } elseif ($args['post_type'][0] === 'post') {
            $args['meta_key'] = 'release_date_new';
            $args['meta_type'] = 'DATE';
        }
    }
    
    return get_posts($args);
}

// 11. Template function for themes
function the_custom_date($format = 'F j, Y') {
    echo get_custom_post_date(get_the_ID(), $format);
}

function get_the_custom_date($format = 'F j, Y', $post_id = null) {
    return get_custom_post_date($post_id, $format);
}