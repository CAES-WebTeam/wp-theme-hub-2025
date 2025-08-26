<?php

// Add admin menu page
add_action('admin_menu', 'state_issue_updater_menu_page');

/**
 * Registers the admin menu page for the State Issue Update Tool.
 */
function state_issue_updater_menu_page() {
    add_submenu_page(
        'caes-tools',                     // Parent slug - points to CAES Tools
        'State Issue Update Tool',        // Page title
        'Update State Issues',            // Menu title
        'manage_options',
        'state-issue-updater',
        'state_issue_updater_render_page'
    );
}

/**
 * Renders the content of the admin page, including the button and log area.
 */
function state_issue_updater_render_page() {
    // Generate a nonce for security. This is crucial for AJAX requests.
    $nonce = wp_create_nonce('state_issue_updater_nonce');
    // Get the AJAX URL for WordPress.
    $ajax_url = admin_url('admin-ajax.php');
    ?>
    <div class="wrap">
        <h1>State Issue Field Update Tool</h1>
        <p>Update the State Issue field for existing publications based on CAES API data</p>

        <hr>

        <h2>Execute State Issue Update</h2>
        <p><strong>Note:</strong> This will only update the State Issue field for existing publications. No new posts will be created.</p>
        <p>Publications will be processed in batches of 20. The process will continue automatically until all publications are processed.</p>
        
        <button class="button button-primary" id="execute-update-btn">Update State Issue Fields</button>
        <div id="execute-update-log" class="log-area"></div>
        <div id="update-progress" class="update-progress" style="display: none;">
            <h4>Update Progress</h4>
            <div class="progress-stats">
                <span id="total-updated">Updated: 0</span> |
                <span id="total-unchanged">Unchanged: 0</span> |
                <span id="total-errors">Errors: 0</span> |
                <span id="total-processed">Processed: 0 of 0</span>
            </div>
        </div>

    </div>
    <style>
        /* Styling for log readability */
        .log-area {
            margin-top: 15px;
            padding: 10px;
            background-color: #f0f0f0;
            border: 1px solid #ccc;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            font-family: monospace;
            font-size: 0.9em;
        }
        .log-area.success { background-color: #e6ffe6; border-color: #00cc00; }
        .log-area.error { background-color: #ffe6e6; border-color: #cc0000; }
        .log-area.info { background-color: #e6f7ff; border-color: #0099ff; }
        .log-area div {
            padding: 2px 0;
        }
        .log-area .log-detail {
            color: #555;
            font-size: 0.85em;
        }
        .log-area .log-error {
            color: #cc0000;
            font-weight: bold;
        }
        .log-area .log-success {
            color: #008000;
            font-weight: bold;
        }
        .log-area .log-info {
            color: #000080;
        }
        .log-area .log-update {
            color: #4169E1; /* Royal Blue for updates */
            font-weight: bold;
        }
        .log-area .log-unchanged {
            color: #696969; /* Dim Gray for unchanged items */
        }
        .update-progress {
            margin: 15px 0;
            padding: 15px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .progress-stats {
            font-family: monospace;
            font-size: 1em;
            font-weight: bold;
        }
        .progress-stats span {
            margin-right: 15px;
        }
    </style>
    <script type="text/javascript">
        jQuery(document).ready(function($) {

            // Helper function to append log messages to the display area
            function appendLog(logElement, message, type = 'info') {
                const timestamp = new Date().toLocaleTimeString();
                logElement.append(`<div class="log-${type}">[${timestamp}] ${message}</div>`);
                logElement.scrollTop(logElement[0].scrollHeight); // Scroll to bottom
            }

            // Helper function to set the class of the log area (for visual feedback)
            function setLogAreaClass(logElement, type) {
                logElement.removeClass('info success error').addClass(type);
            }

            // Global variables to track running totals
            let runningTotals = {
                updated: 0,
                unchanged: 0,
                errors: 0,
                processed: 0,
                total: 0
            };

            // Event listener for the "Update State Issue Fields" button
            $('#execute-update-btn').on('click', function() {
                const $button = $(this);
                const $logArea = $('#execute-update-log');
                
                 // Reset running totals
                runningTotals = { updated: 0, unchanged: 0, errors: 0, processed: 0, total: 0 };
                
                // Show progress area
                $('#update-progress').show();
                updateProgressDisplay();

                // Start with batch 1
                executeUpdateBatch(1, $button, $logArea);
            });

            // Function to update the progress display
            function updateProgressDisplay() {
                $('#total-updated').text(`Updated: ${runningTotals.updated}`);
                $('#total-unchanged').text(`Unchanged: ${runningTotals.unchanged}`);
                $('#total-errors').text(`Errors: ${runningTotals.errors}`);
                $('#total-processed').text(`Processed: ${runningTotals.processed} of ${runningTotals.total}`);
            }

            // Function to handle batch processing
            function executeUpdateBatch(batchNumber, $button, $logArea) {
                if (batchNumber === 1) {
                    $logArea.empty(); // Clear logs only on first batch
                    setLogAreaClass($logArea, 'info');
                }
                
                $button.prop('disabled', true).text(`Processing batch ${batchNumber}...`);
                appendLog($logArea, `Starting batch ${batchNumber}...`);

                $.ajax({
                    url: '<?php echo esc_js($ajax_url); ?>',
                    type: 'POST',
                    data: {
                        action: 'execute_state_issue_update',
                        nonce: '<?php echo esc_js($nonce); ?>',
                        batch: batchNumber
                    },
                    success: function(response) {
                        if (response.success) {

                            // Update running totals
                            runningTotals.updated += response.data.stats.posts_updated;
                            runningTotals.unchanged += response.data.stats.posts_unchanged;
                            runningTotals.errors += response.data.stats.posts_with_errors;
                            runningTotals.processed = response.data.processed_so_far;
                            runningTotals.total = response.data.total_publications;
                            
                            // Update the progress display
                            updateProgressDisplay();

                            appendLog($logArea, response.data.message, 'success');
                            
                            // Log all the batch details
                            if (response.data.log && response.data.log.length > 0) {
                                response.data.log.forEach(msg => {
                                    let logType = 'detail';
                                    if (msg.startsWith('UPDATE:')) logType = 'update';
                                    else if (msg.startsWith('UNCHANGED:')) logType = 'unchanged';
                                    else if (msg.startsWith('ERROR:')) logType = 'error';
                                    else if (msg.startsWith('SUCCESS:')) logType = 'success';
                                    else if (msg.startsWith('Warning:')) logType = 'error';
                                    appendLog($logArea, msg, logType);
                                });
                            }
                            
                            // Check if there are more batches to process
                            if (response.data.has_more_batches) {
                                appendLog($logArea, `Batch ${batchNumber} complete. Starting next batch in 1 second...`, 'info');
                                // Automatically continue to next batch after a short delay
                                setTimeout(() => {
                                    executeUpdateBatch(batchNumber + 1, $button, $logArea);
                                }, 1000); // 1 second delay between batches
                            } else {
                                // All batches complete
                                appendLog($logArea, '', 'info'); // Empty line
                                appendLog($logArea, '=== STATE ISSUE UPDATE COMPLETE ===', 'success');
                                appendLog($logArea, `Final totals: Updated ${runningTotals.updated}, Unchanged ${runningTotals.unchanged}, Errors ${runningTotals.errors}`, 'success');
                                setLogAreaClass($logArea, 'success');
                                $button.prop('disabled', false).text('Update State Issue Fields');
                            }
                            
                        } else {
                            // Handle error
                            appendLog($logArea, `Batch ${batchNumber} failed: ${response.data}`, 'error');
                            setLogAreaClass($logArea, 'error');
                            $button.prop('disabled', false).text('Update State Issue Fields');
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        appendLog($logArea, `Batch ${batchNumber} AJAX failed: ${textStatus}`, 'error');
                        setLogAreaClass($logArea, 'error');
                        $button.prop('disabled', false).text('Update State Issue Fields');
                    }
                });
            }

        });
    </script>
    <?php
}

// --- AJAX Handler ---

// Register the AJAX handler for state issue updates
add_action('wp_ajax_execute_state_issue_update', 'state_issue_updater_execute_update');

/**
 * Handles the AJAX request to update state issue fields.
 */
function state_issue_updater_execute_update() {
    // Verify the nonce for security.
    if (!check_ajax_referer('state_issue_updater_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed: Invalid nonce.', 403);
    }

    // Check if the current user has the 'manage_options' capability.
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied: You do not have sufficient permissions.', 403);
    }

    $log = []; // Array to store log messages
    $api_url = 'https://secure.caes.uga.edu/rest/publications/getPubs?apiKey=541398745&omitPublicationText=true&bypassReturnLimit=true&includeStateIssueLabels=true';
    
    $stats = [
        'posts_updated' => 0,
        'posts_unchanged' => 0,
        'posts_with_errors' => 0
    ];

    // Batch processing parameters
    $batch_size = 20;
    $current_batch_number = isset($_POST['batch']) ? intval($_POST['batch']) : 1;
    
    // Transient key for storing API data
    $api_data_transient_key = 'state_issue_api_data';
    $api_timestamp_transient_key = 'state_issue_api_timestamp';

    try {
        // --- Fetch or Retrieve Cached API Data ---
        $decoded_API_response = get_transient($api_data_transient_key);
        $api_fetch_timestamp = get_transient($api_timestamp_transient_key);
        
        if ($decoded_API_response === false || $current_batch_number === 1) {
            // Either no cached data exists, or this is batch 1 (fresh start)
            $log[] = "Fetching publication data from API...";
            
            $response = wp_remote_get($api_url, array(
                'timeout' => 60 // Increase timeout for large responses
            ));

            if (is_wp_error($response)) {
                throw new Exception('API Request Failed: ' . $response->get_error_message());
            }

            $raw_JSON = wp_remote_retrieve_body($response);
            $decoded_API_response = json_decode($raw_JSON, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('JSON decode error from API: ' . json_last_error_msg());
            }

            if (!is_array($decoded_API_response)) {
                throw new Exception('Invalid API response format: Expected an array.');
            }

            // Store the API data in transient (expires in 1 hour)
            set_transient($api_data_transient_key, $decoded_API_response, HOUR_IN_SECONDS);
            set_transient($api_timestamp_transient_key, current_time('mysql'), HOUR_IN_SECONDS);
            
            $log[] = "Successfully fetched and cached " . count($decoded_API_response) . " publications from API.";
        } else {
            // Use cached data
            $log[] = "Using cached API data from " . $api_fetch_timestamp . " (" . count($decoded_API_response) . " publications).";
        }

        // Create API lookup array by publication ID
        $api_lookup = [];
        foreach ($decoded_API_response as $api_pub) {
            if (isset($api_pub['ID'])) {
                $api_lookup[(string)$api_pub['ID']] = $api_pub;
            }
        }

        // --- Get existing WordPress publications for this batch ---
        $start_index = ($current_batch_number - 1) * $batch_size;
        
        $args = array(
            'post_type'      => 'publications',
            'posts_per_page' => $batch_size,
            'offset'         => $start_index,
            'post_status'    => array('publish', 'draft', 'private')
        );
        $wordpress_posts = get_posts($args);
        
        // Get total count for progress tracking
        if ($current_batch_number === 1) {
            $total_count_args = array(
                'post_type'      => 'publications',
                'posts_per_page' => -1,
                'post_status'    => array('publish', 'draft', 'private'),
                'fields'         => 'ids'
            );
            $all_post_ids = get_posts($total_count_args);
            $total_publications = count($all_post_ids);
        } else {
            // For subsequent batches, we need to calculate total
            // This is a simplified approach - in production you might want to store this in a transient
            $total_count_args = array(
                'post_type'      => 'publications',
                'posts_per_page' => -1,
                'post_status'    => array('publish', 'draft', 'private'),
                'fields'         => 'ids'
            );
            $all_post_ids = get_posts($total_count_args);
            $total_publications = count($all_post_ids);
        }

        $actual_batch_size = count($wordpress_posts);
        $log[] = "Processing batch {$current_batch_number}: WordPress publications " . ($start_index + 1) . " to " . ($start_index + $actual_batch_size) . " of {$total_publications}";

        // --- Process each WordPress publication in the batch ---
        foreach ($wordpress_posts as $wp_post) {
            $publication_id = get_field('publication_id', $wp_post->ID);
            
            if (!$publication_id) {
                $log[] = "UNCHANGED: WordPress post ID {$wp_post->ID} ('{$wp_post->post_title}') - no publication_id field found";
                $stats['posts_unchanged']++;
                continue;
            }

            $publication_id_str = (string)$publication_id;

            // Check if this publication exists in API data
            if (!isset($api_lookup[$publication_id_str])) {
                $log[] = "UNCHANGED: WordPress post ID {$wp_post->ID} (Publication ID: {$publication_id}) - not found in API data";
                $stats['posts_unchanged']++;
                continue;
            }

            $api_pub = $api_lookup[$publication_id_str];

            try {
                // Get current state issue value from WordPress
                $current_state_issue = get_field('state_issue', $wp_post->ID);
                
                // Get API state issue label value (STATE_ISSUE_LABEL field)
                $api_state_issue_label = isset($api_pub['STATE_ISSUE_LABEL']) ? $api_pub['STATE_ISSUE_LABEL'] : null;
                
                // Convert empty strings to null for consistent comparison
                if ($current_state_issue === '') {
                    $current_state_issue = null;
                }
                if ($api_state_issue_label === '') {
                    $api_state_issue_label = null;
                }

                // Compare current value with API label value
                if ($current_state_issue == $api_state_issue_label) {
                    $log[] = "UNCHANGED: WordPress post ID {$wp_post->ID} (Publication ID: {$publication_id}) - State Issue already correct (" . 
                             ($current_state_issue ? $current_state_issue : 'null') . ")";
                    $stats['posts_unchanged']++;
                } else {
                    // Update the state issue field with the label value
                    $update_result = update_field('state_issue', $api_state_issue_label, $wp_post->ID);
                    
                    if ($update_result) {
                        $old_value = $current_state_issue ? $current_state_issue : 'null';
                        $new_value = $api_state_issue_label ? $api_state_issue_label : 'null';
                        $log[] = "UPDATE: WordPress post ID {$wp_post->ID} (Publication ID: {$publication_id}) - State Issue updated from '{$old_value}' to '{$new_value}'";
                        $stats['posts_updated']++;
                    } else {
                        $log[] = "ERROR: WordPress post ID {$wp_post->ID} (Publication ID: {$publication_id}) - Failed to update State Issue field";
                        $stats['posts_with_errors']++;
                    }
                }
                
            } catch (Exception $e) {
                $log[] = "ERROR: WordPress post ID {$wp_post->ID} (Publication ID: {$publication_id}) - " . $e->getMessage();
                $stats['posts_with_errors']++;
            }
        }

        // Calculate if there are more batches
        $processed_so_far = $start_index + $actual_batch_size;
        $has_more_batches = $processed_so_far < $total_publications;
        
        // Clean up transients if this is the last batch
        if (!$has_more_batches) {
            delete_transient($api_data_transient_key);
            delete_transient($api_timestamp_transient_key);
            $log[] = "Update complete - cleared cached API data.";
        }
        
        // --- Final Summary for this batch ---
        $log[] = ""; // Empty line for readability
        $log[] = "=== BATCH {$current_batch_number} COMPLETE ===";
        $log[] = "Fields UPDATED in this batch: " . $stats['posts_updated'];
        $log[] = "Fields UNCHANGED in this batch: " . $stats['posts_unchanged'];
        $log[] = "ERRORS in this batch: " . $stats['posts_with_errors'];
        
        if ($has_more_batches) {
            $log[] = "Remaining publications to process: " . ($total_publications - $processed_so_far);
        } else {
            $log[] = "All publications have been processed!";
        }

        $message = "Batch {$current_batch_number} complete. Updated {$stats['posts_updated']}, unchanged {$stats['posts_unchanged']}, errors {$stats['posts_with_errors']}.";

        wp_send_json_success([
            'message' => $message,
            'log' => $log,
            'stats' => $stats,
            'batch_number' => $current_batch_number,
            'has_more_batches' => $has_more_batches,
            'total_publications' => $total_publications,
            'processed_so_far' => $processed_so_far
        ]);

    } catch (Exception $e) {
        error_log('State Issue Update Error: ' . $e->getMessage());
        wp_send_json_error('Error during update batch ' . $current_batch_number . ': ' . $e->getMessage());
    }
}

// Optional helper function to manually clear the cached data if needed
function state_issue_updater_clear_cache() {
    delete_transient('state_issue_api_data');
    delete_transient('state_issue_api_timestamp');
    return 'API cache cleared successfully.';
}
