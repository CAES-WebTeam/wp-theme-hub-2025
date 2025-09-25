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
 * UPDATED: Prevent clearing calendars when non-authors save the post
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

    // **NEW: Only sync for original authors, not approvers**
    $current_user_id = get_current_user_id();
    $user = get_userdata($current_user_id);
    $user_roles = (array) $user->roles;
    
    // Admins and editors can sync
    if (!in_array('administrator', $user_roles) && !in_array('editor', $user_roles)) {
        // For non-admin users, only sync if they're the original author
        $post_author_id = get_post_field('post_author', $post_id);
        if ((int) $current_user_id !== (int) $post_author_id) {
            // This is NOT the original submitter - don't sync taxonomy
            // This prevents approvers from accidentally clearing the calendar selection
            return;
        }
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

/*===========================================================================
 * CALENDAR PERMISSIONS SYSTEM
 *===========================================================================*/

/**
 * Filter ACF calendar field to show only allowed calendars
 */
add_filter('acf/load_field/name=caes_department', 'filter_calendar_choices_by_permissions');

function filter_calendar_choices_by_permissions($field) {
    // Only filter in admin
    if (!is_admin()) {
        return $field;
    }
    
    // Get current user
    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        return $field;
    }
    
    // Get user and roles
    $user = get_userdata($current_user_id);
    if (!$user) {
        return $field;
    }
    
    $user_roles = (array) $user->roles;
    
    // Admins and editors can see all calendars - skip filtering
    if (in_array('administrator', $user_roles) || in_array('editor', $user_roles)) {
        return $field;
    }
    
    // Get all calendars first
    $all_calendars = get_terms(array(
        'taxonomy' => 'event_caes_departments',
        'hide_empty' => false,
    ));
    
    if (is_wp_error($all_calendars) || empty($all_calendars)) {
        return $field;
    }
    
    // For non-admin users, filter by permissions
    $allowed_calendar_ids = get_user_submit_calendars($current_user_id);
    
    // Build filtered choices
    $field['choices'] = array();
    
    if (!empty($allowed_calendar_ids)) {
        foreach ($all_calendars as $calendar) {
            if (in_array($calendar->term_id, $allowed_calendar_ids)) {
                $field['choices'][$calendar->term_id] = $calendar->name;
            }
        }
    }
    
    // If no calendars available, show helpful message
    if (empty($field['choices'])) {
        $field['choices'] = array('' => 'No calendars available - contact administrator');
        $field['disabled'] = 1;
        $field['allow_null'] = 1;
    }
    
    // Debug logging for troubleshooting
    // if (defined('WP_DEBUG') && WP_DEBUG) {
    //     error_log('ACF Calendar Filter Debug:');
    //     error_log('User ID: ' . $current_user_id);
    //     error_log('User Roles: ' . implode(', ', $user_roles));
    //     error_log('Allowed Calendar IDs: ' . implode(', ', $allowed_calendar_ids));
    //     error_log('Final Choices: ' . implode(', ', array_keys($field['choices'])));
    // }
    
    return $field;
}

/**
 * JavaScript approach - disable checkboxes instead of hiding them
 */
add_action('admin_footer', 'filter_calendar_checkboxes_js');

function filter_calendar_checkboxes_js() {
    global $typenow, $pagenow;
    
    // Only on events edit pages
    if ($typenow !== 'events' || !in_array($pagenow, ['post.php', 'post-new.php'])) {
        return;
    }
    
    $current_user_id = get_current_user_id();
    $user = get_userdata($current_user_id);
    $user_roles = (array) $user->roles;
    
    // Skip for admins/editors
    if (in_array('administrator', $user_roles) || in_array('editor', $user_roles)) {
        return;
    }
    
    // Get user permissions
    $allowed_submit_calendars = get_user_submit_calendars($current_user_id);
    $allowed_approve_calendars = array();
    
    // Get calendars user can approve
    $all_calendars = get_terms(array(
        'taxonomy' => 'event_caes_departments',
        'hide_empty' => false,
    ));
    
    if (!is_wp_error($all_calendars)) {
        foreach ($all_calendars as $calendar) {
            if (user_can_approve_calendar($current_user_id, $calendar->term_id)) {
                $allowed_approve_calendars[] = $calendar->term_id;
            }
        }
    }
    
    // Check if user is the original author
    $post_id = isset($_GET['post']) ? intval($_GET['post']) : 0;
    $is_original_author = false;
    
    if ($post_id) {
        $post_author_id = get_post_field('post_author', $post_id);
        $is_original_author = ((int) $current_user_id === (int) $post_author_id);
    } else {
        // New post - assume they're the author
        $is_original_author = true;
    }
    
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var allowedSubmitCalendars = <?php echo json_encode($allowed_submit_calendars); ?>;
        var allowedApproveCalendars = <?php echo json_encode($allowed_approve_calendars); ?>;
        var isOriginalAuthor = <?php echo json_encode($is_original_author); ?>;
        var currentUserId = <?php echo $current_user_id; ?>;
        
        console.log('Calendar permissions for user ' + currentUserId + ':');
        console.log('- Can submit to:', allowedSubmitCalendars);
        console.log('- Can approve:', allowedApproveCalendars);
        console.log('- Is original author:', isOriginalAuthor);
        
        // Function to disable/enable calendar checkboxes based on permissions
        function setupCalendarPermissions() {
            // Find the calendar field
            var $calendarField = $('.acf-field[data-name="caes_department"]');
            
            if ($calendarField.length === 0) {
                $calendarField = $('[data-name="caes_department"]').closest('.acf-field');
            }
            
            if ($calendarField.length === 0) {
                console.log('Calendar field not found');
                return;
            }
            
            console.log('Found calendar field:', $calendarField);
            
            // Add explanatory text
            var $existingNotice = $calendarField.find('.calendar-permission-notice');
            if ($existingNotice.length === 0) {
                var noticeText = '';
                if (isOriginalAuthor) {
                    noticeText = '<div class="calendar-permission-notice" style="background: #e7f3ff; border: 1px solid #0073aa; padding: 8px; margin: 5px 0; font-size: 12px;"><strong>Note:</strong> You can only select calendars you have permission to submit to. Disabled calendars are not available to you.</div>';
                } else {
                    noticeText = '<div class="calendar-permission-notice" style="background: #fff3cd; border: 1px solid #ffc107; padding: 8px; margin: 5px 0; font-size: 12px;"><strong>Approver View:</strong> You can see all calendars this event was submitted to, but you can only approve calendars you have permission for using the approval buttons in the sidebar.</div>';
                }
                $calendarField.find('.acf-input').prepend(noticeText);
            }
            
            // For non-authors, completely disable all interaction
            if (!isOriginalAuthor) {
                // Disable ALL calendar checkboxes for non-authors
                $calendarField.find('input[type="checkbox"]').each(function() {
                    var $checkbox = $(this);
                    var $label = $checkbox.closest('label');
                    
                    // Disable completely
                    $checkbox.prop('disabled', true);
                    $label.css({
                        'opacity': '0.7',
                        'cursor': 'not-allowed',
                        'pointer-events': 'none'
                    });
                    
                    // Add visual indicator
                    if (!$label.find('.permission-indicator').length) {
                        $label.append(' <span class="permission-indicator" style="color: #999; font-size: 11px;">(View only)</span>');
                    }
                });
                
                // Remove the field from form submission entirely
                $calendarField.find('input[type="checkbox"]').attr('name', '');
                
                // Prevent any form interaction
                $calendarField.on('click change', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                });
                
                console.log('Completely disabled calendar field for non-author');
            } else {
                // For original authors, handle permission-based disabling
                $calendarField.find('input[type="checkbox"]').each(function() {
                    var $checkbox = $(this);
                    var $label = $checkbox.closest('label');
                    var value = $checkbox.val();
                    var numericValue = parseInt(value, 10);
                    
                    if (isNaN(numericValue)) {
                        return; // Skip non-numeric values
                    }
                    
                    var canSubmit = allowedSubmitCalendars.indexOf(numericValue) !== -1;
                    
                    if (!canSubmit) {
                        // Disable checkboxes they can't submit to
                        $checkbox.prop('disabled', true);
                        $label.css({
                            'opacity': '0.6',
                            'cursor': 'not-allowed'
                        });
                        
                        // Add visual indicator and uncheck if checked
                        if (!$label.find('.permission-indicator').length) {
                            $label.append(' <span class="permission-indicator" style="color: #999; font-size: 11px;">(No permission)</span>');
                        }
                        
                        // Uncheck if it was checked
                        if ($checkbox.is(':checked')) {
                            $checkbox.prop('checked', false);
                        }
                        
                        console.log('Disabled calendar option for author:', value);
                    } else {
                        // Make sure it's enabled and styled normally
                        $checkbox.prop('disabled', false);
                        $label.css({
                            'opacity': '1',
                            'cursor': 'pointer'
                        });
                        
                        console.log('Enabled calendar option for author:', value);
                    }
                });
            }
        }
        
        // Run immediately
        setupCalendarPermissions();
        
        // Run after ACF is ready
        if (typeof acf !== 'undefined') {
            acf.addAction('ready', function() {
                setTimeout(setupCalendarPermissions, 100);
            });
            
            acf.addAction('append', function() {
                setTimeout(setupCalendarPermissions, 100);
            });
        }
        
        // Run on DOM changes as fallback
        setTimeout(setupCalendarPermissions, 1000);
        setTimeout(setupCalendarPermissions, 3000);
    });
    </script>
    <?php
}

/**
 * Show notice if user has no calendar permissions
 */
add_action('admin_notices', 'show_calendar_permission_notice');

function show_calendar_permission_notice() {
    global $typenow, $pagenow;
    
    // Only show on events pages
    if ($typenow !== 'events' || !in_array($pagenow, ['post.php', 'post-new.php', 'edit.php'])) {
        return;
    }
    
    $current_user_id = get_current_user_id();
    $user = get_userdata($current_user_id);
    $user_roles = (array) $user->roles;
    
    // Don't show for admins/editors
    if (in_array('administrator', $user_roles) || in_array('editor', $user_roles)) {
        return;
    }
    
    // Check if user has any calendar permissions
    $allowed_calendars = get_user_submit_calendars($current_user_id);
    
    if (empty($allowed_calendars)) {
        echo '<div class="notice notice-warning">';
        echo '<p><strong>No Calendar Access:</strong> You don\'t have permission to submit events to any calendars. Please contact an administrator to assign calendar permissions to your account.</p>';
        echo '</div>';
    }
}

/**
 * Validate that user can only submit to calendars they have access to
 * ONLY runs for original submitters, NEVER for approvers
 */
add_action('save_post', 'validate_calendar_permissions_on_save', 5, 3);

function validate_calendar_permissions_on_save($post_id, $post, $update) {
    // Only for events
    if ($post->post_type !== 'events') {
        return;
    }
    
    // Skip for autosaves, revisions, etc.
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (wp_is_post_revision($post_id)) {
        return;
    }
    
    // Skip during AJAX approval actions
    if (defined('DOING_AJAX') && DOING_AJAX && isset($_POST['action']) && $_POST['action'] === 'approve_event_calendar') {
        return;
    }
    
    $current_user_id = get_current_user_id();
    $user = get_userdata($current_user_id);
    $user_roles = (array) $user->roles;
    
    // Admins and editors can do anything
    if (in_array('administrator', $user_roles) || in_array('editor', $user_roles)) {
        return;
    }
    
    // **CRITICAL: Only validate for the original author, NEVER for approvers**
    $post_author_id = get_post_field('post_author', $post_id);
    if ((int) $current_user_id !== (int) $post_author_id) {
        // This is NOT the original submitter - don't validate calendar permissions
        // Approvers should NEVER modify calendar selections
        return;
    }
    
    // **ADDITIONAL CHECK: Only run on initial submission, not later edits**
    $has_been_submitted = get_post_meta($post_id, '_submitted_for_approval', true);
    if ($has_been_submitted && $post->post_status === 'pending') {
        // Event has already been submitted - don't modify calendar selection
        return;
    }
    
    // Only run validation for original submitter during initial submission
    $selected_calendars = get_field('caes_department', $post_id);
    if (!is_array($selected_calendars)) {
        $selected_calendars = array();
    }
    
    $allowed_calendars = get_user_submit_calendars($current_user_id);
    
    $unauthorized_calendars = array_diff($selected_calendars, $allowed_calendars);
    
    if (!empty($unauthorized_calendars)) {
        $authorized_calendars = array_intersect($selected_calendars, $allowed_calendars);
        update_field('caes_department', $authorized_calendars, $post_id);
        
        set_transient('calendar_permission_warning_' . $current_user_id, 
            'Some calendars were removed because you don\'t have permission to submit to them.', 30);
    }
}

/**
 * Show calendar permission warning
 */
add_action('admin_notices', 'show_calendar_permission_warning');

function show_calendar_permission_warning() {
    $user_id = get_current_user_id();
    $warning = get_transient('calendar_permission_warning_' . $user_id);
    
    if ($warning) {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>Warning:</strong> ' . esc_html($warning) . '</p>';
        echo '</div>';
        delete_transient('calendar_permission_warning_' . $user_id);
    }
}

/**
 * Additional protection: Make ACF field readonly for non-authors
 */
add_filter('acf/prepare_field/name=caes_department', 'make_calendar_field_readonly_for_non_authors');

function make_calendar_field_readonly_for_non_authors($field) {
    // Only in admin
    if (!is_admin()) {
        return $field;
    }
    
    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        return $field;
    }
    
    $user = get_userdata($current_user_id);
    $user_roles = (array) $user->roles;
    
    // Skip for admins/editors
    if (in_array('administrator', $user_roles) || in_array('editor', $user_roles)) {
        return $field;
    }
    
    // Get post ID
    $post_id = 0;
    if (isset($_GET['post']) && is_numeric($_GET['post'])) {
        $post_id = intval($_GET['post']);
    } elseif (isset($_POST['post_ID']) && is_numeric($_POST['post_ID'])) {
        $post_id = intval($_POST['post_ID']);
    }
    
    if ($post_id) {
        $post_author_id = get_post_field('post_author', $post_id);
        
        // If current user is NOT the original author, make field readonly
        if ((int) $current_user_id !== (int) $post_author_id) {
            $field['readonly'] = 1;
            $field['disabled'] = 1;
            
            // Add instruction explaining why it's disabled
            $field['instructions'] = 'Calendar selection is locked. Only the original submitter can modify which calendars this event is submitted to. Use the approval buttons in the sidebar to approve calendars you have permission for.';
        }
        
        // Also check if already submitted - even author can't modify after submission
        $has_been_submitted = get_post_meta($post_id, '_submitted_for_approval', true);
        if ($has_been_submitted && get_post_status($post_id) === 'pending') {
            $field['readonly'] = 1;
            $field['disabled'] = 1;
            $field['instructions'] = 'Calendar selection is locked because this event has been submitted for approval. Use the approval buttons in the sidebar to approve specific calendars.';
        }
    }
    
    return $field;
}

/*===========================================================================
 * RESTRICT USERS TO ONLY SEE THEIR EVENTS OR EVENTS THEY CAN APPROVE
 *===========================================================================*/

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
			// Get calendars assigned to this user (using new permission system)
			$all_calendars = get_terms(array(
				'taxonomy' => 'event_caes_departments',
				'hide_empty' => false,
			));
			
			$assigned_calendar_ids = array();
			
			if (!is_wp_error($all_calendars) && !empty($all_calendars)) {
				foreach ($all_calendars as $calendar) {
					// Check both the old ACF system and new permission system
					$assigned_approver = get_field('calendar_approver', 'event_caes_departments_' . $calendar->term_id);
					
					if ($assigned_approver && (int) $assigned_approver === (int) $current_user_id) {
						$assigned_calendar_ids[] = $calendar->term_id;
					} elseif (user_can_approve_calendar($current_user_id, $calendar->term_id)) {
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

/*===========================================================================
 * EVENT EXPIRY FUNCTIONS WITH CUSTOM POST STATUS
 *===========================================================================*/

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
    // Since 'event_date_type' no longer exists, we will directly check for the date fields.
    // This logic assumes all events follow the simple start/end date format.
    $last_event_date = null;

    // Use WordPress's native get_post_meta() for reliability in all contexts.
    $end_date = get_post_meta($post_id, 'end_date', true);
    $start_date = get_post_meta($post_id, 'start_date', true);
    
    // Prioritize the end_date if it exists, otherwise fall back to the start_date.
    if (!empty($end_date)) {
        $last_event_date = $end_date;
    } elseif (!empty($start_date)) {
        $last_event_date = $start_date;
    }
    
    // If no date was found at all, we cannot expire the event.
    if (empty($last_event_date)) {
        return false;
    }
    
    // Convert the date from 'Ymd' format to a DateTime object for comparison.
    $date_object = DateTime::createFromFormat('Ymd', $last_event_date);
    if (!$date_object) {
        return false; // Stop if the date format is invalid.
    }
    
    // An event officially expires the day AFTER its last day.
    $date_object->add(new DateInterval('P1D'));
    $expiry_date_str = $date_object->format('Y-m-d');
    
    // Get today's date based on the WordPress timezone for an accurate comparison.
    $today_str = (new DateTime('now', new DateTimeZone(wp_timezone_string())))->format('Y-m-d');
    
    // The event is expired if today's date is on or after the expiry date.
    return $today_str >= $expiry_date_str;
}

/**
 * Exclude expired events from all frontend queries
 * This is now much simpler since expired events have a different post status
 * ALSO exclude pending events from frontend
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
        
        // Ensure we only get published events (excludes expired and pending automatically)
        $post_status = $query->get('post_status');
        if (empty($post_status)) {
            $query->set('post_status', 'publish');
        } elseif (is_array($post_status)) {
            // If post_status is an array, make sure only 'publish' is included for events
            $query->set('post_status', array('publish'));
        } elseif ($post_status !== 'publish') {
            // If a specific non-publish status is set, override it to publish
            $query->set('post_status', 'publish');
        }
    }
}
add_action('pre_get_posts', 'exclude_expired_events_from_queries');

/**
 * Make expired events return 404 on single event pages
 * This handles edge cases where cron hasn't run yet
 * ALSO handle pending events - they should not be viewable on frontend
 */
function redirect_expired_events_to_404() {
    if (is_singular('events')) {
        $post_id = get_queried_object_id();
        $post_status = get_post_status($post_id);
        
        // If marked as expired, 404
        if ($post_status === 'expired') {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return;
        }
        
        // If pending (not approved yet), 404 on frontend
        if ($post_status === 'pending') {
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

/*===========================================================================
 * DEBUGGING FUNCTIONS (Remove after testing)
 *===========================================================================*/

/**
 * Debug function to check what permissions a user has
 */
function debug_user_calendar_permissions() {
    if (!current_user_can('administrator')) {
        return; // Only admins can see this debug info
    }
    
    if (isset($_GET['debug_user_permissions']) && is_numeric($_GET['debug_user_permissions'])) {
        $user_id = intval($_GET['debug_user_permissions']);
        $user = get_userdata($user_id);
        
        if ($user) {
            echo '<div style="background: #f0f0f0; padding: 20px; margin: 20px; border: 1px solid #ccc;">';
            echo '<h3>Debug: User Calendar Permissions for ' . esc_html($user->display_name) . '</h3>';
            
            $submit_permissions = get_user_meta($user_id, 'calendar_submit_permissions', true);
            $approve_permissions = get_user_meta($user_id, 'calendar_approve_permissions', true);
            
            echo '<p><strong>Submit Permissions:</strong> ';
            if (is_array($submit_permissions) && !empty($submit_permissions)) {
                foreach ($submit_permissions as $term_id) {
                    $term = get_term($term_id);
                    echo $term ? $term->name . ' (' . $term_id . '), ' : 'Unknown (' . $term_id . '), ';
                }
            } else {
                echo 'None';
            }
            echo '</p>';
            
            echo '<p><strong>Approve Permissions:</strong> ';
            if (is_array($approve_permissions) && !empty($approve_permissions)) {
                foreach ($approve_permissions as $term_id) {
                    $term = get_term($term_id);
                    echo $term ? $term->name . ' (' . $term_id . '), ' : 'Unknown (' . $term_id . '), ';
                }
            } else {
                echo 'None';
            }
            echo '</p>';
            
            $allowed_submit_calendars = get_user_submit_calendars($user_id);
            echo '<p><strong>get_user_submit_calendars() result:</strong> ';
            if (!empty($allowed_submit_calendars)) {
                foreach ($allowed_submit_calendars as $term_id) {
                    $term = get_term($term_id);
                    echo $term ? $term->name . ' (' . $term_id . '), ' : 'Unknown (' . $term_id . '), ';
                }
            } else {
                echo 'None';
            }
            echo '</p>';
            
            echo '<p><strong>User Roles:</strong> ' . implode(', ', $user->roles) . '</p>';
            echo '</div>';
        }
    }
}
add_action('admin_notices', 'debug_user_calendar_permissions');

/**
 * Add debug link to user list
 */
add_filter('user_row_actions', 'add_debug_permission_link', 10, 2);
function add_debug_permission_link($actions, $user_object) {
    if (current_user_can('administrator')) {
        $actions['debug_permissions'] = '<a href="' . admin_url('users.php?debug_user_permissions=' . $user_object->ID) . '">Debug Permissions</a>';
    }
    return $actions;
}

/**
 * Debug the ACF field filtering
 */
add_action('admin_footer', 'debug_acf_field_filtering');
function debug_acf_field_filtering() {
    global $typenow, $pagenow;
    
    if ($typenow === 'events' && in_array($pagenow, ['post.php', 'post-new.php']) && current_user_can('administrator')) {
        ?>
        <script>
        console.log('ACF Field Debug - Current User ID:', <?php echo get_current_user_id(); ?>);
        console.log('ACF Field Debug - User Roles:', <?php echo json_encode(wp_get_current_user()->roles); ?>);
        
        // Log when ACF field loads
        if (typeof acf !== 'undefined') {
            acf.addAction('ready', function() {
                var field = acf.getField('field_caes_department'); // Replace with your actual field key
                if (field) {
                    console.log('ACF Field found:', field);
                    console.log('ACF Field choices:', field.get('choices'));
                } else {
                    console.log('ACF Field not found - trying alternative selectors');
                    // Try to find by name
                    jQuery('[data-name="caes_department"]').each(function() {
                        console.log('Found ACF field element:', this);
                    });
                }
            });
        }
        </script>
        <?php
    }
}

/**
 * PROPER FIX: Restore calendar values AFTER ACF finishes processing
 * Priority 20 ensures this runs AFTER all ACF internal processing
 */
add_action('acf/save_post', 'restore_calendar_values_after_acf', 20);

function restore_calendar_values_after_acf($post_id) {
    // Only for events
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'events') {
        return;
    }
    
    // Skip during AJAX approval actions
    if (defined('DOING_AJAX') && DOING_AJAX && isset($_POST['action']) && $_POST['action'] === 'approve_event_calendar') {
        return;
    }
    
    $current_user_id = get_current_user_id();
    $user = get_userdata($current_user_id);
    $user_roles = (array) $user->roles;
    
    // Admins and editors can modify anything
    if (in_array('administrator', $user_roles) || in_array('editor', $user_roles)) {
        return;
    }
    
    // Check if current user is the original author
    $post_author_id = get_post_field('post_author', $post_id);
    if ((int) $current_user_id === (int) $post_author_id) {
        return; // Original author can modify
    }
    
    // Non-author tried to save - restore original calendar values
    $preserved_calendars = get_transient('calendar_backup_' . $post_id . '_' . $current_user_id);
    
    if ($preserved_calendars !== false) {
        // error_log("RESTORING calendars for post {$post_id}: " . print_r($preserved_calendars, true));
        
        // Restore both ACF field and taxonomy
        update_field('caes_department', $preserved_calendars, $post_id);
        wp_set_post_terms($post_id, $preserved_calendars, 'event_caes_departments');
        
        // Clean up
        delete_transient('calendar_backup_' . $post_id . '_' . $current_user_id);
    }
}

// Store calendar values BEFORE WordPress processes the form
add_action('init', 'preserve_calendars_before_save');

function preserve_calendars_before_save() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_ID'])) {
        $post_id = intval($_POST['post_ID']);
        $post = get_post($post_id);
        
        if ($post && $post->post_type === 'events') {
            $current_user_id = get_current_user_id();
            $post_author_id = get_post_field('post_author', $post_id);
            
            // If non-author is saving, preserve calendar values
            if ((int) $current_user_id !== (int) $post_author_id) {
                $existing_calendars = get_field('caes_department', $post_id);
                if (!empty($existing_calendars)) {
                    set_transient('calendar_backup_' . $post_id . '_' . $current_user_id, $existing_calendars, 300);
                    // error_log("PRESERVING calendars before save: " . print_r($existing_calendars, true));
                }
            }
        }
    }
}

/**
 * ========================================================================
 * ADVANCED DEBUGGING: EVENT EXPIRATION TEST & TRIGGER TOOL
 * ========================================================================
 */

// 1. Add a new submenu page under "Events"
add_action('admin_menu', 'caes_add_event_expiration_test_page');
function caes_add_event_expiration_test_page() {
    add_submenu_page(
        'edit.php?post_type=events',
        'Event Expiration Tool',
        'Expiration Tool',
        'manage_options',
        'event-expiration-tool',
        'caes_render_event_expiration_tool_page'
    );
}

// 2. Render the content for the tool page
function caes_render_event_expiration_tool_page() {
    ?>
    <div class="wrap">
        <h1>Event Expiration Tool & Debugger</h1>
        <p>This tool helps you test and manually run the daily script that moves past events to an "expired" status.</p>

        <?php
        // Handle the MANUAL RUN if the button was clicked
        if (isset($_POST['caes_run_expiration_script_now']) && check_admin_referer('caes_run_expiration_script_nonce')) {
            $expired_count = caes_run_expiration_script_manually();
            echo '<div class="notice notice-success is-dismissible"><p>Successfully ran the expiration script. <strong>' . absint($expired_count) . '</strong> events were moved to "Expired" status.</p></div>';
        }
        ?>

        <div class="card">
            <h2 class="title">Manually Run Expiration Script</h2>
            <p>This will immediately run the expiration script on all "Published" and "Pending" events. This is the same action the automated daily cron job performs.</p>
            <form method="post">
                <?php wp_nonce_field('caes_run_expiration_script_nonce'); ?>
                <input type="hidden" name="caes_run_expiration_script_now" value="1">
                <input type="submit" class="button button-primary" value="Run Expiration Script Now" onclick="return confirm('Are you sure you want to run the expiration script? This will immediately change the status of any past events to EXPIRED.');">
            </form>
        </div>

        <hr>

        <div class="card">
            <h2 class="title">Run a "Dry Run" Debugging Test</h2>
            <p>This will simulate the expiration check without making any changes to your events. Use this to see which events the script *would* expire if it were run.</p>
            <form method="post">
                <?php wp_nonce_field('caes_run_expiration_test_nonce'); ?>
                <input type="hidden" name="caes_run_expiration_test" value="1">
                <p><input type="submit" class="button button-secondary" value="Run Debugging Test"></p>
            </form>

            <?php
            // If the debug test form was submitted, show the results
            if (isset($_POST['caes_run_expiration_test']) && check_admin_referer('caes_run_expiration_test_nonce')) {
                caes_run_expiration_debug_test();
            }
            ?>
        </div>
    </div>
    <?php
}

// 3. The main debugging test function (no changes to database)
function caes_run_expiration_debug_test() {
    echo '<h2>Test Results:</h2>';

    $events = get_posts(array(
        'post_type'      => 'events',
        'posts_per_page' => -1,
        'post_status'    => ['publish', 'pending'],
        'orderby'        => 'ID',
        'order'          => 'DESC'
    ));

    if (empty($events)) {
        echo '<p>No published or pending events found to test.</p>';
        return;
    }

    echo '<p>Found ' . count($events) . ' events to check. Today\'s date for comparison is: <strong>' . date('Y-m-d') . '</strong></p>';
    echo '<table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">';
    echo '<thead><tr><th style="width: 25%;">Event Title (ID)</th><th style="width: 45%;">Data Check</th><th>Decision</th></tr></thead>';
    echo '<tbody>';

    foreach ($events as $event) {
        $event_id = $event->ID;
        
        $last_event_date = null;
        $end_date = get_post_meta($event_id, 'end_date', true);
        $start_date = get_post_meta($event_id, 'start_date', true);

        if (!empty($end_date)) {
            $last_event_date = $end_date;
        } elseif (!empty($start_date)) {
            $last_event_date = $start_date;
        }

        $data_check_output = '<strong>End Date Found:</strong> <code>' . ($end_date ? $end_date : 'Not Set') . '</code><br>';
        $data_check_output .= '<strong>Start Date Found:</strong> <code>' . ($start_date ? $start_date : 'Not Set') . '</code><br>';
        $data_check_output .= '<strong>Date Used for Check:</strong> <code>' . ($last_event_date ? $last_event_date : 'None') . '</code>';
        
        $is_expired = is_event_expired($event_id);

        echo '<tr>';
        echo '<td><a href="' . get_edit_post_link($event_id) . '">' . esc_html($event->post_title) . '</a> (' . $event_id . ')</td>';
        echo '<td>' . $data_check_output . '</td>';
        
        if ($is_expired) {
            echo '<td style="background-color: #f8d7da; color: #721c24;"><strong>Will Expire</strong></td>';
        } else {
            echo '<td style="background-color: #d4edda; color: #155724;">Will Not Expire</td>';
        }
        
        echo '</tr>';
    }

    echo '</tbody></table>';
}

/**
 * 4. NEW Function to MANUALLY run the expiration and return a count.
 * This function makes actual changes to the database.
 *
 * @return int The number of posts that were expired.
 */
function caes_run_expiration_script_manually() {
    $expired_count = 0;
    
    $events = get_posts(array(
        'post_type'      => 'events',
        'posts_per_page' => -1,
        'post_status'    => ['publish', 'pending'],
        'fields'         => 'ids'
    ));
    
    foreach ($events as $event_id) {
        if (is_event_expired($event_id)) {
            wp_update_post(array(
                'ID' => $event_id,
                'post_status' => 'expired'
            ));
            $expired_count++;
        }
    }
    
    return $expired_count;
}

/**
 * ===================================================================
 * FINAL DIAGNOSTIC: Is the Image Alt Text actually being saved?
 * ===================================================================
 * This hook fires specifically when an attachment's metadata is updated.
 * It will tell us if the alt text from the media modal is ever received by WordPress.
 */
function debug_check_attachment_save( $attachment_id ) {
    
    // Check the data that was sent in the request
    // WordPress puts attachment changes in the 'changes' array in the POST data.
    $changes = isset($_POST['changes']) ? $_POST['changes'] : null;

    // Log the raw data received for this attachment ID
    error_log("--- Fired 'edit_attachment' for Attachment ID: {$attachment_id} ---");
    error_log("Data received in \$_POST['changes']: " . print_r($changes, true));
    error_log("-------------------------------------------\n");

}
add_action( 'edit_attachment', 'debug_check_attachment_save' );

/**
 * ===================================================================
 * THE REQUIRED FIX (Keep this active)
 * ===================================================================
 * This function remains essential to ensure the theme and WordPress core
 * are in sync.
 */
function final_sync_acf_image_to_thumbnail( $post_id, $post ) {
    if ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_revision($post_id) ) {
        return;
    }
    if ( function_exists('get_field') ) {
        $image_array = get_field('featured_image', $post_id, false);
        $image_id = is_array($image_array) ? $image_array['id'] : $image_array;
        if ( !empty($image_id) && is_numeric($image_id) ) {
            update_post_meta($post_id, '_thumbnail_id', $image_id);
        } else {
            delete_post_meta($post_id, '_thumbnail_id');
        }
    }
}
add_action( 'save_post_events', 'final_sync_acf_image_to_thumbnail', 99, 2 );