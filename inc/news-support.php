<?php
/***  Sync News Stories with Story Writers Users  ***/
add_action('admin_init', function () {
    if (!current_user_can('manage_options') || !isset($_GET['link_writers_to_stories'])) return;

    $json_file_path = get_template_directory() . '/json/news-writers-association.json';

    if (!file_exists($json_file_path)) {
        wp_die('Data file not found.');
    }

    // Read and sanitize JSON
    $json_data = file_get_contents($json_file_path);
    $json_data = preg_replace('/^\xEF\xBB\xBF/', '', $json_data);
    $json_data = mb_convert_encoding($json_data, 'UTF-8', 'UTF-8');
    $records = json_decode($json_data, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_die('JSON decode error: ' . json_last_error_msg());
    }

    $linked = 0;

    foreach ($records as $pair) {
        $story_id = intval($pair['STORY_ID']);
        $writer_id = intval($pair['WRITER_ID']);

        // Find post with matching ACF 'id'
        $posts = get_posts([
            'post_type' => 'post',
            'meta_key' => 'id',
            'meta_value' => $story_id,
            'numberposts' => 1,
            'fields' => 'ids',
        ]);

        if (empty($posts)) continue;
        $post_id = $posts[0];

        // Find user with matching ACF 'writer_id'
        $users = get_users([
            'meta_key' => 'writer_id',
            'meta_value' => $writer_id,
            'number' => 1,
            'fields' => 'ID',
        ]);

        if (empty($users)) continue;
        $user_id = $users[0];

        // Load existing experts (always an array)
	    $authors = get_field('authors', $post_id);
		if (!is_array($authors)) $authors = [];

		$already_added = false;
		foreach ($authors as $row) {
		    $existing_user = $row['user'];

		    // Normalize to user ID
		    if (is_object($existing_user) && isset($existing_user->ID)) {
		        $existing_user = $existing_user->ID;
		    } elseif (is_array($existing_user) && isset($existing_user['ID'])) {
		        $existing_user = $existing_user['ID'];
		    }

		    if (intval($existing_user) === intval($user_id)) {
		        $already_added = true;
		        break;
		    }
		}

	    // Add user if not already in the repeater
	    if (!$already_added) {
		    $authors[] = ['user' => $user_id];
		    update_field('authors', $authors, $post_id);
		    $linked++;
		}
    }

    wp_die("Writer linking complete. Writers linked to posts: {$linked}");
});


/***  Sync News Stories with Story Expert/Source Users  ***/
add_action('admin_init', function () {
    if (!current_user_can('manage_options') || !isset($_GET['link_experts_to_stories'])) return;

    $json_file_path = get_template_directory() . '/json/NewsAssociationStorySourceExpert.json';

    if (!file_exists($json_file_path)) {
        wp_die('Data file not found.');
    }

    // Read and sanitize JSON
    $json_data = file_get_contents($json_file_path);
    $json_data = preg_replace('/^\xEF\xBB\xBF/', '', $json_data);
    $json_data = mb_convert_encoding($json_data, 'UTF-8', 'UTF-8');
    $records = json_decode($json_data, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_die('JSON decode error: ' . json_last_error_msg());
    }

    $linked = 0;

    foreach ($records as $pair) {
        $story_id = intval($pair['STORY_ID']);
        $expert_id = intval($pair['SOURCE_EXPERT_ID']);

        // Find post with matching ACF 'id'
        $posts = get_posts([
            'post_type' => 'post',
            'meta_key' => 'id',
            'meta_value' => $story_id,
            'numberposts' => 1,
            'fields' => 'ids',
        ]);

        if (empty($posts)) continue;
        $post_id = $posts[0];

        // Find user with matching ACF 'source_expert_id'
        $users = get_users([
            'meta_key' => 'source_expert_id',
            'meta_value' => $expert_id,
            'number' => 1,
            'fields' => 'ID',
        ]);

        if (empty($users)) continue;
        $user_id = $users[0];

        // Load existing experts (always an array)
	   $experts = get_field('experts', $post_id);
		if (!is_array($experts)) $experts = [];

		$already_added = false;
		foreach ($experts as $row) {
		    $existing_user = $row['user'];

		    // Normalize to user ID
		    if (is_object($existing_user) && isset($existing_user->ID)) {
		        $existing_user = $existing_user->ID;
		    } elseif (is_array($existing_user) && isset($existing_user['ID'])) {
		        $existing_user = $existing_user['ID'];
		    }

		    if (intval($existing_user) === intval($user_id)) {
		        $already_added = true;
		        break;
		    }
		}

	    // Add user if not already in the repeater
	    if (!$already_added) {
		    $experts[] = ['user' => $user_id];
		    update_field('experts', $experts, $post_id);
		    $linked++;
		}
    }

    wp_die("Expert linking complete. Experts linked to posts: {$linked}");
});


/***   Sync Keywords to News Story  ***/
add_action('admin_init', function () {
    if (!current_user_can('manage_options') || !isset($_GET['associate_keywords_to_posts'])) return;

    $json_file = get_template_directory() . '/json/NEWS_ASSOCIATION_STORY_KEYWORD.json';

    if (!file_exists($json_file)) {
        wp_die('JSON file not found.');
    }

    // Read and clean
    $json_data = file_get_contents($json_file);
    $json_data = preg_replace('/^\xEF\xBB\xBF/', '', $json_data);
    $json_data = mb_convert_encoding($json_data, 'UTF-8', 'UTF-8');
    $records = json_decode($json_data, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_die('JSON decode error: ' . json_last_error_msg());
    }

    $linked = 0;

    foreach ($records as $pair) {
        $story_id = intval($pair['STORY_ID']);
        $keyword_id = intval($pair['KEYWORD_ID']);

        // Find the post by ACF field "id"
        $posts = get_posts([
            'post_type' => 'any',
            'meta_key' => 'id',
            'meta_value' => $story_id,
            'numberposts' => 1,
            'fields' => 'ids',
        ]);

        if (empty($posts)) continue;
        $post_id = $posts[0];

        // Find term in 'keywords' taxonomy by ACF field "keyword_id"
        $terms = get_terms([
            'taxonomy' => 'keywords',
            'hide_empty' => false,
            'meta_query' => [[
                'key' => 'keyword_id',
                'value' => $keyword_id,
                'compare' => '='
            ]]
        ]);

        if (empty($terms) || is_wp_error($terms)) continue;
        $term_id = $terms[0]->term_id;

        // Get current terms
        $existing_terms = wp_get_object_terms($post_id, 'keywords', ['fields' => 'ids']);

        // Prevent duplicates
        if (!in_array($term_id, $existing_terms)) {
            $existing_terms[] = $term_id;
            wp_set_object_terms($post_id, $existing_terms, 'keywords');
            $linked++;
        }
    }

    wp_die("Keyword linking complete. Keywords linked to posts: {$linked}");
});

// Custom external URLs for news stories
function custom_external_story_url( $url, $post ) {
	// Only apply to the 'post' post type
	if ( get_post_type( $post ) !== 'post' ) {
		return $url;
	}
	// Check for the ACF field
	$external_url = get_field( 'external_story_url', $post->ID );
	// If it's a valid URL, return it
	if ( ! empty( $external_url ) && filter_var( $external_url, FILTER_VALIDATE_URL ) ) {
		return esc_url( $external_url );
	}
	// Otherwise, use the default permalink
	return $url;
}
add_filter( 'post_link', 'custom_external_story_url', 10, 2 );