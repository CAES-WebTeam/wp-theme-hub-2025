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


add_action('init', function () {
    if (!is_admin() && isset($_GET['trigger_save_posts']) && $_GET['trigger_save_posts'] == 1) {
        $post_type = 'post';

        $posts = get_posts([
            'post_type'      => $post_type,
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ]);

        foreach ($posts as $post_id) {
            wp_update_post(['ID' => $post_id]);
        }

        // Optional: show a simple message in the browser
        echo count($posts) . " posts for '{$post_type}' were updated.";
        exit;
    }
});


add_action('init', function () {
    if (!is_admin() && isset($_GET['trigger_save_pubs']) && $_GET['trigger_save_pubs'] == 1) {
        $post_type = 'publications';

        $posts = get_posts([
            'post_type'      => $post_type,
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ]);

        foreach ($posts as $post_id) {
            wp_update_post(['ID' => $post_id]);
        }

        // Optional: show a simple message in the browser
        echo count($posts) . " publications for '{$post_type}' were updated.";
        exit;
    }
});