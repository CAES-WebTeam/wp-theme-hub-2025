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
        $updated = 0;

        foreach ($posts as $post_id) {
            $updated_fields = [];

            // Handle "authors" repeater
            $authors = get_field('authors', $post_id);
            if ($authors && is_array($authors)) {
                foreach ($authors as $i => &$row) {
                    $row['type'] = 'User';
                }
                update_field('authors', $authors, $post_id);
                $updated_fields[] = 'authors';
            }

            // Handle "experts" repeater
            $experts = get_field('experts', $post_id);
            if ($experts && is_array($experts)) {
                foreach ($experts as $i => &$row) {
                    $row['type'] = 'User';
                }
                update_field('experts', $experts, $post_id);
                $updated_fields[] = 'experts';
            }

            if (!empty($updated_fields)) {
                clean_post_cache($post_id);
                ++$updated;
            }
        }

        echo "{$updated} '{$post_type}' posts had 'authors' and/or 'experts' type fields updated to 'User'.";
        exit;
    }
});