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
        <h1>PDF Management</h1>

        <div style="background: #f1f1f1; padding: 15px; border-radius: 5px; margin: 20px 0; display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            
            <div style="display: flex; flex-grow: 1; min-width: 250px;">
                <input type="search" id="publication-search" placeholder="Search by title or pub #" style="width: 100%; margin-right: -1px;">
                <button id="search-button" class="button">Search</button>
            </div>

            <button id="queue-selected-pdfs" class="button button-primary" disabled>Queue Selected PDFs</button>
            <button id="clear-cron-lock" class="button button-secondary">Clear Stuck Processes</button>

            <label style="margin-left: auto;">
                <input type="checkbox" id="filter-no-manual" style="margin-right: 5px;" checked>
                Show only publications without manual PDFs
            </label>

            <span id="status-message" style="margin-left: 20px; color: #666; width: 100%; text-align: right;"></span>
        </div>

        <div id="publications-table-container">
            <p style="color: #666; font-style: italic;">Loading publications...</p>
        </div>

        <script>
jQuery(document).ready(function($) {
    var currentPage = 1;
    var filterNoManual = true;
    var searchTerm = ''; // NEW: Search term state
    var searchTimeout; // NEW: Timeout for debouncing search input

    function setStatus(message, type = 'info') {
        var color = type === 'error' ? 'red' : (type === 'success' ? 'green' : '#666');
        $('#status-message').html('<span style="color:' + color + ';">' + message + '</span>');
    }

    function loadPublicationsTable(page = 1) {
        setStatus('Loading publications...');
        $('#publications-table-container').html('<p style="color: #ff9800;">Loading page ' + page + '...</p>');
        
        // Clear any existing timeout
        clearTimeout(searchTimeout);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'fr2025_get_publications_table',
                page: page,
                filter_no_manual: filterNoManual ? 1 : 0,
                search: searchTerm, // NEW: Pass search term
                _ajax_nonce: '<?php echo wp_create_nonce('fr2025_pdf_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#publications-table-container').html(response.data.html);
                    currentPage = response.data.page;
                    var statusText = 'Page ' + page + ' loaded (' + response.data.total + ' total publications found)';
                    if (filterNoManual) statusText += ' (filtered)';
                    if (searchTerm) statusText += ' for search term "' + searchTerm + '"';
                    setStatus(statusText, 'success');
                    updateBulkButtonState();
                } else {
                    $('#publications-table-container').html('<p style="color: red;">Error: ' + response.data + '</p>');
                    setStatus('Error loading table', 'error');
                }
            },
            error: function() {
                $('#publications-table-container').html('<p style="color: red;">Network error loading table</p>');
                setStatus('Network error', 'error');
            }
        });
    }
    
    function updateBulkButtonState() {
        var checkedCount = $('.publication-checkbox:checked').length;
        if (checkedCount > 0) {
            $('#queue-selected-pdfs').prop('disabled', false).text('Queue ' + checkedCount + ' Selected');
        } else {
            $('#queue-selected-pdfs').prop('disabled', true).text('Queue Selected PDFs');
        }
    }

    // --- Event handlers ---
    
    // NEW: Search functionality with debouncing
    $('#publication-search').on('keyup', function() {
        clearTimeout(searchTimeout);
        var newSearchTerm = $(this).val();
        
        searchTimeout = setTimeout(function() {
            if (newSearchTerm !== searchTerm) {
                searchTerm = newSearchTerm;
                currentPage = 1; // Reset to first page
                loadPublicationsTable(1);
            }
        }, 500); // 500ms delay after user stops typing
    });

    $('#search-button').on('click', function() {
        searchTerm = $('#publication-search').val();
        currentPage = 1; // Reset to first page
        loadPublicationsTable(1);
    });
    
    $('#filter-no-manual').on('change', function() {
        filterNoManual = $(this).is(':checked');
        currentPage = 1;
        loadPublicationsTable(1);
    });
    
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
                setStatus('Cron lock cleared', 'success');
                loadPublicationsTable(currentPage);
            }
        });
    });

    $('#queue-selected-pdfs').on('click', function() {
        var postIds = $('.publication-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
        
        if (postIds.length === 0) {
            alert('Please select at least one publication to queue.');
            return;
        }
        
        if (!confirm('Are you sure you want to queue PDF generation for ' + postIds.length + ' selected publications?')) {
            return;
        }
        
        setStatus('Queuing ' + postIds.length + ' publications...');
        $(this).prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'fr2025_queue_bulk_pdfs',
                post_ids: postIds,
                _ajax_nonce: '<?php echo wp_create_nonce('fr2025_pdf_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    setStatus(response.data.message, 'success');
                    loadPublicationsTable(currentPage);
                } else {
                    setStatus('Error queuing PDFs: ' + response.data, 'error');
                }
                updateBulkButtonState();
            },
            error: function() {
                setStatus('Network error during bulk queue.', 'error');
                updateBulkButtonState();
            }
        });
    });

    // --- Delegated event handlers ---
    $(document).on('click', '.pagination-btn', function() {
        var page = $(this).data('page');
        loadPublicationsTable(page);
    });
    
    $(document).on('change', '.publication-checkbox', function() {
        updateBulkButtonState();
    });

    $(document).on('change', '#select-all-publications', function() {
        $('.publication-checkbox').prop('checked', $(this).is(':checked'));
        updateBulkButtonState();
    });

    $(document).on('click', '.regenerate-pdf-btn', function() {
        var postId = $(this).data('post-id');
        var postTitle = $(this).data('post-title');
        var $button = $(this);
        var $row = $button.closest('tr');
        
        if (!confirm('Generate PDF for "' + postTitle + '"?')) return;
        
        $row.find('td:nth-child(5)').html('<span class="status-indicator status-processing"></span>Processing');
        $row.find('td:nth-child(6)').html('Queued just now');
        $button.prop('disabled', true).text('Queued...');
        $row.detach().prependTo($row.closest('tbody'));
        $row.css('background-color', '#fff3cd');
        setTimeout(function() { $row.css('background-color', ''); }, 3000);
        
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
                    setStatus('Queued PDF generation for ' + postTitle, 'success');
                    $button.text('Processing...');
                } else {
                    setStatus('Failed to queue PDF: ' + response.data, 'error');
                    $button.prop('disabled', false).text('Generate PDF');
                    $row.find('td:nth-child(5)').html('<span class="status-indicator status-missing"></span>Missing');
                    $row.find('td:nth-child(6)').html('Never');
                }
            },
            error: function() {
                setStatus('Network error queuing PDF', 'error');
                $button.prop('disabled', false).text('Generate PDF');
                $row.find('td:nth-child(5)').html('<span class="status-indicator status-missing"></span>Missing');
                $row.find('td:nth-child(6)').html('Never');
            }
        });
    });

    // Initialize
    loadPublicationsTable(1);
});
</script>

        <style>
            .publications-table { width: 100%; border-collapse: collapse; background: white; }
            .publications-table th, .publications-table td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #eee; vertical-align: middle; }
            .publications-table th { background-color: #f9f9f9; font-weight: bold; border-bottom: 2px solid #ddd; }
            .publications-table tr:hover { background-color: #f5f5f5; }
            .status-indicator { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 5px; }
            .status-generated { background-color: #4CAF50; }
            .status-manual { background-color: #2196F3; }
            .status-missing { background-color: #f44336; }
            .status-processing { background-color: #ff9800; }
            .pagination-controls { margin: 20px 0; text-align: center; }
            .pagination-controls button { margin: 0 2px; }
            .recently-queued { background-color: #fff3cd !important; }
            .recently-queued:hover { background-color: #ffeaa7 !important; }
        </style>
    </div>
<?php
}

add_action('wp_ajax_fr2025_get_publications_table', 'fr2025_ajax_get_publications_table');
add_action('wp_ajax_fr2025_queue_single_pdf', 'fr2025_ajax_queue_single_pdf');
add_action('wp_ajax_fr2025_clear_cron_lock', 'fr2025_ajax_clear_cron_lock');
add_action('wp_ajax_fr2025_queue_bulk_pdfs', 'fr2025_ajax_queue_bulk_pdfs');

/**
 * Get publications table with all PDF status information
 */
function fr2025_ajax_get_publications_table() {
    check_ajax_referer('fr2025_pdf_nonce', '_ajax_nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Insufficient permissions');

    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $filter_no_manual = isset($_POST['filter_no_manual']) && $_POST['filter_no_manual'] == '1';
    $search_term = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : ''; // NEW: Get search term
    $per_page = 25;
    $offset = ($page - 1) * $per_page;
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'pdf_generation_queue';
    
    // Base WHERE clause
    $where_clause = "p.post_type = 'publications' AND p.post_status = 'publish'";
    
    // Filter for publications without manual PDFs
    if ($filter_no_manual) {
        $manual_pdf_post_ids = $wpdb->get_col(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'pdf' AND meta_value IS NOT NULL AND meta_value != '' AND meta_value != 'a:0:{}'"
        );
        
        if (!empty($manual_pdf_post_ids)) {
            $manual_pdf_ids_string = implode(',', array_map('intval', $manual_pdf_post_ids));
            $where_clause .= " AND p.ID NOT IN ($manual_pdf_ids_string)";
        }
    }

    // NEW: Add search conditions
    $search_joins = '';
    $search_params = [];
    if (!empty($search_term)) {
        // We need to join postmeta to search the publication number
        $search_joins = "LEFT JOIN {$wpdb->postmeta} pm_pub_num ON p.ID = pm_pub_num.post_id AND pm_pub_num.meta_key = 'publication_number'";
        $where_clause .= " AND (p.post_title LIKE %s OR pm_pub_num.meta_value LIKE %s)";
        $like_term = '%' . $wpdb->esc_like($search_term) . '%';
        $search_params[] = $like_term;
        $search_params[] = $like_term;
    }
    
    // Construct the final query for getting the posts
    $query = "SELECT DISTINCT p.ID, p.post_title, q.status as queue_status, q.queued_at
              FROM {$wpdb->posts} p
              LEFT JOIN {$table_name} q ON p.ID = q.post_id AND q.id = (SELECT id FROM {$table_name} q2 WHERE q2.post_id = p.ID ORDER BY q2.queued_at DESC LIMIT 1)
              {$search_joins}
              WHERE {$where_clause}
              ORDER BY CASE WHEN q.status = 'processing' THEN 1 WHEN q.status = 'pending' THEN 2 ELSE 3 END, q.queued_at DESC, p.post_title
              LIMIT %d OFFSET %d";
              
    $publications = $wpdb->get_results($wpdb->prepare($query, array_merge($search_params, [$per_page, $offset])));
    
    // Construct the query for the total count
    $total_query = "SELECT COUNT(DISTINCT p.ID) 
                    FROM {$wpdb->posts} p
                    {$search_joins}
                    WHERE {$where_clause}";
                    
    $total_pubs = $wpdb->get_var($wpdb->prepare($total_query, $search_params));

    $html = '<table class="publications-table">';
    $html .= '<thead><tr>';
    $html .= '<th><input type="checkbox" id="select-all-publications"></th>';
    $html .= '<th>Publication</th>';
    $html .= '<th>Publication #</th>';
    $html .= '<th>Manual PDF</th>';
    $html .= '<th>Generated PDF Status</th>';
    $html .= '<th>Last Generated</th>';
    $html .= '<th>Actions</th>';
    $html .= '</tr></thead><tbody>';
    
    if (empty($publications)) {
        $html .= '<tr><td colspan="7" style="text-align:center; padding: 20px;">No publications found matching your criteria.</td></tr>';
    } else {
        foreach ($publications as $pub) {
            $pub_number = get_field('publication_number', $pub->ID);
            $manual_pdf = get_field('pdf', $pub->ID);
            $generated_pdf = get_field('pdf_download_url', $pub->ID);
            
            $queue_item = $wpdb->get_row($wpdb->prepare("SELECT status, completed_at, queued_at FROM {$table_name} WHERE post_id = %d ORDER BY queued_at DESC LIMIT 1", $pub->ID));
            
            $row_class = ($queue_item && in_array($queue_item->status, ['processing', 'pending'])) ? ' class="recently-queued"' : '';
            
            $html .= '<tr' . $row_class . '>';
            $html .= '<td><input type="checkbox" class="publication-checkbox" value="' . $pub->ID . '"></td>';
            $html .= '<td><strong>' . esc_html($pub->post_title) . '</strong></td>';
            $html .= '<td>' . esc_html($pub_number ?: 'N/A') . '</td>';
            
            $manual_status = '<span class="status-indicator status-missing"></span>No';
            if ((is_array($manual_pdf) && !empty($manual_pdf['url'])) || (is_string($manual_pdf) && filter_var($manual_pdf, FILTER_VALIDATE_URL))) {
                $manual_status = '<span class="status-indicator status-manual"></span>Yes';
            }
            $html .= '<td>' . $manual_status . '</td>';
            
            $gen_status = '<span class="status-indicator status-missing"></span>Missing';
            if ($queue_item && $queue_item->status === 'processing') {
                $gen_status = '<span class="status-indicator status-processing"></span>Processing';
            } elseif ($queue_item && $queue_item->status === 'pending') {
                $gen_status = '<span class="status-indicator status-processing"></span>Pending';
            } elseif ($generated_pdf) {
                $gen_status = '<span class="status-indicator status-generated"></span>Generated';
            }
            $html .= '<td>' . $gen_status . '</td>';
            
            $last_generated = 'Never';
            if ($queue_item && $queue_item->completed_at) {
                $last_generated = date('M j, Y g:i A', strtotime($queue_item->completed_at));
            } elseif ($queue_item && in_array($queue_item->status, ['processing', 'pending'])) {
                $last_generated = 'Queued ' . human_time_diff(strtotime($queue_item->queued_at)) . ' ago';
            }
            $html .= '<td>' . $last_generated . '</td>';
            
            $actions = '';
            if ($manual_pdf && is_array($manual_pdf) && !empty($manual_pdf['url'])) {
                $actions .= '<a href="' . esc_url($manual_pdf['url']) . '" target="_blank" class="button button-small">Manual PDF</a> ';
            }
            if ($generated_pdf) {
                $actions .= '<a href="' . esc_url($generated_pdf) . '" target="_blank" class="button button-small">Generated PDF</a> ';
            }
            
            if ($queue_item && in_array($queue_item->status, ['processing', 'pending'])) {
                $actions .= '<span style="color: #ff9800;">Processing...</span>';
            } else {
                $button_text = $generated_pdf ? 'Regenerate' : 'Generate';
                $actions .= '<button class="button button-small button-primary regenerate-pdf-btn" data-post-id="' . $pub->ID . '" data-post-title="' . esc_attr($pub->post_title) . '">' . $button_text . '</button>';
            }
            
            $actions .= '<a href="' . esc_url(get_edit_post_link($pub->ID)) . '" style="margin-left: 5px;" title="Edit Publication">Edit</a>';
            $actions .= '<a href="' . esc_url(get_permalink($pub->ID)) . '" target="_blank" style="margin-left: 5px;" title="View Publication on site">View</a> ';
            
            $html .= '<td>' . rtrim($actions) . '</td>';
            $html .= '</tr>';
        }
    }
    
    $html .= '</tbody></table>';
    
    $total_pages = ceil($total_pubs / $per_page);
    if ($total_pages > 1) {
        $html .= '<div class="pagination-controls">';
        if ($page > 1) {
            $html .= '<button class="button pagination-btn" data-page="1">First</button> ';
            $html .= '<button class="button pagination-btn" data-page="' . ($page - 1) . '">Previous</button> ';
        }
        $start = max(1, $page - 2); $end = min($total_pages, $page + 2);
        for ($i = $start; $i <= $end; $i++) {
            $class = $i === $page ? 'button-primary' : 'button-secondary';
            $html .= '<button class="button ' . $class . ' pagination-btn" data-page="' . $i . '">' . $i . '</button> ';
        }
        if ($page < $total_pages) {
            $html .= '<button class="button pagination-btn" data-page="' . ($page + 1) . '">Next</button> ';
            $html .= '<button class="button pagination-btn" data-page="' . $total_pages . '">Last</button>';
        }
        $html .= '</div>';
    }
    
    wp_send_json_success(['html' => $html, 'page' => $page, 'count' => count($publications), 'total' => $total_pubs]);
}

/**
 * Queue a single PDF for generation
 */
function fr2025_ajax_queue_single_pdf()
{
    check_ajax_referer('fr2025_pdf_nonce', '_ajax_nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Insufficient permissions');

    $post_id = intval($_POST['post_id']);

    if ($post_id > 0 && insert_or_update_pdf_queue($post_id, 'pending')) {
        wp_send_json_success('PDF queued successfully');
    } else {
        wp_send_json_error('Failed to queue PDF');
    }
}

/**
 * NEW: Queue multiple PDFs for generation
 */
function fr2025_ajax_queue_bulk_pdfs()
{
    check_ajax_referer('fr2025_pdf_nonce', '_ajax_nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }

    if (!isset($_POST['post_ids']) || !is_array($_POST['post_ids'])) {
        wp_send_json_error('No post IDs provided.');
    }

    $post_ids = array_map('intval', $_POST['post_ids']);
    $queued_count = 0;
    $failed_count = 0;

    foreach ($post_ids as $post_id) {
        if ($post_id > 0) {
            if (insert_or_update_pdf_queue($post_id, 'pending')) {
                $queued_count++;
            } else {
                $failed_count++;
            }
        }
    }

    if ($queued_count > 0) {
        $message = sprintf('Successfully queued %d publications.', $queued_count);
        if ($failed_count > 0) {
            $message .= sprintf(' %d failed to queue.', $failed_count);
        }
        wp_send_json_success(array('message' => $message));
    } else {
        wp_send_json_error(sprintf('Failed to queue %d publications.', $failed_count));
    }
}


/**
 * AJAX handler to clear cron lock and remove stuck processes
 */
function fr2025_ajax_clear_cron_lock() {
    check_ajax_referer('fr2025_pdf_nonce', '_ajax_nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'pdf_generation_queue';
    
    delete_transient('pdf_generation_lock');
    
    $affected = $wpdb->query("DELETE FROM {$table_name} WHERE status = 'processing' AND TIMESTAMPDIFF(MINUTE, queued_at, NOW()) > 10");
    $wpdb->query("DELETE FROM {$table_name} WHERE status = 'pending' AND TIMESTAMPDIFF(MINUTE, queued_at, NOW()) > 60");
    
    wp_send_json_success("Cron lock cleared and removed $affected stuck processes");
}