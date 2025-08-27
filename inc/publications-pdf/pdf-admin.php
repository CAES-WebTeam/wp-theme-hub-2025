<?php
/**
 * Handles admin-related functions for PDF generation, including notifications.
 */

// Ensure this file is called by WordPress
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Queues PDF generation when a 'publications' post is saved or updated.
 *
 * @param int     $post_id The post ID.
 * @param WP_Post $post    The post object.
 */
function queue_pdf_generation_on_save($post_id, $post) {
    // Check if it's a 'publications' post type and not an autosave, revision, or auto-draft.
    if ($post->post_type !== 'publications' || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || $post->post_status == 'auto-draft') {
        return;
    }

    // NEW CONDITION 1: Only generate if the post is published
    if ($post->post_status !== 'publish') {
        set_transient('pdf_generation_notice_' . $post_id, array(
            'type' => 'info',
            'message' => sprintf('PDF generation for "%s" was not queued because the post is not yet published.', get_the_title($post_id))
        ), 60);
        return;
    }

    // Get the publication number (assuming it's a critical field for filename)
    $publication_number = get_field('publication_number', $post_id);

    if (empty($publication_number)) {
        // If no publication number, do NOT queue and show an immediate error notification.
        set_transient('pdf_generation_notice_' . $post_id, array(
            'type' => 'error',
            'message' => sprintf('PDF generation for "%s" was *not* queued because the Publication Number is missing. Please add a Publication Number and save again.', get_the_title($post_id))
        ), 60); // Show for 60 seconds
        return; // Stop execution here, do not proceed to queue
    }

    // NEW CONDITION 2: Only generate if a manual PDF does NOT exist
    $manual_pdf_attachment = get_field('pdf', $post_id);
    $manual_pdf_exists = false;
    if (is_array($manual_pdf_attachment) && !empty($manual_pdf_attachment['url'])) {
        $manual_pdf_exists = true;
    } elseif (is_string($manual_pdf_attachment) && filter_var($manual_pdf_attachment, FILTER_VALIDATE_URL)) {
        $manual_pdf_exists = true;
    }

    if ($manual_pdf_exists) {
        set_transient('pdf_generation_notice_' . $post_id, array(
            'type' => 'info',
            'message' => sprintf('PDF generation for "%s" was *not* queued because a manual PDF already exists.', get_the_title($post_id))
        ), 60);
        return; // Stop execution, a manual PDF is present
    }

    // Queue the PDF generation task
    // This function is in inc/publications-pdf/pdf-queue.php
    if (insert_or_update_pdf_queue($post_id, 'pending')) {
        // You might set a temporary transient here to indicate it's queued.
        // The actual success/failure notification will come from the cron job.
        set_transient('pdf_generation_notice_' . $post_id, array('type' => 'info', 'message' => sprintf('PDF generation for "%s" has been queued. It will be generated in the background.', get_the_title($post_id))), 60); // Show for 60 seconds
    } else {
        set_transient('pdf_generation_notice_' . $post_id, array('type' => 'error', 'message' => sprintf('Failed to queue PDF generation for "%s". Please try again.', get_the_title($post_id))), 60);
    }

    // Ensure the cron job is scheduled to run soon if it's not already
    // This is a safety check; the cron should already be scheduled by pdf-cron.php
    if (!wp_next_scheduled('my_pdf_generation_cron_hook')) {
        wp_schedule_single_event(time() + 30, 'my_pdf_generation_cron_hook'); // Try to run in 30 seconds
    }
}
add_action('save_post_publications', 'queue_pdf_generation_on_save', 10, 2);

/**
 * Displays admin notices for PDF generation status.
 */
function display_pdf_generation_admin_notices() {
    global $pagenow;

    // Only show notices on relevant admin pages
    if ($pagenow === 'post.php' || $pagenow === 'edit.php') {
        $post_id = isset($_GET['post']) ? intval($_GET['post']) : (isset($_POST['post_ID']) ? intval($_POST['post_ID']) : 0);
        if (!$post_id && $pagenow === 'edit.php') {
            // For the list table, check all recent queue items (or rely on a more global notice if needed)
            // For simplicity, we'll focus on single post edit screen for specific notices.
            return;
        }

        $notice_key = 'pdf_generation_notice_' . $post_id;
        $notice_data = get_transient($notice_key);

        if ($notice_data && is_array($notice_data)) {
            $class = 'notice notice-' . esc_attr($notice_data['type']);
            $message = esc_html($notice_data['message']);
            printf('<div class="%1$s"><p>%2$s</p></div>', $class, $message);
            delete_transient($notice_key); // Display once and delete
        }
    }
}
add_action('admin_notices', 'display_pdf_generation_admin_notices');

/**
 * Adds custom columns to the publications list table.
 *
 * @param array $columns Existing columns.
 * @return array Modified columns.
 */
function add_pdf_status_column($columns) {
    // Add the new column for manually uploaded PDFs
    $columns['manual_pdf'] = __('Manual PDF', 'textdomain');
    $columns['pdf_status'] = __('Generated PDF Status', 'textdomain'); // Renamed for clarity
    $columns['pdf_link'] = __('Generated PDF Link', 'textdomain'); // Renamed for clarity
    return $columns;
}
add_filter('manage_publications_posts_columns', 'add_pdf_status_column');

/**
 * Displays content for the custom PDF columns.
 *
 * @param string $column_name The name of the column.
 * @param int    $post_id     The post ID.
 */
function display_pdf_status_column_content($column_name, $post_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pdf_generation_queue';

    switch ($column_name) {
        case 'manual_pdf':
            // Try to get a manually uploaded PDF (from the 'pdf' ACF field)
            $manual_pdf_attachment = get_field('pdf', $post_id);
            $manual_pdf_url = null;

            if (is_array($manual_pdf_attachment) && !empty($manual_pdf_attachment['url'])) {
                $manual_pdf_url = $manual_pdf_attachment['url'];
            } elseif (is_string($manual_pdf_attachment) && filter_var($manual_pdf_attachment, FILTER_VALIDATE_URL)) {
                $manual_pdf_url = $manual_pdf_attachment;
            }

            if ($manual_pdf_url) {
                echo '<a href="' . esc_url($manual_pdf_url) . '" target="_blank" class="button button-small">View Manual PDF</a>';
            } else {
                echo 'N/A';
            }
            break;

        case 'pdf_status':
            $queue_item = $wpdb->get_row($wpdb->prepare("SELECT status FROM {$table_name} WHERE post_id = %d", $post_id));
            if ($queue_item) {
                echo esc_html(ucfirst($queue_item->status));
            } else {
                echo 'Not Queued';
            }
            break;
        case 'pdf_link':
            $pdf_url = get_field('pdf_download_url', $post_id);
            if ($pdf_url) {
                echo '<a href="' . esc_url($pdf_url) . '" target="_blank" class="button button-small">Download Generated PDF</a>';
            } else {
                echo 'N/A';
            }
            break;
    }
}
add_action('manage_publications_posts_custom_column', 'display_pdf_status_column_content', 10, 2);

/**
 * Ensures that the PDF cache directory is created.
 */
function create_pdf_cache_directory() {
    $upload_dir = wp_upload_dir();
    $cache_dir_path = $upload_dir['basedir'] . '/generated-pub-pdfs/';
    if (!file_exists($cache_dir_path)) {
        wp_mkdir_p($cache_dir_path);
    }
}
add_action('after_setup_theme', 'create_pdf_cache_directory');

/**
 * Adds the consolidated PDF management admin page
 */
function fr2025_add_pdf_management_page() {
    // Remove the separate pages
    remove_action('admin_menu', 'fr2025_add_pdf_generation_tool_page');
    remove_action('admin_menu', 'fr2025_add_pdf_monitor_page');
    
    add_submenu_page(
        'edit.php?post_type=publications',
        'PDF Management',
        'PDF Management', 
        'manage_options',
        'fr2025-pdf-management',
        'fr2025_render_pdf_management_page'
    );
}
add_action('admin_menu', 'fr2025_add_pdf_management_page', 11);

/**
 * Renders the consolidated PDF management page
 */
function fr2025_render_pdf_management_page() {
    ?>
    <div class="wrap">
        <h1>PDF Management Console</h1>
        
        <!-- System Status -->
        <div id="system-status" style="margin: 20px 0; padding: 15px; background: #f1f1f1; border-radius: 5px;">
            <h3>System Status</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div><strong>Memory:</strong> <?php echo ini_get('memory_limit'); ?> (Used: <?php echo size_format(memory_get_usage(true)); ?>)</div>
                <div><strong>Execution Time:</strong> <?php echo ini_get('max_execution_time'); ?>s</div>
                <div><strong>Cron Status:</strong> <span id="cron-lock-status"><?php echo get_transient('pdf_generation_lock') ? 'LOCKED' : 'FREE'; ?></span></div>
                <div><strong>Next Cron:</strong> <?php 
                    $next_cron = wp_next_scheduled('my_pdf_generation_cron_hook');
                    echo $next_cron ? date('H:i:s', $next_cron) : 'Not scheduled';
                ?></div>
            </div>
            <div style="margin-top: 10px;">
                <button id="refresh-all" class="button button-primary">Refresh All</button>
                <button id="clear-cron-lock" class="button button-secondary">Clear Cron Lock</button>
                <button id="force-cron-run" class="button button-secondary">Force Cron Run</button>
            </div>
        </div>

        <!-- Three Main Sections -->
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-top: 20px;">
            
            <!-- Generate PDFs Section -->
            <div class="pdf-section" style="border: 1px solid #ddd; border-radius: 5px; padding: 15px;">
                <h3>Generate Missing PDFs</h3>
                <div id="generate-status">
                    <p><strong>Status:</strong> <span id="generate-current-status">Idle</span></p>
                    <p><strong>Progress:</strong> <span id="generate-queued-count">0</span> / <span id="generate-total-posts">...</span></p>
                    <div style="width: 100%; background-color: #f3f3f3; border-radius: 3px; height: 15px; overflow: hidden; margin: 10px 0;">
                        <div id="generate-progress-bar" style="height: 100%; width: 0%; background-color: #4CAF50; text-align: center; color: white; line-height: 15px; font-size: 11px;">0%</div>
                    </div>
                    <p id="generate-last-message" style="font-size: 12px; color: #666;"></p>
                </div>
                <button id="start-pdf-generation" class="button button-primary" style="width: 100%;">Start Generation</button>
                <button id="process-next-batch" class="button button-secondary" style="width: 100%; display: none;">Process Next Batch</button>
            </div>

            <!-- Monitor Current Processes -->
            <div class="pdf-section" style="border: 1px solid #ddd; border-radius: 5px; padding: 15px;">
                <h3>Current Processes</h3>
                <div id="current-processes">
                    <p style="color: #666; font-size: 12px;">Loading...</p>
                </div>
            </div>

            <!-- Find Tables Section -->
            <div class="pdf-section" style="border: 1px solid #ddd; border-radius: 5px; padding: 15px;">
                <h3>Find Publications with Tables</h3>
                <div id="table-scan-status">
                    <p><strong>Status:</strong> <span id="table-scan-current-status">Idle</span></p>
                    <p><strong>Progress:</strong> <span id="table-scan-processed">0</span> / <span id="table-scan-total">...</span></p>
                    <div style="width: 100%; background-color: #f3f3f3; border-radius: 3px; height: 15px; overflow: hidden; margin: 10px 0;">
                        <div id="table-scan-progress-bar" style="height: 100%; width: 0%; background-color: #ff9800; text-align: center; color: white; line-height: 15px; font-size: 11px;">0%</div>
                    </div>
                    <p id="table-scan-results" style="font-size: 12px; max-height: 150px; overflow-y: auto;"></p>
                </div>
                <button id="start-table-scan" class="button button-primary" style="width: 100%;">Scan for Tables</button>
                <button id="continue-table-scan" class="button button-secondary" style="width: 100%; display: none;">Continue Scan</button>
            </div>
        </div>

        <!-- Detailed Results Section (expandable) -->
        <div id="detailed-results" style="margin-top: 30px; display: none;">
            <h3>Detailed Results <button id="toggle-details" class="button button-small">Hide Details</button></h3>
            <div id="detailed-content"></div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Generate PDFs variables
            var generateState = {
                totalPosts: 0,
                processedCount: 0,
                currentOffset: 0,
                batchSize: 50,
                isProcessing: false
            };

            // Table scan variables
            var tableScanState = {
                totalPosts: 0,
                processedCount: 0,
                currentOffset: 0,
                batchSize: 100,
                isScanning: false,
                foundTables: []
            };

            function updateGenerateProgress() {
                if (generateState.totalPosts === 0) return;
                var percentage = (generateState.processedCount / generateState.totalPosts) * 100;
                $('#generate-progress-bar').css('width', percentage + '%').text(Math.round(percentage) + '%');
            }

            function updateTableScanProgress() {
                if (tableScanState.totalPosts === 0) return;
                var percentage = (tableScanState.processedCount / tableScanState.totalPosts) * 100;
                $('#table-scan-progress-bar').css('width', percentage + '%').text(Math.round(percentage) + '%');
            }

            function logGenerateMessage(type, message) {
                var color = type === 'error' ? 'red' : (type === 'success' ? 'green' : '#666');
                $('#generate-last-message').html('<span style="color:' + color + ';">' + message + '</span>');
            }

            function refreshCurrentProcesses() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fr2025_get_current_processes',
                        _ajax_nonce: '<?php echo wp_create_nonce('fr2025_pdf_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#current-processes').html(response.data.html);
                            $('#cron-lock-status').text(response.data.cron_locked ? 'LOCKED' : 'FREE');
                        }
                    }
                });
            }

            function initializeGenerate() {
                $('#generate-current-status').text('Calculating...');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fr2025_pdf_get_total_missing_pdfs',
                        _ajax_nonce: '<?php echo wp_create_nonce('fr2025_pdf_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            generateState.totalPosts = response.data.total;
                            $('#generate-total-posts').text(generateState.totalPosts);
                            if (generateState.totalPosts === 0) {
                                $('#generate-current-status').text('No PDFs needed');
                                logGenerateMessage('success', 'All publications have PDFs');
                                $('#start-pdf-generation').prop('disabled', true);
                            } else {
                                $('#generate-current-status').text('Ready');
                                logGenerateMessage('info', generateState.totalPosts + ' publications need PDFs');
                            }
                        }
                    }
                });
            }

            function initializeTableScan() {
                $('#table-scan-current-status').text('Calculating...');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fr2025_get_total_publications',
                        _ajax_nonce: '<?php echo wp_create_nonce('fr2025_pdf_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            tableScanState.totalPosts = response.data.total;
                            $('#table-scan-total').text(tableScanState.totalPosts);
                            $('#table-scan-current-status').text('Ready');
                        }
                    }
                });
            }

            // Event handlers
            $('#refresh-all').on('click', function() {
                initializeGenerate();
                refreshCurrentProcesses();
                initializeTableScan();
            });

            $('#clear-cron-lock').on('click', function() {
                if (!confirm('Clear the cron lock?')) return;
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fr2025_clear_cron_lock',
                        _ajax_nonce: '<?php echo wp_create_nonce('fr2025_pdf_nonce'); ?>'
                    },
                    success: function(response) {
                        alert(response.data || 'Cron lock cleared');
                        refreshCurrentProcesses();
                    }
                });
            });

            $('#force-cron-run').on('click', function() {
                if (!confirm('Force a cron run now?')) return;
                $(this).prop('disabled', true).text('Running...');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fr2025_force_cron_run',
                        _ajax_nonce: '<?php echo wp_create_nonce('fr2025_pdf_nonce'); ?>'
                    },
                    success: function(response) {
                        alert(response.data || 'Cron run completed');
                        refreshCurrentProcesses();
                    },
                    complete: function() {
                        $('#force-cron-run').prop('disabled', false).text('Force Cron Run');
                    }
                });
            });

            $('#start-pdf-generation').on('click', function() {
                generateState.isProcessing = true;
                generateState.processedCount = 0;
                generateState.currentOffset = 0;
                $('#generate-queued-count').text('0');
                updateGenerateProgress();
                $(this).hide();
                $('#process-next-batch').show();
                processGenerateBatch();
            });

            $('#process-next-batch').on('click', function() {
                if (!generateState.isProcessing) processGenerateBatch();
            });

            function processGenerateBatch() {
                generateState.isProcessing = true;
                $('#process-next-batch').prop('disabled', true).text('Processing...');
                $('#generate-current-status').text('Processing batch...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fr2025_pdf_queue_missing_pdfs',
                        offset: generateState.currentOffset,
                        batch_size: generateState.batchSize,
                        _ajax_nonce: '<?php echo wp_create_nonce('fr2025_pdf_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            generateState.processedCount += response.data.processed_count;
                            $('#generate-queued-count').text(generateState.processedCount);
                            updateGenerateProgress();
                            logGenerateMessage('info', 'Batch completed');
                            generateState.currentOffset += generateState.batchSize;

                            if (generateState.processedCount >= generateState.totalPosts) {
                                $('#generate-current-status').text('Complete');
                                logGenerateMessage('success', 'All available publications queued');
                                $('#process-next-batch').hide();
                                $('#start-pdf-generation').show().prop('disabled', false);
                            } else {
                                $('#generate-current-status').text('Ready for next batch');
                                $('#process-next-batch').prop('disabled', false).text('Process Next Batch');
                            }
                        }
                        generateState.isProcessing = false;
                        refreshCurrentProcesses();
                    }
                });
            }

            $('#start-table-scan').on('click', function() {
                tableScanState.processedCount = 0;
                tableScanState.currentOffset = 0;
                tableScanState.foundTables = [];
                $('#table-scan-processed').text('0');
                updateTableScanProgress();
                $('#table-scan-results').html('');
                $(this).hide();
                $('#continue-table-scan').show();
                processTableScanBatch();
            });

            $('#continue-table-scan').on('click', function() {
                if (!tableScanState.isScanning) processTableScanBatch();
            });

            function processTableScanBatch() {
                tableScanState.isScanning = true;
                $('#continue-table-scan').prop('disabled', true).text('Scanning...');
                $('#table-scan-current-status').text('Scanning batch...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fr2025_scan_tables_batch',
                        offset: tableScanState.currentOffset,
                        batch_size: tableScanState.batchSize,
                        _ajax_nonce: '<?php echo wp_create_nonce('fr2025_pdf_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            tableScanState.processedCount += response.data.processed_count;
                            $('#table-scan-processed').text(tableScanState.processedCount);
                            updateTableScanProgress();

                            if (response.data.found_tables.length > 0) {
                                tableScanState.foundTables = tableScanState.foundTables.concat(response.data.found_tables);
                                var resultsHtml = '<strong>' + tableScanState.foundTables.length + ' publications with tables:</strong><br>';
                                tableScanState.foundTables.forEach(function(item) {
                                    resultsHtml += '<a href="' + item.edit_url + '" target="_blank">' + item.title + '</a> (' + item.table_count + ' tables)<br>';
                                });
                                $('#table-scan-results').html(resultsHtml);
                            }

                            tableScanState.currentOffset += tableScanState.batchSize;

                            if (tableScanState.processedCount >= tableScanState.totalPosts) {
                                $('#table-scan-current-status').text('Scan complete');
                                $('#continue-table-scan').hide();
                                $('#start-table-scan').show().text('Rescan');
                            } else {
                                $('#table-scan-current-status').text('Ready for next batch');
                                $('#continue-table-scan').prop('disabled', false).text('Continue Scan');
                            }
                        }
                        tableScanState.isScanning = false;
                    }
                });
            }

            // Initialize everything
            initializeGenerate();
            refreshCurrentProcesses();
            initializeTableScan();
            
            // Auto-refresh current processes every 30 seconds
            setInterval(refreshCurrentProcesses, 30000);
        });
        </script>

        <style>
        .pdf-section h3 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .pdf-section {
            min-height: 300px;
        }
        @media (max-width: 1200px) {
            div[style*="grid-template-columns: 1fr 1fr 1fr"] {
                grid-template-columns: 1fr !important;
            }
        }
        </style>
    </div>
    <?php
}

// Add the AJAX handlers for the new functionality
add_action('wp_ajax_fr2025_get_current_processes', 'fr2025_ajax_get_current_processes');
add_action('wp_ajax_fr2025_get_total_publications', 'fr2025_ajax_get_total_publications');
add_action('wp_ajax_fr2025_scan_tables_batch', 'fr2025_ajax_scan_tables_batch');

/**
 * AJAX handler for current processes (simplified)
 */
function fr2025_ajax_get_current_processes() {
    check_ajax_referer('fr2025_pdf_nonce', '_ajax_nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'pdf_generation_queue';
    
    // Only get current/recent items (not all completed)
    $queue_items = $wpdb->get_results(
        "SELECT *, TIMESTAMPDIFF(MINUTE, queued_at, NOW()) as minutes_in_queue 
         FROM {$table_name} 
         WHERE status IN ('processing', 'pending', 'failed') 
            OR (status = 'completed' AND completed_at > DATE_SUB(NOW(), INTERVAL 1 HOUR))
         ORDER BY 
            CASE status 
                WHEN 'processing' THEN 1 
                WHEN 'pending' THEN 2 
                WHEN 'failed' THEN 3 
                ELSE 4 
            END,
            queued_at DESC
         LIMIT 10"
    );
    
    $html = '';
    if (empty($queue_items)) {
        $html = '<p style="color: #666; font-size: 12px;">No current processes</p>';
    } else {
        foreach ($queue_items as $item) {
            $post_title = get_the_title($item->post_id) ?: 'Unknown Post';
            $status_color = [
                'processing' => '#ff9800',
                'pending' => '#2196F3', 
                'failed' => '#f44336',
                'completed' => '#4CAF50'
            ][$item->status] ?? '#666';
            
            $stuck_warning = '';
            if ($item->status === 'processing' && $item->minutes_in_queue > 10) {
                $stuck_warning = ' <span style="color: red; font-size: 10px;">(STUCK?)</span>';
            }
            
            $html .= '<div style="padding: 5px; border-left: 3px solid ' . $status_color . '; margin-bottom: 5px; font-size: 12px;">';
            $html .= '<strong>' . esc_html(substr($post_title, 0, 30)) . '</strong>' . $stuck_warning . '<br>';
            $html .= '<span style="color: #666;">' . strtoupper($item->status) . ' (' . $item->minutes_in_queue . 'm)</span>';
            $html .= '</div>';
        }
    }
    
    wp_send_json_success([
        'html' => $html,
        'cron_locked' => (bool)get_transient('pdf_generation_lock')
    ]);
}

/**
 * AJAX handler to get total publications count
 */
function fr2025_ajax_get_total_publications() {
    check_ajax_referer('fr2025_pdf_nonce', '_ajax_nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $query_args = array(
        'post_type' => 'publications',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'post_status' => 'publish'
    );
    
    $posts_query = new WP_Query($query_args);
    wp_send_json_success(array('total' => $posts_query->post_count));
}

/**
 * AJAX handler to scan publications for tables
 */
function fr2025_ajax_scan_tables_batch() {
    check_ajax_referer('fr2025_pdf_nonce', '_ajax_nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $offset = intval($_POST['offset']);
    $batch_size = intval($_POST['batch_size']);
    
    $query_args = array(
        'post_type' => 'publications',
        'posts_per_page' => $batch_size,
        'offset' => $offset,
        'post_status' => 'publish',
        'orderby' => 'ID',
        'order' => 'ASC'
    );
    
    $posts_query = new WP_Query($query_args);
    $found_tables = [];
    $processed_count = 0;
    
    if ($posts_query->have_posts()) {
        foreach ($posts_query->posts as $post) {
            $processed_count++;
            $table_count = preg_match_all('/<table[^>]*>.*?<\/table>/is', $post->post_content);
            
            if ($table_count > 0) {
                $found_tables[] = [
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'table_count' => $table_count,
                    'edit_url' => get_edit_post_link($post->ID)
                ];
            }
        }
    }
    
    wp_send_json_success([
        'processed_count' => $processed_count,
        'found_tables' => $found_tables
    ]);
}