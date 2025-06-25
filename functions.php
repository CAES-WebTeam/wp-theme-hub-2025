<?php
/*
 *  Author: UGA - CAES OIT, Frankel Agency
 *  URL: hub.caes.uga.edu
 *  Custom functions, support, custom post types and more.
 */

/*------------------------------------*\
	Load files
\*------------------------------------*/

require get_template_directory() . '/inc/theme-support.php';
require get_template_directory() . '/inc/post-types.php';
require get_template_directory() . '/inc/blocks.php';
require get_template_directory() . '/inc/acf.php';
require get_template_directory() . '/inc/events-support.php';
require get_template_directory() . '/inc/publications-support.php';
require get_template_directory() . '/inc/user-support.php';
require get_template_directory() . '/inc/news-support.php';
require get_template_directory() . '/block-variations/index.php';


// Temp include
require get_template_directory() . '/inc/release-date-migration.php';
require get_template_directory() . '/inc/release-date-clear.php';
require get_template_directory() . '/inc/detect-duplicates.php';
require get_template_directory() . '/inc/import-legacy-slideshows-to-news.php';
require get_template_directory() . '/inc/link-users.php';

function debug_acf_field_config() {
    if (isset($_GET['debug_acf_field']) && current_user_can('manage_options')) {
        $post_id = 5310;
        
        echo '<h3>ACF Field Configuration Debug:</h3>';
        
        // Get ACF field object
        $field_object = get_field_object('external_publisher', $post_id);
        echo '<h4>Field Object:</h4>';
        if ($field_object) {
            echo 'Field Key: ' . $field_object['key'] . '<br>';
            echo 'Field Name: ' . $field_object['name'] . '<br>';
            echo 'Field Type: ' . $field_object['type'] . '<br>';
            echo 'Return Format: ' . $field_object['return_format'] . '<br>';
            echo 'Save Terms: ' . ($field_object['save_terms'] ? 'Yes' : 'No') . '<br>';
            echo 'Load Terms: ' . ($field_object['load_terms'] ? 'Yes' : 'No') . '<br>';
            echo 'Value: '; var_dump($field_object['value']); echo '<br>';
        } else {
            echo 'Field object not found!<br>';
        }
        
        // Check all meta keys that might be related
        echo '<h4>All Meta Keys (filtered):</h4>';
        $all_meta = get_post_meta($post_id);
        foreach ($all_meta as $key => $value) {
            if (strpos($key, 'external') !== false || strpos($key, 'publisher') !== false || strpos($key, 'field_') !== false) {
                echo $key . ': '; 
                if (is_array($value) && count($value) == 1) {
                    var_dump($value[0]);
                } else {
                    var_dump($value);
                }
                echo '<br>';
            }
        }
        
        // Try to find the field by different methods
        echo '<h4>Alternative Field Retrieval:</h4>';
        
        // Try with field key if we found one
        if ($field_object && isset($field_object['key'])) {
            $field_key = $field_object['key'];
            echo 'Using field key ' . $field_key . ': '; 
            var_dump(get_field($field_key, $post_id)); 
            echo '<br>';
        }
        
        wp_die('ACF Debug complete');
    }
}
add_action('init', 'debug_acf_field_config');