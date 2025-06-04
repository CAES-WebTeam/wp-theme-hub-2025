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
	if (! current_user_can('manage_options')) {
		return;
	}

	global $wpdb;
	echo '<div class="wrap"><h1>Duplicate Post Checker</h1>';

	// ─────────────────────────────────────────────────────────────────────────
	// 1) HANDLE DELETIONS
	// ─────────────────────────────────────────────────────────────────────────
	if (isset($_POST['delete_duplicates']) && ! empty($_POST['delete_post_ids'])) {
		check_admin_referer('delete_duplicate_posts');
		$to_delete = array_map('intval', $_POST['delete_post_ids']);
		foreach ($to_delete as $post_id) {
			wp_delete_post($post_id, true);
		}
		echo '<div class="notice notice-success"><p>Deleted ' . count($to_delete) . ' post(s).</p></div>';
	}

	// ─────────────────────────────────────────────────────────────────────────
	// 2) PAGINATION SETTINGS
	// ─────────────────────────────────────────────────────────────────────────
	$groups_per_page = 20;
	$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
	$offset = ($paged - 1) * $groups_per_page;

	// Count total distinct titles that have more than one post
	$count_sql = "
		SELECT COUNT(*) as total_groups
		FROM (
			SELECT post_title
			FROM {$wpdb->posts}
			WHERE post_type = 'post'
				AND post_status != 'trash'
				AND post_title != ''
			GROUP BY post_title
			HAVING COUNT(*) > 1
		) AS dup_groups
	";
	$total_groups = (int) $wpdb->get_var($count_sql);
	$total_pages = (int) ceil($total_groups / $groups_per_page);

	// Fetch only the page of groups we need
	$groups_sql = $wpdb->prepare("
		SELECT post_title, COUNT(*) as cnt
		FROM {$wpdb->posts}
		WHERE post_type = 'post'
			AND post_status != 'trash'
			AND post_title != ''
		GROUP BY post_title
		HAVING COUNT(*) > 1
		ORDER BY post_title ASC
		LIMIT %d, %d
	", $offset, $groups_per_page);

	$duplicate_groups = $wpdb->get_results($groups_sql);
	if (empty($duplicate_groups)) {
		echo '<p>No duplicate titles found.</p></div>';
		return;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// 3) RENDER LIST OF DUPLICATE GROUPS
	// ─────────────────────────────────────────────────────────────────────────
	echo '<form method="POST">';
	wp_nonce_field('delete_duplicate_posts');

	foreach ($duplicate_groups as $group) {
		$title = $group->post_title;

		// Fetch all posts that share this title
		$posts = get_posts([
			'post_type'   => 'post',
			'post_status' => 'any',
			'title'       => $title,
			'numberposts' => -1,
			'orderby'     => 'date',
			'order'       => 'ASC',
		]);

		echo '<h2 style="margin-top:2em;">' . esc_html($title) . '</h2>';
		echo '<table class="widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th style="width:1%;"></th>';
		echo '<th>Post ID</th>';
		echo '<th>Imported ID</th>';
		echo '<th>Post Date</th>';
		echo '<th>Release Date</th>';
		echo '<th>Actions</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		foreach ($posts as $post) {
			$custom_id    = get_post_meta($post->ID, 'ID', true);
			$release_date = get_post_meta($post->ID, 'release_date', true);
			$post_link    = get_edit_post_link($post->ID);

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

	// ─────────────────────────────────────────────────────────────────────────
	// 4) PAGINATION LINKS
	// ─────────────────────────────────────────────────────────────────────────
	if ($total_pages > 1) {
		echo '<div class="tablenav"><div class="tablenav-pages">';
		$base_url = add_query_arg([
			'paged' => '%#%',
			'page'  => 'duplicate-post-checker',
		], admin_url('tools.php'));

		echo paginate_links([
			'base'      => $base_url,
			'format'    => '',
			'current'   => $paged,
			'total'     => $total_pages,
			'prev_text' => __('‹ Previous'),
			'next_text' => __('Next ›'),
		]);
		echo '</div></div>';
	}

	echo '</div>'; // .wrap
}