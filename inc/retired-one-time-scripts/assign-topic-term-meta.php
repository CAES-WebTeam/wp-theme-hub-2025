<?php
/**
 * Script to update 'topics' taxonomy term meta (ACF fields)
 * from a JSON file.
 *
 * IMPORTANT: This script is intended for one-time use.
 * After running it successfully, you can remove the menu item
 * or keep it for future manual re-runs if needed.
 */

// 1. Add a new menu item under 'Tools'
// add_action( 'admin_menu', 'register_topic_updater_admin_page' );

// function register_topic_updater_admin_page() {
//     add_management_page(
//         'Update Topic Meta',        // Page title
//         'Update Topic Meta',        // Menu title
//         'manage_options',           // Capability required to access
//         'topic-meta-updater',       // Menu slug
//         'render_topic_updater_page' // Callback function to render the page content
//     );
// }

// /**
//  * Renders the admin page content and handles the script execution.
//  */
// function render_topic_updater_page() {
//     // Ensure this script only runs for administrators
//     if ( ! current_user_can( 'manage_options' ) ) {
//         echo '<div class="notice notice-error"><p>You do not have permission to run this script.</p></div>';
//         return;
//     }

//     echo '<div class="wrap">';
//     echo '<h1>Update Topic Meta</h1>';
//     echo '<p>This tool will update the ACF fields (topic_id, type_id, is_active, is_internal) for your "topics" taxonomy terms based on data from your `json/terms.json` file.</p>';
//     echo '<p><strong>Warning:</strong> This is a one-time operation. Ensure your JSON file is correctly formatted before proceeding.</p>';

//     // Add a button to trigger the update
//     echo '<form method="post" action="">';
//     wp_nonce_field( 'run_topic_meta_update', 'topic_meta_update_nonce' );
//     echo '<input type="submit" name="run_update_script" class="button button-primary" value="Run Topic Meta Update">';
//     echo '</form>';

//     // Check if the update button was pressed and nonce is valid
//     if ( isset( $_POST['run_update_script'] ) && check_admin_referer( 'run_topic_meta_update', 'topic_meta_update_nonce' ) ) {
//         // Define the path to your JSON file
//         // Adjust this path if your JSON file is located elsewhere
//         $json_file_path = get_stylesheet_directory() . '/json/FrankelPubsKeywordsCorrectedJSON.json';

//         // Check if the JSON file exists
//         if ( ! file_exists( $json_file_path ) ) {
//             echo '<div class="notice notice-error"><p>Error: JSON file not found at ' . esc_html( $json_file_path ) . '</p></div>';
//             return;
//         }

//         // Read the JSON file content
//         $json_data = file_get_contents( $json_file_path );

//         // Remove BOM if present
//         $json_data = trim( $json_data ); // Trim whitespace, including potential BOM
//         if ( substr( $json_data, 0, 3 ) == pack( "CCC", 0xEF, 0xBB, 0xBF ) ) {
//             $json_data = substr( $json_data, 3 );
//         }

//         // Decode the JSON data
//         $term_entries = json_decode( $json_data, true );

//         // Check if JSON decoding was successful and it's an array
//         if ( ! is_array( $term_entries ) ) {
//             $json_error = json_last_error();
//             $json_error_msg = json_last_error_msg();
//             echo '<div class="notice notice-error"><p>Error: Could not decode JSON data or JSON is not an array. JSON Error (' . esc_html( $json_error ) . '): ' . esc_html( $json_error_msg ) . '</p></div>';
//             echo '<div class="notice notice-info"><p>Please check your `json/terms.json` file for syntax errors, ensure it\'s a valid JSON array, and verify file permissions.</p></div>';
//             return;
//         }

//         echo '<div class="notice notice-success"><p>Starting update of "topics" term meta from JSON...</p></div>';
//         echo '<ul>';

//         $updated_count = 0;
//         $skipped_count = 0;

//         foreach ( $term_entries as $entry ) {
//             // Ensure required keys exist in the JSON entry
//             if ( ! isset( $entry['ID'], $entry['LABEL'], $entry['TYPE_ID'], $entry['IS_ACTIVE'], $entry['IS_INTERNAL'] ) ) {
//                 echo '<li>Skipping entry due to missing required data: ' . esc_html( json_encode( $entry ) ) . '</li>';
//                 $skipped_count++;
//                 continue;
//             }

//             $json_id        = (int) $entry['ID'];
//             $json_label     = sanitize_text_field( $entry['LABEL'] );
//             $json_type_id   = (int) $entry['TYPE_ID'];
//             $json_is_active = (bool) $entry['IS_ACTIVE'];
//             $json_is_internal = (bool) $entry['IS_INTERNAL'];

//             // Find the term in the 'topics' taxonomy by its name (LABEL)
//             $term = get_term_by( 'name', $json_label, 'topics' );

//             if ( $term && ! is_wp_error( $term ) ) {
//                 $term_id = $term->term_id;

//                 // Update ACF fields for the term
//                 // Ensure ACF is active and update_field function exists
//                 if ( function_exists( 'update_field' ) ) {
//                     update_field( 'topic_id', $json_id, 'term_' . $term_id );
//                     update_field( 'type_id', $json_type_id, 'term_' . $term_id );
//                     update_field( 'is_active', $json_is_active, 'term_' . $term_id );
//                     update_field( 'is_internal', $json_is_internal, 'term_' . $term_id );

//                     echo '<li>Updated term: <strong>' . esc_html( $json_label ) . '</strong> (ID: ' . esc_html( $term_id ) . ') with JSON data.</li>';
//                     $updated_count++;
//                 } else {
//                     echo '<li>Error: ACF function update_field not found. Is ACF plugin active? Skipping term: ' . esc_html( $json_label ) . '</li>';
//                     $skipped_count++;
//                 }
//             } else {
//                 echo '<li>Skipped: No "topics" term found matching label: <strong>' . esc_html( $json_label ) . '</strong></li>';
//                 $skipped_count++;
//             }
//         }

//         echo '</ul>';
//         echo '<div class="notice notice-success"><p>Update complete. Total terms updated: ' . esc_html( $updated_count ) . '. Total skipped: ' . esc_html( $skipped_count ) . '.</p></div>';
//     }
//     echo '</div>'; // Close .wrap
// }
