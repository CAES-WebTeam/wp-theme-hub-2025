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
        <h1>Publication API Tool</h1>
        <p>Click the button below to access the Publications API, validate its JSON response, and count the number of publications fetched.</p>

        <hr>

        <h2>Access Publication Data</h2>
        <p>This will fetch publication data from the API and display the count.</p>
        <button class="button button-primary" id="fetch-publications-btn">Validate API</button>
        <div id="fetch-publications-log" class="log-area"></div>

        <hr>

        <h2>Compare Publication Data</h2>
        <p>This will fetch publication data from the API and compare it against publications in your WordPress database (post type "Publication").</p>
        <button class="button button-secondary" id="compare-publications-btn">Compare Publications</button>
        <div id="compare-publications-log" class="log-area"></div>

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

            // Event listener for the "Fetch Publications" button
            $('#fetch-publications-btn').on('click', function() {
                const $button = $(this); // The clicked button element
                const $logArea = $('#fetch-publications-log'); // The log display area

                $logArea.empty(); // Clear previous logs
                $button.prop('disabled', true).text('Fetching...'); // Disable button and change text
                setLogAreaClass($logArea, 'info'); // Set log area to 'info' state
                appendLog($logArea, 'Initiating API request...');

                // Perform AJAX request to the WordPress backend
                $.ajax({
                    url: '<?php echo esc_js($ajax_url); ?>', // WordPress AJAX handler URL
                    type: 'POST',
                    data: {
                        action: 'fetch_publications_data', // The AJAX action to trigger in PHP
                        nonce: '<?php echo esc_js($nonce); ?>', // Security nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // If the AJAX call was successful (PHP returned wp_send_json_success)
                            appendLog($logArea, response.data.message, 'success');
                            if (response.data.log && response.data.log.length > 0) {
                                response.data.log.forEach(msg => appendLog($logArea, msg, 'detail'));
                            }
                            setLogAreaClass($logArea, 'success');
                        } else {
                            // If the AJAX call failed (PHP returned wp_send_json_error)
                            appendLog($logArea, `Error: ${response.data}`, 'error');
                            setLogAreaClass($logArea, 'error');
                        }
                        $button.prop('disabled', false).text('Validate API'); // Re-enable button
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        // Handle network or server errors for the AJAX request
                        appendLog($logArea, `AJAX Error: ${textStatus} - ${errorThrown}`, 'error');
                        setLogAreaClass($logArea, 'error');
                        $button.prop('disabled', false).text('Validate API'); // Re-enable button
                    }
                });
            });

            // Event listener for the "Compare Publications" button
            $('#compare-publications-btn').on('click', function() {
                const $button = $(this);
                const $logArea = $('#compare-publications-log');

                $logArea.empty();
                $button.prop('disabled', true).text('Comparing...');
                setLogAreaClass($logArea, 'info');
                appendLog($logArea, 'Initiating comparison...');

                $.ajax({
                    url: '<?php echo esc_js($ajax_url); ?>',
                    type: 'POST',
                    data: {
                        action: 'compare_publications_data',
                        nonce: '<?php echo esc_js($nonce); ?>',
                    },
                    success: function(response) {
                        if (response.success) {
                            appendLog($logArea, response.data.message, 'success');
                            if (response.data.log && response.data.log.length > 0) {
                                response.data.log.forEach(msg => {
                                    if (msg.startsWith('Discrepancy:')) {
                                        appendLog($logArea, msg, 'discrepancy');
                                    } else {
                                        appendLog($logArea, msg, 'detail');
                                    }
                                });
                            }
                            setLogAreaClass($logArea, 'success');
                        } else {
                            appendLog($logArea, `Error: ${response.data}`, 'error');
                            setLogAreaClass($logArea, 'error');
                        }
                        $button.prop('disabled', false).text('Compare Publications');
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        appendLog($logArea, `AJAX Error: ${textStatus} - ${errorThrown}`, 'error');
                        setLogAreaClass($logArea, 'error');
                        $button.prop('disabled', false).text('Compare Publications');
                    }
                });
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
    // check_ajax_referer('publication_api_tool_nonce', 'nonce');

    // Check if the current user has the 'manage_options' capability.
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have sufficient permissions.');
    }

    $api_url = 'https://secure.caes.uga.edu/rest/publications/getPubs?apiKey=541398745&omitPublicationText=true&bypassReturnLimit=true';
    $log = []; // Array to store log messages
    $api_publication_data = []; // Store ID and Title for API publications
    $wordpress_publication_titles = [];

    try {
        // --- Fetch API Data ---
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
            if (isset($publication['ID']) && isset($publication['TITLE'])) { // Use 'TITLE' as per your sample data
                // Store both ID and Title for comparison if needed, or just the title for title-based comparison
                $api_publication_data[$publication['ID']] = $publication['TITLE'];
            }
        }
        $log[] = "Fetched " . count($api_publication_data) . " publications from API.";

        // --- Fetch WordPress Data ---
        $args = array(
            'post_type'      => 'publication', // Assuming 'publication' is the correct post type
            'posts_per_page' => -1,          // Get all publications
            'post_status'    => 'publish',   // Only published ones
            'meta_query'     => array(
                array(
                    'key'     => 'PUBLICATION_ID', // Ensure this custom field exists and holds the API ID
                    'compare' => 'EXISTS',         // Only fetch posts that have this custom field
                ),
            ),
        );
        $wordpress_publications = get_posts($args);

        foreach ($wordpress_publications as $post) {
            $publication_id = get_post_meta($post->ID, 'PUBLICATION_ID', true);
            if (!empty($publication_id)) {
                $wordpress_publication_data[$publication_id] = $post->post_title;
            }
        }
        $log[] = "Fetched " . count($wordpress_publication_data) . " publications from WordPress database with PUBLICATION_ID.";

        // --- Compare Data ---
        $discrepancies_found = false;

        // Check for publications in API but not in WordPress (based on PUBLICATION_ID)
        foreach ($api_publication_data as $api_id => $api_title) {
            if (!array_key_exists($api_id, $wordpress_publication_data)) {
                $log[] = "Discrepancy: API publication (ID: {$api_id}, Title: '{$api_title}') is NOT found in WordPress by PUBLICATION_ID.";
                $discrepancies_found = true;
            } else {
                // Optional: You could also compare titles if you want to ensure they match for existing IDs
                if ($api_title !== $wordpress_publication_data[$api_id]) {
                    $log[] = "Discrepancy: API publication (ID: {$api_id}, Title: '{$api_title}') has a differing title in WordPress ('{$wordpress_publication_data[$api_id]}').";
                    $discrepancies_found = true;
                }
            }
        }

        // Check for publications in WordPress but not in API (based on PUBLICATION_ID)
        $api_ids_flat = array_keys($api_publication_data);
        foreach ($wordpress_publication_data as $wp_publication_id => $wp_title) {
            if (!in_array($wp_publication_id, $api_ids_flat)) {
                $log[] = "Discrepancy: WordPress publication (PUBLICATION_ID: {$wp_publication_id}, Title: '{$wp_title}') is NOT found in API data.";
                $discrepancies_found = true;
            }
        }

        $message = "Comparison complete. " . ( $discrepancies_found ? "Discrepancies found. See log for details." : "No discrepancies found." );

        wp_send_json_success([
            'message' => $message,
            'log' => $log,
        ]);

    } catch (Exception $e) {
        error_log('Publication API Comparison Tool Error: ' . $e->getMessage());
        wp_send_json_error('Comparison Error: ' . $e->getMessage());
    }
}