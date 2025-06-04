<?php

/**
 * Paginated Duplicate Post Checker
 * 
 * Shows only titles where:
 *  - At least two posts share the same title AND the same ACF “id” field
 *    OR
 *  - At least one post in the group has a blank “id”
 * 
 * For “blank id” groups, all posts with that title are listed.
 * Otherwise, only those posts sharing a non‐blank “id” that appears more than once are listed.
 */

add_action('admin_menu', function () {
    add_management_page(
        'Duplicate Post Checker',
        'Duplicate Post Checker',
        'manage_options',
        'duplicate-post-checker',
        'render_duplicate_post_checker'
    );
});

function render_duplicate_post_checker()
{
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

    // ─────────────────────────────────────────────────────────────────────────
    // 3) FETCH ALL TITLES WITH MORE THAN ONE POST
    // ─────────────────────────────────────────────────────────────────────────
    // (We’ll filter them in PHP according to the ID rules.)
    $all_dup_titles_sql = "
		SELECT post_title
		FROM {$wpdb->posts}
		WHERE post_type = 'post'
			AND post_status != 'trash'
			AND post_title != ''
		GROUP BY post_title
		HAVING COUNT(*) > 1
		ORDER BY post_title ASC
	";
    $all_dup_titles = $wpdb->get_col($all_dup_titles_sql); // array of titles

    if (empty($all_dup_titles)) {
        echo '<p>No duplicate titles found.</p></div>';
        return;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4) FILTER TITLES BY “SAME ID” OR “BLANK ID” RULE
    // ─────────────────────────────────────────────────────────────────────────
    $filtered_titles = [];
    foreach ($all_dup_titles as $title) {
        // Get all posts that share this title
        $posts = get_posts([
            'post_type'   => 'post',
            'post_status' => 'any',
            'title'       => $title,
            'numberposts' => -1,
            'fields'      => 'ids',
        ]);

        if (count($posts) < 2) {
            continue;
        }

        // Collect “id” meta for each post
        $id_map = []; // post_id => id_value
        $id_counts = []; // id_value => count
        $has_blank = false;

        foreach ($posts as $pid) {
            $val = get_post_meta($pid, 'id', true);
            $val_trim = trim((string) $val);
            if ($val_trim === '') {
                $has_blank = true;
            } else {
                $id_counts[$val_trim] = (isset($id_counts[$val_trim]) ? $id_counts[$val_trim] + 1 : 1);
            }
            $id_map[$pid] = $val_trim;
        }

        // Check if ANY id appears more than once
        $same_id_group = false;
        foreach ($id_counts as $id_val => $count) {
            if ($count > 1) {
                $same_id_group = true;
                break;
            }
        }

        // Include this title if either:
        //  • There is at least one blank ID (so we’ll list all)
        //  • OR at least one non‐blank ID appears more than once
        if ($has_blank || $same_id_group) {
            $filtered_titles[] = $title;
        }
    }

    if (empty($filtered_titles)) {
        echo '<p>No duplicate groups match the “same ID” or “blank ID” criteria.</p></div>';
        return;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5) PAGINATE THE FILTERED TITLES
    // ─────────────────────────────────────────────────────────────────────────
    $total_groups = count($filtered_titles);
    $total_pages = (int) ceil($total_groups / $groups_per_page);

    // Grab only the slice of titles needed for this page
    $titles_for_page = array_slice($filtered_titles, $offset, $groups_per_page);

    // ─────────────────────────────────────────────────────────────────────────
    // 6) RENDER TABLES FOR EACH TITLE GROUP ON THIS PAGE
    // ─────────────────────────────────────────────────────────────────────────
    echo '<form method="POST">';
    wp_nonce_field('delete_duplicate_posts');

    foreach ($titles_for_page as $title) {
        // Re‐fetch post IDs and ID meta to decide which to show
        $posts = get_posts([
            'post_type'   => 'post',
            'post_status' => 'any',
            'title'       => $title,
            'numberposts' => -1,
            'orderby'     => 'date',
            'order'       => 'ASC',
        ]);

        // Re‐collect ID values
        $id_map = [];
        $id_counts = [];
        $has_blank = false;
        foreach ($posts as $post) {
            $val = trim(get_post_meta($post->ID, 'id', true));
            if ($val === '') {
                $has_blank = true;
            } else {
                $id_counts[$val] = (isset($id_counts[$val]) ? $id_counts[$val] + 1 : 1);
            }
            $id_map[$post->ID] = $val;
        }

        // Build a list of which post IDs to actually display:
        //  - If $has_blank, show ALL posts in this group
        //  - Otherwise, show only those posts whose $id_map value appears >1 in $id_counts
        $to_show = [];
        if ($has_blank) {
            foreach ($posts as $post) {
                $to_show[] = $post->ID;
            }
        } else {
            foreach ($posts as $post) {
                $val = $id_map[$post->ID];
                if (isset($id_counts[$val]) && $id_counts[$val] > 1) {
                    $to_show[] = $post->ID;
                }
            }
        }

        // If less than 2 posts meet the criteria, skip (shouldn’t happen, but safe)
        if (count($to_show) < 2) {
            continue;
        }

        // Display the group header (the duplicate title)
        echo '<h2 style="margin-top:2em;">' . esc_html($title) . '</h2>';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th style="width:1%;"></th>';
        echo '<th>Post ID</th>';
        echo '<th>Post Slug</th>';  // NEW COLUMN
        echo '<th>Imported ID</th>';
        echo '<th>Created At (UTC)</th>';
        echo '<th>Published Date</th>';
        echo '<th>Release Date</th>';
        echo '<th>Actions</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($to_show as $pid) {
            $post_obj      = get_post($pid);
            $custom_id     = esc_html($id_map[$pid]);
            $created_at    = $post_obj->post_date_gmt;
            $published     = $post_obj->post_date;
            $release_date  = get_post_meta($pid, 'release_date', true);
            $post_link     = get_edit_post_link($pid);
            $post_slug     = esc_html($post_obj->post_name);  // the slug
        
            echo '<tr>';
            echo '<td><input type="checkbox" name="delete_post_ids[]" value="' . esc_attr($pid) . '"></td>';
            echo '<td><a href="' . esc_url($post_link) . '">' . $pid . '</a></td>';
            echo '<td><code>' . $post_slug . '</code></td>';  // NEW COLUMN
            echo '<td><code>' . $custom_id . '</code></td>';
            echo '<td>' . esc_html($created_at) . '</td>';
            echo '<td>' . esc_html($published) . '</td>';
            echo '<td><code>' . esc_html($release_date) . '</code></td>';
            echo '<td><a class="button button-small" href="' . esc_url($post_link) . '">Edit</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    echo '<p><input type="submit" name="delete_duplicates" class="button button-danger" value="Delete Selected Posts"></p>';
    echo '</form>';

    // ─────────────────────────────────────────────────────────────────────────
    // 7) RENDER PAGINATION LINKS
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
