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

// Add targeted attachment editing for Event Approvers
add_filter('map_meta_cap', 'allow_event_approver_attachment_editing', 10, 4);

function allow_event_approver_attachment_editing($caps, $cap, $user_id, $args) {
    // Only apply to edit_post capability for attachments
    if ($cap !== 'edit_post' || empty($args) || !isset($args[0])) {
        return $caps;
    }
    
    $post_id = $args[0];
    $post = get_post($post_id);
    
    // Only apply to attachment posts
    if (!$post || $post->post_type !== 'attachment') {
        return $caps;
    }
    
    $user = get_userdata($user_id);
    if (!$user) {
        return $caps;
    }
    
    $user_roles = (array) $user->roles;
    
    // Only apply to Event Approvers (admins/editors already have access)
    if (!in_array('event_approver', $user_roles)) {
        return $caps;
    }
    
    // Check multiple ways this attachment might be used in events
    $events_using_attachment = get_posts(array(
        'post_type' => 'events',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => array(
            'relation' => 'OR',
            // Method 1: Direct ID match
            array(
                'key' => 'featured_image',
                'value' => $post_id,
                'compare' => '='
            ),
            // Method 2: Array format match (for ACF arrays)
            array(
                'key' => 'featured_image',
                'value' => '"' . $post_id . '"',
                'compare' => 'LIKE'
            ),
            // Method 3: Serialized format match  
            array(
                'key' => 'featured_image',
                'value' => 'i:' . $post_id . ';',
                'compare' => 'LIKE'
            )
        )
    ));
    
    if (!empty($events_using_attachment)) {
        foreach ($events_using_attachment as $event_id) {
            // Check if user can approve any calendar for this event
            $event_calendars = get_the_terms($event_id, 'event_caes_departments');
            if ($event_calendars && !is_wp_error($event_calendars)) {
                foreach ($event_calendars as $calendar) {
                    if (user_can_approve_calendar($user_id, $calendar->term_id)) {
                        // User can approve this event, so allow attachment editing
                        return array();
                    }
                }
            }
        }
    }
    
    // If we get here, user shouldn't be able to edit this attachment
    return $caps;
}

// Hide admin menu items from Event Approvers
add_action('admin_menu', 'hide_admin_menus_from_event_approvers', 999);

// Replace the hide_admin_menus_from_event_approvers function with this whitelist approach
function hide_admin_menus_from_event_approvers() {
    $current_user = wp_get_current_user();
    $user_roles = (array) $current_user->roles;
    
    // Only apply to Event Approvers
    if (in_array('event_approver', $user_roles) && !in_array('administrator', $user_roles)) {
        
        // Get all menu items
        global $menu, $submenu;
        
        // Define what they CAN see (whitelist)
        $allowed_menus = array(
            'index.php',                    // Dashboard
            'edit.php?post_type=events',    // Events
            'profile.php'                   // Profile (so they can edit their own profile)
        );
        
        // Remove all menu items except the allowed ones
        foreach ($menu as $key => $menu_item) {
            $menu_file = $menu_item[2];
            
            if (!in_array($menu_file, $allowed_menus)) {
                remove_menu_page($menu_file);
            }
        }
        
        // Remove unwanted dashboard widgets
        remove_meta_box('dashboard_right_now', 'dashboard', 'normal');
        remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
        remove_meta_box('dashboard_incoming_links', 'dashboard', 'normal');
        remove_meta_box('dashboard_plugins', 'dashboard', 'normal');
    }
}

// Restrict post type access in admin
add_action('load-edit.php', 'restrict_post_type_access_for_event_approvers');
add_action('load-post.php', 'restrict_post_type_access_for_event_approvers');
add_action('load-post-new.php', 'restrict_post_type_access_for_event_approvers');

function restrict_post_type_access_for_event_approvers() {
    $current_user = wp_get_current_user();
    $user_roles = (array) $current_user->roles;
    
    // Only apply to Event Approvers
    if (in_array('event_approver', $user_roles) && !in_array('administrator', $user_roles)) {
        global $typenow;
        
        // Allow access only to events
        $allowed_post_types = array('events');
        
        if (!in_array($typenow, $allowed_post_types)) {
            wp_die('You do not have permission to access this area.');
        }
    }
}

// Hide other post types from the admin bar "New" menu
add_action('admin_bar_menu', 'hide_admin_bar_new_items_for_event_approvers', 999);

function hide_admin_bar_new_items_for_event_approvers($wp_admin_bar) {
    $current_user = wp_get_current_user();
    $user_roles = (array) $current_user->roles;
    
    // Only apply to Event Approvers
    if (in_array('event_approver', $user_roles) && !in_array('administrator', $user_roles)) {    
        $nodes = $wp_admin_bar->get_nodes();
        foreach ($nodes as $node) {
            if (strpos($node->id, 'new-') === 0 && $node->id !== 'new-events') {
                $wp_admin_bar->remove_node($node->id);
            }
        }
    }
}

// Redirect Event Approvers if they try to access restricted areas directly
add_action('current_screen', 'redirect_event_approvers_from_restricted_areas');

function redirect_event_approvers_from_restricted_areas($current_screen) {
    $current_user = wp_get_current_user();
    $user_roles = (array) $current_user->roles;
    
    // Only apply to Event Approvers
    if (in_array('event_approver', $user_roles) && !in_array('administrator', $user_roles)) {
        
        // Define allowed screens (whitelist)
        $allowed_screens = array(
            'dashboard',
            'events',
            'edit-events', 
            'profile',
            'profile-network' // For multisite
        );
        
        // If current screen is not in allowed list, redirect to events
        if (!in_array($current_screen->id, $allowed_screens)) {
            wp_redirect(admin_url('edit.php?post_type=events'));
            exit;
        }
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

function add_calendar_permissions_fields($user)
{
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

function save_calendar_permissions_fields($user_id)
{
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
function user_can_submit_to_calendar($user_id, $calendar_term_id)
{
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
function user_can_approve_calendar($user_id, $calendar_term_id)
{
    $user = get_userdata($user_id);
    if (!$user) {
        // error_log("DEBUG user_can_approve_calendar: User ID $user_id not found");
        return false;
    }

    $user_roles = (array) $user->roles;
    // error_log("DEBUG user_can_approve_calendar: User $user_id roles: " . implode(', ', $user_roles));

    // Admins and editors can approve all calendars
    if (in_array('administrator', $user_roles) || in_array('editor', $user_roles)) {
        // error_log("DEBUG user_can_approve_calendar: User $user_id is admin/editor - APPROVED");
        return true;
    }

    // Check user's specific approve permissions
    $approve_permissions = get_user_meta($user_id, 'calendar_approve_permissions', true);
    if (!is_array($approve_permissions)) {
        $approve_permissions = array();
    }

    // error_log("DEBUG user_can_approve_calendar: User $user_id approve permissions: " . print_r($approve_permissions, true));
    // error_log("DEBUG user_can_approve_calendar: Checking calendar term ID: $calendar_term_id");

    $can_approve = in_array($calendar_term_id, $approve_permissions);
    // error_log("DEBUG user_can_approve_calendar: Can approve result: " . ($can_approve ? 'YES' : 'NO'));

    return $can_approve;
}

/**
 * Get all calendars a user can submit to
 */
function get_user_submit_calendars($user_id)
{
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