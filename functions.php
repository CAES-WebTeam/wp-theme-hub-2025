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
    if (!is_admin() || !isset($_GET['assign_carousel_images'])) return;

    $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;

    $json_path = get_stylesheet_directory() . '/json/FrankelNewsImagesJoined.json';
    if (!file_exists($json_path)) {
        wp_die('JSON file not found.');
    }

    $json_data = file_get_contents($json_path);
    $json_data = preg_replace('/^\xEF\xBB\xBF/', '', $json_data);
    $records = json_decode($json_data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_die('JSON decode error: ' . json_last_error_msg());
    }

    $batch = array_slice($records, $start, $limit);

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $uploaded_cache = [];
    $updated = 0;

    foreach ($batch as $record) {
        $story_id   = $record['STORY_ID'] ?? null;
        $image_id   = $record['IMAGE_ID'] ?? null;
        $image_data = $record['IMAGE'][0] ?? null;

        if (!$story_id || !$image_id || !$image_data) continue;

        $filename = $image_data['WEB_VERSION_FILE_NAME'] ?? '';
        $alt      = $image_data['IMAGE_LABEL'] ?? '';
        $caption  = $image_data['DESCRIPTION'] ?? '';

        if (!$filename) continue;

        $image_url = "https://secure.caes.uga.edu/news/multimedia/images/{$image_id}/{$filename}";

        // Find matching publication post by ACF 'story_id'
        $posts = get_posts([
            'post_type'        => 'post',
            'post_status'      => ['publish', 'draft', 'pending', 'future', 'private'],
            'meta_key'         => 'id',
            'meta_value'       => $story_id,
            'posts_per_page'   => 1,
            'fields'           => 'ids',
            'suppress_filters' => false,
        ]);

        if (empty($posts)) continue;
        $post_id = $posts[0];

        // Check if image already uploaded (globally)
        $media_key = md5($image_url);
        if (!isset($uploaded_cache[$media_key])) {
            $tmp = download_url($image_url);
            if (is_wp_error($tmp)) continue;

            $file_array = [
                'name'     => basename($filename),
                'tmp_name' => $tmp,
            ];

            $attach_id = media_handle_sideload($file_array, $post_id);
            if (is_wp_error($attach_id)) {
                @unlink($tmp);
                continue;
            }

            wp_update_post([
                'ID'            => $attach_id,
                'post_excerpt'  => $caption,
                'post_content'  => '',
            ]);
            update_post_meta($attach_id, '_wp_attachment_image_alt', $alt);

            $uploaded_cache[$media_key] = $attach_id;
        } else {
            $attach_id = $uploaded_cache[$media_key];
        }

        // Append to carousel if not already added to this post
        $carousel = get_field('carousel', $post_id) ?: [];
        $image_ids = array_column($carousel, 'image');

        if (!in_array($attach_id, $image_ids)) {
            $carousel[] = [
                'image'   => $attach_id,
                'caption' => $caption,
                'alt'     => $alt,
            ];
            update_field('carousel', $carousel, $post_id);
            $updated++;
        }
    }

    wp_die("Processed {$updated} images. Batch {$start} to " . ($start + $limit - 1) . " complete.");
});
