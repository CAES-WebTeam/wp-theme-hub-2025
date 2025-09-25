<?php
/**
 * events-approval-workflow.php
 *
 * This file contains the core logic for the Events approval system.
 * It hooks into the 'save_post' action to handle submissions from 'Event Submitters'
 * and to manage the approval status of events submitted to multiple calendars.
 *
 * This version ONLY uses the user permission system - no ACF dependencies.
 *
 * @package YourThemeName/Events
 */

// Hook into 'save_post' to handle event submissions and approval logic.
add_action('save_post', 'handle_event_submission_and_approval', 10, 3);

/**
 * Handles the event submission and approval process.
 *
 * This function intercepts the `save_post` action for the 'events' post type.
 * It checks the user's permissions, the selected calendars, and a custom
 * post meta field to determine the event's overall status. It also
 * sends notifications to the appropriate approvers.
 *
 * @param int      $post_id The ID of the post being saved.
 * @param WP_Post  $post    The post object.
 * @param bool     $update  Whether this is an existing post being updated.
 */
function handle_event_submission_and_approval($post_id, $post, $update) {
    // Bail early if it's an autosave, a revision, or if the user lacks the necessary permissions.
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
        return;
    }
    if ( wp_is_post_revision($post_id) ) {
        return;
    }
    // Check if we are dealing with our custom post type 'events'.
    if ( 'events' !== $post->post_type ) {
        return;
    }
    // Check if the current user can edit the post.
    if ( ! current_user_can('edit_post', $post_id) ) {
        return;
    }

    // Skip calendar validation if this is triggered by an AJAX approval action
    if (defined('DOING_AJAX') && DOING_AJAX && isset($_POST['action']) && $_POST['action'] === 'approve_event_calendar') {
        // Continue with the rest of the workflow logic without calendar validation
        error_log("=== WORKFLOW DEBUG: AJAX Approval detected, skipping calendar validation ===");
    } else {
        // CALENDAR VALIDATION LOGIC - Only for regular form submissions
        
        // Get the current user ID and their roles.
        $current_user_id = get_current_user_id();
        $current_user = new WP_User($current_user_id);
        $current_user_roles = (array) $current_user->roles;

        // Check for calendar selection with multiple methods
        $has_calendars = false;
        
        // Method 1: Check taxonomy terms
        $event_calendars = get_the_terms($post_id, 'event_caes_departments');
        if ($event_calendars && !is_wp_error($event_calendars) && !empty($event_calendars)) {
            $has_calendars = true;
        }
        
        // Method 2: Check ACF field directly if no taxonomy terms found
        if (!$has_calendars) {
            $acf_calendars = get_field('caes_department', $post_id);
            if ($acf_calendars && !empty($acf_calendars)) {
                $has_calendars = true;
            }
        }
        
        // Method 3: Check POST data for calendar selection (during form submission)
        if (!$has_calendars && isset($_POST['acf']) && !empty($_POST['acf'])) {
            foreach ($_POST['acf'] as $field_key => $field_value) {
                // Look for ACF field that might contain calendar data
                if (is_array($field_value) && !empty($field_value)) {
                    // Check if this might be the calendar field
                    $field_object = get_field_object($field_key);
                    if ($field_object && isset($field_object['name']) && $field_object['name'] === 'caes_department') {
                        $has_calendars = true;
                        break;
                    }
                }
            }
        }

        // Only enforce calendar selection for non-admin/editor users trying to submit for approval
        if (!$has_calendars && !in_array('administrator', $current_user_roles) && !in_array('editor', $current_user_roles)) {
            // Only prevent submission if user is trying to submit for approval
            if ($post->post_status === 'pending' || ($post->post_status === 'publish' && !current_user_can('publish_events'))) {
                // Remove this hook temporarily to avoid infinite loop
                remove_action('save_post', 'handle_event_submission_and_approval', 10);
                
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_status' => 'draft',
                ));
                
                // Re-add the hook
                add_action('save_post', 'handle_event_submission_and_approval', 10, 3);
                
                // Add admin notice for the user
                set_transient('event_calendar_required_' . $current_user_id, 'You must select at least one calendar before submitting your event for approval.', 30);
                
                update_post_meta($post_id, '_previous_status', 'draft');
                return;
            }
        }
    }

    // Get the current user ID and their roles (if not already set above)
    if (!isset($current_user_id)) {
        $current_user_id = get_current_user_id();
        $current_user = new WP_User($current_user_id);
        $current_user_roles = (array) $current_user->roles;
    }

    // Get the previous post status.
    $previous_status = get_post_meta($post_id, '_previous_status', true);
    if (!$previous_status) {
        $previous_status = 'draft';
    }
    
    // Get submission flag
    $was_submitted = get_post_meta($post_id, '_submitted_for_approval', true);

    // Check if current user can publish this specific event
    $user_can_publish = false;
    
    // Admins and editors can always publish
    if (in_array('administrator', $current_user_roles) || in_array('editor', $current_user_roles)) {
        $user_can_publish = true;
    } else {
        // For other users, check if they can approve AT LEAST ONE selected calendar
        $user_can_publish = user_can_approve_any_event_calendars($current_user_id, $post_id);
    }

    // SCENARIO 1: User trying to publish but can't - force to pending and notify
    if (!$user_can_publish && $post->post_status === 'publish') {
        // Remove this hook temporarily to avoid infinite loop
        remove_action('save_post', 'handle_event_submission_and_approval', 10);
        
        wp_update_post(array(
            'ID' => $post_id,
            'post_status' => 'pending',
        ));
        
        // Mark as submitted for approval
        update_post_meta($post_id, '_submitted_for_approval', true);
        
        // Re-add the hook
        add_action('save_post', 'handle_event_submission_and_approval', 10, 3);
        
        // Send notification to approvers
        $calendar_approvers = get_event_approvers_for_post($post_id);
        if (!empty($calendar_approvers)) {
            send_approval_notification_email($post_id, $calendar_approvers);
        }
        
        update_post_meta($post_id, '_previous_status', 'pending');
        return;
    }

    // SCENARIO 2: Status change to pending (submission for approval)
    if ($post->post_status === 'pending' && $previous_status !== 'pending' && !$was_submitted) {
        // Mark as submitted for approval
        update_post_meta($post_id, '_submitted_for_approval', true);
        
        // Send notification to approvers
        $calendar_approvers = get_event_approvers_for_post($post_id);
        if (!empty($calendar_approvers)) {
            send_approval_notification_email($post_id, $calendar_approvers);
        }
    }

    // SCENARIO 3: User can publish and is publishing
    if ($user_can_publish && $post->post_status === 'publish') {
        // Auto-approve calendars the user has permission for
        $event_calendars = get_the_terms($post_id, 'event_caes_departments');
        $calendar_approval_status = get_post_meta($post_id, '_calendar_approval_status', true);
        if (!is_array($calendar_approval_status)) {
            $calendar_approval_status = array();
        }
        
        $calendars_needing_approval = array();
        $any_auto_approved = false;
        
        if ($event_calendars && !is_wp_error($event_calendars)) {
            foreach ($event_calendars as $term) {
                // If user can approve this calendar, auto-approve it
                if (user_can_approve_calendar($current_user_id, $term->term_id)) {
                    $calendar_approval_status[$term->term_id] = 'approved';
                    $any_auto_approved = true;
                    error_log("Auto-approved calendar {$term->term_id} ({$term->name}) for user {$current_user_id}");
                } else {
                    // Calendar needs approval from someone else
                    $calendars_needing_approval[] = $term->term_id;
                    error_log("Calendar {$term->term_id} ({$term->name}) needs approval from others");
                }
            }
        }
        
        // Update approval status if any calendars were auto-approved
        if ($any_auto_approved) {
            update_post_meta($post_id, '_calendar_approval_status', $calendar_approval_status);
        }
        
        // Send notifications for calendars that need approval from others
        if (!empty($calendars_needing_approval)) {
            // Mark as submitted for approval
            update_post_meta($post_id, '_submitted_for_approval', true);
            
            // Get approvers for the calendars that need approval
            $calendar_approvers = array();
            foreach ($calendars_needing_approval as $calendar_term_id) {
                $users_with_approve_permission = get_users(array(
                    'meta_query' => array(
                        array(
                            'key' => 'calendar_approve_permissions',
                            'value' => $calendar_term_id,
                            'compare' => 'LIKE'
                        )
                    ),
                    'fields' => 'ID'
                ));
                
                if (!empty($users_with_approve_permission)) {
                    $calendar_approvers = array_merge($calendar_approvers, $users_with_approve_permission);
                }
            }
            
            // Send notifications to approvers for remaining calendars
            if (!empty($calendar_approvers)) {
                $unique_approvers = array_unique($calendar_approvers);
                send_approval_notification_email($post_id, $unique_approvers);
                error_log("Sent approval notifications to: " . implode(', ', $unique_approvers));
            }
        }
        
        // Send notification to original submitter if status changed from pending (and submitter is different from publisher)
        if ($previous_status === 'pending' && $current_user_id != get_post_field('post_author', $post_id)) {
            $event_submitter = get_post_field('post_author', $post_id);
            send_submitter_notification_email($post_id, $event_submitter);
        }
    }

    // Update the previous status for next time
    update_post_meta($post_id, '_previous_status', $post->post_status);
}

// Allow Event Approvers to edit pending events for their assigned calendars
add_filter('map_meta_cap', 'allow_event_approver_edit_permissions', 10, 4);

/**
 * Custom capability filter to allow Event Approvers to edit pending events
 * for calendars they're assigned to approve
 */
function allow_event_approver_edit_permissions($caps, $cap, $user_id, $args) {
    // Only apply to edit_post capability for events
    if ($cap !== 'edit_post' || empty($args) || !isset($args[0])) {
        return $caps;
    }
    
    $post_id = $args[0];
    $post = get_post($post_id);
    
    // Only apply to events post type
    if (!$post || $post->post_type !== 'events') {
        return $caps;
    }
    
    $user = get_userdata($user_id);
    if (!$user) {
        return $caps;
    }
    
    $user_roles = (array) $user->roles;
    
    // Admins and editors already have full access
    if (in_array('administrator', $user_roles) || in_array('editor', $user_roles)) {
        return $caps;
    }
    
    // Only apply to Event Approvers
    if (!in_array('event_approver', $user_roles)) {
        return $caps;
    }
    
    // Check if this Event Approver has approval permissions for any of the event's calendars
    $event_calendars = get_the_terms($post_id, 'event_caes_departments');
    
    if ($event_calendars && !is_wp_error($event_calendars)) {
        foreach ($event_calendars as $calendar) {
            // Check if this user has approval permission for this calendar
            if (user_can_approve_calendar($user_id, $calendar->term_id)) {
                // Return empty array to grant permission
                return array();
            }
        }
    }
    
    // If not assigned to any of the event's calendars, keep original capabilities
    return $caps;
}

/**
 * Check if a user can approve all calendars selected for an event
 */
function user_can_approve_all_event_calendars($user_id, $post_id) {
    $event_calendars = get_the_terms($post_id, 'event_caes_departments');
    
    // If no calendars selected, only admins/editors can publish
    if (!$event_calendars || is_wp_error($event_calendars)) {
        return false;
    }
    
    // Check each calendar
    foreach ($event_calendars as $term) {
        // Check if this user can approve this calendar
        if (!user_can_approve_calendar($user_id, $term->term_id)) {
            return false;
        }
    }
    
    return true;
}

/**
 * Check if a user can approve at least one calendar selected for an event
 */
function user_can_approve_any_event_calendars($user_id, $post_id) {
    $event_calendars = get_the_terms($post_id, 'event_caes_departments');
    
    // If no calendars selected, only admins/editors can publish
    if (!$event_calendars || is_wp_error($event_calendars)) {
        return false;
    }
    
    // Check each calendar - return true if user can approve any of them
    foreach ($event_calendars as $term) {
        if (user_can_approve_calendar($user_id, $term->term_id)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Sends an approval notification email to the designated approvers.
 * For local development, this function logs the email content to the error log.
 *
 * @param int   $post_id The event post ID.
 * @param array $approver_ids An array of user IDs to notify.
 */
function send_approval_notification_email($post_id, $approver_ids) {
    $post_title = get_the_title($post_id);
    $edit_link = get_edit_post_link($post_id);
    $to_emails = [];

    foreach ($approver_ids as $approver_id) {
        $user_info = get_userdata($approver_id);
        if ($user_info) {
            $to_emails[] = $user_info->user_email;
        }
    }

    $subject = sprintf(__('New event submission: %s', 'caes-hub'), $post_title);
    $message = sprintf(__('A new event "%s" has been submitted and is awaiting your approval. You can review it here: %s', 'caes-hub'), $post_title, $edit_link);

    // For local testing, we log the details.
    error_log("--- NEW EVENT SUBMISSION ---\n" .
              "Event Title: " . $post_title . "\n" .
              "Awaiting Approval Link: " . $edit_link . "\n" .
              "Approvers Notified: " . implode(', ', $to_emails) . "\n" .
              "----------------------------");

    // To use wp_mail, uncomment the following line and comment out the error_log above.
    wp_mail($to_emails, $subject, $message);
}

/**
 * Sends a publication notification email to the original event submitter.
 * For local development, this function logs the email content to the error log.
 *
 * @param int $post_id The event post ID.
 * @param int $submitter_id The ID of the original post author.
 */
function send_submitter_notification_email($post_id, $submitter_id) {
    $user_info = get_userdata($submitter_id);
    if (!$user_info) {
        return;
    }

    $post_title = get_the_title($post_id);
    $permalink = get_permalink($post_id);
    $to = $user_info->user_email;

    $subject = sprintf(__('Your event has been approved: %s', 'caes-hub'), $post_title);
    $message = sprintf(__('Your event "%s" has been approved and is now live! View it here: %s', 'caes-hub'), $post_title, $permalink);

    // For local testing, we log the details.
    error_log("--- EVENT APPROVED ---\n" .
              "Event Title: " . $post_title . "\n" .
              "Submitter Notified: " . $to . "\n" .
              "Live Event Link: " . $permalink . "\n" .
              "----------------------");

    // To use wp_mail, uncomment the following line and comment out the error_log above.
    wp_mail($to, $subject, $message);
}

// Add admin notice for approval requirement
add_action('admin_notices', 'show_event_approval_notices');
function show_event_approval_notices() {
    $user_id = get_current_user_id();
    
    // Check for approval requirement notice
    $approval_notice = get_transient('event_approval_notice_' . $user_id);
    if ($approval_notice) {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p>' . esc_html($approval_notice) . '</p>';
        echo '</div>';
        delete_transient('event_approval_notice_' . $user_id);
    }
    
    // Check for calendar requirement notice
    $calendar_notice = get_transient('event_calendar_required_' . $user_id);
    if ($calendar_notice) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>Error:</strong> ' . esc_html($calendar_notice) . '</p>';
        echo '</div>';
        delete_transient('event_calendar_required_' . $user_id);
    }
}