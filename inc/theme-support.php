<?php

// Editor Styles
function caes_hub_editor_styles() {
	add_editor_style('./assets/css/editor.css');
}
add_action('after_setup_theme', 'caes_hub_editor_styles');


// Enqueue style sheet and JavaScript
function caes_hub_styles() {
	wp_enqueue_style(
		'caes-hub-styles',
		get_theme_file_uri('assets/css/main.css'),
		[],
		wp_get_theme()->get('Version')
	);
	wp_enqueue_script('caes-hub-script', get_template_directory_uri() . '/assets/js/main.js', array(), '1.0.0', true);
}
add_action('wp_enqueue_scripts', 'caes_hub_styles');


// Adds custom style choices to core blocks with add-block-styles.js
function add_block_style() {
	wp_enqueue_script(
		'add-block-style',
		get_theme_file_uri() . '/assets/js/add-block-styles.js',
		array('wp-blocks', 'wp-dom-ready', 'wp-edit-post')
	);
}
add_action('enqueue_block_editor_assets', 'add_block_style');


// Remove Default Block Patterns
function remove_default_block_patterns() {
	remove_theme_support('core-block-patterns');
}
add_action('after_setup_theme', 'remove_default_block_patterns');


// Unregister API Patterns
add_filter( 'should_load_remote_block_patterns', '__return_false' );


// Create custom user roles
function add_custom_user_roles() {
	// Add the CAES role
	add_role(
		'caes-staff', // Role slug
		'CAES Staff', // Display name
		array(
			'read'         => true,  // Allows reading posts
			'edit_posts'   => false, // Disallows editing posts
			'delete_posts' => false, // Disallows deleting posts
		)
	);

	// Add the Extension role
	add_role(
		'extension-staff', // Role slug
		'Extension Staff', // Display name
		array(
			'read'         => true,  // Allows reading posts
			'edit_posts'   => false, // Disallows editing posts
			'delete_posts' => false, // Disallows deleting posts
		)
	);
}
//add_action('init', 'add_custom_user_roles');


// Add phone number field to Contact Info section in user profile
function add_phone_to_contact_info( $user ) {
    ?>
    <h3><?php _e( 'Extra Info', 'textdomain' ); ?></h3>

    <table class="form-table" role="presentation">
        <!-- Phone Number Field -->
        <tr>
            <th><label for="phone"><?php _e( 'Phone Number', 'textdomain' ); ?></label></th>
            <td>
                <input type="text" name="phone" id="phone" value="<?php echo esc_attr( get_user_meta( $user->ID, 'phone', true ) ); ?>" class="regular-text" /><br>
                <span class="description"><?php _e( 'Please enter the user\'s phone number.', 'textdomain' ); ?></span>
            </td>
        </tr>
    </table>
    <?php
}
add_action( 'show_user_profile', 'add_phone_to_contact_info' );
add_action( 'edit_user_profile', 'add_phone_to_contact_info' );

// Save phone number field value when profile is updated
function save_user_phone_field( $user_id ) {
    // Check permission and save phone number
    if ( current_user_can( 'edit_user', $user_id ) && isset( $_POST['phone'] ) ) {
        update_user_meta( $user_id, 'phone', sanitize_text_field( $_POST['phone'] ) );
    }
}
add_action( 'personal_options_update', 'save_user_phone_field' );
add_action( 'edit_user_profile_update', 'save_user_phone_field' );


// Function to retrieve the user's IP address
if (!function_exists('getUserIP')) {
	function getUserIP() {
		// Check for shared internet/proxy servers
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			return $_SERVER['HTTP_CLIENT_IP'];
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			// To handle multiple IPs passed by proxies
			$ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
			return trim($ipList[0]);
		} else {
			return $_SERVER['REMOTE_ADDR'];
		}
	}
}

// Define a constant for the user's IP address
if (!defined('USER_IP')) {
	//define('USER_IP', getUserIP());
	define('USER_IP', '174.109.38.141');
}

// Set IPStack API key
if (!defined('IPSTACK_API_KEY')) {
	define('IPSTACK_API_KEY', '338d99bff58c62f955abeb40826ee660');
}

// Function to get user location
if (!function_exists('getUserLocation')) {
	function getUserLocation() {
		$url = "http://api.ipstack.com/" . USER_IP . "?access_key=" . IPSTACK_API_KEY;

		// Make the API request
		$response = wp_remote_get($url); // Use WordPress HTTP API
		if (is_wp_error($response)) {
			return null;
		}

		$data = json_decode(wp_remote_retrieve_body($response), true);

		if (isset($data['latitude']) && isset($data['longitude'])) {
			return [
				'latitude' => $data['latitude'],
				'longitude' => $data['longitude']
			];
		}

		return null;
	}
}

// Function to calculate the distance
if (!function_exists('calculateDistance')) {
	function calculateDistance($lat1, $lon1, $lat2, $lon2) {
		// Convert degrees to radians
		$lat1 = deg2rad($lat1);
		$lon1 = deg2rad($lon1);
		$lat2 = deg2rad($lat2);
		$lon2 = deg2rad($lon2);

		// Haversine formula
		$earthRadius = 3958.8; // Radius of Earth in miles
		$deltaLat = $lat2 - $lat1;
		$deltaLon = $lon2 - $lon1;

		$a = sin($deltaLat / 2) * sin($deltaLat / 2) +
			 cos($lat1) * cos($lat2) *
			 sin($deltaLon / 2) * sin($deltaLon / 2);
		$c = 2 * atan2(sqrt($a), sqrt(1 - $a));

		return $earthRadius * $c; // Distance in miles
	}
}

// Function to check if within radius
if (!function_exists('isWithinRadius')) {
	function isWithinRadius($userLat, $userLon, $targetLat, $targetLon, $radius) {
		$distance = calculateDistance($userLat, $userLon, $targetLat, $targetLon);
		return $distance <= $radius;
	}
}
