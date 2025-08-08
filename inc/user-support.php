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
        $email = sanitize_email($user['EMAIL']);
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
            $existing_user = get_user_by('email', $email);
            if ($existing_user && in_array('personnel_user', $existing_user->roles)) {
                $user_id = $existing_user->ID;
                error_log("Found existing personnel_user by email {$email}: User ID {$user_id} (missing personnel_id)");
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
                'user_email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'nickname' => $nickname,
                'display_name' => $display_name
            ]);

            // Update ACF fields for the existing user.
            update_field('personnel_id', $personnel_id, 'user_' . $user_id); // Make sure this gets set!
            update_field('college_id', $college_id, 'user_' . $user_id);
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
            // Create New User if not found.
            $user_id = wp_insert_user([
                'user_login' => $username,
                'user_email' => $email,
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

                error_log("Created new user {$user_id} with personnel_id {$personnel_id}");
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
    $api_url = 'https://secure.caes.uga.edu/rest/personnel/Personnel?returnOnlyNewsAuthorsAndExperts=true&isActive=false&returnContactInfoColumns=true';

    // Fetch API Data.
    $response = wp_remote_get($api_url);
    if (is_wp_error($response)) {
        error_log('API Request Failed for Inactive Personnel: ' . $response->get_error_message());
        return new WP_Error('api_error', 'Inactive Personnel API Request Failed: ' . $response->get_error_message());
    }

    $data = wp_remote_retrieve_body($response);
    $users = json_decode($data, true);

    if (!is_array($users)) {
        error_log('Invalid API response for Inactive Personnel.');
        return new WP_Error('invalid_response', 'Invalid API response for Inactive Personnel.');
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
        $email = sanitize_email($user['EMAIL']);
        $username = sanitize_user(strtolower(str_replace(' ', '', $user['NAME'])));
        $first_name = sanitize_text_field($user['FNAME']);
        $last_name = sanitize_text_field($user['LNAME']);
        $display_name = sanitize_text_field($user['NAME']);
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
            $existing_user = get_user_by('email', $email);
            if ($existing_user && in_array('personnel_user', $existing_user->roles)) {
                $user_id = $existing_user->ID;
                error_log("Found existing personnel_user by email {$email}: User ID {$user_id} (missing personnel_id)");
            }
        }

        if ($user_id) {
            // Update Existing User
            $updated_count++;
            
            // Update core WordPress user fields.
            wp_update_user([
                'ID' => $user_id,
                'user_email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name,
            ]);

            // Update ACF fields for the existing user.
            update_field('personnel_id', $personnel_id, 'user_' . $user_id); // Make sure this gets set!
            update_field('college_id', $college_id, 'user_' . $user_id);
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
            // Create New User if not found.
            try {
                $user_id = wp_insert_user([
                    'user_login' => $username,
                    'user_email' => $email,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'user_pass' => wp_generate_password(),
                    'role' => 'personnel_user'
                ]);
            } catch (Exception $e) {
                error_log("Error creating user from Inactive Personnel feed: " . $e->getMessage());
                continue; // Skip this user if there's an error.
            }

            if (!is_wp_error($user_id)) {
                $created_count++;
                
                // Update ACF fields for the new user.
                update_field('personnel_id', $personnel_id, 'user_' . $user_id);
                update_field('college_id', $college_id, 'user_' . $user_id);
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
                
                error_log("Created new user {$user_id} with personnel_id {$personnel_id}");
            } else {
                error_log("Failed to create user for personnel_id {$personnel_id}: " . $user_id->get_error_message());
            }
        }
    }

    // Return results for reporting in the admin interface.
    return [
        'created' => $created_count,
        'updated' => $updated_count,
        'message' => "Inactive Personnel users synced successfully. Created: {$created_count}, Updated: {$updated_count}."
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
 * Imports news experts/sources data from a local JSON file (`news-experts.json`).
 * Creates new 'expert_user' roles or updates existing ones.
 * Handles matching by email or personnel ID.
 *
 * @return array|WP_Error An array with import results (created/updated/linked counts) on success,
 * or a WP_Error object on failure.
 */
function import_news_experts()
{
    $json_file_path = get_template_directory() . '/json/news-experts.json';

    if (!file_exists($json_file_path)) {
        return new WP_Error('file_not_found', 'News Experts JSON file not found.');
    }

    // Load and sanitize JSON content.
    $json_data = file_get_contents($json_file_path);
    $json_data = preg_replace('/^\xEF\xBB\xBF/', '', $json_data); // Remove Byte Order Mark (BOM).
    $json_data = mb_convert_encoding($json_data, 'UTF-8', 'UTF-8'); // Normalize encoding to UTF-8.
    $json_data = trim($json_data);

    $records = json_decode($json_data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('json_decode_error', 'News Experts JSON decode error: ' . json_last_error_msg());
    }

    $created = 0;
    $updated = 0;
    $linked = 0; // Count of users linked by personnel ID.

    // Iterate through each record in the JSON data.
    foreach ($records as $person) {
        $email = isset($person['EMAIL']) ? sanitize_email($person['EMAIL']) : null;
        $first_name = sanitize_text_field($person['FIRST_NAME'] ?? '');
        $last_name = sanitize_text_field($person['LAST_NAME'] ?? '');
        $personnel_id = $person['PERSONNEL_ID'] ?? null;

        $user_id = null;

        // Attempt to find or create user by email first.
        if ($email && is_email($email)) {
            $user = get_user_by('email', $email);
            if (!$user) {
                // Create New User if email doesn't exist.
                $user_id = wp_insert_user([
                    'user_login' => sanitize_user($email), // Use email as login for consistency.
                    'user_pass' => wp_generate_password(),
                    'user_email' => $email,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'role' => 'expert_user',
                ]);

                if (is_wp_error($user_id)) {
                    error_log("User creation failed for expert {$email}: " . $user_id->get_error_message());
                    continue; // Skip to next record on error.
                }

                $created++;
            } else {
                // If user exists by email, just get their ID for updating.
                $user_id = $user->ID;
                $updated++;
            }
        }

        // If user not found by email, and personnel_id exists, attempt to link by personnel ID.
        if (!$user_id && $personnel_id) {
            $users = get_users([
                'meta_key' => 'personnel_id',
                'meta_value' => $personnel_id,
                'number' => 1,
                'fields' => 'ID',
            ]);

            if (!empty($users)) {
                $user_id = $users[0];
                $linked++;
            }
        }

        // If still no user_id (meaning no email and no matching personnel_id), create with placeholder email.
        if (!$user_id && !$email && !$personnel_id) {
            $placeholder_email = generate_placeholder_email($first_name, $last_name);
            $user_id = wp_insert_user([
                'user_login' => sanitize_user($placeholder_email),
                'user_pass' => wp_generate_password(),
                'user_email' => $placeholder_email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'role' => 'expert_user',
            ]);

            if (is_wp_error($user_id)) {
                error_log("User creation failed for expert {$first_name} {$last_name} with placeholder: " . $user_id->get_error_message());
                continue; // Skip to next record on error.
            }

            $created++;
        }

        // Update ACF fields if a user was found or created.
        if ($user_id) {
            update_field('phone_number', $person['PHONE'] ?? '', 'user_' . $user_id);
            update_field('description', $person['DESCRIPTION'] ?? '', 'user_' . $user_id);
            // Update personnel_id if provided and not already set (to avoid overwriting API data if that's the source of truth).
            // If JSON is the definitive source for personnel_id for experts, this 'if' condition could be removed.
            if ($personnel_id && empty(get_user_meta($user_id, 'personnel_id', true))) {
                update_field('personnel_id', $personnel_id, 'user_' . $user_id);
            }
            update_field('source_expert_id', $person['ID'], 'user_' . $user_id);
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
 * Imports news writers data from a local JSON file (`news-writers.json`).
 * Creates new 'expert_user' roles or updates existing ones.
 * Handles matching by email or personnel ID.
 *
 * @return array|WP_Error An array with import results (created/updated/linked counts) on success,
 * or a WP_Error object on failure.
 */
function import_news_writers()
{
    $json_file_path = get_template_directory() . '/json/news-writers.json';

    if (!file_exists($json_file_path)) {
        error_log('DEBUG WRITER: News Writers JSON file not found at ' . $json_file_path);
        return new WP_Error('file_not_found', 'News Writers JSON file not found.');
    }

    // Load and sanitize JSON content.
    $json_data = file_get_contents($json_file_path);
    $json_data = preg_replace('/^\xEF\xBB\xBF/', '', $json_data); // Remove Byte Order Mark (BOM).
    $json_data = mb_convert_encoding($json_data, 'UTF-8', 'UTF-8'); // Normalize encoding to UTF-8.
    $json_data = trim($json_data);

    $records = json_decode($json_data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('DEBUG WRITER: JSON decode error: ' . json_last_error_msg());
        return new WP_Error('json_decode_error', 'News Writers JSON decode error: ' . json_last_error_msg());
    }

    $created = 0;
    $updated = 0;
    $linked = 0; // Count of users linked by personnel ID.

    // Iterate through each record in the JSON data.
    foreach ($records as $person) {
        // Specifically log data for Elmer Gray if found, or for all if names are empty.
        $current_name = (isset($person['FIRST_NAME']) ? $person['FIRST_NAME'] : '') . ' ' . (isset($person['LAST_NAME']) ? $person['LAST_NAME'] : '');
        if (strpos($current_name, 'Elmer Gray') !== false || (empty($person['FIRST_NAME']) && empty($person['LAST_NAME']))) {
            error_log('DEBUG WRITER: Processing record: ' . print_r($person, true));
        }

        $email = isset($person['EMAIL']) ? sanitize_email($person['EMAIL']) : null;
        $first_name = sanitize_text_field($person['FIRST_NAME'] ?? '');
        $last_name = sanitize_text_field($person['LAST_NAME'] ?? '');
        $personnel_id = $person['PERSONNEL_ID'] ?? null;
        $writer_id_from_json = $person['ID'] ?? null; // Capture the ID field from JSON.

        $user_id = null;

        // Attempt to find or create user by email first.
        if ($email && is_email($email)) {
            $user = get_user_by('email', $email);
            if (!$user) {
                // Create New User if email doesn't exist.
                $user_id = wp_insert_user([
                    'user_login' => sanitize_user($email), // Use email as login for consistency.
                    'user_pass' => wp_generate_password(),
                    'user_email' => $email,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'role' => 'expert_user', // Changed from 'author' to 'expert_user'
                ]);

                if (is_wp_error($user_id)) {
                    error_log("DEBUG WRITER: User creation failed for writer {$email}: " . $user_id->get_error_message());
                    continue; // Skip to next record on error.
                }

                $created++;
                error_log("DEBUG WRITER: Created new user with ID {$user_id} for {$email} ({$first_name} {$last_name}).");
            } else {
                // If user exists by email, just get their ID for updating.
                $user_id = $user->ID;
                $updated++;
                error_log("DEBUG WRITER: Found existing user by email {$email} with ID {$user_id} for {$first_name} {$last_name}.");
            }
        }

        // If user not found by email, and personnel_id exists, attempt to link by personnel ID.
        if (!$user_id && $personnel_id) {
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

        // If still no user_id (meaning no email and no matching personnel_id), create with placeholder email.
        if (!$user_id && !$email && !$personnel_id) {
            $placeholder_email = generate_placeholder_email($first_name, $last_name);
            $user_id = wp_insert_user([
                'user_login' => sanitize_user($placeholder_email),
                'user_pass' => wp_generate_password(),
                'user_email' => $placeholder_email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'role' => 'expert_user', // Changed from 'author' to 'expert_user'
            ]);

            if (is_wp_error($user_id)) {
                error_log("DEBUG WRITER: User creation failed for writer {$first_name} {$last_name} with placeholder: " . $user_id->get_error_message());
                continue; // Skip to next record on error.
            }

            $created++;
            error_log("DEBUG WRITER: Created new user with ID {$user_id} for {$first_name} {$last_name} using placeholder email: {$placeholder_email}.");
        }

        // Update ACF fields if a user was found or created.
        if ($user_id) {
            error_log("DEBUG WRITER: Attempting to update ACF fields for user ID: {$user_id} ({$first_name} {$last_name}).");
            update_field('phone_number', $person['PHONE'] ?? '', 'user_' . $user_id);
            update_field('tagline', $person['TAGLINE'] ?? '', 'user_' . $user_id);
            // Update personnel_id if provided and not already set.
            if ($personnel_id && empty(get_user_meta($user_id, 'personnel_id', true))) {
                update_field('personnel_id', $personnel_id, 'user_' . $user_id);
                error_log("DEBUG WRITER: Updated personnel_id to {$personnel_id} for user {$user_id}.");
            }

            // --- Specific Debugging for writer_id ---
            error_log("DEBUG WRITER: Trying to set writer_id for user {$user_id} to value '{$writer_id_from_json}'.");
            $update_result = update_field('writer_id', $writer_id_from_json, 'user_' . $user_id);

            if ($update_result === false) {
                error_log("DEBUG WRITER: Failed to update 'writer_id' for user {$user_id}. This could mean the field doesn't exist or is not configured correctly for users.");
            } else {
                error_log("DEBUG WRITER: Successfully updated 'writer_id' to '{$writer_id_from_json}' for user {$user_id}.");
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
