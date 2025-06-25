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

function debug_specific_post() {
    if (isset($_GET['debug_post']) && current_user_can('manage_options')) {
        $post_id = 5310; // The post that should have UGA Today
        
        echo '<h3>Post 5310 Debug:</h3>';
        echo '<strong>Post Title:</strong> ' . get_the_title($post_id) . '<br>';
        
        // Check all meta for this post
        echo '<h4>All Post Meta:</h4>';
        $all_meta = get_post_meta($post_id);
        foreach ($all_meta as $key => $value) {
            if (strpos($key, 'external') !== false || strpos($key, 'publisher') !== false) {
                echo $key . ': '; var_dump($value); echo '<br>';
            }
        }
        
        // Check if any external_publisher meta exists at all
        echo '<h4>External Publisher Checks:</h4>';
        echo 'external_publisher: '; var_dump(get_post_meta($post_id, 'external_publisher', true)); echo '<br>';
        echo 'external_publisher (all): '; var_dump(get_post_meta($post_id, 'external_publisher')); echo '<br>';
        echo '_external_publisher: '; var_dump(get_post_meta($post_id, '_external_publisher', true)); echo '<br>';
        
        // ACF field check
        echo 'ACF get_field: '; var_dump(get_field('external_publisher', $post_id)); echo '<br>';
        
        wp_die('Debug complete');
    }
}
add_action('init', 'debug_specific_post');