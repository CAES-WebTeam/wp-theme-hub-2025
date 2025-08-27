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
function queue_pdf_generation_on_save($post_id, $post)
{
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
function display_pdf_generation_admin_notices()
{
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
function add_pdf_status_column($columns)
{
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
function display_pdf_status_column_content($column_name, $post_id)
{
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
function create_pdf_cache_directory()
{
    $upload_dir = wp_upload_dir();
    $cache_dir_path = $upload_dir['basedir'] . '/generated-pub-pdfs/';
    if (!file_exists($cache_dir_path)) {
        wp_mkdir_p($cache_dir_path);
    }
}
add_action('after_setup_theme', 'create_pdf_cache_directory');

/**
 * PDF Maintenance Admin Page
 */
function fr2025_add_pdf_maintenance_page()
{
    add_submenu_page(
        'edit.php?post_type=publications',
        'PDF Maintenance',
        'PDF Maintenance',
        'manage_options',
        'fr2025-pdf-maintenance',
        'fr2025_render_pdf_maintenance_page'
    );
}
add_action('admin_menu', 'fr2025_add_pdf_maintenance_page');

function fr2025_render_pdf_maintenance_page()
{
?>
    <div class="wrap">
        <h1>PDF Maintenance</h1>

        <!-- Quick Actions Bar -->
        <div style="background: #f1f1f1; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <button id="scan-table-pdfs" class="button button-primary">Find PDFs with Tables</button>
            <button id="scan-missing-pdfs" class="button button-primary">Find Missing PDFs</button>
            <button id="refresh-processes" class="button button-secondary">Refresh Processes</button>
            <button id="clear-cron-lock" class="button button-secondary">Clear Stuck Processes</button>
            <span id="quick-status" style="margin-left: 20px; color: #666;"></span>
        </div>

        <!-- Three Problem-Solving Sections -->
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-top: 20px;">

            <!-- Problem 1: Publications with Tables -->
            <div class="maintenance-section">
                <h3>Tables in Publications</h3>
                <p style="font-size: 12px; color: #666; margin-bottom: 15px;">Publications that contain tables (shows PDF generation status and allows regeneration)</p>
                <div id="table-pdfs-content">
                    <p style="color: #999; font-style: italic;">Click "Find PDFs with Tables" to scan</p>
                </div>
            </div>

            <!-- Problem 2: Missing PDFs -->
            <div class="maintenance-section">
                <h3>📄 Missing PDFs</h3>
                <p style="font-size: 12px; color: #666; margin-bottom: 15px;">Published publications that don't have generated PDFs yet</p>
                <div id="missing-pdfs-content">
                    <p style="color: #999; font-style: italic;">Click "Find Missing PDFs" to scan</p>
                </div>
            </div>

            <!-- Problem 3: Process Monitor -->
            <div class="maintenance-section">
                <h3>⚙️ Current Processes</h3>
                <p style="font-size: 12px; color: #666; margin-bottom: 15px;">Active PDF generation processes and recent completions</p>
                <div id="current-processes-content">
                    <p style="color: #999; font-style: italic;">Loading...</p>
                </div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function($) {

                function setQuickStatus(message, type = 'info') {
                    var color = type === 'error' ? 'red' : (type === 'success' ? 'green' : '#666');
                    $('#quick-status').html('<span style="color:' + color + ';">' + message + '</span>');
                }

                function refreshProcesses() {
                    setQuickStatus('Refreshing processes...');
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'fr2025_get_current_processes_simple',
                            _ajax_nonce: '<?php echo wp_create_nonce('fr2025_pdf_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#current-processes-content').html(response.data.html);
                                var statusText = response.data.cron_locked ? 'Cron LOCKED' : 'Cron FREE';
                                setQuickStatus(statusText + ' - ' + response.data.count + ' processes');
                            } else {
                                setQuickStatus('Error refreshing processes', 'error');
                            }
                        }
                    });
                }

                function scanTablePDFs() {
                    loadTablePage(1);
                }

                function loadTablePage(page) {
                    $('#table-pdfs-content').html('<p style="color: #ff9800;">Loading publications with tables (page ' + page + ')...</p>');
                    setQuickStatus('Loading page ' + page + '...');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'fr2025_find_pdfs_with_tables',
                            page: page,
                            _ajax_nonce: '<?php echo wp_create_nonce('fr2025_pdf_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#table-pdfs-content').html(response.data.html);
                                setQuickStatus('Page ' + response.data.page + ' loaded - ' + response.data.total_found + ' publications with tables', 'success');
                            } else {
                                $('#table-pdfs-content').html('<p style="color: red;">Error: ' + response.data + '</p>');
                                setQuickStatus('Error loading page', 'error');
                            }
                        },
                        error: function() {
                            $('#table-pdfs-content').html('<p style="color: red;">Network error</p>');
                            setQuickStatus('Network error', 'error');
                        }
                    });
                }

                // Add pagination click handler to the existing delegated event handlers
                $(document).on('click', '.table-pagination-btn', function() {
                    var page = $(this).data('page');
                    loadTablePage(page);
                });

                function scanMissingPDFs() {
                    $('#missing-pdfs-content').html('<p style="color: #ff9800;">Scanning for publications without PDFs...</p>');
                    setQuickStatus('Scanning for missing PDFs...');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'fr2025_find_missing_pdfs',
                            _ajax_nonce: '<?php echo wp_create_nonce('fr2025_pdf_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#missing-pdfs-content').html(response.data.html);
                                setQuickStatus('Found ' + response.data.count + ' missing PDFs', 'success');
                            } else {
                                $('#missing-pdfs-content').html('<p style="color: red;">Error: ' + response.data + '</p>');
                                setQuickStatus('Error scanning missing PDFs', 'error');
                            }
                        }
                    });
                }

                // Event handlers
                $('#scan-table-pdfs').on('click', scanTablePDFs);
                $('#scan-missing-pdfs').on('click', scanMissingPDFs);
                $('#refresh-processes').on('click', refreshProcesses);

                $('#clear-cron-lock').on('click', function() {
                    if (!confirm('Clear cron lock and cancel stuck processes?')) return;
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'fr2025_clear_cron_lock',
                            _ajax_nonce: '<?php echo wp_create_nonce('fr2025_pdf_nonce'); ?>'
                        },
                        success: function(response) {
                            setQuickStatus('Cron lock cleared', 'success');
                            refreshProcesses();
                        }
                    });
                });

                // Delegated event handlers for dynamic buttons
                $(document).on('click', '.regenerate-pdf-btn', function() {
                    var postId = $(this).data('post-id');
                    var postTitle = $(this).data('post-title');

                    if (!confirm('Regenerate PDF for "' + postTitle + '"?')) return;

                    $(this).prop('disabled', true).text('Queuing...');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'fr2025_queue_single_pdf',
                            post_id: postId,
                            _ajax_nonce: '<?php echo wp_create_nonce('fr2025_pdf_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                setQuickStatus('Queued PDF generation for ' + postTitle, 'success');
                                refreshProcesses();
                            } else {
                                setQuickStatus('Failed to queue PDF: ' + response.data, 'error');
                            }
                        }
                    });
                });

                $(document).on('click', '.cancel-process-btn', function() {
                    var queueId = $(this).data('queue-id');

                    if (!confirm('Cancel this PDF generation process?')) return;

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'fr2025_cancel_pdf_process',
                            queue_id: queueId,
                            _ajax_nonce: '<?php echo wp_create_nonce('fr2025_pdf_nonce'); ?>'
                        },
                        success: function(response) {
                            setQuickStatus(response.data || 'Process cancelled', 'success');
                            refreshProcesses();
                        }
                    });
                });

                // Initialize
                refreshProcesses();
            });
        </script>

        <style>
            .maintenance-section {
                border: 1px solid #ddd;
                border-radius: 5px;
                padding: 15px;
                background: white;
                min-height: 400px;
            }

            .maintenance-section h3 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
            }

            .pdf-item {
                padding: 8px;
                border: 1px solid #eee;
                border-radius: 3px;
                margin-bottom: 8px;
                background: #fafafa;
            }

            .pdf-item-title {
                font-weight: bold;
                font-size: 13px;
                margin-bottom: 4px;
            }

            .pdf-item-meta {
                font-size: 11px;
                color: #666;
                margin-bottom: 6px;
            }

            .process-item {
                padding: 6px;
                margin-bottom: 6px;
                border-left: 3px solid #ccc;
                font-size: 12px;
            }

            .process-item.processing {
                border-left-color: #ff9800;
            }

            .process-item.pending {
                border-left-color: #2196F3;
            }

            .process-item.failed {
                border-left-color: #f44336;
            }

            .process-item.completed {
                border-left-color: #4CAF50;
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

// AJAX handlers for the three main functions
add_action('wp_ajax_fr2025_find_pdfs_with_tables', 'fr2025_ajax_find_pdfs_with_tables');
add_action('wp_ajax_fr2025_find_missing_pdfs', 'fr2025_ajax_find_missing_pdfs');
add_action('wp_ajax_fr2025_get_current_processes_simple', 'fr2025_ajax_get_current_processes_simple');
add_action('wp_ajax_fr2025_queue_single_pdf', 'fr2025_ajax_queue_single_pdf');
add_action('wp_ajax_fr2025_cancel_pdf_process', 'fr2025_ajax_cancel_pdf_process');
add_action('wp_ajax_fr2025_clear_cron_lock', 'fr2025_ajax_clear_cron_lock');

/**
 * Find publications with tables
 */
function fr2025_ajax_find_pdfs_with_tables()
{
    check_ajax_referer('fr2025_pdf_nonce', '_ajax_nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Insufficient permissions');

    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $per_page = 20; // Show 20 results per page
    $offset = ($page - 1) * $per_page;

    global $wpdb;

    // Get all publications (with pagination)
    $posts = $wpdb->get_results($wpdb->prepare(
        "SELECT ID, post_title 
         FROM {$wpdb->posts} 
         WHERE post_type = 'publications' 
         AND post_status = 'publish' 
         ORDER BY post_title 
         LIMIT %d OFFSET %d",
        $per_page,
        $offset
    ));

    // Get total count for pagination
    $total_posts = $wpdb->get_var(
        "SELECT COUNT(*) 
         FROM {$wpdb->posts} 
         WHERE post_type = 'publications' 
         AND post_status = 'publish'"
    );

    $publications_with_tables = array();

    foreach ($posts as $post) {
        $post_content = get_post_field('post_content', $post->ID);
        $table_count = preg_match_all('/<table[^>]*>.*?<\/table>/is', $post_content);

        if ($table_count > 0) {
            // Get PDF info
            $pdf_url = get_field('pdf_download_url', $post->ID);
            $pdf_generated_date = '';

            // Get latest PDF generation date from queue
            $queue_item = $wpdb->get_row($wpdb->prepare(
                "SELECT completed_at FROM {$wpdb->prefix}pdf_generation_queue 
                 WHERE post_id = %d AND status = 'completed' 
                 ORDER BY completed_at DESC LIMIT 1",
                $post->ID
            ));

            if ($queue_item && $queue_item->completed_at) {
                $pdf_generated_date = date('M j, Y g:i A', strtotime($queue_item->completed_at));
            }

            $publications_with_tables[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'table_count' => $table_count,
                'pdf_url' => $pdf_url,
                'pdf_generated_date' => $pdf_generated_date,
                'view_url' => get_permalink($post->ID)
            );
        }
    }

    // Build HTML
    $html = '';
    if (empty($publications_with_tables)) {
        $html = '<p style="color: #666;">No publications with tables found on this page.</p>';
    } else {
        foreach ($publications_with_tables as $item) {
            $html .= '<div class="pdf-item">';
            $html .= '<div class="pdf-item-title">' . esc_html($item['title']) . '</div>';

            $meta_parts = array($item['table_count'] . ' tables');
            if ($item['pdf_generated_date']) {
                $meta_parts[] = 'PDF generated: ' . $item['pdf_generated_date'];
            } else {
                $meta_parts[] = 'No PDF generated yet';
            }

            $html .= '<div class="pdf-item-meta">' . implode(' | ', $meta_parts) . '</div>';

            $html .= '<div class="pdf-item-actions" style="margin-top: 6px;">';

            // View Publication button
            $html .= '<a href="' . esc_url($item['view_url']) . '" target="_blank" class="button button-small">View Publication</a> ';

            // View PDF button (if PDF exists)
            if ($item['pdf_url']) {
                $html .= '<a href="' . esc_url($item['pdf_url']) . '" target="_blank" class="button button-small">View PDF</a> ';
                $html .= '<button class="button button-small regenerate-pdf-btn" data-post-id="' . $item['id'] . '" data-post-title="' . esc_attr($item['title']) . '">Regenerate PDF</button>';
            } else {
                $html .= '<button class="button button-small button-primary regenerate-pdf-btn" data-post-id="' . $item['id'] . '" data-post-title="' . esc_attr($item['title']) . '">Generate PDF</button>';
            }

            $html .= '</div></div>';
        }
    }

    // Add pagination
    $total_pages = ceil($total_posts / $per_page);
    if ($total_pages > 1) {
        $html .= '<div class="pagination-controls" style="margin-top: 15px; text-align: center;">';

        if ($page > 1) {
            $html .= '<button class="button table-pagination-btn" data-page="' . ($page - 1) . '">Previous</button> ';
        }

        $html .= '<span style="margin: 0 10px;">Page ' . $page . ' of ' . $total_pages . '</span>';

        if ($page < $total_pages) {
            $html .= ' <button class="button table-pagination-btn" data-page="' . ($page + 1) . '">Next</button>';
        }

        $html .= '</div>';
    }

    wp_send_json_success(array(
        'html' => $html,
        'count' => count($publications_with_tables),
        'total_found' => count($publications_with_tables),
        'page' => $page,
        'total_pages' => $total_pages
    ));
}

/**
 * Find publications without PDFs
 */
function fr2025_ajax_find_missing_pdfs()
{
    check_ajax_referer('fr2025_pdf_nonce', '_ajax_nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Insufficient permissions');

    global $wpdb;

    // Get posts that DON'T have pdf_download_url
    $post_ids_with_pdf = $wpdb->get_col(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'pdf_download_url' AND meta_value != ''"
    );

    $query_args = array(
        'post_type' => 'publications',
        'posts_per_page' => 50, // Limit to first 50 for performance
        'post_status' => 'publish',
        'post__not_in' => !empty($post_ids_with_pdf) ? $post_ids_with_pdf : array(0),
        'meta_query' => array(
            array('key' => 'publication_number', 'compare' => 'EXISTS'),
            array('key' => 'publication_number', 'value' => '', 'compare' => '!='),
        ),
        'orderby' => 'title',
        'order' => 'ASC'
    );

    $posts = new WP_Query($query_args);

    $html = '';
    if (!$posts->have_posts()) {
        $html = '<p style="color: #666;">No missing PDFs found.</p>';
    } else {
        foreach ($posts->posts as $post) {
            $pub_number = get_field('publication_number', $post->ID);
            $html .= '<div class="pdf-item">';
            $html .= '<div class="pdf-item-title">' . esc_html($post->post_title) . '</div>';
            $html .= '<div class="pdf-item-meta">Pub #: ' . esc_html($pub_number) . ' | <a href="' . get_edit_post_link($post->ID) . '" target="_blank">Edit</a></div>';
            $html .= '<button class="button button-small button-primary regenerate-pdf-btn" data-post-id="' . $post->ID . '" data-post-title="' . esc_attr($post->post_title) . '">Generate PDF</button>';
            $html .= '</div>';
        }
        if ($posts->found_posts > 50) {
            $html .= '<p style="color: #666; font-style: italic;">Showing first 50 of ' . $posts->found_posts . ' total</p>';
        }
    }

    wp_send_json_success(array('html' => $html, 'count' => $posts->found_posts));
}

/**
 * Get current processes (simplified)
 */
function fr2025_ajax_get_current_processes_simple()
{
    check_ajax_referer('fr2025_pdf_nonce', '_ajax_nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Insufficient permissions');

    global $wpdb;
    $table_name = $wpdb->prefix . 'pdf_generation_queue';

    $queue_items = $wpdb->get_results(
        "SELECT *, TIMESTAMPDIFF(MINUTE, queued_at, NOW()) as minutes_in_queue 
         FROM {$table_name} 
         WHERE status IN ('processing', 'pending', 'failed') 
            OR (status = 'completed' AND completed_at > DATE_SUB(NOW(), INTERVAL 2 HOUR))
         ORDER BY 
            CASE status WHEN 'processing' THEN 1 WHEN 'pending' THEN 2 WHEN 'failed' THEN 3 ELSE 4 END,
            queued_at DESC
         LIMIT 15"
    );

    $html = '';
    if (empty($queue_items)) {
        $html = '<p style="color: #666;">No current processes</p>';
    } else {
        foreach ($queue_items as $item) {
            $post_title = get_the_title($item->post_id) ? get_the_title($item->post_id) : 'Unknown Post';
            $stuck_warning = ($item->status === 'processing' && $item->minutes_in_queue > 10) ? ' <span style="color: red;">(STUCK)</span>' : '';

            $html .= '<div class="process-item ' . $item->status . '">';
            $html .= '<strong>' . esc_html(substr($post_title, 0, 30)) . '</strong>' . $stuck_warning . '<br>';
            $html .= '<span style="color: #666;">' . strtoupper($item->status) . ' (' . $item->minutes_in_queue . ' min ago)</span>';

            if (in_array($item->status, array('processing', 'pending', 'failed'))) {
                $html .= '<br><button class="button button-small cancel-process-btn" data-queue-id="' . $item->id . '">Cancel</button>';
            }
            $html .= '</div>';
        }
    }

    wp_send_json_success(array(
        'html' => $html,
        'count' => count($queue_items),
        'cron_locked' => (bool)get_transient('pdf_generation_lock')
    ));
}

/**
 * Queue a single PDF for generation
 */
function fr2025_ajax_queue_single_pdf()
{
    check_ajax_referer('fr2025_pdf_nonce', '_ajax_nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Insufficient permissions');

    $post_id = intval($_POST['post_id']);

    if (insert_or_update_pdf_queue($post_id, 'pending')) {
        wp_send_json_success('PDF queued successfully');
    } else {
        wp_send_json_error('Failed to queue PDF');
    }
}

/**
 * AJAX handler to cancel a PDF process
 */
function fr2025_ajax_cancel_pdf_process()
{
    check_ajax_referer('fr2025_pdf_nonce', '_ajax_nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }

    $queue_id = intval($_POST['queue_id']);

    global $wpdb;
    $table_name = $wpdb->prefix . 'pdf_generation_queue';

    // Remove from queue entirely
    $result = $wpdb->delete($table_name, array('id' => $queue_id), array('%d'));

    if ($result !== false) {
        wp_send_json_success('Process cancelled and removed from queue');
    } else {
        wp_send_json_error('Failed to cancel process');
    }
}

/**
 * AJAX handler to clear cron lock
 */
function fr2025_ajax_clear_cron_lock()
{
    check_ajax_referer('fr2025_pdf_nonce', '_ajax_nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }

    delete_transient('pdf_generation_lock');
    wp_send_json_success('Cron lock cleared');
}
