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

        <h2>Verify API</h2>
        <p>This will fetch publication data from the API and display the count.</p>
        <button class="button button-primary" id="fetch-publications-btn">Validate API</button>
        <div id="fetch-publications-log" class="log-area"></div>

        <hr>

        <h2>Compare Publication Data</h2>
        <p>This will fetch publication data from the API and compare it against publications in your WordPress database (post type "Publication").</p>
        <button class="button button-secondary" id="compare-publications-btn">Compare Publications</button>
        <div id="compare-publications-log" class="log-area"></div>

        <hr>

        <h2>Dry Run Migration</h2>
        <p>This will simulate the data migration process, showing what posts would be created and what data would be updated without actually making any changes.</p>
        <button class="button button-secondary" id="dry-run-migration-btn">Run Dry Run Migration</button>
        <div id="dry-run-migration-log" class="log-area"></div>

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

            // Centralized AJAX handler function for reusability and error handling
            function performAjaxCall(action, nonce, $button, $logArea, buttonText) {
                $logArea.empty(); // Clear previous logs
                $button.prop('disabled', true).text('Processing...'); // Disable button and change text
                setLogAreaClass($logArea, 'info'); // Set log area to 'info' state
                appendLog($logArea, 'Initiating AJAX request for ' + action + '...');

                $.ajax({
                    url: '<?php echo esc_js($ajax_url); ?>', // WordPress AJAX handler URL
                    type: 'POST',
                    data: {
                        action: action, // The AJAX action to trigger in PHP
                        nonce: nonce,   // Security nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // PHP returned wp_send_json_success
                            appendLog($logArea, response.data.message, 'success');
                            if (response.data.log && response.data.log.length > 0) {
                                response.data.log.forEach(msg => {
                                    let logType = 'detail';
                                    if (msg.startsWith('Discrepancy:')) {
                                        logType = 'discrepancy';
                                    } else if (msg.startsWith('CREATE:')) {
                                        logType = 'create';
                                    } else if (msg.startsWith('UPDATE:')) {
                                        logType = 'update';
                                    } else if (msg.startsWith('SKIP:')) {
                                        logType = 'skip';
                                    }
                                    appendLog($logArea, msg, logType);
                                });
                            }
                            setLogAreaClass($logArea, 'success');

                            // Log WordPress post details if available (for 'Compare' action)
                            if (response.data.wordpress_post_details) {
                                console.log('All WordPress Post Details:', response.data.wordpress_post_details);
                                appendLog($logArea, 'WordPress Post Details logged to console.', 'info');
                            }

                        } else {
                            // PHP returned wp_send_json_error
                            let errorMessage = 'An unknown error occurred.';
                            if (response.data) {
                                // response.data from wp_send_json_error is typically a string
                                // or an object if you passed an array to wp_send_json_error.
                                // In our PHP, we ensure it's a string, so check for string.
                                errorMessage = response.data;
                            }
                            appendLog($logArea, `Server Error: ${errorMessage}`, 'error');
                            setLogAreaClass($logArea, 'error');
                            console.error('AJAX Server Error Response:', response); // Log the full response object for deeper inspection
                        }
                        $button.prop('disabled', false).text(buttonText); // Re-enable button
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        // This fires for HTTP errors (e.g., 404, 500, no response)
                        let detailedError = `HTTP Status: ${jqXHR.status} (${textStatus}) - ${errorThrown}`;
                        let responseText = jqXHR.responseText ? `Response: ${jqXHR.responseText.substring(0, 500)}...` : 'No response text.';

                        // Try to parse JSON if responseText looks like JSON and status isn't 200
                        if (jqXHR.responseText && jqXHR.responseText.trim().startsWith('{')) {
                            try {
                                const errorResponse = JSON.parse(jqXHR.responseText);
                                if (errorResponse.data) {
                                    detailedError += ` | Server Message: ${errorResponse.data}`;
                                } else if (errorResponse.message) { // Sometimes a different structure
                                    detailedError += ` | Server Message: ${errorResponse.message}`;
                                }
                            } catch (e) {
                                // Not valid JSON, keep as is
                            }
                        }

                        appendLog($logArea, `AJAX Request Failed: ${detailedError}`, 'error');
                        appendLog($logArea, responseText, 'error'); // Show raw response if available
                        setLogAreaClass($logArea, 'error');
                        console.error('Full jQuery AJAX Error Object:', jqXHR); // Crucial for debugging
                        $button.prop('disabled', false).text(buttonText); // Re-enable button
                    }
                });
            }

            // Event listener for the "Fetch Publications" button
            $('#fetch-publications-btn').on('click', function() {
                const $button = $(this);
                const $logArea = $('#fetch-publications-log');
                performAjaxCall('fetch_publications_data', '<?php echo esc_js($nonce); ?>', $button, $logArea, 'Validate API');
            });

            // Event listener for the "Compare Publications" button
            $('#compare-publications-btn').on('click', function() {
                const $button = $(this);
                const $logArea = $('#compare-publications-log');
                performAjaxCall('compare_publications_data', '<?php echo esc_js($nonce); ?>', $button, $logArea, 'Compare Publications');
            });

            // Event listener for the "Dry Run Migration" button
            $('#dry-run-migration-btn').on('click', function() {
                const $button = $(this);
                const $logArea = $('#dry-run-migration-log');
                performAjaxCall('dry_run_migration', '<?php echo esc_js($nonce); ?>', $button, $logArea, 'Run Dry Run Migration');
            });

        });
    </script>
    <?php
}

// --- AJAX Handlers ---
// These functions run on the server when the AJAX action is triggered.

// Register the AJAX handler for logged-in users
add_action('wp_ajax_fetch_publications_data', 'publication_api_tool_fetch_publications');

/**
 * Handles the AJAX request to fetch and process publication data from the API.
 */
function publication_api_tool_fetch_publications() {
    // Verify the nonce for security.
    check_ajax_referer('publication_api_tool_nonce', 'nonce');

    // Check if the current user has the 'manage_options' capability.
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have sufficient permissions.');
    }

    $api_url = 'https://secure.caes.uga.edu/rest/publications/getPubs?apiKey=541398745&omitPublicationText=true&bypassReturnLimit=true';
    $decoded_API_response = null;
    $log = []; // Array to store log messages

    try {
        // Fetch data from the external API using WordPress's HTTP API.
        $response = wp_remote_get($api_url);

        // Check for WordPress HTTP API errors (e.g., network issues).
        if (is_wp_error($response)) {
            throw new Exception('API Request Failed: ' . $response->get_error_message());
        }

        // Get the raw JSON body from the API response.
        $raw_JSON = wp_remote_retrieve_body($response);

        // Attempt to decode the JSON string into a PHP array.
        $decoded_API_response = json_decode($raw_JSON, true);

        // Check for JSON decoding errors.
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON decode error from API: ' . json_last_error_msg());
        }

        // Validate that the decoded response is an array.
        // The API returns an array of publication objects.
        if (!is_array($decoded_API_response)) {
            throw new Exception('Invalid API response format: Expected an array.');
        }

        $log[] = "API response successfully fetched and JSON validated.";

        // Count publications using the 'ID' field.
        $publication_count = 0;
        foreach ($decoded_API_response as $publication) {
            if (isset($publication['ID'])) {
                $publication_count++;
            }
        }

        // Send a success response back to the JavaScript.
        wp_send_json_success([
            'message' => "Successfully fetched {$publication_count} publications.",
            'publication_count' => $publication_count,
            'log' => $log,
        ]);

    } catch (Exception $e) {
        // Log the error for debugging purposes (check your WordPress debug.log).
        error_log('Publication API Tool Error: ' . $e->getMessage());
        // Send an error response back to the JavaScript.
        wp_send_json_error('API Error: ' . $e->getMessage());
    }
}


// Register the AJAX handler for logged-in users for comparing data
add_action('wp_ajax_compare_publications_data', 'publication_api_tool_compare_publications');

/**
 * Handles the AJAX request to compare API publication data with WordPress data.
 */
function publication_api_tool_compare_publications() {
    // Verify the nonce for security.
    if (!check_ajax_referer('publication_api_tool_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed: Invalid nonce.', 403);
    }

    // Check if the current user has the 'manage_options' capability.
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied: You do not have sufficient permissions.', 403);
    }

    $log = []; // Array to store log messages
    $api_url = 'https://secure.caes.uga.edu/rest/publications/getPubs?apiKey=541398745&omitPublicationText=true&bypassReturnLimit=true';
    $api_publication_ids = [];
    $wordpress_publication_ids = []; // Changed to store publication ids

    try {
        // --- Fetch API Data (similar to publication_api_tool_fetch_publications) ---
        $log[] = "Attempting to fetch publication data from the API...";
        $response = wp_remote_get($api_url);

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

        foreach ($decoded_API_response as $publication) {
            if (isset($publication['ID'])) {
                $api_publication_ids[] = (string) $publication['ID']; // Ensure string for consistent comparison
            }
        }
        $log[] = "Successfully fetched " . count($api_publication_ids) . " publication IDs from the API.";

        // --- Fetch WordPress Data and ACF field ---
        $log[] = "Attempting to access publications and their 'publication_id' field in the WordPress database...";
        $args = array(
            'post_type'      => 'publications',
            'posts_per_page' => -1, // Get all publications
            'post_status' => array('publish', 'draft')
        );
        $wordpress_posts = get_posts($args);

        if (!empty($wordpress_posts)) {
            foreach ($wordpress_posts as $post) {
                // Get the ACF field 'publication_id' for each post
                $publication_id = get_field('publication_id', $post->ID);
                if ($publication_id) {
                    $wordpress_publication_ids[] = (string) $publication_id; // Ensure string for consistent comparison
                } else {
                    $log[] = "Warning: WordPress post ID '{$post->ID}' is missing the 'publication_id' ACF field.";
                }
            }
        }

        $log[] = "Successfully located " . count($wordpress_publication_ids) . " 'publication_id' records from published 'publication' posts in the WordPress database.";

        // --- Compare IDs (now comparing API IDs with WordPress publication ids) ---
        $message = "Comparison Results:";
        $discrepancies_found = false;

        // IDs present in API but not in WordPress (using publication_id)
        $api_only_ids = array_diff($api_publication_ids, $wordpress_publication_ids);
        if (!empty($api_only_ids)) {
            $discrepancies_found = true;
            $log[] = "Discrepancy: " . count($api_only_ids) . " publications found in API but no matching 'publication_id' in WordPress.";
            foreach ($api_only_ids as $id) {
                $log[] = "Discrepancy: API ID '{$id}' is missing in WordPress (by 'publication_id').";
            }
        } else {
            $log[] = "No API publications missing from WordPress (by 'publication_id').";
        }

        // IDs (publication_ids) present in WordPress but not in API
        $wordpress_only_ids = array_diff($wordpress_publication_ids, $api_publication_ids);
        if (!empty($wordpress_only_ids)) {
            $discrepancies_found = true;
            $log[] = "Discrepancy: " . count($wordpress_only_ids) . " 'publication_id' fields found in WordPress but not in API.";
            foreach ($wordpress_only_ids as $id) {
                $log[] = "Discrepancy: WordPress 'publication_id' '{$id}' is missing from API.";
            }
        } else {
            $log[] = "No WordPress 'publication_id' fields missing from API.";
        }

        if (!$discrepancies_found) {
            $log[] = "All API publication IDs match WordPress 'publication_id' fields. No discrepancies found.";
            $message = "Comparison complete: All IDs match.";
        } else {
            $message = "Comparison complete: Discrepancies found.";
        }

        wp_send_json_success([
            'message' => $message,
            'log'     => $log,
        ]);

    } catch (Exception $e) {
        error_log('Publication API Comparison Tool Error: ' . $e->getMessage());
        wp_send_json_error('Error during comparison: ' . $e->getMessage());
    }
}

// Register the AJAX handler for dry run migration
add_action('wp_ajax_dry_run_migration', 'publication_api_tool_dry_run_migration');

/**
 * Handles the AJAX request to perform a dry run migration simulation.
 */
function publication_api_tool_dry_run_migration() {
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
    
    // Field mapping array based on your table
    $field_mapping = [
        'ID' => 'publication_id',
        'SERIES_ID' => 'series_id',
        'CATEGORY_ID' => 'category_id', 
        'UPDATER_ID' => 'updater_id',
        'CAES_TRANSLATOR_ID' => 'translator',
        'PUBLICATION_NUMBER' => 'publication_number',
        'TITLE' => 'title', // WordPress post_title
        'SHORT_SUMMARY' => 'short_summary',
        'ABSTRACT' => 'summary',
        'NOTES' => 'notes',
        'DATE_CREATED' => 'post_date', // WordPress post_date
        'DATE_LAST_UPDATED' => 'post_modified', // WordPress post_modified
        'AUTOMATIC_SUNSET_DATE' => 'sunset_date',
        'VERSION' => 'version',
        'PUBLICATION_TEXT' => 'post_content', // WordPress post_content
        'PRIMARY_IMAGE_PATH' => 'primary_image_path',
        'THUMBNAIL_IMAGE_PATH' => 'thumbnail_image_path',
        'IS_COMMERCIAL_PUBLICATION' => 'is_commercial',
        'IS_FEATURED_PUBLICATION' => 'is_featured'
    ];

    $stats = [
        'posts_to_create' => 0,
        'posts_to_update' => 0,
        'posts_up_to_date' => 0,
        'posts_with_errors' => 0
    ];

    try {
        // --- Fetch API Data ---
        $log[] = "Fetching publication data from API (including full content for dry run)...";
        $response = wp_remote_get($api_url);

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

        $log[] = "Successfully fetched " . count($decoded_API_response) . " publications from API.";

        // --- Get existing WordPress publications ---
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

        $log[] = "Found " . count($wordpress_posts) . " existing WordPress publications.";
        $log[] = "Starting dry run migration simulation...";
        $log[] = ""; // Empty line for readability

        // --- Process each API publication ---
        foreach ($decoded_API_response as $index => $api_publication) {
            if (!isset($api_publication['ID'])) {
                $log[] = "SKIP: API publication at index {$index} missing ID field.";
                $stats['posts_with_errors']++;
                continue;
            }

            $api_id = (string) $api_publication['ID'];
            $api_title = isset($api_publication['TITLE']) ? $api_publication['TITLE'] : 'Untitled Publication';

            // Check if post exists in WordPress
            if (isset($wordpress_lookup[$api_id])) {
                // Post exists - check if update needed
                $existing_post = $wordpress_lookup[$api_id];
                $needs_update = false;
                $updates_needed = [];

                // Check each field for differences
                foreach ($field_mapping as $api_field => $wp_field) {
                    $api_value = isset($api_publication[$api_field]) ? $api_publication[$api_field] : '';
                    
                    if (in_array($wp_field, ['title', 'post_content', 'post_date', 'post_modified'])) {
                        // WordPress core fields
                        $wp_value = '';
                        switch ($wp_field) {
                            case 'title':
                                $wp_value = $existing_post->post_title;
                                break;
                            case 'post_content':
                                $wp_value = $existing_post->post_content;
                                break;
                            case 'post_date':
                                $wp_value = $existing_post->post_date;
                                break;
                            case 'post_modified':
                                $wp_value = $existing_post->post_modified;
                                break;
                        }
                    } else {
                        // ACF fields
                        $wp_value = get_field($wp_field, $existing_post->ID);
                        if (is_null($wp_value)) $wp_value = '';
                    }

                    // Simple comparison (you might want to make this more sophisticated)
                    if (trim($api_value) !== trim($wp_value)) {
                        $needs_update = true;
                        $updates_needed[] = $wp_field;
                    }
                }

                if ($needs_update) {
                    $log[] = "UPDATE: Post '{$api_title}' (ID: {$api_id}) needs updates in fields: " . implode(', ', $updates_needed);
                    $stats['posts_to_update']++;
                } else {
                    $log[] = "SKIP: Post '{$api_title}' (ID: {$api_id}) is up to date.";
                    $stats['posts_up_to_date']++;
                }

            } else {
                // Post doesn't exist - would create new post
                $log[] = "CREATE: New post would be created for '{$api_title}' (ID: {$api_id})";
                
                // Show what data would be populated
                $create_details = [];
                foreach ($field_mapping as $api_field => $wp_field) {
                    if (isset($api_publication[$api_field]) && !empty($api_publication[$api_field])) {
                        $value = $api_publication[$api_field];
                        // Truncate long values for display
                        if (strlen($value) > 100) {
                            $value = substr($value, 0, 100) . '...';
                        }
                        $create_details[] = "{$wp_field}: {$value}";
                    }
                }
                
                if (!empty($create_details)) {
                    $log[] = "  Data to populate: " . implode(' | ', array_slice($create_details, 0, 3));
                    if (count($create_details) > 3) {
                        $log[] = "  (" . (count($create_details) - 3) . " more fields would be populated)";
                    }
                }
                
                $stats['posts_to_create']++;
            }
        }

        // --- Summary Statistics ---
        $log[] = ""; // Empty line for readability
        $log[] = "=== DRY RUN MIGRATION SUMMARY ===";
        $log[] = "Posts to CREATE: " . $stats['posts_to_create'];
        $log[] = "Posts to UPDATE: " . $stats['posts_to_update'];
        $log[] = "Posts UP TO DATE: " . $stats['posts_up_to_date'];
        $log[] = "Posts with ERRORS: " . $stats['posts_with_errors'];
        $log[] = "Total API publications processed: " . count($decoded_API_response);

        $message = "Dry run complete. Would create {$stats['posts_to_create']} new posts and update {$stats['posts_to_update']} existing posts.";

        wp_send_json_success([
            'message' => $message,
            'log'     => $log,
            'stats'   => $stats
        ]);

    } catch (Exception $e) {
        error_log('Publication API Dry Run Migration Error: ' . $e->getMessage());
        wp_send_json_error('Error during dry run migration: ' . $e->getMessage());
    }
}