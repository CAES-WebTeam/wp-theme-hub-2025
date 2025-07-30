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

        <hr>

        <h2>Search Individual Publications</h2>
        <p>Search for specific publications by Post ID or Publication Number. Fill in one or both fields and click Search.</p>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="search-post-id">Post ID:</label></th>
                <td><input type="number" id="search-post-id" class="regular-text" placeholder="Enter WordPress Post ID" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="search-publication-number">Publication Number:</label></th>
                <td><input type="text" id="search-publication-number" class="regular-text" placeholder="Enter Publication Number" /></td>
            </tr>
        </table>
        <p class="submit">
            <button class="button button-secondary" id="search-publications-btn">Search Publications</button>
        </p>
        <div id="search-publications-log" class="log-area"></div>

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

            // Centralized AJAX handler function for reusability and error handling
            function performAjaxCall(action, nonce, $button, $logArea, buttonText) {
                $logArea.empty(); // Clear previous logs
                $button.prop('disabled', true).text('Comparing publications...'); // Disable button and change text
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
                                    if (msg.startsWith('Discrepancy:')) {
                                        appendLog($logArea, msg, 'discrepancy');
                                    } else {
                                        appendLog($logArea, msg, 'detail');
                                    }
                                });
                            }
                            setLogAreaClass($logArea, 'success');

                            // Log WordPress post details if available (for 'Compare' action)
                            if (response.data.wordpress_post_details) {
                                console.log('All WordPress Post Details:', response.data.wordpress_post_details);
                                appendLog($logArea, 'WordPress Post Details logged to console. Check browser console for full list.', 'info');
                                
                                // Also display summary in the log area
                                appendLog($logArea, `Found ${response.data.wordpress_post_details.length} WordPress posts with publication numbers.`, 'info');
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

            // Event listener for the "Search Publications" button
            $('#search-publications-btn').on('click', function() {
                const $button = $(this);
                const $logArea = $('#search-publications-log');
                const postId = $('#search-post-id').val().trim();
                const publicationNumber = $('#search-publication-number').val().trim();

                // Validate that at least one field is filled
                if (!postId && !publicationNumber) {
                    $logArea.empty();
                    setLogAreaClass($logArea, 'error');
                    appendLog($logArea, 'Please enter either a Post ID or Publication Number to search.', 'error');
                    return;
                }

                $logArea.empty(); // Clear previous logs
                $button.prop('disabled', true).text('Searching...'); // Disable button and change text
                setLogAreaClass($logArea, 'info'); // Set log area to 'info' state
                appendLog($logArea, 'Initiating search for publications...');

                $.ajax({
                    url: '<?php echo esc_js($ajax_url); ?>', // WordPress AJAX handler URL
                    type: 'POST',
                    data: {
                        action: 'search_publications_data', // The AJAX action to trigger in PHP
                        nonce: '<?php echo esc_js($nonce); ?>',   // Security nonce
                        post_id: postId,
                        publication_number: publicationNumber
                    },
                    success: function(response) {
                        if (response.success) {
                            // PHP returned wp_send_json_success
                            appendLog($logArea, response.data.message, 'success');
                            if (response.data.log && response.data.log.length > 0) {
                                response.data.log.forEach(msg => {
                                    appendLog($logArea, msg, 'detail');
                                });
                            }
                            setLogAreaClass($logArea, 'success');

                            // Log found publications to console
                            if (response.data.found_publications) {
                                console.log('Found Publications:', response.data.found_publications);
                                appendLog($logArea, 'Publication details logged to console. Check browser console for full details.', 'info');
                                
                                // Also display summary in the log area
                                appendLog($logArea, `Found ${response.data.found_publications.length} matching publication(s).`, 'info');
                            }

                        } else {
                            // PHP returned wp_send_json_error
                            let errorMessage = response.data || 'An unknown error occurred.';
                            appendLog($logArea, `Server Error: ${errorMessage}`, 'error');
                            setLogAreaClass($logArea, 'error');
                            console.error('AJAX Server Error Response:', response);
                        }
                        $button.prop('disabled', false).text('Search Publications'); // Re-enable button
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        // This fires for HTTP errors (e.g., 404, 500, no response)
                        let detailedError = `HTTP Status: ${jqXHR.status} (${textStatus}) - ${errorThrown}`;
                        let responseText = jqXHR.responseText ? `Response: ${jqXHR.responseText.substring(0, 500)}...` : 'No response text.';

                        appendLog($logArea, `AJAX Request Failed: ${detailedError}`, 'error');
                        appendLog($logArea, responseText, 'error');
                        setLogAreaClass($logArea, 'error');
                        console.error('Full jQuery AJAX Error Object:', jqXHR);
                        $button.prop('disabled', false).text('Search Publications'); // Re-enable button
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

// Register the AJAX handler for searching individual publications
add_action('wp_ajax_search_publications_data', 'publication_api_tool_search_publications');

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
    $api_publication_numbers = [];
    $wordpress_publication_numbers = []; // Changed to store publication numbers
    $wordpress_posts_details = []; // New array to store WordPress post IDs and their publication numbers

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
            if (isset($publication['PUBLICATION_NUMBER'])) {
                $api_publication_numbers[] = (string) $publication['PUBLICATION_NUMBER']; // Ensure string for consistent comparison
            }
        }
        $log[] = "Successfully fetched " . count($api_publication_numbers) . " publication IDs from the API.";

        // --- Fetch WordPress Data and ACF field ---
        $log[] = "Attempting to access publications and their 'publication_number' field in the WordPress database...";
        $args = array(
            'post_type'      => 'publications',
            'posts_per_page' => -1, // Get all publications
        );
        $wordpress_posts = get_posts($args);

        if (!empty($wordpress_posts)) {
            foreach ($wordpress_posts as $post) {
                // Get the ACF field 'publication_number' for each post
                $publication_number = get_field('publication_number', $post->ID);
                if ($publication_number) {
                    $wordpress_publication_numbers[] = (string) $publication_number; // Ensure string for consistent comparison
                    // Store the post ID and its publication number
                    $wordpress_posts_details[] = [
                        'ID' => $post->ID,
                        'post_title' => $post->post_title,
                        'post_status' => $post->post_status,
                        'PUBLICATION_NUMBER' => (string) $publication_number
                    ];
                } else {
                    $log[] = "Warning: WordPress post ID '{$post->ID}' ('{$post->post_title}') is missing the 'publication_number' ACF field.";
                    // Still add to details for complete record
                    $wordpress_posts_details[] = [
                        'ID' => $post->ID,
                        'post_title' => $post->post_title,
                        'post_status' => $post->post_status,
                        'PUBLICATION_NUMBER' => null
                    ];
                }
            }
        }

        $log[] = "Successfully located " . count($wordpress_publication_numbers) . " 'publication_number' records from 'publications' posts in the WordPress database.";
        $log[] = "Total WordPress posts found: " . count($wordpress_posts_details);

        // --- Compare IDs (now comparing API IDs with WordPress publication numbers) ---
        $message = "Comparison Results:";
        $discrepancies_found = false;

        // IDs present in API but not in WordPress (using publication_number)
        $api_only_ids = array_diff($api_publication_numbers, $wordpress_publication_numbers);
        if (!empty($api_only_ids)) {
            $discrepancies_found = true;
            $log[] = "Discrepancy: " . count($api_only_ids) . " publications found in API but no matching 'publication_number' in WordPress.";
            foreach ($api_only_ids as $id) {
                $log[] = "Discrepancy: API ID '{$id}' is missing in WordPress (by 'publication_number').";
            }
        } else {
            $log[] = "No API publications missing from WordPress (by 'publication_number').";
        }

        // IDs (publication_numbers) present in WordPress but not in API
        $wordpress_only_numbers = array_diff($wordpress_publication_numbers, $api_publication_numbers);
        if (!empty($wordpress_only_numbers)) {
            $discrepancies_found = true;
            $log[] = "Discrepancy: " . count($wordpress_only_numbers) . " 'publication_number' fields found in WordPress but not in API.";
            foreach ($wordpress_only_numbers as $number) {
                $log[] = "Discrepancy: WordPress 'publication_number' '{$number}' is missing from API.";
            }
        } else {
            $log[] = "No WordPress 'publication_number' fields missing from API.";
        }

        if (!$discrepancies_found) {
            $log[] = "All API publication IDs match WordPress 'publication_number' fields. No discrepancies found.";
            $message = "Comparison complete: All IDs match.";
        } else {
            $message = "Comparison complete: Discrepancies found.";
        }

        // Send success response with all the data including WordPress post details
        wp_send_json_success([
            'message' => $message,
            'log' => $log,
            'wordpress_post_details' => $wordpress_posts_details, // This will be logged to console
            'api_count' => count($api_publication_numbers),
            'wordpress_count' => count($wordpress_publication_numbers),
            'total_wordpress_posts' => count($wordpress_posts_details)
        ]);

    } catch (Exception $e) {
        error_log('Publication API Comparison Tool Error: ' . $e->getMessage());
        wp_send_json_error('Error during comparison: ' . $e->getMessage());
    }
}

/**
 * Handles the AJAX request to search for specific publications by ID or Publication Number.
 */
function publication_api_tool_search_publications() {
    // Verify the nonce for security.
    if (!check_ajax_referer('publication_api_tool_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed: Invalid nonce.', 403);
    }

    // Check if the current user has the 'manage_options' capability.
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied: You do not have sufficient permissions.', 403);
    }

    $log = []; // Array to store log messages
    $found_publications = []; // Array to store found publications
    $post_id = isset($_POST['post_id']) ? sanitize_text_field($_POST['post_id']) : '';
    $publication_number = isset($_POST['publication_number']) ? sanitize_text_field($_POST['publication_number']) : '';

    try {
        // Validate input - at least one field must be provided
        if (empty($post_id) && empty($publication_number)) {
            wp_send_json_error('Please provide either a Post ID or Publication Number to search.');
        }

        $log[] = "Starting search with Post ID: '{$post_id}' and Publication Number: '{$publication_number}'";

        // Search by Post ID if provided
        if (!empty($post_id)) {
            $log[] = "Searching by Post ID: {$post_id}";
            
            $post = get_post($post_id);
            if ($post && $post->post_type === 'publications') {
                $publication_number_field = get_field('publication_number', $post->ID);
                
                $publication_details = [
                    'search_method' => 'post_id',
                    'ID' => $post->ID,
                    'post_title' => $post->post_title,
                    'post_status' => $post->post_status,
                    'post_date' => $post->post_date,
                    'post_modified' => $post->post_modified,
                    'post_content' => $post->post_content,
                    'post_excerpt' => $post->post_excerpt,
                    'PUBLICATION_NUMBER' => $publication_number_field,
                    'all_meta' => get_post_meta($post->ID), // Get all custom fields
                    'all_acf_fields' => function_exists('get_fields') ? get_fields($post->ID) : 'ACF not available'
                ];
                
                $found_publications[] = $publication_details;
                $log[] = "Found publication by Post ID: '{$post->post_title}' (ID: {$post->ID})";
                
                if ($publication_number_field) {
                    $log[] = "Publication Number from ACF: {$publication_number_field}";
                } else {
                    $log[] = "Warning: No 'publication_number' ACF field found for this post";
                }
            } else {
                $log[] = "No publication post found with ID: {$post_id}";
            }
        }

        // Search by Publication Number if provided
        if (!empty($publication_number)) {
            $log[] = "Searching by Publication Number: {$publication_number}";
            
            // Use WP_Query to search for posts with the specific ACF field value
            $args = array(
                'post_type' => 'publications',
                'posts_per_page' => -1,
                'post_status' => array('publish', 'draft', 'private'),
                'meta_query' => array(
                    array(
                        'key' => 'publication_number',
                        'value' => $publication_number,
                        'compare' => '='
                    )
                )
            );
            
            $query = new WP_Query($args);
            
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post = get_post();
                    
                    // Check if we already found this post by ID to avoid duplicates
                    $already_found = false;
                    foreach ($found_publications as $found_pub) {
                        if ($found_pub['ID'] === $post->ID) {
                            $already_found = true;
                            // Update search method to indicate it was found by both methods
                            $found_pub['search_method'] = 'both_post_id_and_publication_number';
                            break;
                        }
                    }
                    
                    if (!$already_found) {
                        $publication_details = [
                            'search_method' => 'publication_number',
                            'ID' => $post->ID,
                            'post_title' => $post->post_title,
                            'post_status' => $post->post_status,
                            'post_date' => $post->post_date,
                            'post_modified' => $post->post_modified,
                            'post_content' => $post->post_content,
                            'post_excerpt' => $post->post_excerpt,
                            'PUBLICATION_NUMBER' => get_field('publication_number', $post->ID),
                            'all_meta' => get_post_meta($post->ID), // Get all custom fields
                            'all_acf_fields' => function_exists('get_fields') ? get_fields($post->ID) : 'ACF not available'
                        ];
                        
                        $found_publications[] = $publication_details;
                        $log[] = "Found publication by Publication Number: '{$post->post_title}' (ID: {$post->ID})";
                    } else {
                        $log[] = "Publication '{$post->post_title}' (ID: {$post->ID}) matches both search criteria";
                    }
                }
                wp_reset_postdata();
            } else {
                $log[] = "No publications found with Publication Number: {$publication_number}";
            }
        }

        // Prepare response message
        $total_found = count($found_publications);
        if ($total_found > 0) {
            $message = "Search completed: Found {$total_found} matching publication(s).";
        } else {
            $message = "Search completed: No matching publications found.";
        }

        // Send success response
        wp_send_json_success([
            'message' => $message,
            'log' => $log,
            'found_publications' => $found_publications,
            'search_criteria' => [
                'post_id' => $post_id,
                'publication_number' => $publication_number
            ],
            'total_found' => $total_found
        ]);

    } catch (Exception $e) {
        error_log('Publication Search Tool Error: ' . $e->getMessage());
        wp_send_json_error('Error during search: ' . $e->getMessage());
    }
}