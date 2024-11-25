<?php

// Load ACF Field Groups
include_once( get_template_directory() . '/inc/acf-fields/publications-field-group.php' );

// Set ACF field 'state_issue' with options from json
function populate_acf_state_issue_field( $field ) {
	// Set path to json file
	$json_file = get_template_directory() . '/json/publication-state-issue.json';

	if ( file_exists( $json_file ) ) {
		// Get the contents of the json file
		$json_data = file_get_contents( $json_file );
		$locations = json_decode( $json_data, true );

		// Clear existing choices
		$field['choices'] = array();

		// Check if there are issues in the json
		if ( isset( $issues['issues'] ) && is_array( $issues['issues'] ) ) {
			// Loop through the locations and add each name as a select option
			foreach ( $issues['issues'] as $issue ) {
				if ( isset( $issue['name'] ) ) {
					$field['choices'][ sanitize_text_field( $issue['name'] ) ] = sanitize_text_field( $issue['name'] );
				}
			}
		}
	}

	// Return the field to ACF
	return $field;
}
add_filter( 'acf/load_field/name=state_issue', 'populate_acf_statue_issue_field' );
