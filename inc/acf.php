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
		'master', 'ccampbell', 'caeswp', 'ashley'
	);

	// get the current user
	$current_user = wp_get_current_user();

	return (in_array($current_user->user_login, $admins));
}

// Include ACF
include_once( get_template_directory() . '/inc/acf/acf.php' );


?>