<?php

/**
 * Optimized Enhanced Paginated Duplicate Post Checker
 * 
 * Shows only titles where:
 *  - At least two posts share the same title AND the same ACF "id" field
 *    OR
 *  - At least one post in the group has a blank "id"
 * 
 * Now includes:
 *  - Both 'post' and 'shorthand_story' post types
 *  - Efficient fuzzy title matching using database-level optimization
 *  - Performance optimizations for large datasets
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
    
    // Add mode selection
    $mode = isset($_GET['mode']) ? $_GET['mode'] : 'exact';
    $similarity_threshold = isset($_GET['threshold']) ? intval($_GET['threshold']) : 85;
    
    echo '<div style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; margin: 20px 0;">';
    echo '<h3>Search Mode</h3>';
    echo '<form method="GET" style="margin-bottom: 0;">';
    echo '<input type="hidden" name="page" value="duplicate-post-checker">';
    echo '<p>';
    echo '<label><input type="radio" name="mode" value="exact"' . ($mode === 'exact' ? ' checked' : '') . '> <strong>Exact Match</strong> - Only exact title duplicates (fastest)</label><br>';
    echo '<label><input type="radio" name="mode" value="fuzzy"' . ($mode === 'fuzzy' ? ' checked' : '') . '> <strong>Fuzzy Match</strong> - Similar titles across post types (slower)</label>';
    echo '</p>';
    if ($mode === 'fuzzy') {
        echo '<p><label>Similarity threshold: <input type="number" name="threshold" value="' . $similarity_threshold . '" min="70" max="95" style="width: 60px;">%</label></p>';
    }
    echo '<p><input type="submit" class="button button-primary" value="Update Results"></p>';
    echo '</form>';
    echo '</div>';

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
    $groups_per_page = $mode === 'fuzzy' ? 10 : 20;
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($paged - 1) * $groups_per_page;

    $start_time = microtime(true);

    // ─────────────────────────────────────────────────────────────────────────
    // 3) GET DUPLICATE GROUPS BASED ON MODE
    // ─────────────────────────────────────────────────────────────────────────
    if ($mode === 'exact') {
        $filtered_groups = get_exact_duplicate_groups($wpdb);
    } else {
        $filtered_groups = get_fuzzy_duplicate_groups($wpdb, $similarity_threshold);
    }

    $processing_time = round(microtime(true) - $start_time, 2);
    echo '<p><em>Processing time: ' . $processing_time . ' seconds</em></p>';

    if (empty($filtered_groups)) {
        echo '<p>No duplicate groups match the "same ID" or "blank ID" criteria.</p></div>';
        return;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4) PAGINATE THE FILTERED GROUPS
    // ─────────────────────────────────────────────────────────────────────────
    $total_groups = count($filtered_groups);
    $total_pages = (int) ceil($total_groups / $groups_per_page);

    // Grab only the slice of groups needed for this page
    $groups_for_page = array_slice($filtered_groups, $offset, $groups_per_page);

    echo '<p>Found <strong>' . $total_groups . '</strong> duplicate groups. Showing page ' . $paged . ' of ' . $total_pages . '.</p>';

    // ─────────────────────────────────────────────────────────────────────────
    // 5) RENDER TABLES FOR EACH GROUP ON THIS PAGE
    // ─────────────────────────────────────────────────────────────────────────
    echo '<form method="POST">';
    wp_nonce_field('delete_duplicate_posts');
    foreach ($_GET as $key => $value) {
        if ($key !== 'paged') {
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
        }
    }

    foreach ($groups_for_page as $group_data) {
        render_duplicate_group($group_data);
    }

    echo '<p><input type="submit" name="delete_duplicates" class="button button-danger" value="Delete Selected Posts"></p>';
    echo '</form>';

    // ─────────────────────────────────────────────────────────────────────────
    // 6) RENDER PAGINATION LINKS
    // ─────────────────────────────────────────────────────────────────────────
    if ($total_pages > 1) {
        echo '<div class="tablenav"><div class="tablenav-pages">';
        $base_url = add_query_arg(array_merge($_GET, ['paged' => '%#%']), admin_url('tools.php'));

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

    // Add CSS
    add_duplicate_checker_styles();
}

/**
 * Get exact duplicate groups (fast method)
 */
function get_exact_duplicate_groups($wpdb) {
    // Get titles that appear more than once across both post types
    $duplicate_titles_sql = "
        SELECT post_title, COUNT(*) as count
        FROM {$wpdb->posts}
        WHERE post_type IN ('post', 'shorthand_story')
            AND post_status != 'trash'
            AND post_title != ''
        GROUP BY post_title
        HAVING COUNT(*) > 1
        ORDER BY post_title ASC
    ";
    $duplicate_titles = $wpdb->get_results($duplicate_titles_sql);
    
    $filtered_groups = [];
    
    foreach ($duplicate_titles as $title_row) {
        $group_data = process_title_group($title_row->post_title);
        if ($group_data) {
            $filtered_groups[] = $group_data;
        }
    }
    
    return $filtered_groups;
}

/**
 * Get fuzzy duplicate groups (optimized method)
 */
function get_fuzzy_duplicate_groups($wpdb, $similarity_threshold) {
    // Step 1: Get potential candidates using database-level pre-filtering
    $candidates_sql = "
        SELECT p1.ID as id1, p1.post_title as title1, p1.post_type as type1,
               p2.ID as id2, p2.post_title as title2, p2.post_type as type2
        FROM {$wpdb->posts} p1
        JOIN {$wpdb->posts} p2 ON (
            p1.ID < p2.ID 
            AND p1.post_type IN ('post', 'shorthand_story')
            AND p2.post_type IN ('post', 'shorthand_story')
            AND p1.post_status != 'trash'
            AND p2.post_status != 'trash'
            AND p1.post_title != ''
            AND p2.post_title != ''
            AND (
                -- Same first 3 characters (quick pre-filter)
                LEFT(LOWER(p1.post_title), 3) = LEFT(LOWER(p2.post_title), 3)
                OR
                -- Similar length (within 30% difference)
                ABS(CHAR_LENGTH(p1.post_title) - CHAR_LENGTH(p2.post_title)) <= GREATEST(CHAR_LENGTH(p1.post_title), CHAR_LENGTH(p2.post_title)) * 0.3
            )
        )
        ORDER BY p1.post_title
    ";
    
    $candidates = $wpdb->get_results($candidates_sql);
    
    // Step 2: Process candidates with PHP similarity matching
    $similarity_groups = [];
    $processed_pairs = [];
    
    foreach ($candidates as $candidate) {
        $pair_key = $candidate->id1 . '-' . $candidate->id2;
        if (isset($processed_pairs[$pair_key])) {
            continue;
        }
        
        $similarity = calculate_title_similarity($candidate->title1, $candidate->title2);
        if ($similarity >= $similarity_threshold) {
            $similarity_groups[] = [
                'id1' => $candidate->id1,
                'id2' => $candidate->id2,
                'title1' => $candidate->title1,
                'title2' => $candidate->title2,
                'similarity' => $similarity
            ];
        }
        
        $processed_pairs[$pair_key] = true;
    }
    
    // Step 3: Group related posts together
    $grouped_posts = group_similar_posts($similarity_groups);
    
    // Step 4: Apply the ID-based filtering rules
    $filtered_groups = [];
    foreach ($grouped_posts as $post_ids) {
        if (count($post_ids) >= 2) {
            $group_data = process_post_group($post_ids);
            if ($group_data) {
                $filtered_groups[] = $group_data;
            }
        }
    }
    
    return $filtered_groups;
}

/**
 * Process a title group to check ID rules
 */
function process_title_group($title) {
    $posts = get_posts([
        'post_type' => ['post', 'shorthand_story'],
        'post_status' => 'any',
        'title' => $title,
        'numberposts' => -1,
    ]);
    
    if (count($posts) < 2) {
        return null;
    }
    
    return process_post_group(array_map(function($p) { return $p->ID; }, $posts));
}

/**
 * Process a group of post IDs to check ID rules
 */
function process_post_group($post_ids) {
    // Collect "id" meta for each post
    $id_map = [];
    $id_counts = [];
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
    foreach ($id_counts as $count) {
        if ($count > 1) {
            $same_id_group = true;
            break;
        }
    }
    
    // Include this group if either condition is met
    if ($has_blank || $same_id_group) {
        // Get full post objects
        $posts = get_posts([
            'post_type' => ['post', 'shorthand_story'],
            'include' => $post_ids,
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'ASC',
        ]);
        
        return [
            'posts' => $posts,
            'id_map' => $id_map,
            'has_blank' => $has_blank,
            'id_counts' => $id_counts
        ];
    }
    
    return null;
}

/**
 * Group similar posts together (Union-Find style algorithm)
 */
function group_similar_posts($similarity_pairs) {
    $groups = [];
    $post_to_group = [];
    
    foreach ($similarity_pairs as $pair) {
        $id1 = $pair['id1'];
        $id2 = $pair['id2'];
        
        $group1 = isset($post_to_group[$id1]) ? $post_to_group[$id1] : null;
        $group2 = isset($post_to_group[$id2]) ? $post_to_group[$id2] : null;
        
        if ($group1 === null && $group2 === null) {
            // Create new group
            $new_group_id = count($groups);
            $groups[$new_group_id] = [$id1, $id2];
            $post_to_group[$id1] = $new_group_id;
            $post_to_group[$id2] = $new_group_id;
        } elseif ($group1 !== null && $group2 === null) {
            // Add id2 to group1
            $groups[$group1][] = $id2;
            $post_to_group[$id2] = $group1;
        } elseif ($group1 === null && $group2 !== null) {
            // Add id1 to group2
            $groups[$group2][] = $id1;
            $post_to_group[$id1] = $group2;
        } elseif ($group1 !== $group2) {
            // Merge groups
            $groups[$group1] = array_merge($groups[$group1], $groups[$group2]);
            foreach ($groups[$group2] as $post_id) {
                $post_to_group[$post_id] = $group1;
            }
            unset($groups[$group2]);
        }
    }
    
    return array_values($groups);
}

/**
 * Optimized similarity calculation
 */
function calculate_title_similarity($title1, $title2) {
    $clean1 = clean_title_for_comparison($title1);
    $clean2 = clean_title_for_comparison($title2);
    
    if (empty($clean1) || empty($clean2)) {
        return 0;
    }
    
    if ($clean1 === $clean2) {
        return 100;
    }
    
    // Use similar_text which is faster for longer strings
    similar_text($clean1, $clean2, $percent);
    
    return round($percent, 1);
}

/**
 * Clean title for comparison (optimized)
 */
function clean_title_for_comparison($title) {
    static $cache = [];
    
    if (isset($cache[$title])) {
        return $cache[$title];
    }
    
    $original = $title;
    
    // Convert to lowercase and remove punctuation
    $title = strtolower(preg_replace('/[^\w\s]/', ' ', $title));
    $title = preg_replace('/\s+/', ' ', trim($title));
    
    // Cache the result
    $cache[$original] = $title;
    
    // Limit cache size
    if (count($cache) > 1000) {
        $cache = array_slice($cache, 500, null, true);
    }
    
    return $title;
}

/**
 * Render a duplicate group table
 */
function render_duplicate_group($group_data) {
    $posts = $group_data['posts'];
    $id_map = $group_data['id_map'];
    $has_blank = $group_data['has_blank'];
    $id_counts = $group_data['id_counts'];

    // Determine which posts to show
    $to_show = [];
    if ($has_blank) {
        $to_show = $posts;
    } else {
        foreach ($posts as $post) {
            $val = $id_map[$post->ID];
            if (isset($id_counts[$val]) && $id_counts[$val] > 1) {
                $to_show[] = $post;
            }
        }
    }

    if (count($to_show) < 2) {
        return;
    }

    // Create group header
    $titles = array_unique(array_map(function($p) { return $p->post_title; }, $to_show));
    $group_title = count($titles) === 1 ? $titles[0] : 'Similar Titles Group (' . count($titles) . ' variations)';

    echo '<h2 style="margin-top:2em;">' . esc_html($group_title) . '</h2>';
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th style="width:1%;"></th>';
    echo '<th>ID</th>';
    echo '<th>Type</th>';
    echo '<th>Title</th>';
    echo '<th>Slug</th>';
    echo '<th>Import ID</th>';
    echo '<th>Created</th>';
    echo '<th>Published</th>';
    echo '<th>Release Date</th>';
    echo '<th>Actions</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    foreach ($to_show as $post) {
        $custom_id = esc_html($id_map[$post->ID]);
        $post_link = get_edit_post_link($post->ID);
        $post_type_label = $post->post_type === 'shorthand_story' ? 'Shorthand' : 'Post';
        $post_type_class = $post->post_type === 'shorthand_story' ? 'shorthand-story' : 'regular-post';
        $release_date = get_post_meta($post->ID, 'release_date', true);

        echo '<tr>';
        echo '<td><input type="checkbox" name="delete_post_ids[]" value="' . esc_attr($post->ID) . '"></td>';
        echo '<td><a href="' . esc_url($post_link) . '">' . $post->ID . '</a></td>';
        echo '<td><span class="post-type-badge ' . $post_type_class . '">' . $post_type_label . '</span></td>';
        echo '<td><strong>' . esc_html($post->post_title) . '</strong></td>';
        echo '<td><code>' . esc_html($post->post_name) . '</code></td>';
        echo '<td><code>' . $custom_id . '</code></td>';
        echo '<td>' . esc_html($post->post_date_gmt) . '</td>';
        echo '<td>' . esc_html($post->post_date) . '</td>';
        echo '<td><code>' . esc_html($release_date) . '</code></td>';
        echo '<td><a class="button button-small" href="' . esc_url($post_link) . '">Edit</a></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}

/**
 * Add styles for the duplicate checker
 */
function add_duplicate_checker_styles() {
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
        .duplicate-group-stats {
            background: #f0f0f1;
            padding: 10px;
            margin: 10px 0;
            border-left: 4px solid #0073aa;
        }
    </style>';
}