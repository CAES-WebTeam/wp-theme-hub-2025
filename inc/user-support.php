<?php

// Load ACF Field Groups
include_once( get_template_directory() . '/inc/acf-fields/user-field-group.php' );

// Create Custom User Role
function add_personnel_user_role() {
	if (!get_role('personnel_user')) {
		add_role('personnel_user', 'Personnel User', [
			'read' => true,
			'edit_posts' => false,
			'delete_posts' => false,
		]);
	}
}
add_action('init', 'add_personnel_user_role');


/*function delete_all_personnel_users() {
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
add_action('admin_menu', 'add_delete_personnel_users_menu');*/


// Slit name into mulitple variables
function split_full_name($full_name) {
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


// Add or Update Users Data from Personnel REST data
function sync_personnel_users() {
	$api_url = 'https://secure.caes.uga.edu/rest/personnel/Personnel';

	// Fetch API Data
	$response = wp_remote_get($api_url);
	if (is_wp_error($response)) {
		error_log('API Request Failed: ' . $response->get_error_message());
		return;
	}

	$data = wp_remote_retrieve_body($response);
	$users = json_decode($data, true);

	if (!is_array($users)) {
		error_log('Invalid API response.');
		return;
	}

	// Fetch existing personnel users
	$existing_users = get_users([
		'role' => 'personnel_user',
		'meta_key' => 'personnel_id',
		'fields' => ['ID', 'user_login']
	]);

	$existing_user_ids = [];
	foreach ($existing_users as $user) {
		$existing_user_ids[get_user_meta($user->ID, 'personnel_id', true)] = $user->ID;
	}

	$api_user_ids = [];

	foreach ($users as $user) {
		if ($user['IS_ACTIVE'] != 1) {
			continue; // Skip inactive users
		}

		$personnel_id = intval($user['PERSONNEL_ID']);
		$email = sanitize_email($user['EMAIL']);
		$username = sanitize_user(strtolower(str_replace(' ', '', $user['NAME'])));
		$name_parts = split_full_name($user['NAME']);
		$first_name = sanitize_text_field($name_parts['first_name']);
		$last_name = sanitize_text_field($name_parts['last_name']);
		$title = sanitize_text_field($user['TITLE']);
		$department = sanitize_text_field($user['DEPARTMENT']);
		$phone = sanitize_text_field($user['PHONE_NUMBER']);
		$cell_phone = sanitize_text_field($user['CELL_PHONE_NUMBER']);
		$fax = sanitize_text_field($user['FAX_NUMBER']);
		$caes_location_id = intval($user['CAES_LOCATION_ID']);
		$image_name = sanitize_text_field($user['IMAGE']);

		$api_user_ids[] = $personnel_id;

		if (!isset($existing_user_ids[$personnel_id])) {
			// Create New User
			$user_id = wp_insert_user([
				'user_login' => $username,
				'user_email' => $email,
				'first_name' => $first_name,
				'last_name' => $last_name,
				'user_pass' => wp_generate_password(),
				'role' => 'personnel_user'
			]);

			if (!is_wp_error($user_id)) {
				update_field('personnel_id', $personnel_id, 'user_' . $user_id);
				update_field('title', $title, 'user_' . $user_id);
				update_field('phone_number', $phone, 'user_' . $user_id);
				update_field('cell_phone_number', $cell_phone, 'user_' . $user_id);
				update_field('fax_number', $fax, 'user_' . $user_id);
				update_field('department', $department, 'user_' . $user_id);
				update_field('caes_location_id', $caes_location_id, 'user_' . $user_id);
				update_field('image_name', $image_name, 'user_' . $user_id);
			}
		} else {
			// Update Existing User
			$user_id = $existing_user_ids[$personnel_id];
			wp_update_user([
				'ID' => $user_id,
				'user_email' => $email,
				'first_name' => $first_name,
				'last_name' => $last_name,
			]);

			update_field('title', $title, 'user_' . $user_id);
			update_field('phone_number', $phone, 'user_' . $user_id);
			update_field('cell_phone_number', $cell_phone, 'user_' . $user_id);
			update_field('fax_number', $fax, 'user_' . $user_id);
			update_field('department', $department, 'user_' . $user_id);
			update_field('caes_location_id', $caes_location_id, 'user_' . $user_id);
			update_field('image_name', $image_name, 'user_' . $user_id);
		}
	}

	// Remove users no longer in API
	foreach ($existing_user_ids as $existing_personnel_id => $user_id) {
		if (!in_array($existing_personnel_id, $api_user_ids)) {
			wp_delete_user($user_id);
		}
	}
}


// Setup daily CRON
if (!wp_next_scheduled('daily_personnel_sync')) {
	wp_schedule_event(time(), 'daily', 'daily_personnel_sync');
}
add_action('daily_personnel_sync', 'sync_personnel_users');


// Add Manual Sync Button to Tools
function add_personnel_sync_menu() {
	add_submenu_page('tools.php', 'Sync Personnel Users', 'Sync Personnel', 'manage_options', 'sync-personnel', function() {
		sync_personnel_users();
		echo '<div class="updated"><p>Personnel users synced successfully!</p></div>';
	});
}
add_action('admin_menu', 'add_personnel_sync_menu');
