<?php
// Load ACF Field Groups
//include_once( get_template_directory() . '/inc/acf-fields/events-field-group.php' );

// Hook into the save_post action to update the series taxonomy when the ACF field is updated
function update_series_taxonomy_from_acf($post_id)
{
	// Check if this is an autosave or a revision, and bail if so
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}

	// Check if the current user can edit the post
	if (! current_user_can('edit_post', $post_id)) {
		return;
	}

	// Get the ACF series field
	$acf_series_terms = get_field('event_series', $post_id);

	// If the ACF series field is empty, empty out the array
	if (! is_array($acf_series_terms)) {
		$acf_series_terms = array();
	}

	// Update the post's series taxonomy with the selected term IDs
	wp_set_post_terms($post_id, $acf_series_terms, 'event_series');
}
add_action('save_post', 'update_series_taxonomy_from_acf', 20);

/**
 * Hook into the save_post action to update the event_caes_departments taxonomy
 * when the ACF field is updated.
 */
function update_departments_taxonomy_from_acf($post_id)
{
    // Check if this is an autosave or a revision, and bail if so
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check if the current user can edit the post
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Get the ACF departments field
    $acf_department_terms = get_field('caes_department', $post_id);

    // If the ACF departments field is empty, empty out the array
    if (!is_array($acf_department_terms)) {
        $acf_department_terms = array();
    }

    // Update the post's event_caes_departments taxonomy with the selected term IDs
    wp_set_post_terms($post_id, $acf_department_terms, 'event_caes_departments');
}
add_action('save_post', 'update_departments_taxonomy_from_acf', 20);

// Function to remove the series taxonomy meta box and featured image from the events post type
function remove_events_metaboxes()
{
	// Remove series taxonomy metabox
	remove_meta_box('tagsdiv-series', 'events', 'side');
	
	// Remove featured image metabox - try both contexts
	remove_meta_box('postimagediv', 'events', 'side');
	remove_meta_box('postimagediv', 'events', 'normal');
}

// Use different hooks for better compatibility
add_action('admin_menu', 'remove_events_metaboxes');
add_action('do_meta_boxes', 'remove_events_metaboxes');


// Set ACF field 'location_caes_room' with options from json
function populate_acf_location_caes_room_field($field)
{
	// Set path to json file
	$json_file = get_template_directory() . '/json/caes-locations.json';

	if (file_exists($json_file)) {
		// Get the contents of the json file
		$json_data = file_get_contents($json_file);
		$locations = json_decode($json_data, true);

		// Clear existing choices
		$field['choices'] = array();

		// Check if there are locations in the json
		if (isset($locations['locations']) && is_array($locations['locations'])) {
			// Loop through the locations and add each name as a select option
			foreach ($locations['locations'] as $location) {
				if (isset($location['name'])) {
					$field['choices'][sanitize_text_field($location['name'])] = sanitize_text_field($location['name']);
				}
			}
		}
	}

	// Return the field to ACF
	return $field;
}
add_filter('acf/load_field/name=location_caes_room', 'populate_acf_location_caes_room_field');

// Set ACF field 'location_county_office' with options from json
function populate_acf_location_county_office_field($field)
{
	// Set path to json file
	$json_file = get_template_directory() . '/json/extension-offices.json';

	if (file_exists($json_file)) {
		// Get the contents of the json file
		$json_data = file_get_contents($json_file);
		$locations = json_decode($json_data, true);

		// Clear existing choices
		$field['choices'] = array();

		// Check if there are locations in the json
		if (isset($locations['locations']) && is_array($locations['locations'])) {
			// Loop through the locations and add each name as a select option
			foreach ($locations['locations'] as $location) {
				if (isset($location['name'])) {
					$field['choices'][sanitize_text_field($location['name'])] = sanitize_text_field($location['name']);
				}
			}
		}
	}

	// Return the field to ACF
	return $field;
}
add_filter('acf/load_field/name=location_county_office', 'populate_acf_location_county_office_field');

// Hook into the ACF form submission to set the post title and send the email notification
function set_acf_post_title_and_notify($post_id)
{
	// Check if this is a new post and it's of the correct post type
	if (get_post_type($post_id) == 'events' && get_post_status($post_id) == 'draft') {

		// Get ACF "title" field
		$acf_title = get_field('title', $post_id);

		// Log if title is empty
		if (empty($acf_title)) {
			error_log("Title field is empty for post ID: $post_id");
		} else {
			// Update the post title with the ACF field value
			$post_data = array(
				'ID'         => $post_id,
				'post_title' => sanitize_text_field($acf_title),
			);
			wp_update_post($post_data);
		}

		// Send an email notification to the user selected in the ACF "approving_admin" field
		$admin_user = get_field('approving_admin', $post_id);

		if ($admin_user && isset($admin_user['user_email']) && !empty($admin_user['user_email'])) {
			$admin_user_email = $admin_user['user_email'];

			// Set the email content
			$subject = 'New Event Submitted: ' . $acf_title;
			$message = 'A new event has been submitted and is awaiting approval. You can view it here: ' . get_edit_post_link($post_id);
			$headers = array('Content-Type: text/html; charset=UTF-8');

			// Send the email notification
			wp_mail($admin_user_email, $subject, $message, $headers);
		} else {
			error_log("No valid approving admin email for post ID: $post_id");
		}
	}
}
add_action('acf/save_post', 'set_acf_post_title_and_notify', 20);


// Hook into 'event's post publish and send out emails
function send_events_publish_emails($post_id)
{
	// Check if 'events' post has been published
	if (get_post_type($post_id) == 'events' && get_post_status($post_id) == 'publish') {

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
		if ($activate) {

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
			foreach ($fields_to_email as $field_label => $field_value) {
				$message .= ucfirst(str_replace('_', ' ', $field_label)) . ": " . $field_value . "\n";
			}

			// Prepare email details
			$to = 'ccampbell@frankelagency.com';
			$subject = 'New Event Published: ' . get_the_title($post_id);
			$headers = array('Content-Type: text/plain; charset=UTF-8');

			// Send the email
			wp_mail($to, $subject, $message, $headers);

			// Update the 'activate' field back to 0
			update_field('activate', 0, $post_id);
		}
	}
}
add_action('acf/save_post', 'send_events_publish_emails', 20);

// Restrict Users to only see events they've created OR events they can approve
function restrict_events_to_author($query)
{
	if (is_admin() && $query->is_main_query() && $query->get('post_type') === 'events') {
		$current_user_id = get_current_user_id();
		$current_user = wp_get_current_user();
		$user_roles = (array) $current_user->roles;
		
		// Admins and editors can see all events
		if (current_user_can('administrator') || current_user_can('editor')) {
			return;
		}
		
		// For event approvers, show their own events plus events for their assigned calendars
		if (in_array('event_approver', $user_roles)) {
			// Get calendars assigned to this user
			$all_calendars = get_terms(array(
				'taxonomy' => 'event_caes_departments',
				'hide_empty' => false,
			));
			
			$assigned_calendar_ids = array();
			
			if (!is_wp_error($all_calendars) && !empty($all_calendars)) {
				foreach ($all_calendars as $calendar) {
					$assigned_approver = get_field('calendar_approver', 'event_caes_departments_' . $calendar->term_id);
					
					if ($assigned_approver && (int) $assigned_approver === (int) $current_user_id) {
						$assigned_calendar_ids[] = $calendar->term_id;
					}
				}
			}
			
			$visible_post_ids = array();
			
			// Get authored events
			$authored_events = get_posts(array(
				'post_type' => 'events',
				'author' => $current_user_id,
				'posts_per_page' => -1,
				'fields' => 'ids',
				'post_status' => array('publish', 'pending', 'draft')
			));
			
			if (!empty($authored_events)) {
				$visible_post_ids = array_merge($visible_post_ids, $authored_events);
			}
			
			// Get events for assigned calendars
			if (!empty($assigned_calendar_ids)) {
				$assigned_calendar_events = get_posts(array(
					'post_type' => 'events',
					'post_status' => array('pending', 'publish'),
					'posts_per_page' => -1,
					'fields' => 'ids',
					'tax_query' => array(
						array(
							'taxonomy' => 'event_caes_departments',
							'field' => 'term_id',
							'terms' => $assigned_calendar_ids,
							'operator' => 'IN'
						)
					)
				));
				
				if (!empty($assigned_calendar_events)) {
					$visible_post_ids = array_merge($visible_post_ids, $assigned_calendar_events);
				}
			}
			
			$visible_post_ids = array_unique($visible_post_ids);
			
			if (!empty($visible_post_ids)) {
				$query->set('post__in', $visible_post_ids);
			} else {
				$query->set('post__in', array(0));
			}
			
			$query->set('author', '');
		} else {
			// For other users (event submitters), only show their own events
			$query->set('author', $current_user_id);
		}
	}
}
add_action('pre_get_posts', 'restrict_events_to_author');

// Set Google Maps API key
function my_acf_google_map_api($api)
{
	$api['key'] = 'AIzaSyBsBpQlrkrD9seg3_4FSMhhZsUU2rGjnm8';
	return $api;
}
add_filter('acf/fields/google_map/api', 'my_acf_google_map_api');

/**
 * Hide specific ACF fields and a tab from non-admin users.
 */
function hide_specific_acf_fields_from_non_admins($field)
{

	// Get the current user object
	$current_user = wp_get_current_user();
	$user_capabilities = $current_user->allcaps; // Get all capabilities

	// Only apply this logic if the current user is NOT an administrator.
	// 'manage_options' is a common capability for administrators.
	if (! current_user_can('manage_options')) {

		// Define the labels and names of the fields you want to hide.
		// For 'tab' fields, use their exact 'Field Label'.
		// For other field types, use their 'Name' (the slug).
		$fields_to_hide = array(
			// Tab field (use its Label)
			'Legacy Fields', // The exact label of your tab group

			// Other fields (use their 'Name' / slug)
			'event_id',
			'button1_text',
			'button1_link',
			'button2_text',
			'button2_link',
			'button3_text',
			'button3_link',
			'button4_text',
			'button4_link',
			'file1',
			'file1_name',
			'contact_personnel_id',
			'submitted_by',
			'image',
			'image_caption',
		);

		// Check if the current field being processed should be hidden.
		// For 'tab' fields, we check the 'label'.
		// For other field types, we check the 'name'.
		if (($field['type'] == 'tab' && in_array($field['label'], $fields_to_hide)) ||
			($field['type'] != 'tab' && in_array($field['name'], $fields_to_hide))
		) {

			// Return false to completely hide the field from the admin UI.
			// This hook is designed to control field rendering directly.
			return false;
		}
	}

	return $field;
}
add_filter('acf/prepare_field', 'hide_specific_acf_fields_from_non_admins');

/**
 * Event Expiry Functions with Custom Post Status
 */

/**
 * Register custom "expired" post status for events
 */
function register_expired_post_status() {
    register_post_status('expired', array(
        'label'                     => _x('Expired', 'post status', 'caes-hub'),
        'public'                    => false,
        'exclude_from_search'       => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop(
            'Expired <span class="count">(%s)</span>',
            'Expired <span class="count">(%s)</span>',
            'caes-hub'
        ),
    ));
}
add_action('init', 'register_expired_post_status');

/**
 * Add "Expired" to the post status dropdown in admin
 */
function add_expired_to_post_status_dropdown() {
    global $post;
    
    if (get_post_type($post) === 'events') {
        echo '<script>
        jQuery(document).ready(function($) {
            $("select#post_status").append("<option value=\"expired\" ' . selected(get_post_status($post->ID), 'expired', false) . '>Expired</option>");
            
            if ($("#post_status").val() == "expired") {
                $("#post-status-display").text("Expired");
            }
            
            $("#post-status-select").on("change", "#post_status", function() {
                if ($(this).val() == "expired") {
                    $("#post-status-display").text("Expired");
                } else if ($(this).val() == "publish") {
                    $("#post-status-display").text("Published");
                } else if ($(this).val() == "draft") {
                    $("#post-status-display").text("Draft");
                }
            });
        });
        </script>';
    }
}
add_action('admin_footer-post.php', 'add_expired_to_post_status_dropdown');

/**
 * Helper function to check if an event has expired
 * Returns true if the event has passed (day after the last event date)
 */
function is_event_expired($post_id) {
    $date_type = get_field('event_date_type', $post_id);
    $last_event_date = null;
    
    if ($date_type === 'single') {
        // For single events, check end_date first, then start_date
        $end_date = get_field('end_date', $post_id);
        $start_date = get_field('start_date', $post_id);
        
        if (!empty($end_date)) {
            $last_event_date = $end_date;
        } elseif (!empty($start_date)) {
            $last_event_date = $start_date;
        }
        
    } elseif ($date_type === 'multi') {
        // For multi events, find the latest date
        $multi_dates = get_field('date_and_time', $post_id);
        
        if (!empty($multi_dates) && is_array($multi_dates)) {
            $latest_date = null;
            
            foreach ($multi_dates as $date_entry) {
                if (!empty($date_entry['start_date_copy'])) {
                    if ($latest_date === null || $date_entry['start_date_copy'] > $latest_date) {
                        $latest_date = $date_entry['start_date_copy'];
                    }
                }
            }
            
            $last_event_date = $latest_date;
        }
    }
    
    // If no date found, don't expire
    if (empty($last_event_date)) {
        return false;
    }
    
    // Convert ACF date format (Ymd) to DateTime and add 1 day
    $date_object = DateTime::createFromFormat('Ymd', $last_event_date);
    if (!$date_object) {
        return false;
    }
    
    // Add one day to get expiry date
    $date_object->add(new DateInterval('P1D'));
    $expiry_date = $date_object->format('Y-m-d');
    
    // Compare with today's date
    $today = date('Y-m-d');
    
    return $today >= $expiry_date;
}

/**
 * Exclude expired events from all frontend queries
 * This is now much simpler since expired events have a different post status
 */
function exclude_expired_events_from_queries($query) {
    // Only run on frontend, but skip if it's a preview
    if (is_admin() || !$query->is_main_query() || is_preview()) {
        return;
    }
    
    // Check if this query includes events
    $post_type = $query->get('post_type');
    
    if ($post_type === 'events' || 
        (is_array($post_type) && in_array('events', $post_type))) {
        
        // Ensure we only get published events (excludes expired automatically)
        $post_status = $query->get('post_status');
        if (empty($post_status)) {
            $query->set('post_status', 'publish');
        }
    }
}
add_action('pre_get_posts', 'exclude_expired_events_from_queries');

/**
 * Make expired events return 404 on single event pages
 * This handles edge cases where cron hasn't run yet
 */
function redirect_expired_events_to_404() {
    if (is_singular('events')) {
        $post_id = get_queried_object_id();
        $post_status = get_post_status($post_id);
        
        // If already marked as expired, 404
        if ($post_status === 'expired') {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return;
        }
        
        // If still published but actually expired, 404 and mark for cron update
        if ($post_status === 'publish' && is_event_expired($post_id)) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return;
        }
    }
}
add_action('template_redirect', 'redirect_expired_events_to_404');

/**
 * Daily cron job to move expired events to "expired" status
 */
function schedule_expire_events_cron() {
    if (!wp_next_scheduled('expire_old_events')) {
        wp_schedule_event(time(), 'daily', 'expire_old_events');
    }
}
add_action('init', 'schedule_expire_events_cron');

/**
 * Function that runs daily to expire old events
 */
function expire_old_events() {
    // Get all published events
    $events = get_posts(array(
        'post_type' => 'events',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'fields' => 'ids'
    ));
    
    $expired_count = 0;
    
    foreach ($events as $event_id) {
        if (is_event_expired($event_id)) {
            // Change status to expired
            wp_update_post(array(
                'ID' => $event_id,
                'post_status' => 'expired'
            ));
            $expired_count++;
        }
    }
    
    // Log the results
    if ($expired_count > 0) {
        error_log("Expired {$expired_count} events on " . date('Y-m-d H:i:s'));
    }
}
add_action('expire_old_events', 'expire_old_events');

/**
 * Add bulk action to manually expire events
 */
function add_expire_events_bulk_action($bulk_actions) {
    $bulk_actions['expire_events'] = __('Mark as Expired', 'caes-hub');
    return $bulk_actions;
}
add_filter('bulk_actions-edit-events', 'add_expire_events_bulk_action');

/**
 * Handle the bulk expire action
 */
function handle_expire_events_bulk_action($redirect_to, $doaction, $post_ids) {
    if ($doaction !== 'expire_events') {
        return $redirect_to;
    }

    foreach ($post_ids as $post_id) {
        wp_update_post(array(
            'ID' => $post_id,
            'post_status' => 'expired'
        ));
    }

    $redirect_to = add_query_arg('bulk_expired_events', count($post_ids), $redirect_to);
    return $redirect_to;
}
add_filter('handle_bulk_actions-edit-events', 'handle_expire_events_bulk_action', 10, 3);

/**
 * Show admin notice for bulk expire action
 */
function expired_events_bulk_action_admin_notice() {
    if (!empty($_REQUEST['bulk_expired_events'])) {
        $count = intval($_REQUEST['bulk_expired_events']);
        printf('<div id="message" class="updated fade"><p>' .
            _n('Marked %s event as expired.',
                'Marked %s events as expired.',
                $count,
                'caes-hub'
            ) . '</p></div>', $count);
    }
}
add_action('admin_notices', 'expired_events_bulk_action_admin_notice');

/**
 * Add bulk action to restore expired events to published
 */
function add_restore_events_bulk_action($bulk_actions) {
    global $typenow;
    if ($typenow === 'events') {
        $bulk_actions['restore_events'] = __('Restore to Published', 'caes-hub');
    }
    return $bulk_actions;
}
add_filter('bulk_actions-edit-events', 'add_restore_events_bulk_action');

/**
 * Handle the bulk restore action
 */
function handle_restore_events_bulk_action($redirect_to, $doaction, $post_ids) {
    if ($doaction !== 'restore_events') {
        return $redirect_to;
    }

    foreach ($post_ids as $post_id) {
        wp_update_post(array(
            'ID' => $post_id,
            'post_status' => 'publish'
        ));
    }

    $redirect_to = add_query_arg('bulk_restored_events', count($post_ids), $redirect_to);
    return $redirect_to;
}
add_filter('handle_bulk_actions-edit-events', 'handle_restore_events_bulk_action', 10, 3);

/**
 * Show admin notice for bulk restore action
 */
function restored_events_bulk_action_admin_notice() {
    if (!empty($_REQUEST['bulk_restored_events'])) {
        $count = intval($_REQUEST['bulk_restored_events']);
        printf('<div id="message" class="updated fade"><p>' .
            _n('Restored %s event to published.',
                'Restored %s events to published.',
                $count,
                'caes-hub'
            ) . '</p></div>', $count);
    }
}
add_action('admin_notices', 'restored_events_bulk_action_admin_notice');

/**
 * Add filter links to show expired events in admin
 */
function add_expired_events_filter_link($views) {
    global $typenow;
    
    if ($typenow === 'events') {
        $expired_count = wp_count_posts('events')->expired ?? 0;
        
        if ($expired_count > 0) {
            $class = (isset($_GET['post_status']) && $_GET['post_status'] === 'expired') ? 'current' : '';
            $views['expired'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                admin_url('edit.php?post_type=events&post_status=expired'),
                $class,
                __('Expired', 'caes-hub'),
                $expired_count
            );
        }
    }
    
    return $views;
}
add_filter('views_edit-events', 'add_expired_events_filter_link');

/**
 * Manual trigger for testing (remove in production or protect with capability check)
 */
function add_expire_events_admin_action() {
    if (current_user_can('manage_options') && isset($_GET['expire_events_now'])) {
        expire_old_events();
        wp_redirect(admin_url('edit.php?post_type=events&expired_now=1'));
        exit;
    }
}
add_action('admin_init', 'add_expire_events_admin_action');