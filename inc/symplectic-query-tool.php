<?php
/**
 * Symplectic Elements API Query Tool for WordPress Admin
 * 
 * This plugin creates an admin page to query user data from the Symplectic Elements API
 * using a proprietary ID.
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
                
                var proprietaryId = $("#proprietary-id").val().trim();
                var resultsArea = $("#symplectic-results");
                var submitButton = $("#symplectic-submit");
                
                if (!proprietaryId) {
                    resultsArea.html("<div class=\"symplectic-error\">Please enter a Proprietary ID.</div>");
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
                        proprietary_id: proprietaryId,
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
                            resultsArea.html(
                                "<div class=\"symplectic-error\">Error: " + 
                                (response.data || "Unknown error occurred") + 
                                "</div>"
                            );
                        }
                    },
                    error: function(xhr, status, error) {
                        resultsArea.html(
                            "<div class=\"symplectic-error\">Request failed: " + error + "</div>"
                        );
                    },
                    complete: function() {
                        submitButton.prop("disabled", false).text("Execute Query");
                    }
                });
            });
            
            function escapeHtml(text) {
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
    
    if (empty($proprietary_id)) {
        wp_send_json_error('Proprietary ID is required');
        return;
    }
    
    // Build the API URL
    $api_url = 'https://uga-test.elements.symplectic.org:8093/secure-api/v6.13/users';
    $api_url .= '?query=proprietary-id=%22' . urlencode($proprietary_id) . '%22&detail=full';
    
    // Get credentials from wp-config.php constants
    $username = SYMPLECTIC_API_USERNAME;
    $password = SYMPLECTIC_API_PASSWORD;
    
    // Set up the API request with authentication
    $args = array(
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
            'Accept' => 'application/json',
        ),
        'timeout' => 300,
        'sslverify' => true, // Set to true in production if SSL cert is valid
    );
    
    // Make the API request
    $response = wp_remote_get($api_url, $args);
    
    // Check for errors
    if (is_wp_error($response)) {
        wp_send_json_error('API request failed: ' . $response->get_error_message());
        return;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    if ($response_code !== 200) {
        wp_send_json_error('API returned status code: ' . $response_code);
        return;
    }
    
    // Try to parse the response as JSON
    $data = json_decode($response_body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        // If not JSON, return raw response
        wp_send_json_success($response_body);
    } else {
        // Return parsed JSON
        wp_send_json_success($data);
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
                using a proprietary ID. Enter a proprietary ID below and click "Execute Query" to retrieve 
                the full user details from the Symplectic Elements system.</p>
            </div>
            
            <form id="symplectic-query-form" method="post">
                <div class="symplectic-form-group">
                    <label for="proprietary-id">Proprietary ID:</label>
                    <input 
                        type="text" 
                        id="proprietary-id" 
                        name="proprietary_id" 
                        class="regular-text" 
                        placeholder="Enter proprietary ID (e.g., 810019979)"
                        value=""
                        <?php echo !$credentials_configured ? 'disabled' : ''; ?>
                    />
                    <p class="description">Enter the proprietary ID of the user you want to query.</p>
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
                        No query executed yet. Enter a proprietary ID above and click "Execute Query" to see results.
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