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
 * 3. Commented-Out User Deletion Functions (For Reference/Future Use)
 * ---------------------------------------------------------------------------------
 * These functions were previously used for bulk deleting personnel users.
 * They are commented out as they are not part of the active synchronization
 * and should be used with extreme caution if re-enabled.
 */
/*
function delete_all_personnel_users() {
    $users = get_users(['role' => 'personnel_user']);

    foreach ($users as $user) {
        wp_delete_user($user->ID);
    }

    echo 'All personnel users have been deleted.';
}

function add_delete_personnel_users_menu() {
    add_submenu_page(
        'tools.php',
        'Delete Personnel Users',
        'Delete Personnel Users',
        'manage_options',
        'delete-personnel-users',
        function() {
            delete_all_personnel_users();
            echo '<div class="updated"><p>All personnel users deleted!</p></div>';
        }
    );
}
add_action('admin_menu', 'add_delete_personnel_users_menu');
*/


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
    $api_url = 'https://secure.caes.uga.edu/rest/personnel/Personnel/?returnContactInfoColumns=true';

    // Fetch data from the API.
    $response = wp_remote_get($api_url);
    if (is_wp_error($response)) {
        error_log('API Request Failed for Active Personnel: ' . $response->get_error_message());
        return new WP_Error('api_error', 'Active Personnel API Request Failed: ' . $response->get_error_message());
    }

    $data = wp_remote_retrieve_body($response);
    $users = json_decode($data, true);

    if (!is_array($users)) {
        error_log('Invalid API response for Active Personnel.');
        return new WP_Error('invalid_response', 'Invalid API response for Active Personnel.');
    }

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

    // Iterate through each user record from the API.
    foreach ($users as $user) {
        // Sanitize and extract relevant user data from the API response.
        $personnel_id = intval($user['PERSONNEL_ID']);
        $college_id = intval($user['COLLEGEID']);
        $original_email = sanitize_email($user['EMAIL']);
        $username = sanitize_user(strtolower(str_replace(' ', '', $user['NAME'])));
        $nickname = sanitize_text_field($user['NAME']);
        $display_name = sanitize_text_field($user['NAME']);
        $first_name = sanitize_text_field($user['FNAME']);
        $last_name = sanitize_text_field($user['LNAME']);
        $title = sanitize_text_field($user['TITLE']);
        $department = sanitize_text_field($user['DEPARTMENT']);
        $program_area = sanitize_text_field($user['PROGRAMAREALIST']);
        $phone = sanitize_text_field($user['PHONE_NUMBER']);
        $cell_phone = sanitize_text_field($user['CELL_PHONE_NUMBER']);
        $fax = sanitize_text_field($user['FAX_NUMBER']);
        $caes_location_id = intval($user['CAES_LOCATION_ID']);
        $mailing_address = sanitize_text_field($user['MAILING_ADDRESS1']);
        $mailing_address2 = sanitize_text_field($user['MAILING_ADDRESS2']);
        $mailing_city = sanitize_text_field($user['MAILING_CITY']);
        $mailing_state = sanitize_text_field($user['MAILING_STATE']);
        $mailing_zip = sanitize_text_field($user['MAILING_ZIP']);
        $shipping_address = sanitize_text_field($user['SHIPPING_ADDRESS1']);
        $shipping_address2 = sanitize_text_field($user['SHIPPING_ADDRESS2']);
        $shipping_city = sanitize_text_field($user['SHIPPING_CITY']);
        $shipping_state = sanitize_text_field($user['SHIPPING_STATE']);
        $shipping_zip = sanitize_text_field($user['SHIPPING_ZIP']);
        $image_name = sanitize_text_field($user['IMAGE']);

        $api_user_ids[] = $personnel_id;

        $user_id = null;

        // First, check if the user already exists by personnel_id.
        if (isset($existing_user_ids[$personnel_id])) {
            $user_id = $existing_user_ids[$personnel_id];
            error_log("Found existing user by personnel_id {$personnel_id}: User ID {$user_id}");
        } else {
            // Fallback: Check if user exists by email (for users who might not have personnel_id set)
            $existing_user = get_user_by('email', $original_email);
            if ($existing_user && in_array('personnel_user', $existing_user->roles)) {
                $user_id = $existing_user->ID;
                error_log("Found existing personnel_user by email {$original_email}: User ID {$user_id} (missing personnel_id)");
            }
        }

        if ($user_id) {
            // Update Existing User
            $updated_count++;

            // Don't use the first name and last name taken from the API.
            // Instead, dice up the 'NAME' API field and find the first and last names from it.
            // Then use those in the first_name and last_name WordPress fields.

            // Update core WordPress user fields.
            wp_update_user([
                'ID' => $user_id,
                'user_email' => $original_email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'nickname' => $nickname,
                'display_name' => $display_name
            ]);

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

            error_log("Updated existing user {$user_id} with personnel_id {$personnel_id}");
        } else {

            $email_to_use = $original_email;
            
            // Check if email already exists in WordPress
            if (email_exists($original_email)) {
                // Create a unique spoofed email address
                $email_to_use = "personnel_{$personnel_id}@caes.uga.edu.spoofed";
                error_log("Email {$original_email} already exists. Using spoofed email: {$email_to_use}");
            }

            // Create New User if not found.
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

            if (!is_wp_error($user_id)) {
                $created_count++;

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

                error_log("Created new user {$user_id} with personnel_id {$personnel_id} using email: {$email_to_use}");
            } else {
                error_log("Failed to create user for personnel_id {$personnel_id}: " . $user_id->get_error_message());
            }
        }
    }

    /*
     * This commented-out section would remove users from WordPress who are no longer
     * present in the API feed. Use with caution as it can lead to data loss if API is temperamental.
    foreach ($existing_user_ids as $existing_personnel_id => $user_id) {
        if (!in_array($existing_personnel_id, $api_user_ids)) {
            wp_delete_user($user_id);
        }
    }
    */

    // Return results for reporting in the admin interface.
    return [
        'created' => $created_count,
        'updated' => $updated_count,
        'message' => "Personnel users synced successfully. Created: {$created_count}, Updated: {$updated_count}."
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
    // API endpoint specifically for inactive news authors/experts with contact info.
    $api_url = 'https://secure.caes.uga.edu/rest/personnel/Personnel?returnOnlyNewsAuthorsAndExpertsAndPubAuthors=true&isActive=false&returnContactInfoColumns=true';

    // Fetch API Data.
    $response = wp_remote_get($api_url);
    if (is_wp_error($response)) {
        error_log('API Request Failed for Inactive Personnel: ' . $response->get_error_message());
        return new WP_Error('api_error', 'Inactive Personnel API Request Failed: ' . $response->get_error_message());
    }

    $data = wp_remote_retrieve_body($response);
    $users = json_decode($data, true);

    if (!is_array($users)) {
        error_log('Invalid API response for Inactive Personnel. Response body: ' . substr($data, 0, 500));
        return new WP_Error('invalid_response', 'Invalid API response for Inactive Personnel.');
    }

    error_log("SYNC START: Processing " . count($users) . " users from Inactive Personnel API");

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

    error_log("EXISTING USERS MAP: Found " . count($existing_user_ids) . " existing personnel users");

    $api_user_ids = []; // To keep track of personnel_ids from the current API fetch.
    $created_count = 0;
    $updated_count = 0;
    $error_count = 0;
    $skipped_count = 0;

    // Iterate through each user record from the API.
    foreach ($users as $index => $user) {
        $user_log_prefix = "USER #{$index}";
        
        // Log raw user data for debugging
        error_log("{$user_log_prefix}: Raw API data: " . json_encode($user));
        
        // Validate required fields first
        $required_fields = ['PERSONNEL_ID', 'EMAIL', 'NAME', 'FNAME', 'LNAME'];
        $missing_fields = [];
        foreach ($required_fields as $field) {
            if (!isset($user[$field]) || empty(trim($user[$field]))) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            error_log("{$user_log_prefix} ERROR: Missing required fields: " . implode(', ', $missing_fields));
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
            error_log("{$user_log_prefix} ERROR: Invalid personnel_id after sanitization: '{$user['PERSONNEL_ID']}' -> {$personnel_id}");
            $error_count++;
            continue;
        }
        
        if (!is_email($original_email)) {
            error_log("{$user_log_prefix} ERROR: Invalid email after sanitization: '{$user['EMAIL']}' -> '{$original_email}'");
            $error_count++;
            continue;
        }
        
        if (empty($username)) {
            error_log("{$user_log_prefix} ERROR: Empty username after sanitization. Original NAME: '{$user['NAME']}'");
            $error_count++;
            continue;
        }
        
        if (strlen($username) < 3) {
            error_log("{$user_log_prefix} ERROR: Username too short after sanitization: '{$username}' (original: '{$user['NAME']}')");
            $error_count++;
            continue;
        }

        error_log("{$user_log_prefix}: Processing personnel_id={$personnel_id}, email={$original_email}, username={$username}");

        $api_user_ids[] = $personnel_id;

        $user_id = null;

        // First, check if the user already exists by personnel_id.
        if (isset($existing_user_ids[$personnel_id])) {
            $user_id = $existing_user_ids[$personnel_id];
            error_log("{$user_log_prefix}: Found existing user by personnel_id {$personnel_id}: User ID {$user_id}");
        } else {
            error_log("{$user_log_prefix}: No existing user found with personnel_id {$personnel_id}");
            

        }

        if ($user_id) {
            // Update Existing User
            error_log("{$user_log_prefix}: UPDATING existing user ID {$user_id}");
            
            try {
                // Update core WordPress user fields.
                $update_result = wp_update_user([
                    'ID' => $user_id,
                    'user_email' => $original_email,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'display_name' => $display_name
                ]);

                if (is_wp_error($update_result)) {
                    error_log("{$user_log_prefix} UPDATE ERROR: wp_update_user failed: " . $update_result->get_error_message());
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
                        error_log("{$user_log_prefix} UPDATE WARNING: Failed to update ACF field '{$field_name}' with value '{$field_value}' for user {$user_id}");
                    }
                }
                
                $updated_count++;
                error_log("{$user_log_prefix}: Successfully updated user {$user_id} with personnel_id {$personnel_id}");
                
            } catch (Exception $e) {
                error_log("{$user_log_prefix} UPDATE ERROR: Exception during update: " . $e->getMessage());
                $error_count++;
            }
        } else {
            // Create New User if not found.
            error_log("{$user_log_prefix}: CREATING new user");
            
            $email_to_use = $original_email;
            
            // Check if email already exists in WordPress
            if (email_exists($original_email)) {
                // Create a unique spoofed email address
                $email_to_use = "personnel_{$personnel_id}@caes.uga.edu.spoofed";
                error_log("{$user_log_prefix}: Email {$original_email} already exists. Using spoofed email: {$email_to_use}");
            }
            
            // Check if username already exists
            if (username_exists($username)) {
                $original_username = $username;
                $username = $username . '_' . $personnel_id;
                error_log("{$user_log_prefix}: Username '{$original_username}' already exists. Using: '{$username}'");
            }
            
            // Validate final username
            if (!validate_username($username)) {
                error_log("{$user_log_prefix} CREATE ERROR: Invalid username after all processing: '{$username}'");
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
                
                error_log("{$user_log_prefix}: Creating user with data: " . json_encode($user_data));
                
                $user_id = wp_insert_user($user_data);
                
            } catch (Exception $e) {
                error_log("{$user_log_prefix} CREATE ERROR: Exception during wp_insert_user: " . $e->getMessage());
                $error_count++;
                continue; // Skip this user if there's an error.
            }

            if (is_wp_error($user_id)) {
                error_log("{$user_log_prefix} CREATE ERROR: wp_insert_user failed: " . $user_id->get_error_message() . 
                         " | Error data: " . json_encode($user_id->get_error_data()));
                $error_count++;
                continue;
            }
            
            if (!$user_id || $user_id <= 0) {
                error_log("{$user_log_prefix} CREATE ERROR: wp_insert_user returned invalid user ID: " . var_export($user_id, true));
                $error_count++;
                continue;
            }

            // Verify user was actually created
            $created_user = get_user_by('ID', $user_id);
            if (!$created_user) {
                error_log("{$user_log_prefix} CREATE ERROR: User ID {$user_id} was returned but user doesn't exist in database");
                $error_count++;
                continue;
            }

            error_log("{$user_log_prefix}: Successfully created user ID {$user_id}, verifying role assignment...");
            
            // Verify role assignment
            if (!in_array('personnel_user', $created_user->roles)) {
                error_log("{$user_log_prefix} CREATE WARNING: User {$user_id} doesn't have personnel_user role. Current roles: " . implode(', ', $created_user->roles));
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
                        error_log("{$user_log_prefix} CREATE WARNING: Failed to update ACF field '{$field_name}' with value '{$field_value}' for new user {$user_id}");
                    }
                }
                
                $created_count++;
                error_log("{$user_log_prefix}: Successfully created new user {$user_id} with personnel_id {$personnel_id} using email: {$email_to_use}");
                
            } catch (Exception $e) {
                error_log("{$user_log_prefix} CREATE ERROR: Exception during ACF field updates for new user {$user_id}: " . $e->getMessage());
                // User was created but ACF update failed - still count as created
                $created_count++;
            }
        }
    }

    $total_processed = $created_count + $updated_count + $error_count + $skipped_count;
    error_log("SYNC COMPLETE: Total API records: " . count($users) . 
             " | Processed: {$total_processed} | Created: {$created_count} | Updated: {$updated_count} | Errors: {$error_count} | Skipped: {$skipped_count}");

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
    $api_url = 'https://secure.caes.uga.edu/rest/news/getExperts';

    // Fetch API Data.
    $response = wp_remote_get($api_url);
    if (is_wp_error($response)) {
        error_log('API Request Failed for News Experts: ' . $response->get_error_message());
        return new WP_Error('api_error', 'News Experts API Request Failed: ' . $response->get_error_message());
    }

    $data = wp_remote_retrieve_body($response);
    $records = json_decode($data, true);

    if (!is_array($records)) {
        error_log('Invalid API response for News Experts.');
        return new WP_Error('invalid_response', 'Invalid API response for News Experts.');
    }

    $created = 0;
    $updated = 0;
    $linked = 0; // Count of users linked by personnel ID or source_expert_id.

    // Iterate through each record from the API.
    foreach ($records as $person) {
        $original_email = isset($person['EMAIL']) ? sanitize_email($person['EMAIL']) : null;
        $first_name = sanitize_text_field($person['FIRST_NAME'] ?? '');
        $last_name = sanitize_text_field($person['LAST_NAME'] ?? '');
        $personnel_id = $person['PERSONNEL_ID'] ?? null;
        $source_expert_id = $person['ID'] ?? null;

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
                error_log("Found existing user by personnel_id {$personnel_id} with ID {$user_id} for {$first_name} {$last_name}.");
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
                error_log("Found existing user by source_expert_id {$source_expert_id} with ID {$user_id} for {$first_name} {$last_name}.");
            }
        }

        // If still no user found, create a new one.
        if (!$user_id) {
            $email_to_use = $original_email;
            
            // Check if we need to spoof the email due to duplicates
            if ($original_email && email_exists($original_email)) {
                // Create a unique spoofed email address using source_expert_id or fallback
                $unique_id = $source_expert_id ? $source_expert_id : uniqid();
                $email_to_use = "expert_{$unique_id}@caes.uga.edu.spoofed";
                error_log("Email {$original_email} already exists. Using spoofed email: {$email_to_use}");
            } elseif (!$original_email) {
                // No email provided, create placeholder
                $unique_id = $source_expert_id ? $source_expert_id : uniqid();
                $email_to_use = "expert_{$unique_id}@caes.uga.edu.spoofed";
                error_log("No email provided. Using spoofed email: {$email_to_use}");
            }

            $username = sanitize_user($email_to_use);
            
            $user_id = wp_insert_user([
                'user_login' => $username,
                'user_pass' => wp_generate_password(),
                'user_email' => $email_to_use,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'role' => 'expert_user',
            ]);

            if (is_wp_error($user_id)) {
                error_log("User creation failed for expert {$first_name} {$last_name}: " . $user_id->get_error_message());
                continue; // Skip to next record on error.
            }

            $created++;
            error_log("Created new expert user with ID {$user_id} for {$first_name} {$last_name} using email: {$email_to_use}");
        } else {
            $updated++;
        }

        // Update ACF fields if a user was found or created.
        if ($user_id) {
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
        }
    }

    // Return results for reporting in the admin interface.
    return [
        'created' => $created,
        'updated' => $updated,
        'linked' => $linked,
        'message' => "News Experts import complete. Created: {$created}, Updated: {$updated}, Linked: {$linked}."
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
    $api_url = 'https://secure.caes.uga.edu/rest/news/getWriters';

    // Fetch API Data.
    $response = wp_remote_get($api_url);
    if (is_wp_error($response)) {
        error_log('API Request Failed for News Writers: ' . $response->get_error_message());
        return new WP_Error('api_error', 'News Writers API Request Failed: ' . $response->get_error_message());
    }

    $data = wp_remote_retrieve_body($response);
    $records = json_decode($data, true);

    if (!is_array($records)) {
        error_log('Invalid API response for News Writers.');
        return new WP_Error('invalid_response', 'Invalid API response for News Writers.');
    }

    $created = 0;
    $updated = 0;
    $linked = 0; // Count of users linked by personnel ID or writer_id.

    // Iterate through each record from the API.
    foreach ($records as $person) {
        $original_email = isset($person['EMAIL']) ? sanitize_email($person['EMAIL']) : null;
        $first_name = sanitize_text_field($person['FIRST_NAME'] ?? '');
        $last_name = sanitize_text_field($person['LAST_NAME'] ?? '');
        $personnel_id = $person['PERSONNEL_ID'] ?? null;
        $writer_id_from_api = $person['ID'] ?? null; // Capture the ID field from API.

        // Specifically log data for Elmer Gray if found, or for all if names are empty.
        $current_name = $first_name . ' ' . $last_name;
        if (strpos($current_name, 'Elmer Gray') !== false || (empty($first_name) && empty($last_name))) {
            error_log('DEBUG WRITER: Processing record: ' . print_r($person, true));
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
                error_log("DEBUG WRITER: Found existing user by personnel_id {$personnel_id} with ID {$user_id} for {$first_name} {$last_name}.");
            } else {
                error_log("DEBUG WRITER: No existing user found by personnel_id {$personnel_id} for {$first_name} {$last_name}.");
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
                error_log("DEBUG WRITER: Found existing user by writer_id {$writer_id_from_api} with ID {$user_id} for {$first_name} {$last_name}.");
            } else {
                error_log("DEBUG WRITER: No existing user found by writer_id {$writer_id_from_api} for {$first_name} {$last_name}.");
            }
        }

        // If still no user found, create a new one.
        if (!$user_id) {
            $email_to_use = $original_email;
            
            // Check if we need to spoof the email due to duplicates
            if ($original_email && email_exists($original_email)) {
                // Create a unique spoofed email address using writer_id or fallback
                $unique_id = $writer_id_from_api ? $writer_id_from_api : uniqid();
                $email_to_use = "writer_{$unique_id}@caes.uga.edu.spoofed";
                error_log("DEBUG WRITER: Email {$original_email} already exists. Using spoofed email: {$email_to_use}");
            } elseif (!$original_email) {
                // No email provided, create placeholder
                $unique_id = $writer_id_from_api ? $writer_id_from_api : uniqid();
                $email_to_use = "writer_{$unique_id}@caes.uga.edu.spoofed";
                error_log("DEBUG WRITER: No email provided. Using spoofed email: {$email_to_use}");
            }

            $username = sanitize_user($email_to_use);
            
            $user_id = wp_insert_user([
                'user_login' => $username,
                'user_pass' => wp_generate_password(),
                'user_email' => $email_to_use,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'role' => 'expert_user',
            ]);

            if (is_wp_error($user_id)) {
                error_log("DEBUG WRITER: User creation failed for writer {$first_name} {$last_name}: " . $user_id->get_error_message());
                continue; // Skip to next record on error.
            }

            $created++;
            error_log("DEBUG WRITER: Created new user with ID {$user_id} for {$first_name} {$last_name} using email: {$email_to_use}.");
        } else {
            $updated++;
        }

        // Update ACF fields if a user was found or created.
        if ($user_id) {
            error_log("DEBUG WRITER: Attempting to update ACF fields for user ID: {$user_id} ({$first_name} {$last_name}).");
            
            // Store original email in uga_email field
            if ($original_email) {
                update_field('uga_email', $original_email, 'user_' . $user_id);
            }
            
            update_field('phone_number', $person['PHONE'] ?? '', 'user_' . $user_id);
            update_field('tagline', $person['TAGLINE'] ?? '', 'user_' . $user_id);
            
            // Update personnel_id if provided
            if ($personnel_id) {
                update_field('personnel_id', $personnel_id, 'user_' . $user_id);
                error_log("DEBUG WRITER: Updated personnel_id to {$personnel_id} for user {$user_id}.");
            }

            // --- Specific Debugging for writer_id ---
            error_log("DEBUG WRITER: Trying to set writer_id for user {$user_id} to value '{$writer_id_from_api}'.");
            $update_result = update_field('writer_id', $writer_id_from_api, 'user_' . $user_id);

            if ($update_result === false) {
                error_log("DEBUG WRITER: Failed to update 'writer_id' for user {$user_id}. This could mean the field doesn't exist or is not configured correctly for users.");
            } else {
                error_log("DEBUG WRITER: Successfully updated 'writer_id' to '{$writer_id_from_api}' for user {$user_id}.");
            }
            // --- End Specific Debugging for writer_id ---

            update_field('coverage_area', $person['COVERAGE_AREA'] ?? '', 'user_' . $user_id);
            update_field('is_proofer', (bool)($person['IS_PROOFER'] ?? false), 'user_' . $user_id);
            update_field('is_media_contact', (bool)($person['IS_MEDIA_CONTACT'] ?? false), 'user_' . $user_id);
            update_field('is_active', (bool)($person['IS_ACTIVE'] ?? false), 'user_' . $user_id);
        } else {
            error_log("DEBUG WRITER: No user_id obtained for record: " . print_r($person, true));
        }
    }

    // Return results for reporting in the admin interface.
    return [
        'created' => $created,
        'updated' => $updated,
        'linked' => $linked,
        'message' => "News Writers import complete. Created: {$created}, Updated: {$updated}, Linked: {$linked}."
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
?>
        <div class="wrap">
            <h1>User Data Management</h1>
            <p><strong>Starting data operation: <span style="color: blue;"><?php echo esc_html(str_replace('_', ' ', $action)); ?></span>. Please do not close this window.</strong></p>
            <div id="sync-progress-messages">
                <?php
                echo '<div class="notice notice-info"><p>Beginning process... Output will appear below.</p></div>';
                ob_flush();
                flush();
                ?>
                <?php

                // Use a switch statement to perform the requested action.
                switch ($action) {
                    case 'import_experts':
                        $current_action_name = 'Importing News Experts';
                        echo '<div class="notice notice-info"><p>🚀 Starting to import news experts from the JSON file...</p></div>';
                        ob_flush();
                        flush();
                        $result = import_news_experts();
                        if (is_wp_error($result)) {
                            echo '<div class="notice notice-error"><p>❌ <strong>Error during News Experts import:</strong> ' . esc_html($result->get_error_message()) . '</p></div>';
                        } else {
                            echo '<div class="notice notice-success"><p>✅ <strong>News Experts import complete:</strong> ' . esc_html($result['message']) . '</p></div>';
                        }
                        break;

                    case 'import_writers':
                        $current_action_name = 'Importing News Writers';
                        echo '<div class="notice notice-info"><p>✍️ Starting to import news writers from the JSON file...</p></div>';
                        ob_flush();
                        flush();
                        $result = import_news_writers();
                        if (is_wp_error($result)) {
                            echo '<div class="notice notice-error"><p>❌ <strong>Error during News Writers import:</strong> ' . esc_html($result->get_error_message()) . '</p></div>';
                        } else {
                            echo '<div class="notice notice-success"><p>✅ <strong>News Writers import complete:</strong> ' . esc_html($result['message']) . '</p></div>';
                        }
                        break;

                    case 'sync_personnel_active':
                        $current_action_name = 'Syncing Active Personnel';
                        echo '<div class="notice notice-info"><p>🔄 Connecting to external API to sync active personnel...</p></div>';
                        ob_flush();
                        flush();
                        $result = sync_personnel_users();
                        if (is_wp_error($result)) {
                            echo '<div class="notice notice-error"><p>❌ <strong>Error syncing active personnel:</strong> ' . esc_html($result->get_error_message()) . '</p></div>';
                        } else {
                            echo '<div class="notice notice-success"><p>✅ <strong>Active Personnel sync complete:</strong> ' . esc_html($result['message']) . '</p></div>';
                        }
                        break;

                    case 'sync_personnel_inactive':
                        $current_action_name = 'Syncing Inactive Personnel';
                        echo '<div class="notice notice-info"><p>♻️ Connecting to external API to sync inactive/archived personnel...</p></div>';
                        ob_flush();
                        flush();
                        $result = sync_personnel_users2();
                        if (is_wp_error($result)) {
                            echo '<div class="notice notice-error"><p>❌ <strong>Error syncing inactive personnel:</strong> ' . esc_html($result->get_error_message()) . '</p></div>';
                        } else {
                            echo '<div class="notice notice-success"><p>✅ <strong>Inactive Personnel sync complete:</strong> ' . esc_html($result['message']) . '</p></div>';
                        }
                        break;

                    case 'run_all_syncs':
                        $current_action_name = 'Running All Synchronizations';
                        echo '<div class="notice notice-info"><p>✨ <strong>Beginning all scheduled user data synchronization and import operations...</strong></p></div>';
                        ob_flush();
                        flush();

                        // 1. Import News Experts
                        echo '<div class="notice notice-info"><p>➡️ Step 1 of 4: Importing News Experts from `news-experts.json`...</p></div>';
                        ob_flush();
                        flush();
                        $result = import_news_experts();
                        if (is_wp_error($result)) {
                            echo '<div class="notice notice-error"><p>❌ <strong>Step 1 Error (News Experts):</strong> ' . esc_html($result->get_error_message()) . '</p></div>';
                        } else {
                            echo '<div class="notice notice-success"><p>✅ <strong>Step 1 Complete (News Experts):</strong> ' . esc_html($result['message']) . '</p></div>';
                        }
                        ob_flush();
                        flush();

                        // 2. Import News Writers
                        echo '<div class="notice notice-info"><p>➡️ Step 2 of 4: Importing News Writers from `news-writers.json`...</p></div>';
                        ob_flush();
                        flush();
                        $result = import_news_writers();
                        if (is_wp_error($result)) {
                            echo '<div class="notice notice-error"><p>❌ <strong>Step 2 Error (News Writers):</strong> ' . esc_html($result->get_error_message()) . '</p></div>';
                        } else {
                            echo '<div class="notice notice-success"><p>✅ <strong>Step 2 Complete (News Writers):</strong> ' . esc_html($result['message']) . '</p></div>';
                        }
                        ob_flush();
                        flush();

                        // 3. Sync Active Personnel
                        echo '<div class="notice notice-info"><p>➡️ Step 3 of 4: Syncing Active Personnel from primary external API...</p></div>';
                        ob_flush();
                        flush();
                        $result = sync_personnel_users();
                        if (is_wp_error($result)) {
                            echo '<div class="notice notice-error"><p>❌ <strong>Step 3 Error (Active Personnel):</strong> ' . esc_html($result->get_error_message()) . '</p></div>';
                        } else {
                            echo '<div class="notice notice-success"><p>✅ <strong>Step 3 Complete (Active Personnel):</strong> ' . esc_html($result['message']) . '</p></div>';
                        }
                        ob_flush();
                        flush();

                        // 4. Sync Inactive Personnel
                        echo '<div class="notice notice-info"><p>➡️ Step 4 of 4: Syncing Inactive/Archived Personnel from secondary external API...</p></div>';
                        ob_flush();
                        flush();
                        $result = sync_personnel_users2();
                        if (is_wp_error($result)) {
                            echo '<div class="notice notice-error"><p>❌ <strong>Step 4 Error (Inactive Personnel):</strong> ' . esc_html($result->get_error_message()) . '</p></div>';
                        } else {
                            echo '<div class="notice notice-success"><p>✅ <strong>Step 4 Complete (Inactive Personnel):</strong> ' . esc_html($result['message']) . '</p></div>';
                        }
                        ob_flush();
                        flush();

                        echo '<div class="notice notice-success"><p>🎉 <strong>All user data sync and import operations have finished successfully!</strong></p></div>';
                        break;
                }
                ?>
            </div>
            <p><a href="<?php echo esc_url(admin_url('tools.php?page=user-data-management')); ?>" class="button button-primary">← Back to User Data Management</a></p>
        </div>
    <?php
        // End output buffering and flush all contents.
        ob_end_flush();
        exit; // Exit after processing the action to prevent displaying the form again.

    } // End if (isset($_GET['action']))
    ?>

    <div class="wrap">
        <h1>User Data Management</h1>

        <?php
        // This section for displaying messages from previous runs is mostly decorative now,
        // as direct output is used for current run feedback.
        if (isset($_GET['message'])) {
            echo '<div id="message" class="updated notice is-dismissible"><p>' . esc_html(urldecode($_GET['message'])) . '</p></div>';
        }
        if (isset($_GET['error'])) {
            echo '<div id="message" class="error notice is-dismissible"><p>' . esc_html(urldecode($_GET['error'])) . '</p></div>';
        }
        ?>

        <p>Use the buttons below to manage and synchronize user data. Each operation will display live progress updates.</p>

        <form method="get" action="<?php echo esc_url(admin_url('tools.php')); ?>">
            <input type="hidden" name="page" value="user-data-management">
            <h2>Individual Data Operations</h2>
            <p>Run each data synchronization process separately. Each operation creates or updates WordPress user accounts.</p>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">Import News Experts</th>
                        <td>
                            <a href="<?php echo esc_url(add_query_arg('action', 'import_experts', admin_url('tools.php?page=user-data-management'))); ?>" class="button button-secondary">Run Import</a>
                            <p class="description">Imports user data from <code>json/news-experts.json</code> to create or update **Expert Users** (with 'expert_user' role and specific ACF fields).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Import News Writers</th>
                        <td>
                            <a href="<?php echo esc_url(add_query_arg('action', 'import_writers', admin_url('tools.php?page=user-data-management'))); ?>" class="button button-secondary">Run Import</a>
                            <p class="description">Imports user data from <code>json/news-writers.json</code> to create or update **Expert Users** (with 'expert_user' role and specific ACF fields).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Sync Active Personnel</th>
                        <td>
                            <a href="<?php echo esc_url(add_query_arg('action', 'sync_personnel_active', admin_url('tools.php?page=user-data-management'))); ?>" class="button button-secondary">Run Sync</a>
                            <p class="description">Synchronizes **active personnel data** from the primary external personnel API. This creates new users or updates existing ones with the 'personnel_user' role and comprehensive contact/department ACF fields.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Sync Inactive Personnel</th>
                        <td>
                            <a href="<?php echo esc_url(add_query_arg('action', 'sync_personnel_inactive', admin_url('tools.php?page=user-data-management'))); ?>" class="button button-secondary">Run Sync</a>
                            <p class="description">Synchronizes **inactive/archived personnel data** from a secondary external personnel API. It updates 'personnel_user' roles for those marked as inactive in the source system, ensuring their status is current.</p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <h2>Run All Syncs in Logical Order</h2>
            <p>This comprehensive option will execute all four synchronization processes sequentially in the recommended order, providing live updates as each step completes. This is useful for a full refresh of user data.</p>
            <ol>
                <li>Import News Experts</li>
                <li>Import News Writers</li>
                <li>Sync Active Personnel (from primary API)</li>
                <li>Sync Inactive/Archived Personnel (from secondary API)</li>
            </ol>
            <p>
                <a href="<?php echo esc_url(add_query_arg('action', 'run_all_syncs', admin_url('tools.php?page=user-data-management'))); ?>" class="button button-primary button-large">Run All Syncs Now</a>
            </p>
        </form>
    </div>
<?php
}
