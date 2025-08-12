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

// Handle AJAX requests
function handle_date_sync_ajax() {
    // Handle data loading
    if (isset($_POST['action']) && $_POST['action'] === 'load_date_sync_data') {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'date_sync_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }
        
        $offset = intval($_POST['offset'] ?? 0);
        $batch_size = 100; // Load 100 items per batch
        $post_type_filter = $_POST['post_type'] ?? 'all';
        
        // Build query args
        $query_args = [
            'post_type' => ['post', 'publications'],
            'post_status' => ['publish', 'future'],
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        if ($post_type_filter !== 'all') {
            $query_args['post_type'] = [$post_type_filter];
        }
        
        $posts = get_posts($query_args);
        $processed_data = [];
        
        foreach ($posts as $post) {
            $custom_date = get_custom_date_for_post($post->ID);
            if (!$custom_date) continue;
            
            $wp_date = $post->post_date;
            $custom_formatted = date('Y-m-d H:i:s', strtotime($custom_date));
            $is_out_of_sync = ($wp_date !== $custom_formatted);
            $custom_timestamp = strtotime($custom_date);
            $now = current_time('timestamp');
            $will_be_scheduled = $custom_timestamp > $now;
            
            $processed_data[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'post_type' => $post->post_type,
                'wp_date' => $post->post_date,
                'custom_date' => $custom_date,
                'custom_formatted' => $custom_formatted,
                'is_out_of_sync' => $is_out_of_sync,
                'will_be_scheduled' => $will_be_scheduled,
                'edit_link' => get_edit_post_link($post->ID),
                'view_link' => get_permalink($post->ID)
            ];
        }
        
        // Get total count for progress
        $total_query = [
            'post_type' => $query_args['post_type'],
            'post_status' => ['publish', 'future'],
            'posts_per_page' => -1,
            'fields' => 'ids'
        ];
        $total_count = count(get_posts($total_query));
        
        wp_send_json_success([
            'data' => $processed_data,
            'has_more' => count($posts) === $batch_size,
            'next_offset' => $offset + $batch_size,
            'total_count' => $total_count,
            'current_loaded' => $offset + count($posts)
        ]);
    }
    
    // Handle batch sync
    if (isset($_POST['action']) && $_POST['action'] === 'sync_batch_dates') {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'date_sync_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }
        
        $post_ids = $_POST['post_ids'] ?? [];
        $updated_count = 0;
        $scheduled_count = 0;
        
        foreach ($post_ids as $post_id) {
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
        
        wp_send_json_success([
            'updated' => $updated_count,
            'scheduled' => $scheduled_count
        ]);
    }
}
add_action('wp_ajax_load_date_sync_data', 'handle_date_sync_ajax');
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
    $post_type_filter = $_GET['post_type'] ?? 'all';
    $show_out_of_sync_only = isset($_GET['out_of_sync_only']) && $_GET['out_of_sync_only'] === '1';
    
    ?>
    <div class="wrap">
        <h1>Date Sync Tool</h1>
        
        <div id="sync-results" style="display: none;" class="notice notice-success is-dismissible">
            <p id="sync-results-text"></p>
        </div>
        
        <!-- Loading Progress -->
        <div id="loading-progress" style="margin: 20px 0;">
            <h3>Loading Posts...</h3>
            <div style="width: 100%; background-color: #f0f0f0; border-radius: 4px; overflow: hidden; margin: 10px 0;">
                <div id="loading-progress-bar" style="width: 0%; height: 20px; background-color: #0073aa; transition: width 0.3s;"></div>
            </div>
            <p id="loading-progress-text">Initializing...</p>
        </div>
        
        <!-- Filters (initially hidden) -->
        <div id="filters-section" style="display: none;">
            <div class="tablenav top">
                <div class="alignleft actions">
                    <select id="post-type-filter">
                        <option value="all" <?php selected($post_type_filter, 'all'); ?>>All Post Types</option>
                        <option value="post" <?php selected($post_type_filter, 'post'); ?>>Posts Only</option>
                        <option value="publications" <?php selected($post_type_filter, 'publications'); ?>>Publications Only</option>
                    </select>
                    
                    <label>
                        <input type="checkbox" id="out-of-sync-filter" <?php checked($show_out_of_sync_only); ?>>
                        Show out of sync only (<span id="out-of-sync-count">0</span> items)
                    </label>
                    
                    <button type="button" id="apply-filters" class="button">Apply Filters</button>
                    <button type="button" id="reload-data" class="button">Reload Data</button>
                </div>
                
                <div class="alignright">
                    <span id="items-count" class="displaying-num">0 items</span>
                </div>
            </div>
        </div>
        
        <!-- Sync Controls (initially hidden) -->
        <div id="sync-controls" style="display: none; margin: 10px 0;">
            <div class="tablenav">
                <div class="alignleft actions">
                    <button type="button" id="select-all-visible" class="button">Select All Visible</button>
                    <button type="button" id="select-out-of-sync" class="button">Select All Out of Sync</button>
                    <button type="button" id="sync-selected" class="button button-primary" disabled>
                        Sync Selected (<span id="selected-count">0</span>)
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Data Table -->
        <div id="data-table-container" style="display: none;">
            <table class="wp-list-table widefat fixed striped" id="date-sync-table">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="select-all-checkbox">
                        </td>
                        <th style="width: 25%;">Title</th>
                        <th style="width: 8%;">Type</th>
                        <th style="width: 15%;">WordPress Date</th>
                        <th style="width: 15%;">Custom Date</th>
                        <th style="width: 17%;">Custom Date Source</th>
                        <th style="width: 20%;">Status</th>
                    </tr>
                </thead>
                <tbody id="data-table-body">
                    <!-- Data will be loaded via AJAX -->
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        var allData = [];
        var filteredData = [];
        var selectedItems = new Set();
        
        // Start loading data
        loadAllData();
        
        function loadAllData() {
            allData = [];
            var totalLoaded = 0;
            var totalCount = 0;
            
            loadBatch(0);
            
            function loadBatch(offset) {
                $.post(ajaxurl, {
                    action: 'load_date_sync_data',
                    offset: offset,
                    post_type: $('#post-type-filter').val() || 'all',
                    _wpnonce: '<?php echo wp_create_nonce('date_sync_nonce'); ?>'
                })
                .done(function(response) {
                    if (response.success) {
                        var data = response.data;
                        allData = allData.concat(data.data);
                        totalLoaded = data.current_loaded;
                        totalCount = data.total_count;
                        
                        var percentage = Math.round((totalLoaded / totalCount) * 100);
                        $('#loading-progress-bar').css('width', percentage + '%');
                        $('#loading-progress-text').text('Loaded ' + totalLoaded.toLocaleString() + ' of ' + totalCount.toLocaleString() + ' posts (' + percentage + '%)');
                        
                        if (data.has_more) {
                            setTimeout(function() {
                                loadBatch(data.next_offset);
                            }, 100);
                        } else {
                            // Loading complete
                            $('#loading-progress').hide();
                            $('#filters-section, #sync-controls, #data-table-container').show();
                            applyFilters();
                        }
                    } else {
                        $('#loading-progress-text').html('<strong style="color: red;">Error:</strong> ' + (response.data.message || 'Unknown error'));
                    }
                })
                .fail(function() {
                    $('#loading-progress-text').html('<strong style="color: red;">Network Error:</strong> Please refresh and try again.');
                });
            }
        }
        
        function applyFilters() {
            var showOutOfSyncOnly = $('#out-of-sync-filter').is(':checked');
            var postTypeFilter = $('#post-type-filter').val();
            
            filteredData = allData.filter(function(item) {
                if (showOutOfSyncOnly && !item.is_out_of_sync) {
                    return false;
                }
                if (postTypeFilter !== 'all' && item.post_type !== postTypeFilter) {
                    return false;
                }
                return true;
            });
            
            var outOfSyncCount = allData.filter(function(item) {
                return item.is_out_of_sync;
            }).length;
            
            $('#out-of-sync-count').text(outOfSyncCount);
            $('#items-count').text(filteredData.length.toLocaleString() + ' items');
            
            renderTable();
            updateSelectedCount();
        }
        
        function renderTable() {
            var tbody = $('#data-table-body');
            tbody.empty();
            
            filteredData.forEach(function(item) {
                var isSelected = selectedItems.has(item.id);
                var rowClass = item.is_out_of_sync ? 'style="background-color: #fff3cd;"' : '';
                
                var row = $('<tr ' + rowClass + '>' +
                    '<th scope="row" class="check-column">' +
                        (item.is_out_of_sync ? '<input type="checkbox" class="item-checkbox" data-id="' + item.id + '"' + (isSelected ? ' checked' : '') + '>' : '') +
                    '</th>' +
                    '<td>' +
                        '<strong><a href="' + item.edit_link + '" target="_blank">' + $('<div>').text(item.title).html() + '</a></strong>' +
                        '<div class="row-actions">' +
                            '<span class="edit"><a href="' + item.edit_link + '" target="_blank">Edit</a> | </span>' +
                            '<span class="view"><a href="' + item.view_link + '" target="_blank">View</a></span>' +
                        '</div>' +
                    '</td>' +
                    '<td>' + (item.post_type.charAt(0).toUpperCase() + item.post_type.slice(1)) + '</td>' +
                    '<td>' +
                        '<strong>' + formatDate(item.wp_date) + '</strong><br>' +
                        '<small>' + formatTime(item.wp_date) + '</small>' +
                    '</td>' +
                    '<td>' +
                        '<strong>' + formatDate(item.custom_date) + '</strong><br>' +
                        '<small>' + formatTime(item.custom_date) + '</small>' +
                    '</td>' +
                    '<td>' +
                        '<small>' + (item.post_type === 'post' ? 'ACF: release_date_new' : 'History: Latest publish date') + '</small>' +
                    '</td>' +
                    '<td>' + getStatusText(item) + '</td>' +
                '</tr>');
                
                tbody.append(row);
            });
        }
        
        function formatDate(dateString) {
            var date = new Date(dateString);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
        
        function formatTime(dateString) {
            var date = new Date(dateString);
            return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        }
        
        function getStatusText(item) {
            if (!item.is_out_of_sync) {
                return '<span style="color: #46b450;">In Sync</span>';
            } else if (item.will_be_scheduled) {
                return '<span style="color: #d63638;">Out of sync - Will schedule</span>';
            } else {
                return '<span style="color: #d63638;">Out of sync - Will publish</span>';
            }
        }
        
        function updateSelectedCount() {
            var count = selectedItems.size;
            $('#selected-count').text(count);
            $('#sync-selected').prop('disabled', count === 0);
        }
        
        // Event handlers
        $('#apply-filters').on('click', applyFilters);
        $('#reload-data').on('click', function() {
            $('#loading-progress').show();
            $('#filters-section, #sync-controls, #data-table-container').hide();
            selectedItems.clear();
            loadAllData();
        });
        
        $(document).on('change', '.item-checkbox', function() {
            var id = parseInt($(this).data('id'));
            if ($(this).is(':checked')) {
                selectedItems.add(id);
            } else {
                selectedItems.delete(id);
            }
            updateSelectedCount();
        });
        
        $('#select-all-checkbox').on('change', function() {
            var isChecked = $(this).is(':checked');
            $('.item-checkbox').prop('checked', isChecked);
            
            selectedItems.clear();
            if (isChecked) {
                filteredData.forEach(function(item) {
                    if (item.is_out_of_sync) {
                        selectedItems.add(item.id);
                    }
                });
            }
            updateSelectedCount();
        });
        
        $('#select-all-visible').on('click', function() {
            $('.item-checkbox').prop('checked', true);
            selectedItems.clear();
            filteredData.forEach(function(item) {
                if (item.is_out_of_sync) {
                    selectedItems.add(item.id);
                }
            });
            updateSelectedCount();
        });
        
        $('#select-out-of-sync').on('click', function() {
            $('.item-checkbox').prop('checked', false);
            selectedItems.clear();
            allData.forEach(function(item) {
                if (item.is_out_of_sync) {
                    selectedItems.add(item.id);
                    $('.item-checkbox[data-id="' + item.id + '"]').prop('checked', true);
                }
            });
            updateSelectedCount();
        });
        
        $('#sync-selected').on('click', function() {
            if (selectedItems.size === 0) return;
            
            if (!confirm('This will update the WordPress publish date for ' + selectedItems.size + ' selected items. Continue?')) {
                return;
            }
            
            var selectedArray = Array.from(selectedItems);
            $(this).prop('disabled', true).text('Syncing...');
            
            $.post(ajaxurl, {
                action: 'sync_batch_dates',
                post_ids: selectedArray,
                _wpnonce: '<?php echo wp_create_nonce('date_sync_nonce'); ?>'
            })
            .done(function(response) {
                if (response.success) {
                    var data = response.data;
                    var message = 'Sync Complete! ';
                    if (data.updated > 0) {
                        message += 'Updated ' + data.updated + ' posts. ';
                    }
                    if (data.scheduled > 0) {
                        message += 'Scheduled ' + data.scheduled + ' future posts.';
                    }
                    
                    $('#sync-results-text').text(message);
                    $('#sync-results').show();
                    
                    // Reload data to reflect changes
                    $('#reload-data').click();
                } else {
                    alert('Error: ' + (response.data.message || 'Unknown error'));
                }
            })
            .fail(function() {
                alert('Network error occurred. Please try again.');
            })
            .always(function() {
                $('#sync-selected').prop('disabled', false).text('Sync Selected (0)');
            });
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
    #loading-progress {
        border: 1px solid #c3c4c7;
        padding: 15px;
        background: #fff;
    }
    </style>
    <?php
}
?>