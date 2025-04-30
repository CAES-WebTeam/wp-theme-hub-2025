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

        $post_type = isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : 'post';

        $start = isset($_GET['start_date']) ? DateTime::createFromFormat('mdY', $_GET['start_date']) : false;
        $end   = isset($_GET['end_date']) ? DateTime::createFromFormat('mdY', $_GET['end_date']) : false;

        $args = [
            'post_type'      => $post_type,
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ];

        if ($start && $end) {
            $args['date_query'] = [
                [
                    'after'     => $start->format('Y-m-d'),
                    'before'    => $end->format('Y-m-d'),
                    'inclusive' => true,
                ],
            ];
        }

        $posts = get_posts($args);

        foreach ($posts as $post_id) {
            wp_update_post(['ID' => $post_id]);
        }

        echo count($posts) . " '{$post_type}' posts updated between " .
             ($start ? $start->format('Y-m-d') : 'any') . " and " .
             ($end ? $end->format('Y-m-d') : 'any') . ".";
        exit;
    }
});