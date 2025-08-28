<?php

/**
 * Batched Enhanced Paginated Duplicate Post Checker
 * 
 * Shows only titles where:
 *  - At least two posts share the same title AND the same ACF "id" field
 *    OR
 *  - At least one post in the group has a blank "id"
 * 
 * Features:
 *  - Both 'post' and 'shorthand_story' post types
 *  - Fast exact matching (default)
 *  - Batched fuzzy matching with progress bar for large datasets
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

// AJAX handler for batched fuzzy processing
add_action('wp_ajax_process_fuzzy_batch', 'handle_fuzzy_batch_ajax');

function handle_fuzzy_batch_ajax() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    check_ajax_referer('fuzzy_batch_nonce', 'nonce');

    global $wpdb;
    
    $batch_size = 200;
    $similarity_threshold = intval($_POST['threshold']);
    $offset = intval($_POST['offset']);
    $session_id = sanitize_text_field($_POST['session_id']);
    
    // Get total count for progress calculation
    $total_posts = $wpdb->get_var("
        SELECT COUNT(*) 
        FROM {$wpdb->posts} 
        WHERE post_type IN ('post', 'shorthand_story')
            AND post_status != 'trash'
            AND post_title != ''
    ");
    
    // Get batch of posts to process
    $posts_batch = $wpdb->get_results($wpdb->prepare("
        SELECT ID, post_title, post_type
        FROM {$wpdb->posts}
        WHERE post_type IN ('post', 'shorthand_story')
            AND post_status != 'trash'
            AND post_title != ''
        ORDER BY ID
        LIMIT %d OFFSET %d
    ", $batch_size, $offset));
    
    if (empty($posts_batch)) {
        // Processing complete, return final results
        $results = get_transient('fuzzy_results_' . $session_id) ?: [];
        wp_send_json_success([
            'complete' => true,
            'total_groups' => count($results),
            'results' => $results
        ]);
        return;
    }
    
    // Process this batch against all remaining posts for fuzzy matching
    $batch_groups = process_fuzzy_batch($posts_batch, $similarity_threshold, $session_id);
    
    // Store results in transient (expires in 1 hour)
    $existing_results = get_transient('fuzzy_results_' . $session_id) ?: [];
    $existing_results = array_merge($existing_results, $batch_groups);
    set_transient('fuzzy_results_' . $session_id, $existing_results, HOUR_IN_SECONDS);
    
    $progress = min(100, round((($offset + $batch_size) / $total_posts) * 100, 1));
    
    wp_send_json_success([
        'complete' => false,
        'progress' => $progress,
        'processed' => $offset + count($posts_batch),
        'total' => $total_posts,
        'found_groups' => count($batch_groups),
        'total_groups' => count($existing_results)
    ]);
}

function process_fuzzy_batch($posts_batch, $similarity_threshold, $session_id) {
    global $wpdb;
    
    $found_groups = [];
    $processed_pairs = get_transient('processed_pairs_' . $session_id) ?: [];
    
    foreach ($posts_batch as $post1) {
        // Find potential matches for this post
        $candidates = $wpdb->get_results($wpdb->prepare("
            SELECT ID, post_title, post_type
            FROM {$wpdb->posts}
            WHERE post_type IN ('post', 'shorthand_story')
                AND post_status != 'trash'
                AND post_title != ''
                AND ID > %d
                AND (
                    LEFT(LOWER(post_title), 3) = LEFT(LOWER(%s), 3)
                    OR ABS(CHAR_LENGTH(post_title) - CHAR_LENGTH(%s)) <= GREATEST(CHAR_LENGTH(post_title), CHAR_LENGTH(%s)) * 0.3
                )
        ", $post1->ID, $post1->post_title, $post1->post_title, $post1->post_title));
        
        foreach ($candidates as $post2) {
            $pair_key = $post1->ID . '-' . $post2->ID;
            
            if (isset($processed_pairs[$pair_key])) {
                continue;
            }
            
            $similarity = calculate_title_similarity($post1->post_title, $post2->post_title);
            
            if ($similarity >= $similarity_threshold) {
                $found_groups[] = [
                    'post1_id' => $post1->ID,
                    'post2_id' => $post2->ID,
                    'similarity' => $similarity,
                    'title1' => $post1->post_title,
                    'title2' => $post2->post_title
                ];
            }
            
            $processed_pairs[$pair_key] = true;
        }
    }
    
    // Update processed pairs cache
    set_transient('processed_pairs_' . $session_id, $processed_pairs, HOUR_IN_SECONDS);
    
    return $found_groups;
}

function render_duplicate_post_checker() {
    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;
    echo '<div class="wrap"><h1>Enhanced Duplicate Post Checker</h1>';

    // Handle deletions
    if (isset($_POST['delete_duplicates']) && !empty($_POST['delete_post_ids'])) {
        check_admin_referer('delete_duplicate_posts');
        $to_delete = array_map('intval', $_POST['delete_post_ids']);
        foreach ($to_delete as $post_id) {
            wp_delete_post($post_id, true);
        }
        echo '<div class="notice notice-success"><p>Deleted ' . count($to_delete) . ' post(s).</p></div>';
    }

    $mode = isset($_GET['mode']) ? $_GET['mode'] : 'exact';
    $similarity_threshold = isset($_GET['threshold']) ? intval($_GET['threshold']) : 85;
    $session_id = isset($_GET['session_id']) ? $_GET['session_id'] : '';

    // Mode selection UI
    echo '<div style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; margin: 20px 0;">';
    echo '<h3>Search Mode</h3>';
    echo '<form method="GET" style="margin-bottom: 0;" id="mode-form">';
    echo '<input type="hidden" name="page" value="duplicate-post-checker">';
    echo '<p>';
    echo '<label><input type="radio" name="mode" value="exact"' . ($mode === 'exact' ? ' checked' : '') . '> <strong>Exact Match</strong> - Only exact title duplicates (instant)</label><br>';
    echo '<label><input type="radio" name="mode" value="fuzzy"' . ($mode === 'fuzzy' ? ' checked' : '') . '> <strong>Fuzzy Match</strong> - Similar titles across post types (batched processing)</label>';
    echo '</p>';
    if ($mode === 'fuzzy') {
        echo '<p><label>Similarity threshold: <input type="number" name="threshold" value="' . $similarity_threshold . '" min="70" max="95" style="width: 60px;">%</label></p>';
    }
    echo '<p><input type="submit" class="button button-primary" value="Run Analysis"></p>';
    echo '</form>';
    echo '</div>';

    if ($mode === 'exact') {
        render_exact_results($wpdb);
    } else {
        render_fuzzy_results($wpdb, $similarity_threshold, $session_id);
    }

    echo '</div>'; // .wrap
    add_duplicate_checker_styles();
    add_fuzzy_processing_script();
}

function render_exact_results($wpdb) {
    $start_time = microtime(true);
    $filtered_groups = get_exact_duplicate_groups($wpdb);
    $processing_time = round(microtime(true) - $start_time, 2);

    echo '<p><em>Processing time: ' . $processing_time . ' seconds</em></p>';

    if (empty($filtered_groups)) {
        echo '<p>No exact duplicate groups found matching the ID criteria.</p>';
        return;
    }

    render_results_table($filtered_groups, 'exact');
}

function render_fuzzy_results($wpdb, $similarity_threshold, $session_id) {
    // Check if we have cached results
    if ($session_id && get_transient('fuzzy_complete_' . $session_id)) {
        $fuzzy_pairs = get_transient('fuzzy_results_' . $session_id) ?: [];
        if (!empty($fuzzy_pairs)) {
            echo '<div class="notice notice-success"><p>Using cached fuzzy matching results. <a href="?page=duplicate-post-checker&mode=fuzzy&threshold=' . $similarity_threshold . '">Start fresh analysis</a></p></div>';
            $filtered_groups = process_fuzzy_results($fuzzy_pairs);
            render_results_table($filtered_groups, 'fuzzy');
            return;
        }
    }

    // Show fuzzy processing interface
    if (!$session_id) {
        $session_id = uniqid('fuzzy_', true);
    }
    
    echo '<div id="fuzzy-processor">';
    echo '<h3>Fuzzy Matching Processor</h3>';
    echo '<p>This will process your posts in batches to find similar titles across post types.</p>';
    echo '<div id="progress-container" style="display: none;">';
    echo '<div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0;">';
    echo '<h4>Processing...</h4>';
    echo '<div class="progress-bar-container" style="width: 100%; background: #f0f0f1; border: 1px solid #ccd0d4; height: 30px; position: relative;">';
    echo '<div id="progress-bar" style="height: 100%; background: linear-gradient(45deg, #0073aa, #005a87); width: 0%; transition: width 0.3s;"></div>';
    echo '<div id="progress-text" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-weight: bold; color: #000;">0%</div>';
    echo '</div>';
    echo '<div id="progress-stats" style="margin-top: 10px; font-size: 14px; color: #666;"></div>';
    echo '<button id="stop-processing" class="button button-secondary" style="margin-top: 10px;">Stop Processing</button>';
    echo '</div>';
    echo '</div>';
    echo '<div id="results-container"></div>';
    echo '<button id="start-fuzzy" class="button button-primary" data-threshold="' . $similarity_threshold . '" data-session="' . $session_id . '">Start Fuzzy Analysis</button>';
    echo '</div>';
}

function process_fuzzy_results($fuzzy_pairs) {
    // Group the pairs into connected components
    $grouped_posts = group_similar_posts($fuzzy_pairs);
    
    // Apply ID-based filtering
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

function render_results_table($filtered_groups, $mode) {
    $groups_per_page = $mode === 'fuzzy' ? 10 : 20;
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($paged - 1) * $groups_per_page;

    $total_groups = count($filtered_groups);
    $total_pages = (int) ceil($total_groups / $groups_per_page);
    $groups_for_page = array_slice($filtered_groups, $offset, $groups_per_page);

    echo '<p>Found <strong>' . $total_groups . '</strong> duplicate groups. Showing page ' . $paged . ' of ' . $total_pages . '.</p>';

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

// Keep all the existing helper functions from before
function get_exact_duplicate_groups($wpdb) {
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

function process_post_group($post_ids) {
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
    
    $same_id_group = false;
    foreach ($id_counts as $count) {
        if ($count > 1) {
            $same_id_group = true;
            break;
        }
    }
    
    if ($has_blank || $same_id_group) {
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

function group_similar_posts($similarity_pairs) {
    $groups = [];
    $post_to_group = [];
    
    foreach ($similarity_pairs as $pair) {
        $id1 = $pair['post1_id'];
        $id2 = $pair['post2_id'];
        
        $group1 = isset($post_to_group[$id1]) ? $post_to_group[$id1] : null;
        $group2 = isset($post_to_group[$id2]) ? $post_to_group[$id2] : null;
        
        if ($group1 === null && $group2 === null) {
            $new_group_id = count($groups);
            $groups[$new_group_id] = [$id1, $id2];
            $post_to_group[$id1] = $new_group_id;
            $post_to_group[$id2] = $new_group_id;
        } elseif ($group1 !== null && $group2 === null) {
            $groups[$group1][] = $id2;
            $post_to_group[$id2] = $group1;
        } elseif ($group1 === null && $group2 !== null) {
            $groups[$group2][] = $id1;
            $post_to_group[$id1] = $group2;
        } elseif ($group1 !== $group2) {
            $groups[$group1] = array_merge($groups[$group1], $groups[$group2]);
            foreach ($groups[$group2] as $post_id) {
                $post_to_group[$post_id] = $group1;
            }
            unset($groups[$group2]);
        }
    }
    
    return array_values($groups);
}

function calculate_title_similarity($title1, $title2) {
    $clean1 = clean_title_for_comparison($title1);
    $clean2 = clean_title_for_comparison($title2);
    
    if (empty($clean1) || empty($clean2)) {
        return 0;
    }
    
    if ($clean1 === $clean2) {
        return 100;
    }
    
    similar_text($clean1, $clean2, $percent);
    return round($percent, 1);
}

function clean_title_for_comparison($title) {
    static $cache = [];
    
    if (isset($cache[$title])) {
        return $cache[$title];
    }
    
    $original = $title;
    $title = strtolower(preg_replace('/[^\w\s]/', ' ', $title));
    $title = preg_replace('/\s+/', ' ', trim($title));
    
    $cache[$original] = $title;
    
    if (count($cache) > 1000) {
        $cache = array_slice($cache, 500, null, true);
    }
    
    return $title;
}

function render_duplicate_group($group_data) {
    $posts = $group_data['posts'];
    $id_map = $group_data['id_map'];
    $has_blank = $group_data['has_blank'];
    $id_counts = $group_data['id_counts'];

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

    $titles = array_unique(array_map(function($p) { return $p->post_title; }, $to_show));
    $group_title = count($titles) === 1 ? $titles[0] : 'Similar Titles Group (' . count($titles) . ' variations)';

    echo '<h2 style="margin-top:2em;">' . esc_html($group_title) . '</h2>';
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th style="width:1%;"></th><th>ID</th><th>Type</th><th>Title</th><th>Slug</th><th>Import ID</th><th>Created</th><th>Published</th><th>Release Date</th><th>Actions</th></tr></thead><tbody>';

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
        .regular-post { background-color: #0073aa; color: white; }
        .shorthand-story { background-color: #d63638; color: white; }
        .progress-bar-container { border-radius: 4px; overflow: hidden; }
        #progress-bar { border-radius: 4px; }
    </style>';
}

function add_fuzzy_processing_script() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        let processingActive = false;
        let currentOffset = 0;

        $('#start-fuzzy').on('click', function() {
            if (processingActive) return;
            
            const threshold = $(this).data('threshold');
            const sessionId = $(this).data('session');
            
            processingActive = true;
            currentOffset = 0;
            $(this).prop('disabled', true).text('Processing...');
            $('#progress-container').show();
            $('#results-container').empty();
            
            processBatch(threshold, sessionId, currentOffset);
        });

        $('#stop-processing').on('click', function() {
            processingActive = false;
            $('#start-fuzzy').prop('disabled', false).text('Start Fuzzy Analysis');
            $('#progress-container').hide();
        });

        function processBatch(threshold, sessionId, offset) {
            if (!processingActive) return;

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'process_fuzzy_batch',
                    threshold: threshold,
                    session_id: sessionId,
                    offset: offset,
                    nonce: '<?php echo wp_create_nonce('fuzzy_batch_nonce'); ?>'
                },
                success: function(response) {
                    if (!processingActive) return;

                    if (response.success) {
                        const data = response.data;
                        
                        if (data.complete) {
                            // Processing complete
                            $('#progress-bar').css('width', '100%');
                            $('#progress-text').text('100%');
                            $('#progress-stats').text('Processing complete! Found ' + data.total_groups + ' groups.');
                            
                            setTimeout(() => {
                                window.location.href = '?page=duplicate-post-checker&mode=fuzzy&threshold=' + threshold + '&session_id=' + sessionId;
                            }, 2000);
                        } else {
                            // Update progress
                            $('#progress-bar').css('width', data.progress + '%');
                            $('#progress-text').text(data.progress + '%');
                            $('#progress-stats').text(
                                'Processed: ' + data.processed + '/' + data.total + 
                                ' | Found: ' + data.total_groups + ' groups'
                            );
                            
                            // Process next batch
                            setTimeout(() => {
                                processBatch(threshold, sessionId, data.processed);
                            }, 100);
                        }
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                        processingActive = false;
                        $('#start-fuzzy').prop('disabled', false).text('Start Fuzzy Analysis');
                    }
                },
                error: function() {
                    alert('AJAX error occurred');
                    processingActive = false;
                    $('#start-fuzzy').prop('disabled', false).text('Start Fuzzy Analysis');
                }
            });
        }
    });
    </script>
    <?php
}
?>