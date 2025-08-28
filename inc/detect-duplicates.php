<?php

/**
 * Simplified Duplicate Post Checker
 * 
 * Shows only titles where:
 *  - At least two posts share the same title AND the same ACF "id" field
 *    OR
 *  - At least one post in the group has a blank "id"
 * 
 * Features:
 *  - Fast exact title matching across both post types
 *  - Optional fuzzy matching: shorthand_story titles vs post titles
 */

add_action('admin_menu', function () {
    add_submenu_page(
        'caes-tools',
        'Story Duplicate Checker',
        'Story Duplicate Checker',
        'manage_options',
        'duplicate-post-checker',
        'render_duplicate_post_checker'
    );
});

// AJAX handler for fuzzy shorthand vs posts
add_action('wp_ajax_fuzzy_shorthand_posts', 'handle_fuzzy_shorthand_ajax');

function handle_fuzzy_shorthand_ajax() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_ajax_referer('fuzzy_shorthand_nonce', 'nonce');

    global $wpdb;
    
    $similarity_threshold = intval($_POST['threshold']);
    $offset = intval($_POST['offset']);
    $batch_size = 100; // Process 100 shorthand stories at a time
    
    // Get total shorthand stories count
    $total_shorthand = $wpdb->get_var("
        SELECT COUNT(*) 
        FROM {$wpdb->posts} 
        WHERE post_type = 'shorthand_story'
            AND post_status != 'trash'
            AND post_title != ''
    ");
    
    // Get batch of shorthand stories
    $shorthand_batch = $wpdb->get_results($wpdb->prepare("
        SELECT ID, post_title
        FROM {$wpdb->posts}
        WHERE post_type = 'shorthand_story'
            AND post_status != 'trash'
            AND post_title != ''
        ORDER BY ID
        LIMIT %d OFFSET %d
    ", $batch_size, $offset));
    
    if (empty($shorthand_batch)) {
        // Complete
        wp_send_json_success(['complete' => true]);
        return;
    }
    
    // Get all regular posts to compare against
    $all_posts = $wpdb->get_results("
        SELECT ID, post_title
        FROM {$wpdb->posts}
        WHERE post_type = 'post'
            AND post_status != 'trash'
            AND post_title != ''
    ");
    
    $matches = [];
    
    // Compare each shorthand story in this batch against all posts
    foreach ($shorthand_batch as $shorthand) {
        foreach ($all_posts as $post) {
            $similarity = calculate_similarity($shorthand->post_title, $post->post_title);
            if ($similarity >= $similarity_threshold) {
                $matches[] = [
                    'shorthand_id' => $shorthand->ID,
                    'shorthand_title' => $shorthand->post_title,
                    'post_id' => $post->ID,
                    'post_title' => $post->post_title,
                    'similarity' => $similarity
                ];
            }
        }
    }
    
    $progress = min(100, round((($offset + $batch_size) / $total_shorthand) * 100, 1));
    
    wp_send_json_success([
        'complete' => false,
        'progress' => $progress,
        'processed' => $offset + count($shorthand_batch),
        'total' => $total_shorthand,
        'matches' => $matches
    ]);
}

function render_duplicate_post_checker() {
    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;
    echo '<div class="wrap"><h1>Story Duplicate Checker</h1>';

    // Handle deletions
    if (isset($_POST['delete_duplicates']) && !empty($_POST['delete_post_ids'])) {
        check_admin_referer('delete_duplicate_posts');
        $to_delete = array_map('intval', $_POST['delete_post_ids']);
        foreach ($to_delete as $post_id) {
            wp_delete_post($post_id, true);
        }
        echo '<div class="notice notice-success"><p>Deleted ' . count($to_delete) . ' post(s).</p></div>';
    }

    // Mode selection
    $show_fuzzy = isset($_GET['include_fuzzy']);
    $similarity_threshold = isset($_GET['threshold']) ? intval($_GET['threshold']) : 85;

    echo '<div style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; margin: 20px 0;">';
    echo '<h3>Search Options</h3>';
    echo '<form method="GET">';
    echo '<input type="hidden" name="page" value="duplicate-post-checker">';
    
    echo '<p>';
    echo '<label><input type="checkbox" name="include_fuzzy" value="1"' . ($show_fuzzy ? ' checked' : '') . '> ';
    echo 'Also find similar titles (Shorthand Stories vs Posts)</label>';
    echo '</p>';
    
    if ($show_fuzzy) {
        echo '<p style="margin-left: 20px;">';
        echo '<label>Similarity threshold: ';
        echo '<input type="number" name="threshold" value="' . $similarity_threshold . '" min="75" max="95" style="width: 60px;">%';
        echo '</label>';
        echo '</p>';
    }
    
    echo '<p><input type="submit" class="button button-primary" value="Search for Duplicates"></p>';
    echo '</form>';
    echo '</div>';

    // Always show exact duplicates first
    $start_time = microtime(true);
    $exact_groups = get_exact_duplicate_groups($wpdb);
    $exact_time = round(microtime(true) - $start_time, 2);

    echo '<h2>Exact Title Matches</h2>';
    echo '<p><em>Found in ' . $exact_time . ' seconds</em></p>';

    if (empty($exact_groups)) {
        echo '<p>No exact duplicate titles found.</p>';
    } else {
        render_results_section($exact_groups, 'exact');
    }

    // Show fuzzy matching interface if requested
    if ($show_fuzzy) {
        echo '<hr style="margin: 40px 0;">';
        echo '<h2>Similar Titles (Shorthand Stories vs Posts)</h2>';
        render_fuzzy_interface($similarity_threshold);
    }

    echo '</div>'; // .wrap
    add_simple_styles();
    if ($show_fuzzy) {
        add_fuzzy_script();
    }
}

function get_exact_duplicate_groups($wpdb) {
    // Get titles that appear in multiple posts (either same type or across types)
    $duplicate_titles = $wpdb->get_results("
        SELECT post_title, COUNT(*) as count,
               GROUP_CONCAT(DISTINCT post_type) as post_types
        FROM {$wpdb->posts}
        WHERE post_type IN ('post', 'shorthand_story')
            AND post_status != 'trash'
            AND post_title != ''
        GROUP BY post_title
        HAVING COUNT(*) > 1
        ORDER BY post_title ASC
    ");

    $filtered_groups = [];
    
    foreach ($duplicate_titles as $title_row) {
        $group_data = process_title_group($title_row->post_title);
        if ($group_data) {
            $filtered_groups[] = $group_data;
        }
    }
    
    return $filtered_groups;
}

function process_title_group($title) {
    $posts = get_posts([
        'post_type' => ['post', 'shorthand_story'],
        'post_status' => 'any',
        'title' => $title,
        'numberposts' => -1,
        'orderby' => 'date',
        'order' => 'ASC',
    ]);
    
    if (count($posts) < 2) {
        return null;
    }
    
    // Check ID rules
    $id_map = [];
    $id_counts = [];
    $has_blank = false;
    
    foreach ($posts as $post) {
        $val = get_post_meta($post->ID, 'id', true);
        $val_trim = trim((string) $val);
        if ($val_trim === '') {
            $has_blank = true;
        } else {
            $id_counts[$val_trim] = (isset($id_counts[$val_trim]) ? $id_counts[$val_trim] + 1 : 1);
        }
        $id_map[$post->ID] = $val_trim;
    }
    
    // Check if ANY id appears more than once
    $same_id_group = false;
    foreach ($id_counts as $count) {
        if ($count > 1) {
            $same_id_group = true;
            break;
        }
    }
    
    // Include if blank ID or same ID appears multiple times
    if ($has_blank || $same_id_group) {
        return [
            'posts' => $posts,
            'id_map' => $id_map,
            'has_blank' => $has_blank,
            'id_counts' => $id_counts
        ];
    }
    
    return null;
}

function render_fuzzy_interface($similarity_threshold) {
    echo '<div id="fuzzy-section">';
    echo '<p>This will compare all Shorthand Story titles against all Post titles to find similar matches.</p>';
    
    echo '<div id="fuzzy-progress" style="display: none;">';
    echo '<div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0;">';
    echo '<h4>Processing...</h4>';
    echo '<div style="width: 100%; background: #f0f0f1; border: 1px solid #ccd0d4; height: 25px; position: relative;">';
    echo '<div id="progress-bar" style="height: 100%; background: #0073aa; width: 0%; transition: width 0.3s;"></div>';
    echo '<div id="progress-text" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-weight: bold; font-size: 12px;">0%</div>';
    echo '</div>';
    echo '<div id="progress-stats" style="margin-top: 10px; font-size: 14px; color: #666;"></div>';
    echo '</div>';
    echo '</div>';
    
    echo '<div id="fuzzy-results"></div>';
    
    echo '<button id="start-fuzzy" class="button button-primary" data-threshold="' . $similarity_threshold . '">';
    echo 'Find Similar Titles (Shorthand → Posts)';
    echo '</button>';
    
    echo '</div>';
}

function render_results_section($groups, $section_id) {
    $groups_per_page = 20;
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($paged - 1) * $groups_per_page;

    $total_groups = count($groups);
    $total_pages = (int) ceil($total_groups / $groups_per_page);
    $groups_for_page = array_slice($groups, $offset, $groups_per_page);

    echo '<p>Found <strong>' . $total_groups . '</strong> duplicate groups.</p>';

    if (empty($groups_for_page)) {
        return;
    }

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

    // Pagination
    if ($total_pages > 1) {
        echo '<div class="tablenav"><div class="tablenav-pages">';
        $base_url = add_query_arg(array_merge($_GET, ['paged' => '%#%']), admin_url('tools.php'));
        echo paginate_links([
            'base' => $base_url,
            'format' => '',
            'current' => $paged,
            'total' => $total_pages,
            'prev_text' => __('‹ Previous'),
            'next_text' => __('Next ›'),
        ]);
        echo '</div></div>';
    }
}

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

    if (count($to_show) < 2) return;

    // Group header
    $title = $posts[0]->post_title;
    $post_types = array_unique(array_map(function($p) { return $p->post_type; }, $to_show));
    $type_indicator = count($post_types) > 1 ? ' (Cross-Type)' : '';
    
    echo '<h3 style="margin-top:2em;">' . esc_html($title) . $type_indicator . '</h3>';
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th style="width:1%;"></th><th>ID</th><th>Type</th><th>Slug</th><th>Import ID</th><th>Created</th><th>Published</th><th>Actions</th></tr></thead><tbody>';

    foreach ($to_show as $post) {
        $custom_id = esc_html($id_map[$post->ID]);
        $post_link = get_edit_post_link($post->ID);
        $post_type_label = $post->post_type === 'shorthand_story' ? 'Shorthand' : 'Post';
        $post_type_class = $post->post_type === 'shorthand_story' ? 'shorthand' : 'post';

        echo '<tr>';
        echo '<td><input type="checkbox" name="delete_post_ids[]" value="' . esc_attr($post->ID) . '"></td>';
        echo '<td><a href="' . esc_url($post_link) . '">' . $post->ID . '</a></td>';
        echo '<td><span class="type-badge ' . $post_type_class . '">' . $post_type_label . '</span></td>';
        echo '<td><code>' . esc_html($post->post_name) . '</code></td>';
        echo '<td><code>' . $custom_id . '</code></td>';
        echo '<td>' . esc_html($post->post_date_gmt) . '</td>';
        echo '<td>' . esc_html($post->post_date) . '</td>';
        echo '<td><a class="button button-small" href="' . esc_url($post_link) . '">Edit</a></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}

function calculate_similarity($title1, $title2) {
    // Quick exact check
    if (strtolower(trim($title1)) === strtolower(trim($title2))) {
        return 100;
    }
    
    // Clean for comparison
    $clean1 = strtolower(preg_replace('/[^\w\s]/', ' ', $title1));
    $clean1 = preg_replace('/\s+/', ' ', trim($clean1));
    
    $clean2 = strtolower(preg_replace('/[^\w\s]/', ' ', $title2));
    $clean2 = preg_replace('/\s+/', ' ', trim($clean2));
    
    if (empty($clean1) || empty($clean2)) return 0;
    
    similar_text($clean1, $clean2, $percent);
    return round($percent, 1);
}

function add_simple_styles() {
    echo '<style>
        .type-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .type-badge.post { background-color: #0073aa; color: white; }
        .type-badge.shorthand { background-color: #d63638; color: white; }
    </style>';
}

function add_fuzzy_script() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        let allMatches = [];
        
        $('#start-fuzzy').on('click', function() {
            const threshold = $(this).data('threshold');
            $(this).prop('disabled', true).text('Processing...');
            $('#fuzzy-progress').show();
            $('#fuzzy-results').empty();
            allMatches = [];
            
            processFuzzyBatch(threshold, 0);
        });

        function processFuzzyBatch(threshold, offset) {
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'fuzzy_shorthand_posts',
                    threshold: threshold,
                    offset: offset,
                    nonce: '<?php echo wp_create_nonce('fuzzy_shorthand_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        
                        if (data.matches) {
                            allMatches = allMatches.concat(data.matches);
                        }
                        
                        if (data.complete) {
                            // Done processing
                            $('#progress-bar').css('width', '100%');
                            $('#progress-text').text('100%');
                            $('#progress-stats').text('Complete! Found ' + allMatches.length + ' similar matches.');
                            $('#start-fuzzy').prop('disabled', false).text('Find Similar Titles (Shorthand → Posts)');
                            
                            displayFuzzyResults();
                        } else {
                            // Update progress
                            $('#progress-bar').css('width', data.progress + '%');
                            $('#progress-text').text(data.progress + '%');
                            $('#progress-stats').text('Processed: ' + data.processed + '/' + data.total);
                            
                            // Continue
                            setTimeout(() => processFuzzyBatch(threshold, data.processed), 50);
                        }
                    } else {
                        alert('Error processing fuzzy matches');
                        $('#start-fuzzy').prop('disabled', false).text('Find Similar Titles (Shorthand → Posts)');
                    }
                }
            });
        }

        function displayFuzzyResults() {
            if (allMatches.length === 0) {
                $('#fuzzy-results').html('<p>No similar matches found.</p>');
                return;
            }

            let html = '<h3>Found ' + allMatches.length + ' Similar Matches</h3>';
            html += '<div style="max-height: 500px; overflow-y: auto; border: 1px solid #ccd0d4;">';
            html += '<table class="widefat fixed striped"><thead><tr>';
            html += '<th>Shorthand Story</th><th>Similar Post</th><th>Match %</th><th>Actions</th>';
            html += '</tr></thead><tbody>';
            
            allMatches.forEach(function(match) {
                html += '<tr>';
                html += '<td><strong>' + match.shorthand_title + '</strong><br><small>ID: ' + match.shorthand_id + '</small></td>';
                html += '<td><strong>' + match.post_title + '</strong><br><small>ID: ' + match.post_id + '</small></td>';
                html += '<td><span style="font-weight: bold; color: ' + (match.similarity >= 90 ? 'green' : 'orange') + ';">' + match.similarity + '%</span></td>';
                html += '<td><a href="post.php?post=' + match.shorthand_id + '&action=edit" class="button button-small">Edit Shorthand</a> ';
                html += '<a href="post.php?post=' + match.post_id + '&action=edit" class="button button-small">Edit Post</a></td>';
                html += '</tr>';
            });
            
            html += '</tbody></table></div>';
            $('#fuzzy-results').html(html);
        }
    });
    </script>
    <?php
}
?>