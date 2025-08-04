<?php

/**
 * events-roles.php
 *
 * This file handles the registration of custom user roles and their capabilities
 * for the Events custom post type. It defines the 'Event Submitter' and
 * 'Event Approver' roles, and also modifies existing roles to interact with
 * the approval system.
 *
 * It is designed to be included in your main functions file or an events-main.php
 * file within the new 'events' directory.
 *
 */

// Hook into 'init' to ensure all roles and capabilities are set up on theme/plugin activation.
add_action('init', 'register_event_custom_roles');

/**
 * Registers custom roles for the Events post type.
 *
 * This function adds two new roles: 'Event Submitter' and 'Event Approver'.
 * It also modifies existing roles to grant them the necessary permissions
 * to interact with the events approval system.
 */
function register_event_custom_roles()
{
    // Get the administrator role. We'll use this to copy existing capabilities.
    $admin_role = get_role('administrator');

    // ----------------------------------------------------------------------
    // 1. Define the 'Event Submitter' Role
    // This role can create and edit their own events, but cannot publish them.
    // Their events will be saved as 'draft' or 'pending' for review.
    // ----------------------------------------------------------------------
    // Define the 'Event Submitter' Role - UPDATED VERSION
    if (null === get_role('event_submitter')) {
        add_role(
            'event_submitter',
            __('Event Submitter', 'caes-hub'),
            array(
                'read'                     => true,  // Can read content
                'edit_events'              => true,  // Can edit events
                'edit_published_events'    => false, // Cannot edit published events
                'publish_events'           => false, // Cannot publish events
                'delete_events'            => true,  // Can delete their own events
                'delete_published_events'  => false, // Cannot delete published events
                'edit_others_events'       => false, // Cannot edit others' events
                'delete_others_events'     => false, // Cannot delete others' events
                'read_private_events'      => false, // Cannot read private events
                'edit_private_events'      => false, // Cannot edit private events
                'delete_private_events'    => false, // Cannot delete private events
                'upload_files'             => true,  // Can upload media for events
            )
        );
    } else {
        // If role already exists, update its capabilities
        $submitter_role = get_role('event_submitter');
        if ($submitter_role) {
            // Remove any potentially problematic capabilities
            $submitter_role->remove_cap('publish_events');
            $submitter_role->remove_cap('edit_published_events');
            $submitter_role->remove_cap('edit_others_events');
            $submitter_role->remove_cap('delete_others_events');
            $submitter_role->remove_cap('delete_published_events');
            $submitter_role->remove_cap('read_private_events');
            $submitter_role->remove_cap('edit_private_events');
            $submitter_role->remove_cap('delete_private_events');

            // Ensure they have the capabilities they need
            $submitter_role->add_cap('read');
            $submitter_role->add_cap('edit_events');
            $submitter_role->add_cap('delete_events');
            $submitter_role->add_cap('upload_files');
        }
    }

    // ----------------------------------------------------------------------
    // 2. Define the 'Event Approver' Role
    // This new role can publish events, which is the key difference from a submitter.
    // They will also receive submission notifications.
    // ----------------------------------------------------------------------
    // Define the 'Event Approver' Role - UPDATED VERSION
    if (null === get_role('event_approver')) {
        add_role(
            'event_approver',
            __('Event Approver', 'caes-hub'),
            array(
                'read'                     => true,  // Can read content
                'edit_events'              => true,  // Can edit events
                'edit_others_events'       => true,  // Can edit events from others
                'edit_published_events'    => true,  // Can edit published events
                'edit_private_events'      => true,  // Can edit private events
                'publish_events'           => true,  // Can publish events
                'delete_events'            => true,  // Can delete events
                'delete_others_events'     => true,  // Can delete others' events
                'delete_published_events'  => true,  // Can delete published events
                'delete_private_events'    => true,  // Can delete private events
                'read_private_events'      => true,  // Can read private events
                'upload_files'             => true,  // Can upload media for events
            )
        );
    } else {
        // If role already exists, update its capabilities to ensure they have edit permissions
        $approver_role = get_role('event_approver');
        if ($approver_role) {
            // Ensure they have all necessary editing capabilities
            $approver_role->add_cap('read');
            $approver_role->add_cap('edit_events');
            $approver_role->add_cap('edit_others_events');
            $approver_role->add_cap('edit_published_events');
            $approver_role->add_cap('edit_private_events');
            $approver_role->add_cap('publish_events');
            $approver_role->add_cap('delete_events');
            $approver_role->add_cap('delete_others_events');
            $approver_role->add_cap('delete_published_events');
            $approver_role->add_cap('delete_private_events');
            $approver_role->add_cap('read_private_events');
            $approver_role->add_cap('upload_files');
        }
    }

    // ----------------------------------------------------------------------
    // 3. Ensure 'editor' and 'administrator' roles have full event capabilities.
    // This is the fallback for calendars without an assigned approver.
    // ----------------------------------------------------------------------
    $editor_role = get_role('editor');
    if ($editor_role) {
        $editor_role->add_cap('edit_events');
        $editor_role->add_cap('read_events');
        $editor_role->add_cap('delete_events');
        $editor_role->add_cap('edit_others_events');
        $editor_role->add_cap('publish_events');
        $editor_role->add_cap('read_private_events');
        $editor_role->add_cap('delete_private_events');
        $editor_role->add_cap('delete_published_events');
    }
}

// ----------------------------------------------------------------------
// Optional: Cleanup
// Remove the roles on theme deactivation to keep the database clean.
// You would add this to your theme's deactivation hook if you have one.
// ----------------------------------------------------------------------
// register_deactivation_hook(__FILE__, 'remove_event_custom_roles');
//
// function remove_event_custom_roles() {
//     remove_role('event_submitter');
//     remove_role('event_approver');
// }

// Add calendar permissions fields to user profile
add_action('show_user_profile', 'add_calendar_permissions_fields');
add_action('edit_user_profile', 'add_calendar_permissions_fields');

function add_calendar_permissions_fields($user) {
    // Only show for users with event-related roles
    $user_roles = (array) $user->roles;
    $event_roles = array('event_submitter', 'event_approver', 'administrator', 'editor');
    
    if (!array_intersect($user_roles, $event_roles)) {
        return;
    }
    
    // Get all calendars
    $calendars = get_terms(array(
        'taxonomy' => 'event_caes_departments',
        'hide_empty' => false,
    ));
    
    if (is_wp_error($calendars) || empty($calendars)) {
        return;
    }
    
    // Get current permissions
    $submit_permissions = get_user_meta($user->ID, 'calendar_submit_permissions', true);
    $approve_permissions = get_user_meta($user->ID, 'calendar_approve_permissions', true);
    
    if (!is_array($submit_permissions)) {
        $submit_permissions = array();
    }
    if (!is_array($approve_permissions)) {
        $approve_permissions = array();
    }
    
    ?>
    <h3><?php _e('Event Calendar Permissions', 'caes-hub'); ?></h3>
    <table class="form-table">
        <tr>
            <th><label><?php _e('Calendar Access', 'caes-hub'); ?></label></th>
            <td>
                <fieldset>
                    <legend class="screen-reader-text"><?php _e('Calendar Permissions', 'caes-hub'); ?></legend>
                    
                    <table style="border-collapse: collapse; width: 100%;">
                        <thead>
                            <tr>
                                <th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">Calendar</th>
                                <th style="text-align: center; padding: 8px; border-bottom: 1px solid #ddd;">Can Submit</th>
                                <th style="text-align: center; padding: 8px; border-bottom: 1px solid #ddd;">Can Approve</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($calendars as $calendar): ?>
                            <tr>
                                <td style="padding: 8px; border-bottom: 1px solid #eee;">
                                    <strong><?php echo esc_html($calendar->name); ?></strong>
                                </td>
                                <td style="text-align: center; padding: 8px; border-bottom: 1px solid #eee;">
                                    <input type="checkbox" 
                                           name="calendar_submit_permissions[]" 
                                           value="<?php echo esc_attr($calendar->term_id); ?>"
                                           <?php checked(in_array($calendar->term_id, $submit_permissions)); ?> />
                                </td>
                                <td style="text-align: center; padding: 8px; border-bottom: 1px solid #eee;">
                                    <input type="checkbox" 
                                           name="calendar_approve_permissions[]" 
                                           value="<?php echo esc_attr($calendar->term_id); ?>"
                                           <?php checked(in_array($calendar->term_id, $approve_permissions)); ?> />
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <p class="description">
                        <?php _e('Select which calendars this user can submit events to and/or approve events for.', 'caes-hub'); ?>
                    </p>
                </fieldset>
            </td>
        </tr>
    </table>
    <?php
}

// Save calendar permissions
add_action('personal_options_update', 'save_calendar_permissions_fields');
add_action('edit_user_profile_update', 'save_calendar_permissions_fields');

function save_calendar_permissions_fields($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return;
    }
    
    // Save submit permissions
    $submit_permissions = isset($_POST['calendar_submit_permissions']) ? 
        array_map('intval', $_POST['calendar_submit_permissions']) : array();
    update_user_meta($user_id, 'calendar_submit_permissions', $submit_permissions);
    
    // Save approve permissions  
    $approve_permissions = isset($_POST['calendar_approve_permissions']) ? 
        array_map('intval', $_POST['calendar_approve_permissions']) : array();
    update_user_meta($user_id, 'calendar_approve_permissions', $approve_permissions);
}

/**
 * Helper function to check if user can submit to a calendar
 */
function user_can_submit_to_calendar($user_id, $calendar_term_id) {
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    
    $user_roles = (array) $user->roles;
    
    // Admins and editors can submit to all calendars
    if (in_array('administrator', $user_roles) || in_array('editor', $user_roles)) {
        return true;
    }
    
    // Check user's specific permissions
    $submit_permissions = get_user_meta($user_id, 'calendar_submit_permissions', true);
    if (!is_array($submit_permissions)) {
        $submit_permissions = array();
    }
    
    return in_array($calendar_term_id, $submit_permissions);
}

/**
 * Helper function to check if user can approve a calendar
 */
function user_can_approve_calendar($user_id, $calendar_term_id) {
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    
    $user_roles = (array) $user->roles;
    
    // Admins and editors can approve all calendars
    if (in_array('administrator', $user_roles) || in_array('editor', $user_roles)) {
        return true;
    }
    
    // Check if they're the assigned approver for this calendar (existing system)
    $assigned_approver = get_field('calendar_approver', 'event_caes_departments_' . $calendar_term_id);
    if ($assigned_approver && (int) $assigned_approver === (int) $user_id) {
        return true;
    }
    
    // Check user's specific approve permissions
    $approve_permissions = get_user_meta($user_id, 'calendar_approve_permissions', true);
    if (!is_array($approve_permissions)) {
        $approve_permissions = array();
    }
    
    return in_array($calendar_term_id, $approve_permissions);
}

/**
 * Get all calendars a user can submit to
 */
function get_user_submit_calendars($user_id) {
    $user = get_userdata($user_id);
    if (!$user) {
        return array();
    }
    
    $user_roles = (array) $user->roles;
    
    // Get all calendars
    $all_calendars = get_terms(array(
        'taxonomy' => 'event_caes_departments',
        'hide_empty' => false,
    ));
    
    if (is_wp_error($all_calendars)) {
        return array();
    }
    
    // Admins and editors can submit to all calendars
    if (in_array('administrator', $user_roles) || in_array('editor', $user_roles)) {
        return wp_list_pluck($all_calendars, 'term_id');
    }
    
    // For other users, check their permissions
    $submit_permissions = get_user_meta($user_id, 'calendar_submit_permissions', true);
    if (!is_array($submit_permissions)) {
        $submit_permissions = array();
    }
    
    return $submit_permissions;
}