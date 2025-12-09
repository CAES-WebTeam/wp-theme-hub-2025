<?php
/**
 * Symplectic Elements API Query Tool for WordPress Admin
 * 
 * This plugin creates an admin page to query user data from the Symplectic Elements API
 * using a username.
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
        .symplectic-form-group input[type="text"] {
            width: 100%;
            max-width: 400px;
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
        }
    ');
    
    // Add inline JavaScript for AJAX functionality
    wp_add_inline_script('jquery', '
        jQuery(document).ready(function($) {
            $("#symplectic-query-form").on("submit", function(e) {
                e.preventDefault();
                
                var username = $("#username").val().trim();
                var resultsArea = $("#symplectic-results");
                var submitButton = $("#symplectic-submit");
                
                if (!username) {
                    resultsArea.html("<div class=\"symplectic-error\">Please enter a Username.</div>");
                    return;
                }
                
                // Show loading state
                submitButton.prop("disabled", true).text("Querying...");
                resultsArea.html("<div class=\"symplectic-loading\">Loading results...</div>");
                
                // Make AJAX request to our PHP handler
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "symplectic_query_api",
                        username: username,
                        nonce: "' . wp_create_nonce('symplectic_query_nonce') . '"
                    },
                    success: function(response) {
                        if (response.success) {
                            resultsArea.html(
                                "<div class=\"symplectic-success\">Query successful!</div>" +
                                "<div class=\"symplectic-json-output\">" + 
                                    escapeHtml(JSON.stringify(response.data, null, 2)) + 
                                "</div>"
                            );
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
            });
            
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
    
    $username = sanitize_text_field($_POST['username']);
    
    if (empty($username)) {
        wp_send_json_error('Username is required');
        return;
    }
    
    // Build the API URL

    // Test API base URL
    // $api_url = 'https://uga-test.elements.symplectic.org:8093/secure-api/v6.13/users';

    // Production API base URL
    $api_url = 'https://uga.elements.symplectic.org:8091/secure-api/v6.13/users';

    // Final query URL
    $api_url .= '?query=username=%22' . urlencode($username) . '%22&detail=full';
    // $api_url .= '&detail=ref';
    
    // Get credentials from wp-config.php constants
    $api_username = SYMPLECTIC_API_USERNAME;
    $api_password = SYMPLECTIC_API_PASSWORD;
    
    // Build authentication header
    $auth_string = base64_encode($api_username . ':' . $api_password);
    
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
        'username_length' => strlen($api_username),
        'password_length' => strlen($api_password),
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
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        // If not JSON, return raw response with metadata
        $result = array(
            'raw_response' => $response_body,
            'content_type' => isset($response_headers['content-type']) ? $response_headers['content-type'] : 'unknown',
            'response_length' => strlen($response_body),
            'diagnostic_info' => $diagnostic_info,
        );
        wp_send_json_success($result);
    } else {
        // Return parsed JSON with metadata
        $result = array(
            'data' => $data,
            'response_metadata' => array(
                'response_code' => $response_code,
                'content_type' => isset($response_headers['content-type']) ? $response_headers['content-type'] : 'unknown',
                'response_size' => strlen($response_body),
            ),
            'diagnostic_info' => $diagnostic_info,
        );
        wp_send_json_success($result);
    }
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
                using a username. Enter a username below and click "Execute Query" to retrieve 
                the full user details from the Symplectic Elements system.</p>
            </div>
            
            <form id="symplectic-query-form" method="post">
                <div class="symplectic-form-group">
                    <label for="username">Username:</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="regular-text" 
                        placeholder="Enter username (e.g., jsmith)"
                        value=""
                        <?php echo !$credentials_configured ? 'disabled' : ''; ?>
                    />
                    <p class="description">Enter the username of the user you want to query.</p>
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
                        No query executed yet. Enter a username above and click "Execute Query" to see results.
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