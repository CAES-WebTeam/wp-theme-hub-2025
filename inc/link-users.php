<?php
/*** Admin Page for Writer/Expert Linking and Clearing ***/

// Add admin menu
add_action('admin_menu', function() {
    add_management_page(
        'Link Writers and Experts to Stories', // Updated page title for clarity
        'Link Writers and Experts to Stories',           // Updated menu title
        'manage_options',
        'link-content-admin',             // Generic slug
        'render_content_linking_admin_page' // Generic render function
    );
});

// Render the admin page
function render_content_linking_admin_page() {
    ?>
    <div class="wrap">
        <h1>Link Writers and Experts to Stories</h1>
        <p>This tool allows you to sync news stories with writers or experts based on JSON data files, or clear all existing links for a selected type. It uses batch processing to handle large datasets.</p>

        <div id="linking-type-selection" style="background: #fff; border: 1px solid #ddd; padding: 15px; margin: 20px 0; border-radius: 8px;">
            <h3>Select Content Type</h3>
            <label style="margin-right: 20px;">
                <input type="radio" name="linking_type" value="writers" checked> Writers
            </label>
            <label>
                <input type="radio" name="linking_type" value="experts"> Experts
            </label>
            <p style="color: #666; font-style: italic; margin-top: 10px;">
                Choose whether to operate on story writers or story experts/sources.
            </p>
        </div>
        
        <div id="batch-settings" style="background: #fff; border: 1px solid #ddd; padding: 15px; margin: 20px 0; border-radius: 8px;">
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
        
        <div id="progress-container" style="display: none; background: #fff; border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 8px;">
            <h3>Progress</h3>
            <div id="progress-bar-container" style="background: #f0f0f0; height: 20px; border-radius: 10px; margin: 10px 0; overflow: hidden;">
                <div id="progress-bar" style="background: #0073aa; height: 100%; border-radius: 10px; width: 0%; transition: width 0.3s ease-out;"></div>
            </div>
            <div id="progress-text">Preparing...</div>
            <div id="batch-info" style="margin: 10px 0; font-weight: bold;"></div>
            <div id="progress-details" style="margin-top: 15px; font-family: monospace; font-size: 12px; background: #f9f9f9; padding: 10px; max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px;"></div>
        </div>

        <div id="results-container" style="display: none; background: #fff; border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 8px;">
            <h3>Final Results</h3>
            <div id="final-results"></div>
        </div>

        <button id="start-linking" class="button button-primary button-large">Start Linking</button>
        <button id="clear-all" class="button button-danger button-large" style="margin-left: 20px;">Clear All Linked <span id="clear-type-label">Writers</span></button>
        <button id="stop-processing" class="button button-secondary" style="margin-left: 10px; display: none;">Stop Processing</button>
        <button id="check-status" class="button" style="margin-left: 10px;">Check Current Status</button>

        <div id="confirm-modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
            <div style="background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.2);">
                <h2 style="margin-top: 0;">Confirm Action</h2>
                <p id="confirm-message" style="font-size: 1.1em;"></p>
                <div style="text-align: right; margin-top: 20px;">
                    <button id="confirm-yes" class="button button-primary">Yes, Proceed</button>
                    <button id="confirm-no" class="button button-secondary" style="margin-left: 10px;">Cancel</button>
                </div>
            </div>
        </div>

    </div>

    <script>
    jQuery(document).ready(function($) {
        let isProcessing = false;
        let shouldStop = false;
        let totalRecords = 0;
        let processedRecords = 0;
        let cumulativeStats = {
            linked: 0,
            cleared: 0, // New stat for clearing
            stories_found: 0,
            users_found: 0,
            already_linked: 0,
            errors: []
        };
        let currentOperation = 'linking'; // 'linking' or 'clearing'
        let linkingType = $('input[name="linking_type"]:checked').val(); // Get initial type

        // Update linkingType and button label when radio button changes
        $('input[name="linking_type"]').change(function() {
            linkingType = $(this).val();
            $('#clear-type-label').text(linkingType === 'writers' ? 'Writers' : 'Experts');
            resetUI(); // Reset UI and stats when type changes
        });

        // Initialize clear button label
        $('#clear-type-label').text(linkingType === 'writers' ? 'Writers' : 'Experts');

        $('#start-linking').click(function() {
            if (isProcessing) return;
            currentOperation = 'linking';
            resetProcessingState();
            $(this).hide();
            $('#clear-all').hide(); // Hide clear button during linking
            $('#stop-processing').show();
            $('#progress-container').show();
            $('#results-container').hide();
            $('#progress-details').empty();
            $('#progress-bar').css('width', '0%');
            $('#progress-text').text('Initializing batch processing for linking...');

            initializeBatchProcess();
        });

        $('#clear-all').click(function() {
            if (isProcessing) return;
            currentOperation = 'clearing';
            const typeLabel = linkingType === 'writers' ? 'Writers' : 'Experts';
            $('#confirm-message').html(`Are you sure you want to <strong>clear ALL linked ${typeLabel}</strong> from ALL stories? This action cannot be undone.`);
            $('#confirm-modal').fadeIn();

            // Store the function to call if confirmed
            $('#confirm-yes').off('click').on('click', function() {
                $('#confirm-modal').fadeOut();
                resetProcessingState();
                $('#start-linking').hide();
                $('#clear-all').hide();
                $('#stop-processing').show();
                $('#progress-container').show();
                $('#results-container').hide();
                $('#progress-details').empty();
                $('#progress-bar').css('width', '0%');
                $('#progress-text').text('Initializing batch processing for clearing...');
                initializeBatchProcess(); // Re-use for clearing initialization
            });

            $('#confirm-no').off('click').on('click', function() {
                $('#confirm-modal').fadeOut();
            });
        });

        $('#stop-processing').click(function() {
            shouldStop = true;
            $(this).prop('disabled', true).text('Stopping...');
            addProgressDetail('STOP REQUESTED - Finishing current batch...', 'warning');
        });

        $('#check-status').click(function() {
            checkCurrentStatus();
        });

        function resetUI() {
            isProcessing = false;
            shouldStop = false;
            totalRecords = 0;
            processedRecords = 0;
            cumulativeStats = { linked: 0, cleared: 0, stories_found: 0, users_found: 0, already_linked: 0, errors: [] };

            $('#start-linking').show();
            $('#clear-all').show().prop('disabled', false); // Ensure clear button is visible and enabled
            $('#stop-processing').hide().prop('disabled', false).text('Stop Processing');
            $('#progress-container').hide();
            $('#results-container').hide();
            $('#progress-details').empty();
            $('#progress-bar').css('width', '0%');
            $('#progress-text').text('Preparing...');
            $('#batch-info').empty();
            $('#final-results').empty();
        }

        function resetProcessingState() {
            isProcessing = true;
            shouldStop = false;
            processedRecords = 0;
            cumulativeStats = { linked: 0, cleared: 0, stories_found: 0, users_found: 0, already_linked: 0, errors: [] };
        }

        function initializeBatchProcess() {
            let actionName = currentOperation === 'linking' ? 'initialize_content_linking' : 'initialize_content_clearing';
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: actionName,
                    nonce: '<?php echo wp_create_nonce("content_linking_nonce"); ?>',
                    linking_type: linkingType
                },
                success: function(response) {
                    if (response.success) {
                        totalRecords = response.data.total_records;
                        addProgressDetail('Found ' + totalRecords + ' records to process for ' + linkingType + ' ' + currentOperation, 'info');
                        
                        if (totalRecords > 0) {
                            processBatch(0);
                        } else {
                            finishProcessing('No records found to process for ' + linkingType + ' ' + currentOperation);
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
            
            updateProgress((offset / totalRecords) * 100, 'Processing batch ' + currentBatch + ' of ' + totalBatches + ' for ' + linkingType + ' ' + currentOperation + '...');
            $('#batch-info').text('Batch ' + currentBatch + '/' + totalBatches + ' (Records ' + (offset + 1) + '-' + Math.min(offset + batchSize, totalRecords) + ' of ' + totalRecords + ')');

            let actionName = currentOperation === 'linking' ? 'process_content_linking_batch' : 'process_content_clearing_batch';

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: actionName,
                    nonce: '<?php echo wp_create_nonce("content_linking_nonce"); ?>',
                    offset: offset,
                    batch_size: batchSize,
                    linking_type: linkingType
                },
                timeout: 60000, // 60 second timeout per batch
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        processedRecords += data.processed_count;
                        
                        // Update cumulative stats based on operation
                        if (currentOperation === 'linking') {
                            cumulativeStats.linked += data.linked;
                            cumulativeStats.stories_found += data.stories_found;
                            cumulativeStats.users_found += data.users_found;
                            cumulativeStats.already_linked += data.already_linked;
                        } else { // clearing
                            cumulativeStats.cleared += data.cleared;
                            cumulativeStats.stories_found += data.processed_count; // For clearing, processed_count is stories found
                        }
                        cumulativeStats.errors = cumulativeStats.errors.concat(data.errors);
                        
                        if (currentOperation === 'linking') {
                            addProgressDetail('Batch ' + currentBatch + ' completed: ' + data.linked + ' linked, ' + data.already_linked + ' already linked, ' + data.errors.length + ' errors', 'success');
                        } else {
                            addProgressDetail('Batch ' + currentBatch + ' completed: ' + data.cleared + ' cleared, ' + data.errors.length + ' errors', 'success');
                        }
                        
                        // Show successful details from this batch
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
                            finishProcessing('All batches completed successfully for ' + linkingType + ' ' + currentOperation);
                        }
                    } else {
                        addProgressDetail('Batch ' + currentBatch + ' failed: ' + response.data.message, 'error');
                        finishProcessing('Processing failed at batch ' + currentBatch + ' for ' + linkingType + ' ' + currentOperation);
                    }
                },
                error: function(xhr, status, error) {
                    addProgressDetail('Batch ' + currentBatch + ' error: ' + error, 'error');
                    finishProcessing('Ajax error at batch ' + currentBatch + ' for ' + linkingType + ' ' + currentOperation + ': ' + error);
                }
            });
        }

        function finishProcessing(message) {
            isProcessing = false;
            updateProgress(100, message);
            $('#start-linking').show();
            $('#clear-all').show().prop('disabled', false); // Show and enable clear button
            $('#stop-processing').hide().prop('disabled', false).text('Stop Processing'); // Reset button state
            addProgressDetail('PROCESS COMPLETE: ' + message, 'success');
            showFinalResults();
        }

        function checkCurrentStatus() {
            let actionName = 'check_content_linking_status'; // Status check is generic
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: actionName,
                    nonce: '<?php echo wp_create_nonce("content_linking_nonce"); ?>',
                    linking_type: linkingType // Pass selected type
                },
                success: function(response) {
                    if (response.success) {
                        $('#progress-container').show();
                        addProgressDetail('STATUS CHECK for ' + linkingType + ': ' + response.data.message, 'info');
                    } else {
                        addProgressDetail('STATUS CHECK FAILED for ' + linkingType + ': ' + response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    addProgressDetail('STATUS CHECK ERROR for ' + linkingType + ': ' + error, 'error');
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
                case 'cleared': 
                    className = 'color: #d63638; font-weight: bold;'; 
                    icon = '🗑️ ';
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
            const typeLabel = linkingType === 'writers' ? 'Writers' : 'Experts';
            const operationLabel = currentOperation === 'linking' ? 'Linking' : 'Clearing';
            let html = '<div style="padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px;">';
            html += `<h4 style="margin-top: 0; color: #155724;">✅ ${operationLabel} Process Completed for ${typeLabel}</h4>`;
            html += '<p><strong>Total records processed:</strong> ' + processedRecords + ' of ' + totalRecords + '</p>';
            
            if (currentOperation === 'linking') {
                html += '<p><strong>' + typeLabel + ' linked to posts:</strong> ' + cumulativeStats.linked + '</p>';
                html += '<p><strong>Stories found:</strong> ' + cumulativeStats.stories_found + '</p>';
                html += '<p><strong>' + typeLabel + ' found:</strong> ' + cumulativeStats.users_found + '</p>';
                html += '<p><strong>Already linked (skipped):</b> ' + cumulativeStats.already_linked + '</p>';
            } else { // clearing
                html += '<p><strong>Total posts cleared:</strong> ' + cumulativeStats.cleared + '</p>';
                html += '<p><strong>Posts processed:</strong> ' + cumulativeStats.stories_found + '</p>'; // Renamed for clarity in clearing
            }
            
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
    .button-danger {
        background: #dc3232;
        border-color: #dc3232;
        color: #fff;
        box-shadow: 0 1px 0 rgba(0,0,0,.15);
        text-shadow: 0 -1px 1px #b32d2d,0 1px 1px #e76d6d;
    }
    .button-danger:hover, .button-danger:focus {
        background: #e03f3f;
        border-color: #e03f3f;
        color: #fff;
    }
    </style>
    <?php
}

// AJAX handler to initialize and count records (generic for linking)
add_action('wp_ajax_initialize_content_linking', 'initialize_content_linking_callback');
function initialize_content_linking_callback() {
    check_ajax_referer('content_linking_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $linking_type = isset($_POST['linking_type']) ? sanitize_text_field($_POST['linking_type']) : 'writers';

    $json_file_path = '';
    if ($linking_type === 'writers') {
        $json_file_path = get_template_directory() . '/json/news-writers-association.json';
    } elseif ($linking_type === 'experts') {
        $json_file_path = get_template_directory() . '/json/NewsAssociationStorySourceExpert.json';
    } else {
        wp_send_json_error(['message' => 'Invalid linking type specified.']);
    }

    if (!file_exists($json_file_path)) {
        wp_send_json_error(['message' => 'Data file not found for ' . $linking_type . ': ' . $json_file_path]);
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
}

// AJAX handler for batch processing (generic for linking)
add_action('wp_ajax_process_content_linking_batch', 'process_content_linking_batch_callback');
function process_content_linking_batch_callback() {
    check_ajax_referer('content_linking_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $offset = intval($_POST['offset']);
    $batch_size = intval($_POST['batch_size']);
    $linking_type = isset($_POST['linking_type']) ? sanitize_text_field($_POST['linking_type']) : 'writers';

    $json_file_path = '';
    $user_meta_key = '';
    $story_user_field = ''; // The ACF repeater field name on the post
    $user_type_label = '';
    $json_id_key = '';

    if ($linking_type === 'writers') {
        $json_file_path = get_template_directory() . '/json/news-writers-association.json';
        $user_meta_key = 'writer_id';
        $story_user_field = 'authors';
        $user_type_label = 'writer';
        $json_id_key = 'WRITER_ID';
    } elseif ($linking_type === 'experts') {
        $json_file_path = get_template_directory() . '/json/NewsAssociationStorySourceExpert.json';
        $user_meta_key = 'source_expert_id';
        $story_user_field = 'experts';
        $user_type_label = 'expert';
        $json_id_key = 'SOURCE_EXPERT_ID';
    } else {
        wp_send_json_error(['message' => 'Invalid linking type specified.']);
    }

    if (!file_exists($json_file_path)) {
        wp_send_json_error(['message' => 'Data file not found for ' . $linking_type]);
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
            'users_found' => 0, // Generic for writers/experts
            'already_linked' => 0,
            'errors' => [],
            'success_details' => []
        ];

        foreach ($batch_records as $pair) {
            $story_id = intval($pair['STORY_ID']);
            $user_content_id = intval($pair[$json_id_key]); // Use dynamic key

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
                // $stats['errors'][] = "Story not found for ID: {$story_id}"; // Too noisy for console, better to omit if success_details are detailed
                continue;
            }
            
            $stats['stories_found']++;
            $post_id = $posts[0];
            $post_title = get_the_title($post_id);
            $stats['success_details'][] = [
                'message' => "Found story: \"{$post_title}\" (Story ID: {$story_id})",
                'type' => 'found'
            ];

            // Find user with matching ACF 'writer_id' or 'source_expert_id' - OPTIMIZED QUERY
            $users = get_users([
                'meta_key' => $user_meta_key,
                'meta_value' => $user_content_id,
                'number' => 1,
                'fields' => 'ID',
                'count_total' => false, // Skip counting
            ]);

            if (empty($users)) {
                $stats['errors'][] = ucfirst($user_type_label) . " not found for ID: {$user_content_id} (Linked to Story ID: {$story_id})";
                continue;
            }
            
            $stats['users_found']++;
            $user_id = $users[0];
            $user_info = get_userdata($user_id);
            $display_name = $user_info ? $user_info->display_name : ucfirst($user_type_label) . " ID {$user_id}";
            $stats['success_details'][] = [
                'message' => "Found {$user_type_label}: \"{$display_name}\" ({$user_type_label} ID: {$user_content_id})",
                'type' => 'found'
            ];

            // Load existing linked users (authors or experts)
            $existing_linked_users = get_field($story_user_field, $post_id);
            if (!is_array($existing_linked_users)) $existing_linked_users = [];

            $already_added = false;
            foreach ($existing_linked_users as $row) {
                $existing_user_in_repeater = $row['user'];

                // Normalize to user ID (handles ACF returning object or array for user field)
                if (is_object($existing_user_in_repeater) && isset($existing_user_in_repeater->ID)) {
                    $existing_user_in_repeater = $existing_user_in_repeater->ID;
                } elseif (is_array($existing_user_in_repeater) && isset($existing_user_in_repeater['ID'])) {
                    $existing_user_in_repeater = $existing_user_in_repeater['ID'];
                }

                if (intval($existing_user_in_repeater) === intval($user_id)) {
                    $already_added = true;
                    break;
                }
            }

            // Add user if not already in the repeater
            if (!$already_added) {
                $existing_linked_users[] = ['user' => $user_id];
                update_field($story_user_field, $existing_linked_users, $post_id);
                do_action('acf/save_post', $post_id);
                clean_post_cache($post_id);
                wp_cache_delete( $post_id, 'post_meta' );

                $stats['linked']++;
                $stats['success_details'][] = [
                    'message' => "✓ LINKED {$user_type_label}: \"{$display_name}\" → \"{$post_title}\"",
                    'type' => 'link'
                ];
            } else {
                $stats['already_linked']++;
                $stats['success_details'][] = [
                    'message' => "Already linked: \"{$display_name}\" → \"{$post_title}\"",
                    'type' => 'info'
                ];
            }
        }

        wp_send_json_success($stats);

    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Exception: ' . $e->getMessage()]);
    }
}

// AJAX handler to initialize and count records for CLEARING
add_action('wp_ajax_initialize_content_clearing', 'initialize_content_clearing_callback');
function initialize_content_clearing_callback() {
    check_ajax_referer('content_linking_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $linking_type = isset($_POST['linking_type']) ? sanitize_text_field($_POST['linking_type']) : 'writers';
    $story_user_field = ($linking_type === 'writers') ? 'authors' : 'experts';

    try {
        // Count all posts that might have the relevant ACF field
        // This is a rough count; actual processing will only affect posts with the field
        $posts_query = new WP_Query([
            'post_type'      => 'post',
            'post_status'    => 'publish', // Only published posts, adjust if needed
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [ // Only count posts that actually have this ACF field
                [
                    'key'     => $story_user_field,
                    'compare' => 'EXISTS',
                ],
            ],
        ]);
        $total_posts = count($posts_query->posts);

        wp_send_json_success(['total_records' => $total_posts]);

    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Exception: ' . $e->getMessage()]);
    }
}

// AJAX handler for batch processing for CLEARING
add_action('wp_ajax_process_content_clearing_batch', 'process_content_clearing_batch_callback');
function process_content_clearing_batch_callback() {
    check_ajax_referer('content_linking_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $offset = intval($_POST['offset']);
    $batch_size = intval($_POST['batch_size']);
    $linking_type = isset($_POST['linking_type']) ? sanitize_text_field($_POST['linking_type']) : 'writers';

    $story_user_field = ($linking_type === 'writers') ? 'authors' : 'experts';
    $user_type_label = ($linking_type === 'writers') ? 'writer' : 'expert';

    $stats = [
        'cleared' => 0,
        'processed_count' => 0, // Number of posts processed in this batch
        'errors' => [],
        'success_details' => []
    ];

    try {
        // Get posts that have the relevant ACF field, in batches
        $posts_query = new WP_Query([
            'post_type'      => 'post',
            'post_status'    => 'publish', // Only published posts, adjust if needed
            'posts_per_page' => $batch_size,
            'offset'         => $offset,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [ // Only get posts that actually have this ACF field
                [
                    'key'     => $story_user_field,
                    'compare' => 'EXISTS',
                ],
            ],
        ]);

        $batch_post_ids = $posts_query->posts;
        $stats['processed_count'] = count($batch_post_ids);

        foreach ($batch_post_ids as $post_id) {
            $post_title = get_the_title($post_id);
            $current_field_value = get_field($story_user_field, $post_id);

            if (!empty($current_field_value)) {
                // Clear the ACF repeater field by setting it to an empty array
                update_field($story_user_field, [], $post_id);
                do_action('acf/save_post', $post_id);
                clean_post_cache($post_id);
                wp_cache_delete( $post_id, 'post_meta' );

                $stats['cleared']++;
                $stats['success_details'][] = [
                    'message' => "🗑️ CLEARED: All {$user_type_label}s from \"{$post_title}\" (Post ID: {$post_id})",
                    'type' => 'cleared'
                ];
            } else {
                $stats['success_details'][] = [
                    'message' => "No {$user_type_label}s to clear for \"{$post_title}\" (Post ID: {$post_id})",
                    'type' => 'info'
                ];
            }
        }

        wp_send_json_success($stats);

    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Exception: ' . $e->getMessage()]);
    }
}


// AJAX handler for status check (generic)
add_action('wp_ajax_check_content_linking_status', 'check_content_linking_status_callback');
function check_content_linking_status_callback() {
    check_ajax_referer('content_linking_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $linking_type = isset($_POST['linking_type']) ? sanitize_text_field($_POST['linking_type']) : 'writers';

    $json_file_path = '';
    if ($linking_type === 'writers') {
        $json_file_path = get_template_directory() . '/json/news-writers-association.json';
    } elseif ($linking_type === 'experts') {
        $json_file_path = get_template_directory() . '/json/NewsAssociationStorySourceExpert.json';
    } else {
        wp_send_json_error(['message' => 'Invalid linking type specified.']);
    }
    
    if (!file_exists($json_file_path)) {
        wp_send_json_success(['message' => 'JSON file not found for ' . $linking_type]);
        return;
    }

    $file_size = filesize($json_file_path);
    $file_modified = date('Y-m-d H:i:s', filemtime($json_file_path));
    
    // Quick count of records
    $json_data = file_get_contents($json_file_path);
    $records = json_decode($json_data, true);
    $record_count = is_array($records) ? count($records) : 0;
    
    wp_send_json_success([
        'message' => "JSON file for " . $linking_type . " exists. Size: {$file_size} bytes. Modified: {$file_modified}. Records: {$record_count}"
    ]);
}