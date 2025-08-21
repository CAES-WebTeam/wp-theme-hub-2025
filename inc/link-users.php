<?php

/*** Admin Page for Writer/Expert/Publication Author Linking ***/

// Add admin menu
add_action('admin_menu', function () {
    add_submenu_page(
        'caes-tools',
        'Link Writers, Experts, and Publication Authors',
        'Link Writers, Experts, and Publication Authors',
        'manage_options',
        'link-content-admin',
        'render_content_linking_admin_page'
    );
});

// Render the admin page
function render_content_linking_admin_page()
{
?>
    <div class="wrap">
        <h1>Link Writers, Experts, and Publication Authors</h1>
        <p>This tool syncs content with users based on live API data using batch processing.</p>

        <div id="linking-type-selection" style="background: #fff; border: 1px solid #ddd; padding: 15px; margin: 20px 0; border-radius: 8px;">
            <h3>Select Content Type</h3>
            <label style="margin-right: 20px;">
                <input type="radio" name="linking_type" value="writers" checked> Writers (News Stories)
            </label>
            <label style="margin-right: 20px;">
                <input type="radio" name="linking_type" value="experts"> Experts (News Stories)
            </label>
            <label>
                <input type="radio" name="linking_type" value="publications"> Publication Authors
            </label>
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
        <button id="stop-processing" class="button button-secondary" style="margin-left: 10px; display: none;">Stop Processing</button>
        <button id="check-status" class="button" style="margin-left: 10px;">Check Current Status</button>
    </div>

    <script>
        jQuery(document).ready(function($) {
            let isProcessing = false;
            let shouldStop = false;
            let totalRecords = 0;
            let processedRecords = 0;
            let cumulativeStats = { linked: 0, already_linked: 0, errors: [] };
            let linkingType = $('input[name="linking_type"]:checked').val();

            $('input[name="linking_type"]').change(function() {
                linkingType = $(this).val();
                resetUI();
            });

            $('#start-linking').click(function() {
                if (isProcessing) return;
                resetProcessingState();
                $(this).hide();
                $('#stop-processing').show();
                $('#progress-container').show();
                $('#results-container').hide();
                $('#progress-details').empty();
                $('#progress-bar').css('width', '0%');
                $('#progress-text').text('Initializing batch processing...');
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

            function resetUI() {
                isProcessing = false;
                shouldStop = false;
                totalRecords = 0;
                processedRecords = 0;
                cumulativeStats = { linked: 0, already_linked: 0, errors: [] };
                $('#start-linking').show();
                $('#stop-processing').hide().prop('disabled', false).text('Stop Processing');
                $('#progress-container').hide();
                $('#results-container').hide();
            }

            function resetProcessingState() {
                isProcessing = true;
                shouldStop = false;
                processedRecords = 0;
                cumulativeStats = { linked: 0, already_linked: 0, errors: [] };
            }

            function initializeBatchProcess() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'initialize_content_linking',
                        nonce: '<?php echo wp_create_nonce("content_linking_nonce"); ?>',
                        linking_type: linkingType
                    },
                    success: function(response) {
                        if (response.success) {
                            totalRecords = response.data.total_records;
                            addProgressDetail('Found ' + totalRecords + ' API records to process for ' + linkingType, 'info');
                            
                            if (totalRecords > 0) {
                                processBatch(0);
                            } else {
                                finishProcessing('No API records found to process');
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

                updateProgress((offset / totalRecords) * 100, 'Processing batch ' + currentBatch + ' of ' + totalBatches + '...');
                $('#batch-info').text('Batch ' + currentBatch + '/' + totalBatches + ' (Records ' + (offset + 1) + '-' + Math.min(offset + batchSize, totalRecords) + ' of ' + totalRecords + ')');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'process_content_linking_batch',
                        nonce: '<?php echo wp_create_nonce("content_linking_nonce"); ?>',
                        offset: offset,
                        batch_size: batchSize,
                        linking_type: linkingType
                    },
                    timeout: 60000,
                    success: function(response) {
                        if (response.success) {
                            const data = response.data;
                            processedRecords += data.processed_count;
                            cumulativeStats.linked += data.linked;
                            cumulativeStats.already_linked += data.already_linked;
                            cumulativeStats.errors = cumulativeStats.errors.concat(data.errors);

                            addProgressDetail('Batch ' + currentBatch + ': ' + data.linked + ' linked, ' + data.already_linked + ' already linked, ' + data.errors.length + ' errors', 'success');

                            if (data.success_details && data.success_details.length > 0) {
                                data.success_details.forEach(function(detail) {
                                    addProgressDetail(detail.message, detail.type);
                                });
                            }

                            if (data.errors.length > 0) {
                                data.errors.forEach(function(error) {
                                    addProgressDetail('  ↳ ' + error, 'warning');
                                });
                            }

                            if (processedRecords < totalRecords && !shouldStop) {
                                setTimeout(function() {
                                    processBatch(offset + batchSize);
                                }, 500);
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
                $('#stop-processing').hide().prop('disabled', false).text('Stop Processing');
                addProgressDetail('PROCESS COMPLETE: ' + message, 'success');
                showFinalResults();
            }

            function checkCurrentStatus() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'check_content_linking_status',
                        nonce: '<?php echo wp_create_nonce("content_linking_nonce"); ?>',
                        linking_type: linkingType
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#progress-container').show();
                            addProgressDetail('STATUS CHECK: ' + response.data.message, 'info');
                        } else {
                            addProgressDetail('STATUS CHECK FAILED: ' + response.data.message, 'error');
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
                let className = '', icon = '';

                switch (type) {
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
                let typeLabel = linkingType === 'writers' ? 'Writers' : linkingType === 'experts' ? 'Experts' : 'Publication Authors';
                
                let html = '<div style="padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px;">';
                html += '<h4 style="margin-top: 0; color: #155724;">✅ Linking Process Completed for ' + typeLabel + '</h4>';
                html += '<p><strong>Total records processed:</strong> ' + processedRecords + ' of ' + totalRecords + '</p>';
                html += '<p><strong>' + typeLabel + ' linked:</strong> ' + cumulativeStats.linked + '</p>';
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
        #progress-details div:nth-child(even) { background-color: #f5f5f5; }
        .button:disabled { opacity: 0.6; cursor: not-allowed; }
        #batch-info { color: #0073aa; font-size: 14px; }
    </style>
<?php
}

// Fetch API data without caching
function fetch_api_data($linking_type)
{
    $api_urls = [
        'writers' => 'https://secure.caes.uga.edu/rest/news/getAssociationStoryWriter',
        'experts' => 'https://secure.caes.uga.edu/rest/news/getAssociationStorySourceExpert',
        'publications' => 'https://secure.caes.uga.edu/rest/publications/getAuthorAssociations'
    ];

    $response = wp_remote_get($api_urls[$linking_type], ['timeout' => 30]);
    
    if (is_wp_error($response)) {
        return new WP_Error('api_error', 'API request failed: ' . $response->get_error_message());
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('json_error', 'API JSON decode error: ' . json_last_error_msg());
    }

    return $data;
}

// Build lookup tables for content and users
function build_lookup_tables($linking_type)
{
    $content_lookup = [];
    $user_lookup = [];

    // Build content lookup
    if ($linking_type === 'publications') {
        $posts = get_posts(['post_type' => 'publications', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids']);
        foreach ($posts as $post_id) {
            $content_id = get_field('publication_id', $post_id);
            if ($content_id) $content_lookup[intval($content_id)] = $post_id;
        }
    } else {
        $posts = get_posts(['post_type' => 'post', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids']);
        foreach ($posts as $post_id) {
            $content_id = get_field('id', $post_id);
            if ($content_id) $content_lookup[intval($content_id)] = $post_id;
        }
    }

    // Build user lookup
    $user_meta_keys = [
        'writers' => 'writer_id',
        'experts' => 'source_expert_id',
        'publications' => 'college_id'
    ];
    
    $users = get_users(['meta_key' => $user_meta_keys[$linking_type], 'fields' => ['ID']]);
    foreach ($users as $user) {
        $user_id_value = get_user_meta($user->ID, $user_meta_keys[$linking_type], true);
        if ($user_id_value) $user_lookup[intval($user_id_value)] = $user->ID;
    }

    return [$content_lookup, $user_lookup];
}

// Process API data into linking requirements
function process_api_data($api_data, $content_lookup, $user_lookup, $linking_type)
{
    $linking_data = [];

    $api_keys = [
        'writers' => ['STORY_ID', 'WRITER_ID'],
        'experts' => ['STORY_ID', 'SOURCE_EXPERT_ID'],
        'publications' => ['PUBLICATION_ID', 'COLLEGE_ID']
    ];

    foreach ($api_data as $record) {
        $content_id = intval($record[$api_keys[$linking_type][0]]);
        $user_id = intval($record[$api_keys[$linking_type][1]]);

        if (isset($content_lookup[$content_id]) && isset($user_lookup[$user_id])) {
            if (!isset($linking_data[$content_id])) {
                $linking_data[$content_id] = [];
            }

            if ($linking_type === 'publications') {
                $linking_data[$content_id][] = [
                    'user_id' => $user_lookup[$user_id],
                    'is_lead_author' => isset($record['IS_LEAD_AUTHOR']) ? (bool)$record['IS_LEAD_AUTHOR'] : false,
                    'is_co_author' => isset($record['IS_CO_AUTHOR']) ? (bool)$record['IS_CO_AUTHOR'] : false
                ];
            } else {
                $linking_data[$content_id][] = $user_lookup[$user_id];
            }
        }
    }

    return $linking_data;
}

// AJAX handler to initialize and count records
add_action('wp_ajax_initialize_content_linking', 'initialize_content_linking_callback');
function initialize_content_linking_callback()
{
    check_ajax_referer('content_linking_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $linking_type = sanitize_text_field($_POST['linking_type']);

    // Fetch fresh API data
    $api_data = fetch_api_data($linking_type);
    if (is_wp_error($api_data)) {
        wp_send_json_error(['message' => $api_data->get_error_message()]);
    }

    wp_send_json_success(['total_records' => count($api_data)]);
}

// AJAX handler for batch processing
add_action('wp_ajax_process_content_linking_batch', 'process_content_linking_batch_callback');
function process_content_linking_batch_callback()
{
    check_ajax_referer('content_linking_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $offset = intval($_POST['offset']);
    $batch_size = intval($_POST['batch_size']);
    $linking_type = sanitize_text_field($_POST['linking_type']);

    // Fresh API data for each batch
    $api_data = fetch_api_data($linking_type);
    if (is_wp_error($api_data)) {
        wp_send_json_error(['message' => $api_data->get_error_message()]);
    }

    // Get the batch of API records
    $batch_records = array_slice($api_data, $offset, $batch_size);
    
    // Build lookup tables
    list($content_lookup, $user_lookup) = build_lookup_tables($linking_type);
    
    $stats = ['linked' => 0, 'processed_count' => count($batch_records), 'already_linked' => 0, 'errors' => [], 'success_details' => []];
    $field_name = ($linking_type === 'experts') ? 'experts' : 'authors';
    $api_keys = [
        'writers' => ['STORY_ID', 'WRITER_ID'],
        'experts' => ['STORY_ID', 'SOURCE_EXPERT_ID'],
        'publications' => ['PUBLICATION_ID', 'COLLEGE_ID']
    ];

    foreach ($batch_records as $record) {
        $content_id = intval($record[$api_keys[$linking_type][0]]);
        $user_external_id = intval($record[$api_keys[$linking_type][1]]);

        // Skip if we don't have matching content or user
        if (!isset($content_lookup[$content_id])) {
            $content_type = ($linking_type === 'publications') ? 'publication' : 'story';
            $stats['errors'][] = "Missing {$content_type}: No {$content_type} found with {$api_keys[$linking_type][0]} = {$content_id}";
            continue;
        }

        if (!isset($user_lookup[$user_external_id])) {
            $user_type = ($linking_type === 'writers') ? 'writer' : ($linking_type === 'experts' ? 'expert' : 'author');
            $stats['errors'][] = "Missing {$user_type}: No user found with {$api_keys[$linking_type][1]} = {$user_external_id}";
            continue;
        }

        $post_id = $content_lookup[$content_id];
        $user_id = $user_lookup[$user_external_id];
        $post_title = get_the_title($post_id);
        $user_info = get_userdata($user_id);
        $display_name = $user_info ? $user_info->display_name : "User {$user_id}";

        // Get existing links
        $existing_links = get_field($field_name, $post_id);
        if (!is_array($existing_links)) $existing_links = [];

        $updated_this_post = false;

        if ($linking_type === 'publications') {
            // Handle publications with roles
            $is_lead_author = isset($record['IS_LEAD_AUTHOR']) ? (bool)$record['IS_LEAD_AUTHOR'] : false;
            $is_co_author = isset($record['IS_CO_AUTHOR']) ? (bool)$record['IS_CO_AUTHOR'] : false;

            $user_found_index = -1;
            $needs_update = false;

            foreach ($existing_links as $index => $existing_link) {
                $existing_user = $existing_link['user'];
                if (is_object($existing_user)) $existing_user = $existing_user->ID;
                if (is_array($existing_user)) $existing_user = $existing_user['ID'];

                if (intval($existing_user) === intval($user_id)) {
                    $user_found_index = $index;
                    $current_lead = isset($existing_link['lead_author']) ? (bool)$existing_link['lead_author'] : false;
                    $current_co = isset($existing_link['co_author']) ? (bool)$existing_link['co_author'] : false;

                    if ($current_lead !== $is_lead_author || $current_co !== $is_co_author) {
                        $needs_update = true;
                    }
                    break;
                }
            }

            if ($user_found_index === -1) {
                // Add new user
                $existing_links[] = [
                    'user' => $user_id,
                    'lead_author' => $is_lead_author,
                    'co_author' => $is_co_author
                ];
                $updated_this_post = true;
                $stats['linked']++;
                
                $roles = [];
                if ($is_lead_author) $roles[] = 'Lead Author';
                if ($is_co_author) $roles[] = 'Co-Author';
                $role_text = !empty($roles) ? ' (' . implode(', ', $roles) . ')' : '';
                
                $stats['success_details'][] = [
                    'message' => "LINKED: \"{$display_name}\" → \"{$post_title}\"{$role_text}",
                    'type' => 'link'
                ];
            } elseif ($needs_update) {
                // Update roles
                $existing_links[$user_found_index]['lead_author'] = $is_lead_author;
                $existing_links[$user_found_index]['co_author'] = $is_co_author;
                $updated_this_post = true;
                $stats['linked']++;
                
                $roles = [];
                if ($is_lead_author) $roles[] = 'Lead Author';
                if ($is_co_author) $roles[] = 'Co-Author';
                $role_text = !empty($roles) ? ' (' . implode(', ', $roles) . ')' : '';
                
                $stats['success_details'][] = [
                    'message' => "UPDATED: \"{$display_name}\" → \"{$post_title}\"{$role_text}",
                    'type' => 'link'
                ];
            } else {
                $stats['already_linked']++;
            }
        } else {
            // Handle writers/experts
            $already_linked = false;
            foreach ($existing_links as $existing_link) {
                $existing_user = $existing_link['user'];
                if (is_object($existing_user)) $existing_user = $existing_user->ID;
                if (is_array($existing_user)) $existing_user = $existing_user['ID'];

                if (intval($existing_user) === intval($user_id)) {
                    $already_linked = true;
                    break;
                }
            }

            if (!$already_linked) {
                $existing_links[] = ['user' => $user_id];
                $updated_this_post = true;
                $stats['linked']++;
                
                $user_type = ($linking_type === 'writers') ? 'writer' : 'expert';
                $stats['success_details'][] = [
                    'message' => "LINKED {$user_type}: \"{$display_name}\" → \"{$post_title}\"",
                    'type' => 'link'
                ];
            } else {
                $stats['already_linked']++;
            }
        }

        if ($updated_this_post) {
            update_field($field_name, $existing_links, $post_id);
            do_action('acf/save_post', $post_id);
        }
    }

    wp_send_json_success($stats);
}

// Status check handler
add_action('wp_ajax_check_content_linking_status', 'check_content_linking_status_callback');
function check_content_linking_status_callback()
{
    check_ajax_referer('content_linking_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $linking_type = sanitize_text_field($_POST['linking_type']);

    try {
        // Get fresh API data for status check
        $api_data = fetch_api_data($linking_type);
        if (is_wp_error($api_data)) {
            wp_send_json_error(['message' => 'API Error: ' . $api_data->get_error_message()]);
        }

        list($content_lookup, $user_lookup) = build_lookup_tables($linking_type);

        $type_labels = [
            'writers' => 'Writers',
            'experts' => 'Experts', 
            'publications' => 'Publication Authors'
        ];

        $message = sprintf(
            'Found %d %s API records to process. %d content items found, %d users found.',
            count($api_data),
            $type_labels[$linking_type],
            count($content_lookup),
            count($user_lookup)
        );

        wp_send_json_success(['message' => $message]);
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Exception: ' . $e->getMessage()]);
    }
}
