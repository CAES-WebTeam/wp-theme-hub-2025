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
    
    // Status mapping
    $status_labels = [
        1 => 'Unpublished/Removed',
        2 => 'Published',
        4 => 'Published with Minor Revisions',
        5 => 'Published with Major Revisions',
        6 => 'Published with Full Review',
        7 => 'Historic/Archived',
        8 => 'In Review for Minor Revisions',
        9 => 'In Review for Major Revisions',
        10 => 'In Review'
    ];
    
    // Published status IDs
    $published_statuses = [2, 4, 5, 6];
    
    $latest_publish_date = null;
    $latest_timestamp = 0;
    
    foreach ($history as $entry) {
        $status_raw = $entry['status'] ?? '';
        $date = $entry['date'] ?? '';
        
        // Check if status is one of the published statuses
        $is_publish = in_array((int)$status_raw, $published_statuses);
        
        if ($is_publish && !empty($date)) {
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
    
    // Also update the unified sorting field for mixed queries
    update_post_meta($post_id, '_computed_date_for_sorting', $custom_date);
}

// Hook to update computed date when publication is saved
add_action('acf/save_post', 'update_publications_computed_date', 20);

// Also hook into regular WordPress saves to catch all save scenarios
add_action('save_post', 'update_computed_dates_on_wp_save', 20, 2);

function update_computed_dates_on_wp_save($post_id, $post) {
    // Skip autosaves and revisions
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }
    
    // Only process published posts
    if ($post->post_status !== 'publish') {
        return;
    }
    
    // Only process our target post types
    if (!in_array($post->post_type, ['post', 'publications'])) {
        return;
    }
    
    if ($post->post_type === 'publications') {
        update_publications_computed_date($post_id);
    } elseif ($post->post_type === 'post') {
        update_post_computed_date($post_id);
    }
}

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

// 6. Update computed date for posts when saved (via ACF or regular WordPress save)
function update_post_computed_date($post_id) {
    if (get_post_type($post_id) === 'post') {
        $release_date = get_field('release_date_new', $post_id);
        if ($release_date) {
            $formatted_date = date('Y-m-d H:i:s', strtotime($release_date));
            update_post_meta($post_id, '_computed_date_for_sorting', $formatted_date);
        } else {
            // If no custom date, use WordPress publish date
            $post = get_post($post_id);
            if ($post) {
                update_post_meta($post_id, '_computed_date_for_sorting', $post->post_date);
            }
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
    // Add this action temporarily to run the population
    // Visit: yoursite.com/wp-admin/admin.php?page=acf-date-sorting-preview&run_population=1
    
    $posts = get_posts([
        'post_type' => ['post', 'publications'],
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ]);
    
    $updated_count = 0;
    $total_count = count($posts);
    
    foreach ($posts as $post) {
        if ($post->post_type === 'publications') {
            update_publications_computed_date($post->ID);
            $updated_count++;
        } elseif ($post->post_type === 'post') {
            update_post_computed_date($post->ID);
            $updated_count++;
        }
        
        // Prevent timeout on large sites
        if ($updated_count % 50 === 0) {
            sleep(1); // Brief pause every 50 posts
        }
    }
    
    return ['updated' => $updated_count, 'total' => $total_count];
}

// Add population trigger to the admin tool
function run_population_if_requested() {
    if (isset($_GET['run_population']) && $_GET['run_population'] === '1' && current_user_can('manage_options')) {
        // Add a nonce check for security
        if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'run_population')) {
            $result = populate_existing_computed_dates();
            
            // Redirect to avoid re-running on refresh
            $redirect_url = admin_url('admin.php?page=acf-date-sorting-preview&populated=1&updated=' . $result['updated'] . '&total=' . $result['total']);
            wp_redirect($redirect_url);
            exit;
        }
    }
}
add_action('admin_init', 'run_population_if_requested');

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
    // Get pagination parameters
    $posts_page = isset($_GET['posts_page']) ? max(1, intval($_GET['posts_page'])) : 1;
    $pubs_page = isset($_GET['pubs_page']) ? max(1, intval($_GET['pubs_page'])) : 1;
    $per_page = 15; // Show more per page
    
    // Calculate offsets
    $posts_offset = ($posts_page - 1) * $per_page;
    $pubs_offset = ($pubs_page - 1) * $per_page;
    
    // Get total counts for pagination
    $total_posts = wp_count_posts('post')->publish;
    $total_publications = wp_count_posts('publications')->publish;
    
    // Calculate total pages
    $total_posts_pages = ceil($total_posts / $per_page);
    $total_pubs_pages = ceil($total_publications / $per_page);
    
    // Get sample posts and publications with pagination
    $posts = get_posts([
        'post_type' => 'post',
        'posts_per_page' => $per_page,
        'offset' => $posts_offset,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC'
    ]);
    
    $publications = get_posts([
        'post_type' => 'publications', 
        'posts_per_page' => $per_page,
        'offset' => $pubs_offset,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC'
    ]);
    
    // Helper function to build pagination URL
    function build_pagination_url($posts_page_num = null, $pubs_page_num = null) {
        $current_posts_page = isset($_GET['posts_page']) ? intval($_GET['posts_page']) : 1;
        $current_pubs_page = isset($_GET['pubs_page']) ? intval($_GET['pubs_page']) : 1;
        
        $posts_page_final = $posts_page_num !== null ? $posts_page_num : $current_posts_page;
        $pubs_page_final = $pubs_page_num !== null ? $pubs_page_num : $current_pubs_page;
        
        return admin_url('admin.php?page=acf-date-sorting-preview&posts_page=' . $posts_page_final . '&pubs_page=' . $pubs_page_final);
    }
    
    ?>
    <div class="wrap">
        <h1>ACF Date Sorting Preview</h1>
        
        <?php 
        // Show success message if population was run
        if (isset($_GET['populated']) && $_GET['populated'] === '1'): 
            $updated = intval($_GET['updated'] ?? 0);
            $total = intval($_GET['total'] ?? 0);
        ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>✅ Population Complete!</strong> Updated <?php echo number_format($updated); ?> of <?php echo number_format($total); ?> posts/publications with computed dates.</p>
            </div>
        <?php endif; ?>
        
        <p><strong>This is a dry run preview.</strong> No data will be saved. This shows what computed dates would be created.</p>
        
        <div style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; margin-bottom: 20px;">
            <h3>Summary & Actions</h3>
            <p><strong>Total Posts:</strong> <?php echo number_format($total_posts); ?> | <strong>Total Publications:</strong> <?php echo number_format($total_publications); ?></p>
            <p>Showing <?php echo $per_page; ?> items per page. Use pagination below to browse through older content to find examples with custom dates.</p>
            
            <?php if (!isset($_GET['populated'])): ?>
            <div style="margin-top: 15px; padding: 15px; background: #e7f3ff; border-left: 4px solid #0073aa;">
                <h4>🚀 Ready to Populate Computed Dates?</h4>
                <p>If the preview looks correct, you can populate the computed date meta fields for all posts:</p>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=acf-date-sorting-preview&run_population=1'), 'run_population'); ?>" 
                   class="button button-primary button-large"
                   onclick="return confirm('This will update ALL published posts and publications with computed dates. This may take a few minutes. Continue?')">
                   🔄 Populate All Computed Dates
                </a>
                <p><small><strong>⚠️ This will process <?php echo number_format($total_posts + $total_publications); ?> total items.</strong> Large sites may take several minutes.</small></p>
            </div>
            <?php else: ?>
            <div style="margin-top: 15px; padding: 15px; background: #d4edda; border-left: 4px solid #28a745;">
                <h4>✅ Population Complete!</h4>
                <p>All posts and publications now have computed date meta fields. You can:</p>
                <ul>
                    <li>Test your Query Loop blocks to verify custom date sorting</li>
                    <li>Re-enable the date display filters if needed</li>
                    <li>Remove this admin tool once everything is working</li>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($posts)): ?>
        <h2>Posts (using release_date_new field)</h2>
        <div style="margin-bottom: 10px;">
            <strong>Page <?php echo $posts_page; ?> of <?php echo $total_posts_pages; ?></strong> | 
            Showing posts <?php echo ($posts_offset + 1); ?>-<?php echo min($posts_offset + $per_page, $total_posts); ?> of <?php echo number_format($total_posts); ?>
        </div>
        
        <!-- Posts Pagination -->
        <div class="tablenav" style="margin-bottom: 10px;">
            <div class="tablenav-pages">
                <?php if ($posts_page > 1): ?>
                    <a class="button" href="<?php echo build_pagination_url(1); ?>">&laquo; First</a>
                    <a class="button" href="<?php echo build_pagination_url($posts_page - 1); ?>">&lsaquo; Previous</a>
                <?php endif; ?>
                
                <span class="paging-input">
                    Page <input type="number" min="1" max="<?php echo $total_posts_pages; ?>" value="<?php echo $posts_page; ?>" 
                           onchange="window.location.href='<?php echo build_pagination_url(); ?>'.replace('posts_page=<?php echo $posts_page; ?>', 'posts_page=' + this.value)" 
                           style="width: 60px;"> 
                    of <?php echo number_format($total_posts_pages); ?>
                </span>
                
                <?php if ($posts_page < $total_posts_pages): ?>
                    <a class="button" href="<?php echo build_pagination_url($posts_page + 1); ?>">Next &rsaquo;</a>
                    <a class="button" href="<?php echo build_pagination_url($total_posts_pages); ?>">Last &raquo;</a>
                <?php endif; ?>
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 25%;">Post Title (Click to Edit)</th>
                    <th style="width: 15%;">WordPress Publish Date</th>
                    <th style="width: 15%;">ACF release_date_new</th>
                    <th style="width: 20%;">Computed Date (What would be saved)</th>
                    <th style="width: 25%;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $posts_with_custom_dates = 0;
                foreach ($posts as $post): 
                    $release_date = get_field('release_date_new', $post->ID);
                    if ($release_date) $posts_with_custom_dates++;
                ?>
                <tr <?php echo $release_date ? 'style="background-color: #f0f8ff;"' : ''; ?>>
                    <td>
                        <strong>
                            <a href="<?php echo get_edit_post_link($post->ID); ?>" target="_blank" title="Edit this post">
                                <?php echo esc_html($post->post_title); ?>
                            </a>
                        </strong>
                        <div style="font-size: 11px; color: #666; margin-top: 2px;">
                            ID: <?php echo $post->ID; ?> | 
                            <a href="<?php echo get_permalink($post->ID); ?>" target="_blank" style="text-decoration: none;">View →</a>
                        </div>
                    </td>
                    <td><?php echo get_post_field('post_date', $post->ID); ?></td>
                    <td>
                        <?php 
                        echo $release_date ? '<strong style="color: green;">' . esc_html($release_date) . '</strong>' : '<em>No release_date_new field</em>';
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
        
        <div style="margin: 10px 0; padding: 10px; background: #e7f3ff; border-left: 4px solid #0073aa;">
            <strong>Posts Summary:</strong> <?php echo $posts_with_custom_dates; ?> of <?php echo count($posts); ?> posts on this page have custom release dates.
            <?php if ($posts_with_custom_dates === 0): ?>
                <em>Try going to later pages to find posts with custom dates.</em>
            <?php endif; ?>
        </div>
        
        <?php else: ?>
        <p><em>No posts found.</em></p>
        <?php endif; ?>
        
        <?php if (!empty($publications)): ?>
        <h2>Publications (using latest publish date from history repeater)</h2>
        <div style="margin-bottom: 10px; margin-top: 30px;">
            <strong>Page <?php echo $pubs_page; ?> of <?php echo $total_pubs_pages; ?></strong> | 
            Showing publications <?php echo ($pubs_offset + 1); ?>-<?php echo min($pubs_offset + $per_page, $total_publications); ?> of <?php echo number_format($total_publications); ?>
        </div>
        
        <!-- Publications Pagination -->
        <div class="tablenav" style="margin-bottom: 10px;">
            <div class="tablenav-pages">
                <?php if ($pubs_page > 1): ?>
                    <a class="button" href="<?php echo build_pagination_url(null, 1); ?>">&laquo; First</a>
                    <a class="button" href="<?php echo build_pagination_url(null, $pubs_page - 1); ?>">&lsaquo; Previous</a>
                <?php endif; ?>
                
                <span class="paging-input">
                    Page <input type="number" min="1" max="<?php echo $total_pubs_pages; ?>" value="<?php echo $pubs_page; ?>" 
                           onchange="window.location.href='<?php echo build_pagination_url(); ?>'.replace('pubs_page=<?php echo $pubs_page; ?>', 'pubs_page=' + this.value)" 
                           style="width: 60px;"> 
                    of <?php echo number_format($total_pubs_pages); ?>
                </span>
                
                <?php if ($pubs_page < $total_pubs_pages): ?>
                    <a class="button" href="<?php echo build_pagination_url(null, $pubs_page + 1); ?>">Next &rsaquo;</a>
                    <a class="button" href="<?php echo build_pagination_url(null, $total_pubs_pages); ?>">Last &raquo;</a>
                <?php endif; ?>
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 20%;">Publication Title (Click to Edit)</th>
                    <th style="width: 12%;">WordPress Publish Date</th>
                    <th style="width: 25%;">History Field Data</th>
                    <th style="width: 15%;">Latest Publish Date Found</th>
                    <th style="width: 15%;">Computed Date</th>
                    <th style="width: 13%;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $pubs_with_publish_dates = 0;
                $published_statuses = [2, 4, 5, 6];
                
                foreach ($publications as $publication): 
                    $history = get_field('history', $publication->ID);
                    
                    // Calculate published status info once per row
                    $has_published_entries = false;
                    $latest_publish_date = null;
                    
                    if ($history && is_array($history)) {
                        $latest_timestamp = 0;
                        foreach ($history as $entry) {
                            $status_raw = $entry['status'] ?? '';
                            $date = $entry['date'] ?? '';
                            
                            if (in_array((int)$status_raw, $published_statuses) && !empty($date)) {
                                $has_published_entries = true;
                                $timestamp = strtotime($date);
                                if ($timestamp > $latest_timestamp) {
                                    $latest_timestamp = $timestamp;
                                    $latest_publish_date = $date;
                                }
                            }
                        }
                    }
                    
                    if ($has_published_entries) $pubs_with_publish_dates++;
                ?>
                <tr <?php echo $has_published_entries ? 'style="background-color: #f0f8ff;"' : ''; ?>>
                    <td>
                        <strong>
                            <a href="<?php echo get_edit_post_link($publication->ID); ?>" target="_blank" title="Edit this publication">
                                <?php echo esc_html($publication->post_title); ?>
                            </a>
                        </strong>
                        <div style="font-size: 11px; color: #666; margin-top: 2px;">
                            ID: <?php echo $publication->ID; ?> | 
                            <a href="<?php echo get_permalink($publication->ID); ?>" target="_blank" style="text-decoration: none;">View →</a>
                        </div>
                    </td>
                    <td><?php echo get_post_field('post_date', $publication->ID); ?></td>
                    <td>
                        <?php 
                        // Status mapping
                        $status_labels = [
                            1 => 'Unpublished/Removed',
                            2 => 'Published',
                            4 => 'Published with Minor Revisions',
                            5 => 'Published with Major Revisions',
                            6 => 'Published with Full Review',
                            7 => 'Historic/Archived',
                            8 => 'In Review for Minor Revisions',
                            9 => 'In Review for Major Revisions',
                            10 => 'In Review'
                        ];
                        $published_statuses = [2, 4, 5, 6];
                        
                        if ($history && is_array($history)) {
                            echo '<ul style="margin: 0; padding-left: 20px; font-size: 11px;">';
                            $count = 0;
                            foreach ($history as $entry) {
                                if ($count >= 3) {
                                    echo '<li><em>... and ' . (count($history) - 3) . ' more entries</em></li>';
                                    break;
                                }
                                $status_raw = $entry['status'] ?? 'No status';
                                $date = $entry['date'] ?? 'No date';
                                
                                // Get formatted status
                                $status_formatted = isset($status_labels[(int)$status_raw]) ? $status_labels[(int)$status_raw] : $status_raw;
                                $is_publish = in_array((int)$status_raw, $published_statuses);
                                
                                echo '<li>';
                                if ($is_publish) {
                                    echo '<strong style="color: green;">' . esc_html($status_formatted) . '</strong>';
                                } else {
                                    echo esc_html($status_formatted);
                                }
                                echo ': ' . esc_html($date);
                                echo '</li>';
                                $count++;
                            }
                            echo '</ul>';
                        } else {
                            echo '<em>No history field data</em>';
                        }
                        ?>
                    </td>
                    <td>
                        <?php 
                        if ($has_published_entries && $latest_publish_date) {
                            echo '<strong style="color: green;">' . esc_html(date('Y-m-d', strtotime($latest_publish_date))) . '</strong>';
                        } else {
                            echo '<em style="color: red;">No publish dates found</em>';
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
                        // Use the same logic as the previous column
                        if ($has_published_entries && $latest_publish_date) {
                            $wp_publish_date = get_post_field('post_date', $publication->ID);
                            $publish_timestamp = strtotime($wp_publish_date);
                            $latest_timestamp = strtotime($latest_publish_date);
                            
                            if ($latest_timestamp > $publish_timestamp) {
                                echo '<span style="color: orange;">History date AFTER WP date</span>';
                            } elseif ($latest_timestamp < $publish_timestamp) {
                                echo '<span style="color: blue;">History date BEFORE WP date</span>';
                            } else {
                                echo '<span style="color: green;">Using history date (same as WP)</span>';
                            }
                        } else {
                            echo '<span style="color: red;">Using WP date (no published status)</span>';
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="margin: 10px 0; padding: 10px; background: #e7f3ff; border-left: 4px solid #0073aa;">
            <strong>Publications Summary:</strong> <?php echo $pubs_with_publish_dates; ?> of <?php echo count($publications); ?> publications on this page have publish status dates.
            <?php if ($pubs_with_publish_dates === 0): ?>
                <em>Try going to later pages to find publications with publish status entries.</em>
            <?php endif; ?>
        </div>
        
        <?php else: ?>
        <p><em>No publications found.</em></p>
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding: 20px; background: #f0f8ff; border-left: 4px solid #0073aa;">
            <h3>About Automatic Updates:</h3>
            <p><strong>✅ All Save Methods Covered:</strong> The computed date fields will automatically update when posts/publications are saved via:</p>
            <ul>
                <li>📝 <strong>ACF Field Updates</strong> - When custom fields are modified</li>
                <li>⚡ <strong>Quick Edit</strong> - WordPress admin quick edit</li>
                <li>📊 <strong>Bulk Edit</strong> - Bulk operations in admin</li>
                <li>🖊️ <strong>Regular Editor Saves</strong> - Classic/Block editor saves</li>
                <li>🔧 <strong>Programmatic Updates</strong> - Plugin/import updates</li>
                <li>📥 <strong>Import Operations</strong> - CSV/XML imports</li>
            </ul>
            
            <h4>Next Steps After Population:</h4>
            <ol>
                <li><strong>Test Query Loop sorting:</strong> Create test Query Loop blocks sorted by date to verify custom date sorting works</li>
                <li><strong>Verify meta fields:</strong> Check that posts have <code>_computed_date_for_sorting</code> and publications have <code>_computed_publish_date</code> meta fields</li>
                <li><strong>Test automatic updates:</strong> Edit a post's release_date_new or publication's history to verify meta fields update automatically</li>
                <li><strong>Optional:</strong> Re-enable date display filters if you want automatic date override in templates</li>
                <li><strong>Clean up:</strong> Remove this admin tool once everything works correctly</li>
            </ol>
        </div>
        
        <div style="margin-top: 20px;">
            <h3>Legend & Instructions:</h3>
            <ul>
                <li><span style="color: green;">●</span> <strong>Green:</strong> Using history publish date (or dates match)</li>
                <li><span style="color: orange;">●</span> <strong>Orange:</strong> History publish date is after WordPress publish date</li>
                <li><span style="color: blue;">●</span> <strong>Blue:</strong> History publish date is before WordPress publish date</li>
                <li><span style="color: red;">●</span> <strong>Red:</strong> No published status found, using WordPress date fallback</li>
                <li><span style="background-color: #f0f8ff; padding: 2px 4px;">Light blue background:</span> Publications with published status entries</li>
                <li><strong>📝 Click post/publication titles</strong> to open the edit page and inspect ACF fields</li>
                <li><strong>👁️ "View →" links</strong> open the front-end page in a new tab</li>
                <li><strong>📊 Published Statuses:</strong> Published (2), Published with Minor Revisions (4), Published with Major Revisions (5), Published with Full Review (6)</li>
            </ul>
        </div>
    </div>
    
    <style>
    .wp-list-table td {
        vertical-align: top;
        font-size: 12px;
    }
    .wp-list-table th {
        font-size: 13px;
    }
    .wp-list-table ul {
        font-size: 11px;
    }
    .tablenav-pages .button {
        margin: 0 2px;
    }
    .paging-input {
        margin: 0 8px;
    }
    .wp-list-table td a {
        text-decoration: none;
        color: #0073aa;
    }
    .wp-list-table td a:hover {
        color: #005177;
        text-decoration: underline;
    }
    .wp-list-table td strong a {
        font-weight: bold;
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