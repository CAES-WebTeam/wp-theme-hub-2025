<?php

/*** Sync Keywords to News Story  ***/
// add_action('admin_init', function () {
//     if (!current_user_can('manage_options') || !isset($_GET['associate_keywords_to_posts'])) return;

//     $json_file = get_template_directory() . '/json/NEWS_ASSOCIATION_STORY_KEYWORD.json';

//     if (!file_exists($json_file)) {
//         wp_die('JSON file not found.');
//     }

//     // Read and clean
//     $json_data = file_get_contents($json_file);
//     $json_data = preg_replace('/^\xEF\xBB\xBF/', '', $json_data);
//     $json_data = mb_convert_encoding($json_data, 'UTF-8', 'UTF-8');
//     $records = json_decode($json_data, true);

//     if (json_last_error() !== JSON_ERROR_NONE) {
//         wp_die('JSON decode error: ' . json_last_error_msg());
//     }

//     $linked = 0;

//     foreach ($records as $pair) {
//         $story_id = intval($pair['STORY_ID']);
//         $topic_id = intval($pair['KEYWORD_ID']);

//         // Find the post by ACF field "id"
//         $posts = get_posts([
//             'post_type' => 'any',
//             'meta_key' => 'id',
//             'meta_value' => $story_id,
//             'numberposts' => 1,
//             'fields' => 'ids',
//         ]);

//         if (empty($posts)) continue;
//         $post_id = $posts[0];

//         // Find term in 'topics' taxonomy by ACF field "keyword_id"
//         $terms = get_terms([
//             'taxonomy' => 'topics', // Changed from 'keywords' to 'topics'
//             'hide_empty' => false,
//             'meta_query' => [[
//                 'key' => 'topic_id',
//                 'value' => $topic_id,
//                 'compare' => '='
//             ]]
//         ]);

//         if (empty($terms) || is_wp_error($terms)) continue;
//         $term_id = $terms[0]->term_id;

//         // Get current terms
//         $existing_terms = wp_get_object_terms($post_id, 'topics', ['fields' => 'ids']); // Changed from 'keywords' to 'topics'

//         // Prevent duplicates
//         if (!in_array($term_id, $existing_terms)) {
//             $existing_terms[] = $term_id;
//             wp_set_object_terms($post_id, $existing_terms, 'topics'); // Changed from 'keywords' to 'topics'
//             $linked++;
//         }
//     }

//     wp_die("Topic linking complete. Topics linked to posts: {$linked}"); // Updated message
// });


// // News Image association
// add_action('admin_init', function () {
//     if (!current_user_can('manage_options') || !isset($_GET['link_story_images'])) return;

//     $json_file = get_template_directory() . '/json/news-image-association.json';

//     if (!file_exists($json_file)) {
//         wp_die('JSON file not found.');
//     }

//     $json_data = file_get_contents($json_file);
//     $json_data = preg_replace('/^\xEF\xBB\xBF/', '', $json_data);
//     $json_data = mb_convert_encoding($json_data, 'UTF-8', 'UTF-8');
//     $records = json_decode($json_data, true);

//     if (json_last_error() !== JSON_ERROR_NONE) {
//         wp_die('JSON decode error: ' . json_last_error_msg());
//     }

//     // Handle batch parameters
//     $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
//     $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 500;

//     $records = array_slice($records, $start, $limit);

//     $updated = 0;

//     foreach ($records as $record) {
//         $story_id = intval($record['STORY_ID']);
//         $image_id = intval($record['IMAGE_ID']);

//         if (!$story_id || !$image_id) continue;

//         // Find post by ACF 'id' field
//         $posts = get_posts([
//             'post_type'  => 'post',
//             'meta_key'   => 'id',
//             'meta_value' => $story_id,
//             'numberposts' => 1,
//             'fields'     => 'ids',
//         ]);

//         if (empty($posts)) continue;
//         $post_id = $posts[0];

//         update_field('image_id', $image_id, $post_id);
//         $updated++;
//     }

//     wp_die("Batch processed from index {$start} to " . ($start + $limit - 1) . ". Total updated: {$updated}");
// });



// // Add News Web Image File Name
// add_action('admin_init', function () {
//     if (!current_user_can('manage_options') || !isset($_GET['assign_web_image_filenames'])) return;

//     $json_file = get_template_directory() . '/json/news-image.json';

//     if (!file_exists($json_file)) {
//         wp_die('JSON file not found.');
//     }

//     $json_data = file_get_contents($json_file);
//     $json_data = preg_replace('/^\xEF\xBB\xBF/', '', $json_data); // Remove BOM
//     $json_data = mb_convert_encoding($json_data, 'UTF-8', 'UTF-8'); // Ensure proper encoding
//     $records = json_decode($json_data, true);

//     if (json_last_error() !== JSON_ERROR_NONE) {
//         wp_die('JSON decode error: ' . json_last_error_msg());
//     }

//     // Batch control
//     $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
//     $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 500;

//     $batch = array_slice($records, $start, $limit);

//     $updated = 0;

//     foreach ($batch as $record) {
//         $image_id = intval($record['ID']);
//         $filename = trim($record['WEB_VERSION_FILE_NAME'] ?? '');

//         if (!$image_id || !$filename) continue;

//         // Find posts with matching ACF field 'image_id'
//         $posts = get_posts([
//             'post_type'  => 'post',
//             'meta_key'   => 'image_id',
//             'meta_value' => $image_id,
//             'numberposts' => -1,
//             'fields'     => 'ids',
//         ]);

//         foreach ($posts as $post_id) {
//             update_field('web_version_file_name', $filename, $post_id);
//             $updated++;
//         }
//     }

//     wp_die("Processed records {$start} to " . ($start + $limit - 1) . ". Total posts updated: {$updated}");
// });


// // Assign Featured Iamge
// add_action('admin_init', function () {
//     if (!current_user_can('manage_options') || !isset($_GET['import_featured_images'])) return;

//     // Get batch controls
//     $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
//     $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;

//     $all_posts = get_posts([
//         'post_type'      => 'post',
//         'post_status'    => 'any',
//         'fields'         => 'ids',
//         'orderby'        => 'ID',
//         'order'          => 'ASC',
//         'posts_per_page' => -1,
//     ]);

//     $batch_posts = array_slice($all_posts, $start, $limit);

//     $updated = 0;

//     foreach ($batch_posts as $post_id) {
//         $image_id  = get_field('image_id', $post_id);
//         $filename  = get_field('web_version_file_name', $post_id);

//         if (!$image_id || !$filename) continue;
//         if (has_post_thumbnail($post_id)) continue;

//         $image_url = "https://secure.caes.uga.edu/news/multimedia/images/{$image_id}/{$filename}";

//         $tmp = download_url($image_url);
//         if (is_wp_error($tmp)) {
//             continue;
//         }

//         $file_array = [
//             'name'     => basename($filename),
//             'tmp_name' => $tmp,
//         ];

//         $attachment_id = media_handle_sideload($file_array, $post_id);

//         if (is_wp_error($attachment_id)) {
//             @unlink($tmp);
//             continue;
//         }

//         set_post_thumbnail($post_id, $attachment_id);
//         $updated++;
//     }

//     wp_die("Processed posts {$start} to " . ($start + $limit - 1) . ". Featured images assigned: {$updated}");
// });


// Replace permalink with ACF external URL if set and valid
function custom_external_story_url($url, $post = null)
{
    if (! $post instanceof WP_Post) {
        $post = get_post($post);
    }

    // Only apply to 'post' post type
    if (! $post || $post->post_type !== 'post') {
        return $url;
    }

    // Use get_post_meta for performance
    $external_url = get_post_meta($post->ID, 'external_story_url', true);

    if ($external_url && filter_var($external_url, FILTER_VALIDATE_URL)) {
        return esc_url($external_url);
    }

    return $url;
}

// Apply to standard permalink filters
add_filter('post_link', 'custom_external_story_url', 10, 2);
add_filter('post_type_link', 'custom_external_story_url', 10, 2);
add_filter('page_link', 'custom_external_story_url', 10, 2);
add_filter('post_type_archive_link', 'custom_external_story_url', 10, 2);

// Apply to REST API responses (used in block editor, feeds, etc.)
add_filter('rest_prepare_post', function ($response, $post, $request) {
    $external_url = get_post_meta($post->ID, 'external_story_url', true);

    if ($external_url && filter_var($external_url, FILTER_VALIDATE_URL)) {
        $response->data['link'] = esc_url($external_url);
    }

    return $response;
}, 10, 3);

add_filter('default_content', 'story_default_content', 10, 2);

// Insert default content into new posts
function story_default_content($content, $post)
{
    // Only work on posts (stories)
    if ($post->post_type !== 'post') {
        return $content;
    }

    // Featured image block
    $image_url = get_template_directory_uri() . '/assets/images/texture.jpg';

    $image_block = '<!-- wp:image {"sizeSlug":"full","linkDestination":"none"} --><figure class="wp-block-image size-full"><img src="' . esc_url($image_url) . '" alt="" /><figcaption class="wp-element-caption">Replace this image and caption. Don\'t forget to write alt text in the image block settings!</figcaption></figure><!-- /wp:image -->';
    // Sources (authors) block
    $sources_block = '<!-- wp:caes-hub/pub-details-authors {"displayVersion":"names-and-titles","type":"sources","grid":false,"className":"is-style-caes-hub-compact","style":{"typography":{"lineHeight":"1.3"}}} /-->';
    // Text block
    $paragraph = '<!-- wp:paragraph --><p>Add your article text here.</p><!-- /wp:paragraph -->';
    // Related content block
    $related_content =  '<!-- wp:group {"metadata":{"name":"Related Content"},"className":"is-style-caes-hub-align-left-40","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|50","right":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|30","margin":{"bottom":"0"}},"border":{"left":{"color":"var:preset|color|hedges","width":"5px"},"top":[],"right":[],"bottom":[]}},"layout":{"type":"default"}} -->
    <div class="wp-block-group is-style-caes-hub-align-left-40" style="border-left-color:var(--wp--preset--color--hedges);border-left-width:5px;margin-bottom:0;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--50)"><!-- wp:heading {"style":{"typography":{"textTransform":"uppercase"}},"fontSize":"regular","fontFamily":"oswald"} -->
    <h2 class="wp-block-heading has-oswald-font-family has-regular-font-size" style="text-transform:uppercase">Related Content</h2>
    <!-- /wp:heading -->
    <!-- wp:caes-hub/hand-picked-post {"postType":["post","publications","shorthand_story"],"style":{"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
    <!-- wp:post-title {"level":3,"isLink":true,"style":{"elements":{"link":{"color":{"text":"var:preset|color|contrast"}}},"typography":{"textDecoration":"underline"},"spacing":{"margin":{"top":"var:preset|spacing|30","bottom":"0"}}},"fontSize":"regular"} /-->
    <!-- /wp:caes-hub/hand-picked-post --></div>
    <!-- /wp:group -->';


    return $image_block . "\n\n" . $sources_block . "\n\n" . $paragraph . "\n\n" . $related_content . "\n\n" . $content;
}

add_filter( 'render_block', function( $block_content, $block ) {
	if ( $block['blockName'] !== 'core/post-date' ) {
		return $block_content;
	}

	$post_id = get_the_ID();
	if ( ! $post_id || get_post_type( $post_id ) !== 'post' ) {
		return $block_content;
	}

	// Use ACF release_date_new if it exists
	$acf_date = get_field( 'release_date_new', $post_id );
	$timestamp = $acf_date ? strtotime( $acf_date ) : get_post_time( 'U', false, $post_id );

	if ( ! $timestamp ) {
		return $block_content;
	}

	// Format the date in APA style
	$apa_date = format_date_apa_style( $timestamp );

	// Replace only the content inside the <time> tag
	$block_content = preg_replace_callback(
		'|<time([^>]*)>(.*?)</time>|i',
		function ( $matches ) use ( $apa_date ) {
			return '<time' . $matches[1] . '>' . esc_html( $apa_date ) . '</time>';
		},
		$block_content
	);

	return $block_content;
}, 10, 2 );