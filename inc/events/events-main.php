<?php
/**
 * events-main.php
 *
 * This is the main handler file for the custom Events approval system.
 * It includes all the individual files required for the system to function.
 *
 * To activate the system, include this file in your theme's functions.php.
 *
 * @package YourThemeName/Events
 */

// Ensure this file is not accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get the path to the 'events' directory.
$events_dir = get_template_directory() . '/inc/events/';

// Include the individual files for our custom event system.
if ( file_exists( $events_dir . 'events-roles.php' ) ) {
    require_once( $events_dir . 'events-roles.php' );
}

if ( file_exists( $events_dir . 'events-approver-assignment.php' ) ) {
    require_once( $events_dir . 'events-approver-assignment.php' );
}

if ( file_exists( $events_dir . 'events-approval-workflow.php' ) ) {
    require_once( $events_dir . 'events-approval-workflow.php' );
}

if ( file_exists( $events_dir . 'events-approval-metabox.php' ) ) {
    require_once( $events_dir . 'events-approval-metabox.php' );
}

if ( file_exists( $events_dir . 'events-approval-admin.php' ) ) {
    require_once( $events_dir . 'events-approval-admin.php' );
}

if ( file_exists( $events_dir . 'events-frontend-queries.php' ) ) {
    require_once( $events_dir . 'events-frontend-queries.php' );
}

if ( file_exists( $events_dir . 'events-rest-api.php' ) ) {
    require_once( $events_dir . 'events-rest-api.php' );
}