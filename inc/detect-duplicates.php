<?php

/**
 * Enhanced Paginated Duplicate Post Checker
 * 
 * Shows only titles where:
 *  - At least two posts share the same title AND the same ACF "id" field
 *    OR
 *  - At least one post in the group has a blank "id"
 * 
 * Now includes:
 *  - Both 'post' and 'shorthand_story' post types
 *  - Fuzzy title matching to catch similar titles across post types
 * 
 * For "blank id" groups, all posts with that title are listed.
 * Otherwise, only those posts sharing a non‐blank "id" that appears more than once are listed.
 */

add_action('admin_menu', function () {
    add_submenu_page(
        'caes-tools',                     // Parent slug - points to CAES Tools
        'Story Duplicate Checker',        // Page title
        'Story Duplicate Checker',        // Menu title
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
    echo '<div class="wrap"><h1>Enhanced Duplicate Post Checker</h1>';
    echo '<p><strong>Note:</strong> Now includes both Posts and Shorthand Stories with fuzzy title matching.</p>';

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
    $groups_per_page = 15; // Reduced since we'll have more complex groups
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($paged - 1) * $groups_per_page;

    // ─────────────────────────────────────────────────────────────────────────
    // 3) FETCH ALL POSTS FROM BOTH POST TYPES
    // ─────────────────────────────────────────────────────────────────────────
    $all_posts_sql = "
        SELECT ID, post_title, post_type, post_name, post_date_gmt, post_date, post_status
        FROM {$wpdb->posts}
        WHERE post_type IN ('post', 'shorthand_story')
            AND post_status != 'trash'
            AND post_title != ''
        ORDER BY post_title ASC, post_type ASC
    ";
    $all_posts = $wpdb->get_results($all_posts_sql);

    if (empty($all_posts)) {
        echo '<p>No posts found.</p></div>';
        return;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4) GROUP POSTS BY SIMILAR TITLES (FUZZY MATCHING)
    // ─────────────────────────────────────────────────────────────────────────
    $similarity_threshold = 85; // Percentage similarity required
    $title_groups = [];
    $processed_posts = [];

    foreach ($all_posts as $post) {
        if (in_array($post->ID, $processed_posts)) {
            continue;
        }

        $current_group = [$post];
        $processed_posts[] = $post->ID;

        // Compare this post's title with all remaining posts
        foreach ($all_posts as $compare_post) {
            if (in_array($compare_post->ID, $processed_posts)) {
                continue;
            }

            $similarity = get_title_similarity($post->post_title, $compare_post->post_title);
            
            if ($similarity >= $similarity_threshold) {
                $current_group[] = $compare_post;
                $processed_posts[] = $compare_post->ID;
            }
        }

        // Only keep groups with 2+ posts
        if (count($current_group) >= 2) {
            $title_groups[] = $current_group;
        }
    }

    if (empty($title_groups)) {
        echo '<p>No duplicate or similar title groups found.</p></div>';
        return;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5) FILTER GROUPS BY "SAME ID" OR "BLANK ID" RULE
    // ─────────────────────────────────────────────────────────────────────────
    $filtered_groups = [];
    
    foreach ($title_groups as $group) {
        $post_ids = array_map(function($p) { return $p->ID; }, $group);
        
        // Collect "id" meta for each post in the group
        $id_map = []; // post_id => id_value
        $id_counts = []; // id_value => count
        $has_blank = false;

        foreach ($post_ids as $pid) {
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

        // Include this group if either:
        //  • There is at least one blank ID (so we'll list all)
        //  • OR at least one non‐blank ID appears more than once
        if ($has_blank || $same_id_group) {
            $filtered_groups[] = [
                'posts' => $group,
                'id_map' => $id_map,
                'has_blank' => $has_blank,
                'id_counts' => $id_counts
            ];
        }
    }

    if (empty($filtered_groups)) {
        echo '<p>No duplicate groups match the "same ID" or "blank ID" criteria.</p></div>';
        return;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6) PAGINATE THE FILTERED GROUPS
    // ─────────────────────────────────────────────────────────────────────────
    $total_groups = count($filtered_groups);
    $total_pages = (int) ceil($total_groups / $groups_per_page);

    // Grab only the slice of groups needed for this page
    $groups_for_page = array_slice($filtered_groups, $offset, $groups_per_page);

    echo '<p>Found <strong>' . $total_groups . '</strong> duplicate groups. Showing page ' . $paged . ' of ' . $total_pages . '.</p>';

    // ─────────────────────────────────────────────────────────────────────────
    // 7) RENDER TABLES FOR EACH GROUP ON THIS PAGE
    // ─────────────────────────────────────────────────────────────────────────
    echo '<form method="POST">';
    wp_nonce_field('delete_duplicate_posts');

    foreach ($groups_for_page as $group_data) {
        $posts = $group_data['posts'];
        $id_map = $group_data['id_map'];
        $has_blank = $group_data['has_blank'];
        $id_counts = $group_data['id_counts'];

        // Build a list of which post IDs to actually display
        $to_show = [];
        if ($has_blank) {
            foreach ($posts as $post) {
                $to_show[] = $post;
            }
        } else {
            foreach ($posts as $post) {
                $val = $id_map[$post->ID];
                if (isset($id_counts[$val]) && $id_counts[$val] > 1) {
                    $to_show[] = $post;
                }
            }
        }

        if (count($to_show) < 2) {
            continue;
        }

        // Create a descriptive group header
        $titles = array_unique(array_map(function($p) { return $p->post_title; }, $to_show));
        $group_title = count($titles) === 1 ? $titles[0] : 'Similar Titles: "' . implode('", "', array_slice($titles, 0, 2)) . '"' . (count($titles) > 2 ? '...' : '');

        echo '<h2 style="margin-top:2em;">' . esc_html($group_title) . '</h2>';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th style="width:1%;"></th>';
        echo '<th>Post ID</th>';
        echo '<th>Post Type</th>';
        echo '<th>Title</th>';
        echo '<th>Post Slug</th>';
        echo '<th>Imported ID</th>';
        echo '<th>Created At (UTC)</th>';
        echo '<th>Published Date</th>';
        echo '<th>Release Date</th>';
        echo '<th>Actions</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($to_show as $post_obj) {
            $pid = $post_obj->ID;
            $custom_id = esc_html($id_map[$pid]);
            $created_at = $post_obj->post_date_gmt;
            $published = $post_obj->post_date;
            $release_date = get_post_meta($pid, 'release_date', true);
            $post_link = get_edit_post_link($pid);
            $post_slug = esc_html($post_obj->post_name);
            $post_type_label = $post_obj->post_type === 'shorthand_story' ? 'Shorthand Story' : 'Post';
            $post_type_class = $post_obj->post_type === 'shorthand_story' ? 'shorthand-story' : 'regular-post';

            echo '<tr>';
            echo '<td><input type="checkbox" name="delete_post_ids[]" value="' . esc_attr($pid) . '"></td>';
            echo '<td><a href="' . esc_url($post_link) . '">' . $pid . '</a></td>';
            echo '<td><span class="post-type-badge ' . $post_type_class . '">' . $post_type_label . '</span></td>';
            echo '<td><strong>' . esc_html($post_obj->post_title) . '</strong></td>';
            echo '<td><code>' . $post_slug . '</code></td>';
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
    // 8) RENDER PAGINATION LINKS
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

    // Add some CSS for post type badges
    echo '<style>
        .post-type-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .regular-post {
            background-color: #0073aa;
            color: white;
        }
        .shorthand-story {
            background-color: #d63638;
            color: white;
        }
    </style>';
}

/**
 * Calculate similarity between two titles
 * Uses multiple methods to get the best match
 */
function get_title_similarity($title1, $title2) {
    // Exact match
    if (strtolower(trim($title1)) === strtolower(trim($title2))) {
        return 100;
    }

    // Clean titles for comparison
    $clean1 = clean_title_for_comparison($title1);
    $clean2 = clean_title_for_comparison($title2);

    // If either title is empty after cleaning, return 0
    if (empty($clean1) || empty($clean2)) {
        return 0;
    }

    // Calculate similarity using PHP's similar_text function
    $percent = 0;
    similar_text($clean1, $clean2, $percent);
    
    // Also try Levenshtein distance for shorter strings
    if (strlen($clean1) <= 255 && strlen($clean2) <= 255) {
        $max_len = max(strlen($clean1), strlen($clean2));
        if ($max_len > 0) {
            $levenshtein_percent = (1 - (levenshtein($clean1, $clean2) / $max_len)) * 100;
            // Use the higher of the two percentages
            $percent = max($percent, $levenshtein_percent);
        }
    }

    return round($percent, 2);
}

/**
 * Clean and normalize title for comparison
 */
function clean_title_for_comparison($title) {
    // Convert to lowercase
    $title = strtolower($title);
    
    // Remove common punctuation and extra whitespace
    $title = preg_replace('/[^\w\s]/', ' ', $title);
    $title = preg_replace('/\s+/', ' ', $title);
    $title = trim($title);
    
    // Remove common stop words that might differ between post types
    $stop_words = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];
    $words = explode(' ', $title);
    $words = array_filter($words, function($word) use ($stop_words) {
        return !in_array(trim($word), $stop_words) && strlen(trim($word)) > 1;
    });
    
    return implode(' ', $words);
}