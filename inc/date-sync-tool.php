<?php
/**
 * Date Sync Admin Tool
 * File: /inc/date-sync-tool.php
 * 
 * Admin utility to sync WordPress publish dates with ACF custom dates
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu
function add_date_sync_admin_tool() {
    add_submenu_page(
        'caes-tools',
        'Date Sync Tool',
        'Date Sync Tool',
        'manage_options',
        'date-sync-tool',
        'render_date_sync_tool_page'
    );
}
add_action('admin_menu', 'add_date_sync_admin_tool');

// Handle form submissions
function handle_date_sync_submissions() {
    if (isset($_POST['sync_dates']) && wp_verify_nonce($_POST['_wpnonce'], 'sync_dates')) {
        $selected_posts = $_POST['selected_posts'] ?? [];
        $updated_count = 0;
        $scheduled_count = 0;
        
        foreach ($selected_posts as $post_id) {
            $post_id = intval($post_id);
            $post = get_post($post_id);
            
            if (!$post) continue;
            
            $custom_date = get_custom_date_for_post($post_id);
            if (!$custom_date) continue;
            
            $custom_timestamp = strtotime($custom_date);
            $now = current_time('timestamp');
            
            // Prepare post data
            $post_data = [
                'ID' => $post_id,
                'post_date' => date('Y-m-d H:i:s', $custom_timestamp),
                'post_date_gmt' => gmdate('Y-m-d H:i:s', $custom_timestamp)
            ];
            
            // Set status based on date
            if ($custom_timestamp > $now) {
                $post_data['post_status'] = 'future';
                $scheduled_count++;
            } else {
                $post_data['post_status'] = 'publish';
                $updated_count++;
            }
            
            wp_update_post($post_data);
        }
        
        $redirect_url = admin_url('admin.php?page=date-sync-tool&updated=' . $updated_count . '&scheduled=' . $scheduled_count);
        wp_redirect($redirect_url);
        exit;
    }
}
add_action('admin_init', 'handle_date_sync_submissions');

// Get custom date for a post
function get_custom_date_for_post($post_id) {
    $post_type = get_post_type($post_id);
    
    if ($post_type === 'post') {
        return get_field('release_date_new', $post_id);
    } elseif ($post_type === 'publications') {
        return get_latest_publish_date_from_history($post_id);
    }
    
    return null;
}

// Get latest publish date from publications history
function get_latest_publish_date_from_history($post_id) {
    $history = get_field('history', $post_id);
    
    if (!$history || !is_array($history)) {
        return null;
    }
    
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
    $latest_publish_date = null;
    $latest_timestamp = 0;
    
    foreach ($history as $entry) {
        $status_raw = $entry['status'] ?? '';
        $date = $entry['date'] ?? '';
        
        if (in_array((int)$status_raw, $published_statuses) && !empty($date)) {
            $timestamp = strtotime($date);
            if ($timestamp > $latest_timestamp) {
                $latest_timestamp = $timestamp;
                $latest_publish_date = $date;
            }
        }
    }
    
    return $latest_publish_date;
}

// Render the admin page
function render_date_sync_tool_page() {
    // Get filter parameters
    $show_out_of_sync_only = isset($_GET['out_of_sync_only']) && $_GET['out_of_sync_only'] === '1';
    $post_type_filter = $_GET['post_type'] ?? 'all';
    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 500;
    $offset = ($page - 1) * $per_page;
    
    // Build query args
    $query_args = [
        'post_type' => ['post', 'publications'],
        'post_status' => ['publish', 'future'],
        'posts_per_page' => -1, // Get all first, then filter
        'orderby' => 'date',
        'order' => 'DESC'
    ];
    
    if ($post_type_filter !== 'all') {
        $query_args['post_type'] = [$post_type_filter];
    }
    
    $all_posts = get_posts($query_args);
    
    // Filter and prepare data
    $filtered_posts = [];
    foreach ($all_posts as $post) {
        $custom_date = get_custom_date_for_post($post->ID);
        if (!$custom_date) continue;
        
        $wp_date = $post->post_date;
        $custom_formatted = date('Y-m-d H:i:s', strtotime($custom_date));
        $is_out_of_sync = ($wp_date !== $custom_formatted);
        
        if ($show_out_of_sync_only && !$is_out_of_sync) {
            continue;
        }
        
        $filtered_posts[] = [
            'post' => $post,
            'custom_date' => $custom_date,
            'custom_formatted' => $custom_formatted,
            'is_out_of_sync' => $is_out_of_sync
        ];
    }
    
    // Pagination
    $total_items = count($filtered_posts);
    $total_pages = ceil($total_items / $per_page);
    $paged_posts = array_slice($filtered_posts, $offset, $per_page);
    
    // Count out of sync items
    $out_of_sync_count = count(array_filter($filtered_posts, function($item) {
        return $item['is_out_of_sync'];
    }));
    
    ?>
    <div class="wrap">
        <h1>Date Sync Tool</h1>
        
        <?php if (isset($_GET['updated']) || isset($_GET['scheduled'])): ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong>Sync Complete!</strong>
                    <?php if (isset($_GET['updated']) && $_GET['updated'] > 0): ?>
                        Updated <?php echo intval($_GET['updated']); ?> posts.
                    <?php endif; ?>
                    <?php if (isset($_GET['scheduled']) && $_GET['scheduled'] > 0): ?>
                        Scheduled <?php echo intval($_GET['scheduled']); ?> future posts.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="tablenav top">
            <div class="alignleft actions">
                <form method="get" style="display: inline;">
                    <input type="hidden" name="page" value="date-sync-tool">
                    
                    <select name="post_type">
                        <option value="all" <?php selected($post_type_filter, 'all'); ?>>All Post Types</option>
                        <option value="post" <?php selected($post_type_filter, 'post'); ?>>Posts Only</option>
                        <option value="publications" <?php selected($post_type_filter, 'publications'); ?>>Publications Only</option>
                    </select>
                    
                    <label>
                        <input type="checkbox" name="out_of_sync_only" value="1" <?php checked($show_out_of_sync_only); ?>>
                        Show out of sync only (<?php echo $out_of_sync_count; ?> items)
                    </label>
                    
                    <input type="submit" class="button" value="Filter">
                </form>
            </div>
            
            <div class="alignright">
                <span class="displaying-num">
                    <?php echo number_format($total_items); ?> items
                    <?php if ($show_out_of_sync_only): ?>
                        (out of sync)
                    <?php endif; ?>
                </span>
            </div>
        </div>
        
        <?php if (empty($paged_posts)): ?>
            <p>No items found matching your criteria.</p>
        <?php else: ?>
        
        <form method="post">
            <?php wp_nonce_field('sync_dates'); ?>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td id="cb" class="manage-column column-cb check-column">
                            <input type="checkbox" id="select-all-posts">
                        </td>
                        <th style="width: 25%;">Title</th>
                        <th style="width: 10%;">Type</th>
                        <th style="width: 15%;">WordPress Date</th>
                        <th style="width: 15%;">Custom Date</th>
                        <th style="width: 20%;">Custom Date Source</th>
                        <th style="width: 15%;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paged_posts as $item): 
                        $post = $item['post'];
                        $custom_date = $item['custom_date'];
                        $is_out_of_sync = $item['is_out_of_sync'];
                        $now = current_time('timestamp');
                        $custom_timestamp = strtotime($custom_date);
                        $will_be_scheduled = $custom_timestamp > $now;
                    ?>
                    <tr <?php if ($is_out_of_sync) echo 'style="background-color: #fff3cd;"'; ?>>
                        <th scope="row" class="check-column">
                            <?php if ($is_out_of_sync): ?>
                                <input type="checkbox" name="selected_posts[]" value="<?php echo $post->ID; ?>">
                            <?php endif; ?>
                        </th>
                        <td>
                            <strong>
                                <a href="<?php echo get_edit_post_link($post->ID); ?>" target="_blank">
                                    <?php echo esc_html($post->post_title); ?>
                                </a>
                            </strong>
                            <div class="row-actions">
                                <span class="edit">
                                    <a href="<?php echo get_edit_post_link($post->ID); ?>" target="_blank">Edit</a> |
                                </span>
                                <span class="view">
                                    <a href="<?php echo get_permalink($post->ID); ?>" target="_blank">View</a>
                                </span>
                            </div>
                        </td>
                        <td><?php echo ucfirst($post->post_type); ?></td>
                        <td>
                            <strong><?php echo date('M j, Y', strtotime($post->post_date)); ?></strong>
                            <br><small><?php echo date('g:i a', strtotime($post->post_date)); ?></small>
                        </td>
                        <td>
                            <strong><?php echo date('M j, Y', $custom_timestamp); ?></strong>
                            <br><small><?php echo date('g:i a', $custom_timestamp); ?></small>
                        </td>
                        <td>
                            <?php if ($post->post_type === 'post'): ?>
                                <small>ACF: release_date_new</small>
                            <?php else: ?>
                                <small>History: Latest publish date</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$is_out_of_sync): ?>
                                <span style="color: #46b450;">In Sync</span>
                            <?php elseif ($will_be_scheduled): ?>
                                <span style="color: #d63638;">Out of sync - Will schedule</span>
                            <?php else: ?>
                                <span style="color: #d63638;">Out of sync - Will publish</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($out_of_sync_count > 0): ?>
            <div class="tablenav bottom">
                <div class="alignleft actions">
                    <input type="submit" name="sync_dates" class="button button-primary" 
                           value="Sync Selected Dates" 
                           onclick="return confirm('This will update the WordPress publish date for selected items. Continue?')">
                </div>
            </div>
            <?php endif; ?>
        </form>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                $base_url = admin_url('admin.php?page=date-sync-tool');
                if ($post_type_filter !== 'all') {
                    $base_url .= '&post_type=' . $post_type_filter;
                }
                if ($show_out_of_sync_only) {
                    $base_url .= '&out_of_sync_only=1';
                }
                
                if ($page > 1): ?>
                    <a class="button" href="<?php echo $base_url . '&paged=' . ($page - 1); ?>">Previous</a>
                <?php endif; ?>
                
                <span class="paging-input">
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                </span>
                
                <?php if ($page < $total_pages): ?>
                    <a class="button" href="<?php echo $base_url . '&paged=' . ($page + 1); ?>">Next</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#select-all-posts').on('change', function() {
            $('input[name="selected_posts[]"]').prop('checked', this.checked);
        });
    });
    </script>
    
    <style>
    .wp-list-table td, .wp-list-table th {
        vertical-align: top;
    }
    .wp-list-table .row-actions {
        visibility: visible;
    }
    </style>
    <?php
}
?>