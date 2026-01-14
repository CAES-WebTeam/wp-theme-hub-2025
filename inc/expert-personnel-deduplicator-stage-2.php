<?php
/**
 * User Merge & Cleanup Utility
 * Review and approve/reject duplicate pairs one at a time.
 * Saves progress to database for persistence across page reloads.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ---------------------------------------------------------------------------------
 * 1. Database Table Setup
 * ---------------------------------------------------------------------------------
 */

function user_merge_get_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'user_merge_queue';
}

function user_merge_create_table() {
    global $wpdb;
    $table_name = user_merge_get_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        session_id varchar(32) NOT NULL,
        csv_line int(11) NOT NULL,
        confidence int(3) NOT NULL,
        match_reasons text NOT NULL,
        personnel_wp_id bigint(20) NOT NULL,
        personnel_name varchar(255) NOT NULL,
        personnel_email varchar(255) NOT NULL,
        personnel_phone varchar(50) DEFAULT '',
        personnel_id varchar(100) NOT NULL,
        expert_wp_id bigint(20) NOT NULL,
        expert_name varchar(255) NOT NULL,
        expert_email varchar(255) NOT NULL,
        expert_phone varchar(50) DEFAULT '',
        expert_source_id varchar(100) DEFAULT '',
        expert_writer_id varchar(100) DEFAULT '',
        affected_posts_count int(11) DEFAULT 0,
        affected_posts_cache longtext DEFAULT NULL,
        status varchar(20) DEFAULT 'pending',
        processed_at datetime DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY session_id (session_id),
        KEY status (status)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * ---------------------------------------------------------------------------------
 * 2. Configuration: Post Types and Fields to Check
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
 * 3. Content Functions
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

function user_exists_in_repeater($post_id, $repeater, $user_field, $user_id) {
    global $wpdb;
    
    $results = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} 
         WHERE post_id = %d 
         AND meta_key LIKE %s 
         AND meta_value = %s",
        $post_id,
        $wpdb->esc_like($repeater) . '_%_' . $user_field,
        $user_id
    ));
    
    return intval($results) > 0;
}

function remove_repeater_row($post_id, $repeater, $row_index) {
    $rows = get_field($repeater, $post_id);
    
    if (!is_array($rows) || !isset($rows[$row_index])) {
        return ['success' => false, 'message' => "Row {$row_index} not found in {$repeater}"];
    }
    
    array_splice($rows, $row_index, 1);
    $result = update_field($repeater, $rows, $post_id);
    
    return [
        'success' => $result !== false,
        'message' => $result !== false ? "Removed row {$row_index} from {$repeater}" : "Failed to remove row",
        'action' => 'remove'
    ];
}

function reassign_user_in_post($post_id, $meta_key, $old_user_id, $new_user_id, $repeater, $user_field, $row_index) {
    $current_value = get_post_meta($post_id, $meta_key, true);
    
    if (intval($current_value) !== intval($old_user_id)) {
        return ['success' => false, 'message' => "Value mismatch"];
    }
    
    // Check for duplicate
    if (user_exists_in_repeater($post_id, $repeater, $user_field, $new_user_id)) {
        return remove_repeater_row($post_id, $repeater, $row_index);
    }

    $result = update_post_meta($post_id, $meta_key, $new_user_id);
    
    return [
        'success' => $result !== false,
        'message' => $result !== false ? "Updated to {$new_user_id}" : "Failed to update",
        'action' => 'update'
    ];
}

function flush_post_and_trigger_save($post_id) {
    clean_post_cache($post_id);
    
    if (function_exists('acf_flush_value_cache')) {
        acf_flush_value_cache($post_id);
    }
    
    wp_cache_delete($post_id, 'posts');
    wp_cache_delete($post_id, 'post_meta');
    
    do_action('acf/save_post', $post_id);
    do_action('save_post', $post_id, get_post($post_id), true);
}

/**
 * ---------------------------------------------------------------------------------
 * 4. CSV Parsing
 * ---------------------------------------------------------------------------------
 */

function parse_and_store_csv($csv_content) {
    global $wpdb;
    $table_name = user_merge_get_table_name();
    
    user_merge_create_table();
    
    $lines = explode("\n", trim($csv_content));
    if (count($lines) < 2) {
        return new WP_Error('invalid_csv', 'CSV must have a header row and at least one data row.');
    }

    $header = str_getcsv(array_shift($lines));
    $header = array_map(function($h) { return strtolower(trim($h)); }, $header);
    
    // Find column indices
    $cols = [
        'confidence' => array_search('confidence', $header),
        'personnel_wp_id' => array_search('personnel wp id', $header),
        'personnel_name' => array_search('personnel name', $header),
        'personnel_email' => array_search('personnel email', $header),
        'personnel_phone' => array_search('personnel phone', $header),
        'personnel_id' => array_search('personnel_id', $header),
        'expert_wp_id' => array_search('expert wp id', $header),
        'expert_name' => array_search('expert name', $header),
        'expert_email' => array_search('expert email', $header),
        'expert_phone' => array_search('expert phone', $header),
        'expert_source_id' => array_search('expert_source_id', $header),
        'expert_writer_id' => array_search('expert_writer_id', $header),
        'match_reasons' => array_search('match reasons', $header),
    ];
    
    // Validate required columns
    if ($cols['personnel_id'] === false || $cols['expert_wp_id'] === false) {
        return new WP_Error('missing_columns', 'CSV missing required columns (personnel_id, expert wp id).');
    }
    
    // Generate session ID
    $session_id = wp_generate_password(16, false);
    
    $imported = 0;
    $errors = [];
    $line_num = 1;
    
    foreach ($lines as $line) {
        $line_num++;
        if (empty(trim($line))) continue;
        
        $data = str_getcsv($line);
        
        $personnel_id = $cols['personnel_id'] !== false ? trim($data[$cols['personnel_id']] ?? '') : '';
        $expert_wp_id = $cols['expert_wp_id'] !== false ? intval($data[$cols['expert_wp_id']] ?? 0) : 0;
        
        if (empty($personnel_id) || empty($expert_wp_id)) {
            $errors[] = "Line {$line_num}: Missing personnel_id or expert_wp_id";
            continue;
        }
        
        // Look up personnel WP ID
        $personnel_wp_id = $cols['personnel_wp_id'] !== false ? intval($data[$cols['personnel_wp_id']] ?? 0) : 0;
        if (!$personnel_wp_id) {
            $users = get_users(['meta_key' => 'personnel_id', 'meta_value' => $personnel_id, 'number' => 1, 'fields' => 'ID']);
            $personnel_wp_id = !empty($users) ? intval($users[0]) : 0;
        }
        
        if (!$personnel_wp_id) {
            $errors[] = "Line {$line_num}: Could not find personnel user with ID '{$personnel_id}'";
            continue;
        }
        
        // Verify expert exists
        $expert_user = get_user_by('ID', $expert_wp_id);
        if (!$expert_user) {
            $errors[] = "Line {$line_num}: Expert WP ID {$expert_wp_id} not found";
            continue;
        }
        
        // Count affected posts
        $affected_posts = find_posts_referencing_user($expert_wp_id);
        
        // Parse confidence (remove % if present)
        $confidence = $cols['confidence'] !== false ? intval(str_replace('%', '', $data[$cols['confidence']] ?? '0')) : 0;
        
        $wpdb->insert($table_name, [
            'session_id' => $session_id,
            'csv_line' => $line_num,
            'confidence' => $confidence,
            'match_reasons' => $cols['match_reasons'] !== false ? ($data[$cols['match_reasons']] ?? '') : '',
            'personnel_wp_id' => $personnel_wp_id,
            'personnel_name' => $cols['personnel_name'] !== false ? ($data[$cols['personnel_name']] ?? '') : '',
            'personnel_email' => $cols['personnel_email'] !== false ? ($data[$cols['personnel_email']] ?? '') : '',
            'personnel_phone' => $cols['personnel_phone'] !== false ? ($data[$cols['personnel_phone']] ?? '') : '',
            'personnel_id' => $personnel_id,
            'expert_wp_id' => $expert_wp_id,
            'expert_name' => $cols['expert_name'] !== false ? ($data[$cols['expert_name']] ?? '') : '',
            'expert_email' => $cols['expert_email'] !== false ? ($data[$cols['expert_email']] ?? '') : '',
            'expert_phone' => $cols['expert_phone'] !== false ? ($data[$cols['expert_phone']] ?? '') : '',
            'expert_source_id' => $cols['expert_source_id'] !== false ? ($data[$cols['expert_source_id']] ?? '') : '',
            'expert_writer_id' => $cols['expert_writer_id'] !== false ? ($data[$cols['expert_writer_id']] ?? '') : '',
            'affected_posts_count' => count($affected_posts),
            'status' => 'pending',
        ]);
        
        $imported++;
    }
    
    if ($imported > 0) {
        update_option('user_merge_session_id', $session_id, false);
    }
    
    return [
        'session_id' => $session_id,
        'imported' => $imported,
        'errors' => $errors,
    ];
}

/**
 * ---------------------------------------------------------------------------------
 * 5. AJAX Handlers
 * ---------------------------------------------------------------------------------
 */

add_action('wp_ajax_user_merge_upload_csv', 'ajax_user_merge_upload_csv');
function ajax_user_merge_upload_csv() {
    check_ajax_referer('user_merge_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    $csv_content = isset($_POST['csv_content']) ? wp_unslash($_POST['csv_content']) : '';
    
    if (empty($csv_content)) {
        wp_send_json_error('No CSV content provided.');
    }

    // Parse header and count lines
    $lines = explode("\n", trim($csv_content));
    if (count($lines) < 2) {
        wp_send_json_error('CSV must have a header row and at least one data row.');
    }
    
    $header = str_getcsv(array_shift($lines));
    $header = array_map(function($h) { return strtolower(trim($h)); }, $header);
    
    // Validate required columns
    $cols = [
        'personnel_id' => array_search('personnel_id', $header),
        'expert_wp_id' => array_search('expert wp id', $header),
    ];
    
    if ($cols['personnel_id'] === false || $cols['expert_wp_id'] === false) {
        wp_send_json_error('CSV missing required columns (personnel_id, expert wp id).');
    }
    
    // Generate session and store CSV for batched processing
    $session_id = wp_generate_password(16, false);
    user_merge_create_table();
    
    // Store the CSV content and header info for batch processing
    update_option('user_merge_session_id', $session_id, false);
    update_option('user_merge_csv_data', [
        'header' => $header,
        'lines' => $lines,
        'total' => count($lines),
        'processed' => 0,
        'imported' => 0,
        'errors' => [],
    ], false);

    wp_send_json_success([
        'session_id' => $session_id,
        'total_lines' => count($lines),
        'message' => 'CSV uploaded. Starting processing...',
    ]);
}

add_action('wp_ajax_user_merge_process_csv_batch', 'ajax_user_merge_process_csv_batch');
function ajax_user_merge_process_csv_batch() {
    check_ajax_referer('user_merge_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    global $wpdb;
    $table_name = user_merge_get_table_name();
    
    $csv_data = get_option('user_merge_csv_data');
    $session_id = get_option('user_merge_session_id');
    
    if (!$csv_data || !$session_id) {
        wp_send_json_error('No CSV data found. Please re-upload.');
    }
    
    $header = $csv_data['header'];
    $lines = $csv_data['lines'];
    $processed = $csv_data['processed'];
    $imported = $csv_data['imported'];
    $errors = $csv_data['errors'];
    $total = $csv_data['total'];
    
    // Column indices
    $cols = [
        'confidence' => array_search('confidence', $header),
        'personnel_wp_id' => array_search('personnel wp id', $header),
        'personnel_name' => array_search('personnel name', $header),
        'personnel_email' => array_search('personnel email', $header),
        'personnel_phone' => array_search('personnel phone', $header),
        'personnel_id' => array_search('personnel_id', $header),
        'expert_wp_id' => array_search('expert wp id', $header),
        'expert_name' => array_search('expert name', $header),
        'expert_email' => array_search('expert email', $header),
        'expert_phone' => array_search('expert phone', $header),
        'expert_source_id' => array_search('expert_source_id', $header),
        'expert_writer_id' => array_search('expert_writer_id', $header),
        'match_reasons' => array_search('match reasons', $header),
    ];
    
    $batch_size = 10; // Process 10 rows at a time
    $batch_end = min($processed + $batch_size, $total);
    
    for ($i = $processed; $i < $batch_end; $i++) {
        $line = $lines[$i];
        $line_num = $i + 2; // +2 for header row and 0-index
        
        if (empty(trim($line))) {
            continue;
        }
        
        $data = str_getcsv($line);
        
        $personnel_id = $cols['personnel_id'] !== false ? trim($data[$cols['personnel_id']] ?? '') : '';
        $expert_wp_id = $cols['expert_wp_id'] !== false ? intval($data[$cols['expert_wp_id']] ?? 0) : 0;
        
        if (empty($personnel_id) || empty($expert_wp_id)) {
            $errors[] = "Line {$line_num}: Missing personnel_id or expert_wp_id";
            continue;
        }
        
        // Look up personnel WP ID
        $personnel_wp_id = $cols['personnel_wp_id'] !== false ? intval($data[$cols['personnel_wp_id']] ?? 0) : 0;
        if (!$personnel_wp_id) {
            $users = get_users(['meta_key' => 'personnel_id', 'meta_value' => $personnel_id, 'number' => 1, 'fields' => 'ID']);
            $personnel_wp_id = !empty($users) ? intval($users[0]) : 0;
        }
        
        if (!$personnel_wp_id) {
            $errors[] = "Line {$line_num}: Personnel ID '{$personnel_id}' not found";
            continue;
        }
        
        // Verify expert exists
        $expert_user = get_user_by('ID', $expert_wp_id);
        if (!$expert_user) {
            $errors[] = "Line {$line_num}: Expert WP ID {$expert_wp_id} not found";
            continue;
        }
        
        // Count affected posts and cache them
        $affected_posts = find_posts_referencing_user($expert_wp_id);
        $affected_posts_for_cache = array_map(function($p) {
            return [
                'post_id' => $p['post_id'],
                'post_title' => $p['post_title'],
                'post_type' => $p['post_type'],
                'repeater' => $p['repeater'],
                'user_field' => $p['user_field'],
                'meta_key' => $p['meta_key'],
                'row_index' => $p['row_index'],
            ];
        }, $affected_posts);

        // Parse confidence
        $confidence = $cols['confidence'] !== false ? intval(str_replace('%', '', $data[$cols['confidence']] ?? '0')) : 0;
        
        $wpdb->insert($table_name, [
            'session_id' => $session_id,
            'csv_line' => $line_num,
            'confidence' => $confidence,
            'match_reasons' => $cols['match_reasons'] !== false ? ($data[$cols['match_reasons']] ?? '') : '',
            'personnel_wp_id' => $personnel_wp_id,
            'personnel_name' => $cols['personnel_name'] !== false ? ($data[$cols['personnel_name']] ?? '') : '',
            'personnel_email' => $cols['personnel_email'] !== false ? ($data[$cols['personnel_email']] ?? '') : '',
            'personnel_phone' => $cols['personnel_phone'] !== false ? ($data[$cols['personnel_phone']] ?? '') : '',
            'personnel_id' => $personnel_id,
            'expert_wp_id' => $expert_wp_id,
            'expert_name' => $cols['expert_name'] !== false ? ($data[$cols['expert_name']] ?? '') : '',
            'expert_email' => $cols['expert_email'] !== false ? ($data[$cols['expert_email']] ?? '') : '',
            'expert_phone' => $cols['expert_phone'] !== false ? ($data[$cols['expert_phone']] ?? '') : '',
            'expert_source_id' => $cols['expert_source_id'] !== false ? ($data[$cols['expert_source_id']] ?? '') : '',
            'expert_writer_id' => $cols['expert_writer_id'] !== false ? ($data[$cols['expert_writer_id']] ?? '') : '',
            'affected_posts_count' => count($affected_posts),
            'affected_posts_cache' => json_encode($affected_posts_for_cache),
            'status' => 'pending',
        ]);
        
        $imported++;
    }
    
    $processed = $batch_end;
    $is_complete = $processed >= $total;
    $progress = $total > 0 ? round(($processed / $total) * 100) : 100;
    
    // Update stored data
    $csv_data['processed'] = $processed;
    $csv_data['imported'] = $imported;
    $csv_data['errors'] = $errors;
    update_option('user_merge_csv_data', $csv_data, false);
    
    if ($is_complete) {
        delete_option('user_merge_csv_data');
    }
    
    wp_send_json_success([
        'processed' => $processed,
        'total' => $total,
        'imported' => $imported,
        'errors_count' => count($errors),
        'progress' => $progress,
        'is_complete' => $is_complete,
    ]);
}

add_action('wp_ajax_user_merge_get_queue', 'ajax_user_merge_get_queue');
function ajax_user_merge_get_queue() {
    check_ajax_referer('user_merge_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    global $wpdb;
    $table_name = user_merge_get_table_name();
    $session_id = get_option('user_merge_session_id');
    
    if (!$session_id) {
        wp_send_json_error('No active session. Please upload a CSV.');
    }
    
    $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'pending';
    $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
    $per_page = 50;
    $offset = ($page - 1) * $per_page;

    $where_status = '';
    if ($filter === 'pending') {
        $where_status = "AND status = 'pending'";
    } elseif ($filter === 'merged') {
        $where_status = "AND status = 'merged'";
    } elseif ($filter === 'rejected') {
        $where_status = "AND status = 'rejected'";
    }
    
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name 
         WHERE session_id = %s $where_status 
         ORDER BY confidence DESC, id ASC
         LIMIT %d OFFSET %d",
        $session_id,
        $per_page,
        $offset
    ), ARRAY_A);
    
    // Get counts
    $counts = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'merged' THEN 1 ELSE 0 END) as merged,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
         FROM $table_name WHERE session_id = %s",
        $session_id
    ), ARRAY_A);
    
    // Get filtered count for pagination
    $filtered_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE session_id = %s $where_status",
        $session_id
    ));

    // Enrich with current affected posts count and edit links
    foreach ($rows as &$row) {
        $row['personnel_edit_url'] = get_edit_user_link($row['personnel_wp_id']);
        $row['expert_edit_url'] = get_edit_user_link($row['expert_wp_id']);
        
        // Use cached affected posts instead of re-querying
        if ($row['status'] === 'pending' && !empty($row['affected_posts_cache'])) {
            $cached_posts = json_decode($row['affected_posts_cache'], true);
            if (is_array($cached_posts)) {
                $row['affected_posts'] = array_map(function($p) {
                    return [
                        'id' => $p['post_id'],
                        'title' => $p['post_title'],
                        'type' => $p['post_type'],
                        'field' => $p['repeater'] . '[' . $p['row_index'] . ']',
                        'edit_url' => get_edit_post_link($p['post_id'], 'raw'),
                    ];
                }, $cached_posts);
            } else {
                $row['affected_posts'] = [];
            }
        } else {
            $row['affected_posts'] = [];
        }
        // Remove the cache from response to reduce payload size
        unset($row['affected_posts_cache']);
    }
    
    wp_send_json_success([
        'rows' => $rows,
        'counts' => $counts,
        'session_id' => $session_id,
        'pagination' => [
            'page' => $page,
            'per_page' => $per_page,
            'total' => intval($filtered_count),
            'total_pages' => ceil($filtered_count / $per_page),
        ],
    ]);
}

add_action('wp_ajax_user_merge_process_single', 'ajax_user_merge_process_single');
function ajax_user_merge_process_single() {
    check_ajax_referer('user_merge_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    global $wpdb;
    $table_name = user_merge_get_table_name();
    
    $row_id = intval($_POST['row_id'] ?? 0);
    $action = sanitize_text_field($_POST['merge_action'] ?? '');
    $delete_expert = isset($_POST['delete_expert']) && $_POST['delete_expert'] === 'true';
    
    if (!$row_id || !in_array($action, ['merge', 'reject'])) {
        wp_send_json_error('Invalid parameters');
    }
    
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $row_id
    ), ARRAY_A);
    
    if (!$row) {
        wp_send_json_error('Row not found');
    }
    
    if ($row['status'] !== 'pending') {
        wp_send_json_error('Row already processed');
    }
    
    $log = [];
    $stats = ['posts_updated' => 0, 'rows_removed' => 0, 'expert_deleted' => false];
    
    if ($action === 'reject') {
        $wpdb->update($table_name, [
            'status' => 'rejected',
            'processed_at' => current_time('mysql'),
        ], ['id' => $row_id]);
        
        $log[] = "Rejected pair: {$row['personnel_name']} <- {$row['expert_name']}";
        
        wp_send_json_success([
            'status' => 'rejected',
            'log' => $log,
            'stats' => $stats,
        ]);
        return;
    }
    
    // Process merge
    $affected_posts = find_posts_referencing_user($row['expert_wp_id']);
    $modified_posts = [];
    
    if (empty($affected_posts)) {
        $log[] = "No posts found referencing Expert WP#{$row['expert_wp_id']}";
    } else {
        $log[] = "Processing " . count($affected_posts) . " post reference(s)";
        
        foreach ($affected_posts as $post_ref) {
            $result = reassign_user_in_post(
                $post_ref['post_id'],
                $post_ref['meta_key'],
                $row['expert_wp_id'],
                $row['personnel_wp_id'],
                $post_ref['repeater'],
                $post_ref['user_field'],
                $post_ref['row_index']
            );
            
            if ($result['success']) {
                $modified_posts[$post_ref['post_id']] = true;
                
                if (isset($result['action']) && $result['action'] === 'remove') {
                    $stats['rows_removed']++;
                    $log[] = "✓ Removed duplicate from {$post_ref['post_type']} \"{$post_ref['post_title']}\"";
                } else {
                    $stats['posts_updated']++;
                    $log[] = "✓ Updated {$post_ref['post_type']} \"{$post_ref['post_title']}\"";
                }
            } else {
                $log[] = "✗ Failed: {$post_ref['post_title']} - {$result['message']}";
            }
        }
        
        // Flush caches
        foreach (array_keys($modified_posts) as $post_id) {
            flush_post_and_trigger_save($post_id);
        }
    }
    
    // Delete expert if requested
    if ($delete_expert) {
        $expert_user = get_user_by('ID', $row['expert_wp_id']);
        if ($expert_user) {
            $deleted = wp_delete_user($row['expert_wp_id'], $row['personnel_wp_id']);
            if ($deleted) {
                $stats['expert_deleted'] = true;
                $log[] = "✓ Deleted Expert User WP#{$row['expert_wp_id']} ({$expert_user->display_name})";
            } else {
                $log[] = "✗ Failed to delete Expert User";
            }
        }
    }
    
    // Update row status
    $wpdb->update($table_name, [
        'status' => 'merged',
        'processed_at' => current_time('mysql'),
    ], ['id' => $row_id]);
    
    wp_send_json_success([
        'status' => 'merged',
        'log' => $log,
        'stats' => $stats,
    ]);
}

add_action('wp_ajax_user_merge_clear_session', 'ajax_user_merge_clear_session');
function ajax_user_merge_clear_session() {
    check_ajax_referer('user_merge_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    global $wpdb;
    $table_name = user_merge_get_table_name();
    $session_id = get_option('user_merge_session_id');
    
    if ($session_id) {
        $wpdb->delete($table_name, ['session_id' => $session_id]);
    }
    
    delete_option('user_merge_session_id');
    
    wp_send_json_success(['message' => 'Session cleared']);
}

add_action('wp_ajax_user_merge_check_session', 'ajax_user_merge_check_session');
function ajax_user_merge_check_session() {
    check_ajax_referer('user_merge_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    global $wpdb;
    $table_name = user_merge_get_table_name();
    
    // Ensure table exists
    user_merge_create_table();
    
    $session_id = get_option('user_merge_session_id');
    
    if (!$session_id) {
        wp_send_json_success(['has_session' => false]);
        return;
    }
    
    $counts = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'merged' THEN 1 ELSE 0 END) as merged,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
         FROM $table_name WHERE session_id = %s",
        $session_id
    ), ARRAY_A);
    
    wp_send_json_success([
        'has_session' => intval($counts['total']) > 0,
        'counts' => $counts,
        'session_id' => $session_id,
    ]);
}

/**
 * ---------------------------------------------------------------------------------
 * 6. Admin Page
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
        <h1>🔀 User Merge Utility</h1>
        <p>Review and merge duplicate user pairs one at a time. Progress is saved automatically.</p>

        <!-- Upload Section -->
        <div id="upload-section" style="background:#fff; padding:20px; border:1px solid #ddd; margin-bottom:20px; max-width:600px;">
            <h2 style="margin-top:0;">📁 Upload CSV</h2>
            <p>Upload a CSV file from the Duplicate Detection tool.</p>
            <input type="file" id="csv-file" accept=".csv" style="margin-bottom:10px;"><br>
            <button id="upload-csv" class="button button-primary">Upload & Parse CSV</button>
            <div id="upload-progress" style="display:none; margin-top:15px;">
                <div style="background:#f0f0f0; border-radius:4px; height:20px; overflow:hidden;">
                    <div id="upload-progress-bar" style="background:#2271b1; height:100%; width:0%; transition:width 0.3s;"></div>
                </div>
                <p id="upload-progress-text" style="margin:5px 0 0; font-size:12px; color:#666;">Initializing...</p>
            </div>
            <div id="upload-status" style="margin-top:10px;"></div>
        </div>

        <!-- Existing Session Notice -->
        <div id="session-notice" style="display:none; background:#f0f6fc; border:1px solid #2271b1; padding:15px; margin-bottom:20px; max-width:600px;">
            <strong>📋 Existing Session Found</strong>
            <p id="session-info"></p>
            <button id="resume-session" class="button button-primary">Resume Working</button>
            <button id="clear-session" class="button">Start Fresh (Clear All)</button>
        </div>

        <!-- Queue Section -->
        <div id="queue-section" style="display:none;">
            <!-- Stats Bar -->
            <div style="background:#fff; padding:15px 20px; border:1px solid #ddd; margin-bottom:20px; display:flex; gap:30px; align-items:center; flex-wrap:wrap;">
                <div><strong>Total:</strong> <span id="count-total">0</span></div>
                <div style="color:#2271b1;"><strong>Pending:</strong> <span id="count-pending">0</span></div>
                <div style="color:#00a32a;"><strong>Merged:</strong> <span id="count-merged">0</span></div>
                <div style="color:#d63638;"><strong>Rejected:</strong> <span id="count-rejected">0</span></div>
                <div style="margin-left:auto;">
                    <label><input type="checkbox" id="delete-experts-global"> Delete experts after merge</label>
                </div>
            </div>
            
            <!-- Filter Tabs -->
            <div style="margin-bottom:15px;">
                <button class="button filter-btn active" data-filter="pending">Pending</button>
                <button class="button filter-btn" data-filter="merged">Merged</button>
                <button class="button filter-btn" data-filter="rejected">Rejected</button>
                <button class="button filter-btn" data-filter="all">All</button>
            </div>

            <!-- Queue Table -->
            <div id="queue-table-container" style="background:#fff; border:1px solid #ddd;"></div>
            
            <!-- Pagination -->
            <div id="pagination-container" style="margin-top:15px; display:flex; gap:10px; align-items:center;"></div>
             
            <!-- Action Log -->
            <div id="action-log" style="display:none; margin-top:20px; background:#1e1e1e; color:#d4d4d4; padding:15px; max-height:200px; overflow-y:auto; font-family:monospace; font-size:12px;"></div>
        </div>
    </div>

    <style>
        .queue-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .queue-table th {
            background: #f0f0f1;
            padding: 12px 10px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #c3c4c7;
            position: sticky;
            top: 0;
        }
        .queue-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #dcdcde;
            vertical-align: top;
        }
        .queue-table tr:hover {
            background: #f6f7f7;
        }
        .queue-table tr.status-merged {
            background: #f0f9f0;
        }
        .queue-table tr.status-rejected {
            background: #fafafa;
            opacity: 0.7;
        }
        .confidence-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-weight: 600;
            font-size: 12px;
        }
        .confidence-high { background: #d63638; color: #fff; }
        .confidence-medium { background: #2271b1; color: #fff; }
        .confidence-low { background: #50575e; color: #fff; }
        
        .user-card {
            background: #f9f9f9;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 5px;
        }
        .user-card .name {
            font-weight: 600;
            color: #1d2327;
        }
        .user-card .meta {
            font-size: 11px;
            color: #646970;
            margin-top: 4px;
        }
        .user-card .meta a {
            color: #2271b1;
        }
        
        .match-reasons {
            font-size: 11px;
            color: #646970;
            max-width: 250px;
        }
        .match-reasons li {
            margin-bottom: 3px;
        }
        
        .affected-posts {
            font-size: 11px;
        }
        .affected-posts .post-item {
            background: #f0f0f1;
            padding: 3px 6px;
            margin: 2px 0;
            border-radius: 3px;
        }
        .affected-posts .post-type {
            background: #dcdcde;
            padding: 1px 4px;
            border-radius: 2px;
            font-size: 9px;
            text-transform: uppercase;
            margin-right: 4px;
        }
        
        .action-buttons {
            white-space: nowrap;
        }
        .action-buttons .button {
            margin: 2px;
        }
        .btn-merge { background: #00a32a !important; border-color: #00a32a !important; color: #fff !important; }
        .btn-merge:hover { background: #008a20 !important; }
        .btn-reject { background: #d63638 !important; border-color: #d63638 !important; color: #fff !important; }
        .btn-reject:hover { background: #b32d2e !important; }
        
        .filter-btn.active {
            background: #2271b1;
            border-color: #2271b1;
            color: #fff;
        }
        
        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 600;
        }
        .status-merged { background: #00a32a; color: #fff; }
        .status-rejected { background: #50575e; color: #fff; }
        .status-pending { background: #2271b1; color: #fff; }
        
        .processing-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .pagination-btn {
            min-width: 36px;
        }
        .pagination-btn.active {
            background: #2271b1;
            border-color: #2271b1;
            color: #fff;
        }
        .pagination-info {
            color: #666;
            font-size: 13px;
        }
    </style>

    <script>
    jQuery(document).ready(function($) {
        const nonce = '<?php echo $nonce; ?>';
        let currentFilter = 'pending';
        let currentPage = 1;

        // Check for existing session on load
        checkSession();

        function checkSession() {
            $.post(ajaxurl, {
                action: 'user_merge_check_session',
                nonce: nonce
            }, function(response) {
                if (response.success && response.data.has_session) {
                    const counts = response.data.counts;
                    $('#session-info').html(`
                        <strong>${counts.total}</strong> pairs loaded: 
                        ${counts.pending} pending, ${counts.merged} merged, ${counts.rejected} rejected
                    `);
                    $('#session-notice').show();
                    $('#upload-section').hide();
                }
            });
        }

        function log(message, type = 'info') {
            const $log = $('#action-log');
            const colors = {info: '#9cdcfe', success: '#4ec9b0', warning: '#dcdcaa', error: '#f14c4c'};
            $log.show().append(`<div style="color:${colors[type]}">${message}</div>`);
            $log.scrollTop($log[0].scrollHeight);
        }

        function loadQueue(page) {
            page = page || 1;
            currentPage = page;
            
            $('#queue-table-container').html('<p style="padding:20px;color:#666;">Loading...</p>');
            
            $.post(ajaxurl, {
                action: 'user_merge_get_queue',
                nonce: nonce,
                filter: currentFilter,
                page: page
            }, function(response) {
                if (!response.success) {
                    $('#queue-table-container').html('<p style="padding:20px;color:red;">Error: ' + response.data + '</p>');
                    return;
                }
                
                const data = response.data;
                updateCounts(data.counts);
                renderTable(data.rows);
                renderPagination(data.pagination);
            }).fail(function() {
                $('#queue-table-container').html('<p style="padding:20px;color:red;">Failed to load queue. Please refresh the page.</p>');
            });
        }

        function updateCounts(counts) {
            $('#count-total').text(counts.total || 0);
            $('#count-pending').text(counts.pending || 0);
            $('#count-merged').text(counts.merged || 0);
            $('#count-rejected').text(counts.rejected || 0);
        }

        function renderPagination(pagination) {
            if (!pagination || pagination.total_pages <= 1) {
                $('#pagination-container').empty();
                return;
            }
            
            let html = '<span class="pagination-info">Page ' + pagination.page + ' of ' + pagination.total_pages + ' (' + pagination.total + ' items)</span>';
            html += '<div style="display:flex; gap:5px;">';
            
            if (pagination.page > 1) {
                html += '<button class="button pagination-btn" data-page="' + (pagination.page - 1) + '">&laquo; Prev</button>';
            }
            
            const startPage = Math.max(1, pagination.page - 3);
            const endPage = Math.min(pagination.total_pages, pagination.page + 3);
            
            if (startPage > 1) {
                html += '<button class="button pagination-btn" data-page="1">1</button>';
                if (startPage > 2) html += '<span style="padding:0 5px;">...</span>';
            }
            
            for (let i = startPage; i <= endPage; i++) {
                const activeClass = i === pagination.page ? ' active' : '';
                html += '<button class="button pagination-btn' + activeClass + '" data-page="' + i + '">' + i + '</button>';
            }
            
            if (endPage < pagination.total_pages) {
                if (endPage < pagination.total_pages - 1) html += '<span style="padding:0 5px;">...</span>';
                html += '<button class="button pagination-btn" data-page="' + pagination.total_pages + '">' + pagination.total_pages + '</button>';
            }
            
            if (pagination.page < pagination.total_pages) {
                html += '<button class="button pagination-btn" data-page="' + (pagination.page + 1) + '">Next &raquo;</button>';
            }
            
            html += '</div>';
            $('#pagination-container').html(html);
        }

        function renderTable(rows) {
            if (!rows.length) {
                $('#queue-table-container').html('<p style="padding:20px;color:#666;">No items found.</p>');
                return;
            }

            let html = `
                <table class="queue-table">
                    <thead>
                        <tr>
                            <th style="width:60px">Conf.</th>
                            <th style="width:200px">Personnel (Target)</th>
                            <th style="width:200px">Expert (Source)</th>
                            <th>Match Reasons</th>
                            <th style="width:180px">Affected Posts</th>
                            <th style="width:130px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            rows.forEach(row => {
                const confClass = row.confidence >= 70 ? 'high' : (row.confidence >= 50 ? 'medium' : 'low');
                const statusClass = `status-${row.status}`;
                
                // Parse match reasons
                let reasonsHtml = '<ul class="match-reasons" style="margin:0;padding-left:15px;">';
                if (row.match_reasons) {
                    row.match_reasons.split(';').forEach(r => {
                        if (r.trim()) reasonsHtml += `<li>${escapeHtml(r.trim())}</li>`;
                    });
                }
                reasonsHtml += '</ul>';
                
                // Affected posts
                let postsHtml = '';
                if (row.status === 'pending' && row.affected_posts && row.affected_posts.length) {
                    postsHtml = '<div class="affected-posts">';
                    row.affected_posts.slice(0, 5).forEach(p => {
                        postsHtml += `<div class="post-item"><span class="post-type">${p.type}</span><a href="${p.edit_url}" target="_blank">${escapeHtml(p.title)}</a></div>`;
                    });
                    if (row.affected_posts.length > 5) {
                        postsHtml += `<div style="color:#666;">+${row.affected_posts.length - 5} more</div>`;
                    }
                    postsHtml += '</div>';
                } else if (row.affected_posts_count > 0) {
                    postsHtml = `<span style="color:#666;">${row.affected_posts_count} post(s)</span>`;
                } else {
                    postsHtml = '<span style="color:#999;">None</span>';
                }
                
                // Actions
                let actionsHtml = '';
                if (row.status === 'pending') {
                    actionsHtml = `
                        <div class="action-buttons">
                            <button class="button btn-merge" data-id="${row.id}" data-action="merge">✓ Merge</button>
                            <button class="button btn-reject" data-id="${row.id}" data-action="reject">✗ Reject</button>
                        </div>
                    `;
                } else {
                    actionsHtml = `<span class="status-badge status-${row.status}">${row.status}</span>`;
                }

                html += `
                    <tr class="${statusClass}" data-row-id="${row.id}">
                        <td><span class="confidence-badge confidence-${confClass}">${row.confidence}%</span></td>
                        <td>
                            <div class="user-card">
                                <div class="name">${escapeHtml(row.personnel_name)}</div>
                                <div class="meta">
                                    WP#${row.personnel_wp_id}<br>
                                    ID: ${row.personnel_id}<br>
                                    ${row.personnel_email}<br>
                                    ${row.personnel_phone ? row.personnel_phone + '<br>' : ''}
                                    <a href="${row.personnel_edit_url}" target="_blank">Edit</a>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="user-card">
                                <div class="name">${escapeHtml(row.expert_name)}</div>
                                <div class="meta">
                                    WP#${row.expert_wp_id}<br>
                                    ${row.expert_source_id ? 'Src: ' + row.expert_source_id + '<br>' : ''}
                                    ${row.expert_writer_id ? 'Writer: ' + row.expert_writer_id + '<br>' : ''}
                                    ${row.expert_email}<br>
                                    ${row.expert_phone ? row.expert_phone + '<br>' : ''}
                                    <a href="${row.expert_edit_url}" target="_blank">Edit</a>
                                </div>
                            </div>
                        </td>
                        <td>${reasonsHtml}</td>
                        <td>${postsHtml}</td>
                        <td>${actionsHtml}</td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            $('#queue-table-container').html(html);
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Upload CSV
        $('#upload-csv').on('click', function() {
            const file = $('#csv-file')[0].files[0];
            if (!file) {
                alert('Please select a CSV file.');
                return;
            }

            const $btn = $(this);
            $btn.prop('disabled', true).text('Uploading...');
            $('#upload-progress').show();
            $('#upload-progress-bar').css('width', '0%');
            $('#upload-progress-text').text('Reading file...');
            $('#upload-status').empty();

            const reader = new FileReader();
            reader.onload = function(e) {
                $('#upload-progress-text').text('Uploading CSV...');
                
                $.post(ajaxurl, {
                    action: 'user_merge_upload_csv',
                    nonce: nonce,
                    csv_content: e.target.result
                }, function(response) {
                    if (response.success) {
                        $('#upload-progress-text').text(`Processing 0 / ${response.data.total_lines} rows...`);
                        processCsvBatch(response.data.total_lines);
                    } else {
                        $btn.prop('disabled', false).text('Upload & Parse CSV');
                        $('#upload-progress').hide();
                        $('#upload-status').html(`<span style="color:red;">Error: ${response.data}</span>`);
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('Upload & Parse CSV');
                    $('#upload-progress').hide();
                    $('#upload-status').html('<span style="color:red;">Upload failed. Please try again.</span>');
                });
            };
            reader.readAsText(file);
        });

        function processCsvBatch(totalLines) {
            $.post(ajaxurl, {
                action: 'user_merge_process_csv_batch',
                nonce: nonce
            }, function(response) {
                if (!response.success) {
                    $('#upload-csv').prop('disabled', false).text('Upload & Parse CSV');
                    $('#upload-progress').hide();
                    $('#upload-status').html(`<span style="color:red;">Error: ${response.data}</span>`);
                    return;
                }
                
                const data = response.data;
                $('#upload-progress-bar').css('width', data.progress + '%');
                $('#upload-progress-text').text(`Processing ${data.processed} / ${data.total} rows... (${data.imported} imported)`);
                
                if (data.is_complete) {
                    $('#upload-csv').prop('disabled', false).text('Upload & Parse CSV');
                    
                    let msg = `✓ Imported ${data.imported} pairs.`;
                    if (data.errors_count > 0) {
                        msg += ` ${data.errors_count} rows skipped.`;
                    }
                    $('#upload-status').html(`<span style="color:green;">${msg}</span>`);
                    
                    setTimeout(function() {
                        $('#upload-section').hide();
                        $('#session-notice').hide();
                        $('#queue-section').show();
                        $('#upload-progress').hide();
                        loadQueue();
                    }, 500);
                } else {
                    processCsvBatch(totalLines);
                }
            }).fail(function() {
                $('#upload-csv').prop('disabled', false).text('Upload & Parse CSV');
                $('#upload-progress').hide();
                $('#upload-status').html('<span style="color:red;">Processing failed. Please try again.</span>');
            });
        }

        // Resume session
        $('#resume-session').on('click', function() {
            $('#session-notice').hide();
            $('#upload-section').hide();
            $('#queue-section').show();
            loadQueue();
        });

        // Clear session
        $('#clear-session').on('click', function() {
            if (!confirm('This will delete all progress. Are you sure?')) return;
            
            $.post(ajaxurl, {
                action: 'user_merge_clear_session',
                nonce: nonce
            }, function(response) {
                if (response.success) {
                    $('#session-notice').hide();
                    $('#upload-section').show();
                    $('#queue-section').hide();
                    $('#action-log').empty().hide();
                }
            });
        });

        // Filter buttons
        $('.filter-btn').on('click', function() {
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');
            currentFilter = $(this).data('filter');
            loadQueue();
        });

        // Pagination buttons
                $(document).on('click', '.pagination-btn', function() {
                    const page = $(this).data('page');
                    loadQueue(page);
                    $('html, body').animate({ scrollTop: $('#queue-table-container').offset().top - 50 }, 200);
                });

        // Merge/Reject actions
        $(document).on('click', '.btn-merge, .btn-reject', function() {
            const $btn = $(this);
            const rowId = $btn.data('id');
            const action = $btn.data('action');
            const deleteExpert = $('#delete-experts-global').is(':checked');
            const $row = $btn.closest('tr');
            
            $btn.prop('disabled', true);
            $row.css('opacity', '0.5');
            
            $.post(ajaxurl, {
                action: 'user_merge_process_single',
                nonce: nonce,
                row_id: rowId,
                merge_action: action,
                delete_expert: deleteExpert ? 'true' : 'false'
            }, function(response) {
                if (response.success) {
                    response.data.log.forEach(msg => {
                        const type = msg.includes('✓') ? 'success' : (msg.includes('✗') ? 'error' : 'info');
                        log(msg, type);
                    });
                    
                    // Refresh the table
                    loadQueue();
                } else {
                    alert('Error: ' + response.data);
                    $btn.prop('disabled', false);
                    $row.css('opacity', '1');
                }
            }).fail(function() {
                alert('AJAX error');
                $btn.prop('disabled', false);
                $row.css('opacity', '1');
            });
        });
    });
    </script>
    <?php
}