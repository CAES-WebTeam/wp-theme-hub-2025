<?php
add_action('admin_menu', function () {
	add_management_page(
		'Duplicate Post Checker',
		'Duplicate Post Checker',
		'manage_options',
		'duplicate-post-checker',
		'render_duplicate_post_checker'
	);
});

function render_duplicate_post_checker() {
	if (!current_user_can('manage_options')) return;

	echo '<div class="wrap"><h1>Duplicate Post Checker</h1>';

	// Handle deletions
	if (isset($_POST['delete_duplicates']) && !empty($_POST['delete_post_ids'])) {
		check_admin_referer('delete_duplicate_posts');
		$to_delete = array_map('intval', $_POST['delete_post_ids']);
		foreach ($to_delete as $post_id) {
			wp_delete_post($post_id, true);
		}
		echo '<div class="notice notice-success"><p>Deleted ' . count($to_delete) . ' post(s).</p></div>';
	}

	global $wpdb;
	$duplicates = $wpdb->get_results("
		SELECT post_title, COUNT(*) as count
		FROM {$wpdb->posts}
		WHERE post_type = 'post' AND post_status != 'trash' AND post_title != ''
		GROUP BY post_title
		HAVING count > 1
	");

	if (empty($duplicates)) {
		echo '<p>No duplicate titles found.</p></div>';
		return;
	}

	echo '<form method="POST">';
	wp_nonce_field('delete_duplicate_posts');

	foreach ($duplicates as $dup) {
		$title = $dup->post_title;
		$posts = get_posts([
			'post_type'   => 'post',
			'post_status' => 'any',
			'title'       => $title,
			'numberposts' => -1,
			'orderby'     => 'date',
			'order'       => 'ASC',
		]);

		echo '<h2 style="margin-top:2em;">' . esc_html($title) . '</h2>';
		echo '<table class="widefat fixed striped"><thead>';
		echo '<tr><th style="width:1%;"></th><th>Post ID</th><th>Imported ID</th><th>Post Date</th><th>Release Date</th><th>Actions</th></tr>';
		echo '</thead><tbody>';

		foreach ($posts as $post) {
			$custom_id     = get_post_meta($post->ID, 'ID', true);
			$release_date  = get_post_meta($post->ID, 'release_date', true);
			$post_link     = get_edit_post_link($post->ID);

			echo '<tr>';
			echo '<td><input type="checkbox" name="delete_post_ids[]" value="' . esc_attr($post->ID) . '"></td>';
			echo '<td><a href="' . esc_url($post_link) . '">' . $post->ID . '</a></td>';
			echo '<td><code>' . esc_html($custom_id) . '</code></td>';
			echo '<td>' . esc_html($post->post_date) . '</td>';
			echo '<td><code>' . esc_html($release_date) . '</code></td>';
			echo '<td><a class="button button-small" href="' . esc_url($post_link) . '">Edit</a></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	echo '<p><input type="submit" name="delete_duplicates" class="button button-danger" value="Delete Selected Posts"></p>';
	echo '</form>';
	echo '</div>';
}
