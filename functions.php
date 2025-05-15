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


add_action('admin_init', function () {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    if (!current_user_can('manage_options') || !isset($_GET['import_inline_images'])) return;


    $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;

    $all_posts = get_posts([
        'post_type'      => 'publications',
        'post_status'    => 'any',
        'fields'         => 'all',
        'orderby'        => 'ID',
        'order'          => 'ASC',
        'posts_per_page' => -1,
    ]);

    $batch = array_slice($all_posts, $start, $limit);
    $updated = 0;

    foreach ($batch as $post) {
        $original_content = $post->post_content;
        $content = $original_content;

        // Remove unwanted characters and entities
        $content = str_replace(
            ["\r\n", "\r", '&#13;', '&#013;', '&amp;#13;', '&#x0D;', '&#x0d;'],
            '',
            $content
        );

        // Match all <img src="..."> values
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $image_url) {
                // Skip local or already-uploaded images
                if (
                    strpos($image_url, home_url()) !== false ||
                    strpos($image_url, '/wp-content/uploads/') !== false
                ) continue;

                $tmp = download_url($image_url);
                if (is_wp_error($tmp)) continue;

                $file_array = [
                    'name'     => basename(parse_url($image_url, PHP_URL_PATH)),
                    'tmp_name' => $tmp,
                ];

                $attachment_id = media_handle_sideload($file_array, $post->ID);

                if (is_wp_error($attachment_id)) {
                    @unlink($tmp);
                    continue;
                }

                $new_url = wp_get_attachment_url($attachment_id);
                if ($new_url) {
                    $content = str_replace($image_url, $new_url, $content);
                }
            }
        }

        // Update post only if content has changed
        if ($content !== $original_content) {
            wp_update_post([
                'ID'           => $post->ID,
                'post_content' => $content,
            ]);
            $updated++;
        }
    }

    wp_die("Processed posts {$start} to " . ($start + $limit - 1) . ". Updated {$updated} posts (images or character cleanup).");
});
