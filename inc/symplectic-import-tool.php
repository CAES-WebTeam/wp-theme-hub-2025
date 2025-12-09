<?php

/**
 * ---------------------------------------------------------------------------------
 * Symplectic API Development & Testing Tool
 * ---------------------------------------------------------------------------------
 * 
 * This script provides a development interface for testing the Symplectic Elements
 * API integration. It allows testing individual API calls, batch processing of
 * WordPress users, and provides detailed logging of all operations.
 * 
 * WORKFLOW:
 * 1. Get WordPress users with 'personnel_user' role
 * 2. Extract College ID from each user's ACF fields
 * 3. Query internal REST API to get MyID using College ID
 * 4. Query Symplectic API using MyID (as 'username' parameter)
 * 5. Store returned data in custom user fields
 * 
 * @package CAES Theme
 * @since 1.0.0
 */

// Exit if accessed directly to prevent unauthorized access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * ---------------------------------------------------------------------------------
 * 1. Configuration Constants
 * ---------------------------------------------------------------------------------
 * Define API endpoints and configuration. Credentials should be in wp-config.php
 */

// Internal Personnel REST API
define('CAES_PERSONNEL_API_BASE', 'https://secure.caes.uga.edu/rest/personnel/Personnel');

// Symplectic Elements API
define('SYMPLECTIC_API_BASE', 'https://uga.elements.symplectic.org:8091/secure-api/v6.13');

/**
 * ---------------------------------------------------------------------------------
 * 2. Global Variables for Logging
 * ---------------------------------------------------------------------------------
 */
global $symplectic_log_messages, $symplectic_errors;
$symplectic_log_messages = [];
$symplectic_errors = [];

/**
 * ---------------------------------------------------------------------------------
 * 3. Logging Helper Functions
 * ---------------------------------------------------------------------------------
 */

/**
 * Outputs a message to the logging console with timestamp and type styling
 * 
 * @param string $message The message to log
 * @param string $type    Message type: 'info', 'success', 'error', 'warning', 'debug'
 */
function symplectic_log($message, $type = 'info') {
    global $symplectic_log_messages;
    
    $timestamp = date('H:i:s');
    $formatted_message = "[{$timestamp}] {$message}";
    $symplectic_log_messages[] = ['message' => $formatted_message, 'type' => $type];
    
    // Also log to WordPress error log for debugging
    error_log("[Symplectic Dev] {$message}");
    
    // Output to page immediately for real-time display
    $css_class = match($type) {
        'error' => 'log-error',
        'success' => 'log-success',
        'warning' => 'log-warning',
        'debug' => 'log-debug',
        default => 'log-info'
    };
    
    echo '<div class="log-entry ' . esc_attr($css_class) . '">' . esc_html($formatted_message) . '</div>';
    ob_flush();
    flush();
}

/**
 * Records an error for the summary display
 * 
 * @param string $identifier User or operation identifier
 * @param string $reason     Error reason/description
 * @param mixed  $raw_data   Optional raw data for debugging
 */
function symplectic_record_error($identifier, $reason, $raw_data = null) {
    global $symplectic_errors;
    
    $symplectic_errors[] = [
        'identifier' => $identifier,
        'error' => $reason,
        'data' => $raw_data,
        'timestamp' => date('H:i:s')
    ];
}

/**
 * Displays a comprehensive error summary
 * 
 * @param string $operation_name Name of the operation for the summary header
 */
function symplectic_display_error_summary($operation_name) {
    global $symplectic_errors;
    
    if (empty($symplectic_errors)) {
        symplectic_log("✅ {$operation_name}: All operations completed successfully - no errors!", 'success');
        return;
    }
    
    $error_count = count($symplectic_errors);
    symplectic_log("⚠️ {$operation_name}: {$error_count} error(s) encountered. See summary below.", 'warning');
    
    echo '<div class="error-summary">';
    echo '<h3>Error Summary for ' . esc_html($operation_name) . '</h3>';
    echo '<div class="error-list">';
    
    foreach ($symplectic_errors as $index => $error) {
        echo '<div class="error-item">';
        echo '<strong>Error #' . ($index + 1) . ' [' . esc_html($error['timestamp']) . ']:</strong> ';
        echo esc_html($error['identifier']) . '<br>';
        echo '<span class="error-reason">Reason: ' . esc_html($error['error']) . '</span>';
        
        if ($error['data']) {
            echo '<details class="error-data">';
            echo '<summary>View Raw Data</summary>';
            echo '<pre>' . esc_html(json_encode($error['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>';
            echo '</details>';
        }
        echo '</div>';
    }
    
    echo '</div></div>';
    
    // Reset errors for next operation
    $symplectic_errors = [];
}

/**
 * ---------------------------------------------------------------------------------
 * 4. API Helper Functions
 * ---------------------------------------------------------------------------------
 */

/**
 * Fetches user data from the internal CAES Personnel API using College ID
 * 
 * @param int $college_id The College ID to look up
 * @return array|WP_Error User data array on success, WP_Error on failure
 */
function fetch_personnel_by_college_id($college_id) {
    $api_url = CAES_PERSONNEL_API_BASE . '?returnContactInfoColumns=true&COLLEGEID=' . intval($college_id);
    
    symplectic_log("📡 Querying CAES Personnel API: {$api_url}", 'debug');
    
    $response = wp_remote_get($api_url, [
        'timeout' => 30,
        'sslverify' => true,
    ]);
    
    if (is_wp_error($response)) {
        return new WP_Error('api_error', 'Personnel API request failed: ' . $response->get_error_message());
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        return new WP_Error('api_error', "Personnel API returned HTTP {$response_code}");
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('parse_error', 'Failed to parse Personnel API response: ' . json_last_error_msg());
    }
    
    return $data;
}

/**
 * Fetches user data from the Symplectic Elements API using MyID (username)
 * 
 * @param string $my_id      The MyID (UGA username) to query
 * @param string $detail     Detail level: 'full', 'ref', or 'minimal'
 * @return array|WP_Error    User data array on success, WP_Error on failure
 */
function fetch_symplectic_user($my_id, $detail = 'full') {
    // Check for required credentials
    if (!defined('SYMPLECTIC_API_USERNAME') || !defined('SYMPLECTIC_API_PASSWORD')) {
        return new WP_Error('config_error', 'Symplectic API credentials not configured in wp-config.php');
    }
    
    // Build the API URL - searching by username (proprietary-id)
    $api_url = SYMPLECTIC_API_BASE . '/users';
    $api_url .= '?query=username%22' . $my_id . '%22&detail=full';

    // https://uga.elements.symplectic.org:8091/secure-api/v6.13/users?query=username=%22ags84031%22&detail=full


    //  $username = sanitize_text_field($_POST['username']);
    
    // if (empty($username)) {
    //     wp_send_json_error('Username is required');
    //     return;
    // }
    
    // // Build the API URL

    // // Test API base URL
    // // $api_url = 'https://uga-test.elements.symplectic.org:8093/secure-api/v6.13/users';

    // // Production API base URL
    // $api_url = 'https://uga.elements.symplectic.org:8091/secure-api/v6.13/users';

    // // Final query URL
    // $api_url .= '?query=username=%22' . urlencode($username) . '%22&detail=full';
    
    symplectic_log("📡 Querying Symplectic API: {$api_url}", 'debug');
    
    // Build authentication header
    $auth_string = base64_encode(SYMPLECTIC_API_USERNAME . ':' . SYMPLECTIC_API_PASSWORD);
    
    $args = [
        'headers' => [
            'Authorization' => 'Basic ' . $auth_string,
            'Accept' => 'application/json',
        ],
        'timeout' => 300,
        'sslverify' => true,
    ];
    
    $response = wp_remote_get($api_url, $args);
    
    if (is_wp_error($response)) {
        return new WP_Error('api_error', 'Symplectic API request failed: ' . $response->get_error_message());
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        $body = wp_remote_retrieve_body($response);
        return new WP_Error('api_error', "Symplectic API returned HTTP {$response_code}: {$body}");
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('parse_error', 'Failed to parse Symplectic API response: ' . json_last_error_msg());
    }
    
    return $data;
}

/**
 * ---------------------------------------------------------------------------------
 * 5. Main Processing Functions
 * ---------------------------------------------------------------------------------
 */

/**
 * Processes a single WordPress user: fetches their College ID, queries Personnel API
 * for MyID, then queries Symplectic API for full user data
 * 
 * @param int  $wp_user_id WordPress User ID
 * @param bool $dry_run    If true, don't save any data, just log what would happen
 * @return array|WP_Error  Result array on success, WP_Error on failure
 */
function process_single_user_symplectic($wp_user_id, $dry_run = true) {
    $user = get_userdata($wp_user_id);
    if (!$user) {
        return new WP_Error('user_not_found', "WordPress user ID {$wp_user_id} not found");
    }
    
    symplectic_log("👤 Processing user: {$user->display_name} (WP ID: {$wp_user_id})", 'info');
    
    // Step 1: Get College ID from ACF field
    $college_id = get_field('college_id', 'user_' . $wp_user_id);
    
    if (empty($college_id)) {
        $error = "No College ID found for user {$user->display_name}";
        symplectic_log("⚠️ {$error}", 'warning');
        return new WP_Error('missing_college_id', $error);
    }
    
    symplectic_log("  → College ID: {$college_id}", 'debug');
    
    // Step 2: Query Personnel API to get MyID
    $personnel_data = fetch_personnel_by_college_id($college_id);
    
    if (is_wp_error($personnel_data)) {
        symplectic_log("❌ Personnel API Error: " . $personnel_data->get_error_message(), 'error');
        return $personnel_data;
    }
    
    // The API returns an array - find the matching record
    $my_id = null;
    if (is_array($personnel_data) && !empty($personnel_data)) {
        // Check if it's a single record or array of records
        if (isset($personnel_data[0])) {
            // Array of records - use the first one
            $my_id = $personnel_data[0]['MYID'] ?? $personnel_data[0]['myid'] ?? null;
            symplectic_log("  → Found " . count($personnel_data) . " record(s) from Personnel API", 'debug');
        } else {
            // Single record
            $my_id = $personnel_data['MYID'] ?? $personnel_data['myid'] ?? null;
        }
    }
    
    if (empty($my_id)) {
        $error = "No MyID found in Personnel API response for College ID {$college_id}";
        symplectic_log("⚠️ {$error}", 'warning');
        symplectic_log("  → Raw response: " . json_encode($personnel_data), 'debug');
        return new WP_Error('missing_myid', $error);
    }
    
    symplectic_log("  → MyID (username): {$my_id}", 'info');
    
    // Step 3: Query Symplectic API using MyID
    $symplectic_data = fetch_symplectic_user($my_id);
    
    if (is_wp_error($symplectic_data)) {
        symplectic_log("❌ Symplectic API Error: " . $symplectic_data->get_error_message(), 'error');
        return $symplectic_data;
    }
    
    symplectic_log("✅ Successfully retrieved Symplectic data for {$my_id}", 'success');
    symplectic_log("  → Response preview: " . substr(json_encode($symplectic_data), 0, 500) . "...", 'debug');
    
    // Step 4: Save data to ACF fields (if not dry run)
    if (!$dry_run) {
        symplectic_log("💾 Saving Symplectic data to user meta...", 'info');
        // TODO: Map Symplectic fields to ACF fields here
        // Example:
        // update_field('symplectic_id', $symplectic_data['id'] ?? '', 'user_' . $wp_user_id);
        // update_field('orcid', $symplectic_data['orcid'] ?? '', 'user_' . $wp_user_id);
        symplectic_log("⚠️ Field mapping not yet implemented - data not saved", 'warning');
    } else {
        symplectic_log("🔍 DRY RUN: No data saved", 'info');
    }
    
    return [
        'wp_user_id' => $wp_user_id,
        'display_name' => $user->display_name,
        'college_id' => $college_id,
        'my_id' => $my_id,
        'symplectic_data' => $symplectic_data,
    ];
}

/**
 * Processes all Personnel Users, fetching Symplectic data for each
 * 
 * @param int  $limit   Maximum number of users to process (0 for all)
 * @param bool $dry_run If true, don't save any data
 * @return array        Results summary
 */
function process_all_personnel_users_symplectic($limit = 0, $dry_run = true) {
    global $symplectic_errors;
    $symplectic_errors = []; // Reset errors
    
    symplectic_log("🚀 Starting batch Symplectic data fetch for Personnel Users", 'info');
    symplectic_log("  → Mode: " . ($dry_run ? "DRY RUN (no data saved)" : "LIVE (data will be saved)"), 'info');
    symplectic_log("  → Limit: " . ($limit > 0 ? $limit . " users" : "All users"), 'info');
    
    // Get all personnel users
    $args = [
        'role' => 'personnel_user',
        'fields' => ['ID', 'display_name'],
        'orderby' => 'display_name',
        'order' => 'ASC',
    ];
    
    if ($limit > 0) {
        $args['number'] = $limit;
    }
    
    $users = get_users($args);
    $total_users = count($users);
    
    symplectic_log("📊 Found {$total_users} Personnel User(s) to process", 'info');
    
    $processed = 0;
    $success_count = 0;
    $error_count = 0;
    $results = [];
    
    foreach ($users as $user) {
        $processed++;
        symplectic_log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'info');
        symplectic_log("Processing [{$processed}/{$total_users}]", 'info');
        
        $result = process_single_user_symplectic($user->ID, $dry_run);
        
        if (is_wp_error($result)) {
            $error_count++;
            symplectic_record_error(
                "User: {$user->display_name} (ID: {$user->ID})",
                $result->get_error_message()
            );
        } else {
            $success_count++;
            $results[] = $result;
        }
    }
    
    symplectic_log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'info');
    symplectic_log("🏁 Batch processing complete!", 'success');
    symplectic_log("  → Processed: {$processed}", 'info');
    symplectic_log("  → Successful: {$success_count}", 'success');
    symplectic_log("  → Errors: {$error_count}", $error_count > 0 ? 'error' : 'info');
    
    return [
        'total' => $total_users,
        'processed' => $processed,
        'success' => $success_count,
        'errors' => $error_count,
        'results' => $results,
    ];
}

/**
 * Test connection to Symplectic API with a specific username
 * 
 * @param string $username The username/MyID to test
 * @return array|WP_Error  API response or error
 */
function test_symplectic_connection($username) {
    symplectic_log("🧪 Testing Symplectic API connection...", 'info');
    symplectic_log("  → Username: {$username}", 'debug');
    
    $result = fetch_symplectic_user($username);
    
    if (is_wp_error($result)) {
        symplectic_log("❌ Connection test failed: " . $result->get_error_message(), 'error');
    } else {
        symplectic_log("✅ Connection test successful!", 'success');
        symplectic_log("  → Response: " . json_encode($result, JSON_PRETTY_PRINT), 'debug');
    }
    
    return $result;
}

/**
 * Test connection to Personnel API with a specific College ID
 * 
 * @param int $college_id The College ID to test
 * @return array|WP_Error API response or error
 */
function test_personnel_connection($college_id) {
    symplectic_log("🧪 Testing Personnel API connection...", 'info');
    symplectic_log("  → College ID: {$college_id}", 'debug');
    
    $result = fetch_personnel_by_college_id($college_id);
    
    if (is_wp_error($result)) {
        symplectic_log("❌ Connection test failed: " . $result->get_error_message(), 'error');
    } else {
        symplectic_log("✅ Connection test successful!", 'success');
        symplectic_log("  → Response: " . json_encode($result, JSON_PRETTY_PRINT), 'debug');
    }
    
    return $result;
}

/**
 * ---------------------------------------------------------------------------------
 * 6. Admin Page Registration and Rendering
 * ---------------------------------------------------------------------------------
 */

/**
 * Registers the admin menu page for the Symplectic Dev Tool
 */
function symplectic_dev_register_admin_menu() {
    add_submenu_page(
        'tools.php',
        'Symplectic API Dev Tool',
        'Symplectic Dev',
        'manage_options',
        'symplectic-dev-tool',
        'symplectic_dev_render_admin_page'
    );
}
add_action('admin_menu', 'symplectic_dev_register_admin_menu');

/**
 * Outputs the CSS styles for the admin page
 */
function symplectic_dev_output_styles() {
    ?>
    <style>
        .symplectic-dev-wrap {
            max-width: 1200px;
            margin: 20px 20px 20px 0;
        }
        
        .symplectic-dev-wrap h1 {
            margin-bottom: 20px;
        }
        
        /* Control Panel */
        .control-panel {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .control-section {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
        }
        
        .control-section h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            font-size: 16px;
        }
        
        .control-section label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .control-section input[type="text"],
        .control-section input[type="number"],
        .control-section select {
            width: 100%;
            max-width: 300px;
            margin-bottom: 15px;
        }
        
        .control-section .button {
            margin-right: 10px;
            margin-bottom: 10px;
        }
        
        .control-section p.description {
            color: #666;
            font-style: italic;
            margin-top: 5px;
        }
        
        /* Logging Console */
        .logging-console-container {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .console-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            background: #f1f1f1;
            border-bottom: 1px solid #ccd0d4;
        }
        
        .console-header h3 {
            margin: 0;
            font-size: 14px;
        }
        
        .console-controls button {
            margin-left: 10px;
            font-size: 12px;
        }
        
        .logging-console {
            background: #1e1e1e;
            color: #d4d4d4;
            font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.5;
            padding: 15px;
            height: 500px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .log-entry {
            padding: 3px 0;
            border-bottom: 1px solid #333;
        }
        
        .log-info { color: #9cdcfe; }
        .log-success { color: #4ec9b0; }
        .log-warning { color: #dcdcaa; }
        .log-error { color: #f14c4c; }
        .log-debug { color: #808080; }
        
        /* Error Summary */
        .error-summary {
            background: #fff8e5;
            border: 1px solid #ffb900;
            border-radius: 4px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .error-summary h3 {
            margin-top: 0;
            color: #826200;
        }
        
        .error-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .error-item {
            background: #fff;
            border: 1px solid #ddd;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 3px;
        }
        
        .error-reason {
            color: #d63638;
        }
        
        .error-data {
            margin-top: 8px;
        }
        
        .error-data summary {
            cursor: pointer;
            color: #0073aa;
            font-size: 12px;
        }
        
        .error-data pre {
            background: #f0f0f0;
            padding: 10px;
            font-size: 11px;
            overflow-x: auto;
            max-height: 150px;
        }
        
        /* Quick Stats */
        .quick-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .stat-box {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 15px 20px;
            min-width: 150px;
        }
        
        .stat-box .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #1d2327;
        }
        
        .stat-box .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        
        /* Checkbox styling */
        .checkbox-group {
            margin-bottom: 15px;
        }
        
        .checkbox-group label {
            display: inline;
            font-weight: normal;
            margin-left: 5px;
        }
    </style>
    <?php
}

/**
 * Outputs the JavaScript for the admin page
 */
function symplectic_dev_output_scripts() {
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-scroll console to bottom
            const console = document.getElementById('logging-console');
            if (console) {
                const observer = new MutationObserver(function() {
                    console.scrollTop = console.scrollHeight;
                });
                observer.observe(console, { childList: true });
            }
            
            // Clear console button
            document.getElementById('clear-console')?.addEventListener('click', function() {
                document.getElementById('logging-console').innerHTML = 
                    '<div class="log-entry log-info">[' + new Date().toLocaleTimeString() + '] Console cleared</div>';
            });
            
            // Toggle debug messages
            document.getElementById('toggle-debug')?.addEventListener('click', function() {
                const debugEntries = document.querySelectorAll('.log-debug');
                const isHidden = debugEntries[0]?.style.display === 'none';
                debugEntries.forEach(entry => {
                    entry.style.display = isHidden ? 'block' : 'none';
                });
                this.textContent = isHidden ? 'Hide Debug' : 'Show Debug';
            });
        });
    </script>
    <?php
}

/**
 * Renders the admin page for the Symplectic Dev Tool
 */
function symplectic_dev_render_admin_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Output styles
    symplectic_dev_output_styles();
    
    // Start output buffering for real-time display
    ob_implicit_flush(true);
    ob_end_flush();
    
    // Process form submissions
    $action_performed = false;
    
    if (isset($_GET['action']) && isset($_GET['_wpnonce'])) {
        if (!wp_verify_nonce($_GET['_wpnonce'], 'symplectic_dev_action')) {
            wp_die('Security check failed');
        }
        
        $action_performed = true;
        
        echo '<div class="wrap symplectic-dev-wrap">';
        echo '<h1>Symplectic API Dev Tool - Running...</h1>';
        echo '<div class="logging-console-container">';
        echo '<div class="console-header">';
        echo '<h3>📋 Live Output Console</h3>';
        echo '</div>';
        echo '<div id="logging-console" class="logging-console">';
        
        $action = sanitize_text_field($_GET['action']);
        
        switch ($action) {
            case 'test_personnel':
                $college_id = isset($_GET['college_id']) ? intval($_GET['college_id']) : 22368;
                test_personnel_connection($college_id);
                break;
                
            case 'test_symplectic':
                $username = isset($_GET['username']) ? sanitize_text_field($_GET['username']) : '';
                if (!empty($username)) {
                    test_symplectic_connection($username);
                } else {
                    symplectic_log("❌ No username provided for Symplectic test", 'error');
                }
                break;
                
            case 'process_single':
                $wp_user_id = isset($_GET['wp_user_id']) ? intval($_GET['wp_user_id']) : 0;
                $dry_run = !isset($_GET['live_mode']);
                if ($wp_user_id > 0) {
                    $result = process_single_user_symplectic($wp_user_id, $dry_run);
                    if (!is_wp_error($result)) {
                        symplectic_log("📦 Full result data:", 'info');
                        symplectic_log(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 'debug');
                    }
                } else {
                    symplectic_log("❌ No WordPress User ID provided", 'error');
                }
                symplectic_display_error_summary('Single User Processing');
                break;
                
            case 'process_batch':
                $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;
                $dry_run = !isset($_GET['live_mode']);
                $result = process_all_personnel_users_symplectic($limit, $dry_run);
                symplectic_display_error_summary('Batch Processing');
                break;
        }
        
        echo '</div></div>';
        
        $back_url = esc_url(admin_url('tools.php?page=symplectic-dev-tool'));
        echo '<p><a href="' . $back_url . '" class="button button-primary">← Back to Dev Tool</a></p>';
        echo '</div>';
        
        symplectic_dev_output_scripts();
        return;
    }
    
    // Count personnel users for stats
    $personnel_user_count = count(get_users(['role' => 'personnel_user', 'fields' => 'ID']));
    
    // Main form display
    ?>
    <div class="wrap symplectic-dev-wrap">
        <h1>🔧 Symplectic API Development Tool</h1>
        <p>Use this tool to test and develop the Symplectic Elements API integration. All operations log detailed output to help with debugging.</p>
        
        <!-- Quick Stats -->
        <div class="quick-stats">
            <div class="stat-box">
                <div class="stat-value"><?php echo esc_html($personnel_user_count); ?></div>
                <div class="stat-label">Personnel Users</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?php echo defined('SYMPLECTIC_API_USERNAME') ? '✓' : '✗'; ?></div>
                <div class="stat-label">API Credentials</div>
            </div>
        </div>
        
        <!-- Control Panel -->
        <div class="control-panel">
            
            <!-- Test Personnel API -->
            <div class="control-section">
                <h2>🏢 Test CAES Personnel API</h2>
                <form method="get" action="<?php echo esc_url(admin_url('tools.php')); ?>">
                    <input type="hidden" name="page" value="symplectic-dev-tool">
                    <input type="hidden" name="action" value="test_personnel">
                    <?php wp_nonce_field('symplectic_dev_action', '_wpnonce'); ?>
                    
                    <label for="college_id">College ID:</label>
                    <input type="number" name="college_id" id="college_id" value="22368" class="regular-text">
                    <p class="description">Enter a College ID to test the Personnel API lookup</p>
                    
                    <button type="submit" class="button button-secondary">Test Personnel API</button>
                </form>
            </div>
            
            <!-- Test Symplectic API -->
            <div class="control-section">
                <h2>📚 Test Symplectic API</h2>
                <form method="get" action="<?php echo esc_url(admin_url('tools.php')); ?>">
                    <input type="hidden" name="page" value="symplectic-dev-tool">
                    <input type="hidden" name="action" value="test_symplectic">
                    <?php wp_nonce_field('symplectic_dev_action', '_wpnonce'); ?>
                    
                    <label for="username">MyID (Username):</label>
                    <input type="text" name="username" id="username" value="" class="regular-text" placeholder="e.g., jsmith">
                    <p class="description">Enter a UGA MyID to test the Symplectic API lookup</p>
                    
                    <button type="submit" class="button button-secondary">Test Symplectic API</button>
                </form>
            </div>
            
            <!-- Process Single User -->
            <div class="control-section">
                <h2>👤 Process Single User</h2>
                <form method="get" action="<?php echo esc_url(admin_url('tools.php')); ?>">
                    <input type="hidden" name="page" value="symplectic-dev-tool">
                    <input type="hidden" name="action" value="process_single">
                    <?php wp_nonce_field('symplectic_dev_action', '_wpnonce'); ?>
                    
                    <label for="wp_user_id">WordPress User ID:</label>
                    <input type="number" name="wp_user_id" id="wp_user_id" value="" class="regular-text" placeholder="e.g., 123">
                    <p class="description">Enter a WordPress User ID to process the full workflow</p>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" name="live_mode" id="live_mode_single" value="1">
                        <label for="live_mode_single">Live Mode (save data to user fields)</label>
                    </div>
                    
                    <button type="submit" class="button button-secondary">Process User</button>
                </form>
            </div>
            
            <!-- Batch Process -->
            <div class="control-section">
                <h2>👥 Batch Process Personnel Users</h2>
                <form method="get" action="<?php echo esc_url(admin_url('tools.php')); ?>">
                    <input type="hidden" name="page" value="symplectic-dev-tool">
                    <input type="hidden" name="action" value="process_batch">
                    <?php wp_nonce_field('symplectic_dev_action', '_wpnonce'); ?>
                    
                    <label for="limit">User Limit:</label>
                    <select name="limit" id="limit">
                        <option value="5">5 users (quick test)</option>
                        <option value="10">10 users</option>
                        <option value="25">25 users</option>
                        <option value="50">50 users</option>
                        <option value="100">100 users</option>
                        <option value="0">All users (no limit)</option>
                    </select>
                    <p class="description">Limit the number of users to process for testing</p>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" name="live_mode" id="live_mode_batch" value="1">
                        <label for="live_mode_batch">Live Mode (save data to user fields)</label>
                    </div>
                    
                    <button type="submit" class="button button-primary">Run Batch Process</button>
                </form>
            </div>
            
        </div>
        
        <!-- Configuration Info -->
        <div class="control-section" style="margin-top: 20px;">
            <h2>⚙️ Configuration Status</h2>
            <table class="form-table">
                <tr>
                    <th>CAES Personnel API Base:</th>
                    <td><code><?php echo esc_html(CAES_PERSONNEL_API_BASE); ?></code></td>
                </tr>
                <tr>
                    <th>Symplectic API Base:</th>
                    <td><code><?php echo esc_html(SYMPLECTIC_API_BASE); ?></code></td>
                </tr>
                <tr>
                    <th>Symplectic Credentials:</th>
                    <td>
                        <?php if (defined('SYMPLECTIC_API_USERNAME') && defined('SYMPLECTIC_API_PASSWORD')): ?>
                            <span style="color: green;">✓ Configured in wp-config.php</span>
                        <?php else: ?>
                            <span style="color: red;">✗ Not configured</span>
                            <p class="description">Add <code>SYMPLECTIC_API_USERNAME</code> and <code>SYMPLECTIC_API_PASSWORD</code> to wp-config.php</p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Help Section -->
        <div class="control-section" style="margin-top: 20px;">
            <h2>📖 Workflow Overview</h2>
            <ol>
                <li><strong>Get Personnel Users:</strong> Iterate through WordPress users with the 'personnel_user' role</li>
                <li><strong>Fetch College ID:</strong> Get the College ID from each user's ACF custom fields</li>
                <li><strong>Query Personnel API:</strong> Use College ID to fetch user data including MyID from <code><?php echo esc_html(CAES_PERSONNEL_API_BASE); ?></code></li>
                <li><strong>Query Symplectic API:</strong> Use MyID (as 'username' parameter) to fetch publication/research data from Symplectic Elements</li>
                <li><strong>Save Data:</strong> Store relevant Symplectic data in WordPress user custom fields</li>
            </ol>
        </div>
        
    </div>
    <?php
    
    symplectic_dev_output_scripts();
}