<?php 

/*ACF*/

// Set ACF path
if(!function_exists("caes_hub_acf_settings_path")) {
	add_filter('acf/settings/path', 'caes_hub_acf_settings_path');

	function caes_hub_acf_settings_path( $path ) {
		$path = get_template_directory() . '/inc/acf/';
		return $path;
	}
}
	 

// Set ACF dir
if(!function_exists("caes_hub_acf_settings_dir")) {
	add_filter('acf/settings/dir', 'caes_hub_acf_settings_dir');
	 
	function caes_hub_acf_settings_dir( $dir ) {
		$dir = get_template_directory_uri() . '/inc/acf/';
		return $dir;
	}
}
 

// Hide ACF menu item
add_filter('acf/settings/show_admin', 'caes_hub_acf_show_admin');
function caes_hub_acf_show_admin($show) {
	// This is a list of usernames who can view ACF menu item
	$admins = array( 
		'master', 'caeswp', 'aaw97657', 'ashley'
	);

	// get the current user
	$current_user = wp_get_current_user();

	return (in_array($current_user->user_login, $admins));
}

// Hide the legacy fields tab for non-admins
add_filter('acf/prepare_field', 'hide_specific_fields_from_non_admins');
function hide_specific_fields_from_non_admins($field) {
    // Only show these fields to admins
    if (!current_user_can('manage_options')) {
        $hidden_fields = [
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
        ];

        if (in_array($field['name'], $hidden_fields)) {
            return false;
        }
    }

    return $field;
}

// Include ACF
include_once( get_template_directory() . '/inc/acf/acf.php' );


?>