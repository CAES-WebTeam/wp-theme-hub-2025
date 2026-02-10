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

                var urlCounter = 1;

                if (diagnosticInfo.user_request_url) {
                    html += "<div class=\"symplectic-url-item\">";
                    html += "<label>" + urlCounter + ". User Query URL:</label>";
                    html += "<code>" + escapeHtml(diagnosticInfo.user_request_url) + "</code>";
                    html += "<div class=\"copy-hint\">Click the URL above to select it, then Ctrl+C to copy</div>";
                    html += "</div>";
                    urlCounter++;
                }

                if (diagnosticInfo.relationships_request_urls && diagnosticInfo.relationships_request_urls.length > 0) {
                    html += "<div class=\"symplectic-url-item\">";
                    html += "<label>" + urlCounter + ". Relationships Query URLs (" + diagnosticInfo.relationships_pages_fetched + " pages, " + diagnosticInfo.relationships_total_objects + " total objects):</label>";
                    diagnosticInfo.relationships_request_urls.forEach(function(url, index) {
                        html += "<code style=\"margin-bottom: 5px;\">" + escapeHtml(url) + "</code>";
                    });
                    html += "<div class=\"copy-hint\">Click any URL above to select it, then Ctrl+C to copy</div>";
                    html += "</div>";
                    urlCounter++;
                }

                if (diagnosticInfo.publication_request_urls && diagnosticInfo.publication_request_urls.length > 0) {
                    html += "<div class=\"symplectic-url-item\">";
                    html += "<label>" + urlCounter + ". Publication Detail URLs (" + diagnosticInfo.publication_request_urls.length + " total):</label>";
                    diagnosticInfo.publication_request_urls.forEach(function(url, index) {
                        html += "<code style=\"margin-bottom: 5px;\">" + escapeHtml(url) + "</code>";
                    });
                    html += "<div class=\"copy-hint\">Click any URL above to select it, then Ctrl+C to copy</div>";
                    html += "</div>";
                    urlCounter++;
                }

                if (diagnosticInfo.activity_request_urls && diagnosticInfo.activity_request_urls.length > 0) {
                    html += "<div class=\"symplectic-url-item\">";
                    html += "<label>" + urlCounter + ". Activity Detail URLs (" + diagnosticInfo.activity_request_urls.length + " total):</label>";
                    diagnosticInfo.activity_request_urls.forEach(function(url, index) {
                        html += "<code style=\"margin-bottom: 5px;\">" + escapeHtml(url) + "</code>";
                    });
                    html += "<div class=\"copy-hint\">Click any URL above to select it, then Ctrl+C to copy</div>";
                    html += "</div>";
                }

                // Show debug info about what was found
                if (diagnosticInfo.category_counts || diagnosticInfo.publications_found !== undefined) {
                    html += "<div style=\"margin-top: 15px; padding: 10px; background: #f0f0f0; border-radius: 3px;\">";
                    html += "<h4 style=\"margin: 0 0 8px 0; font-size: 13px;\">Debug: Objects Found in Relationships</h4>";

                    if (diagnosticInfo.publications_found !== undefined) {
                        html += "<div style=\"font-size: 12px; margin-bottom: 5px;\">";
                        html += "<strong>Publications:</strong> " + diagnosticInfo.publications_found + " ";
                        html += "<strong>Activities:</strong> " + diagnosticInfo.activities_found + " ";
                        html += "<strong>Teaching:</strong> " + diagnosticInfo.teaching_activities_found;
                        html += "</div>";
                    }

                    if (diagnosticInfo.category_counts && Object.keys(diagnosticInfo.category_counts).length > 0) {
                        html += "<div style=\"font-size: 12px;\">";
                        html += "<strong>All Categories Found:</strong> ";
                        var cats = [];
                        for (var cat in diagnosticInfo.category_counts) {
                            cats.push(cat + " (" + diagnosticInfo.category_counts[cat] + ")");
                        }
                        html += cats.join(", ");
                        html += "</div>";
                    }

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

/**
 * Helper function to extract publication fields from XML
 */
function extract_publication_fields($pub_xml) {
    $fields_data = array();

    $pub_xml->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');

    // Find the native or preferred record fields
    $records = $pub_xml->xpath('//api:record[@format="native" or @format="preferred"]');

    if (!empty($records)) {
        $record = $records[0];
        $record->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');

        // Extract fields
        $fields = $record->xpath('.//api:field');
        foreach ($fields as $field) {
            $field->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');
            $field_name = (string)$field['name'];
            $field_type = (string)$field['type'];

            // Extract value based on field type
            $field_value = null;

            if ($field_type === 'text') {
                $text_nodes = $field->xpath('./api:text');
                if (!empty($text_nodes)) {
                    $field_value = (string)$text_nodes[0];
                }
            } elseif ($field_type === 'date') {
                $date_nodes = $field->xpath('./api:date');
                if (!empty($date_nodes)) {
                    $date_node = $date_nodes[0];
                    $year = (string)$date_node->year;
                    $month = (string)$date_node->month;
                    $day = (string)$date_node->day;

                    if ($year) {
                        $field_value = $year;
                        if ($month) {
                            $field_value = $month . '/' . $field_value;
                            if ($day) {
                                $field_value = $day . '/' . $field_value;
                            }
                        }
                    }
                }
            } elseif ($field_type === 'person-list') {
                $people_nodes = $field->xpath('./api:people/api:person');
                if (!empty($people_nodes)) {
                    $authors = array();
                    foreach ($people_nodes as $person) {
                        $first_name = (string)$person->{'first-names'};
                        $last_name = (string)$person->{'last-name'};
                        if ($first_name && $last_name) {
                            $authors[] = $first_name . ' ' . $last_name;
                        } elseif ($last_name) {
                            $authors[] = $last_name;
                        }
                    }
                    $field_value = implode(', ', $authors);
                }
            }

            // Store common publication fields
            if ($field_value && $field_name === 'title') {
                $fields_data['title'] = $field_value;
            } elseif ($field_value && $field_name === 'journal') {
                $fields_data['journal'] = $field_value;
            } elseif ($field_value && $field_name === 'authors') {
                $fields_data['authors'] = $field_value;
            } elseif ($field_value && $field_name === 'publication-date') {
                $fields_data['publication_date'] = $field_value;
            } elseif ($field_value && $field_name === 'volume') {
                $fields_data['volume'] = $field_value;
            } elseif ($field_value && $field_name === 'issue') {
                $fields_data['issue'] = $field_value;
            } elseif ($field_value && $field_name === 'doi') {
                $fields_data['doi'] = $field_value;
            } elseif ($field_value && $field_name === 'abstract') {
                $fields_data['abstract'] = $field_value;
            } elseif ($field_value && $field_name === 'publisher') {
                $fields_data['publisher'] = $field_value;
            }
        }
    }

    return $fields_data;
}

/**
 * Helper function to extract activity fields from XML
 */
function extract_activity_fields($activity_xml) {
    $fields_data = array();

    $activity_xml->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');

    // Find the native or preferred record fields
    $records = $activity_xml->xpath('//api:record[@format="native" or @format="preferred"]');

    if (!empty($records)) {
        $record = $records[0];
        $record->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');

        // Extract fields
        $fields = $record->xpath('.//api:field');
        foreach ($fields as $field) {
            $field->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');
            $field_name = (string)$field['name'];
            $field_type = (string)$field['type'];

            // Extract value based on field type
            $field_value = null;

            if ($field_type === 'text') {
                $text_nodes = $field->xpath('./api:text');
                if (!empty($text_nodes)) {
                    $field_value = (string)$text_nodes[0];
                }
            } elseif ($field_type === 'date') {
                $date_nodes = $field->xpath('./api:date');
                if (!empty($date_nodes)) {
                    $date_node = $date_nodes[0];
                    $year = (string)$date_node->year;
                    $month = (string)$date_node->month;
                    $day = (string)$date_node->day;

                    if ($year) {
                        $field_value = $year;
                        if ($month) {
                            $field_value = $month . '/' . $field_value;
                            if ($day) {
                                $field_value = $day . '/' . $field_value;
                            }
                        }
                    }
                }
            }

            // Store activity fields
            if ($field_value && $field_name === 'title') {
                $fields_data['title'] = $field_value;
            } elseif ($field_value && $field_name === 'name') {
                $fields_data['name'] = $field_value;
            } elseif ($field_value && $field_name === 'start-date') {
                $fields_data['date'] = $field_value;
            } elseif ($field_value && $field_name === 'awarded-date') {
                $fields_data['date'] = $field_value;
            } elseif ($field_value && $field_name === 'description') {
                $fields_data['description'] = $field_value;
            } elseif ($field_value && $field_name === 'location') {
                $fields_data['location'] = $field_value;
            } elseif ($field_value && $field_name === 'associated-institution') {
                $fields_data['institution'] = $field_value;
            }
        }
    }

    return $fields_data;
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

    // Build API request
    $api_url = 'https://uga.elements.symplectic.org:8091/secure-api/v6.13/users?query=proprietary-id=%22' . urlencode($proprietary_id) . '%22&detail=full';

    $username = SYMPLECTIC_API_USERNAME;
    $password = SYMPLECTIC_API_PASSWORD;

    $args = array(
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
            'Accept' => 'application/xml',
        ),
        'timeout' => 30,
        'sslverify' => true,
    );

    // Make API request
    $response = wp_remote_get($api_url, $args);

    // Check for errors
    if (is_wp_error($response)) {
        wp_send_json_error(array(
            'error_message' => $response->get_error_message(),
            'api_url' => $api_url,
        ));
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    // Handle non-200 responses
    if ($response_code !== 200) {
        wp_send_json_error(array(
            'error_type' => 'HTTP Error ' . $response_code,
            'response_body' => substr($response_body, 0, 1000),
            'api_url' => $api_url,
        ));
        return;
    }

    // Parse XML response using SimpleXML
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($response_body);

    if ($xml === false) {
        wp_send_json_error(array(
            'error_type' => 'XML Parse Error',
            'raw_response' => substr($response_body, 0, 1000),
            'api_url' => $api_url,
        ));
        return;
    }

    // Register namespace and extract user object
    $xml->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');
    $objects = $xml->xpath('//api:object[@category="user"]');

    if (empty($objects)) {
        wp_send_json_error(array(
            'error_type' => 'No user object found',
            'raw_response' => substr($response_body, 0, 1000),
            'api_url' => $api_url,
        ));
        return;
    }

    // Extract user data from attributes of the first object
    $object = $objects[0];
    $user_info = array();

    // Get all attributes
    foreach ($object->attributes() as $attr_name => $attr_value) {
        $user_info[$attr_name] = (string)$attr_value;
    }

    // Get user ID for relationships call
    $user_id = $user_info['id'];

    // Initialize result arrays
    $publications = array();
    $activities = array();
    $teaching_activities = array();
    $relationships_error = null;
    $publication_urls = array();
    $publication_raw_responses = array();
    $activity_urls = array();
    $activity_raw_responses = array();

    // Step 2: Try to get relationships (but don't fail if this errors)
    if ($user_id) {
        // Increase execution time limit to handle large datasets
        @set_time_limit(300); // 5 minutes max

        // Initialize pagination with smaller batch size to avoid timeouts
        $relationships_url = 'https://uga.elements.symplectic.org:8091/secure-api/v6.13/users/' . $user_id . '/relationships?per-page=100';
        $all_relationships_urls = array();
        $page_count = 0;
        $total_objects_processed = 0;
        $max_pages = 10; // Limit to prevent infinite loops or excessive processing

        // Track categories for debugging
        $category_counts = array();

        // Pagination loop - fetch and process pages incrementally
        do {
            $has_next_page = false;
            $next_page_url = null;
            $page_count++;

            // Safety check: don't process more than max_pages
            if ($page_count > $max_pages) {
                $relationships_error = 'Reached maximum page limit (' . $max_pages . ' pages). Displaying first ' . $total_objects_processed . ' relationships.';
                break;
            }

            // Track this URL for debugging
            $all_relationships_urls[] = $relationships_url;

            $relationships_response = wp_remote_get($relationships_url, $args);

            if (!is_wp_error($relationships_response) && wp_remote_retrieve_response_code($relationships_response) === 200) {
                $rel_body = wp_remote_retrieve_body($relationships_response);

                libxml_use_internal_errors(true);
                $rel_xml = simplexml_load_string($rel_body);

                if ($rel_xml !== false) {
                    $rel_xml->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');

                    // Get objects from THIS PAGE ONLY
                    $page_objects = $rel_xml->xpath('//api:object');

                    // Process this page's objects immediately before fetching next page
                    if (!empty($page_objects)) {
                        foreach ($page_objects as $rel_object) {
                            $obj_data = array();

                            // Get all attributes
                            foreach ($rel_object->attributes() as $attr_name => $attr_value) {
                                $obj_data[$attr_name] = (string)$attr_value;
                            }

                            // Sort by category
                            $category = isset($obj_data['category']) ? $obj_data['category'] : null;

                            // Track category counts for debugging
                            if ($category) {
                                if (!isset($category_counts[$category])) {
                                    $category_counts[$category] = 0;
                                }
                                $category_counts[$category]++;
                            }

                            // Categorize and immediately fetch details for this object
                            if ($category === 'publication' && isset($obj_data['href'])) {
                                // Track URL
                                $publication_urls[] = $obj_data['href'];

                                // Fetch publication details immediately
                                $pub_response = wp_remote_get($obj_data['href'], $args);

                                if (!is_wp_error($pub_response) && wp_remote_retrieve_response_code($pub_response) === 200) {
                                    $pub_body = wp_remote_retrieve_body($pub_response);
                                    $pub_xml = simplexml_load_string($pub_body);

                                    if ($pub_xml !== false) {
                                        $obj_data = array_merge($obj_data, extract_publication_fields($pub_xml));
                                    }
                                }

                                $publications[] = $obj_data;

                            } elseif ($category === 'activity' && isset($obj_data['href'])) {
                                // Track URL
                                $activity_urls[] = $obj_data['href'];

                                // Fetch activity details immediately
                                $activity_response = wp_remote_get($obj_data['href'], $args);

                                if (!is_wp_error($activity_response) && wp_remote_retrieve_response_code($activity_response) === 200) {
                                    $activity_body = wp_remote_retrieve_body($activity_response);
                                    $activity_xml = simplexml_load_string($activity_body);

                                    if ($activity_xml !== false) {
                                        $obj_data = array_merge($obj_data, extract_activity_fields($activity_xml));
                                    }
                                }

                                $activities[] = $obj_data;

                            } elseif ($category === 'teaching-activity') {
                                $teaching_activities[] = $obj_data;
                            }

                            $total_objects_processed++;
                        }

                        // Free memory after processing this batch
                        unset($page_objects);
                    }

                    // Check for pagination info to see if there's a next page
                    $pagination_nodes = $rel_xml->xpath('//api:pagination');
                    if (!empty($pagination_nodes)) {
                        $pagination = $pagination_nodes[0];
                        $pagination->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');

                        // Look for the next page link
                        $next_links = $pagination->xpath('./api:page[@position="next"]');
                        if (!empty($next_links)) {
                            $next_page_url = (string)$next_links[0]['href'];
                            if ($next_page_url) {
                                $has_next_page = true;
                                $relationships_url = $next_page_url;
                            }
                        }
                    }

                    // Free memory
                    unset($rel_xml);
                } else {
                    // Failed to parse XML, stop pagination
                    $relationships_error = 'Failed to parse relationships XML response on page ' . $page_count;
                    break;
                }
            } else {
                // API error, stop pagination
                if (is_wp_error($relationships_response)) {
                    $relationships_error = $relationships_response->get_error_message();
                } else {
                    $relationships_error = 'Relationships API returned HTTP ' . wp_remote_retrieve_response_code($relationships_response) . ' on page ' . $page_count;
                }
                break;
            }
        } while ($has_next_page);

    }

    // Return success with user data (and relationships if available)
    $result = array(
        'user_info' => $user_info,
        'publications' => $publications,
        'activities' => $activities,
        'teaching_activities' => $teaching_activities,
        'raw_user_data' => $user_info,
        'publication_raw_responses' => $publication_raw_responses,
        'activity_raw_responses' => $activity_raw_responses,
        'diagnostic_info' => array(
            'user_request_url' => $api_url,
            'relationships_request_urls' => isset($all_relationships_urls) ? $all_relationships_urls : array(),
            'relationships_pages_fetched' => isset($page_count) ? $page_count : 0,
            'relationships_total_objects' => isset($total_objects_processed) ? $total_objects_processed : 0,
            'publication_request_urls' => $publication_urls,
            'activity_request_urls' => $activity_urls,
            'relationships_error' => $relationships_error,
            'category_counts' => isset($category_counts) ? $category_counts : array(),
            'publications_found' => count($publications),
            'activities_found' => count($activities),
            'teaching_activities_found' => count($teaching_activities),
            'timestamp' => current_time('mysql'),
        ),
    );

    wp_send_json_success($result);
}

// Render the admin page
function symplectic_query_tool_render_page() {
    // Check if credentials are configured
    $credentials_configured = defined('SYMPLECTIC_API_USERNAME') && defined('SYMPLECTIC_API_PASSWORD');
    ?>
    <div class="wrap">
        <h1>Symplectic Elements User Query Tool <span style="font-size: 0.6em; color: #666;">v1.8</span></h1>
        
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