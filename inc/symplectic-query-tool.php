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
            max-width: 1000px;
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
        .symplectic-section {
            margin-top: 25px;
            padding: 15px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .symplectic-section h3 {
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #0073aa;
            color: #23282d;
            font-size: 16px;
        }
        .symplectic-section-count {
            font-weight: normal;
            color: #666;
            font-size: 14px;
        }
        .symplectic-item {
            padding: 12px;
            margin-bottom: 10px;
            background: #f9f9f9;
            border: 1px solid #e5e5e5;
            border-radius: 3px;
        }
        .symplectic-item:last-child {
            margin-bottom: 0;
        }
        .symplectic-item-title {
            font-weight: 600;
            color: #23282d;
            margin-bottom: 5px;
        }
        .symplectic-item-meta {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .symplectic-item-meta span {
            margin-right: 15px;
        }
        .symplectic-user-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .symplectic-user-field {
            padding: 8px;
            background: #f9f9f9;
            border-radius: 3px;
        }
        .symplectic-user-field label {
            display: block;
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .symplectic-user-field value {
            display: block;
            font-weight: 500;
            color: #23282d;
        }
        .symplectic-no-data {
            color: #666;
            font-style: italic;
            padding: 10px;
        }
        .symplectic-tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 0;
        }
        .symplectic-tab {
            padding: 10px 20px;
            cursor: pointer;
            border: 1px solid transparent;
            border-bottom: none;
            margin-bottom: -1px;
            background: #f5f5f5;
            margin-right: 5px;
            border-radius: 3px 3px 0 0;
        }
        .symplectic-tab.active {
            background: #fff;
            border-color: #ddd;
            font-weight: 600;
        }
        .symplectic-tab-content {
            display: none;
            padding: 15px;
            background: #fff;
            border: 1px solid #ddd;
            border-top: none;
        }
        .symplectic-tab-content.active {
            display: block;
        }
        .symplectic-debug-urls {
            margin-top: 15px;
            padding: 15px;
            background: #fffbcc;
            border: 1px solid #e6db55;
            border-radius: 5px;
        }
        .symplectic-debug-urls h4 {
            margin: 0 0 10px 0;
            color: #826200;
        }
        .symplectic-url-item {
            margin-bottom: 10px;
            padding: 10px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .symplectic-url-item label {
            display: block;
            font-weight: 600;
            color: #23282d;
            margin-bottom: 5px;
        }
        .symplectic-url-item code {
            display: block;
            padding: 8px;
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 3px;
            word-break: break-all;
            font-size: 12px;
            user-select: all;
        }
        .symplectic-url-item .copy-hint {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
            font-style: italic;
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
                resultsArea.html("<div class=\"symplectic-loading\">Loading user data and related records...</div>");

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
                            var data = response.data;
                            var html = "<div class=\"symplectic-success\">Query successful!</div>";

                            // Always show debug URLs first
                            html += renderDebugUrls(data.diagnostic_info);

                            // Build tabbed interface
                            html += "<div class=\"symplectic-tabs\">";
                            html += "<div class=\"symplectic-tab active\" data-tab=\"user\">User Info</div>";
                            html += "<div class=\"symplectic-tab\" data-tab=\"publications\">Scholarly Works (" + (data.publications ? data.publications.length : 0) + ")</div>";
                            html += "<div class=\"symplectic-tab\" data-tab=\"activities\">Distinctions & Awards (" + (data.activities ? data.activities.length : 0) + ")</div>";
                            html += "<div class=\"symplectic-tab\" data-tab=\"teaching\">Courses Taught (" + (data.teaching_activities ? data.teaching_activities.length : 0) + ")</div>";
                            html += "<div class=\"symplectic-tab\" data-tab=\"raw\">Raw JSON</div>";
                            html += "</div>";

                            // User Info Tab
                            html += "<div class=\"symplectic-tab-content active\" data-tab=\"user\">";
                            html += renderUserInfo(data.user_info);
                            html += "</div>";

                            // Publications Tab
                            html += "<div class=\"symplectic-tab-content\" data-tab=\"publications\">";
                            html += renderPublications(data.publications);
                            html += "</div>";

                            // Activities Tab (Distinctions & Awards)
                            html += "<div class=\"symplectic-tab-content\" data-tab=\"activities\">";
                            html += renderActivities(data.activities);
                            html += "</div>";

                            // Teaching Activities Tab
                            html += "<div class=\"symplectic-tab-content\" data-tab=\"teaching\">";
                            html += renderTeachingActivities(data.teaching_activities);
                            html += "</div>";

                            // Raw JSON Tab
                            html += "<div class=\"symplectic-tab-content\" data-tab=\"raw\">";
                            html += "<div class=\"symplectic-json-output\">" + escapeHtml(JSON.stringify(data, null, 2)) + "</div>";
                            html += "</div>";

                            resultsArea.html(html);

                            // Tab click handlers
                            $(".symplectic-tab").on("click", function() {
                                var tab = $(this).data("tab");
                                $(".symplectic-tab").removeClass("active");
                                $(this).addClass("active");
                                $(".symplectic-tab-content").removeClass("active");
                                $(".symplectic-tab-content[data-tab=\"" + tab + "\"]").addClass("active");
                            });
                        } else {
                            // Handle error response with detailed information
                            var errorHtml = "";

                            // Check if response.data is a string or object
                            if (typeof response.data === "string") {
                                errorHtml = "<div class=\"symplectic-error\">Error: " + escapeHtml(response.data) + "</div>";
                            } else if (typeof response.data === "object" && response.data !== null) {
                                // Show debug URLs first if available
                                if (response.data.diagnostic_info) {
                                    errorHtml += renderDebugUrls(response.data.diagnostic_info);
                                }
                                // Build detailed error display
                                errorHtml += "<div class=\"symplectic-error\">";

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

            function renderDebugUrls(diagnosticInfo) {
                if (!diagnosticInfo) return "";

                var html = "<div class=\"symplectic-debug-urls\">";
                html += "<h4>API URLs Queried (click to select, then copy)</h4>";

                if (diagnosticInfo.user_request_url) {
                    html += "<div class=\"symplectic-url-item\">";
                    html += "<label>1. User Query URL:</label>";
                    html += "<code>" + escapeHtml(diagnosticInfo.user_request_url) + "</code>";
                    html += "<div class=\"copy-hint\">Click the URL above to select it, then Ctrl+C to copy</div>";
                    html += "</div>";
                }

                if (diagnosticInfo.relationships_request_url) {
                    html += "<div class=\"symplectic-url-item\">";
                    html += "<label>2. Relationships Query URL:</label>";
                    html += "<code>" + escapeHtml(diagnosticInfo.relationships_request_url) + "</code>";
                    html += "<div class=\"copy-hint\">Click the URL above to select it, then Ctrl+C to copy</div>";
                    html += "</div>";
                }

                if (diagnosticInfo.timestamp) {
                    html += "<div style=\"font-size: 11px; color: #666; margin-top: 10px;\">Query executed at: " + escapeHtml(diagnosticInfo.timestamp) + "</div>";
                }

                html += "</div>";
                return html;
            }

            function renderUserInfo(user) {
                if (!user) return "<div class=\"symplectic-no-data\">No user information available.</div>";

                var html = "<div class=\"symplectic-section\">";
                html += "<h3>User Information</h3>";
                html += "<div class=\"symplectic-user-info\">";

                var fields = [
                    {label: "Name", value: (user.title || "") + " " + (user.first_name || "") + " " + (user.last_name || "")},
                    {label: "Elements ID", value: user.id},
                    {label: "Proprietary ID", value: user.proprietary_id},
                    {label: "Username", value: user.username},
                    {label: "Email", value: user.email},
                    {label: "Position", value: user.position},
                    {label: "Department", value: user.department},
                    {label: "Primary Group", value: user.primary_group}
                ];

                fields.forEach(function(field) {
                    if (field.value && field.value.trim() !== "") {
                        html += "<div class=\"symplectic-user-field\">";
                        html += "<label>" + escapeHtml(field.label) + "</label>";
                        html += "<value>" + escapeHtml(field.value) + "</value>";
                        html += "</div>";
                    }
                });

                html += "</div></div>";
                return html;
            }

            function renderPublications(publications) {
                if (!publications || publications.length === 0) {
                    return "<div class=\"symplectic-no-data\">No scholarly works found for this user.</div>";
                }

                var html = "<div class=\"symplectic-section\">";
                html += "<h3>Scholarly and Creative Works <span class=\"symplectic-section-count\">(" + publications.length + " items)</span></h3>";

                publications.forEach(function(pub) {
                    html += "<div class=\"symplectic-item\">";
                    html += "<div class=\"symplectic-item-title\">" + escapeHtml(pub.title || "Untitled") + "</div>";
                    html += "<div class=\"symplectic-item-meta\">";
                    if (pub.type) html += "<span><strong>Type:</strong> " + escapeHtml(pub.type) + "</span>";
                    if (pub.publication_date) html += "<span><strong>Date:</strong> " + escapeHtml(pub.publication_date) + "</span>";
                    if (pub.journal) html += "<span><strong>Journal:</strong> " + escapeHtml(pub.journal) + "</span>";
                    if (pub.doi) html += "<span><strong>DOI:</strong> " + escapeHtml(pub.doi) + "</span>";
                    html += "</div>";
                    html += "</div>";
                });

                html += "</div>";
                return html;
            }

            function renderActivities(activities) {
                if (!activities || activities.length === 0) {
                    return "<div class=\"symplectic-no-data\">No distinctions or awards found for this user.</div>";
                }

                var html = "<div class=\"symplectic-section\">";
                html += "<h3>Distinctions and Awards <span class=\"symplectic-section-count\">(" + activities.length + " items)</span></h3>";

                activities.forEach(function(activity) {
                    html += "<div class=\"symplectic-item\">";
                    html += "<div class=\"symplectic-item-title\">" + escapeHtml(activity.title || "Untitled Activity") + "</div>";
                    html += "<div class=\"symplectic-item-meta\">";
                    if (activity.type) html += "<span><strong>Type:</strong> " + escapeHtml(activity.type) + "</span>";
                    if (activity.date) html += "<span><strong>Date:</strong> " + escapeHtml(activity.date) + "</span>";
                    if (activity.description) html += "<div style=\"margin-top: 8px;\">" + escapeHtml(activity.description) + "</div>";
                    html += "</div>";
                    html += "</div>";
                });

                html += "</div>";
                return html;
            }

            function renderTeachingActivities(activities) {
                if (!activities || activities.length === 0) {
                    return "<div class=\"symplectic-no-data\">No courses taught found for this user.</div>";
                }

                var html = "<div class=\"symplectic-section\">";
                html += "<h3>Courses Taught <span class=\"symplectic-section-count\">(" + activities.length + " items)</span></h3>";

                activities.forEach(function(course) {
                    html += "<div class=\"symplectic-item\">";
                    html += "<div class=\"symplectic-item-title\">" + escapeHtml(course.title || "Untitled Course") + "</div>";
                    html += "<div class=\"symplectic-item-meta\">";
                    if (course.course_code) html += "<span><strong>Code:</strong> " + escapeHtml(course.course_code) + "</span>";
                    if (course.academic_year) html += "<span><strong>Year:</strong> " + escapeHtml(course.academic_year) + "</span>";
                    if (course.term) html += "<span><strong>Term:</strong> " + escapeHtml(course.term) + "</span>";
                    if (course.role) html += "<span><strong>Role:</strong> " + escapeHtml(course.role) + "</span>";
                    html += "</div>";
                    html += "</div>";
                });

                html += "</div>";
                return html;
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

/**
 * Helper function to make authenticated API requests to Symplectic Elements
 */
function symplectic_api_request($url) {
    $username = SYMPLECTIC_API_USERNAME;
    $password = SYMPLECTIC_API_PASSWORD;
    $auth_string = base64_encode($username . ':' . $password);

    $args = array(
        'headers' => array(
            'Authorization' => 'Basic ' . $auth_string,
            'Accept' => 'application/json',
        ),
        'timeout' => 300,
        'sslverify' => true,
    );

    return wp_remote_get($url, $args);
}

/**
 * Extract user information from API response
 */
function symplectic_extract_user_info($user_data) {
    if (empty($user_data)) return null;

    return array(
        'id' => isset($user_data['id']) ? $user_data['id'] : null,
        'proprietary_id' => isset($user_data['proprietary-id']) ? $user_data['proprietary-id'] : null,
        'username' => isset($user_data['username']) ? $user_data['username'] : null,
        'title' => isset($user_data['title']) ? $user_data['title'] : null,
        'first_name' => isset($user_data['first-name']) ? $user_data['first-name'] : null,
        'last_name' => isset($user_data['last-name']) ? $user_data['last-name'] : null,
        'email' => isset($user_data['email-address']) ? $user_data['email-address'] : null,
        'position' => isset($user_data['position']) ? $user_data['position'] : null,
        'department' => isset($user_data['department']) ? $user_data['department'] : null,
        'primary_group' => isset($user_data['primary-group-descriptor']) ? $user_data['primary-group-descriptor'] : null,
    );
}

/**
 * Extract publications from relationships response
 */
function symplectic_extract_publications($relationships) {
    $publications = array();

    if (empty($relationships)) return $publications;

    foreach ($relationships as $rel) {
        // Check if this is a publication relationship
        $category = isset($rel['related']['category']) ? $rel['related']['category'] : null;
        if ($category !== 'publication') continue;

        $object = isset($rel['related']['object']) ? $rel['related']['object'] : null;
        if (empty($object)) continue;

        $pub = array(
            'id' => isset($object['id']) ? $object['id'] : null,
            'title' => null,
            'type' => isset($object['type-display-name']) ? $object['type-display-name'] : (isset($object['type']) ? $object['type'] : null),
            'publication_date' => null,
            'journal' => null,
            'doi' => null,
        );

        // Extract fields from records
        if (isset($object['records']) && is_array($object['records'])) {
            foreach ($object['records'] as $record) {
                if (isset($record['native'])) {
                    $native = $record['native'];

                    // Title
                    if (empty($pub['title']) && isset($native['title']['text'])) {
                        $pub['title'] = $native['title']['text'];
                    }

                    // Publication date
                    if (empty($pub['publication_date']) && isset($native['publication-date']['date'])) {
                        $date = $native['publication-date']['date'];
                        $pub['publication_date'] = symplectic_format_date($date);
                    }

                    // Journal
                    if (empty($pub['journal']) && isset($native['journal']['text'])) {
                        $pub['journal'] = $native['journal']['text'];
                    }

                    // DOI
                    if (empty($pub['doi']) && isset($native['doi']['text'])) {
                        $pub['doi'] = $native['doi']['text'];
                    }
                }
            }
        }

        $publications[] = $pub;
    }

    return $publications;
}

/**
 * Extract activities (distinctions/awards) from relationships response
 */
function symplectic_extract_activities($relationships) {
    $activities = array();

    if (empty($relationships)) return $activities;

    foreach ($relationships as $rel) {
        // Check if this is an activity relationship
        $category = isset($rel['related']['category']) ? $rel['related']['category'] : null;
        if ($category !== 'activity') continue;

        $object = isset($rel['related']['object']) ? $rel['related']['object'] : null;
        if (empty($object)) continue;

        $activity = array(
            'id' => isset($object['id']) ? $object['id'] : null,
            'title' => null,
            'type' => isset($object['type-display-name']) ? $object['type-display-name'] : (isset($object['type']) ? $object['type'] : null),
            'date' => null,
            'description' => null,
        );

        // Extract fields from records
        if (isset($object['records']) && is_array($object['records'])) {
            foreach ($object['records'] as $record) {
                if (isset($record['native'])) {
                    $native = $record['native'];

                    // Title
                    if (empty($activity['title']) && isset($native['title']['text'])) {
                        $activity['title'] = $native['title']['text'];
                    }

                    // Date
                    if (empty($activity['date']) && isset($native['start-date']['date'])) {
                        $activity['date'] = symplectic_format_date($native['start-date']['date']);
                    }

                    // Description
                    if (empty($activity['description']) && isset($native['description']['text'])) {
                        $activity['description'] = $native['description']['text'];
                    }
                }
            }
        }

        $activities[] = $activity;
    }

    return $activities;
}

/**
 * Extract teaching activities (courses) from relationships response
 */
function symplectic_extract_teaching_activities($relationships) {
    $teaching = array();

    if (empty($relationships)) return $teaching;

    foreach ($relationships as $rel) {
        // Check if this is a teaching-activity relationship
        $category = isset($rel['related']['category']) ? $rel['related']['category'] : null;
        if ($category !== 'teaching-activity') continue;

        $object = isset($rel['related']['object']) ? $rel['related']['object'] : null;
        if (empty($object)) continue;

        $course = array(
            'id' => isset($object['id']) ? $object['id'] : null,
            'title' => null,
            'course_code' => null,
            'academic_year' => null,
            'term' => null,
            'role' => null,
        );

        // Extract fields from records
        if (isset($object['records']) && is_array($object['records'])) {
            foreach ($object['records'] as $record) {
                if (isset($record['native'])) {
                    $native = $record['native'];

                    // Title/Course name
                    if (empty($course['title']) && isset($native['title']['text'])) {
                        $course['title'] = $native['title']['text'];
                    }
                    if (empty($course['title']) && isset($native['course-name']['text'])) {
                        $course['title'] = $native['course-name']['text'];
                    }

                    // Course code
                    if (empty($course['course_code']) && isset($native['course-code']['text'])) {
                        $course['course_code'] = $native['course-code']['text'];
                    }

                    // Academic year
                    if (empty($course['academic_year']) && isset($native['academic-year']['text'])) {
                        $course['academic_year'] = $native['academic-year']['text'];
                    }

                    // Term
                    if (empty($course['term']) && isset($native['term']['text'])) {
                        $course['term'] = $native['term']['text'];
                    }

                    // Role
                    if (empty($course['role']) && isset($native['role']['text'])) {
                        $course['role'] = $native['role']['text'];
                    }
                }
            }
        }

        $teaching[] = $course;
    }

    return $teaching;
}

/**
 * Format date from API response
 */
function symplectic_format_date($date) {
    if (empty($date)) return null;

    $parts = array();
    if (isset($date['year'])) $parts[] = $date['year'];
    if (isset($date['month'])) array_unshift($parts, str_pad($date['month'], 2, '0', STR_PAD_LEFT));
    if (isset($date['day'])) array_unshift($parts, str_pad($date['day'], 2, '0', STR_PAD_LEFT));

    if (count($parts) === 3) {
        return $parts[0] . '/' . $parts[1] . '/' . $parts[2]; // DD/MM/YYYY
    } elseif (count($parts) === 2) {
        return $parts[0] . '/' . $parts[1]; // MM/YYYY
    } elseif (count($parts) === 1) {
        return $parts[0]; // YYYY
    }

    return null;
}

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

    // Base API URL
    $api_base = 'https://uga.elements.symplectic.org:8091/secure-api/v6.13';

    // Step 1: Query for the user
    $user_url = $api_base . '/users?query=proprietary-id=%22' . urlencode($proprietary_id) . '%22&detail=full&format=json';

    $diagnostic_info = array(
        'user_request_url' => $user_url,
        'timestamp' => current_time('mysql'),
    );

    $user_response = symplectic_api_request($user_url);

    // Check for errors
    if (is_wp_error($user_response)) {
        $error_data = array(
            'error_message' => $user_response->get_error_message(),
            'error_code' => $user_response->get_error_code(),
            'diagnostic_info' => $diagnostic_info,
        );
        wp_send_json_error($error_data);
        return;
    }

    $response_code = wp_remote_retrieve_response_code($user_response);
    $response_body = wp_remote_retrieve_body($user_response);
    $response_headers = wp_remote_retrieve_headers($user_response);

    // Handle non-200 responses
    if ($response_code !== 200) {
        $error_details = array(
            'status_code' => $response_code,
            'status_message' => wp_remote_retrieve_response_message($user_response),
            'response_body' => $response_body,
            'diagnostic_info' => $diagnostic_info,
        );

        switch ($response_code) {
            case 401:
                $error_details['error_type'] = 'Authentication Failed';
                $error_details['likely_causes'] = array(
                    'Invalid username or password',
                    'Credentials expired or account disabled',
                    'Account lacks API access permissions',
                );
                $error_details['troubleshooting_steps'] = array(
                    '1. Verify SYMPLECTIC_API_USERNAME and SYMPLECTIC_API_PASSWORD in wp-config.php',
                    '2. Check if credentials work in another API client (like Postman)',
                    '3. Contact Symplectic Elements administrator to verify account status',
                );
                break;
            case 404:
                $error_details['error_type'] = 'Not Found';
                $error_details['likely_causes'] = array(
                    'User with this proprietary ID does not exist',
                    'API endpoint URL is incorrect',
                );
                break;
            default:
                $error_details['error_type'] = 'HTTP Error ' . $response_code;
        }

        wp_send_json_error($error_details);
        return;
    }

    // Parse user response
    $user_data = json_decode($response_body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error(array(
            'error_type' => 'JSON Parse Error',
            'error_message' => 'Failed to parse API response as JSON',
            'raw_response' => substr($response_body, 0, 500),
        ));
        return;
    }

    // Extract user from results
    $user = null;
    $user_id = null;

    if (isset($user_data['results']) && is_array($user_data['results']) && count($user_data['results']) > 0) {
        $user = $user_data['results'][0];
        $user_id = isset($user['id']) ? $user['id'] : null;
    }

    if (!$user_id) {
        wp_send_json_error(array(
            'error_type' => 'User Not Found',
            'error_message' => 'No user found with proprietary ID: ' . $proprietary_id,
        ));
        return;
    }

    // Extract user info
    $user_info = symplectic_extract_user_info($user);

    // Initialize empty arrays for related data (not querying relationships for now)
    $publications = array();
    $activities = array();
    $teaching_activities = array();

    // Build final response
    $result = array(
        'user_info' => $user_info,
        'publications' => $publications,
        'activities' => $activities,
        'teaching_activities' => $teaching_activities,
        'raw_user_data' => $user,
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
                <p><strong>Purpose:</strong> This tool queries user information from the Symplectic Elements API
                using a proprietary ID. It retrieves:</p>
                <ul style="margin-left: 20px; list-style-type: disc;">
                    <li>Basic user profile information</li>
                    <li>Scholarly and creative works (publications)</li>
                    <li>Distinctions and awards (activities)</li>
                    <li>Courses taught (teaching activities)</li>
                </ul>
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
                        No query executed yet. Enter a proprietary ID above and click "Execute Query" to see user information, scholarly works, distinctions/awards, and courses taught.
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