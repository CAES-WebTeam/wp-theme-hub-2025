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

// ...
add_action('init', function () {
	if (!is_admin() || !isset($_GET['reassign_carousel'])) return;

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
	$updated = 0;

	foreach ($batch as $record) {
		$story_id   = $record['STORY_ID'] ?? null;
		$image_data = $record['IMAGE'][0] ?? null;

		if (!$story_id || !$image_data) continue;

		$filename = $image_data['WEB_VERSION_FILE_NAME'] ?? '';
		$alt      = $image_data['IMAGE_LABEL'] ?? '';
		$caption  = $image_data['DESCRIPTION'] ?? '';

		if (!$filename) continue;

		// Find post by ACF story_id
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
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'name'           => sanitize_title(pathinfo($filename, PATHINFO_FILENAME)),
			'fields'         => 'ids',
		]);

		if (empty($attachments)) continue;
		$attach_id = $attachments[0];

		// Skip if already in carousel
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

	wp_die("Reassigned {$updated} carousel images. Batch {$start} to " . ($start + $limit - 1) . ".");
});
