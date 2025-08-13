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

// Handle AJAX requests for sync operations only
function handle_date_sync_ajax() {
    // Handle batch sync
    if (isset($_POST['action']) && $_POST['action'] === 'sync_batch_dates') {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'sync_dates')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }
        
        $post_ids = $_POST['post_ids'] ?? [];
        $batch_size = 25; // Process 25 at a time
        $offset = intval($_POST['offset'] ?? 0);
        
        $batch_ids = array_slice($post_ids, $offset, $batch_size);
        $updated_count = 0;
        $scheduled_count = 0;
        
        foreach ($batch_ids as $post_id) {
            $post_id = intval($post_id);
            $post = get_post($post_id);
            
            if (!$post) continue;
            
            $custom_date = get_custom_date_for_post($post_id);
            if (!$custom_date) continue;
            
            $custom_timestamp = strtotime($custom_date);
            $now = current_time('timestamp');
            
            $post_data = [
                'ID' => $post_id,
                'post_date' => date('Y-m-d H:i:s', $custom_timestamp),
                'post_date_gmt' => gmdate('Y-m-d H:i:s', $custom_timestamp)
            ];
            
            if ($custom_timestamp > $now) {
                $post_data['post_status'] = 'future';
                $scheduled_count++;
            } else {
                $post_data['post_status'] = 'publish';
                $updated_count++;
            }
            
            wp_update_post($post_data);
        }
        
        $total_count = count($post_ids);
        $processed_count = min($offset + $batch_size, $total_count);
        $has_more = $processed_count < $total_count;
        
        wp_send_json_success([
            'updated' => $updated_count,
            'scheduled' => $scheduled_count,
            'processed' => count($batch_ids),
            'total_processed' => $processed_count,
            'total_count' => $total_count,
            'has_more' => $has_more,
            'next_offset' => $offset + $batch_size
        ]);
    }
}
add_action('wp_ajax_sync_batch_dates', 'handle_date_sync_ajax');

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
    $per_page = 100;
    $offset = ($page - 1) * $per_page;
    
    // Build efficient query args - only get what we need for this page
    $query_args = [
        'post_type' => ['post', 'publications'],
        'post_status' => ['publish', 'future'],
        'posts_per_page' => $per_page,
        'offset' => $offset,
        'orderby' => 'date',
        'order' => 'DESC',
        'fields' => 'ids' // Only get IDs first for efficiency
    ];
    
    if ($post_type_filter !== 'all') {
        $query_args['post_type'] = [$post_type_filter];
    }
    
    // Get post IDs for this page
    $post_ids = get_posts($query_args);
    
    // Prepare data only for posts on this page
    $paged_posts = [];
    foreach ($post_ids as $post_id) {
        $post = get_post($post_id);
        if (!$post) continue;
        
        $custom_date = get_custom_date_for_post($post_id);
        if (!$custom_date) continue;
        
        $wp_date = $post->post_date;
        $custom_formatted = date('Y-m-d H:i:s', strtotime($custom_date));
        $is_out_of_sync = ($wp_date !== $custom_formatted);
        
        if ($show_out_of_sync_only && !$is_out_of_sync) {
            continue;
        }
        
        $paged_posts[] = [
            'post' => $post,
            'custom_date' => $custom_date,
            'custom_formatted' => $custom_formatted,
            'is_out_of_sync' => $is_out_of_sync
        ];
    }
    
    // Get total counts efficiently
    $total_query = [
        'post_type' => $query_args['post_type'],
        'post_status' => ['publish', 'future'],
        'posts_per_page' => -1,
        'fields' => 'ids'
    ];
    
    $all_post_ids = get_posts($total_query);
    $total_items = count($all_post_ids);
    $total_pages = ceil($total_items / $per_page);
    
    // Count out of sync items (do this efficiently in smaller batches if needed)
    $out_of_sync_count = 0;
    if ($total_items < 1000) {
        // If manageable size, count out of sync items
        foreach ($all_post_ids as $post_id) {
            $custom_date = get_custom_date_for_post($post_id);
            if (!$custom_date) continue;
            
            $post = get_post($post_id);
            if (!$post) continue;
            
            $wp_date = $post->post_date;
            $custom_formatted = date('Y-m-d H:i:s', strtotime($custom_date));
            if ($wp_date !== $custom_formatted) {
                $out_of_sync_count++;
            }
        }
    } else {
        // For very large sites, approximate or skip the count
        $out_of_sync_count = '?';
    }
    
    ?>
    <div class="wrap">
        <h1>Date Sync Tool</h1>
        
        <div id="sync-progress" style="display: none; margin: 20px 0; border: 1px solid #c3c4c7; padding: 15px; background: #fff;">
            <h3>Syncing Dates...</h3>
            <div style="width: 100%; background-color: #f0f0f0; border-radius: 4px; overflow: hidden; margin: 10px 0;">
                <div id="sync-progress-bar" style="width: 0%; height: 20px; background-color: #0073aa; transition: width 0.3s;"></div>
            </div>
            <p id="sync-progress-text">Preparing...</p>
        </div>
        
        <div id="sync-results" style="display: none;" class="notice notice-success is-dismissible">
            <p id="sync-results-text"></p>
        </div>
        
        <!-- Performance Notice for Large Sites -->
        <?php if ($total_items > 1000): ?>
        <div class="notice notice-info">
            <p><strong>Large Dataset Notice:</strong> You have <?php echo number_format($total_items); ?> posts. Out-of-sync counting is disabled for performance. Use filters to focus on specific content.</p>
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
                        Show out of sync only <?php if ($out_of_sync_count !== '?'): ?>(<?php echo $out_of_sync_count; ?> items)<?php endif; ?>
                    </label>
                    
                    <input type="submit" class="button" value="Filter">
                </form>
            </div>
            
            <div class="alignright">
                <span class="displaying-num">
                    <?php echo number_format(count($paged_posts)); ?> of <?php echo number_format($total_items); ?> items
                    <?php if ($show_out_of_sync_only): ?>
                        (filtered)
                    <?php endif; ?>
                </span>
            </div>
        </div>
        
        <?php if (empty($paged_posts)): ?>
            <p>No items found matching your criteria on this page.</p>
        <?php else: ?>
        
        <form id="sync-form">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td id="cb" class="manage-column column-cb check-column">
                            <input type="checkbox" id="select-all-posts">
                        </td>
                        <th style="width: 25%;">Title</th>
                        <th style="width: 8%;">Type</th>
                        <th style="width: 15%;">WordPress Date</th>
                        <th style="width: 15%;">Custom Date</th>
                        <th style="width: 17%;">Custom Date Source</th>
                        <th style="width: 20%;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $has_out_of_sync = false;
                    foreach ($paged_posts as $item): 
                        $post = $item['post'];
                        $custom_date = $item['custom_date'];
                        $is_out_of_sync = $item['is_out_of_sync'];
                        if ($is_out_of_sync) $has_out_of_sync = true;
                        
                        $now = current_time('timestamp');
                        $custom_timestamp = strtotime($custom_date);
                        $will_be_scheduled = $custom_timestamp > $now;
                    ?>
                    <tr <?php if ($is_out_of_sync) echo 'style="background-color: #fff3cd;"'; ?>>
                        <th scope="row" class="check-column">
                            <?php if ($is_out_of_sync): ?>
                                <input type="checkbox" name="selected_posts[]" value="<?php echo $post->ID; ?>" class="sync-checkbox">
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
            
            <?php if ($has_out_of_sync): ?>
            <div class="tablenav bottom">
                <div class="alignleft actions">
                    <button type="button" id="sync-selected" class="button button-primary" disabled>
                        Sync Selected (<span id="selected-count">0</span>)
                    </button>
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
        var selectedItems = [];
        
        function updateSelectedCount() {
            selectedItems = [];
            $('.sync-checkbox:checked').each(function() {
                selectedItems.push(parseInt($(this).val()));
            });
            
            $('#selected-count').text(selectedItems.length);
            $('#sync-selected').prop('disabled', selectedItems.length === 0);
        }
        
        $('#select-all-posts').on('change', function() {
            $('.sync-checkbox').prop('checked', this.checked);
            updateSelectedCount();
        });
        
        $(document).on('change', '.sync-checkbox', function() {
            updateSelectedCount();
        });
        
        $('#sync-selected').on('click', function() {
            if (selectedItems.length === 0) return;
            
            if (!confirm('This will update the WordPress publish date for ' + selectedItems.length + ' selected items in batches. Continue?')) {
                return;
            }
            
            startBatchSync();
        });
        
        function startBatchSync() {
            $('#sync-progress').show();
            $('#sync-selected').prop('disabled', true).text('Syncing...');
            
            var totalUpdated = 0;
            var totalScheduled = 0;
            
            processBatch(0);
            
            function processBatch(offset) {
                $('#sync-progress-text').text('Processing batch ' + Math.floor(offset/25 + 1) + '... (' + Math.min(offset + 25, selectedItems.length) + ' of ' + selectedItems.length + ')');
                
                var percentage = Math.round((offset / selectedItems.length) * 100);
                $('#sync-progress-bar').css('width', percentage + '%');
                
                $.post(ajaxurl, {
                    action: 'sync_batch_dates',
                    post_ids: selectedItems,
                    offset: offset,
                    _wpnonce: '<?php echo wp_create_nonce('sync_dates'); ?>'
                })
                .done(function(response) {
                    if (response.success) {
                        var data = response.data;
                        totalUpdated += data.updated;
                        totalScheduled += data.scheduled;
                        
                        var newPercentage = Math.round((data.total_processed / selectedItems.length) * 100);
                        $('#sync-progress-bar').css('width', newPercentage + '%');
                        
                        if (data.has_more) {
                            setTimeout(function() {
                                processBatch(data.next_offset);
                            }, 500);
                        } else {
                            // Complete
                            $('#sync-progress-bar').css('width', '100%');
                            $('#sync-progress-text').text('Complete! Updated ' + totalUpdated + ' posts, scheduled ' + totalScheduled + ' future posts.');
                            
                            var message = 'Sync Complete! ';
                            if (totalUpdated > 0) {
                                message += 'Updated ' + totalUpdated + ' posts. ';
                            }
                            if (totalScheduled > 0) {
                                message += 'Scheduled ' + totalScheduled + ' future posts.';
                            }
                            
                            $('#sync-results-text').text(message);
                            $('#sync-results').show();
                            
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        }
                    } else {
                        $('#sync-progress-text').html('<strong style="color: red;">Error:</strong> ' + (response.data.message || 'Unknown error'));
                    }
                })
                .fail(function() {
                    $('#sync-progress-text').html('<strong style="color: red;">Network Error:</strong> Please try again.');
                });
            }
        }
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