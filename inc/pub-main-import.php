<?php

// Add admin menu page
add_action('admin_menu', 'publication_api_tool_menu_page');

/**
 * Registers the admin menu page for the Publication API Tool.
 */
function publication_api_tool_menu_page() {
    add_management_page(
        'Publication Import Tool', // Page title
        'Import Publications',      // Menu title
        'manage_options',       // Capability required to access
        'publication-api-tool', // Menu slug
        'publication_api_tool_render_page' // Function to render the page content
    );
}

/**
 * Renders the content of the admin page, including the button and log area.
 */

function publication_api_tool_render_page() {
    // Generate a nonce for security. This is crucial for AJAX requests.
    $nonce = wp_create_nonce('publication_api_tool_nonce');
    // Get the AJAX URL for WordPress.
    $ajax_url = admin_url('admin-ajax.php');
    ?>
    <div class="wrap">
        <h1>Publication Import Tools</h1>
        <p>Useful tools for pulling in publications data from the CAES API</p>

        <hr>

        <h2>Execute Migration</h2>
        <p><strong>WARNING:</strong> This will create and update posts in your WordPress database. Make sure you have a backup before proceeding.</p>
        <p>Publications will be processed in batches of 10. The process will continue automatically until all publications are processed.</p>
        
        <!-- Migration Options -->
        <div class="migration-options">
            <h4>Migration Options</h4>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="specific-publication-id">Specific Publication ID</label></th>
                    <td>
                        <input type="text" id="specific-publication-id" class="regular-text" placeholder="e.g., 12345" />
                        <p class="description">Leave empty to import all publications, or enter a specific Publication ID to import only that publication.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="starting-batch">Starting Batch Number</label></th>
                    <td>
                        <input type="number" id="starting-batch" class="small-text" value="1" min="1" />
                        <p class="description">Specify which batch to start from. Useful for resuming interrupted migrations.</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <button class="button button-primary" id="execute-migration-btn">Execute Migration</button>
        <div id="execute-migration-log" class="log-area"></div>
        <div id="migration-progress" class="migration-progress" style="display: none;">
            <h4>Migration Progress</h4>
            <div class="progress-stats">
                <span id="total-created">Created: 0</span> |
                <span id="total-updated">Updated: 0</span> |
                <span id="total-skipped">Skipped: 0</span> |
                <span id="total-errors">Errors: 0</span> |
                <span id="total-processed">Processed: 0 of 0</span>
            </div>
        </div>

    </div>
    <style>
        /* Styling similar to your example for log readability */
        .log-area {
            margin-top: 15px;
            padding: 10px;
            background-color: #f0f0f0;
            border: 1px solid #ccc;
            max-height: 300px;
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
        .log-area .log-discrepancy {
            color: #FF8C00; /* Orange for discrepancies */
            font-weight: bold;
        }
        .log-area .log-create {
            color: #228B22; /* Forest Green for new posts */
            font-weight: bold;
        }
        .log-area .log-update {
            color: #4169E1; /* Royal Blue for updates */
            font-weight: bold;
        }
        .log-area .log-skip {
            color: #696969; /* Dim Gray for skipped items */
        }
        .migration-progress {
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
        .migration-options {
            margin: 20px 0;
            padding: 15px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .migration-options h4 {
            margin-top: 0;
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
                created: 0,
                updated: 0,
                skipped: 0,
                errors: 0,
                processed: 0,
                total: 0
            };

            // Event listener for the "Execute Migration" button
            $('#execute-migration-btn').on('click', function() {
                const $button = $(this);
                const $logArea = $('#execute-migration-log');
                
                 // Reset running totals
                runningTotals = { created: 0, updated: 0, skipped: 0, errors: 0, processed: 0, total: 0 };
                
                // Show progress area
                $('#migration-progress').show();
                updateProgressDisplay();

                // Get starting batch number
                const startingBatch = parseInt($('#starting-batch').val()) || 1;
                
                // Start with specified batch
                executeMigrationBatch(startingBatch, $button, $logArea);
            });

            // Function to update the progress display
            function updateProgressDisplay() {
                $('#total-created').text(`Created: ${runningTotals.created}`);
                $('#total-updated').text(`Updated: ${runningTotals.updated}`);
                $('#total-skipped').text(`Skipped: ${runningTotals.skipped}`);
                $('#total-errors').text(`Errors: ${runningTotals.errors}`);
                $('#total-processed').text(`Processed: ${runningTotals.processed} of ${runningTotals.total}`);
            }

            // Function to handle batch processing
            function executeMigrationBatch(batchNumber, $button, $logArea) {
                if (batchNumber === 1 || batchNumber === parseInt($('#starting-batch').val())) {
                    $logArea.empty(); // Clear logs only on first batch or starting batch
                    setLogAreaClass($logArea, 'info');
                }
                
                $button.prop('disabled', true).text(`Processing batch ${batchNumber}...`);
                appendLog($logArea, `Starting batch ${batchNumber}...`);

                // Get specific publication ID if provided
                const specificPublicationId = $('#specific-publication-id').val().trim();

                $.ajax({
                    url: '<?php echo esc_js($ajax_url); ?>',
                    type: 'POST',
                    data: {
                        action: 'execute_migration',
                        nonce: '<?php echo esc_js($nonce); ?>',
                        batch: batchNumber,
                        specific_publication_id: specificPublicationId
                    },
                    success: function(response) {
                        if (response.success) {

                            // Update running totals
                            runningTotals.created += response.data.stats.posts_created;
                            runningTotals.updated += response.data.stats.posts_updated;
                            runningTotals.skipped += response.data.stats.posts_skipped;
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
                                    if (msg.startsWith('CREATE:')) logType = 'create';
                                    else if (msg.startsWith('UPDATE:')) logType = 'update';
                                    else if (msg.startsWith('SKIP:')) logType = 'skip';
                                    else if (msg.startsWith('ERROR:')) logType = 'error';
                                    else if (msg.startsWith('SUCCESS:')) logType = 'success';
                                    else if (msg.startsWith('Warning:')) logType = 'discrepancy';
                                    else if (msg.includes('No updates needed')) logType = 'info';
                                    appendLog($logArea, msg, logType);
                                });
                            }
                            
                            // Check if there are more batches to process
                            if (response.data.has_more_batches && !specificPublicationId) {
                                appendLog($logArea, `Batch ${batchNumber} complete. Starting next batch in 1 second...`, 'info');
                                // Automatically continue to next batch after a short delay
                                setTimeout(() => {
                                    executeMigrationBatch(batchNumber + 1, $button, $logArea);
                                }, 1000); // 1 second delay between batches
                            } else {
                                // All batches complete or specific publication processed
                                appendLog($logArea, '', 'info'); // Empty line
                                if (specificPublicationId) {
                                    appendLog($logArea, '=== SPECIFIC PUBLICATION MIGRATION COMPLETE ===', 'success');
                                } else {
                                    appendLog($logArea, '=== MIGRATION COMPLETE ===', 'success');
                                }
                                appendLog($logArea, `Final totals: Created ${runningTotals.created}, Updated ${runningTotals.updated}, Skipped ${runningTotals.skipped}, Errors ${runningTotals.errors}`, 'success');
                                setLogAreaClass($logArea, 'success');
                                $button.prop('disabled', false).text('Execute Migration');
                            }
                            
                        } else {
                            // Handle error
                            appendLog($logArea, `Batch ${batchNumber} failed: ${response.data}`, 'error');
                            setLogAreaClass($logArea, 'error');
                            $button.prop('disabled', false).text('Execute Migration');
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        appendLog($logArea, `Batch ${batchNumber} AJAX failed: ${textStatus}`, 'error');
                        setLogAreaClass($logArea, 'error');
                        $button.prop('disabled', false).text('Execute Migration');
                    }
                });
            }

        });
    </script>
    <?php
}

// --- AJAX Handlers ---
// These functions run on the server when the AJAX action is triggered.

// Register the AJAX handler for actual migration
add_action('wp_ajax_execute_migration', 'publication_api_tool_execute_migration');

/**
 * Handles the AJAX request to perform the actual data migration.
 */
function publication_api_tool_execute_migration() {
    // Verify the nonce for security.
    if (!check_ajax_referer('publication_api_tool_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed: Invalid nonce.', 403);
    }

    // Check if the current user has the 'manage_options' capability.
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied: You do not have sufficient permissions.', 403);
    }

    $log = []; // Array to store log messages
    $api_url = 'https://secure.caes.uga.edu/rest/publications/getPubs?apiKey=541398745&omitPublicationText=false&bypassReturnLimit=true';
    
    // Same field mapping as dry run
    $field_mapping = [
        'ID' => 'publication_id',
        // 'SERIES_ID' => 'series_id',
        // 'TYPE_ID' => 'publication_type',
        // 'DIVISION_ID' => 'division',
        // 'LANGUAGE_ID' => 'language',
        // 'STATE_ISSUE_ID' => 'state_issue',
        // 'CATEGORY_ID' => 'category_id', 
        // 'UPDATER_ID' => 'updater_id',
        // 'CAES_TRANSLATOR_ID' => 'translator',
        // 'PUBLICATION_NUMBER' => 'publication_number',
        'TITLE' => 'title',
        // 'SHORT_SUMMARY' => 'short_summary',
        // 'ABSTRACT' => 'summary',
        // 'NOTES' => 'notes',
        // 'DATE_CREATED' => 'post_date',
        // 'DATE_LAST_UPDATED' => 'post_modified',
        // 'AUTOMATIC_SUNSET_DATE' => 'sunset_date',
        // 'VERSION' => 'version',
        'PUBLICATION_TEXT' => 'post_content'
        // 'PRIMARY_IMAGE_PATH' => 'primary_image_path',
        // 'THUMBNAIL_IMAGE_PATH' => 'thumbnail_image_path',
        // 'IS_COMMERCIAL_PUBLICATION' => 'is_commercial',
        // 'IS_FEATURED_PUBLICATION' => 'is_featured'
    ];

    $stats = [
        'posts_created' => 0,
        'posts_updated' => 0,
        'posts_skipped' => 0,
        'posts_with_errors' => 0
    ];

    // Batch processing parameters
    $batch_size = 10;
    $current_batch_number = isset($_POST['batch']) ? intval($_POST['batch']) : 1;
    $start_index = ($current_batch_number - 1) * $batch_size;
    
    // Check for specific publication ID
    $specific_publication_id = isset($_POST['specific_publication_id']) ? trim($_POST['specific_publication_id']) : '';
    
    // Transient key for storing API data
    $api_data_transient_key = 'publication_api_migration_data';
    $api_timestamp_transient_key = 'publication_api_migration_timestamp';
    
    // Turn off KSES filters so <style> and other tags import correctly
    kses_remove_filters();

    try {
        // --- Fetch or Retrieve Cached API Data ---
        $decoded_API_response = get_transient($api_data_transient_key);
        $api_fetch_timestamp = get_transient($api_timestamp_transient_key);
        
        if ($decoded_API_response === false || $current_batch_number === 1) {
            // Either no cached data exists, or this is batch 1 (fresh start)
            $log[] = "Fetching fresh publication data from API...";
            
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

        // Handle specific publication ID filtering
        if (!empty($specific_publication_id)) {
            $filtered_publications = array_filter($decoded_API_response, function($pub) use ($specific_publication_id) {
                return isset($pub['ID']) && (string)$pub['ID'] === $specific_publication_id;
            });
            
            if (empty($filtered_publications)) {
                throw new Exception("Publication with ID '{$specific_publication_id}' not found in API data.");
            }
            
            $decoded_API_response = array_values($filtered_publications); // Re-index array
            $log[] = "Filtered to specific publication ID: {$specific_publication_id}";
            
            // For specific publication, process all in one batch
            $batch_of_publications = $decoded_API_response;
            $actual_batch_size = count($batch_of_publications);
        } else {
            $total_publications = count($decoded_API_response);
            
            // Extract batch
            $batch_of_publications = array_slice($decoded_API_response, $start_index, $batch_size);
            $actual_batch_size = count($batch_of_publications);
            
            $log[] = "Processing batch {$current_batch_number}: publications " . ($start_index + 1) . " to " . ($start_index + $actual_batch_size) . " of {$total_publications}";
        }

        // --- Get existing WordPress publications for comparison ---
        $log[] = "Fetching existing WordPress publications...";
        $args = array(
            'post_type'      => 'publications',
            'posts_per_page' => -1,
            'post_status'    => array('publish', 'draft', 'private')
        );
        $wordpress_posts = get_posts($args);

        // Create lookup array by publication_id
        $wordpress_lookup = [];
        foreach ($wordpress_posts as $post) {
            $publication_id = get_field('publication_id', $post->ID);
            if ($publication_id) {
                $wordpress_lookup[$publication_id] = $post;
            }
        }

        $log[] = "Found " . count($wordpress_posts) . " existing WordPress publications for comparison.";

        // --- Process each publication in the batch ---
        $log[] = "Starting to process individual publications...";
        
        foreach ($batch_of_publications as $batch_index => $one_api_publication) {
            if (!isset($one_api_publication['ID'])) {
                $log[] = "SKIP: API publication at batch index {$batch_index} missing ID field.";
                $stats['posts_with_errors']++;
                continue;
            }

            $api_id = (string) $one_api_publication['ID'];

            if (!isset($one_api_publication['TITLE'])) {
                $log[] = "SKIP: API publication number {$api_id} missing TITLE field.";
                $stats['posts_with_errors']++;
                continue;
            }

            $api_title = (string) $one_api_publication['TITLE'];

            // Check if post exists in WordPress
            if (isset($wordpress_lookup[$api_id])) {
                // POST EXISTS - UPDATE LOGIC
                $existing_post = $wordpress_lookup[$api_id];
                $log[] = "Processing existing post: '{$api_title}' (Publication ID: {$api_id})";
                
                try {
                    $fields_updated = 0;
                    $post_data_changed = false;
                    
                    // Prepare post data for WordPress update
                    $post_data = array(
                        'ID'          => $existing_post->ID, // CRITICAL: WordPress needs this to know which post to update
                        'post_type'   => 'publications',
                        'post_status' => 'publish'
                    );
                    
                    // Process each field according to our mapping
                    foreach ($field_mapping as $api_field => $wp_field) {
                        if (!isset($one_api_publication[$api_field])) {
                            continue; // Skip if API field doesn't exist
                        }
                        
                        $api_value = $one_api_publication[$api_field];
                        
                        // Handle WordPress core fields
                        if ($wp_field === 'title') {
                            if (trim($api_value) != trim($existing_post->post_title)) {
                                $post_data['post_title'] = $api_value;
                                $post_data_changed = true;
                                $fields_updated++;
                            }
                        } 
                        elseif ($wp_field === 'post_content') {
                            // Clean and process the content
                            $cleaned_api_content = clean_wysiwyg_content_pubs_version($api_value);
                            $cleaned_api_content = strip_line_breaks_preserve_html_pubs_version($cleaned_api_content);
                            
                            if (trim($cleaned_api_content) != trim($existing_post->post_content)) {
                            	$post_data['post_content'] = $cleaned_api_content;
                                $post_data_changed = true;
                                $fields_updated++;
                            } else {

                            }
                        }
                        elseif ($wp_field === 'post_date') {
                            $converted_date = convert_api_date_to_wordpress($api_value);
                            if ($converted_date !== null) {
                                if ($converted_date != $existing_post->post_date) {
                                    $post_data['post_date'] = $converted_date;
                                    $post_data_changed = true;
                                    $fields_updated++;
                                }
                            } else {
                                $log[] = "  Warning: Failed to convert date field '{$api_field}' with value '{$api_value}' - skipping field";
                            }
                        }
                        elseif ($wp_field === 'post_modified') {
                            $converted_date = convert_api_date_to_wordpress($api_value);
                            if ($converted_date !== null) {
                                if ($converted_date != $existing_post->post_modified) {
                                    $post_data['post_modified'] = $converted_date;
                                    $post_data_changed = true;
                                    $fields_updated++;
                                }
                            } else {
                                $log[] = "  Warning: Failed to convert date field '{$api_field}' with value '{$api_value}' - skipping field";
                            }
                        }
                    }
                    
                    // Update the WordPress core fields only if there were changes
                    if ($post_data_changed) {
                        $updated_post_id = wp_update_post($post_data);
                        
                        if (is_wp_error($updated_post_id)) {
                            throw new Exception('Failed to update post core fields: ' . $updated_post_id->get_error_message());
                        }
                    }
                    
                    // Now update all ACF fields
                    foreach ($field_mapping as $api_field => $wp_field) {
                        if (!isset($one_api_publication[$api_field])) {
                            continue;
                        }
                        
                        // Skip WordPress core fields (already handled above)
                        if (in_array($wp_field, ['title', 'post_content', 'post_date', 'post_modified'])) {
                            continue;
                        }
                        
                        $api_value = $one_api_publication[$api_field];
                        
                        // Clean content fields if they contain HTML/text content
                        if (in_array($wp_field, ['short_summary', 'summary', 'notes'])) {
                            $api_value = clean_wysiwyg_content_pubs_version($api_value);
                            $api_value = strip_line_breaks_preserve_html_pubs_version($api_value);
                        }
                        
                        // Get existing ACF field value
                        $existing_acf_value = get_field($wp_field, $existing_post->ID);
                        
                        // Compare ACF field to see if update is needed
                        $acf_field_identical = (trim($api_value) == trim($existing_acf_value)) || 
                                             (is_null($api_value) && is_null($existing_acf_value)) ||
                                             (empty($api_value) && empty($existing_acf_value));

                        if (!$acf_field_identical) {
                            // Update ACF field
                            $acf_updated = update_field($wp_field, $api_value, $existing_post->ID);
                            
                            if ($acf_updated) {
                                $fields_updated++;
                            } else {
                                $log[] = "  Warning: Failed to update ACF field '{$wp_field}' for post ID {$existing_post->ID}";
                            }
                        }
                    }
                    
                    if ($fields_updated > 0) {
                        $log[] = "  SUCCESS: Updated post with WordPress ID {$existing_post->ID} ({$fields_updated} fields updated)";
                        $stats['posts_updated']++;
                    } else {
                        $log[] = "  No updates needed for publication '{$api_title}' (WordPress ID: {$existing_post->ID})";
                        $stats['posts_skipped']++;
                    }
                    
                } catch (Exception $e) {
                    $log[] = "  ERROR: Failed to update post '{$api_title}': " . $e->getMessage();
                    $stats['posts_with_errors']++;
                }
                
            } else {
                // POST DOESN'T EXIST - CREATE LOGIC
                $log[] = "Creating new post: '{$api_title}' (ID: {$api_id})";
                
                try {
                    // Prepare post data for WordPress
                    $post_data = array(
                        'post_type'   => 'publications',
                        'post_status' => 'publish',
                        'meta_input'  => array() // We'll populate this with ACF fields
                    );
                    
                    // Process each field according to our mapping
                    foreach ($field_mapping as $api_field => $wp_field) {
                        if (!isset($one_api_publication[$api_field])) {
                            continue; // Skip if API field doesn't exist
                        }
                        
                        $api_value = $one_api_publication[$api_field];
                        
                        // Handle WordPress core fields
                        if ($wp_field === 'title') {
                            $post_data['post_title'] = $api_value;
                        } 
                        elseif ($wp_field === 'post_content') {
                            // Clean and process the content
                            $cleaned_api_content = clean_wysiwyg_content_pubs_version($api_value);
                            $cleaned_api_content = strip_line_breaks_preserve_html_pubs_version($cleaned_api_content);
                            $post_data['post_content'] = $cleaned_api_content;
                        }
                        elseif ($wp_field === 'post_date') {
                            $converted_date = convert_api_date_to_wordpress($api_value);
                            if ($converted_date !== null) {
                                $post_data['post_date'] = $converted_date;
                            } else {
                                $log[] = "  Warning: Failed to convert date field '{$api_field}' with value '{$api_value}' - skipping field";
                            }
                        }
                        elseif ($wp_field === 'post_modified') {
                            $converted_date = convert_api_date_to_wordpress($api_value);
                            if ($converted_date !== null) {
                                $post_data['post_modified'] = $converted_date;
                            } else {
                                $log[] = "  Warning: Failed to convert date field '{$api_field}' with value '{$api_value}' - skipping field";
                            }
                        }
                        else {
                            // Handle ACF fields - clean content fields
                            if (in_array($wp_field, ['short_summary', 'summary', 'notes'])) {
                                $api_value = clean_wysiwyg_content_pubs_version($api_value);
                                $api_value = strip_line_breaks_preserve_html_pubs_version($api_value);
                            }
                            $post_data['meta_input'][$wp_field] = $api_value;
                        }
                    }
                    
                    // Create the post
                    $new_post_id = wp_insert_post($post_data);
                    
                    if (is_wp_error($new_post_id)) {
                        throw new Exception('Failed to create post: ' . $new_post_id->get_error_message());
                    }
                    
                    $log[] = "  SUCCESS: Created post with WordPress ID {$new_post_id}";
                    $stats['posts_created']++;
                    
                } catch (Exception $e) {
                    $log[] = "  ERROR: Failed to create post '{$api_title}': " . $e->getMessage();
                    $stats['posts_with_errors']++;
                }
            }
        }

        // --- End of processing loop ---
        
        if (!empty($specific_publication_id)) {
            // For specific publication, we're done
            $has_more_batches = false;
            $total_publications = count($decoded_API_response);
            $processed_so_far = $total_publications;
        } else {
            // Calculate if there are more batches
            $total_publications = count($decoded_API_response);
            $total_remaining = $total_publications - ($start_index + $actual_batch_size);
            $has_more_batches = $total_remaining > 0;
            $processed_so_far = $start_index + $actual_batch_size;
        }
        
        // Clean up transients if this is the last batch
        if (!$has_more_batches) {
            delete_transient($api_data_transient_key);
            delete_transient($api_timestamp_transient_key);
            $log[] = "Migration complete - cleared cached API data.";
        }
        
        // --- Final Summary for this batch ---
        $log[] = ""; // Empty line for readability
        if (!empty($specific_publication_id)) {
            $log[] = "=== SPECIFIC PUBLICATION PROCESSING COMPLETE ===";
        } else {
            $log[] = "=== BATCH {$current_batch_number} COMPLETE ===";
        }
        $log[] = "Posts CREATED in this batch: " . $stats['posts_created'];
        $log[] = "Posts UPDATED in this batch: " . $stats['posts_updated'];
        $log[] = "Posts SKIPPED in this batch: " . $stats['posts_skipped'];
        $log[] = "Posts with ERRORS in this batch: " . $stats['posts_with_errors'];
        
        if (!empty($specific_publication_id)) {
            $log[] = "Specific publication processing complete.";
        } else {
            if ($has_more_batches) {
                $log[] = "Remaining publications to process: " . ($total_publications - $processed_so_far);
            } else {
                $log[] = "All publications have been processed!";
            }
        }

        $message_suffix = !empty($specific_publication_id) ? " for publication ID {$specific_publication_id}" : " {$current_batch_number}";
        $message = "Batch{$message_suffix} complete. Created {$stats['posts_created']}, updated {$stats['posts_updated']}, skipped {$stats['posts_skipped']}, errors {$stats['posts_with_errors']}.";

        wp_send_json_success([
            'message' => $message,
            'log' => $log,
            'stats' => $stats,
            'batch_number' => $current_batch_number,
            'has_more_batches' => $has_more_batches,
            'total_publications' => !empty($specific_publication_id) ? count($decoded_API_response) : $total_publications,
            'processed_so_far' => $processed_so_far
        ]);

    } catch (Exception $e) {
        error_log('Publication API Migration Error: ' . $e->getMessage());
        wp_send_json_error('Error during migration batch ' . $current_batch_number . ': ' . $e->getMessage());
    }
}

// Optional helper function to manually clear the cached data if needed
function publication_api_tool_clear_cache() {
    delete_transient('publication_api_migration_data');
    delete_transient('publication_api_migration_timestamp');
    return 'API cache cleared successfully.';
}

/**
 * Helper function to convert API date format to WordPress format
 * API format: "June, 02 2006 14:26:07"
 * WordPress format: "2006-06-02 14:26:07"
 */
function convert_api_date_to_wordpress($api_date) {
    if (empty($api_date)) {
        return null;
    }
    
    try {
        // Create DateTime object from API format
        $date = DateTime::createFromFormat('F, d Y H:i:s', $api_date);
        
        if ($date === false) {
            // If that fails, try without the comma
            $date = DateTime::createFromFormat('F d Y H:i:s', str_replace(',', '', $api_date));
        }
        
        if ($date === false) {
            throw new Exception("Unable to parse date: " . $api_date);
        }
        
        // Return in WordPress format
        return $date->format('Y-m-d H:i:s');
        
    } catch (Exception $e) {
        error_log('Date conversion error: ' . $e->getMessage());
        return null;
    }
}

// Helper function to clean up artifacts from the rich text editor
function clean_wysiwyg_content_pubs_version($content)
{
    if (empty($content)) {
        return $content;
    }

    // Clean unwanted characters
    $content = str_replace(["\r\n", "\r", '&#13;', '&#013;', '&amp;#13;', '&#x0D;', '&#x0d;'], '', $content);

    // Fix escaped forward slashes from JSON
    $content = str_replace(['<\/'], ['</'], $content);

    // Convert HTML entities
    $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Remove empty paragraphs
    $content = preg_replace('/<p>\s*<\/p>/', '', $content);

    // Trim whitespace
    $content = trim($content);

    return $content;
}

// Helper function to strip line breaks, again from the rich text editor
function strip_line_breaks_preserve_html_pubs_version($content) {
    if (empty($content)) {
        return $content;
    }
    
    // Remove all line break characters but preserve HTML <br> tags
    $content = str_replace(["\r\n", "\r", "\n"], '', $content);
    
    return $content;
}