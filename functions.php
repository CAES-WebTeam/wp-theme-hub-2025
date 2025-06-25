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

function test_save_single_post() {
    if (isset($_GET['test_save_post']) && current_user_can('manage_options')) {
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 5310;
        
        echo '<h3>Testing Single Post Save</h3>';
        echo 'Post ID: ' . $post_id . '<br>';
        echo 'Post Title: ' . get_the_title($post_id) . '<br><br>';
        
        // Check BEFORE save
        echo '<h4>BEFORE Save:</h4>';
        echo 'get_field result: '; var_dump(get_field('external_publisher', $post_id)); echo '<br>';
        echo 'get_post_meta result: '; var_dump(get_post_meta($post_id, 'external_publisher', true)); echo '<br><br>';
        
        // Re-save the post (this triggers ACF save hooks)
        $result = wp_update_post([
            'ID' => $post_id,
            'post_modified' => current_time('mysql'),
            'post_modified_gmt' => current_time('mysql', 1)
        ]);
        
        echo '<h4>Save Result:</h4>';
        if (is_wp_error($result)) {
            echo 'Error: ' . $result->get_error_message() . '<br>';
        } else {
            echo 'Success! Post updated.<br>';
        }
        echo '<br>';
        
        // Check AFTER save
        echo '<h4>AFTER Save:</h4>';
        echo 'get_field result: '; var_dump(get_field('external_publisher', $post_id)); echo '<br>';
        echo 'get_post_meta result: '; var_dump(get_post_meta($post_id, 'external_publisher', true)); echo '<br>';
        
        echo '<br><strong>Now go check your query block to see if this post shows the external publisher!</strong>';
        
        wp_die();
    }
}
add_action('init', 'test_save_single_post');