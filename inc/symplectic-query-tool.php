<?php
/**
 * Symplectic Elements API Query Tool for WordPress Admin
 * 
 * This plugin creates an admin page to query user data from the Symplectic Elements API
 * using a proprietary ID, or fetch all users with automatic pagination.
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
            max-width: 1200px;
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
        .symplectic-progress {
            background: #fff;
            border: 1px solid #0073aa;
            border-radius: 3px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .symplectic-progress h4 {
            margin: 0 0 10px 0;
            color: #0073aa;
        }
        .symplectic-progress-bar {
            background: #e0e0e0;
            border-radius: 3px;
            height: 20px;
            overflow: hidden;
            margin: 10px 0;
        }
        .symplectic-progress-bar-fill {
            background: #0073aa;
            height: 100%;
            transition: width 0.3s ease;
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
            margin-bottom: 15px;
        }
        .symplectic-json-output {
            background: #fff;
            padding: 15px;
            border: 1px solid #ccc;
            border-radius: 3px;
            overflow-x: auto;
            font-family: monospace;
            font-size: 12px;
            line-height: 1.4;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 800px;
            overflow-y: auto;
        }
        .symplectic-summary {
            background: #fff;
            padding: 15px;
            border: 1px solid #0073aa;
            border-radius: 3px;
            margin-bottom: 15px;
        }
        .symplectic-summary h4 {
            margin: 0 0 10px 0;
            color: #0073aa;
        }
        .symplectic-summary p {
            margin: 5px 0;
        }
        .symplectic-hint {
            background: #fff8e5;
            border-left: 4px solid #ffb900;
            padding: 10px 15px;
            margin-bottom: 15px;
        }
        .symplectic-user-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            margin-top: 15px;
        }
        .symplectic-user-table th,
        .symplectic-user-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 13px;
        }
        .symplectic-user-table th {
            background: #f5f5f5;
            font-weight: 600;
        }
        .symplectic-user-table tr:nth-child(even) {
            background: #fafafa;
        }
        .symplectic-user-table tr:hover {
            background: #f0f0f0;
        }
        .symplectic-export-buttons {
            margin: 15px 0;
        }
        .symplectic-export-buttons button {
            margin-right: 10px;
        }
    ');
    
    // Add inline JavaScript for AJAX functionality
    wp_add_inline_script('jquery', '
        jQuery(document).ready(function($) {
            var allUsers = [];
            var isRunning = false;
            
            $("#symplectic-query-form").on("submit", function(e) {
                e.preventDefault();
                
                if (isRunning) {
                    return;
                }
                
                allUsers = [];
                executeQuery();
            });
            
            function executeQuery() {
                var proprietaryId = $("#proprietary-id").val().trim();
                var perPage = parseInt($("#per-page").val()) || 100;
                var resultsArea = $("#symplectic-results");
                var submitButton = $("#symplectic-submit");
                
                if (!proprietaryId) {
                    resultsArea.html("<div class=\"symplectic-error\">Please enter a Proprietary ID or \"null\" to fetch all users.</div>");
                    return;
                }
                
                var fetchAllUsers = (proprietaryId.toLowerCase() === "null");
                
                if (fetchAllUsers) {
                    // Start fetching all pages
                    isRunning = true;
                    submitButton.prop("disabled", true).text("Fetching...");
                    resultsArea.html("<div class=\"symplectic-progress\"><h4>Fetching All Users</h4><p>Starting...</p></div>");
                    fetchAllPages(1, perPage, resultsArea, submitButton);
                } else {
                    // Single user query
                    submitButton.prop("disabled", true).text("Querying...");
                    resultsArea.html("<div class=\"symplectic-loading\">Loading results...</div>");
                    fetchSingleUser(proprietaryId, resultsArea, submitButton);
                }
            }
            
            function fetchAllPages(page, perPage, resultsArea, submitButton) {
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "symplectic_query_api",
                        proprietary_id: "null",
                        per_page: perPage,
                        page: page,
                        nonce: "' . wp_create_nonce('symplectic_query_nonce') . '"
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            var users = data.users || [];
                            var pagination = data.pagination || {};
                            var totalPages = pagination.total_pages || 1;
                            var totalResults = pagination.total_results || 0;
                            
                            // Add users to our collection
                            allUsers = allUsers.concat(users);
                            
                            // Update progress
                            var progressPercent = Math.round((page / totalPages) * 100);
                            var progressHtml = "<div class=\"symplectic-progress\">";
                            progressHtml += "<h4>Fetching All Users</h4>";
                            progressHtml += "<p>Page " + page + " of " + totalPages + " (" + allUsers.length + " users fetched so far)</p>";
                            progressHtml += "<div class=\"symplectic-progress-bar\"><div class=\"symplectic-progress-bar-fill\" style=\"width: " + progressPercent + "%\"></div></div>";
                            progressHtml += "</div>";
                            resultsArea.html(progressHtml);
                            
                            // Check if there are more pages
                            if (page < totalPages) {
                                // Fetch next page
                                setTimeout(function() {
                                    fetchAllPages(page + 1, perPage, resultsArea, submitButton);
                                }, 100); // Small delay to prevent overwhelming the server
                            } else {
                                // All done - display results
                                isRunning = false;
                                submitButton.prop("disabled", false).text("Execute Query");
                                displayAllUsers(allUsers, pagination, resultsArea);
                            }
                        } else {
                            isRunning = false;
                            submitButton.prop("disabled", false).text("Execute Query");
                            displayError(response.data, resultsArea);
                        }
                    },
                    error: function(xhr, status, error) {
                        isRunning = false;
                        submitButton.prop("disabled", false).text("Execute Query");
                        
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
                    }
                });
            }
            
            function fetchSingleUser(proprietaryId, resultsArea, submitButton) {
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "symplectic_query_api",
                        proprietary_id: proprietaryId,
                        per_page: 25,
                        page: 1,
                        nonce: "' . wp_create_nonce('symplectic_query_nonce') . '"
                    },
                    success: function(response) {
                        submitButton.prop("disabled", false).text("Execute Query");
                        
                        if (response.success) {
                            var html = "<div class=\"symplectic-success\">Query successful!</div>";
                            html += "<div class=\"symplectic-json-output\">" + 
                                escapeHtml(JSON.stringify(response.data, null, 2)) + 
                            "</div>";
                            resultsArea.html(html);
                        } else {
                            displayError(response.data, resultsArea);
                        }
                    },
                    error: function(xhr, status, error) {
                        submitButton.prop("disabled", false).text("Execute Query");
                        
                        var errorMessage = "<div class=\"symplectic-error\">";
                        errorMessage += "<strong>Request Failed:</strong> " + escapeHtml(error);
                        errorMessage += "</div>";
                        resultsArea.html(errorMessage);
                    }
                });
            }
            
            function displayAllUsers(users, pagination, resultsArea) {
                var html = "";
                
                // Summary
                html += "<div class=\"symplectic-summary\">";
                html += "<h4>Fetch Complete</h4>";
                html += "<p><strong>Total Users Retrieved:</strong> " + users.length + "</p>";
                if (pagination.total_results) {
                    html += "<p><strong>Total in System:</strong> " + pagination.total_results + "</p>";
                }
                if (pagination.total_pages) {
                    html += "<p><strong>Pages Fetched:</strong> " + pagination.total_pages + "</p>";
                }
                html += "</div>";
                
                // Export buttons
                html += "<div class=\"symplectic-export-buttons\">";
                html += "<button type=\"button\" id=\"export-json\" class=\"button\">Export as JSON</button>";
                html += "<button type=\"button\" id=\"export-csv\" class=\"button\">Export as CSV</button>";
                html += "<button type=\"button\" id=\"toggle-table\" class=\"button\">Toggle Table View</button>";
                html += "<button type=\"button\" id=\"toggle-json\" class=\"button button-primary\">Toggle JSON View</button>";
                html += "</div>";
                
                // Table view (initially hidden)
                html += "<div id=\"table-view\" style=\"display: none; overflow-x: auto;\">";
                if (users.length > 0) {
                    html += buildUserTable(users);
                }
                html += "</div>";
                
                // JSON view (initially visible)
                html += "<div id=\"json-view\">";
                html += "<div class=\"symplectic-json-output\">" + escapeHtml(JSON.stringify(users, null, 2)) + "</div>";
                html += "</div>";
                
                resultsArea.html(html);
                
                // Store users data for export
                window.symplecticUsers = users;
                
                // Bind export buttons
                $("#export-json").on("click", function() {
                    exportJSON(users);
                });
                
                $("#export-csv").on("click", function() {
                    exportCSV(users);
                });
                
                $("#toggle-table").on("click", function() {
                    $("#table-view").toggle();
                });
                
                $("#toggle-json").on("click", function() {
                    $("#json-view").toggle();
                });
            }
            
            function buildUserTable(users) {
                if (users.length === 0) return "<p>No users found.</p>";
                
                // Get all unique keys from all users
                var allKeys = {};
                users.forEach(function(user) {
                    Object.keys(user).forEach(function(key) {
                        allKeys[key] = true;
                    });
                });
                var headers = Object.keys(allKeys);
                
                var html = "<table class=\"symplectic-user-table\">";
                html += "<thead><tr>";
                html += "<th>#</th>";
                headers.forEach(function(h) {
                    html += "<th>" + escapeHtml(h) + "</th>";
                });
                html += "</tr></thead>";
                html += "<tbody>";
                
                users.forEach(function(user, i) {
                    html += "<tr>";
                    html += "<td>" + (i + 1) + "</td>";
                    headers.forEach(function(h) {
                        var val = user[h];
                        if (val === null || val === undefined) {
                            val = "";
                        } else if (typeof val === "object") {
                            val = JSON.stringify(val);
                        }
                        html += "<td>" + escapeHtml(String(val)) + "</td>";
                    });
                    html += "</tr>";
                });
                
                html += "</tbody></table>";
                return html;
            }
            
            function exportJSON(users) {
                var dataStr = JSON.stringify(users, null, 2);
                var dataBlob = new Blob([dataStr], {type: "application/json"});
                var url = URL.createObjectURL(dataBlob);
                var link = document.createElement("a");
                link.href = url;
                link.download = "symplectic_users_" + new Date().toISOString().slice(0, 10) + ".json";
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            }
            
            function exportCSV(users) {
                if (users.length === 0) {
                    alert("No users to export");
                    return;
                }
                
                // Get all possible keys from all users
                var allKeys = {};
                users.forEach(function(user) {
                    Object.keys(user).forEach(function(key) {
                        allKeys[key] = true;
                    });
                });
                var headers = Object.keys(allKeys);
                
                // Build CSV
                var csv = headers.map(function(h) { return "\"" + h.replace(/"/g, "\"\"") + "\""; }).join(",") + "\n";
                
                users.forEach(function(user) {
                    var row = headers.map(function(h) {
                        var val = user[h];
                        if (val === null || val === undefined) {
                            return "";
                        }
                        if (typeof val === "object") {
                            val = JSON.stringify(val);
                        }
                        return "\"" + String(val).replace(/"/g, "\"\"") + "\"";
                    });
                    csv += row.join(",") + "\n";
                });
                
                var dataBlob = new Blob([csv], {type: "text/csv;charset=utf-8;"});
                var url = URL.createObjectURL(dataBlob);
                var link = document.createElement("a");
                link.href = url;
                link.download = "symplectic_users_" + new Date().toISOString().slice(0, 10) + ".csv";
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            }
            
            function displayError(data, resultsArea) {
                var errorHtml = "";
                
                if (typeof data === "string") {
                    errorHtml = "<div class=\"symplectic-error\">Error: " + escapeHtml(data) + "</div>";
                } else if (typeof data === "object" && data !== null) {
                    errorHtml = "<div class=\"symplectic-error\">";
                    
                    if (data.error_type) {
                        errorHtml += "<strong>" + escapeHtml(data.error_type) + "</strong>";
                        if (data.status_code) {
                            errorHtml += " (HTTP " + data.status_code + ")";
                        }
                    } else if (data.error_message) {
                        errorHtml += "<strong>Error:</strong> " + escapeHtml(data.error_message);
                    } else {
                        errorHtml += "<strong>API Request Failed</strong>";
                    }
                    
                    errorHtml += "</div>";
                    errorHtml += "<div class=\"symplectic-error-details\">";
                    
                    if (data.status_message) {
                        errorHtml += "<div class=\"symplectic-error-section\">";
                        errorHtml += "<h4>Status Message:</h4>";
                        errorHtml += "<p>" + escapeHtml(data.status_message) + "</p>";
                        errorHtml += "</div>";
                    }
                    
                    if (data.likely_causes && Array.isArray(data.likely_causes)) {
                        errorHtml += "<div class=\"symplectic-error-section\">";
                        errorHtml += "<h4>Likely Causes:</h4>";
                        errorHtml += "<ul>";
                        data.likely_causes.forEach(function(cause) {
                            errorHtml += "<li>" + escapeHtml(cause) + "</li>";
                        });
                        errorHtml += "</ul>";
                        errorHtml += "</div>";
                    }
                    
                    if (data.troubleshooting_steps && Array.isArray(data.troubleshooting_steps)) {
                        errorHtml += "<div class=\"symplectic-error-section\">";
                        errorHtml += "<h4>Troubleshooting Steps:</h4>";
                        errorHtml += "<ul>";
                        data.troubleshooting_steps.forEach(function(step) {
                            errorHtml += "<li>" + escapeHtml(step) + "</li>";
                        });
                        errorHtml += "</ul>";
                        errorHtml += "</div>";
                    }
                    
                    errorHtml += "<div class=\"symplectic-error-section\">";
                    errorHtml += "<h4>Full Error Details:</h4>";
                    errorHtml += "<details>";
                    errorHtml += "<summary style=\"cursor: pointer;\">Click to expand</summary>";
                    errorHtml += "<pre style=\"margin-top: 10px;\">" + escapeHtml(JSON.stringify(data, null, 2)) + "</pre>";
                    errorHtml += "</details>";
                    errorHtml += "</div>";
                    
                    errorHtml += "</div>";
                } else {
                    errorHtml = "<div class=\"symplectic-error\">An unknown error occurred. Please try again.</div>";
                }
                
                resultsArea.html(errorHtml);
            }
            
            function escapeHtml(text) {
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
    $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 100;
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    
    // Validate pagination parameters
    $per_page = max(1, min(100, $per_page));
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
    
    // Check if we're fetching all users (proprietary_id is "null")
    $fetch_all_users = (strtolower($proprietary_id) === 'null');
    
    // Build query parameters
    $query_params = array();
    
    if (!$fetch_all_users) {
        // Query for specific user by proprietary ID
        $query_params['query'] = 'proprietary-id="' . $proprietary_id . '"';
        $query_params['detail'] = 'full';
    } else {
        // Fetching all users - use pagination parameters
        $query_params['per-page'] = $per_page;
        $query_params['page'] = $page;
        $query_params['detail'] = 'ref';
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
            'Accept' => 'application/json, application/xml',
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
    $content_type = isset($response_headers['content-type']) ? $response_headers['content-type'] : '';
    
    // Enhanced error handling for non-200 responses
    if ($response_code !== 200) {
        $error_details = array(
            'status_code' => $response_code,
            'status_message' => wp_remote_retrieve_response_message($response),
            'response_body' => substr($response_body, 0, 1000),
            'diagnostic_info' => $diagnostic_info,
        );
        
        switch ($response_code) {
            case 401:
                $error_details['error_type'] = 'Authentication Failed';
                $error_details['likely_causes'] = array(
                    'Invalid username or password',
                    'Credentials expired or account disabled',
                );
                $error_details['troubleshooting_steps'] = array(
                    '1. Verify SYMPLECTIC_API_USERNAME and SYMPLECTIC_API_PASSWORD in wp-config.php',
                    '2. Check if credentials work in another API client (like Postman)',
                );
                break;
            case 403:
                $error_details['error_type'] = 'Access Forbidden';
                break;
            case 404:
                $error_details['error_type'] = 'Not Found';
                break;
            default:
                $error_details['error_type'] = 'HTTP Error ' . $response_code;
        }
        
        wp_send_json_error($error_details);
        return;
    }
    
    // Parse the response
    $users = array();
    $pagination_info = array(
        'page' => $page,
        'per_page' => $per_page,
        'total_results' => null,
        'total_pages' => 1,
    );
    
    // Check if response is XML
    if (strpos($content_type, 'xml') !== false || strpos($response_body, '<?xml') === 0) {
        // Parse XML response
        $parsed = symplectic_parse_xml_response($response_body);
        $users = $parsed['users'];
        $pagination_info = array_merge($pagination_info, $parsed['pagination']);
    } else {
        // Try JSON
        $data = json_decode($response_body, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            // Handle JSON response
            if (isset($data['users'])) {
                $users = $data['users'];
            } elseif (isset($data['entry'])) {
                $users = $data['entry'];
            }
            
            if (isset($data['results-count'])) {
                $pagination_info['total_results'] = (int)$data['results-count'];
            }
            if (isset($data['last-page'])) {
                $pagination_info['total_pages'] = (int)$data['last-page'];
            }
        }
    }
    
    // Return the result
    $result = array(
        'users' => $users,
        'pagination' => $pagination_info,
        'response_metadata' => array(
            'response_code' => $response_code,
            'content_type' => $content_type,
            'response_size' => strlen($response_body),
            'fetch_all_users' => $fetch_all_users,
        ),
        'diagnostic_info' => $diagnostic_info,
    );
    
    wp_send_json_success($result);
}

/**
 * Parse Symplectic Elements XML response and extract user data
 */
function symplectic_parse_xml_response($xml_string) {
    $users = array();
    $pagination = array(
        'total_results' => null,
        'total_pages' => 1,
    );
    
    // Suppress errors for malformed XML
    libxml_use_internal_errors(true);
    
    $xml = simplexml_load_string($xml_string);
    
    if ($xml === false) {
        // Return empty if XML parsing fails
        return array('users' => $users, 'pagination' => $pagination);
    }
    
    // Register namespaces that Symplectic uses
    $namespaces = $xml->getNamespaces(true);
    
    // Get pagination info from root attributes
    $attrs = $xml->attributes();
    if (isset($attrs['results-count'])) {
        $pagination['total_results'] = (int)$attrs['results-count'];
    }
    if (isset($attrs['last-page'])) {
        $pagination['total_pages'] = (int)$attrs['last-page'];
    }
    
    // Also check for api namespace attributes
    if (isset($namespaces['api'])) {
        $api_attrs = $xml->attributes($namespaces['api']);
        if (isset($api_attrs['results-count'])) {
            $pagination['total_results'] = (int)$api_attrs['results-count'];
        }
        if (isset($api_attrs['last-page'])) {
            $pagination['total_pages'] = (int)$api_attrs['last-page'];
        }
    }
    
    // Find user entries - try different possible structures
    $entries = array();
    
    // Try direct children named 'entry'
    if (isset($xml->entry) && count($xml->entry) > 0) {
        foreach ($xml->entry as $entry) {
            $entries[] = $entry;
        }
    }
    // Try 'object' elements
    elseif (isset($xml->object) && count($xml->object) > 0) {
        foreach ($xml->object as $obj) {
            $entries[] = $obj;
        }
    }
    // Try with api namespace
    elseif (isset($namespaces['api'])) {
        $xml->registerXPathNamespace('api', $namespaces['api']);
        $xpath_results = $xml->xpath('//api:object');
        if ($xpath_results) {
            $entries = $xpath_results;
        }
    }
    
    // If still no entries, try to find any child elements that might be users
    if (empty($entries)) {
        foreach ($xml->children() as $child) {
            $entries[] = $child;
        }
    }
    
    // Parse each entry
    foreach ($entries as $entry) {
        $user = symplectic_parse_user_entry($entry, $namespaces);
        if (!empty($user)) {
            $users[] = $user;
        }
    }
    
    return array('users' => $users, 'pagination' => $pagination);
}

/**
 * Parse a single user entry from XML
 */
function symplectic_parse_user_entry($entry, $namespaces = array()) {
    $user = array();
    
    // Get attributes from the entry
    $attrs = $entry->attributes();
    foreach ($attrs as $name => $value) {
        $key = str_replace('-', '_', strtolower($name));
        $user[$key] = (string)$value;
    }
    
    // Try to get api namespace attributes
    if (isset($namespaces['api'])) {
        $api_attrs = $entry->attributes($namespaces['api']);
        foreach ($api_attrs as $name => $value) {
            $key = str_replace('-', '_', strtolower($name));
            if (!isset($user[$key])) {
                $user[$key] = (string)$value;
            }
        }
    }
    
    // Get all child elements (default namespace)
    foreach ($entry->children() as $name => $value) {
        $key = str_replace('-', '_', strtolower($name));
        if (!isset($user[$key])) {
            if ($value->count() > 0) {
                // Has children - could be complex type
                $user[$key] = symplectic_xml_to_array($value);
            } else {
                $user[$key] = (string)$value;
            }
        }
    }
    
    // Also try namespaced children
    foreach ($namespaces as $prefix => $ns) {
        foreach ($entry->children($ns) as $name => $value) {
            $key = str_replace('-', '_', strtolower($name));
            if (!isset($user[$key])) {
                if ($value->count() > 0) {
                    $user[$key] = symplectic_xml_to_array($value);
                } else {
                    $user[$key] = (string)$value;
                }
            }
        }
    }
    
    return $user;
}

/**
 * Convert a SimpleXML element to an array
 */
function symplectic_xml_to_array($xml) {
    $result = array();
    
    // Get attributes
    foreach ($xml->attributes() as $name => $value) {
        $result['@' . $name] = (string)$value;
    }
    
    // Get children
    foreach ($xml->children() as $name => $value) {
        $key = str_replace('-', '_', strtolower($name));
        if ($value->count() > 0) {
            $result[$key] = symplectic_xml_to_array($value);
        } else {
            $result[$key] = (string)$value;
        }
    }
    
    // If no children and no attributes, just return the text content
    if (empty($result)) {
        return (string)$xml;
    }
    
    return $result;
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
                <p><strong>Tip:</strong> Enter <code>null</code> as the Proprietary ID to fetch <strong>all users</strong> from the system. 
                The tool will automatically iterate through all pages until every user record has been retrieved.</p>
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
                        <label for="per-page">Results Per Page (for pagination):</label>
                        <select 
                            id="per-page" 
                            name="per_page"
                            <?php echo !$credentials_configured ? 'disabled' : ''; ?>
                        >
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100" selected>100</option>
                        </select>
                        <p class="description">Number of results per API request when fetching all users.</p>
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
    return $allcaps;
}