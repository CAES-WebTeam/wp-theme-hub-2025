<?php

// Load ACF Field Groups
include_once( get_template_directory() . '/inc/acf-fields/events-field-group.php' );

// Hook into the save_post action to update the series taxonomy when the ACF field is updated
function update_series_taxonomy_from_acf( $post_id ) {
	// Check if this is an autosave or a revision, and bail if so
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// Check if the current user can edit the post
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Get the ACF series field
	$acf_series_terms = get_field( 'series', $post_id );

	// If the ACF series field is empty, empty out the array
	if ( ! is_array( $acf_series_terms ) ) {
		$acf_series_terms = array();
	}

	// Update the post's series taxonomy with the selected term IDs
	wp_set_post_terms( $post_id, $acf_series_terms, 'series' );
}
add_action( 'save_post', 'update_series_taxonomy_from_acf', 20 );


// Function to remove the series taxonomy meta box from the events post type
function remove_series_taxonomy_metabox() {

	remove_meta_box( 'tagsdiv-series', 'events', 'side' );
}
add_action( 'admin_menu', 'remove_series_taxonomy_metabox' );

// Set ACF field 'location_caes_room' with options from json
function populate_acf_location_caes_room_field( $field ) {
	// Set path to json file
	$json_file = get_template_directory() . '/json/caes-locations.json';

	if ( file_exists( $json_file ) ) {
		// Get the contents of the json file
		$json_data = file_get_contents( $json_file );
		$locations = json_decode( $json_data, true );

		// Clear existing choices
		$field['choices'] = array();

		// Check if there are locations in the json
		if ( isset( $locations['locations'] ) && is_array( $locations['locations'] ) ) {
			// Loop through the locations and add each name as a select option
			foreach ( $locations['locations'] as $location ) {
				if ( isset( $location['name'] ) ) {
					$field['choices'][ sanitize_text_field( $location['name'] ) ] = sanitize_text_field( $location['name'] );
				}
			}
		}
	}

	// Return the field to ACF
	return $field;
}
add_filter( 'acf/load_field/name=location_caes_room', 'populate_acf_location_caes_room_field' );

// Set ACF field 'location_county_office' with options from json
function populate_acf_location_county_office_field( $field ) {
	// Set path to json file
	$json_file = get_template_directory() . '/json/extension-offices.json';

	if ( file_exists( $json_file ) ) {
		// Get the contents of the json file
		$json_data = file_get_contents( $json_file );
		$locations = json_decode( $json_data, true );

		// Clear existing choices
		$field['choices'] = array();

		// Check if there are locations in the json
		if ( isset( $locations['locations'] ) && is_array( $locations['locations'] ) ) {
			// Loop through the locations and add each name as a select option
			foreach ( $locations['locations'] as $location ) {
				if ( isset( $location['name'] ) ) {
					$field['choices'][ sanitize_text_field( $location['name'] ) ] = sanitize_text_field( $location['name'] );
				}
			}
		}
	}

	// Return the field to ACF
	return $field;
}
add_filter( 'acf/load_field/name=location_county_office', 'populate_acf_location_county_office_field' );

// Hook into the ACF form submission to set the post title and send the email notification
function set_acf_post_title_and_notify( $post_id ) {
	// Check if this is a new post and it's of the correct post type
	if ( get_post_type($post_id) == 'events' && get_post_status($post_id) == 'draft' ) {

		// Get ACF "title" field
		$acf_title = get_field('title', $post_id);

		// Log if title is empty
		if ( empty($acf_title) ) {
			error_log("Title field is empty for post ID: $post_id");
		} else {
			// Update the post title with the ACF field value
			$post_data = array(
				'ID'         => $post_id,
				'post_title' => sanitize_text_field( $acf_title ),
			);
			wp_update_post( $post_data );
		}

		// Send an email notification to the user selected in the ACF "approving_admin" field
		$admin_user = get_field('approving_admin', $post_id);

		if ( $admin_user && isset($admin_user['user_email']) && !empty($admin_user['user_email']) ) {
			$admin_user_email = $admin_user['user_email'];

			// Set the email content
			$subject = 'New Event Submitted: ' . $acf_title;
			$message = 'A new event has been submitted and is awaiting approval. You can view it here: ' . get_edit_post_link($post_id);
			$headers = array('Content-Type: text/html; charset=UTF-8');

			// Send the email notification
			wp_mail( $admin_user_email, $subject, $message, $headers );
		} else {
			error_log("No valid approving admin email for post ID: $post_id");
		}
	}
}
add_action('acf/save_post', 'set_acf_post_title_and_notify', 20);


// Hook into 'event's post publish and send out emails
function send_events_publish_emails( $post_id ) {
	// Check if 'events' post has been published
	if ( get_post_type($post_id) == 'events' && get_post_status($post_id) == 'publish' ) {

		/*------------------------------------*\
			Send Submitter Approval Email
		\*------------------------------------*/

		// Get the contact type
		$contact_type = get_field('contact_type', $post_id);

		// Initialize the email recipient variable
		$recipient_email = '';

		// Check the contact type
		if ($contact_type === 'default') {
			// Get the ACF user field (assuming it's a user object)
			$acf_user = get_field('contact', $post_id);
			if ($acf_user && isset($acf_user['user_email'])) {
				$recipient_email = $acf_user['user_email'];
			}
		} else {
			// Get the custom contact email from the ACF group
			$custom_contact_email = get_field('custom_contact', $post_id);
			if ($custom_contact_email && isset($custom_contact_email['contact_email'])) {
				$recipient_email = $custom_contact_email['contact_email'];
			}
		}

		// Now you can use $recipient_email to send the email
		if (!empty($recipient_email)) {
			$subject = 'Your Event has been approved.';
			$message = 'Thank you for submitting your event, it has been approved by system admins.';
			$headers = array('Content-Type: text/html; charset=UTF-8');

			if (wp_mail($recipient_email, $subject, $message, $headers)) {
				error_log('Email sent successfully to: ' . $recipient_email);
			} else {
				error_log('Failed to send email to: ' . $recipient_email);
			}
		} else {
			error_log('No valid email address to send to.');
		}


		/*------------------------------------*\
			Send Destiny One Email
		\*------------------------------------*/

		$activate = get_field('activate', $post_id);

		// Check if the "activate" field is checked
		if ( $activate ) {

			// Set up array
			$fields_to_email = array();

			// Assign all fields
			$fields_to_email['title'] = get_field('title', $post_id);
			$fields_to_email['registration_start_date'] = get_field('registration_start_date', $post_id);
			$fields_to_email['registration_end_date'] = get_field('registration_end_date', $post_id);
			$fields_to_email['max_participants'] = get_field('max_participants', $post_id);
			$fields_to_email['min_participants'] = get_field('min_participants', $post_id);
			$fields_to_email['max_waitlist'] = get_field('max_waitlist', $post_id);

			$allow_group_enrollment = get_field('allow_group_enrollment', $post_id);
			$fields_to_email['allow_group_enrollment'] = ($allow_group_enrollment == 1) ? 'yes' : 'no';

			$fields_to_email['deposit_type'] = get_field('deposit_type', $post_id);
			$fields_to_email['deposit_amount'] = get_field('deposit_amount', $post_id);
			$fields_to_email['balance_payment_date'] = get_field('balance_payment_date', $post_id);

			// Check if deposit type is 'full'
			if ($fields_to_email['deposit_type'] === 'full') {
				$fields_to_email['deposit_amount'] = get_field('cost', $post_id);
				$fields_to_email['balance_payment_date'] = null;
			}

			$fields_to_email['additional_info'] = get_field('additional_info', $post_id);

			// Create the email message body
			$message = "New Event:\n\n";

			// Loop through array of fields
			foreach ( $fields_to_email as $field_label => $field_value ) {
				$message .= ucfirst(str_replace('_', ' ', $field_label)) . ": " . $field_value . "\n";
			}

			// Prepare email details
			$to = 'ccampbell@frankelagency.com';
			$subject = 'New Event Published: ' . get_the_title($post_id);
			$headers = array('Content-Type: text/plain; charset=UTF-8');

			// Send the email
			wp_mail( $to, $subject, $message, $headers );

			// Update the 'activate' field back to 0
			update_field('activate', 0, $post_id);
		}
	}
}
add_action( 'acf/save_post', 'send_events_publish_emails', 20 );

// Restrict Users to only see events they've created
function restrict_events_to_author($query) {
	if ( is_admin() && $query->is_main_query() && $query->get('post_type') === 'events' ) {
		if ( !current_user_can('administrator') ) {
			$query->set('author', get_current_user_id());
		}
	}
}
add_action('pre_get_posts', 'restrict_events_to_author');