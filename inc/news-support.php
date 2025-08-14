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

// Make regular posts show as /news/post-name/
add_filter('post_type_link', function($link, $post) {
    if ($post->post_type === 'post') {
        return home_url('/news/' . $post->post_name . '/');
    }
    return $link;
}, 10, 2);

// Add rewrite rule for /news/ posts
add_action('init', function() {
    add_rewrite_rule('^news/([^/]+)/?$', 'index.php?name=$matches[1]', 'top');
});

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

    // Featured image block (keeping the texture.jpg placeholder)
    $image_url = get_template_directory_uri() . '/assets/images/texture.jpg';

    $default_content = '<!-- wp:image {"sizeSlug":"full","linkDestination":"none","align":"wide"} -->
<figure class="wp-block-image alignwide size-full"><img src="' . esc_url($image_url) . '" alt=""/><figcaption class="wp-element-caption">Replace this image and caption. Don\'t forget to write alt text in the image block settings!</figcaption></figure>
<!-- /wp:image -->

<!-- wp:group {"metadata":{"categories":["content_patterns"],"patternName":"caes-hub/takeaways-1","name":"Takeaways"},"className":"caes-hub-takeaways","style":{"shadow":"var:preset|shadow|small","spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|60","right":"var:preset|spacing|60"},"blockGap":"var:preset|spacing|60"},"border":{"left":{"color":"var:preset|color|hedges","width":"5px"}}},"backgroundColor":"base","layout":{"type":"default"}} -->
<div class="wp-block-group caes-hub-takeaways has-base-background-color has-background" style="border-left-color:var(--wp--preset--color--hedges);border-left-width:5px;padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--60);box-shadow:var(--wp--preset--shadow--small)"><!-- wp:group {"metadata":{"name":"caes-hub-takeaways__header"},"className":"caes-hub-takeaways__header","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group caes-hub-takeaways__header"><!-- wp:heading {"style":{"typography":{"textTransform":"uppercase"}},"fontSize":"large","fontFamily":"oswald"} -->
<h2 class="wp-block-heading has-oswald-font-family has-large-font-size" style="text-transform:uppercase">Takeaways</h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:list {"className":"is-style-default"} -->
<ul class="wp-block-list is-style-default"><!-- wp:list-item {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|60"}}}} -->
<li style="margin-bottom:var(--wp--preset--spacing--60)"><strong>Takeaway:</strong> Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris ut turpis neque. Duis a nisi placerat, scelerisque sapien eu, sollicitudin enim. Maecenas egestas quam et est venenatis, ut congue sapien porttitor.</li>
<!-- /wp:list-item -->

<!-- wp:list-item {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|60"}}}} -->
<li style="margin-bottom:var(--wp--preset--spacing--60)"><strong>Takeaway:</strong> Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris ut turpis neque. Duis a nisi placerat, scelerisque sapien eu, sollicitudin enim. Maecenas egestas quam et est venenatis, ut congue sapien porttitor.</li>
<!-- /wp:list-item -->

<!-- wp:list-item {"style":{"spacing":{"margin":{"bottom":"0"}}}} -->
<li style="margin-bottom:0"><strong>Takeaway:</strong> Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris ut turpis neque. Duis a nisi placerat, scelerisque sapien eu, sollicitudin enim. Maecenas egestas quam et est venenatis, ut congue sapien porttitor.</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:group -->

<!-- wp:paragraph -->
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nam faucibus nibh ex, eu cursus orci faucibus quis. Nunc ut feugiat dui. Praesent congue sit amet felis in blandit. In tristique odio ut nisi auctor consectetur. Nunc nunc sapien, luctus et orci a, imperdiet aliquam nisi. Integer efficitur lacus at purus molestie, in auctor nunc fermentum. Nulla pharetra felis sed tincidunt pharetra.</p>
<!-- /wp:paragraph -->

<!-- wp:caes-hub/pub-details-authors {"displayVersion":"names-and-titles","type":"sources","grid":false,"className":"is-style-caes-hub-compact","style":{"typography":{"lineHeight":"1.3"}}} /-->

<!-- wp:paragraph -->
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nam faucibus nibh ex, eu cursus orci faucibus quis. Nunc ut feugiat dui. Praesent congue sit amet felis in blandit. In tristique odio ut nisi auctor consectetur. Nunc nunc sapien, luctus et orci a, imperdiet aliquam nisi. Integer efficitur lacus at purus molestie, in auctor nunc fermentum. Nulla pharetra felis sed tincidunt pharetra.</p>
<!-- /wp:paragraph -->';

    return $default_content . "\n\n" . $content;
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

function update_flat_expert_ids_meta($post_id)
{
    // Check if it's an autosave to prevent unnecessary processing
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Ensure this only runs for 'post' post type
    if (get_post_type($post_id) !== 'post') {
        return;
    }

    // Get the ACF repeater field called 'experts'
    $experts = get_field('experts', $post_id);

    // If no experts are selected or the field is empty, delete the meta key
    if (!$experts || !is_array($experts)) {
        delete_post_meta($post_id, 'all_expert_ids');
        return;
    }

    $expert_ids = [];

    foreach ($experts as $expert_row) {
        // Ensure the 'user' sub-field exists and is a valid user ID
        if (!empty($expert_row['user']) && is_numeric($expert_row['user'])) {
            $expert_ids[] = (int) $expert_row['user'];
        } else {
            // Log an error if an expert entry is malformed
            error_log("⚠️ Invalid or missing 'user' field in expert entry for post ID: {$post_id}");
        }
    }

    // Store the array of expert IDs as a single meta value (serialized by WordPress)
    update_post_meta($post_id, 'all_expert_ids', $expert_ids);
}
add_action('acf/save_post', 'update_flat_expert_ids_meta', 20);