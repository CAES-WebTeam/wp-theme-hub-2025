<?php
/**
 * Symplectic Elements API Query Tool for WordPress Admin
 * 
 * This plugin creates an admin page to query user data from the Symplectic Elements API
 * using a proprietary ID, or fetch all users with pagination.
 * 
 * IMPORTANT: Add the following constants to your wp-config.php file:
 * define('SYMPLECTIC_API_USERNAME', 'your_username_here');
 * define('SYMPLECTIC_API_PASSWORD', 'your_password_here');
 */

// Hook into the admin menu to add our tool page
add_action('admin_menu', 'symplectic_query_tool_add_admin_page');

function symplectic_query_tool_add_admin_page() {
    add_submenu_page(
        'caes-tools',                           // Parent slug
        'Symplectic Elements User Query Tool',  // Page title
        'Symplectic User Query',                // Menu title
        'manage_options',                        // Capability required
        'symplectic-user-query',                // Menu slug
        'symplectic_query_tool_render_page'     // Function to render the page
    );
}

// Enqueue necessary scripts and styles
add_action('admin_enqueue_scripts', 'symplectic_query_tool_enqueue_scripts');

function symplectic_query_tool_enqueue_scripts($hook) {
    // Only load on our specific admin page
    if ($hook !== 'caes-tools_page_symplectic-user-query') {
        return;
    }
    
    // Enqueue WordPress default styles for better formatting
    wp_enqueue_style('wp-admin');
    
    // Add custom styles
    wp_add_inline_style('wp-admin', '
        .symplectic-query-tool-wrapper {
            max-width: 800px;
            margin: 20px 0;
        }
        .symplectic-form-group {
            margin-bottom: 20px;
        }
        .symplectic-form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .symplectic-form-group input[type="text"],
        .symplectic-form-group input[type="number"],
        .symplectic-form-group select {
            width: 100%;
            max-width: 400px;
        }
        .symplectic-form-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .symplectic-form-row .symplectic-form-group {
            flex: 1;
            min-width: 150px;
        }
        .symplectic-results-area {
            margin-top: 30px;
            padding: 20px;
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 5px;
            min-height: 100px;
        }
        .symplectic-results-area.empty {
            color: #666;
            font-style: italic;
        }
        .symplectic-loading {
            color: #0073aa;
            font-style: italic;
        }
        .symplectic-error {
            color: #dc3232;
            padding: 10px;
            background: #ffebe8;
            border: 1px solid #dc3232;
            border-radius: 3px;
            margin-bottom: 15px;
        }
        .symplectic-error-details {
            margin-top: 10px;
            padding: 10px;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 3px;
            font-family: monospace;
            font-size: 12px;
        }
        .symplectic-error-section {
            margin-bottom: 15px;
        }
        .symplectic-error-section h4 {
            margin: 0 0 5px 0;
            color: #23282d;
            font-size: 14px;
        }
        .symplectic-error-section ul {
            margin: 5px 0 5px 20px;
            list-style-type: disc;
        }
        .symplectic-error-section pre {
            background: #f1f1f1;
            padding: 8px;
            border-radius: 3px;
            overflow-x: auto;
            margin: 5px 0;
        }
        .symplectic-success {
            color: #46b450;
            padding: 10px;
            background: #ecf7ed;
            border: 1px solid #46b450;
            border-radius: 3px;
        }
        .symplectic-json-output {
            background: #fff;
            padding: 15px;
            border: 1px solid #ccc;
            border-radius: 3px;
            overflow-x: auto;
            font-family: monospace;
            font-size: 13px;
            line-height: 1.4;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 600px;
            overflow-y: auto;
        }
        .symplectic-pagination-info {
            background: #fff;
            padding: 15px;
            border: 1px solid #0073aa;
            border-radius: 3px;
            margin-bottom: 15px;
        }
        .symplectic-pagination-info h4 {
            margin: 0 0 10px 0;
            color: #0073aa;
        }
        .symplectic-pagination-info p {
            margin: 5px 0;
        }
        .symplectic-pagination-controls {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }
        .symplectic-pagination-controls button {
            min-width: 100px;
        }
        .symplectic-pagination-controls .page-info {
            flex: 1;
            text-align: center;
            font-weight: 600;
        }
        .symplectic-hint {
            background: #fff8e5;
            border-left: 4px solid #ffb900;
            padding: 10px 15px;
            margin-bottom: 15px;
        }
    ');
    
    // Add inline JavaScript for AJAX functionality
    wp_add_inline_script('jquery', '
        jQuery(document).ready(function($) {
            var currentPage = 1;
            var totalPages = 1;
            var lastQueryParams = {};
            
            $("#symplectic-query-form").on("submit", function(e) {
                e.preventDefault();
                currentPage = 1;
                executeQuery();
            });
            
            // Pagination button handlers
            $(document).on("click", "#symplectic-prev-page", function() {
                if (currentPage > 1) {
                    currentPage--;
                    executeQuery();
                }
            });
            
            $(document).on("click", "#symplectic-next-page", function() {
                if (currentPage < totalPages) {
                    currentPage++;
                    executeQuery();
                }
            });
            
            $(document).on("click", "#symplectic-first-page", function() {
                if (currentPage > 1) {
                    currentPage = 1;
                    executeQuery();
                }
            });
            
            $(document).on("click", "#symplectic-last-page", function() {
                if (currentPage < totalPages) {
                    currentPage = totalPages;
                    executeQuery();
                }
            });
            
            function executeQuery() {
                var proprietaryId = $("#proprietary-id").val().trim();
                var perPage = parseInt($("#per-page").val()) || 25;
                var resultsArea = $("#symplectic-results");
                var submitButton = $("#symplectic-submit");
                
                if (!proprietaryId) {
                    resultsArea.html("<div class=\"symplectic-error\">Please enter a Proprietary ID or \"null\" to fetch all users.</div>");
                    return;
                }
                
                // Store query params for pagination
                lastQueryParams = {
                    proprietary_id: proprietaryId,
                    per_page: perPage,
                    page: currentPage
                };
                
                // Show loading state
                submitButton.prop("disabled", true).text("Querying...");
                resultsArea.html("<div class=\"symplectic-loading\">Loading results" + (proprietaryId.toLowerCase() === "null" ? " (fetching all users, page " + currentPage + ")..." : "...") + "</div>");
                
                // Make AJAX request to our PHP handler
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "symplectic_query_api",
                        proprietary_id: proprietaryId,
                        per_page: perPage,
                        page: currentPage,
                        nonce: "' . wp_create_nonce('symplectic_query_nonce') . '"
                    },
                    success: function(response) {
                        if (response.success) {
                            var html = "";
                            
                            // Check for pagination info
                            if (response.data.pagination) {
                                var pagination = response.data.pagination;
                                totalPages = pagination.total_pages || 1;
                                
                                html += "<div class=\"symplectic-pagination-info\">";
                                html += "<h4>Pagination Information</h4>";
                                html += "<p><strong>Total Results:</strong> " + (pagination.total_results || "Unknown") + "</p>";
                                html += "<p><strong>Results Per Page:</strong> " + (pagination.per_page || perPage) + "</p>";
                                html += "<p><strong>Current Page:</strong> " + currentPage + " of " + totalPages + "</p>";
                                
                                if (pagination.results_on_page !== undefined) {
                                    html += "<p><strong>Results on This Page:</strong> " + pagination.results_on_page + "</p>";
                                }
                                
                                // Pagination controls
                                if (totalPages > 1) {
                                    html += "<div class=\"symplectic-pagination-controls\">";
                                    html += "<button type=\"button\" id=\"symplectic-first-page\" class=\"button\" " + (currentPage <= 1 ? "disabled" : "") + ">« First</button>";
                                    html += "<button type=\"button\" id=\"symplectic-prev-page\" class=\"button\" " + (currentPage <= 1 ? "disabled" : "") + ">‹ Previous</button>";
                                    html += "<span class=\"page-info\">Page " + currentPage + " of " + totalPages + "</span>";
                                    html += "<button type=\"button\" id=\"symplectic-next-page\" class=\"button\" " + (currentPage >= totalPages ? "disabled" : "") + ">Next ›</button>";
                                    html += "<button type=\"button\" id=\"symplectic-last-page\" class=\"button\" " + (currentPage >= totalPages ? "disabled" : "") + ">Last »</button>";
                                    html += "</div>";
                                }
                                
                                html += "</div>";
                            }
                            
                            html += "<div class=\"symplectic-success\">Query successful!</div>";
                            html += "<div class=\"symplectic-json-output\">" + 
                                escapeHtml(JSON.stringify(response.data, null, 2)) + 
                            "</div>";
                            
                            // Add bottom pagination controls for long results
                            if (response.data.pagination && response.data.pagination.total_pages > 1) {
                                html += "<div class=\"symplectic-pagination-controls\" style=\"margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;\">";
                                html += "<button type=\"button\" id=\"symplectic-first-page\" class=\"button\" " + (currentPage <= 1 ? "disabled" : "") + ">« First</button>";
                                html += "<button type=\"button\" id=\"symplectic-prev-page\" class=\"button\" " + (currentPage <= 1 ? "disabled" : "") + ">‹ Previous</button>";
                                html += "<span class=\"page-info\">Page " + currentPage + " of " + totalPages + "</span>";
                                html += "<button type=\"button\" id=\"symplectic-next-page\" class=\"button\" " + (currentPage >= totalPages ? "disabled" : "") + ">Next ›</button>";
                                html += "<button type=\"button\" id=\"symplectic-last-page\" class=\"button\" " + (currentPage >= totalPages ? "disabled" : "") + ">Last »</button>";
                                html += "</div>";
                            }
                            
                            resultsArea.html(html);
                        } else {
                            // Handle error response with detailed information
                            var errorHtml = "";
                            
                            // Check if response.data is a string or object
                            if (typeof response.data === "string") {
                                errorHtml = "<div class=\"symplectic-error\">Error: " + escapeHtml(response.data) + "</div>";
                            } else if (typeof response.data === "object" && response.data !== null) {
                                // Build detailed error display
                                errorHtml = "<div class=\"symplectic-error\">";
                                
                                // Main error message
                                if (response.data.error_type) {
                                    errorHtml += "<strong>" + escapeHtml(response.data.error_type) + "</strong>";
                                    if (response.data.status_code) {
                                        errorHtml += " (HTTP " + response.data.status_code + ")";
                                    }
                                } else if (response.data.error_message) {
                                    errorHtml += "<strong>Error:</strong> " + escapeHtml(response.data.error_message);
                                } else {
                                    errorHtml += "<strong>API Request Failed</strong>";
                                }
                                
                                errorHtml += "</div>";
                                
                                // Error details section
                                errorHtml += "<div class=\"symplectic-error-details\">";
                                
                                // Status message
                                if (response.data.status_message) {
                                    errorHtml += "<div class=\"symplectic-error-section\">";
                                    errorHtml += "<h4>Status Message:</h4>";
                                    errorHtml += "<p>" + escapeHtml(response.data.status_message) + "</p>";
                                    errorHtml += "</div>";
                                }
                                
                                // Likely causes
                                if (response.data.likely_causes && Array.isArray(response.data.likely_causes)) {
                                    errorHtml += "<div class=\"symplectic-error-section\">";
                                    errorHtml += "<h4>Likely Causes:</h4>";
                                    errorHtml += "<ul>";
                                    response.data.likely_causes.forEach(function(cause) {
                                        errorHtml += "<li>" + escapeHtml(cause) + "</li>";
                                    });
                                    errorHtml += "</ul>";
                                    errorHtml += "</div>";
                                }
                                
                                // Troubleshooting steps
                                if (response.data.troubleshooting_steps && Array.isArray(response.data.troubleshooting_steps)) {
                                    errorHtml += "<div class=\"symplectic-error-section\">";
                                    errorHtml += "<h4>Troubleshooting Steps:</h4>";
                                    errorHtml += "<ul>";
                                    response.data.troubleshooting_steps.forEach(function(step) {
                                        errorHtml += "<li>" + escapeHtml(step) + "</li>";
                                    });
                                    errorHtml += "</ul>";
                                    errorHtml += "</div>";
                                }
                                
                                // Response body (if present and not too large)
                                if (response.data.response_body) {
                                    errorHtml += "<div class=\"symplectic-error-section\">";
                                    errorHtml += "<h4>Response Body:</h4>";
                                    var bodyText = response.data.response_body;
                                    if (bodyText.length > 500) {
                                        bodyText = bodyText.substring(0, 500) + "... (truncated)";
                                    }
                                    errorHtml += "<pre>" + escapeHtml(bodyText) + "</pre>";
                                    errorHtml += "</div>";
                                }
                                
                                // Response headers
                                if (response.data.response_headers) {
                                    errorHtml += "<div class=\"symplectic-error-section\">";
                                    errorHtml += "<h4>Response Headers:</h4>";
                                    errorHtml += "<pre>" + escapeHtml(JSON.stringify(response.data.response_headers, null, 2)) + "</pre>";
                                    errorHtml += "</div>";
                                }
                                
                                // Diagnostic info
                                if (response.data.diagnostic_info) {
                                    errorHtml += "<div class=\"symplectic-error-section\">";
                                    errorHtml += "<h4>Request Details:</h4>";
                                    errorHtml += "<pre>" + escapeHtml(JSON.stringify(response.data.diagnostic_info, null, 2)) + "</pre>";
                                    errorHtml += "</div>";
                                }
                                
                                // Full error object (collapsed by default)
                                errorHtml += "<div class=\"symplectic-error-section\">";
                                errorHtml += "<h4>Full Error Details:</h4>";
                                errorHtml += "<details>";
                                errorHtml += "<summary style=\"cursor: pointer;\">Click to expand raw error data</summary>";
                                errorHtml += "<pre style=\"margin-top: 10px;\">" + escapeHtml(JSON.stringify(response.data, null, 2)) + "</pre>";
                                errorHtml += "</details>";
                                errorHtml += "</div>";
                                
                                errorHtml += "</div>";
                            } else {
                                // Fallback for unexpected data types
                                errorHtml = "<div class=\"symplectic-error\">An unknown error occurred. Please try again.</div>";
                            }
                            
                            resultsArea.html(errorHtml);
                        }
                    },
                    error: function(xhr, status, error) {
                        var errorMessage = "<div class=\"symplectic-error\">";
                        errorMessage += "<strong>Request Failed:</strong> " + escapeHtml(error);
                        errorMessage += "</div>";
                        
                        if (xhr.responseText) {
                            errorMessage += "<div class=\"symplectic-error-details\">";
                            errorMessage += "<h4>Server Response:</h4>";
                            errorMessage += "<pre>" + escapeHtml(xhr.responseText) + "</pre>";
                            errorMessage += "</div>";
                        }
                        
                        resultsArea.html(errorMessage);
                    },
                    complete: function() {
                        submitButton.prop("disabled", false).text("Execute Query");
                    }
                });
            }
            
            function escapeHtml(text) {
                // Handle non-string types
                if (typeof text !== "string") {
                    text = String(text);
                }
                
                var map = {
                    "&": "&amp;",
                    "<": "&lt;",
                    ">": "&gt;",
                    "\"": "&quot;",
                    "\'": "&#039;"
                };
                return text.replace(/[&<>"\']/g, function(m) { return map[m]; });
            }
        });
    ');
}

// AJAX handler for API requests
add_action('wp_ajax_symplectic_query_api', 'symplectic_query_api_handler');

function symplectic_query_api_handler() {
    // Verify nonce for security
    if (!wp_verify_nonce($_POST['nonce'], 'symplectic_query_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    // Check if API credentials are defined in wp-config.php
    if (!defined('SYMPLECTIC_API_USERNAME') || !defined('SYMPLECTIC_API_PASSWORD')) {
        wp_send_json_error('API credentials not configured. Please add SYMPLECTIC_API_USERNAME and SYMPLECTIC_API_PASSWORD to wp-config.php');
        return;
    }
    
    $proprietary_id = sanitize_text_field($_POST['proprietary_id']);
    $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 25;
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    
    // Validate pagination parameters
    $per_page = max(1, min(100, $per_page)); // Limit between 1 and 100
    $page = max(1, $page);
    
    if (empty($proprietary_id)) {
        wp_send_json_error('Proprietary ID is required (enter "null" to fetch all users)');
        return;
    }
    
    // Build the API URL
    // Production API base URL
    $api_base_url = 'https://uga.elements.symplectic.org:8091/secure-api/v6.13/users';
    
    // Test API base URL (uncomment to use)
    // $api_base_url = 'https://uga-test.elements.symplectic.org:8093/secure-api/v6.13/users';
    
    // Build query parameters
    $query_params = array();
    
    // Check if we're fetching all users (proprietary_id is "null")
    $fetch_all_users = (strtolower($proprietary_id) === 'null');
    
    if (!$fetch_all_users) {
        // Query for specific user by proprietary ID
        $query_params['query'] = 'proprietary-id="' . $proprietary_id . '"';
        $query_params['detail'] = 'full';
    } else {
        // Fetching all users - use pagination parameters
        $query_params['per-page'] = $per_page;
        $query_params['page'] = $page;
        $query_params['detail'] = 'ref'; // Use 'ref' for lighter response when fetching all
    }
    
    // Build the final URL
    $api_url = $api_base_url . '?' . http_build_query($query_params);
    
    // Get credentials from wp-config.php constants
    $username = SYMPLECTIC_API_USERNAME;
    $password = SYMPLECTIC_API_PASSWORD;
    
    // Build authentication header
    $auth_string = base64_encode($username . ':' . $password);
    
    // Set up the API request with authentication
    $args = array(
        'headers' => array(
            'Authorization' => 'Basic ' . $auth_string,
            'Accept' => 'application/json',
        ),
        'timeout' => 300,
        'sslverify' => true,
    );
    
    // Prepare detailed diagnostic information
    $diagnostic_info = array(
        'request_url' => $api_url,
        'request_method' => 'GET',
        'fetch_all_users' => $fetch_all_users,
        'page' => $page,
        'per_page' => $per_page,
        'username_length' => strlen($username),
        'password_length' => strlen($password),
        'auth_header_length' => strlen($auth_string),
        'timestamp' => current_time('mysql'),
    );
    
    // Make the API request
    $response = wp_remote_get($api_url, $args);
    
    // Check for errors
    if (is_wp_error($response)) {
        $error_data = array(
            'error_message' => $response->get_error_message(),
            'error_code' => $response->get_error_code(),
            'diagnostic_info' => $diagnostic_info,
        );
        wp_send_json_error($error_data);
        return;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $response_headers = wp_remote_retrieve_headers($response);
    
    // Enhanced error handling for non-200 responses
    if ($response_code !== 200) {
        // Prepare detailed error information
        $error_details = array(
            'status_code' => $response_code,
            'status_message' => wp_remote_retrieve_response_message($response),
            'response_body' => $response_body,
            'response_headers' => array(
                'content_type' => isset($response_headers['content-type']) ? $response_headers['content-type'] : 'Not provided',
                'www_authenticate' => isset($response_headers['www-authenticate']) ? $response_headers['www-authenticate'] : 'Not provided',
                'server' => isset($response_headers['server']) ? $response_headers['server'] : 'Not provided',
            ),
            'diagnostic_info' => $diagnostic_info,
        );
        
        // Add specific guidance based on status code
        switch ($response_code) {
            case 401:
                $error_details['error_type'] = 'Authentication Failed';
                $error_details['likely_causes'] = array(
                    'Invalid username or password',
                    'Credentials expired or account disabled',
                    'Username/password contains special characters not properly encoded',
                    'Account lacks API access permissions',
                    'IP address not whitelisted (if API has IP restrictions)',
                );
                $error_details['troubleshooting_steps'] = array(
                    '1. Verify SYMPLECTIC_API_USERNAME and SYMPLECTIC_API_PASSWORD in wp-config.php',
                    '2. Check if credentials work in another API client (like Postman)',
                    '3. Confirm the account has API access enabled in Symplectic Elements',
                    '4. Check for typos, extra spaces, or hidden characters in credentials',
                    '5. Contact Symplectic Elements administrator to verify account status',
                );
                break;
            case 403:
                $error_details['error_type'] = 'Access Forbidden';
                $error_details['likely_causes'] = array(
                    'Account lacks permissions for this API endpoint',
                    'IP address blocked',
                );
                break;
            case 404:
                $error_details['error_type'] = 'Not Found';
                $error_details['likely_causes'] = array(
                    'API endpoint URL is incorrect',
                    'API version v6.13 may not be available',
                );
                break;
            case 500:
            case 502:
            case 503:
                $error_details['error_type'] = 'Server Error';
                $error_details['likely_causes'] = array(
                    'Symplectic Elements API server is experiencing issues',
                    'Database connection problems on server side',
                );
                break;
            default:
                $error_details['error_type'] = 'HTTP Error ' . $response_code;
        }
        
        wp_send_json_error($error_details);
        return;
    }
    
    // Success - parse and return the response
    $data = json_decode($response_body, true);
    
    // Extract pagination information from the response
    $pagination_info = array(
        'page' => $page,
        'per_page' => $per_page,
        'total_results' => null,
        'total_pages' => 1,
        'results_on_page' => 0,
    );
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        // If not JSON, try to parse XML (Symplectic may return XML)
        $xml_data = @simplexml_load_string($response_body);
        
        if ($xml_data !== false) {
            // Convert XML to array for easier handling
            $data = json_decode(json_encode($xml_data), true);
            
            // Try to extract pagination from XML attributes
            if (isset($xml_data['results-count'])) {
                $pagination_info['total_results'] = (int)$xml_data['results-count'];
            }
            if (isset($xml_data['last-page'])) {
                $pagination_info['total_pages'] = (int)$xml_data['last-page'];
            }
        } else {
            // Return raw response with metadata
            $result = array(
                'raw_response' => $response_body,
                'content_type' => isset($response_headers['content-type']) ? $response_headers['content-type'] : 'unknown',
                'response_length' => strlen($response_body),
                'diagnostic_info' => $diagnostic_info,
            );
            wp_send_json_success($result);
            return;
        }
    }
    
    // Try to extract pagination info from JSON response
    // Symplectic API typically includes pagination in the response structure
    if (is_array($data)) {
        // Check for common pagination response patterns
        if (isset($data['pagination'])) {
            $pagination_info['total_results'] = isset($data['pagination']['results-count']) ? (int)$data['pagination']['results-count'] : null;
            $pagination_info['total_pages'] = isset($data['pagination']['last-page']) ? (int)$data['pagination']['last-page'] : 1;
        }
        
        // Check for root-level pagination attributes (common in Symplectic responses)
        if (isset($data['@attributes'])) {
            if (isset($data['@attributes']['results-count'])) {
                $pagination_info['total_results'] = (int)$data['@attributes']['results-count'];
            }
            if (isset($data['@attributes']['last-page'])) {
                $pagination_info['total_pages'] = (int)$data['@attributes']['last-page'];
            }
        }
        
        // Direct attributes (sometimes present)
        if (isset($data['results-count'])) {
            $pagination_info['total_results'] = (int)$data['results-count'];
        }
        if (isset($data['last-page'])) {
            $pagination_info['total_pages'] = (int)$data['last-page'];
        }
        
        // Count results on current page
        if (isset($data['entry']) && is_array($data['entry'])) {
            $pagination_info['results_on_page'] = count($data['entry']);
        } elseif (isset($data['users']) && is_array($data['users'])) {
            $pagination_info['results_on_page'] = count($data['users']);
        } elseif (isset($data['api:object']) && is_array($data['api:object'])) {
            $pagination_info['results_on_page'] = count($data['api:object']);
        }
    }
    
    // Return parsed data with metadata
    $result = array(
        'data' => $data,
        'pagination' => $fetch_all_users ? $pagination_info : null,
        'response_metadata' => array(
            'response_code' => $response_code,
            'content_type' => isset($response_headers['content-type']) ? $response_headers['content-type'] : 'unknown',
            'response_size' => strlen($response_body),
            'fetch_all_users' => $fetch_all_users,
        ),
        'diagnostic_info' => $diagnostic_info,
    );
    
    wp_send_json_success($result);
}

// Render the admin page
function symplectic_query_tool_render_page() {
    // Check if credentials are configured
    $credentials_configured = defined('SYMPLECTIC_API_USERNAME') && defined('SYMPLECTIC_API_PASSWORD');
    ?>
    <div class="wrap">
        <h1>Symplectic Elements User Query Tool</h1>
        
        <div class="symplectic-query-tool-wrapper">
            <?php if (!$credentials_configured): ?>
                <div class="notice notice-error">
                    <p><strong>Configuration Required:</strong> API credentials are not configured. Please add the following to your wp-config.php file:</p>
                    <pre>define('SYMPLECTIC_API_USERNAME', 'your_username_here');
define('SYMPLECTIC_API_PASSWORD', 'your_password_here');</pre>
                </div>
            <?php endif; ?>
            
            <div class="notice notice-info">
                <p><strong>Purpose:</strong> This tool allows you to query user information from the Symplectic Elements API 
                using a proprietary ID. Enter a proprietary ID below and click "Execute Query" to retrieve 
                the full user details from the Symplectic Elements system.</p>
            </div>
            
            <div class="symplectic-hint">
                <p><strong>Tip:</strong> Enter <code>null</code> as the Proprietary ID to fetch all users from the system. 
                Use the pagination controls to navigate through large result sets.</p>
            </div>
            
            <form id="symplectic-query-form" method="post">
                <div class="symplectic-form-group">
                    <label for="proprietary-id">Proprietary ID:</label>
                    <input 
                        type="text" 
                        id="proprietary-id" 
                        name="proprietary_id" 
                        class="regular-text" 
                        placeholder="Enter proprietary ID (e.g., 810019979) or &quot;null&quot; for all users"
                        value=""
                        <?php echo !$credentials_configured ? 'disabled' : ''; ?>
                    />
                    <p class="description">Enter the proprietary ID of the user you want to query, or enter "null" to fetch all users.</p>
                </div>
                
                <div class="symplectic-form-row">
                    <div class="symplectic-form-group">
                        <label for="per-page">Results Per Page:</label>
                        <select 
                            id="per-page" 
                            name="per_page"
                            <?php echo !$credentials_configured ? 'disabled' : ''; ?>
                        >
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <p class="description">Number of results per page (applies when fetching all users).</p>
                    </div>
                </div>
                
                <div class="symplectic-form-group">
                    <button type="submit" id="symplectic-submit" class="button button-primary" <?php echo !$credentials_configured ? 'disabled' : ''; ?>>
                        Execute Query
                    </button>
                </div>
            </form>
            
            <div class="symplectic-results-area">
                <h2>Query Results</h2>
                <div id="symplectic-results" class="empty">
                    <?php if ($credentials_configured): ?>
                        No query executed yet. Enter a proprietary ID above and click "Execute Query" to see results, or enter "null" to fetch all users.
                    <?php else: ?>
                        Please configure API credentials in wp-config.php before using this tool.
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// Optional: Add a capability check to ensure only authorized users can access
add_filter('user_has_cap', 'symplectic_query_tool_capability_check', 10, 3);

function symplectic_query_tool_capability_check($allcaps, $caps, $args) {
    // This is optional - you can customize who has access to the tool
    // By default, it requires 'manage_options' capability
    return $allcaps;
}