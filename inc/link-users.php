<?php
/***  Admin Page for Writer Linking with Batch Processing  ***/

// Add admin menu
add_action('admin_menu', function() {
    add_management_page(
        'Link Writers to Stories',
        'Link Writers',
        'manage_options',
        'link-writers-admin',
        'render_writer_linking_admin_page'
    );
});

// Render the admin page
function render_writer_linking_admin_page() {
    ?>
    <div class="wrap">
        <h1>Link Writers to Stories</h1>
        <p>This tool will sync news stories with story writers based on the JSON data file using batch processing to handle large datasets.</p>
        
        <div id="batch-settings" style="background: #fff; border: 1px solid #ddd; padding: 15px; margin: 20px 0;">
            <h3>Batch Settings</h3>
            <label for="batch-size">Records per batch:</label>
            <select id="batch-size" style="margin-left: 10px;">
                <option value="10">10 (Safe for slow servers)</option>
                <option value="25" selected>25 (Recommended)</option>
                <option value="50">50 (Fast servers)</option>
                <option value="100">100 (Very fast servers)</option>
            </select>
            <p style="color: #666; font-style: italic; margin-top: 5px;">
                Smaller batches are safer but slower. Increase if your server is fast and has good memory limits.
            </p>
        </div>
        
        <div id="progress-container" style="display: none;">
            <div style="background: #fff; border: 1px solid #ddd; padding: 20px; margin: 20px 0;">
                <h3>Progress</h3>
                <div id="progress-bar-container" style="background: #f0f0f0; height: 20px; border-radius: 10px; margin: 10px 0;">
                    <div id="progress-bar" style="background: #0073aa; height: 100%; border-radius: 10px; width: 0%; transition: width 0.3s;"></div>
                </div>
                <div id="progress-text">Preparing...</div>
                <div id="batch-info" style="margin: 10px 0; font-weight: bold;"></div>
                <div id="progress-details" style="margin-top: 15px; font-family: monospace; font-size: 12px; background: #f9f9f9; padding: 10px; max-height: 300px; overflow-y: auto; border: 1px solid #ddd;"></div>
            </div>
        </div>

        <div id="results-container" style="display: none;">
            <div style="background: #fff; border: 1px solid #ddd; padding: 20px; margin: 20px 0;">
                <h3>Final Results</h3>
                <div id="final-results"></div>
            </div>
        </div>

        <button id="start-linking" class="button button-primary button-large">Start Batch Processing</button>
        <button id="stop-processing" class="button button-secondary" style="margin-left: 10px; display: none;">Stop Processing</button>
        <button id="check-status" class="button" style="margin-left: 10px;">Check Current Status</button>
    </div>

    <script>
    jQuery(document).ready(function($) {
        let isProcessing = false;
        let shouldStop = false;
        let totalRecords = 0;
        let processedRecords = 0;
        let cumulativeStats = {
            linked: 0,
            stories_found: 0,
            writers_found: 0,
            already_linked: 0,
            errors: []
        };

        $('#start-linking').click(function() {
            if (isProcessing) return;
            
            isProcessing = true;
            shouldStop = false;
            processedRecords = 0;
            cumulativeStats = { linked: 0, stories_found: 0, writers_found: 0, already_linked: 0, errors: [] };
            
            $(this).hide();
            $('#stop-processing').show();
            $('#progress-container').show();
            $('#results-container').hide();
            $('#progress-details').empty();
            $('#progress-bar').css('width', '0%');
            $('#progress-text').text('Initializing batch processing...');

            // First, get the total count
            initializeBatchProcess();
        });

        $('#stop-processing').click(function() {
            shouldStop = true;
            $(this).prop('disabled', true).text('Stopping...');
            addProgressDetail('STOP REQUESTED - Finishing current batch...', 'warning');
        });

        $('#check-status').click(function() {
            checkCurrentStatus();
        });

        function initializeBatchProcess() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'initialize_writer_linking',
                    nonce: '<?php echo wp_create_nonce("writer_linking_nonce"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        totalRecords = response.data.total_records;
                        addProgressDetail('Found ' + totalRecords + ' records to process', 'info');
                        
                        if (totalRecords > 0) {
                            processBatch(0);
                        } else {
                            finishProcessing('No records found to process');
                        }
                    } else {
                        finishProcessing('Initialization failed: ' + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    finishProcessing('Initialization error: ' + error);
                }
            });
        }

        function processBatch(offset) {
            if (shouldStop) {
                finishProcessing('Processing stopped by user');
                return;
            }

            const batchSize = parseInt($('#batch-size').val());
            const currentBatch = Math.floor(offset / batchSize) + 1;
            const totalBatches = Math.ceil(totalRecords / batchSize);
            
            updateProgress((offset / totalRecords) * 100, 'Processing batch ' + currentBatch + ' of ' + totalBatches);
            $('#batch-info').text('Batch ' + currentBatch + '/' + totalBatches + ' (Records ' + (offset + 1) + '-' + Math.min(offset + batchSize, totalRecords) + ' of ' + totalRecords + ')');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'process_writer_linking_batch',
                    nonce: '<?php echo wp_create_nonce("writer_linking_nonce"); ?>',
                    offset: offset,
                    batch_size: batchSize
                },
                timeout: 60000, // 60 second timeout per batch
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        processedRecords += data.processed_count;
                        
                        // Update cumulative stats
                        cumulativeStats.linked += data.linked;
                        cumulativeStats.stories_found += data.stories_found;
                        cumulativeStats.writers_found += data.writers_found;
                        cumulativeStats.already_linked += data.already_linked;
                        cumulativeStats.errors = cumulativeStats.errors.concat(data.errors);
                        
                        addProgressDetail('Batch ' + currentBatch + ' completed: ' + data.linked + ' linked, ' + data.already_linked + ' already linked, ' + data.errors.length + ' errors', 'success');
                        
                        // Show successful links from this batch
                        if (data.success_details && data.success_details.length > 0) {
                            data.success_details.forEach(function(detail) {
                                addProgressDetail(detail.message, detail.type);
                            });
                        }
                        
                        // Show errors from this batch
                        if (data.errors.length > 0) {
                            data.errors.forEach(function(error) {
                                addProgressDetail('  ↳ ' + error, 'warning');
                            });
                        }
                        
                        // Process next batch if there are more records
                        if (processedRecords < totalRecords && !shouldStop) {
                            setTimeout(function() {
                                processBatch(offset + batchSize);
                            }, 500); // Small delay between batches to prevent overwhelming
                        } else {
                            finishProcessing('All batches completed successfully');
                        }
                    } else {
                        addProgressDetail('Batch ' + currentBatch + ' failed: ' + response.data.message, 'error');
                        finishProcessing('Processing failed at batch ' + currentBatch);
                    }
                },
                error: function(xhr, status, error) {
                    addProgressDetail('Batch ' + currentBatch + ' error: ' + error, 'error');
                    finishProcessing('Ajax error at batch ' + currentBatch + ': ' + error);
                }
            });
        }

        function finishProcessing(message) {
            isProcessing = false;
            updateProgress(100, message);
            $('#start-linking').show();
            $('#stop-processing').hide();
            addProgressDetail('PROCESS COMPLETE: ' + message, 'success');
            showFinalResults();
        }

        function checkCurrentStatus() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'check_writer_linking_status',
                    nonce: '<?php echo wp_create_nonce("writer_linking_nonce"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $('#progress-container').show();
                        addProgressDetail('STATUS CHECK: ' + response.data.message, 'info');
                    }
                }
            });
        }

        function updateProgress(percentage, text) {
            $('#progress-bar').css('width', percentage + '%');
            $('#progress-text').text(text);
        }

        function addProgressDetail(message, type = 'info') {
            const timestamp = new Date().toLocaleTimeString();
            let className = '';
            let icon = '';
            
            switch(type) {
                case 'error': 
                    className = 'color: #d63638;'; 
                    icon = '❌ ';
                    break;
                case 'success': 
                    className = 'color: #00a32a;'; 
                    icon = '✅ ';
                    break;
                case 'warning': 
                    className = 'color: #dba617;'; 
                    icon = '⚠️ ';
                    break;
                case 'link': 
                    className = 'color: #00a32a; font-weight: bold;'; 
                    icon = '🔗 ';
                    break;
                case 'found': 
                    className = 'color: #2271b1;'; 
                    icon = '📄 ';
                    break;
                default: 
                    className = 'color: #2271b1;';
                    icon = 'ℹ️ ';
            }
            
            $('#progress-details').append(
                '<div style="' + className + ' margin: 2px 0; padding: 2px 0;">[' + timestamp + '] ' + icon + message + '</div>'
            );
            $('#progress-details').scrollTop($('#progress-details')[0].scrollHeight);
        }

        function showFinalResults() {
            $('#results-container').show();
            let html = '<div style="padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px;">';
            html += '<h4 style="margin-top: 0; color: #155724;">✅ Process Completed</h4>';
            html += '<p><strong>Total records processed:</strong> ' + processedRecords + ' of ' + totalRecords + '</p>';
            html += '<p><strong>Writers linked to posts:</strong> ' + cumulativeStats.linked + '</p>';
            html += '<p><strong>Stories found:</strong> ' + cumulativeStats.stories_found + '</p>';
            html += '<p><strong>Writers found:</strong> ' + cumulativeStats.writers_found + '</p>';
            html += '<p><strong>Already linked (skipped):</strong> ' + cumulativeStats.already_linked + '</p>';
            
            if (cumulativeStats.errors && cumulativeStats.errors.length > 0) {
                html += '<h5 style="color: #721c24; margin-top: 15px;">Issues encountered (' + cumulativeStats.errors.length + '):</h5>';
                html += '<div style="max-height: 200px; overflow-y: auto; background: #f8d7da; padding: 10px; border-radius: 3px;">';
                cumulativeStats.errors.forEach(function(error) {
                    html += '<div style="margin: 2px 0; font-size: 12px;">' + error + '</div>';
                });
                html += '</div>';
            }
            
            html += '</div>';
            $('#final-results').html(html);
        }
    });
    </script>

    <style>
    #progress-details div:nth-child(even) {
        background-color: #f5f5f5;
    }
    .button:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    #batch-info {
        color: #0073aa;
        font-size: 14px;
    }
    </style>
    <?php
}

// AJAX handler to initialize and count records
add_action('wp_ajax_initialize_writer_linking', function() {
    check_ajax_referer('writer_linking_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $json_file_path = get_template_directory() . '/json/news-writers-association.json';

    if (!file_exists($json_file_path)) {
        wp_send_json_error(['message' => 'Data file not found: ' . $json_file_path]);
    }

    try {
        $json_data = file_get_contents($json_file_path);
        $json_data = preg_replace('/^\xEF\xBB\xBF/', '', $json_data);
        $json_data = mb_convert_encoding($json_data, 'UTF-8', 'UTF-8');
        $records = json_decode($json_data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(['message' => 'JSON decode error: ' . json_last_error_msg()]);
        }

        wp_send_json_success(['total_records' => count($records)]);

    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Exception: ' . $e->getMessage()]);
    }
});

// AJAX handler for batch processing
add_action('wp_ajax_process_writer_linking_batch', function() {
    check_ajax_referer('writer_linking_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $offset = intval($_POST['offset']);
    $batch_size = intval($_POST['batch_size']);

    $json_file_path = get_template_directory() . '/json/news-writers-association.json';

    if (!file_exists($json_file_path)) {
        wp_send_json_error(['message' => 'Data file not found']);
    }

    try {
        // Read and parse JSON
        $json_data = file_get_contents($json_file_path);
        $json_data = preg_replace('/^\xEF\xBB\xBF/', '', $json_data);
        $json_data = mb_convert_encoding($json_data, 'UTF-8', 'UTF-8');
        $records = json_decode($json_data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(['message' => 'JSON decode error: ' . json_last_error_msg()]);
        }

        // Get the batch slice
        $batch_records = array_slice($records, $offset, $batch_size);
        
        $stats = [
            'linked' => 0,
            'processed_count' => count($batch_records),
            'stories_found' => 0,
            'writers_found' => 0,
            'already_linked' => 0,
            'errors' => [],
            'success_details' => []
        ];

        foreach ($batch_records as $pair) {
            $story_id = intval($pair['STORY_ID']);
            $writer_id = intval($pair['WRITER_ID']);

            // Find post with matching ACF 'id' - OPTIMIZED QUERY
            $posts = get_posts([
                'post_type' => 'post',
                'meta_key' => 'id',
                'meta_value' => $story_id,
                'numberposts' => 1,
                'fields' => 'ids',
                'no_found_rows' => true, // Skip counting
                'update_post_term_cache' => false, // Skip term cache
                'update_post_meta_cache' => false, // Skip meta cache for other fields
            ]);

            if (empty($posts)) {
                // $stats['errors'][] = "Story not found for ID: {$story_id}";
                continue;
            }
            
            $stats['stories_found']++;
            $post_id = $posts[0];
            $post_title = get_the_title($post_id);
            $stats['success_details'][] = [
                'message' => "Found story: \"{$post_title}\" (ID: {$story_id})",
                'type' => 'found'
            ];

            // Find user with matching ACF 'writer_id' - OPTIMIZED QUERY
            $users = get_users([
                'meta_key' => 'writer_id',
                'meta_value' => $writer_id,
                'number' => 1,
                'fields' => 'ID',
                'count_total' => false, // Skip counting
            ]);

            if (empty($users)) {
                $stats['errors'][] = "Writer not found for ID: {$writer_id}";
                continue;
            }
            
            $stats['writers_found']++;
            $user_id = $users[0];
            $user_info = get_userdata($user_id);
            $writer_name = $user_info ? $user_info->display_name : "User ID {$user_id}";
            $stats['success_details'][] = [
                'message' => "Found writer: \"{$writer_name}\" (Writer ID: {$writer_id})",
                'type' => 'found'
            ];

            // Load existing authors
            $authors = get_field('authors', $post_id);
            if (!is_array($authors)) $authors = [];

            $already_added = false;
            foreach ($authors as $row) {
                $existing_user = $row['user'];

                // Normalize to user ID
                if (is_object($existing_user) && isset($existing_user->ID)) {
                    $existing_user = $existing_user->ID;
                } elseif (is_array($existing_user) && isset($existing_user['ID'])) {
                    $existing_user = $existing_user['ID'];
                }

                if (intval($existing_user) === intval($user_id)) {
                    $already_added = true;
                    break;
                }
            }

            // Add user if not already in the repeater
            if (!$already_added) {
                $authors[] = ['user' => $user_id];
                update_field('authors', $authors, $post_id);
                $stats['linked']++;
                $stats['success_details'][] = [
                    'message' => "✓ LINKED: \"{$writer_name}\" → \"{$post_title}\"",
                    'type' => 'link'
                ];
            } else {
                $stats['already_linked']++;
                $stats['success_details'][] = [
                    'message' => "Already linked: \"{$writer_name}\" → \"{$post_title}\"",
                    'type' => 'info'
                ];
            }
        }

        wp_send_json_success($stats);

    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Exception: ' . $e->getMessage()]);
    }
});

// AJAX handler for status check (unchanged)
add_action('wp_ajax_check_writer_linking_status', function() {
    check_ajax_referer('writer_linking_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $json_file_path = get_template_directory() . '/json/news-writers-association.json';
    
    if (!file_exists($json_file_path)) {
        wp_send_json_success(['message' => 'JSON file not found']);
        return;
    }

    $file_size = filesize($json_file_path);
    $file_modified = date('Y-m-d H:i:s', filemtime($json_file_path));
    
    // Quick count of records
    $json_data = file_get_contents($json_file_path);
    $records = json_decode($json_data, true);
    $record_count = is_array($records) ? count($records) : 0;
    
    wp_send_json_success([
        'message' => "JSON file exists. Size: {$file_size} bytes. Modified: {$file_modified}. Records: {$record_count}"
    ]);
});
?>