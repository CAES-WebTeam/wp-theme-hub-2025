<?php

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
            'post_type' => 'any',
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

