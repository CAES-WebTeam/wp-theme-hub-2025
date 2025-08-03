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
