<?php
/**
 * User Merge & Cleanup Utility
 * Reassigns content from Expert Users to Personnel Users and optionally deletes the Expert Users.
 * Uses AJAX batching with real-time console output.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ---------------------------------------------------------------------------------
 * 1. Configuration: Post Types and Fields to Check
 * ---------------------------------------------------------------------------------
 */

function get_user_reference_fields() {
    return [
        'post' => [
            ['repeater' => 'authors', 'user_field' => 'user'],
            ['repeater' => 'artists', 'user_field' => 'user'],
            ['repeater' => 'experts', 'user_field' => 'user'],
        ],
        'publications' => [
            ['repeater' => 'authors', 'user_field' => 'user'],
            ['repeater' => 'translator', 'user_field' => 'user'],
            ['repeater' => 'artists', 'user_field' => 'user'],
        ],
        'shorthand_story' => [
            ['repeater' => 'authors', 'user_field' => 'user'],
            ['repeater' => 'artists', 'user_field' => 'user'],
        ],
    ];
}

/**
 * ---------------------------------------------------------------------------------
 * 2. CSV Parsing Functions
 * ---------------------------------------------------------------------------------
 */

function parse_merge_csv($csv_content) {
    $lines = explode("\n", trim($csv_content));
    if (count($lines) < 2) {
        return new WP_Error('invalid_csv', 'CSV must have a header row and at least one data row.');
    }

    $header = str_getcsv(array_shift($lines));
    $header = array_map('trim', $header);
    $header = array_map('strtolower', $header);

    // Find required column indices
    $personnel_id_idx = array_search('personnel_id', $header);
    $expert_source_idx = array_search('expert_source_id', $header);
    $expert_writer_idx = array_search('expert_writer_id', $header);

    if ($personnel_id_idx === false) {
        return new WP_Error('missing_column', 'CSV must contain a "personnel_id" column.');
    }
    if ($expert_source_idx === false && $expert_writer_idx === false) {
        return new WP_Error('missing_column', 'CSV must contain either "expert_source_id" or "expert_writer_id" column.');
    }

    $pairs = [];
    $line_num = 1;

    foreach ($lines as $line) {
        $line_num++;
        if (empty(trim($line))) continue;

        $data = str_getcsv($line);
        $personnel_id = isset($data[$personnel_id_idx]) ? trim($data[$personnel_id_idx]) : '';
        $source_id = ($expert_source_idx !== false && isset($data[$expert_source_idx])) ? trim($data[$expert_source_idx]) : '';
        $writer_id = ($expert_writer_idx !== false && isset($data[$expert_writer_idx])) ? trim($data[$expert_writer_idx]) : '';

        if (empty($personnel_id)) {
            continue; // Skip rows without personnel_id
        }

        if (empty($source_id) && empty($writer_id)) {
            continue; // Skip rows without any expert identifier
        }

        $pairs[] = [
            'line' => $line_num,
            'personnel_id' => $personnel_id,
            'source_expert_id' => $source_id,
            'writer_id' => $writer_id,
        ];
    }

    return $pairs;
}

/**
 * ---------------------------------------------------------------------------------
 * 3. User Lookup Functions
 * ---------------------------------------------------------------------------------
 */

function find_personnel_user_by_personnel_id($personnel_id) {
    if (empty($personnel_id)) return null;
    
    $users = get_users([
        'meta_key' => 'personnel_id',
        'meta_value' => $personnel_id,
        'number' => 1,
        'fields' => 'ID',
    ]);

    return !empty($users) ? intval($users[0]) : null;
}

function find_expert_user_by_ids($source_expert_id, $writer_id) {
    // Try source_expert_id first
    if (!empty($source_expert_id)) {
        $users = get_users([
            'meta_key' => 'source_expert_id',
            'meta_value' => $source_expert_id,
            'number' => 1,
            'fields' => 'ID',
        ]);
        if (!empty($users)) {
            return intval($users[0]);
        }
    }

    // Try writer_id
    if (!empty($writer_id)) {
        $users = get_users([
            'meta_key' => 'writer_id',
            'meta_value' => $writer_id,
            'number' => 1,
            'fields' => 'ID',
        ]);
        if (!empty($users)) {
            return intval($users[0]);
        }
    }

    return null;
}

/**
 * ---------------------------------------------------------------------------------
 * 4. Content Reassignment Functions
 * ---------------------------------------------------------------------------------
 */

function find_posts_referencing_user($expert_wp_id) {
    global $wpdb;
    
    $field_config = get_user_reference_fields();
    $found_posts = [];

    foreach ($field_config as $post_type => $fields) {
        foreach ($fields as $field_info) {
            $repeater = $field_info['repeater'];
            $user_field = $field_info['user_field'];

            // Query for posts that have this user in any row of the repeater
            // ACF stores repeater user fields as: {repeater}_{index}_{user_field}
            $meta_key_pattern = $repeater . '_%_' . $user_field;
            
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT DISTINCT p.ID, p.post_title, p.post_type, pm.meta_key
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE p.post_type = %s
                 AND p.post_status != 'trash'
                 AND pm.meta_key LIKE %s
                 AND pm.meta_value = %s",
                $post_type,
                $wpdb->esc_like($repeater) . '_%_' . $user_field,
                $expert_wp_id
            ));

            foreach ($results as $row) {
                $key = $row->ID . '_' . $row->meta_key;
                if (!isset($found_posts[$key])) {
                    // Extract row index from meta_key
                    preg_match('/' . preg_quote($repeater, '/') . '_(\d+)_' . preg_quote($user_field, '/') . '/', $row->meta_key, $matches);
                    $row_index = isset($matches[1]) ? intval($matches[1]) : null;

                    $found_posts[$key] = [
                        'post_id' => $row->ID,
                        'post_title' => $row->post_title,
                        'post_type' => $row->post_type,
                        'repeater' => $repeater,
                        'user_field' => $user_field,
                        'meta_key' => $row->meta_key,
                        'row_index' => $row_index,
                    ];
                }
            }
        }
    }

    return array_values($found_posts);
}

function reassign_user_in_post($post_id, $meta_key, $old_user_id, $new_user_id, $dry_run = false) {
    $current_value = get_post_meta($post_id, $meta_key, true);
    
    if (intval($current_value) !== intval($old_user_id)) {
        return [
            'success' => false,
            'message' => "Meta key {$meta_key} value ({$current_value}) doesn't match expected ({$old_user_id})"
        ];
    }

    if ($dry_run) {
        return [
            'success' => true,
            'message' => "Would update {$meta_key} from {$old_user_id} to {$new_user_id}",
            'dry_run' => true
        ];
    }

    $result = update_post_meta($post_id, $meta_key, $new_user_id);
    
    if ($result !== false) {
        // Clear all caches for this post so front-end reflects the change immediately
        clean_post_cache($post_id);
        
        // Touch the post to trigger any save hooks (updates modified date too)
        wp_update_post([
            'ID' => $post_id,
            'post_modified' => current_time('mysql'),
            'post_modified_gmt' => current_time('mysql', 1),
        ]);
    }
    
    return [
        'success' => $result !== false,
        'message' => $result !== false 
            ? "Updated {$meta_key} from {$old_user_id} to {$new_user_id}"
            : "Failed to update {$meta_key}"
    ];
}

/**
 * ---------------------------------------------------------------------------------
 * 5. AJAX Handlers
 * ---------------------------------------------------------------------------------
 */

add_action('wp_ajax_user_merge_parse_csv', 'ajax_user_merge_parse_csv');
function ajax_user_merge_parse_csv() {
    check_ajax_referer('user_merge_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    $csv_content = isset($_POST['csv_content']) ? wp_unslash($_POST['csv_content']) : '';
    
    if (empty($csv_content)) {
        wp_send_json_error('No CSV content provided.');
    }

    $pairs = parse_merge_csv($csv_content);
    
    if (is_wp_error($pairs)) {
        wp_send_json_error($pairs->get_error_message());
    }

    if (empty($pairs)) {
        wp_send_json_error('No valid user pairs found in CSV.');
    }

    // Validate pairs and resolve WordPress user IDs
    $validated_pairs = [];
    $errors = [];

    foreach ($pairs as $pair) {
        $personnel_wp_id = find_personnel_user_by_personnel_id($pair['personnel_id']);
        $expert_wp_id = find_expert_user_by_ids($pair['source_expert_id'], $pair['writer_id']);

        if (!$personnel_wp_id) {
            $errors[] = "Line {$pair['line']}: Personnel ID '{$pair['personnel_id']}' not found in database.";
            continue;
        }

        if (!$expert_wp_id) {
            $expert_ids = array_filter([$pair['source_expert_id'], $pair['writer_id']]);
            $errors[] = "Line {$pair['line']}: Expert with ID(s) '" . implode("' or '", $expert_ids) . "' not found in database.";
            continue;
        }

        if ($personnel_wp_id === $expert_wp_id) {
            $errors[] = "Line {$pair['line']}: Personnel and Expert resolve to the same WordPress user ({$personnel_wp_id}).";
            continue;
        }

        $validated_pairs[] = [
            'line' => $pair['line'],
            'personnel_id' => $pair['personnel_id'],
            'personnel_wp_id' => $personnel_wp_id,
            'source_expert_id' => $pair['source_expert_id'],
            'writer_id' => $pair['writer_id'],
            'expert_wp_id' => $expert_wp_id,
        ];
    }

    set_transient('user_merge_pairs', $validated_pairs, HOUR_IN_SECONDS);
    set_transient('user_merge_index', 0, HOUR_IN_SECONDS);

    wp_send_json_success([
        'total_pairs' => count($validated_pairs),
        'validation_errors' => $errors,
        'message' => "Parsed " . count($validated_pairs) . " valid user pairs. " . count($errors) . " rows had errors."
    ]);
}

/**
 * Dry Run Preview - generates a full preview of what will happen
 */
add_action('wp_ajax_user_merge_dry_run_preview', 'ajax_user_merge_dry_run_preview');
function ajax_user_merge_dry_run_preview() {
    check_ajax_referer('user_merge_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    $pairs = get_transient('user_merge_pairs');
    if (!$pairs) {
        wp_send_json_error('Session expired. Please re-upload the CSV.');
    }

    $delete_experts = isset($_POST['delete_experts']) && $_POST['delete_experts'] === 'true';
    
    $preview_data = [];
    $totals = [
        'pairs' => count($pairs),
        'posts_to_update' => 0,
        'experts_to_delete' => 0,
    ];

    foreach ($pairs as $pair) {
        $personnel_user = get_user_by('ID', $pair['personnel_wp_id']);
        $expert_user = get_user_by('ID', $pair['expert_wp_id']);
        
        $personnel_name = $personnel_user ? $personnel_user->display_name : "Unknown (WP#{$pair['personnel_wp_id']})";
        $expert_name = $expert_user ? $expert_user->display_name : "Unknown (WP#{$pair['expert_wp_id']})";
        
        // Find affected posts
        $affected_posts = find_posts_referencing_user($pair['expert_wp_id']);
        $totals['posts_to_update'] += count($affected_posts);
        
        // Format affected posts for display
        $posts_display = [];
        foreach ($affected_posts as $post_ref) {
            $posts_display[] = [
                'id' => $post_ref['post_id'],
                'title' => $post_ref['post_title'],
                'type' => $post_ref['post_type'],
                'field' => $post_ref['repeater'] . '[' . $post_ref['row_index'] . ']',
                'edit_url' => get_edit_post_link($post_ref['post_id'], 'raw'),
            ];
        }
        
        $preview_data[] = [
            'personnel' => [
                'wp_id' => $pair['personnel_wp_id'],
                'name' => $personnel_name,
                'personnel_id' => $pair['personnel_id'],
                'edit_url' => get_edit_user_link($pair['personnel_wp_id']),
            ],
            'expert' => [
                'wp_id' => $pair['expert_wp_id'],
                'name' => $expert_name,
                'source_id' => $pair['source_expert_id'],
                'writer_id' => $pair['writer_id'],
                'edit_url' => get_edit_user_link($pair['expert_wp_id']),
                'will_delete' => $delete_experts,
            ],
            'affected_posts' => $posts_display,
            'post_count' => count($affected_posts),
        ];
        
        if ($delete_experts) {
            $totals['experts_to_delete']++;
        }
    }

    wp_send_json_success([
        'preview' => $preview_data,
        'totals' => $totals,
        'delete_experts' => $delete_experts,
    ]);
}

add_action('wp_ajax_user_merge_process_batch', 'ajax_user_merge_process_batch');
function ajax_user_merge_process_batch() {
    check_ajax_referer('user_merge_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    $dry_run = isset($_POST['dry_run']) && $_POST['dry_run'] === 'true';
    $delete_experts = isset($_POST['delete_experts']) && $_POST['delete_experts'] === 'true';
    $batch_size = intval($_POST['batch_size'] ?? 5);

    $pairs = get_transient('user_merge_pairs');
    $current_index = get_transient('user_merge_index') ?: 0;

    if (!$pairs) {
        wp_send_json_error('Session expired. Please re-upload the CSV.');
    }

    $total_pairs = count($pairs);
    $processed = 0;
    $log = [];
    $stats = [
        'posts_updated' => 0,
        'experts_deleted' => 0,
        'errors' => 0
    ];

    for ($i = $current_index; $i < $current_index + $batch_size && $i < $total_pairs; $i++) {
        $pair = $pairs[$i];
        $processed++;

        $prefix = $dry_run ? "[DRY RUN] " : "";
        $log[] = ['type' => 'info', 'message' => "{$prefix}Processing pair {$i}/{$total_pairs}: Personnel ID {$pair['personnel_id']} (WP#{$pair['personnel_wp_id']}) <- Expert WP#{$pair['expert_wp_id']}"];

        // Find all posts referencing this expert user
        $affected_posts = find_posts_referencing_user($pair['expert_wp_id']);

        if (empty($affected_posts)) {
            $log[] = ['type' => 'info', 'message' => "{$prefix}  No posts found referencing Expert WP#{$pair['expert_wp_id']}"];
        } else {
            $log[] = ['type' => 'info', 'message' => "{$prefix}  Found " . count($affected_posts) . " field reference(s) to update"];

            foreach ($affected_posts as $post_ref) {
                $result = reassign_user_in_post(
                    $post_ref['post_id'],
                    $post_ref['meta_key'],
                    $pair['expert_wp_id'],
                    $pair['personnel_wp_id'],
                    $dry_run
                );

                if ($result['success']) {
                    $stats['posts_updated']++;
                    $log[] = [
                        'type' => 'success',
                        'message' => "{$prefix}  ✓ {$post_ref['post_type']} #{$post_ref['post_id']} \"{$post_ref['post_title']}\" - {$post_ref['repeater']}[{$post_ref['row_index']}]: {$result['message']}"
                    ];
                } else {
                    $stats['errors']++;
                    $log[] = [
                        'type' => 'error',
                        'message' => "{$prefix}  ✗ {$post_ref['post_type']} #{$post_ref['post_id']} \"{$post_ref['post_title']}\": {$result['message']}"
                    ];
                }
            }
        }

        // Delete expert user if requested
        if ($delete_experts) {
            $expert_user = get_user_by('ID', $pair['expert_wp_id']);
            if ($expert_user) {
                if ($dry_run) {
                    $log[] = ['type' => 'warning', 'message' => "{$prefix}  Would DELETE Expert User WP#{$pair['expert_wp_id']} ({$expert_user->display_name})"];
                    $stats['experts_deleted']++;
                } else {
                    // Reassign any posts authored by this user to the personnel user
                    $deleted = wp_delete_user($pair['expert_wp_id'], $pair['personnel_wp_id']);
                    if ($deleted) {
                        $stats['experts_deleted']++;
                        $log[] = ['type' => 'warning', 'message' => "{$prefix}  DELETED Expert User WP#{$pair['expert_wp_id']} ({$expert_user->display_name})"];
                    } else {
                        $stats['errors']++;
                        $log[] = ['type' => 'error', 'message' => "{$prefix}  Failed to delete Expert User WP#{$pair['expert_wp_id']}"];
                    }
                }
            }
        }
    }

    $new_index = $current_index + $processed;
    set_transient('user_merge_index', $new_index, HOUR_IN_SECONDS);

    $is_complete = $new_index >= $total_pairs;
    $progress = $total_pairs > 0 ? round(($new_index / $total_pairs) * 100, 1) : 100;

    if ($is_complete) {
        delete_transient('user_merge_pairs');
        delete_transient('user_merge_index');
    }

    wp_send_json_success([
        'processed' => $processed,
        'current_index' => $new_index,
        'total' => $total_pairs,
        'progress' => $progress,
        'is_complete' => $is_complete,
        'log' => $log,
        'stats' => $stats,
        'dry_run' => $dry_run
    ]);
}

/**
 * ---------------------------------------------------------------------------------
 * 6. Admin Page Rendering
 * ---------------------------------------------------------------------------------
 */

add_action('admin_menu', function() {
    add_users_page(
        'User Merge Utility',
        'User Merge',
        'manage_options',
        'user-merge-utility',
        'render_user_merge_page'
    );
});

function render_user_merge_page() {
    $nonce = wp_create_nonce('user_merge_nonce');
    ?>
    <div class="wrap">
        <h1>🔀 User Merge & Cleanup Utility</h1>
        <p>Reassigns content from Expert Users to Personnel Users based on a CSV mapping file.</p>

        <div style="background:#fff; padding:20px; border:1px solid #ddd; margin-bottom:20px; max-width:900px;">
            <h2 style="margin-top:0;">Step 1: Upload CSV</h2>
            <p>Upload a CSV file with columns: <code>personnel_id</code>, <code>expert_source_id</code>, <code>expert_writer_id</code></p>
            <p><em>Tip: Use the Duplicate Detection tool to generate this CSV.</em></p>
            
            <input type="file" id="csv-file" accept=".csv" style="margin-bottom:10px;"><br>
            <button id="parse-csv" class="button button-primary">Parse CSV</button>
        </div>

        <div id="csv-preview" style="display:none; background:#fff; padding:20px; border:1px solid #ddd; margin-bottom:20px; max-width:900px;">
            <h2 style="margin-top:0;">Step 2: Review Parsed Data</h2>
            <div id="csv-stats"></div>
            <div id="csv-errors"></div>
        </div>

        <div id="merge-settings" style="display:none; background:#fff; padding:20px; border:1px solid #ddd; margin-bottom:20px; max-width:900px;">
            <h2 style="margin-top:0;">Step 3: Configure & Run</h2>
            <table class="form-table">
                <tr>
                    <th>Delete Expert Users?</th>
                    <td>
                        <label>
                            <input type="checkbox" id="delete-experts">
                            Delete Expert User accounts after reassigning their content
                        </label>
                        <p class="description" style="color:#d63638;">⚠️ This permanently deletes the user accounts!</p>
                    </td>
                </tr>
                <tr>
                    <th>Batch Size</th>
                    <td>
                        <input type="number" id="batch-size" value="5" min="1" max="20" style="width:80px;">
                        <p class="description">User pairs to process per batch (lower = more stable)</p>
                    </td>
                </tr>
            </table>
            <p style="margin-top:20px;">
                <button id="preview-merge" class="button button-secondary button-hero">👁️ Preview Changes</button>
                <button id="start-merge" class="button button-primary button-hero" style="display:none;">🚀 Run Merge</button>
                <button id="stop-merge" class="button button-secondary" style="display:none;">Stop</button>
            </p>
        </div>

        <!-- Dry Run Preview Table -->
        <div id="preview-section" style="display:none; background:#fff; padding:20px; border:1px solid #ddd; margin-bottom:20px; max-width:1200px;">
            <h2 style="margin-top:0;">📋 Preview: Changes That Will Be Made</h2>
            <div id="preview-summary" style="background:#f0f6fc; padding:15px; margin-bottom:20px; border-left:4px solid #2271b1;"></div>
            <div id="preview-table-container" style="max-height:600px; overflow-y:auto;"></div>
            <p style="margin-top:20px; padding-top:15px; border-top:1px solid #ddd;">
                <button id="confirm-merge" class="button button-primary button-hero">✅ Confirm & Run Merge</button>
                <button id="cancel-preview" class="button">Cancel</button>
            </p>
        </div>

        <div id="progress-section" style="display:none; max-width:900px;">
            <h2>Progress</h2>
            <div style="background:#f0f0f0; border-radius:4px; height:30px; margin-bottom:10px;">
                <div id="progress-bar" style="background:#0073aa; height:100%; border-radius:4px; width:0%; transition:width 0.3s;"></div>
            </div>
            <p id="progress-text">Initializing...</p>
            <div id="stats-display" style="display:flex; gap:20px; margin:10px 0;">
                <div><strong>Posts Updated:</strong> <span id="stat-posts">0</span></div>
                <div><strong>Experts Deleted:</strong> <span id="stat-deleted">0</span></div>
                <div><strong>Errors:</strong> <span id="stat-errors">0</span></div>
            </div>
        </div>

        <div id="console-section" style="display:none; max-width:900px; margin-top:20px;">
            <h2>Console Output</h2>
            <div id="console-output" style="background:#1e1e1e; color:#d4d4d4; font-family:monospace; font-size:12px; height:300px; overflow-y:auto; padding:10px; border-radius:4px;"></div>
        </div>

        <div id="completion-section" style="display:none; max-width:900px; margin-top:20px;">
            <div class="notice notice-success" style="padding:15px;">
                <h3 style="margin-top:0;">✅ Process Complete</h3>
                <div id="final-stats"></div>
            </div>
        </div>
    </div>

    <style>
        .preview-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .preview-table th {
            background: #f0f0f1;
            padding: 12px 10px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #c3c4c7;
            position: sticky;
            top: 0;
        }
        .preview-table td {
            padding: 10px;
            border-bottom: 1px solid #dcdcde;
            vertical-align: top;
        }
        .preview-table tr:hover {
            background: #f6f7f7;
        }
        .preview-table .user-cell {
            min-width: 180px;
        }
        .preview-table .user-name {
            font-weight: 600;
            color: #1d2327;
        }
        .preview-table .user-meta {
            font-size: 11px;
            color: #646970;
            margin-top: 3px;
        }
        .preview-table .posts-cell {
            max-width: 400px;
        }
        .preview-table .post-item {
            background: #f0f0f1;
            padding: 4px 8px;
            margin: 2px 0;
            border-radius: 3px;
            font-size: 12px;
        }
        .preview-table .post-item a {
            color: #2271b1;
            text-decoration: none;
        }
        .preview-table .post-item a:hover {
            text-decoration: underline;
        }
        .preview-table .post-type {
            background: #dcdcde;
            padding: 1px 5px;
            border-radius: 2px;
            font-size: 10px;
            text-transform: uppercase;
            margin-right: 5px;
        }
        .preview-table .no-posts {
            color: #646970;
            font-style: italic;
        }
        .preview-table .delete-badge {
            background: #d63638;
            color: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            margin-top: 5px;
            display: inline-block;
        }
        .preview-table .arrow-cell {
            text-align: center;
            font-size: 20px;
            color: #2271b1;
        }
    </style>

    <script>
    jQuery(document).ready(function($) {
        const nonce = '<?php echo $nonce; ?>';
        let isRunning = false;
        let shouldStop = false;
        let totalStats = {posts: 0, deleted: 0, errors: 0};

        function log(message, type = 'info') {
            const $console = $('#console-output');
            const time = new Date().toLocaleTimeString();
            const colors = {
                info: '#9cdcfe',
                success: '#4ec9b0',
                warning: '#dcdcaa',
                error: '#f14c4c'
            };
            const color = colors[type] || colors.info;
            $console.append(`<div style="color:${color}">[${time}] ${message}</div>`);
            $console.scrollTop($console[0].scrollHeight);
        }

        function updateProgress(percent, text) {
            $('#progress-bar').css('width', percent + '%');
            $('#progress-text').text(text);
        }

        function updateStats(stats) {
            totalStats.posts += stats.posts_updated || 0;
            totalStats.deleted += stats.experts_deleted || 0;
            totalStats.errors += stats.errors || 0;
            $('#stat-posts').text(totalStats.posts);
            $('#stat-deleted').text(totalStats.deleted);
            $('#stat-errors').text(totalStats.errors);
        }

        // Parse CSV
        $('#parse-csv').on('click', function() {
            const file = $('#csv-file')[0].files[0];
            if (!file) {
                alert('Please select a CSV file.');
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const content = e.target.result;
                
                $.post(ajaxurl, {
                    action: 'user_merge_parse_csv',
                    nonce: nonce,
                    csv_content: content
                }, function(response) {
                    if (response.success) {
                        const data = response.data;
                        $('#csv-stats').html(`<p><strong>${data.total_pairs}</strong> valid user pairs found.</p>`);
                        
                        if (data.validation_errors.length > 0) {
                            let errHtml = '<p style="color:#d63638;"><strong>Validation Warnings:</strong></p><ul style="color:#d63638; font-size:12px;">';
                            data.validation_errors.forEach(err => {
                                errHtml += `<li>${err}</li>`;
                            });
                            errHtml += '</ul>';
                            $('#csv-errors').html(errHtml);
                        } else {
                            $('#csv-errors').html('<p style="color:#00a32a;">All rows validated successfully.</p>');
                        }

                        $('#csv-preview').show();
                        if (data.total_pairs > 0) {
                            $('#merge-settings').show();
                        }
                    } else {
                        alert('Error parsing CSV: ' + response.data);
                    }
                });
            };
            reader.readAsText(file);
        });

        // Preview Changes (Dry Run)
        $('#preview-merge').on('click', function() {
            const $btn = $(this);
            $btn.prop('disabled', true).text('Loading preview...');

            $.post(ajaxurl, {
                action: 'user_merge_dry_run_preview',
                nonce: nonce,
                delete_experts: $('#delete-experts').is(':checked') ? 'true' : 'false'
            }, function(response) {
                $btn.prop('disabled', false).text('👁️ Preview Changes');

                if (!response.success) {
                    alert('Error: ' + response.data);
                    return;
                }

                const data = response.data;
                renderPreviewTable(data.preview, data.totals, data.delete_experts);
                $('#preview-section').show();
                $('#merge-settings').hide();
            });
        });

        function renderPreviewTable(preview, totals, deleteExperts) {
            // Summary
            let summaryHtml = `
                <strong>Summary of Changes:</strong><br>
                • <strong>${totals.pairs}</strong> user pairs will be processed<br>
                • <strong>${totals.posts_to_update}</strong> post references will be updated
            `;
            if (deleteExperts) {
                summaryHtml += `<br>• <strong style="color:#d63638;">${totals.experts_to_delete}</strong> expert users will be <strong style="color:#d63638;">DELETED</strong>`;
            }
            $('#preview-summary').html(summaryHtml);

            // Table
            let tableHtml = `
                <table class="preview-table">
                    <thead>
                        <tr>
                            <th class="user-cell">Expert User (Source)</th>
                            <th class="arrow-cell"></th>
                            <th class="user-cell">Personnel User (Target)</th>
                            <th class="posts-cell">Affected Posts</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            preview.forEach(item => {
                const expert = item.expert;
                const personnel = item.personnel;
                
                // Expert user cell
                let expertHtml = `
                    <div class="user-name">${escapeHtml(expert.name)}</div>
                    <div class="user-meta">
                        WP ID: ${expert.wp_id}<br>
                        ${expert.source_id ? `Source ID: ${expert.source_id}<br>` : ''}
                        ${expert.writer_id ? `Writer ID: ${expert.writer_id}<br>` : ''}
                        <a href="${expert.edit_url}" target="_blank">Edit User</a>
                    </div>
                `;
                if (expert.will_delete) {
                    expertHtml += `<div class="delete-badge">WILL BE DELETED</div>`;
                }

                // Personnel user cell
                let personnelHtml = `
                    <div class="user-name">${escapeHtml(personnel.name)}</div>
                    <div class="user-meta">
                        WP ID: ${personnel.wp_id}<br>
                        Personnel ID: ${personnel.personnel_id}<br>
                        <a href="${personnel.edit_url}" target="_blank">Edit User</a>
                    </div>
                `;

                // Affected posts cell
                let postsHtml = '';
                if (item.affected_posts.length === 0) {
                    postsHtml = '<span class="no-posts">No posts to update</span>';
                } else {
                    item.affected_posts.forEach(post => {
                        postsHtml += `
                            <div class="post-item">
                                <span class="post-type">${post.type}</span>
                                <a href="${post.edit_url}" target="_blank">${escapeHtml(post.title)}</a>
                                <span style="color:#646970;">→ ${post.field}</span>
                            </div>
                        `;
                    });
                }

                tableHtml += `
                    <tr>
                        <td class="user-cell">${expertHtml}</td>
                        <td class="arrow-cell">→</td>
                        <td class="user-cell">${personnelHtml}</td>
                        <td class="posts-cell">${postsHtml}</td>
                    </tr>
                `;
            });

            tableHtml += '</tbody></table>';
            $('#preview-table-container').html(tableHtml);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Cancel preview
        $('#cancel-preview').on('click', function() {
            $('#preview-section').hide();
            $('#merge-settings').show();
        });

        // Confirm and run merge from preview
        $('#confirm-merge').on('click', function() {
            if (!confirm('⚠️ WARNING: You are about to make PERMANENT changes to the database.\n\nThis will:\n- Update user references in posts\n' + ($('#delete-experts').is(':checked') ? '- DELETE Expert User accounts\n' : '') + '\nAre you sure you want to continue?')) {
                return;
            }
            
            $('#preview-section').hide();
            startMerge(false); // Not a dry run
        });

        // Run merge batch
        function runBatch(isDryRun) {
            if (shouldStop) {
                log('Process stopped by user.', 'warning');
                isRunning = false;
                $('#start-merge').prop('disabled', false);
                $('#stop-merge').hide();
                return;
            }

            $.post(ajaxurl, {
                action: 'user_merge_process_batch',
                nonce: nonce,
                dry_run: isDryRun ? 'true' : 'false',
                delete_experts: $('#delete-experts').is(':checked') ? 'true' : 'false',
                batch_size: $('#batch-size').val()
            }, function(response) {
                if (!response.success) {
                    log('Error: ' + response.data, 'error');
                    isRunning = false;
                    $('#start-merge').prop('disabled', false);
                    $('#stop-merge').hide();
                    return;
                }

                const data = response.data;
                const dryLabel = data.dry_run ? '[DRY RUN] ' : '';
                
                updateProgress(data.progress, `${dryLabel}Processing: ${data.current_index} / ${data.total} pairs (${data.progress}%)`);
                updateStats(data.stats);

                // Output log messages
                if (data.log) {
                    data.log.forEach(entry => log(entry.message, entry.type));
                }

                if (data.is_complete) {
                    log('✅ Process complete!', 'success');
                    isRunning = false;
                    $('#start-merge').prop('disabled', false);
                    $('#stop-merge').hide();
                    
                    const modeText = data.dry_run ? 'DRY RUN - No changes were made' : 'Changes have been applied';
                    $('#final-stats').html(`
                        <p><strong>Mode:</strong> ${modeText}</p>
                        <p><strong>Total Posts Updated:</strong> ${totalStats.posts}</p>
                        <p><strong>Total Expert Users Deleted:</strong> ${totalStats.deleted}</p>
                        <p><strong>Total Errors:</strong> ${totalStats.errors}</p>
                    `);
                    $('#completion-section').show();
                } else {
                    runBatch(isDryRun);
                }
            }).fail(function(xhr) {
                log('AJAX error: ' + xhr.statusText, 'error');
                isRunning = false;
                $('#start-merge').prop('disabled', false);
                $('#stop-merge').hide();
            });
        }

        function startMerge(isDryRun) {
            if (isRunning) return;

            isRunning = true;
            shouldStop = false;
            totalStats = {posts: 0, deleted: 0, errors: 0};

            $('#start-merge').prop('disabled', true);
            $('#stop-merge').show();
            $('#progress-section, #console-section').show();
            $('#completion-section').hide();
            $('#console-output').empty();
            $('#stat-posts, #stat-deleted, #stat-errors').text('0');
            updateProgress(0, 'Starting...');

            const mode = isDryRun ? 'DRY RUN' : 'LIVE';
            log(`🚀 Starting merge process in ${mode} mode...`, 'info');

            // Reset the index
            $.post(ajaxurl, {
                action: 'user_merge_parse_csv',
                nonce: nonce,
                csv_content: window.lastCsvContent || ''
            }, function() {
                runBatch(isDryRun);
            });
        }

        // Start merge (old button, kept for compatibility)
        $('#start-merge').on('click', function() {
            if (isRunning) return;

            const isDryRun = false;
            if (!confirm('⚠️ WARNING: You are about to make PERMANENT changes to the database.\n\nThis will:\n- Update user references in posts\n' + ($('#delete-experts').is(':checked') ? '- DELETE Expert User accounts\n' : '') + '\nAre you sure you want to continue?')) {
                return;
            }

            startMerge(isDryRun);
        });

        // Stop merge
        $('#stop-merge').on('click', function() {
            shouldStop = true;
            log('Stopping after current batch...', 'warning');
        });
    });
    </script>
    <?php
}