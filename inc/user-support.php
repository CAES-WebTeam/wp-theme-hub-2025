<?php

// Exit if accessed directly to prevent unauthorized access.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * ---------------------------------------------------------------------------------
 * 1. ACF Field Group Inclusion
 * ---------------------------------------------------------------------------------
 * This section ensures that Advanced Custom Fields (ACF) field groups
 * required for user profiles (e.g., personnel_id, phone_number, etc.) are loaded.
 * These fields must be registered before user data can be saved to them.
 */
include_once(get_template_directory() . '/inc/acf-fields/user-field-group.php');

/**
 * ---------------------------------------------------------------------------------
 * 2. Custom User Role Definitions
 * ---------------------------------------------------------------------------------
 * Defines custom user roles used within the application. These roles provide
 * specific capabilities and allow for better organization of different types of users.
 * They are added on the `init` hook to ensure they are available early in WordPress loading.
 */

/**
 * Adds the 'Personnel User' custom user role.
 * This role is typically for users synced from the external personnel system.
 */
function add_personnel_user_role()
{
    // Check if the role already exists to prevent re-adding on every page load.
    if (!get_role('personnel_user')) {
        add_role('personnel_user', 'Personnel User', [
            'read' => true, // Allows reading content
            'edit_posts' => false, // Prevents editing posts
            'delete_posts' => false, // Prevents deleting posts
        ]);
    }
}
add_action('init', 'add_personnel_user_role');

/**
 * Adds the 'Expert User' custom user role.
 * This role is typically for users imported as news experts/sources.
 */
function add_expert_user_role()
{
    // Check if the role already exists to prevent re-adding on every page load.
    if (!get_role('expert_user')) {
        add_role('expert_user', 'Expert User', [
            'read' => true, // Allows reading content
            'edit_posts' => false, // Prevents editing posts
            'delete_posts' => false, // Prevents deleting posts
        ]);
    }
}
add_action('init', 'add_expert_user_role');


/**
 * ---------------------------------------------------------------------------------
 * 3. Live Output Helper Functions
 * ---------------------------------------------------------------------------------
 * Functions to handle real-time display of messages during import processes.
 */

// Global variable to store import messages and errors
global $import_messages, $import_errors;
$import_messages = [];
$import_errors = [];

/**
 * Outputs a message to both the page and error log, with real-time display
 */
function output_sync_message($message, $type = 'info') {
    global $import_messages;
    
    $timestamp = date('H:i:s');
    $formatted_message = "[{$timestamp}] {$message}";
    $import_messages[] = ['message' => $formatted_message, 'type' => $type];
    
    // Also log to WordPress error log
    error_log($message);
    
    // Output to page immediately
    $css_class = $type === 'error' ? 'notice-error' : ($type === 'success' ? 'notice-success' : 'notice-info');
    echo '<div class="notice ' . $css_class . '"><p>' . esc_html($formatted_message) . '</p></div>';
    ob_flush();
    flush();
}

/**
 * Records an import error for summary display
 */
function record_import_error($user_identifier, $error_reason, $raw_data = null) {
    global $import_errors;
    
    $import_errors[] = [
        'user' => $user_identifier,
        'error' => $error_reason,
        'data' => $raw_data
    ];
}

/**
 * Displays a summary of all import errors
 */
function display_import_error_summary($operation_name) {
    global $import_errors;
    
    if (empty($import_errors)) {
        output_sync_message("✅ {$operation_name}: All users processed successfully - no errors recorded!", 'success');
        return;
    }
    
    $error_count = count($import_errors);
    output_sync_message("❌ {$operation_name}: {$error_count} users could not be imported. Details below:", 'error');
    
    echo '<div class="notice notice-warning" style="margin: 10px 0; padding: 15px; border-left: 4px solid #ffb900;">';
    echo '<h3 style="margin-top: 0;">Import Errors Summary for ' . esc_html($operation_name) . '</h3>';
    echo '<div style="max-height: 400px; overflow-y: auto; background: #f9f9f9; padding: 10px; border: 1px solid #ddd;">';
    
    foreach ($import_errors as $index => $error) {
        echo '<div style="margin-bottom: 15px; padding: 10px; background: white; border: 1px solid #ccc;">';
        echo '<strong>Error #' . ($index + 1) . ':</strong> ' . esc_html($error['user']) . '<br>';
        echo '<span style="color: #d63638;"><strong>Reason:</strong> ' . esc_html($error['error']) . '</span><br>';
        
        if ($error['data']) {
            echo '<details style="margin-top: 5px;">';
            echo '<summary style="cursor: pointer; color: #0073aa;">View Raw Data</summary>';
            echo '<pre style="background: #f0f0f0; padding: 5px; font-size: 11px; overflow: auto; max-height: 100px;">';
            echo esc_html(json_encode($error['data'], JSON_PRETTY_PRINT));
            echo '</pre>';
            echo '</details>';
        }
        echo '</div>';
    }
    
    echo '</div>';
    echo '</div>';
    
    // Clear errors for next operation
    $import_errors = [];
}

/**
 * ---------------------------------------------------------------------------------
 * 4. Helper Functions
 * ---------------------------------------------------------------------------------
 * Utility functions used by the main synchronization and import processes.
 */

/**
 * Splits a full name string into first name and last name.
 * Assumes the last word is the last name, and the rest is the first name.
 *
 * @param string $full_name The full name string.
 * @return array An associative array with 'first_name' and 'last_name'.
 */
function split_full_name($full_name)
{
    $name_parts = explode(' ', trim($full_name));

    if (count($name_parts) > 1) {
        $last_name = array_pop($name_parts);
        $first_name = implode(' ', $name_parts);
    } else {
        $first_name = $full_name;
        $last_name = '';
    }

    return [
        'first_name' => $first_name,
        'last_name' => $last_name
    ];
}

/**
 * Generates a placeholder email address for users who do not have an email in the source data.
 * This ensures all WordPress users have a unique email.
 *
 * @param string $first First name.
 * @param string $last Last name.
 * @return string A generated placeholder email.
 */
function generate_placeholder_email($first, $last)
{
    // Normalize and clean names for use in email parts.
    $first_clean = sanitize_email_part($first);
    $last_clean = sanitize_email_part($last);

    // Fallback to 'user' or a unique ID if name parts are empty to ensure uniqueness.
    if (empty($first_clean)) $first_clean = 'user';
    if (empty($last_clean)) $last_clean = uniqid();

    return "{$first_clean}.{$last_clean}@placeholder.uga.edu";
}

/**
 * Sanitizes a string part for use in an email address (e.g., removing special characters).
 *
 * @param string $name The string to sanitize.
 * @return string The sanitized string, containing only lowercase letters and numbers.
 */
function sanitize_email_part($name)
{
    $name = strtolower(trim($name));
    $name = preg_replace('/[^a-z0-9]/', '', $name); // Keep only a-z and 0–9
    return $name;
}

/**
 * ---------------------------------------------------------------------------------
 * 5. Personnel User Synchronization Functions
 * ---------------------------------------------------------------------------------
 * These functions fetch user data from external REST APIs and synchronize it
 * with WordPress user accounts, creating new users or updating existing ones.
 */

/**
 * Syncs active personnel user data from the primary external API endpoint.
 * Creates new 'personnel_user' roles or updates existing ones based on PERSONNEL_ID.
 *
 * @return array|WP_Error An array with sync results (created/updated counts) on success,
 * or a WP_Error object on failure.
 */
function sync_personnel_users()
{
    global $import_errors;
    $import_errors = []; // Reset errors for this operation
    
    $api_url = 'https://secure.caes.uga.edu/rest/personnel/Personnel/?returnContactInfoColumns=true';

    // Fetch data from the API.
    $response = wp_remote_get($api_url);
    if (is_wp_error($response)) {
        $error_msg = 'API Request Failed for Active Personnel: ' . $response->get_error_message();
        output_sync_message($error_msg, 'error');
        return new WP_Error('api_error', $error_msg);
    }

    $data = wp_remote_retrieve_body($response);
    $users = json_decode($data, true);

    if (!is_array($users)) {
        $error_msg = 'Invalid API response for Active Personnel.';
        output_sync_message($error_msg, 'error');
        return new WP_Error('invalid_response', $error_msg);
    }

    output_sync_message("SYNC START: Processing " . count($users) . " users from Active Personnel API");

    // Prepare a map of existing personnel_id to WordPress user ID for efficient lookup.
    $existing_users = get_users([
        'role' => 'personnel_user',
        'meta_key' => 'personnel_id',
        'fields' => ['ID', 'user_login']
    ]);

    $existing_user_ids = [];
    foreach ($existing_users as $user) {
        $personnel_id_meta = get_user_meta($user->ID, 'personnel_id', true);
        if ($personnel_id_meta) {
            $existing_user_ids[$personnel_id_meta] = $user->ID;
        }
    }

    $api_user_ids = []; // To keep track of personnel_ids from the current API fetch.
    $created_count = 0;
    $updated_count = 0;
    $error_count = 0;
    $skipped_count = 0;

    // Iterate through each user record from the API.
    foreach ($users as $index => $user) {
        $user_log_prefix = "USER #{$index}";
        
        // Validate required fields first
        $required_fields = ['PERSONNEL_ID', 'NAME', 'FNAME', 'LNAME'];
        $missing_fields = [];
        foreach ($required_fields as $field) {
            if (!isset($user[$field]) || empty(trim($user[$field]))) {
                $missing_fields[] = $field;
            }
        }
        
        // Spoof an email if necessary so that the record can be imported
        if (!isset($user['EMAIL']) || empty(trim($user['EMAIL']))) {
                $user['EMAIL'] = generate_placeholder_email($user['FNAME'], $user['LNAME']);
        }

        if (!empty($missing_fields)) {
            $error_msg = "Missing required fields: " . implode(', ', $missing_fields);
            output_sync_message("{$user_log_prefix} ERROR: {$error_msg}");
            record_import_error($user_log_prefix, $error_msg, $user);
            $error_count++;
            continue;
        }

        // Sanitize and extract relevant user data from the API response.
        $personnel_id = intval($user['PERSONNEL_ID']);
        $college_id = intval($user['COLLEGEID'] ?? 0);
        $original_email = sanitize_email($user['EMAIL']);
        $username = sanitize_user(strtolower(str_replace(' ', '', $user['NAME'])));
        $first_name = sanitize_text_field($user['FNAME']);
        $last_name = sanitize_text_field($user['LNAME']);
        $display_name = sanitize_text_field($user['NAME']);
        $title = sanitize_text_field($user['TITLE'] ?? '');
        $department = sanitize_text_field($user['DEPARTMENT'] ?? '');
        $program_area = sanitize_text_field($user['PROGRAMAREALIST'] ?? '');
        $phone = sanitize_text_field($user['PHONE_NUMBER'] ?? '');
        $cell_phone = sanitize_text_field($user['CELL_PHONE_NUMBER'] ?? '');
        $fax = sanitize_text_field($user['FAX_NUMBER'] ?? '');
        $caes_location_id = intval($user['CAES_LOCATION_ID'] ?? 0);
        $mailing_address = sanitize_text_field($user['MAILING_ADDRESS1'] ?? '');
        $mailing_address2 = sanitize_text_field($user['MAILING_ADDRESS2'] ?? '');
        $mailing_city = sanitize_text_field($user['MAILING_CITY'] ?? '');
        $mailing_state = sanitize_text_field($user['MAILING_STATE'] ?? '');
        $mailing_zip = sanitize_text_field($user['MAILING_ZIP'] ?? '');
        $shipping_address = sanitize_text_field($user['SHIPPING_ADDRESS1'] ?? '');
        $shipping_address2 = sanitize_text_field($user['SHIPPING_ADDRESS2'] ?? '');
        $shipping_city = sanitize_text_field($user['SHIPPING_CITY'] ?? '');
        $shipping_state = sanitize_text_field($user['SHIPPING_STATE'] ?? '');
        $shipping_zip = sanitize_text_field($user['SHIPPING_ZIP'] ?? '');
        $image_name = sanitize_text_field($user['IMAGE'] ?? '');


        // Validate sanitized data
        if ($personnel_id <= 0) {
            $error_msg = "Invalid personnel_id after sanitization: '{$user['PERSONNEL_ID']}' -> {$personnel_id}";
            output_sync_message("{$user_log_prefix} ERROR: {$error_msg}");
            record_import_error("{$user_log_prefix} (Personnel ID: {$user['PERSONNEL_ID']})", $error_msg, $user);
            $error_count++;
            continue;
        }
        
        if (!is_email($original_email)) {
            $error_msg = "Invalid email after sanitization: '{$user['EMAIL']}' -> '{$original_email}'";
            output_sync_message("{$user_log_prefix} ERROR: {$error_msg}");
            record_import_error("{$user_log_prefix} (Personnel ID: {$personnel_id})", $error_msg, $user);
            $error_count++;
            continue;
        }
        
        if (empty($username) || strlen($username) < 3) {
            $error_msg = "Invalid username after sanitization: '{$username}' (original: '{$user['NAME']}')";
            output_sync_message("{$user_log_prefix} ERROR: {$error_msg}");
            record_import_error("{$user_log_prefix} (Personnel ID: {$personnel_id})", $error_msg, $user);
            $error_count++;
            continue;
        }

        $api_user_ids[] = $personnel_id;
        $user_id = null;

        // First, check if the user already exists by personnel_id.
        if (isset($existing_user_ids[$personnel_id])) {
            $user_id = $existing_user_ids[$personnel_id];
            output_sync_message("{$user_log_prefix}: Found existing user by personnel_id {$personnel_id}: User ID {$user_id}");
        }

        if ($user_id) {
            // Update Existing User
            try {

                // Check to ensure email is not a duplicate. Generate a non-duplicate email if needed.
                $email_to_use = $original_email;
                
                if (email_exists($original_email)) {
                    // Strengthening unique emails further because there are just that many duplicates. JDK 8/14/2025
                    $unique_id = uniqid();
                    $email_to_use = "personnel_{$personnel_id}{$unique_id}@caes.uga.edu.spoofed";
                    output_sync_message("{$user_log_prefix}: Email {$original_email} already exists. Using spoofed email: {$email_to_use}");
                }

                $update_result = wp_update_user([
                    'ID' => $user_id,
                    'user_email' => $email_to_use,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'nickname' => $nickname,
                    'display_name' => $display_name
                ]);

                if (is_wp_error($update_result)) {
                    $error_msg = "wp_update_user failed: " . $update_result->get_error_message();
                    output_sync_message("{$user_log_prefix} UPDATE ERROR: {$error_msg}");
                    record_import_error("{$user_log_prefix} (Personnel ID: {$personnel_id})", $error_msg, $user);
                    $error_count++;
                    continue;
                }

                // Update ACF fields for the existing user.
                update_field('personnel_id', $personnel_id, 'user_' . $user_id); // Make sure this gets set!
                update_field('college_id', $college_id, 'user_' . $user_id);
                update_field('uga_email', $original_email, 'user_' . $user_id); // Store original email in ACF field
                update_field('title', $title, 'user_' . $user_id);
                update_field('phone_number', $phone, 'user_' . $user_id);
                update_field('cell_phone_number', $cell_phone, 'user_' . $user_id);
                update_field('fax_number', $fax, 'user_' . $user_id);
                update_field('department', $department, 'user_' . $user_id);
                update_field('program_area', $program_area, 'user_' . $user_id);
                update_field('caes_location_id', $caes_location_id, 'user_' . $user_id);
                update_field('mailing_address', $mailing_address, 'user_' . $user_id);
                update_field('mailing_address2', $mailing_address2, 'user_' . $user_id);
                update_field('mailing_city', $mailing_city, 'user_' . $user_id);
                update_field('mailing_state', $mailing_state, 'user_' . $user_id);
                update_field('mailing_zip', $mailing_zip, 'user_' . $user_id);
                update_field('shipping_address', $shipping_address, 'user_' . $user_id);
                update_field('shipping_address2', $shipping_address2, 'user_' . $user_id);
                update_field('shipping_city', $shipping_city, 'user_' . $user_id);
                update_field('shipping_state', $shipping_state, 'user_' . $user_id);
                update_field('shipping_zip', $shipping_zip, 'user_' . $user_id);
                update_field('image_name', $image_name, 'user_' . $user_id);

                $updated_count++;
                output_sync_message("{$user_log_prefix}: Successfully updated user {$user_id} with personnel_id {$personnel_id}");
                
            } catch (Exception $e) {
                $error_msg = "Exception during update: " . $e->getMessage();
                output_sync_message("{$user_log_prefix} UPDATE ERROR: {$error_msg}");
                record_import_error("{$user_log_prefix} (Personnel ID: {$personnel_id})", $error_msg, $user);
                $error_count++;
            }
        } else {
            // Create New User
            try {
                $email_to_use = $original_email;
                
                // Check if email already exists in WordPress
                if (email_exists($original_email) || $original_email == '') {
                    // Strengthening unique emails further because there are just that many duplicates. JDK 8/14/2025
                    $unique_id = uniqid();
                    $email_to_use = "personnel_{$personnel_id}{$unique_id}@caes.uga.edu.spoofed";
                    output_sync_message("{$user_log_prefix}: Email {$original_email} already exists. Using spoofed email: {$email_to_use}");
                }

                // Check if username already exists
                if (username_exists($username)) {
                    $original_username = $username;
                    $username = $username . '_' . $personnel_id;
                    output_sync_message("{$user_log_prefix}: Username '{$original_username}' already exists. Using: '{$username}'");
                }

                $user_id = wp_insert_user([
                    'user_login' => $username,
                    'user_email' => $email_to_use,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'nickname' => $nickname,
                    'display_name' => $display_name,
                    'user_pass' => wp_generate_password(),
                    'role' => 'personnel_user'
                ]);

                if (is_wp_error($user_id)) {
                    $error_msg = "wp_insert_user failed: " . $user_id->get_error_message();
                    output_sync_message("{$user_log_prefix} CREATE ERROR: {$error_msg}");
                    record_import_error("{$user_log_prefix} (Personnel ID: {$personnel_id})", $error_msg, $user);
                    $error_count++;
                    continue;
                }

                // Update ACF fields for the new user.
                update_field('personnel_id', $personnel_id, 'user_' . $user_id);
                update_field('college_id', $college_id, 'user_' . $user_id);
                update_field('uga_email', $original_email, 'user_' . $user_id); // Store original email in ACF field
                update_field('title', $title, 'user_' . $user_id);
                update_field('phone_number', $phone, 'user_' . $user_id);
                update_field('cell_phone_number', $cell_phone, 'user_' . $user_id);
                update_field('fax_number', $fax, 'user_' . $user_id);
                update_field('department', $department, 'user_' . $user_id);
                update_field('program_area', $program_area, 'user_' . $user_id);
                update_field('caes_location_id', $caes_location_id, 'user_' . $user_id);
                update_field('mailing_address', $mailing_address, 'user_' . $user_id);
                update_field('mailing_address2', $mailing_address2, 'user_' . $user_id);
                update_field('mailing_city', $mailing_city, 'user_' . $user_id);
                update_field('mailing_state', $mailing_state, 'user_' . $user_id);
                update_field('mailing_zip', $mailing_zip, 'user_' . $user_id);
                update_field('shipping_address', $shipping_address, 'user_' . $user_id);
                update_field('shipping_address2', $shipping_address2, 'user_' . $user_id);
                update_field('shipping_city', $shipping_city, 'user_' . $user_id);
                update_field('shipping_state', $shipping_state, 'user_' . $user_id);
                update_field('shipping_zip', $shipping_zip, 'user_' . $user_id);
                update_field('image_name', $image_name, 'user_' . $user_id);

                $created_count++;
                output_sync_message("{$user_log_prefix}: Successfully created new user {$user_id} with personnel_id {$personnel_id} using email: {$email_to_use}");
                
            } catch (Exception $e) {
                $error_msg = "Exception during user creation: " . $e->getMessage();
                output_sync_message("{$user_log_prefix} CREATE ERROR: {$error_msg}");
                record_import_error("{$user_log_prefix} (Personnel ID: {$personnel_id})", $error_msg, $user);
                $error_count++;
            }
        }
    }

    $total_processed = $created_count + $updated_count + $error_count;
    output_sync_message("SYNC COMPLETE: Total API records: " . count($users) . 
             " | Processed: {$total_processed} | Created: {$created_count} | Updated: {$updated_count} | Errors: {$error_count}", 'success');

    // Return results for reporting in the admin interface.
    return [
        'created' => $created_count,
        'updated' => $updated_count,
        'errors' => $error_count,
        'total_api_records' => count($users),
        'message' => "Active Personnel users synced. Created: {$created_count}, Updated: {$updated_count}, Errors: {$error_count} out of " . count($users) . " API records."
    ];
}
/**
 * Sets up a daily CRON job to automatically run `sync_personnel_users`.
 * This ensures regular synchronization without manual intervention.
 */
if (!wp_next_scheduled('daily_personnel_sync')) {
    wp_schedule_event(time(), 'daily', 'daily_personnel_sync');
}
add_action('daily_personnel_sync', 'sync_personnel_users');
/**
 * Syncs inactive/archived personnel user data from a secondary external API endpoint.
 * This is similar to `sync_personnel_users` but targets users marked as inactive.
 *
 * @return array|WP_Error An array with sync results (created/updated counts) on success,
 * or a WP_Error object on failure.
 */
function sync_personnel_users2()
{
    global $import_errors;
    $import_errors = []; // Reset errors for this operation
    
    // API endpoint specifically for inactive news authors/experts with contact info.
    $api_url = 'https://secure.caes.uga.edu/rest/personnel/Personnel?returnOnlyNewsAuthorsAndExpertsAndPubAuthors=true&isActive=false&returnContactInfoColumns=true';

    // Fetch API Data.
    $response = wp_remote_get($api_url);
    if (is_wp_error($response)) {
        $error_msg = 'API Request Failed for Inactive Personnel: ' . $response->get_error_message();
        output_sync_message($error_msg, 'error');
        return new WP_Error('api_error', $error_msg);
    }

    $data = wp_remote_retrieve_body($response);
    $users = json_decode($data, true);

    if (!is_array($users)) {
        $error_msg = 'Invalid API response for Inactive Personnel. Response body: ' . substr($data, 0, 500);
        output_sync_message($error_msg, 'error');
        return new WP_Error('invalid_response', $error_msg);
    }

    output_sync_message("SYNC START: Processing " . count($users) . " users from Inactive Personnel API");

    // Prepare a map of existing personnel_id to WordPress user ID for efficient lookup.
    $existing_users = get_users([
        'role' => 'personnel_user',
        'meta_key' => 'personnel_id',
        'fields' => ['ID', 'user_login']
    ]);

    $existing_user_ids = [];
    foreach ($existing_users as $user) {
        $personnel_id_meta = get_user_meta($user->ID, 'personnel_id', true);
        if ($personnel_id_meta) {
            $existing_user_ids[$personnel_id_meta] = $user->ID;
        }
    }
    output_sync_message("EXISTING USERS MAP: Found " . count($existing_user_ids) . " existing personnel users");

    $api_user_ids = []; // To keep track of personnel_ids from the current API fetch.
    $created_count = 0;
    $updated_count = 0;
    $error_count = 0;

    // Iterate through each user record from the API.
    foreach ($users as $index => $user) {
        $user_log_prefix = "USER #{$index}";
        
                // Validate required fields first
        $required_fields = ['PERSONNEL_ID', 'NAME', 'FNAME', 'LNAME'];
        $missing_fields = [];
        foreach ($required_fields as $field) {
            if (!isset($user[$field]) || empty(trim($user[$field]))) {
                $missing_fields[] = $field;
            }
        }
        
        // Spoof an email if necessary so that the record can be imported
        if (!isset($user['EMAIL']) || empty(trim($user['EMAIL']))) {
            $user['EMAIL'] = generate_placeholder_email($user['FNAME'], $user['LNAME']);
        }
        
        if (!empty($missing_fields)) {
            $error_msg = "Missing required fields: " . implode(', ', $missing_fields);
            output_sync_message("{$user_log_prefix} ERROR: {$error_msg}");
            record_import_error($user_log_prefix, $error_msg, $user);
            $error_count++;
            continue;
        }

        // Sanitize and extract relevant user data from the API response.
        $personnel_id = intval($user['PERSONNEL_ID']);
        $college_id = intval($user['COLLEGEID'] ?? 0);
        $original_email = sanitize_email($user['EMAIL']);
        $username = sanitize_user(strtolower(str_replace(' ', '', $user['NAME'])));
        $nickname = sanitize_text_field($user['NAME']);
        $display_name = sanitize_text_field($user['NAME']);
        $first_name = sanitize_text_field($user['FNAME']);
        $last_name = sanitize_text_field($user['LNAME']);
        $title = sanitize_text_field($user['TITLE'] ?? '');
        $department = sanitize_text_field($user['DEPARTMENT'] ?? '');
        $program_area = sanitize_text_field($user['PROGRAMAREALIST'] ?? '');
        $phone = sanitize_text_field($user['PHONE_NUMBER'] ?? '');
        $cell_phone = sanitize_text_field($user['CELL_PHONE_NUMBER'] ?? '');
        $fax = sanitize_text_field($user['FAX_NUMBER'] ?? '');
        $caes_location_id = intval($user['CAES_LOCATION_ID'] ?? 0);
        $mailing_address = sanitize_text_field($user['MAILING_ADDRESS1'] ?? '');
        $mailing_address2 = sanitize_text_field($user['MAILING_ADDRESS2'] ?? '');
        $mailing_city = sanitize_text_field($user['MAILING_CITY'] ?? '');
        $mailing_state = sanitize_text_field($user['MAILING_STATE'] ?? '');
        $mailing_zip = sanitize_text_field($user['MAILING_ZIP'] ?? '');
        $shipping_address = sanitize_text_field($user['SHIPPING_ADDRESS1'] ?? '');
        $shipping_address2 = sanitize_text_field($user['SHIPPING_ADDRESS2'] ?? '');
        $shipping_city = sanitize_text_field($user['SHIPPING_CITY'] ?? '');
        $shipping_state = sanitize_text_field($user['SHIPPING_STATE'] ?? '');
        $shipping_zip = sanitize_text_field($user['SHIPPING_ZIP'] ?? '');
        $image_name = sanitize_text_field($user['IMAGE'] ?? '');

        // Validate sanitized data
        if ($personnel_id <= 0) {
            $error_msg = "Invalid personnel_id after sanitization: '{$user['PERSONNEL_ID']}' -> {$personnel_id}";
            output_sync_message("{$user_log_prefix} ERROR: {$error_msg}");
            record_import_error("{$user_log_prefix} (Personnel ID: {$user['PERSONNEL_ID']})", $error_msg, $user);
            $error_count++;
            continue;
        }
        
        if (!is_email($original_email)) {
            $error_msg = "Invalid email after sanitization: '{$user['EMAIL']}' -> '{$original_email}'";
            output_sync_message("{$user_log_prefix} ERROR: {$error_msg}");
            record_import_error("{$user_log_prefix} (Personnel ID: {$personnel_id})", $error_msg, $user);
            $error_count++;
            continue;
        }
        
        if (empty($username)) {
            $error_msg = "Empty username after sanitization. Original NAME: '{$user['NAME']}'";
            output_sync_message("{$user_log_prefix} ERROR: {$error_msg}");
            record_import_error("{$user_log_prefix} (Personnel ID: {$personnel_id})", $error_msg, $user);
            $error_count++;
            continue;
        }
        
        if (strlen($username) < 3) {
            $error_msg = "Username too short after sanitization: '{$username}' (original: '{$user['NAME']}')";
            output_sync_message("{$user_log_prefix} ERROR: {$error_msg}");
            record_import_error("{$user_log_prefix} (Personnel ID: {$personnel_id})", $error_msg, $user);
            $error_count++;
            continue;
        }

        output_sync_message("{$user_log_prefix}: Processing personnel_id={$personnel_id}, email={$original_email}, username={$username}");

        $api_user_ids[] = $personnel_id;

        $user_id = null;

        // First, check if the user already exists by personnel_id.
        if (isset($existing_user_ids[$personnel_id])) {
            $user_id = $existing_user_ids[$personnel_id];
            output_sync_message("{$user_log_prefix}: Found existing user by personnel_id {$personnel_id}: User ID {$user_id}");
        } else {
            output_sync_message("{$user_log_prefix}: No existing user found with personnel_id {$personnel_id}");
        }

        if ($user_id) {
            // Update Existing User
            output_sync_message("{$user_log_prefix}: UPDATING existing user ID {$user_id}");
            
            try {
                // Check to ensure email is not a duplicate. Generate a non-duplicate email if needed.
                $email_to_use = $original_email;
                
                if (email_exists($original_email)) {
                    // Strengthening unique emails further because there are just that many duplicates. JDK 8/14/2025
                    $unique_id = uniqid();
                    $email_to_use = "personnel_{$personnel_id}{$unique_id}@caes.uga.edu.spoofed";
                    output_sync_message("{$user_log_prefix}: Email {$original_email} already exists. Using spoofed email: {$email_to_use}");
                }

                // Update core WordPress user fields.
                $update_result = wp_update_user([
                    'ID' => $user_id,
                    'user_email' => $email_to_use,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'display_name' => $display_name
                ]);

                if (is_wp_error($update_result)) {
                    $error_msg = "wp_update_user failed: " . $update_result->get_error_message();
                    output_sync_message("{$user_log_prefix} UPDATE ERROR: {$error_msg}");
                    record_import_error("{$user_log_prefix} (Personnel ID: {$personnel_id})", $error_msg, $user);
                    $error_count++;
                    continue;
                }

                // Update ACF fields for the existing user.
                $acf_updates = [
                    'personnel_id' => $personnel_id,
                    'college_id' => $college_id,
                    'uga_email' => $original_email,
                    'title' => $title,
                    'phone_number' => $phone,
                    'cell_phone_number' => $cell_phone,
                    'fax_number' => $fax,
                    'department' => $department,
                    'program_area' => $program_area,
                    'caes_location_id' => $caes_location_id,
                    'mailing_address' => $mailing_address,
                    'mailing_address2' => $mailing_address2,
                    'mailing_city' => $mailing_city,
                    'mailing_state' => $mailing_state,
                    'mailing_zip' => $mailing_zip,
                    'shipping_address' => $shipping_address,
                    'shipping_address2' => $shipping_address2,
                    'shipping_city' => $shipping_city,
                    'shipping_state' => $shipping_state,
                    'shipping_zip' => $shipping_zip,
                    'image_name' => $image_name
                ];

                foreach ($acf_updates as $field_name => $field_value) {
                    $acf_result = update_field($field_name, $field_value, 'user_' . $user_id);
                    if (!$acf_result) {
                        output_sync_message("{$user_log_prefix} UPDATE WARNING: Failed to update ACF field '{$field_name}' with value '{$field_value}' for user {$user_id}");
                    }
                }
                
                $updated_count++;
                output_sync_message("{$user_log_prefix}: Successfully updated user {$user_id} with personnel_id {$personnel_id}");
                
            } catch (Exception $e) {
                $error_msg = "Exception during update: " . $e->getMessage();
                output_sync_message("{$user_log_prefix} UPDATE ERROR: {$error_msg}");
                record_import_error("{$user_log_prefix} (Personnel ID: {$personnel_id})", $error_msg, $user);
                $error_count++;
            }
        } else {
            // Create New User if not found.
            output_sync_message("{$user_log_prefix}: CREATING new user");
            
            $email_to_use = $original_email;
                
            // Check if email already exists in WordPress
            if (email_exists($original_email)) {
                // Strengthening unique emails further because there are just that many duplicates. JDK 8/14/2025
                $unique_id = uniqid();
                $email_to_use = "personnel_{$personnel_id}{$unique_id}@caes.uga.edu.spoofed";
                output_sync_message("{$user_log_prefix}: Email {$original_email} already exists. Using spoofed email: {$email_to_use}");
            }
            
            // Check if username already exists
            if (username_exists($username)) {
                $original_username = $username;
                $username = $username . '_' . $personnel_id;
                output_sync_message("{$user_log_prefix}: Username '{$original_username}' already exists. Using: '{$username}'");
            }
            
            // Validate final username
            if (!validate_username($username)) {
                $error_msg = "Invalid username after all processing: '{$username}'";
                output_sync_message("{$user_log_prefix} CREATE ERROR: {$error_msg}");
                record_import_error("{$user_log_prefix} (Personnel ID: {$personnel_id})", $error_msg, $user);
                $error_count++;
                continue;
            }
            
            try {
                $user_data = [
                    'user_login' => $username,
                    'user_email' => $email_to_use,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'display_name' => $display_name,
                    'user_pass' => wp_generate_password(),
                    'role' => 'personnel_user'
                ];
                
                output_sync_message("{$user_log_prefix}: Creating user with data: " . json_encode($user_data));
                
                $user_id = wp_insert_user($user_data);
                
            } catch (Exception $e) {
                $error_msg = "Exception during wp_insert_user: " . $e->getMessage();
                output_sync_message("{$user_log_prefix} CREATE ERROR: {$error_msg}");
                record_import_error("{$user_log_prefix} (Personnel ID: {$personnel_id})", $error_msg, $user);
                $error_count++;
                continue; // Skip this user if there's an error.
            }

            if (is_wp_error($user_id)) {
                $error_msg = "wp_insert_user failed: " . $user_id->get_error_message() . 
                         " | Error data: " . json_encode($user_id->get_error_data());
                output_sync_message("{$user_log_prefix} CREATE ERROR: {$error_msg}");
                record_import_error("{$user_log_prefix} (Personnel ID: {$personnel_id})", $error_msg, $user);
                $error_count++;
                continue;
            }
            
            if (!$user_id || $user_id <= 0) {
                $error_msg = "wp_insert_user returned invalid user ID: " . var_export($user_id, true);
                output_sync_message("{$user_log_prefix} CREATE ERROR: {$error_msg}");
                record_import_error("{$user_log_prefix} (Personnel ID: {$personnel_id})", $error_msg, $user);
                $error_count++;
                continue;
            }

            // Verify user was actually created
            $created_user = get_user_by('ID', $user_id);
            if (!$created_user) {
                $error_msg = "User ID {$user_id} was returned but user doesn't exist in database";
                output_sync_message("{$user_log_prefix} CREATE ERROR: {$error_msg}");
                record_import_error("{$user_log_prefix} (Personnel ID: {$personnel_id})", $error_msg, $user);
                $error_count++;
                continue;
            }

            output_sync_message("{$user_log_prefix}: Successfully created user ID {$user_id}, verifying role assignment...");
            
            // Verify role assignment
            if (!in_array('personnel_user', $created_user->roles)) {
                output_sync_message("{$user_log_prefix} CREATE WARNING: User {$user_id} doesn't have personnel_user role. Current roles: " . implode(', ', $created_user->roles));
                // Try to assign the role manually
                $created_user->set_role('personnel_user');
            }

            try {
                // Update ACF fields for the new user.
                $acf_updates = [
                    'personnel_id' => $personnel_id,
                    'college_id' => $college_id,
                    'uga_email' => $original_email,
                    'title' => $title,
                    'phone_number' => $phone,
                    'cell_phone_number' => $cell_phone,
                    'fax_number' => $fax,
                    'department' => $department,
                    'program_area' => $program_area,
                    'caes_location_id' => $caes_location_id,
                    'mailing_address' => $mailing_address,
                    'mailing_address2' => $mailing_address2,
                    'mailing_city' => $mailing_city,
                    'mailing_state' => $mailing_state,
                    'mailing_zip' => $mailing_zip,
                    'shipping_address' => $shipping_address,
                    'shipping_address2' => $shipping_address2,
                    'shipping_city' => $shipping_city,
                    'shipping_state' => $shipping_state,
                    'shipping_zip' => $shipping_zip,
                    'image_name' => $image_name
                ];

                foreach ($acf_updates as $field_name => $field_value) {
                    $acf_result = update_field($field_name, $field_value, 'user_' . $user_id);
                    if (!$acf_result) {
                        output_sync_message("{$user_log_prefix} CREATE WARNING: Failed to update ACF field '{$field_name}' with value '{$field_value}' for new user {$user_id}");
                    }
                }
                
                $created_count++;
                output_sync_message("{$user_log_prefix}: Successfully created new user {$user_id} with personnel_id {$personnel_id} using email: {$email_to_use}");
                
            } catch (Exception $e) {
                $error_msg = "Exception during ACF field updates for new user {$user_id}: " . $e->getMessage();
                output_sync_message("{$user_log_prefix} CREATE ERROR: {$error_msg}");
                record_import_error("{$user_log_prefix} (Personnel ID: {$personnel_id})", $error_msg, $user);
                // User was created but ACF update failed - still count as created
                $created_count++;
            }
        }
    }

    $total_processed = $created_count + $updated_count + $error_count + $skipped_count;
    output_sync_message("SYNC COMPLETE: Total API records: " . count($users) . 
             " | Processed: {$total_processed} | Created: {$created_count} | Updated: {$updated_count} | Errors: {$error_count} | Skipped: {$skipped_count}", 'success');

    // Return results for reporting in the admin interface.
    return [
        'created' => $created_count,
        'updated' => $updated_count,
        'errors' => $error_count,
        'skipped' => $skipped_count,
        'total_api_records' => count($users),
        'message' => "Inactive Personnel users synced. Created: {$created_count}, Updated: {$updated_count}, Errors: {$error_count}, Skipped: {$skipped_count} out of " . count($users) . " API records."
    ];
}

/**
 * ---------------------------------------------------------------------------------
 * 6. News User Import Functions (from JSON)
 * ---------------------------------------------------------------------------------
 * These functions import specific user types (experts and writers) from local JSON files.
 * They create new users or update existing ones, primarily setting specific ACF fields.
 */

/**
 * Imports news experts/sources data from the API endpoint.
 * Creates new 'expert_user' roles or updates existing ones.
 * Handles matching by personnel ID or source_expert_id.
 *
 * @return array|WP_Error An array with import results (created/updated/linked counts) on success,
 * or a WP_Error object on failure.
 */
function import_news_experts()
{
    global $import_errors;
    $import_errors = []; // Reset errors for this operation
    
    $api_url = 'https://secure.caes.uga.edu/rest/news/getExperts';

    // Fetch API Data.
    $response = wp_remote_get($api_url);
    if (is_wp_error($response)) {
        $error_msg = 'API Request Failed for News Experts: ' . $response->get_error_message();
        output_sync_message($error_msg, 'error');
        return new WP_Error('api_error', $error_msg);
    }

    $data = wp_remote_retrieve_body($response);
    $records = json_decode($data, true);

    if (!is_array($records)) {
        $error_msg = 'Invalid API response for News Experts.';
        output_sync_message($error_msg, 'error');
        return new WP_Error('invalid_response', $error_msg);
    }

    output_sync_message("IMPORT START: Processing " . count($records) . " experts from News Experts API");

    $created = 0;
    $updated = 0;
    $linked = 0; // Count of users linked by personnel ID or source_expert_id.
    $error_count = 0;

    // Iterate through each record from the API.
    foreach ($records as $index => $person) {
        $user_log_prefix = "EXPERT #{$index}";
        
        $original_email = isset($person['EMAIL']) ? sanitize_email($person['EMAIL']) : null;
        $first_name = sanitize_text_field($person['FIRST_NAME'] ?? '');
        $last_name = sanitize_text_field($person['LAST_NAME'] ?? '');
        $personnel_id = $person['PERSONNEL_ID'] ?? null;
        $source_expert_id = $person['ID'] ?? null;

        // Basic validation
        // No names is OK.
        // if (empty($first_name) && empty($last_name)) {
        //     $error_msg = "Missing both first name and last name";
        //     output_sync_message("{$user_log_prefix} ERROR: {$error_msg}");
        //     record_import_error("{$user_log_prefix} (Expert ID: {$source_expert_id})", $error_msg, $person);
        //     $error_count++;
        //     continue;
        // }

        if (!$source_expert_id) {
            $error_msg = "Missing source expert ID";
            output_sync_message("{$user_log_prefix} ERROR: {$error_msg}");
            record_import_error("{$user_log_prefix} ({$first_name} {$last_name})", $error_msg, $person);
            $error_count++;
            continue;
        }

        $user_id = null;

        // First, attempt to find user by personnel_id if it exists.
        if ($personnel_id) {
            $users = get_users([
                'meta_key' => 'personnel_id',
                'meta_value' => $personnel_id,
                'number' => 1,
                'fields' => 'ID',
            ]);

            if (!empty($users)) {
                $user_id = $users[0];
                $linked++;
                output_sync_message("{$user_log_prefix}: Found existing user by personnel_id {$personnel_id} with ID {$user_id} for {$first_name} {$last_name}.");
            }
        }

        // If not found by personnel_id, try to find by source_expert_id.
        if (!$user_id && $source_expert_id) {
            $users = get_users([
                'meta_key' => 'source_expert_id',
                'meta_value' => $source_expert_id,
                'number' => 1,
                'fields' => 'ID',
            ]);

            if (!empty($users)) {
                $user_id = $users[0];
                $linked++;
                output_sync_message("{$user_log_prefix}: Found existing user by source_expert_id {$source_expert_id} with ID {$user_id} for {$first_name} {$last_name}.");
            }
        }

        // If still no user found, create a new one.
        if (!$user_id) {
            try {
                $email_to_use = $original_email;
                
                // Check if we need to spoof the email due to duplicates
                if ($original_email && email_exists($original_email)) {
                    // Create a unique spoofed email address using source_expert_id or fallback
                    $unique_id = $source_expert_id ? $source_expert_id : uniqid();
                    $email_to_use = "expert_{$unique_id}@caes.uga.edu.spoofed";
                    output_sync_message("{$user_log_prefix}: Email {$original_email} already exists. Using spoofed email: {$email_to_use}");
                } elseif ($original_email == '') {
                    // No email provided, create placeholder
                    $unique_id = $source_expert_id ? $source_expert_id : uniqid();
                    $email_to_use = "expert_{$unique_id}@caes.uga.edu.spoofed";
                    output_sync_message("{$user_log_prefix}: No email provided. Using spoofed email: {$email_to_use}");
                }

                $username = sanitize_user($email_to_use);
                
                // Check if username exists and modify if needed
                if (username_exists($username)) {
                    $username = $username . '_' . $source_expert_id;
                    output_sync_message("{$user_log_prefix}: Username already exists. Using: {$username}");
                }
                
                $user_id = wp_insert_user([
                    'user_login' => $username,
                    'user_pass' => wp_generate_password(),
                    'user_email' => $email_to_use,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'role' => 'expert_user',
                ]);

                if (is_wp_error($user_id)) {
                    $error_msg = "User creation failed: " . $user_id->get_error_message();
                    output_sync_message("{$user_log_prefix} CREATE ERROR: {$error_msg}");
                    record_import_error("{$user_log_prefix} ({$first_name} {$last_name})", $error_msg, $person);
                    $error_count++;
                    continue; // Skip to next record on error.
                }

                $created++;
                output_sync_message("{$user_log_prefix}: Created new expert user with ID {$user_id} for {$first_name} {$last_name} using email: {$email_to_use}");
                
            } catch (Exception $e) {
                $error_msg = "Exception during user creation: " . $e->getMessage();
                output_sync_message("{$user_log_prefix} CREATE ERROR: {$error_msg}");
                record_import_error("{$user_log_prefix} ({$first_name} {$last_name})", $error_msg, $person);
                $error_count++;
                continue;
            }
        } else {
            $updated++;
        }

        // Update ACF fields if a user was found or created.
        if ($user_id) {
            try {
                // Store original email in uga_email field
                if ($original_email) {
                    update_field('uga_email', $original_email, 'user_' . $user_id);
                }
                
                update_field('phone_number', $person['PHONE'] ?? '', 'user_' . $user_id);
                update_field('description', $person['DESCRIPTION'] ?? '', 'user_' . $user_id);
                
                // Update personnel_id if provided
                if ($personnel_id) {
                    update_field('personnel_id', $personnel_id, 'user_' . $user_id);
                }
                
                update_field('source_expert_id', $source_expert_id, 'user_' . $user_id);
                update_field('area_of_expertise', $person['AREA_OF_EXPERTISE'] ?? '', 'user_' . $user_id);
                update_field('is_source', (bool)($person['IS_SOURCE'] ?? false), 'user_' . $user_id);
                update_field('is_expert', (bool)($person['IS_EXPERT'] ?? false), 'user_' . $user_id);
                update_field('is_active', (bool)($person['IS_ACTIVE'] ?? false), 'user_' . $user_id);
                
                output_sync_message("{$user_log_prefix}: Successfully updated ACF fields for user {$user_id}");
                
            } catch (Exception $e) {
                $error_msg = "Exception during ACF field updates: " . $e->getMessage();
                output_sync_message("{$user_log_prefix} ACF ERROR: {$error_msg}");
                record_import_error("{$user_log_prefix} ({$first_name} {$last_name})", $error_msg, $person);
                $error_count++;
            }
        }
    }

    output_sync_message("IMPORT COMPLETE: News Experts processed. Created: {$created}, Updated: {$updated}, Linked: {$linked}, Errors: {$error_count}", 'success');

    // Return results for reporting in the admin interface.
    return [
        'created' => $created,
        'updated' => $updated,
        'linked' => $linked,
        'errors' => $error_count,
        'total_api_records' => count($records),
        'message' => "News Experts import complete. Created: {$created}, Updated: {$updated}, Linked: {$linked}, Errors: {$error_count}."
    ];
}

/**
 * Imports news writers data from the API endpoint.
 * Creates new 'expert_user' roles or updates existing ones.
 * Handles matching by personnel ID or writer_id.
 *
 * @return array|WP_Error An array with import results (created/updated/linked counts) on success,
 * or a WP_Error object on failure.
 */
function import_news_writers()
{
    global $import_errors;
    $import_errors = []; // Reset errors for this operation
    
    $api_url = 'https://secure.caes.uga.edu/rest/news/getWriters';

    // Fetch API Data.
    $response = wp_remote_get($api_url);
    if (is_wp_error($response)) {
        $error_msg = 'API Request Failed for News Writers: ' . $response->get_error_message();
        output_sync_message($error_msg, 'error');
        return new WP_Error('api_error', $error_msg);
    }

    $data = wp_remote_retrieve_body($response);
    $records = json_decode($data, true);

    if (!is_array($records)) {
        $error_msg = 'Invalid API response for News Writers.';
        output_sync_message($error_msg, 'error');
        return new WP_Error('invalid_response', $error_msg);
    }

    output_sync_message("IMPORT START: Processing " . count($records) . " writers from News Writers API");

    $created = 0;
    $updated = 0;
    $linked = 0; // Count of users linked by personnel ID or writer_id.
    $error_count = 0;

    // Iterate through each record from the API.
    foreach ($records as $index => $person) {
        $user_log_prefix = "WRITER #{$index}";
        
        $original_email = isset($person['EMAIL']) ? sanitize_email($person['EMAIL']) : null;
        $first_name = sanitize_text_field($person['FIRST_NAME'] ?? '');
        $last_name = sanitize_text_field($person['LAST_NAME'] ?? '');
        $personnel_id = $person['PERSONNEL_ID'] ?? null;
        $writer_id_from_api = $person['ID'] ?? null; // Capture the ID field from API.

        // Basic validation
        // No names is OK.
        // if (empty($first_name) && empty($last_name)) {
        //     $error_msg = "Missing both first name and last name";
        //     output_sync_message("{$user_log_prefix} ERROR: {$error_msg}");
        //     record_import_error("{$user_log_prefix} (Writer ID: {$writer_id_from_api})", $error_msg, $person);
        //     $error_count++;
        //     continue;
        // }

        if (!$writer_id_from_api) {
            $error_msg = "Missing writer ID";
            output_sync_message("{$user_log_prefix} ERROR: {$error_msg}");
            record_import_error("{$user_log_prefix} ({$first_name} {$last_name})", $error_msg, $person);
            $error_count++;
            continue;
        }

        // Specifically log data for Elmer Gray if found, or for all if names are empty.
        $current_name = $first_name . ' ' . $last_name;
        if (strpos($current_name, 'Elmer Gray') !== false || (empty($first_name) && empty($last_name))) {
            output_sync_message("{$user_log_prefix}: Processing special case record for '{$current_name}': " . json_encode($person));
        }

        $user_id = null;

        // First, attempt to find user by personnel_id if it exists.
        if ($personnel_id) {
            $users = get_users([
                'meta_key' => 'personnel_id',
                'meta_value' => $personnel_id,
                'number' => 1,
                'fields' => 'ID',
            ]);

            if (!empty($users)) {
                $user_id = $users[0];
                $linked++;
                output_sync_message("{$user_log_prefix}: Found existing user by personnel_id {$personnel_id} with ID {$user_id} for {$first_name} {$last_name}.");
            } else {
                output_sync_message("{$user_log_prefix}: No existing user found by personnel_id {$personnel_id} for {$first_name} {$last_name}.");
            }
        }

        // If not found by personnel_id, try to find by writer_id.
        if (!$user_id && $writer_id_from_api) {
            $users = get_users([
                'meta_key' => 'writer_id',
                'meta_value' => $writer_id_from_api,
                'number' => 1,
                'fields' => 'ID',
            ]);

            if (!empty($users)) {
                $user_id = $users[0];
                $linked++;
                output_sync_message("{$user_log_prefix}: Found existing user by writer_id {$writer_id_from_api} with ID {$user_id} for {$first_name} {$last_name}.");
            } else {
                output_sync_message("{$user_log_prefix}: No existing user found by writer_id {$writer_id_from_api} for {$first_name} {$last_name}.");
            }
        }

        // If still no user found, create a new one.
        if (!$user_id) {
            try {
                $email_to_use = $original_email;
                
                // Check if we need to spoof the email due to duplicates
                if ($original_email && email_exists($original_email)) {
                    // Create a unique spoofed email address using writer_id or fallback
                    $unique_id = $writer_id_from_api ? $writer_id_from_api : uniqid();
                    $email_to_use = "writer_{$unique_id}@caes.uga.edu.spoofed";
                    output_sync_message("{$user_log_prefix}: Email {$original_email} already exists. Using spoofed email: {$email_to_use}");
                } elseif (!$original_email) {
                    // No email provided, create placeholder
                    $unique_id = $writer_id_from_api ? $writer_id_from_api : uniqid();
                    $email_to_use = "writer_{$unique_id}@caes.uga.edu.spoofed";
                    output_sync_message("{$user_log_prefix}: No email provided. Using spoofed email: {$email_to_use}");
                }

                $username = sanitize_user($email_to_use);
                
                // Check if username exists and modify if needed
                if (username_exists($username)) {
                    $username = $username . '_' . $writer_id_from_api;
                    output_sync_message("{$user_log_prefix}: Username already exists. Using: {$username}");
                }
                
                $user_id = wp_insert_user([
                    'user_login' => $username,
                    'user_pass' => wp_generate_password(),
                    'user_email' => $email_to_use,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'role' => 'expert_user',
                ]);

                if (is_wp_error($user_id)) {
                    $error_msg = "User creation failed: " . $user_id->get_error_message();
                    output_sync_message("{$user_log_prefix} CREATE ERROR: {$error_msg}");
                    record_import_error("{$user_log_prefix} ({$first_name} {$last_name})", $error_msg, $person);
                    $error_count++;
                    continue; // Skip to next record on error.
                }

                $created++;
                output_sync_message("{$user_log_prefix}: Created new user with ID {$user_id} for {$first_name} {$last_name} using email: {$email_to_use}.");
                
            } catch (Exception $e) {
                $error_msg = "Exception during user creation: " . $e->getMessage();
                output_sync_message("{$user_log_prefix} CREATE ERROR: {$error_msg}");
                record_import_error("{$user_log_prefix} ({$first_name} {$last_name})", $error_msg, $person);
                $error_count++;
                continue;
            }
        } else {
            $updated++;
        }

        // Update ACF fields if a user was found or created.
        if ($user_id) {
            try {
                output_sync_message("{$user_log_prefix}: Attempting to update ACF fields for user ID: {$user_id} ({$first_name} {$last_name}).");
                
                // Store original email in uga_email field
                if ($original_email) {
                    update_field('uga_email', $original_email, 'user_' . $user_id);
                }
                
                update_field('phone_number', $person['PHONE'] ?? '', 'user_' . $user_id);
                update_field('tagline', $person['TAGLINE'] ?? '', 'user_' . $user_id);
                
                // Update personnel_id if provided
                if ($personnel_id) {
                    update_field('personnel_id', $personnel_id, 'user_' . $user_id);
                    output_sync_message("{$user_log_prefix}: Updated personnel_id to {$personnel_id} for user {$user_id}.");
                }

                // --- Specific Debugging for writer_id ---
                output_sync_message("{$user_log_prefix}: Trying to set writer_id for user {$user_id} to value '{$writer_id_from_api}'.");
                $update_result = update_field('writer_id', $writer_id_from_api, 'user_'.$user_id);

                if ($update_result === false) {
                    $error_msg = "Failed to update 'writer_id' field - field may not exist or not configured correctly for users";
                    output_sync_message("{$user_log_prefix} ACF ERROR: {$error_msg}");
                    record_import_error("{$user_log_prefix} ({$first_name} {$last_name})", $error_msg, $person);
                    $error_count++;
                } else {
                    output_sync_message("{$user_log_prefix}: Successfully updated 'writer_id' to '{$writer_id_from_api}' for user {$user_id}.");
                }
                // --- End Specific Debugging for writer_id ---

                update_field('coverage_area', $person['COVERAGE_AREA'] ?? '', 'user_' . $user_id);
                update_field('is_proofer', (bool)($person['IS_PROOFER'] ?? false), 'user_' . $user_id);
                update_field('is_media_contact', (bool)($person['IS_MEDIA_CONTACT'] ?? false), 'user_' . $user_id);
                update_field('is_active', (bool)($person['IS_ACTIVE'] ?? false), 'user_' . $user_id);
                
                output_sync_message("{$user_log_prefix}: Successfully updated ACF fields for user {$user_id}");
                
            } catch (Exception $e) {
                $error_msg = "Exception during ACF field updates: " . $e->getMessage();
                output_sync_message("{$user_log_prefix} ACF ERROR: {$error_msg}");
                record_import_error("{$user_log_prefix} ({$first_name} {$last_name})", $error_msg, $person);
                $error_count++;
            }
        } else {
            $error_msg = "No user_id obtained for record";
            output_sync_message("{$user_log_prefix} ERROR: {$error_msg}");
            record_import_error("{$user_log_prefix} ({$first_name} {$last_name})", $error_msg, $person);
            $error_count++;
        }
    }

    output_sync_message("IMPORT COMPLETE: News Writers processed. Created: {$created}, Updated: {$updated}, Linked: {$linked}, Errors: {$error_count}", 'success');

    // Return results for reporting in the admin interface.
    return [
        'created' => $created,
        'updated' => $updated,
        'linked' => $linked,
        'errors' => $error_count,
        'total_api_records' => count($records),
        'message' => "News Writers import complete. Created: {$created}, Updated: {$updated}, Linked: {$linked}, Errors: {$error_count}."
    ];
}

/**
 * ---------------------------------------------------------------------------------
 * 7. Admin Tool for User Data Management
 * ---------------------------------------------------------------------------------
 * This section creates a dedicated admin page under the 'Tools' menu
 * to allow administrators to manually trigger the user data import and sync processes.
 * It provides a user-friendly interface with clear options and feedback.
 */

/**
 * Adds the 'User Data Management' submenu page under 'Tools'.
 */
add_action('admin_menu', 'add_user_data_management_page');

function add_user_data_management_page()
{
    add_submenu_page(
        'tools.php', // Parent slug for the 'Tools' menu.
        'User Data Management', // Page title.
        'User Data Management', // Menu title.
        'manage_options', // Capability required to access this page.
        'user-data-management', // Unique slug for the page.
        'user_data_management_page_content' // Callback function to render the page content.
    );
}

/**
 * Renders the content of the 'User Data Management' admin page.
 * Handles form submissions for various data operations and displays messages.
 */
function user_data_management_page_content()
{
    // Check user capabilities for security.
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $message = '';
    $error_message = '';
    $current_action_name = ''; // To display which action is running

    // Check if an action was triggered by the form submission.
    if (isset($_GET['action']) && current_user_can('manage_options')) {
        $action = sanitize_text_field($_GET['action']);

        // Start output buffering to capture messages and flush them incrementally.
        // This is crucial for showing real-time progress for long operations.
        ob_start();
        
        $action_display = esc_html(str_replace('_', ' ', $action));
        
        echo '<div class="wrap">';
        echo '<h1>User Data Management</h1>';
        echo '<p><strong>Starting data operation: <span style="color: blue;">' . $action_display . '</span>. Please do not close this window.</strong></p>';
        
        // Output CSS styles
        echo <<<CSS
            <style>
                .sync-output {
                    background: #f0f0f0;
                    border: 1px solid #ccc;
                    max-height: 500px;
                    overflow-y: auto;
                    padding: 15px;
                    margin: 20px 0;
                    font-family: monospace;
                    font-size: 12px;
                }
                .sync-output .notice {
                    margin: 5px 0;
                    padding: 8px 12px;
                    border-left: 4px solid #0073aa;
                }
                .sync-output .notice-error {
                    border-left-color: #d63638;
                    background-color: #fcf2f2;
                }
                .sync-output .notice-success {
                    border-left-color: #00a32a;
                    background-color: #f0f6fc;
                }
                .sync-output .notice-warning {
                    border-left-color: #ffb900;
                    background-color: #fff8e5;
                }
            </style>
CSS;
        
        echo '<div id="sync-progress-messages" class="sync-output">';
        echo '<div class="notice notice-info"><p>Beginning process... Output will appear below.</p></div>';
        ob_flush();
        flush();

        // Use a switch statement to perform the requested action.
        switch ($action) {
            case 'import_experts':
                $current_action_name = 'Importing News Experts';
                output_sync_message('🚀 Starting to import news experts from the API...', 'info');
                $result = import_news_experts();
                if (is_wp_error($result)) {
                    output_sync_message('❌ Error during News Experts import: ' . $result->get_error_message(), 'error');
                } else {
                    output_sync_message('✅ News Experts import complete: ' . $result['message'], 'success');
                }
                display_import_error_summary('News Experts Import');
                break;

            case 'import_writers':
                $current_action_name = 'Importing News Writers';
                output_sync_message('✍️ Starting to import news writers from the API...', 'info');
                $result = import_news_writers();
                if (is_wp_error($result)) {
                    output_sync_message('❌ Error during News Writers import: ' . $result->get_error_message(), 'error');
                } else {
                    output_sync_message('✅ News Writers import complete: ' . $result['message'], 'success');
                }
                display_import_error_summary('News Writers Import');
                break;

            case 'sync_personnel_active':
                $current_action_name = 'Syncing Active Personnel';
                output_sync_message('🔄 Connecting to external API to sync active personnel...', 'info');
                $result = sync_personnel_users();
                if (is_wp_error($result)) {
                    output_sync_message('❌ Error syncing active personnel: ' . $result->get_error_message(), 'error');
                } else {
                    output_sync_message('✅ Active Personnel sync complete: ' . $result['message'], 'success');
                }
                display_import_error_summary('Active Personnel Sync');
                break;

            case 'sync_personnel_inactive':
                $current_action_name = 'Syncing Inactive Personnel';
                output_sync_message('♻️ Connecting to external API to sync inactive/archived personnel...', 'info');
                $result = sync_personnel_users2();
                if (is_wp_error($result)) {
                    output_sync_message('❌ Error syncing inactive personnel: ' . $result->get_error_message(), 'error');
                } else {
                    output_sync_message('✅ Inactive Personnel sync complete: ' . $result['message'], 'success');
                }
                display_import_error_summary('Inactive Personnel Sync');
                break;

            case 'run_all_syncs':
                $current_action_name = 'Running All Synchronizations';
                output_sync_message('✨ Beginning all scheduled user data synchronization and import operations...', 'info');

                // 1. Import News Experts
                output_sync_message('➡️ Step 1 of 4: Importing News Experts from API...', 'info');
                $result = import_news_experts();
                if (is_wp_error($result)) {
                    output_sync_message('❌ Step 1 Error (News Experts): ' . $result->get_error_message(), 'error');
                } else {
                    output_sync_message('✅ Step 1 Complete (News Experts): ' . $result['message'], 'success');
                }
                display_import_error_summary('Step 1 - News Experts Import');

                // 2. Import News Writers
                output_sync_message('➡️ Step 2 of 4: Importing News Writers from API...', 'info');
                $result = import_news_writers();
                if (is_wp_error($result)) {
                    output_sync_message('❌ Step 2 Error (News Writers): ' . $result->get_error_message(), 'error');
                } else {
                    output_sync_message('✅ Step 2 Complete (News Writers): ' . $result['message'], 'success');
                }
                display_import_error_summary('Step 2 - News Writers Import');

                // 3. Sync Active Personnel
                output_sync_message('➡️ Step 3 of 4: Syncing Active Personnel from primary external API...', 'info');
                $result = sync_personnel_users();
                if (is_wp_error($result)) {
                    output_sync_message('❌ Step 3 Error (Active Personnel): ' . $result->get_error_message(), 'error');
                } else {
                    output_sync_message('✅ Step 3 Complete (Active Personnel): ' . $result['message'], 'success');
                }
                display_import_error_summary('Step 3 - Active Personnel Sync');

                // 4. Sync Inactive Personnel
                output_sync_message('➡️ Step 4 of 4: Syncing Inactive/Archived Personnel from secondary external API...', 'info');
                $result = sync_personnel_users2();
                if (is_wp_error($result)) {
                    output_sync_message('❌ Step 4 Error (Inactive Personnel): ' . $result->get_error_message(), 'error');
                } else {
                    output_sync_message('✅ Step 4 Complete (Inactive Personnel): ' . $result['message'], 'success');
                }
                display_import_error_summary('Step 4 - Inactive Personnel Sync');

                output_sync_message('🎉 All user data sync and import operations have finished!', 'success');
                break;
        }
        
        echo '</div>';
        $back_url = esc_url(admin_url('tools.php?page=user-data-management'));
        echo '<p><a href="' . $back_url . '" class="button button-primary">← Back to User Data Management</a></p>';
        echo '</div>';
        
        // End output buffering and flush all contents.
        ob_end_flush();
        exit; // Exit after processing the action to prevent displaying the form again.

    } // End if (isset($_GET['action']))

    // Display the main form when no action is being processed
    echo '<div class="wrap">';
    echo '<h1>User Data Management</h1>';

    // Display messages from previous runs
    if (isset($_GET['message'])) {
        echo '<div id="message" class="updated notice is-dismissible"><p>' . esc_html(urldecode($_GET['message'])) . '</p></div>';
    }
    if (isset($_GET['error'])) {
        echo '<div id="message" class="error notice is-dismissible"><p>' . esc_html(urldecode($_GET['error'])) . '</p></div>';
    }

    echo '<p>Use the buttons below to manage and synchronize user data. Each operation will display live progress updates and detailed error reports.</p>';

    $form_action = esc_url(admin_url('tools.php'));
    echo '<form method="get" action="' . $form_action . '">';
    echo '<input type="hidden" name="page" value="user-data-management">';
    echo '<h2>Individual Data Operations</h2>';
    echo '<p>Run each data synchronization process separately. Each operation creates or updates WordPress user accounts and provides detailed error reporting.</p>';
    echo '<table class="form-table">';
    echo '<tbody>';
    
    // Import News Experts row
    $experts_url = esc_url(add_query_arg('action', 'import_experts', admin_url('tools.php?page=user-data-management')));
    echo '<tr>';
    echo '<th scope="row">Import News Experts</th>';
    echo '<td>';
    echo '<a href="' . $experts_url . '" class="button button-secondary">Run Import</a>';
    echo '<p class="description">Imports user data from the News Experts API to create or update **Expert Users** (with \'expert_user\' role and specific ACF fields). Shows detailed progress and error summaries.</p>';
    echo '</td>';
    echo '</tr>';
    
    // Import News Writers row
    $writers_url = esc_url(add_query_arg('action', 'import_writers', admin_url('tools.php?page=user-data-management')));
    echo '<tr>';
    echo '<th scope="row">Import News Writers</th>';
    echo '<td>';
    echo '<a href="' . $writers_url . '" class="button button-secondary">Run Import</a>';
    echo '<p class="description">Imports user data from the News Writers API to create or update **Expert Users** (with \'expert_user\' role and specific ACF fields). Includes special debugging for specific cases.</p>';
    echo '</td>';
    echo '</tr>';
    
    // Sync Active Personnel row
    $active_url = esc_url(add_query_arg('action', 'sync_personnel_active', admin_url('tools.php?page=user-data-management')));
    echo '<tr>';
    echo '<th scope="row">Sync Active Personnel</th>';
    echo '<td>';
    echo '<a href="' . $active_url . '" class="button button-secondary">Run Sync</a>';
    echo '<p class="description">Synchronizes **active personnel data** from the primary external personnel API. Creates new users or updates existing ones with the \'personnel_user\' role and comprehensive contact/department ACF fields.</p>';
    echo '</td>';
    echo '</tr>';
    
    // Sync Inactive Personnel row
    $inactive_url = esc_url(add_query_arg('action', 'sync_personnel_inactive', admin_url('tools.php?page=user-data-management')));
    echo '<tr>';
    echo '<th scope="row">Sync Inactive Personnel</th>';
    echo '<td>';
    echo '<a href="' . $inactive_url . '" class="button button-secondary">Run Sync</a>';
    echo '<p class="description">Synchronizes **inactive/archived personnel data** from a secondary external personnel API. Updates \'personnel_user\' roles for those marked as inactive in the source system, with detailed error tracking.</p>';
    echo '</td>';
    echo '</tr>';
    
    echo '</tbody>';
    echo '</table>';

    echo '<h2>Run All Syncs in Logical Order</h2>';
    echo '<p>This comprehensive option will execute all four synchronization processes sequentially in the recommended order, providing live updates and detailed error summaries for each step. Perfect for a full refresh of user data with complete visibility into any issues.</p>';
    echo '<ol>';
    echo '<li>Import News Experts (with error summary)</li>';
    echo '<li>Import News Writers (with error summary)</li>';
    echo '<li>Sync Active Personnel (with error summary)</li>';
    echo '<li>Sync Inactive/Archived Personnel (with error summary)</li>';
    echo '</ol>';
    
    $all_syncs_url = esc_url(add_query_arg('action', 'run_all_syncs', admin_url('tools.php?page=user-data-management')));
    echo '<p>';
    echo '<a href="' . $all_syncs_url . '" class="button button-primary button-large">Run All Syncs Now</a>';
    echo '</p>';
    echo '</form>';
    echo '</div>';
}