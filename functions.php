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

function find_real_field_names() {
    if (isset($_GET['find_fields']) && current_user_can('manage_options')) {
        $post_id = 5310;
        
        echo '<h3>All ACF Fields for Post ' . $post_id . ':</h3>';
        
        // Get all fields for this post
        $fields = get_fields($post_id);
        echo '<h4>get_fields() result:</h4>';
        if ($fields) {
            foreach ($fields as $field_name => $field_value) {
                echo 'Field: ' . $field_name . ' = '; var_dump($field_value); echo '<br>';
            }
        } else {
            echo 'No fields found with get_fields()<br>';
        }
        
        // Try to get field objects for common variations
        echo '<h4>Testing Field Name Variations:</h4>';
        $variations = [
            'external_publisher',
            'external-publisher', 
            'external_publishers',
            'publisher',
            'publishers'
        ];
        
        foreach ($variations as $variation) {
            $field_obj = get_field_object($variation, $post_id);
            if ($field_obj) {
                echo 'FOUND: ' . $variation . ' - Type: ' . $field_obj['type'] . '<br>';
                echo 'Value: '; var_dump($field_obj['value']); echo '<br>';
            } else {
                echo 'Not found: ' . $variation . '<br>';
            }
        }
        
        // Check what's actually selected in admin by trying all meta
        echo '<h4>All Post Meta (including field references):</h4>';
        $all_meta = get_post_meta($post_id);
        foreach ($all_meta as $key => $value) {
            echo $key . ': '; 
            if (is_array($value) && count($value) == 1) {
                echo $value[0];
            } else {
                var_dump($value);
            }
            echo '<br>';
        }
        
        wp_die('Field discovery complete');
    }
}
add_action('init', 'find_real_field_names');