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


// --- ADMIN TOOL SUBMENU ---

/**
 * Adds the admin menu item for the PDF generation tool under 'Publications'.
 */
function fr2025_add_pdf_generation_tool_page() {
    add_submenu_page(
        'edit.php?post_type=publications', // Parent slug for 'Publications' CPT
        'Generate Missing PDFs',           // Page title
        'Generate Missing PDFs',           // Menu title
        'manage_options',                  // Capability required to access
        'fr2025-generate-pdfs',            // Menu slug
        'fr2025_render_pdf_generation_tool_page' // Callback function to render the page
    );
}
add_action('admin_menu', 'fr2025_add_pdf_generation_tool_page');

/**
 * Renders the content of the PDF generation tool admin page.
 */
function fr2025_render_pdf_generation_tool_page() {
    ?>
    <div class="wrap">
        <h1>Generate Missing Publication PDFs</h1>
        <p>This tool will queue all 'publications' posts that do not currently have a PDF assigned. The PDFs will be generated in the background by the cron system.</p>
        <p>You can process posts in batches at your own pace. Click "Process Next Batch" to continue.</p>

        <div id="pdf-generation-status">
            <p><strong>Status:</strong> <span id="current-status">Idle</span></p>
            <p><strong>Queued (this session):</strong> <span id="queued-count">0</span> / <span id="total-posts">Calculating...</span></p>
            <div class="progress-bar-container" style="width: 100%; background-color: #f3f3f3; border-radius: 5px; height: 20px; overflow: hidden;">
                <div class="progress-bar" style="height: 100%; width: 0%; background-color: #4CAF50; text-align: center; color: white; line-height: 20px;">0%</div>
            </div>
            <p id="last-message" style="margin-top: 10px;"></p>
        </div>

        <button id="start-pdf-generation" class="button button-primary" style="margin-top: 20px;">Start Generation</button>
        <button id="process-next-batch" class="button button-secondary" style="margin-top: 20px; display: none;">Process Next Batch</button>
        <button id="reset-pdf-generation" class="button" style="margin-top: 20px; margin-left: 10px; display: none;">Reset Tool</button>

        <script>
            jQuery(document).ready(function($) {
                var totalPosts = 0;
                var processedCount = 0; // Posts processed in the current session
                var batchSize = 100; // You can adjust this for more or less intense batches
                var currentOffset = 0; // Offset for fetching posts from DB
                var isProcessingBatch = false; // Flag to prevent multiple clicks

                function updateProgressBar() {
                    if (totalPosts === 0) return;
                    var percentage = (processedCount / totalPosts) * 100;
                    $('.progress-bar').css('width', percentage + '%').text(Math.round(percentage) + '%');
                }

                function logMessage(type, message) {
                    $('#last-message').html('<span style="color:' + (type === 'error' ? 'red' : (type === 'warning' ? 'orange' : (type === 'success' ? 'green' : 'black'))) + ';">' + message + '</span>');
                }

                function fetchTotalPosts() {
                    $('#current-status').text('Calculating total missing publications...');
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'fr2025_pdf_get_total_missing_pdfs',
                            _ajax_nonce: '<?php echo wp_create_nonce('fr2025_pdf_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                totalPosts = response.data.total;
                                $('#total-posts').text(totalPosts);
                                if (totalPosts === 0) {
                                    $('#current-status').text('Complete');
                                    logMessage('success', 'No publications found missing PDFs. All good!');
                                    $('#start-pdf-generation').prop('disabled', true).text('No PDFs to Generate').hide();
                                    $('#process-next-batch').hide();
                                    $('#reset-pdf-generation').show();
                                } else {
                                    $('#current-status').text('Ready to start');
                                    $('#start-pdf-generation').prop('disabled', false).show().text('Start Generation');
                                    $('#process-next-batch').hide();
                                    $('#reset-pdf-generation').hide();
                                    logMessage('info', 'Found ' + totalPosts + ' publications missing PDFs. Click "Start Generation" to begin.');
                                }
                            } else {
                                logMessage('error', response.data || 'Error fetching total posts.');
                                $('#start-pdf-generation').prop('disabled', true).hide();
                                $('#process-next-batch').hide();
                            }
                        },
                        error: function() {
                            logMessage('error', 'AJAX error fetching total posts.');
                            $('#start-pdf-generation').prop('disabled', true).hide();
                            $('#process-next-batch').hide();
                        }
                    });
                }

                function processBatch() {
                    isProcessingBatch = true;
                    $('#start-pdf-generation').prop('disabled', true).hide();
                    $('#process-next-batch').prop('disabled', true).text('Processing...').show();
                    $('#current-status').text('Processing batch ' + (Math.floor(currentOffset / batchSize) + 1) + '...');
                    logMessage('info', 'Sending request for batch...');


                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'fr2025_pdf_queue_missing_pdfs',
                            offset: currentOffset,
                            batch_size: batchSize,
                            _ajax_nonce: '<?php echo wp_create_nonce('fr2025_pdf_nonce'); ?>'
                        },
                        success: function(response) {
                            isProcessingBatch = false;
                            if (response.success) {
                                processedCount += response.data.processed_count;
                                $('#queued-count').text(processedCount);
                                updateProgressBar();
                                logMessage('info', response.data.message);

                                currentOffset += batchSize; // Update offset for the next batch

                                if (processedCount >= totalPosts) {
                                    $('#current-status').text('Complete!');
                                    logMessage('success', 'All available publications queued for PDF generation. You can now close this page.');
                                    $('#start-pdf-generation').hide();
                                    $('#process-next-batch').hide();
                                    $('#reset-pdf-generation').show();
                                } else {
                                    $('#current-status').text('Batch complete. ' + (totalPosts - processedCount) + ' remaining.');
                                    $('#process-next-batch').prop('disabled', false).text('Process Next Batch');
                                }
                            } else {
                                $('#current-status').text('Error!');
                                logMessage('error', response.data || 'Error processing batch.');
                                $('#process-next-batch').prop('disabled', false).text('Process Next Batch (Error)');
                                $('#start-pdf-generation').hide(); // Stay on 'Process Next Batch' mode
                            }
                        },
                        error: function() {
                            isProcessingBatch = false;
                            $('#current-status').text('Error!');
                            logMessage('error', 'AJAX error during batch processing. Check console for details.');
                            $('#process-next-batch').prop('disabled', false).text('Process Next Batch (Error)');
                            $('#start-pdf-generation').hide();
                        }
                    });
                }

                // Event Handlers
                $('#start-pdf-generation').on('click', function() {
                    if (isProcessingBatch) return;
                    $(this).hide(); // Hide 'Start' button
                    $('#process-next-batch').show(); // Show 'Next Batch' button
                    processedCount = 0; // Reset count for new session
                    currentOffset = 0;
                    $('#queued-count').text('0');
                    updateProgressBar();
                    processBatch(); // Start first batch
                });

                $('#process-next-batch').on('click', function() {
                    if (isProcessingBatch) return;
                    if (currentOffset >= totalPosts) {
                        logMessage('info', 'All posts have already been processed.');
                        return;
                    }
                    processBatch();
                });

                $('#reset-pdf-generation').on('click', function() {
                    // Reset UI and fetch total posts again
                    totalPosts = 0;
                    processedCount = 0;
                    currentOffset = 0;
                    $('#queued-count').text('0');
                    $('#total-posts').text('Calculating...');
                    $('#current-status').text('Idle');
                    $('.progress-bar').css('width', '0%').text('0%');
                    $('#last-message').empty();
                    $('#reset-pdf-generation').hide();
                    $('#process-next-batch').hide();
                    $('#start-pdf-generation').show().prop('disabled', false).text('Start Generation');
                    fetchTotalPosts(); // Re-calculate in case new posts were added/updated
                });

                // Initial load
                fetchTotalPosts();
            });
        </script>
    </div>
    <?php
}

/**
 * AJAX handler to get the total number of missing PDFs.
 */
function fr2025_ajax_get_total_missing_pdfs() {
    check_ajax_referer('fr2025_pdf_nonce', '_ajax_nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to perform this action.');
    }

    global $wpdb;
    // Get post IDs that already have a non-empty pdf_download_url
    $post_ids_with_pdf = $wpdb->get_col(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'pdf_download_url' AND meta_value != ''"
    );

    $query_args = array(
        'post_type'      => 'publications',
        'posts_per_page' => -1, // Get all posts to count
        'fields'         => 'ids',
        'post_status'    => 'publish', // Only queue published posts
        'post__not_in'   => !empty($post_ids_with_pdf) ? $post_ids_with_pdf : array(0), // Exclude posts that already have a PDF URL
        'meta_query'     => array(
            'relation' => 'AND', // Ensure both conditions are met
            array(
                'key'     => 'publication_number',
                'compare' => 'EXISTS', // Must have a publication number field
            ),
            array(
                'key'     => 'publication_number',
                'value'   => '',
                'compare' => '!=', // And it must not be empty
            ),
        ),
    );

    $posts_query = new WP_Query($query_args);
    $total_missing = $posts_query->post_count;

    wp_send_json_success(array('total' => $total_missing));
}
add_action('wp_ajax_fr2025_pdf_get_total_missing_pdfs', 'fr2025_ajax_get_total_missing_pdfs');


/**
 * AJAX handler to queue a batch of missing PDFs.
 */
function fr2025_ajax_queue_missing_pdfs() {
    check_ajax_referer('fr2025_pdf_nonce', '_ajax_nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to perform this action.');
    }

    $offset = intval($_POST['offset']);
    $batch_size = intval($_POST['batch_size']);

    global $wpdb;
    // Get post IDs that already have a non-empty pdf_download_url
    $post_ids_with_pdf = $wpdb->get_col(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'pdf_download_url' AND meta_value != ''"
    );

    $query_args = array(
        'post_type'      => 'publications',
        'posts_per_page' => $batch_size,
        'offset'         => $offset,
        'fields'         => 'ids',
        'post_status'    => 'publish', // Only queue published posts
        'post__not_in'   => !empty($post_ids_with_pdf) ? $post_ids_with_pdf : array(0),
        'meta_query'     => array(
            'relation' => 'AND', // Ensure both conditions are met
            array(
                'key'     => 'publication_number',
                'compare' => 'EXISTS', // Must have a publication number field
            ),
            array(
                'key'     => 'publication_number',
                'value'   => '',
                'compare' => '!=', // And it must not be empty
            ),
        ),
        'orderby'        => 'ID', // Important for consistent pagination
        'order'          => 'ASC',
    );

    $posts_query = new WP_Query($query_args);
    $processed_count = 0;
    $messages = array();

    if ($posts_query->have_posts()) {
        foreach ($posts_query->posts as $post_id) {
            $publication_number = get_field('publication_number', $post_id);
            $post_title = get_the_title($post_id);

            if (empty($publication_number)) {
                 // This should theoretically be caught by meta_query, but as a safeguard
                $messages[] = sprintf("Skipped post ID %d ('%s'): Missing Publication Number.", $post_id, $post_title);
                continue;
            }

            // Check if manual PDF exists before queuing from the tool
            $manual_pdf_attachment = get_field('pdf', $post_id);
            $manual_pdf_exists_for_queueing = false;
            if (is_array($manual_pdf_attachment) && !empty($manual_pdf_attachment['url'])) {
                $manual_pdf_exists_for_queueing = true;
            } elseif (is_string($manual_pdf_attachment) && filter_var($manual_pdf_attachment, FILTER_VALIDATE_URL)) {
                $manual_pdf_exists_for_queueing = true;
            }

            if ($manual_pdf_exists_for_queueing) {
                $messages[] = sprintf("Skipped post ID %d ('%s'): Manual PDF already exists.", $post_id, $post_title);
                continue; // Skip queuing if manual PDF is present
            }


            // Insert or update in queue (function from pdf-queue.php)
            if (insert_or_update_pdf_queue($post_id, 'pending')) {
                $messages[] = sprintf("Queued post ID %d ('%s').", $post_id, $post_title);
                $processed_count++;
            } else {
                $messages[] = sprintf("Failed to queue post ID %d ('%s').", $post_id, $post_title);
            }
        }
    }

    wp_send_json_success(array(
        'processed_count' => $processed_count,
        'message' => implode('<br>', $messages)
    ));
}
add_action('wp_ajax_fr2025_pdf_queue_missing_pdfs', 'fr2025_ajax_queue_missing_pdfs');

/**
 * Adds the PDF process monitor admin page
 */
function fr2025_add_pdf_monitor_page() {
    add_submenu_page(
        'edit.php?post_type=publications',
        'PDF Process Monitor',
        'PDF Process Monitor', 
        'manage_options',
        'fr2025-pdf-monitor',
        'fr2025_render_pdf_monitor_page'
    );
}
add_action('admin_menu', 'fr2025_add_pdf_monitor_page');

/**
 * Renders the PDF process monitor page
 */
function fr2025_render_pdf_monitor_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pdf_generation_queue';
    ?>
    <div class="wrap">
        <h1>PDF Process Monitor</h1>
        
        <div id="process-status" style="margin: 20px 0; padding: 15px; background: #f1f1f1; border-radius: 5px;">
            <h3>System Status</h3>
            <p><strong>Server Memory:</strong> <?php echo ini_get('memory_limit'); ?> (Used: <?php echo size_format(memory_get_usage(true)); ?>)</p>
            <p><strong>Max Execution Time:</strong> <?php echo ini_get('max_execution_time'); ?>s</p>
            <p><strong>Cron Lock Status:</strong> <span id="cron-lock-status"><?php echo get_transient('pdf_generation_lock') ? 'LOCKED' : 'FREE'; ?></span></p>
            <p><strong>Next Cron Run:</strong> <?php 
                $next_cron = wp_next_scheduled('my_pdf_generation_cron_hook');
                echo $next_cron ? date('Y-m-d H:i:s', $next_cron) : 'Not scheduled';
            ?></p>
        </div>

        <div style="margin: 20px 0;">
            <button id="refresh-monitor" class="button button-primary">Refresh Status</button>
            <button id="clear-cron-lock" class="button button-secondary">Clear Cron Lock</button>
            <button id="force-cron-run" class="button button-secondary">Force Cron Run</button>
        </div>

        <div id="queue-table-container">
            <!-- Table will be populated by AJAX -->
        </div>

        <script>
        jQuery(document).ready(function($) {
            function refreshMonitor() {
                $('#refresh-monitor').prop('disabled', true).text('Refreshing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fr2025_get_pdf_queue_status',
                        _ajax_nonce: '<?php echo wp_create_nonce('fr2025_pdf_monitor_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#queue-table-container').html(response.data.table_html);
                            $('#cron-lock-status').text(response.data.cron_locked ? 'LOCKED' : 'FREE');
                        }
                    },
                    complete: function() {
                        $('#refresh-monitor').prop('disabled', false).text('Refresh Status');
                    }
                });
            }

            function cancelProcess(queueId, postId) {
                if (!confirm('Cancel this PDF generation process?')) return;
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fr2025_cancel_pdf_process',
                        queue_id: queueId,
                        post_id: postId,
                        _ajax_nonce: '<?php echo wp_create_nonce('fr2025_pdf_monitor_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            refreshMonitor();
                        }
                        alert(response.data || 'Action completed');
                    }
                });
            }

            function requeueProcess(queueId, postId) {
                if (!confirm('Requeue this PDF for generation?')) return;
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST', 
                    data: {
                        action: 'fr2025_requeue_pdf_process',
                        queue_id: queueId,
                        post_id: postId,
                        _ajax_nonce: '<?php echo wp_create_nonce('fr2025_pdf_monitor_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            refreshMonitor();
                        }
                        alert(response.data || 'Action completed');
                    }
                });
            }

            // Event handlers
            $('#refresh-monitor').on('click', refreshMonitor);
            
            $('#clear-cron-lock').on('click', function() {
                if (!confirm('Clear the cron lock? This should only be done if processes are stuck.')) return;
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fr2025_clear_cron_lock',
                        _ajax_nonce: '<?php echo wp_create_nonce('fr2025_pdf_monitor_nonce'); ?>'
                    },
                    success: function(response) {
                        alert(response.data || 'Cron lock cleared');
                        refreshMonitor();
                    }
                });
            });

            $('#force-cron-run').on('click', function() {
                if (!confirm('Force a cron run now? This will process pending PDFs immediately.')) return;
                
                $(this).prop('disabled', true).text('Running...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fr2025_force_cron_run',
                        _ajax_nonce: '<?php echo wp_create_nonce('fr2025_pdf_monitor_nonce'); ?>'
                    },
                    success: function(response) {
                        alert(response.data || 'Cron run completed');
                        refreshMonitor();
                    },
                    complete: function() {
                        $('#force-cron-run').prop('disabled', false).text('Force Cron Run');
                    }
                });
            });

            // Delegate events for dynamically added buttons
            $(document).on('click', '.cancel-process', function() {
                const queueId = $(this).data('queue-id');
                const postId = $(this).data('post-id');
                cancelProcess(queueId, postId);
            });

            $(document).on('click', '.requeue-process', function() {
                const queueId = $(this).data('queue-id');
                const postId = $(this).data('post-id');
                requeueProcess(queueId, postId);
            });

            // Initial load
            refreshMonitor();
            
            // Auto-refresh every 30 seconds
            setInterval(refreshMonitor, 30000);
        });
        </script>
    </div>
    <?php
}

/**
 * AJAX handler to get current queue status
 */
function fr2025_ajax_get_pdf_queue_status() {
    check_ajax_referer('fr2025_pdf_monitor_nonce', '_ajax_nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'pdf_generation_queue';
    
    $queue_items = $wpdb->get_results(
        "SELECT *, TIMESTAMPDIFF(MINUTE, queued_at, NOW()) as minutes_in_queue 
         FROM {$table_name} 
         ORDER BY 
            CASE status 
                WHEN 'processing' THEN 1 
                WHEN 'pending' THEN 2 
                WHEN 'failed' THEN 3 
                ELSE 4 
            END,
            queued_at DESC"
    );
    
    $table_html = '<h3>PDF Generation Queue (' . count($queue_items) . ' items)</h3>';
    $table_html .= '<table class="wp-list-table widefat fixed striped">';
    $table_html .= '<thead><tr>';
    $table_html .= '<th>Post ID</th><th>Title</th><th>Status</th><th>Queued</th><th>Time in Queue</th><th>Message</th><th>Actions</th>';
    $table_html .= '</tr></thead><tbody>';
    
    if (empty($queue_items)) {
        $table_html .= '<tr><td colspan="7">No items in queue</td></tr>';
    } else {
        foreach ($queue_items as $item) {
            $post_title = get_the_title($item->post_id) ?: 'Unknown Post';
            $status_class = '';
            $actions = '';
            
            switch ($item->status) {
                case 'processing':
                    $status_class = 'style="background-color: #fff3cd;"';
                    $actions = '<button class="button button-small cancel-process" data-queue-id="' . $item->id . '" data-post-id="' . $item->post_id . '">Cancel</button>';
                    // Highlight stuck processes (processing for more than 10 minutes)
                    if ($item->minutes_in_queue > 10) {
                        $status_class = 'style="background-color: #f8d7da;"';
                        $actions .= ' <strong style="color: red;">STUCK?</strong>';
                    }
                    break;
                case 'pending':
                    $status_class = 'style="background-color: #d1ecf1;"';
                    $actions = '<button class="button button-small cancel-process" data-queue-id="' . $item->id . '" data-post-id="' . $item->post_id . '">Remove</button>';
                    break;
                case 'failed':
                    $status_class = 'style="background-color: #f8d7da;"';
                    $actions = '<button class="button button-small requeue-process" data-queue-id="' . $item->id . '" data-post-id="' . $item->post_id . '">Retry</button>';
                    break;
                case 'completed':
                    $status_class = 'style="background-color: #d4edda;"';
                    $actions = '<button class="button button-small cancel-process" data-queue-id="' . $item->id . '" data-post-id="' . $item->post_id . '">Remove</button>';
                    break;
            }
            
            $table_html .= '<tr ' . $status_class . '>';
            $table_html .= '<td>' . $item->post_id . '</td>';
            $table_html .= '<td><a href="' . get_edit_post_link($item->post_id) . '">' . esc_html($post_title) . '</a></td>';
            $table_html .= '<td><strong>' . strtoupper($item->status) . '</strong></td>';
            $table_html .= '<td>' . $item->queued_at . '</td>';
            $table_html .= '<td>' . $item->minutes_in_queue . ' min</td>';
            $table_html .= '<td>' . esc_html($item->message ?: 'N/A') . '</td>';
            $table_html .= '<td>' . $actions . '</td>';
            $table_html .= '</tr>';
        }
    }
    
    $table_html .= '</tbody></table>';
    
    wp_send_json_success([
        'table_html' => $table_html,
        'cron_locked' => (bool)get_transient('pdf_generation_lock')
    ]);
}
add_action('wp_ajax_fr2025_get_pdf_queue_status', 'fr2025_ajax_get_pdf_queue_status');

/**
 * AJAX handler to cancel a PDF process
 */
function fr2025_ajax_cancel_pdf_process() {
    check_ajax_referer('fr2025_pdf_monitor_nonce', '_ajax_nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $queue_id = intval($_POST['queue_id']);
    $post_id = intval($_POST['post_id']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'pdf_generation_queue';
    
    // Remove from queue entirely
    $result = $wpdb->delete($table_name, ['id' => $queue_id], ['%d']);
    
    if ($result !== false) {
        wp_send_json_success('Process cancelled and removed from queue');
    } else {
        wp_send_json_error('Failed to cancel process');
    }
}
add_action('wp_ajax_fr2025_cancel_pdf_process', 'fr2025_ajax_cancel_pdf_process');

/**
 * AJAX handler to requeue a failed PDF process
 */
function fr2025_ajax_requeue_pdf_process() {
    check_ajax_referer('fr2025_pdf_monitor_nonce', '_ajax_nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $queue_id = intval($_POST['queue_id']);
    $post_id = intval($_POST['post_id']);
    
    if (update_pdf_queue_status($queue_id, 'pending', 'Manually requeued from monitor')) {
        wp_send_json_success('Process requeued successfully');
    } else {
        wp_send_json_error('Failed to requeue process');
    }
}
add_action('wp_ajax_fr2025_requeue_pdf_process', 'fr2025_ajax_requeue_pdf_process');

/**
 * AJAX handler to clear cron lock
 */
function fr2025_ajax_clear_cron_lock() {
    check_ajax_referer('fr2025_pdf_monitor_nonce', '_ajax_nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    delete_transient('pdf_generation_lock');
    wp_send_json_success('Cron lock cleared');
}
add_action('wp_ajax_fr2025_clear_cron_lock', 'fr2025_ajax_clear_cron_lock');

/**
 * AJAX handler to force cron run
 */
function fr2025_ajax_force_cron_run() {
    check_ajax_referer('fr2025_pdf_monitor_nonce', '_ajax_nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    // Clear any existing lock first
    delete_transient('pdf_generation_lock');
    
    // Run the cron function directly
    process_pdf_generation_queue();
    
    wp_send_json_success('Cron run completed');
}
add_action('wp_ajax_fr2025_force_cron_run', 'fr2025_ajax_force_cron_run');