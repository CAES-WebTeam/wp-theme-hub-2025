<?php
/**
 * events-approver-assignment.php
 *
 * This file handles helper functions to retrieve approvers for events.
 * NOTE: This version ONLY uses the user permission system.
 * No ACF fields are created for individual calendar assignment.
 *
 * @package YourThemeName/Events
 */

/**
 * Helper function to retrieve the approver user IDs for a given event post.
 *
 * This function gets all users who have approval permissions for the 
 * calendars associated with this event.
 *
 * @param int $post_id The ID of the event post.
 * @return array An array of unique user IDs of all designated approvers.
 */
function get_event_approvers_for_post( $post_id ) {
    $approvers = array();
    $event_calendars = get_the_terms( $post_id, 'event_caes_departments' );

    if ( ! $event_calendars || is_wp_error( $event_calendars ) ) {
        return array();
    }

    foreach ( $event_calendars as $term ) {
        // Get users who have approval permissions for this calendar
        $users_with_approve_permission = get_users(array(
            'meta_query' => array(
                array(
                    'key' => 'calendar_approve_permissions',
                    'value' => $term->term_id,
                    'compare' => 'LIKE'
                )
            ),
            'fields' => 'ID'
        ));
        
        if (!empty($users_with_approve_permission)) {
            $approvers = array_merge($approvers, $users_with_approve_permission);
        }
    }

    return array_unique( $approvers );
}