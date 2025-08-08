<?php

// ===================
// PUBLICATION HISTORY UPDATE TOOL
// ===================

// Add admin menu item under Tools
add_action('admin_menu', function() {
    add_management_page(
        'Update Publication History',
        'Update Pub History',
        'manage_options',
        'update-publication-history',
        'render_publication_history_admin_page'
    );
});

// Render the admin page
function render_publication_history_admin_page() {
    ?>
    <div class="wrap">
        <h1>Update Publication History</h1>
        <p>This tool will update publication history data from the JSON file. It processes publications in batches to prevent timeouts.</p>
        
        <div id="history-update-container">
            <div id="pre-check-section">
                <h3>Pre-flight Check</h3>
                <div id="pre-check-results"></div>
                <button id="run-pre-check" class="button">Run Pre-flight Check</button>
            </div>
            
            <div id="update-section" style="display: none;">
                <h3>Update Process</h3>
                <div id="progress-container">
                    <div id="progress-bar-container" style="width: 100%; background-color: #f0f0f0; border-radius: 5px; margin: 10px 0;">
                        <div id="progress-bar" style="width: 0%; height: 30px; background-color: #4CAF50; border-radius: 5px; text-align: center; line-height: 30px; color: white; font-weight: bold;">0%</div>
                    </div>
                    <div id="progress-text">Ready to start...</div>
                </div>
                
                <div id="update-controls">
                    <button id="start-update" class="button button-primary">Start Update Process</button>
                    <button id="stop-update" class="button" style="display: none;">Stop Process</button>
                </div>
                
                <div id="update-log" style="margin-top: 20px;">
                    <h4>Process Log:</h4>
                    <div id="log-container" style="height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9; font-family: monospace; font-size: 12px;"></div>
                </div>
            </div>
            
            <div id="results-section" style="display: none;">
                <h3>Results</h3>
                <div id="final-results"></div>
                <button id="reset-tool" class="button">Reset Tool</button>
            </div>
        </div>
    </div>
    
    <style>
        #history-update-container {
            max-width: 800px;
        }
        
        .status-good {
            color: #4CAF50;
            font-weight: bold;
        }
        
        .status-warning {
            color: #FF9800;
            font-weight: bold;
        }
        
        .status-error {
            color: #F44336;
            font-weight: bold;
        }
        
        .log-entry {
            margin: 2px 0;
            padding: 2px 5px;
        }
        
        .log-info {
            color: #333;
        }
        
        .log-success {
            color: #4CAF50;
        }
        
        .log-warning {
            color: #FF9800;
        }
        
        .log-error {
            color: #F44336;
            font-weight: bold;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        
        .stat-item {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
    
    <script>
        jQuery(document).ready(function($) {
            let isProcessing = false;
            let shouldStop = false;
            let totalPublications = 0;
            let processedCount = 0;
            let errorCount = 0;
            let successCount = 0;
            
            // Pre-flight check
            $('#run-pre-check').click(function() {
                $(this).prop('disabled', true).text('Checking...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'pub_history_precheck',
                        nonce: '<?php echo wp_create_nonce('pub_history_update'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            displayPreCheckResults(response.data);
                            if (response.data.can_proceed) {
                                $('#update-section').show();
                            }
                        } else {
                            $('#pre-check-results').html('<div class="status-error">Error: ' + response.data + '</div>');
                        }
                    },
                    error: function() {
                        $('#pre-check-results').html('<div class="status-error">Failed to run pre-flight check</div>');
                    },
                    complete: function() {
                        $('#run-pre-check').prop('disabled', false).text('Run Pre-flight Check');
                    }
                });
            });
            
            // Start update process
            $('#start-update').click(function() {
                if (isProcessing) return;
                
                isProcessing = true;
                shouldStop = false;
                processedCount = 0;
                errorCount = 0;
                successCount = 0;
                
                $('#start-update').hide();
                $('#stop-update').show();
                $('#log-container').empty();
                
                logMessage('Starting publication history update process...', 'info');
                
                // First, clear all existing history
                clearAllHistory();
            });
            
            // Stop process
            $('#stop-update').click(function() {
                shouldStop = true;
                logMessage('Stop requested by user...', 'warning');
                $(this).prop('disabled', true).text('Stopping...');
            });
            
            // Reset tool
            $('#reset-tool').click(function() {
                location.reload();
            });
            
            function displayPreCheckResults(data) {
                let html = '<div class="stats-grid">';
                html += '<div class="stat-item"><div class="stat-number">' + data.total_publications + '</div><div class="stat-label">Total Publications</div></div>';
                html += '<div class="stat-item"><div class="stat-number">' + data.json_entries + '</div><div class="stat-label">JSON History Entries</div></div>';
                html += '<div class="stat-item"><div class="stat-number">' + data.grouped_publications + '</div><div class="stat-label">Publications with History</div></div>';
                html += '</div>';
                
                if (data.json_file_exists) {
                    html += '<div class="status-good">✓ JSON file found and readable</div>';
                } else {
                    html += '<div class="status-error">✗ JSON file not found or not readable</div>';
                }
                
                if (data.json_valid) {
                    html += '<div class="status-good">✓ JSON structure is valid</div>';
                } else {
                    html += '<div class="status-error">✗ JSON structure is invalid</div>';
                }
                
                if (data.can_proceed) {
                    html += '<div class="status-good">✓ Ready to proceed with update</div>';
                } else {
                    html += '<div class="status-error">✗ Cannot proceed due to errors above</div>';
                }
                
                $('#pre-check-results').html(html);
            }
            
            function clearAllHistory() {
                logMessage('Clearing all existing history data...', 'info');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'pub_history_clear_all',
                        nonce: '<?php echo wp_create_nonce('pub_history_update'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            logMessage('Successfully cleared history for ' + response.data.cleared + ' publications', 'success');
                            totalPublications = response.data.total;
                            updateProgress(0, 'Loading history data...');
                            
                            // Now start processing history updates
                            processHistoryBatch(0);
                        } else {
                            logMessage('Error clearing history: ' + response.data, 'error');
                            stopProcess();
                        }
                    },
                    error: function() {
                        logMessage('Failed to clear existing history', 'error');
                        stopProcess();
                    }
                });
            }
            
            function processHistoryBatch(offset) {
                if (shouldStop) {
                    logMessage('Process stopped by user', 'warning');
                    stopProcess();
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'pub_history_process_batch',
                        nonce: '<?php echo wp_create_nonce('pub_history_update'); ?>',
                        offset: offset
                    },
                    success: function(response) {
                        if (response.success) {
                            const data = response.data;
                            processedCount += data.processed;
                            successCount += data.success;
                            errorCount += data.errors;
                            
                            // Log batch results
                            if (data.processed > 0) {
                                logMessage('Batch complete: ' + data.processed + ' processed, ' + data.success + ' updated, ' + data.errors + ' errors', 'info');
                            }
                            
                            // Log any specific errors
                            if (data.error_messages && data.error_messages.length > 0) {
                                data.error_messages.forEach(function(msg) {
                                    logMessage(msg, 'warning');
                                });
                            }
                            
                            updateProgress(processedCount, 'Processed ' + processedCount + ' of ' + totalPublications + ' publications');
                            
                            if (data.has_more && !shouldStop) {
                                // Continue with next batch
                                setTimeout(function() {
                                    processHistoryBatch(offset + data.batch_size);
                                }, 500); // Small delay to prevent overwhelming the server
                            } else {
                                // Process complete
                                finishProcess();
                            }
                        } else {
                            logMessage('Batch error: ' + response.data, 'error');
                            errorCount++;
                            stopProcess();
                        }
                    },
                    error: function() {
                        logMessage('Failed to process batch at offset ' + offset, 'error');
                        errorCount++;
                        stopProcess();
                    }
                });
            }
            
            function updateProgress(current, text) {
                const percentage = totalPublications > 0 ? Math.round((current / totalPublications) * 100) : 0;
                $('#progress-bar').css('width', percentage + '%').text(percentage + '%');
                $('#progress-text').text(text);
            }
            
            function logMessage(message, type) {
                const timestamp = new Date().toLocaleTimeString();
                const logClass = 'log-' + type;
                const logEntry = $('<div class="log-entry ' + logClass + '">[' + timestamp + '] ' + message + '</div>');
                $('#log-container').append(logEntry);
                $('#log-container').scrollTop($('#log-container')[0].scrollHeight);
            }
            
            function finishProcess() {
                isProcessing = false;
                logMessage('Process completed successfully!', 'success');
                
                const finalResults = '<div class="stats-grid">' +
                    '<div class="stat-item"><div class="stat-number">' + processedCount + '</div><div class="stat-label">Total Processed</div></div>' +
                    '<div class="stat-item"><div class="stat-number">' + successCount + '</div><div class="stat-label">Successfully Updated</div></div>' +
                    '<div class="stat-item"><div class="stat-number">' + errorCount + '</div><div class="stat-label">Errors</div></div>' +
                    '</div>';
                
                $('#final-results').html(finalResults);
                $('#results-section').show();
                $('#stop-update').hide();
                $('#start-update').show();
                
                updateProgress(processedCount, 'Process completed! ' + successCount + ' publications updated.');
            }
            
            function stopProcess() {
                isProcessing = false;
                $('#stop-update').hide();
                $('#start-update').show();
                updateProgress(processedCount, 'Process stopped. ' + processedCount + ' publications processed.');
            }
        });
    </script>
    <?php
}

// AJAX handler for pre-flight check
add_action('wp_ajax_pub_history_precheck', function() {
    check_ajax_referer('pub_history_update', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $json_path = get_template_directory() . '/json/pubs-history.json';
    $response = array(
        'json_file_exists' => false,
        'json_valid' => false,
        'total_publications' => 0,
        'json_entries' => 0,
        'grouped_publications' => 0,
        'can_proceed' => false
    );
    
    // Check if JSON file exists
    if (file_exists($json_path)) {
        $response['json_file_exists'] = true;
        
        // Try to read and parse JSON
        $json_data = file_get_contents($json_path);
        if ($json_data !== false) {
            $json_data = preg_replace('/^\xEF\xBB\xBF/', '', $json_data); // Strip BOM
            $json_data = mb_convert_encoding($json_data, 'UTF-8', 'UTF-8');
            $entries = json_decode($json_data, true);
            
            if (is_array($entries)) {
                $response['json_valid'] = true;
                $response['json_entries'] = count($entries);
                
                // Group entries by publication ID to count unique publications
                $grouped = array();
                foreach ($entries as $entry) {
                    $pub_id = $entry['PUBLICATION_ID'] ?? null;
                    if ($pub_id) {
                        $grouped[$pub_id] = true;
                    }
                }
                $response['grouped_publications'] = count($grouped);
            }
        }
    }
    
    // Count total publications
    $total_publications = get_posts(array(
        'post_type' => 'publications',
        'post_status' => array('publish', 'draft', 'pending', 'future', 'private', 'inherit'),
        'posts_per_page' => -1,
        'fields' => 'ids'
    ));
    $response['total_publications'] = count($total_publications);
    
    // Determine if we can proceed
    $response['can_proceed'] = $response['json_file_exists'] && $response['json_valid'] && $response['json_entries'] > 0;
    
    wp_send_json_success($response);
});

// AJAX handler for clearing all history
add_action('wp_ajax_pub_history_clear_all', function() {
    check_ajax_referer('pub_history_update', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $all_posts = get_posts(array(
        'post_type' => 'publications',
        'post_status' => array('publish', 'draft', 'pending', 'future', 'private', 'inherit'),
        'fields' => 'ids',
        'posts_per_page' => -1,
    ));
    
    $cleared = 0;
    foreach ($all_posts as $post_id) {
        delete_field('history', $post_id);
        $cleared++;
    }
    
    wp_send_json_success(array(
        'cleared' => $cleared,
        'total' => count($all_posts)
    ));
});

// AJAX handler for processing batches
add_action('wp_ajax_pub_history_process_batch', function() {
    check_ajax_referer('pub_history_update', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $offset = intval($_POST['offset']);
    $batch_size = 25; // Process 25 publications at a time
    
    // Load and parse JSON (cache it in a static variable)
    static $grouped_history = null;
    if ($grouped_history === null) {

        // Static JSON file deprecated -- now uses API

        // $json_path = get_template_directory() . '/json/pubs-history.json';
        // if (!file_exists($json_path)) {
        //     wp_send_json_error('JSON file not found');
        // }
        
        // $json_data = file_get_contents($json_path);
        // $json_data = preg_replace('/^\xEF\xBB\xBF/', '', $json_data); // Strip BOM
        // $json_data = mb_convert_encoding($json_data, 'UTF-8', 'UTF-8');
        // $entries = json_decode($json_data, true);
        
        // if (!is_array($entries)) {
        //     wp_send_json_error('Invalid JSON structure');
        // }
        
        //New method: Hit API
        $api_url = 'https://secure.caes.uga.edu/rest/publications/getPubsHistory';
        $decoded_API_response = null; // Initialize to null

        try {
            // Fetch data from the API.
            $response = wp_remote_get($api_url);

            if (is_wp_error($response)) {
                throw new Exception('API Request Failed: ' . $response->get_error_message());
            }

            $raw_JSON = wp_remote_retrieve_body($response);
            $json_data = preg_replace('/^\xEF\xBB\xBF/', '', $raw_JSON); // Strip BOM
            $json_data = mb_convert_encoding($json_data, 'UTF-8', 'UTF-8');
            $decoded_API_response = json_decode($json_data, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('JSON decode error from API: ' . json_last_error_msg());
            }

            if (!is_array($decoded_API_response)) {
                throw new Exception('Invalid API response format: Expected an array.');
            }

            $entries = $decoded_API_response;

        } catch (Exception $e) {
            error_log('Publications History API Error: ' . $e->getMessage());
            wp_send_json_error('API Error for Publications History: ' . $e->getMessage());
        }
        // End API call 

        // Group entries by publication ID
        $grouped_history = array();
        foreach ($entries as $entry) {
            $pub_id = $entry['PUBLICATION_ID'] ?? null;
            $state = $entry['STATE_ID'] ?? null;
            $date = $entry['DATE_OF_CHANGE'] ?? null;
            
            if ($pub_id && $state && $date) {
                $grouped_history[$pub_id][] = array(
                    'status' => $state,
                    'date' => $date
                );
            }
        }
    }
    
    // Get publications for this batch
    $all_publications = get_posts(array(
        'post_type' => 'publications',
        'post_status' => array('publish', 'draft', 'pending', 'future', 'private', 'inherit'),
        'fields' => 'ids',
        'posts_per_page' => -1,
    ));
    
    $batch_publications = array_slice($all_publications, $offset, $batch_size);
    
    $processed = 0;
    $success = 0;
    $errors = 0;
    $error_messages = array();
    
    foreach ($batch_publications as $post_id) {
        $processed++;
        
        $publication_id = get_field('publication_id', $post_id);
        if (!$publication_id) {
            $errors++;
            $error_messages[] = "Post ID {$post_id}: No publication_id found";
            continue;
        }
        
        if (isset($grouped_history[$publication_id])) {
            $history_rows = $grouped_history[$publication_id];
            $result = update_field('history', $history_rows, $post_id);
            
            if ($result) {
                $success++;
            } else {
                $errors++;
                $error_messages[] = "Post ID {$post_id}: Failed to update history field";
            }
        } else {
            // No history data for this publication - this is not necessarily an error
            // Just log it if we want to track it
        }
    }
    
    $has_more = ($offset + $batch_size) < count($all_publications);
    
    wp_send_json_success(array(
        'processed' => $processed,
        'success' => $success,
        'errors' => $errors,
        'error_messages' => $error_messages,
        'has_more' => $has_more,
        'batch_size' => $batch_size,
        'total_publications' => count($all_publications)
    ));
});