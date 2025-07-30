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