<?php
/**
 * events-approver-assignment.php
 *
 * This file handles the creation of an Advanced Custom Fields (ACF) field
 * group to assign an "Event Approver" to each term in the
 * 'event_caes_departments' taxonomy. It also includes a helper function to
 * retrieve the approvers for a specific event post.
 *
 * It is designed to be included in your main functions.php file or a main
 * events handler file within your theme's 'events' directory.
 *
 * @package YourThemeName/Events
 */

// We must check if ACF is active before we try to use its functions.
if ( function_exists('acf_add_local_field_group') ) {

    // Register the ACF field group on the 'init' hook.
    add_action('init', 'register_calendar_approver_field_group');

    /**
     * Registers the ACF field group for the 'event_caes_departments' taxonomy.
     *
     * This field group adds a single 'User' field to each calendar term,
     * allowing a user with the 'Event Approver' role to be selected as
     * the approver for that calendar.
     */
    function register_calendar_approver_field_group() {
        acf_add_local_field_group(array(
            'key' => 'group_caes_hub_event_approver', // Unique key for the field group
            'title' => __('Calendar Approver Settings', 'caes-hub'),
            'fields' => array(
                array(
                    'key' => 'field_caes_hub_event_approver', // Unique key for the field
                    'label' => __('Assigned Approver', 'caes-hub'),
                    'name' => 'calendar_approver', // Field name
                    'type' => 'user', // The field type is 'User'
                    'instructions' => __('Select the user who will approve events submitted to this calendar. If left empty, site Administrators and Editors will be the default approvers.', 'caes-hub'),
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ),
                    'return_format' => 'id', // We only need the user ID
                    'post_type' => '',
                    'taxonomy' => '',
                    'allow_null' => 1,
                    'multiple' => 0,
                    'roles' => array(
                        0 => 'event_approver', // Only users with this role can be selected
                        1 => 'administrator', // Administrators can also be selected
                        2 => 'editor', // Editors can also be selected
                    ),
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'taxonomy',
                        'operator' => '==',
                        'value' => 'event_caes_departments', // Attach this field to the 'event_caes_departments' taxonomy
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
            'active' => 1,
            'description' => '',
            'show_in_rest' => 0,
        ));
    }
}

/**
 * Helper function to retrieve the approver user IDs for a given event post.
 *
 * This function handles the logic for multiple calendars and the fallback to
 * admin/editor roles if no approver is assigned to a calendar term.
 *
 * @param int $post_id The ID of the event post.
 * @return array An array of unique user IDs of all designated approvers.
 */
function get_event_approvers_for_post( $post_id ) {
    $approvers = array();

    // Get the terms (calendars) selected for the event.
    $event_calendars = get_the_terms( $post_id, 'event_caes_departments' );

    // If there are no calendars selected, we can't determine approvers.
    if ( ! $event_calendars || is_wp_error( $event_calendars ) ) {
        return array();
    }

    // Loop through each selected calendar.
    foreach ( $event_calendars as $term ) {
        // First check for assigned approver (existing ACF system)
        $assigned_approver = get_field('calendar_approver', 'event_caes_departments_' . $term->term_id);

        if ( $assigned_approver ) {
            // If an approver is assigned, add their ID to our list.
            $approvers[] = $assigned_approver;
        } else {
            // Check for users with permission to approve this calendar
            $users_with_approve_permission = get_users(array(
                'meta_query' => array(
                    array(
                        'key' => 'calendar_approve_permissions',
                        'value' => '"' . $term->term_id . '"',
                        'compare' => 'LIKE'
                    )
                ),
                'fields' => 'ID'
            ));
            
            if (!empty($users_with_approve_permission)) {
                $approvers = array_merge($approvers, $users_with_approve_permission);
            } else {
                // Fallback: If no approver is assigned and no permission-based approvers, use all Editors and Administrators.
                // Get all users with the 'editor' role.
                $editors = get_users( array( 'role' => 'editor', 'fields' => 'ID' ) );
                $approvers = array_merge( $approvers, $editors );

                // Get all users with the 'administrator' role.
                $admins = get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) );
                $approvers = array_merge( $approvers, $admins );
            }
        }
    }

    // Return only unique user IDs to avoid sending multiple notifications to the same person.
    return array_unique( $approvers );
}
