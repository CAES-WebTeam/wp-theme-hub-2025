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
	if (!is_admin() || !isset($_GET['reassign_carousel'])) return;

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

	$updated = 0;

	foreach ($records as $record) {
		$story_id   = $record['STORY_ID'] ?? null;
		$image_data = $record['IMAGE'][0] ?? null;

		if (!$story_id || !$image_data) continue;

		$filename = $image_data['WEB_VERSION_FILE_NAME'] ?? '';
		$alt      = $image_data['IMAGE_LABEL'] ?? '';
		$caption  = $image_data['DESCRIPTION'] ?? '';

		if (!$filename) continue;

		// Find post with matching ACF 'story_id'
		$posts = get_posts([
			'post_type'      => 'publications',
			'post_status'    => ['publish', 'draft', 'pending', 'future', 'private'],
			'meta_key'       => 'id',
			'meta_value'     => $story_id,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		]);

		if (empty($posts)) continue;
		$post_id = $posts[0];

		// Find attachment by filename
		$attachments = get_posts([
			'post_type'      => 'attachment',
			'posts_per_page' => 1,
			'name'           => sanitize_title(pathinfo($filename, PATHINFO_FILENAME)),
			'post_status'    => 'inherit',
			'fields'         => 'ids',
		]);

		if (empty($attachments)) continue;
		$attach_id = $attachments[0];

		// Append to carousel if not already assigned
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

	wp_die("Carousel reassignment complete. {$updated} images assigned.");
});
