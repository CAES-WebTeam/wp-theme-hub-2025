<?php
/**
 * Duplicate User Detection Utility (Database-Backed Version)
 * Uses a custom table for incremental saves, supports resume, and preloads user data in batches.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ---------------------------------------------------------------------------------
 * 1. Database Table Setup
 * ---------------------------------------------------------------------------------
 */

function dup_detect_get_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'dup_detect_results';
}

function dup_detect_create_table() {
    global $wpdb;
    $table_name = dup_detect_get_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        session_id varchar(32) NOT NULL,
        personnel_id bigint(20) NOT NULL,
        expert_id bigint(20) NOT NULL,
        confidence int(3) NOT NULL,
        reasons longtext NOT NULL,
        personnel_data longtext NOT NULL,
        expert_data longtext NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY session_id (session_id),
        KEY confidence (confidence)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * ---------------------------------------------------------------------------------
 * 2. Fuzzy Matching Helper Functions
 * ---------------------------------------------------------------------------------
 */

function normalize_name($name) {
    if (empty($name)) return '';
    $name = strtolower(trim($name));
    $name = preg_replace('/[^a-z0-9\s]/', '', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    return $name;
}

function calculate_similarity($str1, $str2) {
    if (empty($str1) || empty($str2)) {
        return ['levenshtein' => 0, 'similar_text' => 0, 'soundex' => false, 'metaphone' => false];
    }
    $norm1 = normalize_name($str1);
    $norm2 = normalize_name($str2);
    if (empty($norm1) || empty($norm2)) {
        return ['levenshtein' => 0, 'similar_text' => 0, 'soundex' => false, 'metaphone' => false];
    }
    $max_len = max(strlen($norm1), strlen($norm2));
    $lev_distance = levenshtein($norm1, $norm2);
    $lev_similarity = $max_len > 0 ? (1 - ($lev_distance / $max_len)) * 100 : 0;
    similar_text($norm1, $norm2, $similar_percent);
    return [
        'levenshtein' => round($lev_similarity, 2),
        'similar_text' => round($similar_percent, 2),
        'soundex' => soundex($norm1) === soundex($norm2),
        'metaphone' => metaphone($norm1) === metaphone($norm2)
    ];
}

function check_nickname_match($name1, $name2) {
    $nicknames = [
        'robert' => ['bob', 'rob', 'bobby', 'robbie'],
        'william' => ['bill', 'will', 'billy', 'willy'],
        'richard' => ['rick', 'dick', 'rich', 'richie'],
        'james' => ['jim', 'jimmy', 'jamie'],
        'john' => ['jack', 'johnny', 'jon'],
        'michael' => ['mike', 'mikey', 'mick'],
        'david' => ['dave', 'davey'],
        'joseph' => ['joe', 'joey'],
        'thomas' => ['tom', 'tommy'],
        'charles' => ['charlie', 'chuck', 'chas'],
        'elizabeth' => ['liz', 'beth', 'betty', 'lizzy', 'eliza'],
        'margaret' => ['maggie', 'meg', 'peggy', 'marge'],
        'jennifer' => ['jen', 'jenny'],
        'katherine' => ['kate', 'kathy', 'katie', 'kit'],
        'patricia' => ['pat', 'patty', 'tricia'],
        'christopher' => ['chris', 'kit'],
        'anthony' => ['tony', 'ant'],
        'daniel' => ['dan', 'danny'],
        'matthew' => ['matt', 'matty'],
        'andrew' => ['andy', 'drew'],
        'benjamin' => ['ben', 'benny'],
        'nicholas' => ['nick', 'nicky'],
        'jonathan' => ['jon', 'john', 'jonny'],
        'alexander' => ['alex', 'al', 'xander'],
        'samuel' => ['sam', 'sammy'],
        'stephen' => ['steve', 'stevie'],
        'edward' => ['ed', 'eddie', 'ted', 'teddy'],
        'timothy' => ['tim', 'timmy'],
        'gregory' => ['greg', 'gregg'],
        'rebecca' => ['becky', 'becca'],
        'jessica' => ['jess', 'jessie'],
        'susan' => ['sue', 'susie', 'suzy'],
        'deborah' => ['deb', 'debbie'],
        'barbara' => ['barb', 'barbie'],
        'dorothy' => ['dot', 'dottie', 'dorrie'],
    ];
    $n1 = strtolower(trim($name1));
    $n2 = strtolower(trim($name2));
    foreach ($nicknames as $full => $nicks) {
        $all_variants = array_merge([$full], $nicks);
        if (in_array($n1, $all_variants) && in_array($n2, $all_variants) && $n1 !== $n2) {
            return true;
        }
    }
    return false;
}

/**
 * Compare users using preloaded data arrays instead of database lookups
 */
function compare_users_preloaded($personnel_data, $expert_data, $threshold) {
    $reasons = [];
    $confidence_score = 0;

    $p_first = $personnel_data['first_name'];
    $p_last = $personnel_data['last_name'];
    $p_display = $personnel_data['display_name'];
    $p_email = $personnel_data['email'];
    $p_phone = $personnel_data['phone'];

    $e_first = $expert_data['first_name'];
    $e_last = $expert_data['last_name'];
    $e_display = $expert_data['display_name'];
    $e_email = $expert_data['email'];
    $e_phone = $expert_data['phone'];

    $p_full = normalize_name($p_first . ' ' . $p_last);
    $e_full = normalize_name($e_first . ' ' . $e_last);

    if (!empty($p_full) && !empty($e_full) && $p_full === $e_full) {
        $confidence_score += 50;
        $reasons[] = "EXACT full name match: '{$p_first} {$p_last}'";
    }

    $first_sim = calculate_similarity($p_first, $e_first);
    if ($first_sim['levenshtein'] >= 90) {
        $confidence_score += 15;
        $reasons[] = "First name high match ({$first_sim['levenshtein']}%): '{$p_first}' vs '{$e_first}'";
    } elseif ($first_sim['levenshtein'] >= 75) {
        $confidence_score += 10;
        $reasons[] = "First name similar ({$first_sim['levenshtein']}%): '{$p_first}' vs '{$e_first}'";
    } elseif ($first_sim['metaphone']) {
        $confidence_score += 8;
        $reasons[] = "First name sounds similar (metaphone): '{$p_first}' vs '{$e_first}'";
    }

    $last_sim = calculate_similarity($p_last, $e_last);
    if ($last_sim['levenshtein'] >= 90) {
        $confidence_score += 20;
        $reasons[] = "Last name high match ({$last_sim['levenshtein']}%): '{$p_last}' vs '{$e_last}'";
    } elseif ($last_sim['levenshtein'] >= 75) {
        $confidence_score += 12;
        $reasons[] = "Last name similar ({$last_sim['levenshtein']}%): '{$p_last}' vs '{$e_last}'";
    } elseif ($last_sim['metaphone']) {
        $confidence_score += 10;
        $reasons[] = "Last name sounds similar (metaphone): '{$p_last}' vs '{$e_last}'";
    }

    $display_sim = calculate_similarity($p_display, $e_display);
    if ($display_sim['levenshtein'] >= 90) {
        $confidence_score += 10;
        $reasons[] = "Display name high match ({$display_sim['levenshtein']}%): '{$p_display}' vs '{$e_display}'";
    }

    $p_is_spoofed = preg_match('/\.spoofed$/', $p_email) || preg_match('/@placeholder\./', $p_email);
    $e_is_spoofed = preg_match('/\.spoofed$/', $e_email) || preg_match('/@placeholder\./', $e_email);

    if (!$p_is_spoofed && !$e_is_spoofed && !empty($p_email) && !empty($e_email)) {
        if (strtolower($p_email) === strtolower($e_email)) {
            $confidence_score += 25;
            $reasons[] = "EXACT email match: '{$p_email}'";
        } else {
            $p_local = explode('@', $p_email)[0];
            $e_local = explode('@', $e_email)[0];
            $email_sim = calculate_similarity($p_local, $e_local);
            if ($email_sim['levenshtein'] >= 85) {
                $confidence_score += 10;
                $reasons[] = "Email local part similar ({$email_sim['levenshtein']}%): '{$p_local}' vs '{$e_local}'";
            }
        }
    }

    if (!empty($p_phone) && !empty($e_phone)) {
        $p_phone_clean = preg_replace('/[^0-9]/', '', $p_phone);
        $e_phone_clean = preg_replace('/[^0-9]/', '', $e_phone);
        if (!empty($p_phone_clean) && !empty($e_phone_clean) && $p_phone_clean === $e_phone_clean) {
            $confidence_score += 20;
            $reasons[] = "EXACT phone match: '{$p_phone}'";
        }
    }

    // Name inversion check
    $inv_first = calculate_similarity($p_first, $e_last);
    $inv_last = calculate_similarity($p_last, $e_first);
    if ($inv_first['levenshtein'] >= 85 && $inv_last['levenshtein'] >= 85) {
        $confidence_score += 15;
        $reasons[] = "Possible name inversion: '{$p_first} {$p_last}' vs '{$e_first} {$e_last}'";
    }

    if (check_nickname_match($p_first, $e_first) && $last_sim['levenshtein'] >= 85) {
        $confidence_score += 12;
        $reasons[] = "Possible nickname match: '{$p_first}' may be nickname of '{$e_first}' (or vice versa)";
    }

    $confidence_score = min($confidence_score, 100);

    if ($confidence_score < $threshold) {
        return null;
    }

    return [
        'confidence' => $confidence_score,
        'reasons' => $reasons,
        'personnel' => [
            'id' => $personnel_data['id'],
            'login' => $personnel_data['login'],
            'name' => $p_first . ' ' . $p_last,
            'display' => $p_display,
            'email' => $p_email,
            'phone' => $p_phone
        ],
        'expert' => [
            'id' => $expert_data['id'],
            'login' => $expert_data['login'],
            'name' => $e_first . ' ' . $e_last,
            'display' => $e_display,
            'email' => $e_email,
            'phone' => $e_phone
        ]
    ];
}

/**
 * Preload user data for a batch of user IDs
 */
function preload_user_data($user_ids) {
    global $wpdb;
    
    if (empty($user_ids)) return [];
    
    $user_ids = array_map('intval', $user_ids);
    $id_placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
    
    // Get basic user data
    $users_query = $wpdb->prepare(
        "SELECT ID, user_login, display_name, user_email FROM {$wpdb->users} WHERE ID IN ($id_placeholders)",
        ...$user_ids
    );
    $users = $wpdb->get_results($users_query, ARRAY_A);
    
    $user_data = [];
    foreach ($users as $user) {
        $user_data[$user['ID']] = [
            'id' => $user['ID'],
            'login' => $user['user_login'],
            'display_name' => $user['display_name'],
            'wp_email' => $user['user_email'],
            'first_name' => '',
            'last_name' => '',
            'email' => $user['user_email'],
            'phone' => ''
        ];
    }
    
    // Get user meta (first_name, last_name)
    $meta_query = $wpdb->prepare(
        "SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} 
         WHERE user_id IN ($id_placeholders) AND meta_key IN ('first_name', 'last_name')",
        ...$user_ids
    );
    $metas = $wpdb->get_results($meta_query, ARRAY_A);
    
    foreach ($metas as $meta) {
        if (isset($user_data[$meta['user_id']])) {
            $user_data[$meta['user_id']][$meta['meta_key']] = $meta['meta_value'];
        }
    }
    
    // Get ACF fields (uga_email, phone_number) - stored in postmeta with user_ prefix or usermeta
    // ACF stores user fields in usermeta with underscore prefix for field key
    $acf_meta_query = $wpdb->prepare(
        "SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} 
         WHERE user_id IN ($id_placeholders) AND meta_key IN ('uga_email', 'phone_number')",
        ...$user_ids
    );
    $acf_metas = $wpdb->get_results($acf_meta_query, ARRAY_A);
    
    foreach ($acf_metas as $meta) {
        if (isset($user_data[$meta['user_id']])) {
            if ($meta['meta_key'] === 'uga_email' && !empty($meta['meta_value'])) {
                $user_data[$meta['user_id']]['email'] = $meta['meta_value'];
            } elseif ($meta['meta_key'] === 'phone_number') {
                $user_data[$meta['user_id']]['phone'] = $meta['meta_value'];
            }
        }
    }
    
    return $user_data;
}

/**
 * ---------------------------------------------------------------------------------
 * 3. AJAX Handlers
 * ---------------------------------------------------------------------------------
 */

add_action('wp_ajax_dup_detect_init', 'ajax_dup_detect_init');
function ajax_dup_detect_init() {
    check_ajax_referer('dup_detect_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    // Ensure table exists
    dup_detect_create_table();

    $personnel_users = get_users(['role' => 'personnel_user', 'number' => -1, 'fields' => 'ID']);
    $expert_users = get_users(['role' => 'expert_user', 'number' => -1, 'fields' => 'ID']);

    $personnel_ids = array_map('intval', $personnel_users);
    $expert_ids = array_map('intval', $expert_users);

    // Generate unique session ID
    $session_id = wp_generate_password(16, false);
    
    // Store session data
    update_option('dup_detect_session', [
        'session_id' => $session_id,
        'personnel_ids' => $personnel_ids,
        'expert_ids' => $expert_ids,
        'current_position' => 0,
        'started_at' => current_time('mysql'),
        'status' => 'running'
    ], false);

    $total = count($personnel_ids) * count($expert_ids);

    wp_send_json_success([
        'session_id' => $session_id,
        'personnel_count' => count($personnel_ids),
        'expert_count' => count($expert_ids),
        'total_comparisons' => $total,
        'message' => "Found " . count($personnel_ids) . " Personnel Users and " . count($expert_ids) . " Expert Users. Total comparisons: " . number_format($total)
    ]);
}

add_action('wp_ajax_dup_detect_batch', 'ajax_dup_detect_batch');
function ajax_dup_detect_batch() {
    check_ajax_referer('dup_detect_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    global $wpdb;
    $table_name = dup_detect_get_table_name();

    $batch_start = intval($_POST['batch_start'] ?? 0);
    $batch_size = intval($_POST['batch_size'] ?? 500);
    $threshold = intval($_POST['threshold'] ?? 40);

    $session = get_option('dup_detect_session');
    if (!$session || empty($session['personnel_ids']) || empty($session['expert_ids'])) {
        wp_send_json_error('Session expired. Please restart the detection.');
    }

    $personnel_ids = $session['personnel_ids'];
    $expert_ids = $session['expert_ids'];
    $session_id = $session['session_id'];

    $p_count = count($personnel_ids);
    $e_count = count($expert_ids);
    $total = $p_count * $e_count;

    // Track execution time - leave buffer for response
    $start_time = microtime(true);
    $max_execution = min(25, (int)ini_get('max_execution_time') - 5); // 25 sec max, or less if PHP limit is lower
    if ($max_execution <= 0) $max_execution = 25;

    $matches = [];
    $processed = 0;
    $log_messages = [];
    
    // Figure out which personnel users we'll need for this batch
    $batch_end = min($batch_start + $batch_size, $total);
    $p_start_index = intval($batch_start / $e_count);
    $p_end_index = intval(($batch_end - 1) / $e_count);
    
    // Preload personnel data for this batch range
    $personnel_batch_ids = array_slice($personnel_ids, $p_start_index, $p_end_index - $p_start_index + 1);
    $personnel_data_cache = preload_user_data($personnel_batch_ids);
    
    // Preload ALL expert data (they're reused across personnel)
    // Only do this once per session - cache in a transient
    $expert_cache_key = 'dup_detect_expert_cache_' . $session_id;
    $expert_data_cache = get_transient($expert_cache_key);
    if ($expert_data_cache === false) {
        $expert_data_cache = preload_user_data($expert_ids);
        set_transient($expert_cache_key, $expert_data_cache, 2 * HOUR_IN_SECONDS);
    }

    for ($i = $batch_start; $i < $batch_start + $batch_size && $i < $total; $i++) {
        // Check execution time
        $elapsed = microtime(true) - $start_time;
        if ($elapsed > $max_execution) {
            // Save progress and exit gracefully
            $log_messages[] = [
                'type' => 'info',
                'message' => "⏱️ Time limit approaching, saving progress at position {$i}"
            ];
            break;
        }

        $p_index = intval($i / $e_count);
        $e_index = $i % $e_count;

        if ($p_index >= $p_count || $e_index >= $e_count) break;

        $p_id = $personnel_ids[$p_index];
        $e_id = $expert_ids[$e_index];
        
        // Use cached data
        $p_data = $personnel_data_cache[$p_id] ?? null;
        $e_data = $expert_data_cache[$e_id] ?? null;
        
        if (!$p_data || !$e_data) {
            $processed++;
            continue;
        }

        $result = compare_users_preloaded($p_data, $e_data, $threshold);
        if ($result) {
            // Save to database immediately
            $wpdb->insert($table_name, [
                'session_id' => $session_id,
                'personnel_id' => $p_id,
                'expert_id' => $e_id,
                'confidence' => $result['confidence'],
                'reasons' => json_encode($result['reasons']),
                'personnel_data' => json_encode($result['personnel']),
                'expert_data' => json_encode($result['expert'])
            ]);
            
            $matches[] = $result;
            $log_messages[] = [
                'type' => 'match',
                'message' => "🎯 Match found: '{$result['personnel']['name']}' (P#{$p_id}) ↔ '{$result['expert']['name']}' (E#{$e_id}) - {$result['confidence']}%"
            ];
        }
        $processed++;
    }

    $new_start = $batch_start + $processed;
    $is_complete = $new_start >= $total;
    $progress = $total > 0 ? round(($new_start / $total) * 100, 2) : 100;

    // Update session progress
    $session['current_position'] = $new_start;
    if ($is_complete) {
        $session['status'] = 'complete';
    }
    update_option('dup_detect_session', $session, false);

    // Get total match count from database
    $total_matches = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE session_id = %s",
        $session_id
    ));

    wp_send_json_success([
        'processed' => $processed,
        'next_start' => $new_start,
        'total' => $total,
        'progress' => $progress,
        'is_complete' => $is_complete,
        'matches_in_batch' => count($matches),
        'total_matches' => intval($total_matches),
        'log' => $log_messages,
        'elapsed_time' => round(microtime(true) - $start_time, 2)
    ]);
}

add_action('wp_ajax_dup_detect_results', 'ajax_dup_detect_results');
function ajax_dup_detect_results() {
    check_ajax_referer('dup_detect_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    global $wpdb;
    $table_name = dup_detect_get_table_name();
    
    $session = get_option('dup_detect_session');
    if (!$session) {
        wp_send_json_error('No session found');
    }
    
    $session_id = $session['session_id'];
    
    // Get all results from database
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE session_id = %s ORDER BY confidence DESC",
        $session_id
    ), ARRAY_A);
    
    $results = [];
    foreach ($rows as $row) {
        $personnel = json_decode($row['personnel_data'], true);
        $expert = json_decode($row['expert_data'], true);
        
        // Enrich with additional IDs
        $personnel['personnel_id'] = get_field('personnel_id', 'user_' . $personnel['id']) ?: '';
        $expert['source_expert_id'] = get_field('source_expert_id', 'user_' . $expert['id']) ?: '';
        $expert['writer_id'] = get_field('writer_id', 'user_' . $expert['id']) ?: '';
        
        $results[] = [
            'confidence' => intval($row['confidence']),
            'reasons' => json_decode($row['reasons'], true),
            'personnel' => $personnel,
            'expert' => $expert
        ];
    }

    // Store for CSV export
    set_transient('dup_detect_export_results', $results, HOUR_IN_SECONDS);
    
    // Clean up expert cache
    delete_transient('dup_detect_expert_cache_' . $session_id);

    wp_send_json_success(['duplicates' => $results]);
}

add_action('wp_ajax_dup_detect_resume', 'ajax_dup_detect_resume');
function ajax_dup_detect_resume() {
    check_ajax_referer('dup_detect_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    global $wpdb;
    $table_name = dup_detect_get_table_name();
    
    $session = get_option('dup_detect_session');
    if (!$session) {
        wp_send_json_error('No previous session found');
    }
    
    $session_id = $session['session_id'];
    $p_count = count($session['personnel_ids']);
    $e_count = count($session['expert_ids']);
    $total = $p_count * $e_count;
    $current = $session['current_position'];
    
    $total_matches = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE session_id = %s",
        $session_id
    ));
    
    wp_send_json_success([
        'session_id' => $session_id,
        'current_position' => $current,
        'total' => $total,
        'total_matches' => intval($total_matches),
        'status' => $session['status'],
        'started_at' => $session['started_at'],
        'progress' => $total > 0 ? round(($current / $total) * 100, 2) : 0,
        'message' => "Found previous session started at {$session['started_at']}. Position: " . number_format($current) . " / " . number_format($total) . " ({$total_matches} matches found)"
    ]);
}

add_action('wp_ajax_dup_detect_clear', 'ajax_dup_detect_clear');
function ajax_dup_detect_clear() {
    check_ajax_referer('dup_detect_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    global $wpdb;
    $table_name = dup_detect_get_table_name();
    
    $session = get_option('dup_detect_session');
    if ($session) {
        // Delete results for this session
        $wpdb->delete($table_name, ['session_id' => $session['session_id']]);
        delete_transient('dup_detect_expert_cache_' . $session['session_id']);
    }
    
    delete_option('dup_detect_session');
    
    wp_send_json_success(['message' => 'Session cleared']);
}

/**
 * ---------------------------------------------------------------------------------
 * 4. CSV Export Handler
 * ---------------------------------------------------------------------------------
 */

add_action('admin_action_export_duplicates_csv', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Permission denied');
    }
    
    check_admin_referer('export_duplicates_csv');
    
    $results = get_transient('dup_detect_export_results');
    if (empty($results)) {
        wp_die('No results available for export. Please run detection first.');
    }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=duplicate-users-' . date('Y-m-d-His') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Header row - column names must match what stage-2 merge tool expects
    fputcsv($output, [
        'Confidence',
        'Personnel WP ID',
        'Personnel Name',
        'Personnel Email',
        'Personnel Phone',
        'personnel_id',
        'Expert WP ID',
        'Expert Name',
        'Expert Email',
        'Expert Phone',
        'expert_source_id',
        'expert_writer_id',
        'Match Reasons'
    ]);
    
    foreach ($results as $row) {
        fputcsv($output, [
            $row['confidence'] . '%',
            $row['personnel']['id'],
            $row['personnel']['name'],
            $row['personnel']['email'],
            $row['personnel']['phone'] ?? '',
            $row['personnel']['personnel_id'] ?? '',
            $row['expert']['id'],
            $row['expert']['name'],
            $row['expert']['email'],
            $row['expert']['phone'] ?? '',
            $row['expert']['source_expert_id'] ?? '',
            $row['expert']['writer_id'] ?? '',
            implode('; ', $row['reasons'])
        ]);
    }
    
    fclose($output);
    exit;
});

/**
 * ---------------------------------------------------------------------------------
 * 5. Admin Page Rendering
 * ---------------------------------------------------------------------------------
 */

add_action('admin_menu', function() {
    add_users_page(
        'Duplicate User Detection',
        'Duplicate Detection',
        'manage_options',
        'duplicate-user-detection',
        'render_duplicate_detection_page'
    );
});

function render_duplicate_detection_page() {
    ?>
    <div class="wrap">
        <h1>🔍 Duplicate User Detection</h1>
        <p>Compares Personnel Users against Expert Users using fuzzy name matching, email comparison, and phone matching.</p>
        
        <div id="session-check" style="background:#fff3cd;border:1px solid #ffc107;padding:15px;margin:20px 0;display:none;">
            <strong>⚠️ Previous Session Found</strong>
            <p id="session-info"></p>
            <button id="resume-session" class="button button-primary">Resume Session</button>
            <button id="clear-session" class="button">Start Fresh</button>
        </div>

        <div style="background:#fff;padding:20px;border:1px solid #ddd;margin-bottom:20px;">
            <h3 style="margin-top:0;">Settings</h3>
            <table class="form-table">
                <tr>
                    <th>Confidence Threshold</th>
                    <td>
                        <input type="number" id="threshold" value="40" min="10" max="100" style="width:80px;"> %
                        <p class="description">Minimum confidence score to report as potential duplicate (recommended: 40-50%)</p>
                    </td>
                </tr>
                <tr>
                    <th>Batch Size</th>
                    <td>
                        <input type="number" id="batch_size" value="1000" min="100" max="5000" style="width:100px;">
                        <p class="description">Comparisons per batch. Higher = faster but more memory. (recommended: 500-2000)</p>
                    </td>
                </tr>
            </table>
            <p>
                <button id="start-detection" class="button button-primary button-hero">🚀 Start Detection</button>
                <button id="stop-detection" class="button" style="display:none;">⏹️ Stop</button>
            </p>
        </div>

        <div id="progress-section" style="display:none;background:#fff;padding:20px;border:1px solid #ddd;margin-bottom:20px;">
            <h3 style="margin-top:0;">Progress</h3>
            <div style="background:#f0f0f0;border-radius:4px;height:30px;margin-bottom:10px;overflow:hidden;">
                <div id="progress-bar" style="background:#2271b1;height:100%;width:0%;transition:width 0.3s;"></div>
            </div>
            <p id="progress-text" style="margin:0;">Initializing...</p>
            <p style="margin-top:10px;"><strong>Matches found:</strong> <span id="match-count">0</span></p>
        </div>

        <div id="console-section" style="display:none;background:#fff;padding:20px;border:1px solid #ddd;margin-bottom:20px;">
            <h3 style="margin-top:0;">Log</h3>
            <div id="console-output" style="background:#1e1e1e;color:#d4d4d4;padding:15px;height:200px;overflow-y:auto;font-family:monospace;font-size:12px;"></div>
        </div>

        <div id="results-section" style="display:none;background:#fff;padding:20px;border:1px solid #ddd;">
            <h3 style="margin-top:0;">Results</h3>
            <div id="results-summary"></div>
            <div id="results-table"></div>
        </div>
    </div>

    <script>
    jQuery(function($) {
        const nonce = '<?php echo wp_create_nonce('dup_detect_nonce'); ?>';
        const ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        let isRunning = false;
        let shouldStop = false;

        // Check for existing session on page load
        checkExistingSession();

        function checkExistingSession() {
            $.post(ajaxurl, {action: 'dup_detect_resume', nonce: nonce}, function(response) {
                if (response.success && response.data.status === 'running' && response.data.current_position > 0) {
                    $('#session-info').text(response.data.message);
                    $('#session-check').show();
                }
            });
        }

        function log(message, type = 'info') {
            const time = new Date().toLocaleTimeString();
            const $console = $('#console-output');
            const colors = {info: '#d4d4d4', success: '#6a9955', error: '#f14c4c', warning: '#cca700', match: '#569cd6'};
            const color = colors[type] || colors.info;
            $console.append(`<div style="color:${color}">[${time}] ${message}</div>`);
            $console.scrollTop($console[0].scrollHeight);
        }

        function updateProgress(percent, text) {
            $('#progress-bar').css('width', percent + '%');
            $('#progress-text').text(text);
        }

        function renderResults(duplicates) {
            if (!duplicates.length) {
                $('#results-summary').html('<div class="notice notice-success"><p><strong>No potential duplicates found!</strong></p></div>');
                $('#results-table').empty();
                return;
            }

            let high = 0, medium = 0, low = 0;
            duplicates.forEach(d => {
                if (d.confidence >= 70) high++;
                else if (d.confidence >= 50) medium++;
                else low++;
            });

            const csvUrl = '<?php echo admin_url('admin.php?action=export_duplicates_csv&_wpnonce='); ?>' + '<?php echo wp_create_nonce('export_duplicates_csv'); ?>';
            $('#results-summary').html(`
                <div style="background:#f9f9f9;padding:15px;margin-bottom:20px;border:1px solid #ddd;">
                    <strong>Found ${duplicates.length} potential duplicates:</strong>
                    <span style="color:#d63638;"> High (70%+): ${high}</span> |
                    <span style="color:#dba617;"> Medium (50-69%): ${medium}</span> |
                    <span style="color:#2271b1;"> Lower (&lt;50%): ${low}</span>
                    <br><br>
                    <a href="${csvUrl}" class="button button-primary">📥 Export to CSV</a>
                    <span style="margin-left:10px;color:#666;">Download for review or use with the User Merge utility</span>
                </div>
            `);

            let html = `<table class="widefat striped"><thead><tr>
                <th style="width:60px">Conf.</th><th>Personnel User</th><th>Expert User</th><th>Match Reasons</th><th style="width:100px">Actions</th>
            </tr></thead><tbody>`;

            duplicates.forEach(d => {
                const confColor = d.confidence >= 70 ? '#d63638' : (d.confidence >= 50 ? '#dba617' : '#2271b1');
                const confBg = d.confidence >= 70 ? '#fcf0f1' : (d.confidence >= 50 ? '#fcf9e8' : '#f0f6fc');
                const p = d.personnel, e = d.expert;
                const reasons = d.reasons.map(r => `<li>${r}</li>`).join('');
                const pPersonnelId = p.personnel_id || 'N/A';
                const eSourceId = e.source_expert_id || 'N/A';
                const eWriterId = e.writer_id || 'N/A';

                html += `<tr style="background:${confBg}">
                    <td style="text-align:center;font-weight:bold;color:${confColor}">${d.confidence}%</td>
                    <td><strong>${p.name}</strong><br><small>WP ID: ${p.id} | Personnel ID: ${pPersonnelId}</small><br><small>${p.email}</small>${p.phone ? `<br><small>${p.phone}</small>` : ''}</td>
                    <td><strong>${e.name}</strong><br><small>WP ID: ${e.id}</small><br><small>Source Expert ID: ${eSourceId} | Writer ID: ${eWriterId}</small><br><small>${e.email}</small>${e.phone ? `<br><small>${e.phone}</small>` : ''}</td>
                    <td><ul style="margin:0;padding-left:15px;font-size:12px">${reasons}</ul></td>
                    <td><a href="user-edit.php?user_id=${p.id}" target="_blank" class="button button-small">Personnel</a><br><br>
                        <a href="user-edit.php?user_id=${e.id}" target="_blank" class="button button-small">Expert</a></td>
                </tr>`;
            });

            html += '</tbody></table>';
            $('#results-table').html(html);
        }

        function runBatch(start, batchSize, threshold, total) {
            if (shouldStop) {
                log('Detection stopped by user. Progress saved - you can resume later.', 'warning');
                isRunning = false;
                $('#start-detection').prop('disabled', false);
                $('#stop-detection').hide();
                checkExistingSession();
                return;
            }

            $.post(ajaxurl, {
                action: 'dup_detect_batch',
                nonce: nonce,
                batch_start: start,
                batch_size: batchSize,
                threshold: threshold
            }, function(response) {
                if (!response.success) {
                    log('Error: ' + (response.data || 'Unknown error'), 'error');
                    log('Progress saved at position ' + start + '. You can resume later.', 'warning');
                    isRunning = false;
                    $('#start-detection').prop('disabled', false);
                    $('#stop-detection').hide();
                    checkExistingSession();
                    return;
                }

                const data = response.data;
                updateProgress(data.progress, `Processing: ${data.next_start.toLocaleString()} / ${data.total.toLocaleString()} (${data.progress}%)`);
                $('#match-count').text(data.total_matches);

                if (data.log && data.log.length) {
                    data.log.forEach(l => log(l.message, l.type || 'match'));
                }

                if (data.processed > 0 && !data.log.some(l => l.type === 'match')) {
                    log(`Processed batch: ${start.toLocaleString()} - ${data.next_start.toLocaleString()} (${data.matches_in_batch} matches) [${data.elapsed_time}s]`, 'info');
                }

                if (data.is_complete) {
                    log('Detection complete! Fetching results...', 'success');
                    updateProgress(100, 'Complete! Loading results...');
                    fetchResults();
                } else {
                    runBatch(data.next_start, batchSize, threshold, total);
                }
            }).fail(function(xhr) {
                log('AJAX error: ' + xhr.statusText, 'error');
                log('Progress saved. You can resume later.', 'warning');
                isRunning = false;
                $('#start-detection').prop('disabled', false);
                $('#stop-detection').hide();
                checkExistingSession();
            });
        }

        function fetchResults() {
            $.post(ajaxurl, {action: 'dup_detect_results', nonce: nonce}, function(response) {
                isRunning = false;
                $('#start-detection').prop('disabled', false);
                $('#stop-detection').hide();
                $('#session-check').hide();

                if (response.success) {
                    log(`Found ${response.data.duplicates.length} potential duplicates.`, 'success');
                    $('#results-section').show();
                    renderResults(response.data.duplicates);
                } else {
                    log('Error fetching results: ' + (response.data || 'Unknown'), 'error');
                }
            });
        }

        function startDetection(resumeFrom = 0) {
            if (isRunning) return;
            isRunning = true;
            shouldStop = false;

            $('#start-detection').prop('disabled', true);
            $('#stop-detection').show();
            $('#progress-section, #console-section').show();
            $('#results-section').hide();
            $('#session-check').hide();
            
            if (resumeFrom === 0) {
                $('#console-output').empty();
            }
            
            $('#match-count').text('0');
            updateProgress(0, 'Initializing...');

            if (resumeFrom > 0) {
                log('Resuming detection from position ' + resumeFrom.toLocaleString() + '...', 'info');
                const batchSize = parseInt($('#batch_size').val());
                const threshold = parseInt($('#threshold').val());
                
                $.post(ajaxurl, {action: 'dup_detect_resume', nonce: nonce}, function(response) {
                    if (response.success) {
                        const data = response.data;
                        updateProgress(data.progress, `Resuming: ${data.current_position.toLocaleString()} / ${data.total.toLocaleString()}`);
                        $('#match-count').text(data.total_matches);
                        runBatch(data.current_position, batchSize, threshold, data.total);
                    }
                });
            } else {
                log('Starting duplicate detection...', 'info');
                $.post(ajaxurl, {action: 'dup_detect_init', nonce: nonce}, function(response) {
                    if (!response.success) {
                        log('Initialization failed: ' + (response.data || 'Unknown error'), 'error');
                        isRunning = false;
                        $('#start-detection').prop('disabled', false);
                        $('#stop-detection').hide();
                        return;
                    }

                    const data = response.data;
                    log(data.message, 'success');
                    log(`Starting comparisons with threshold: ${$('#threshold').val()}%, batch size: ${$('#batch_size').val()}`, 'info');

                    const batchSize = parseInt($('#batch_size').val());
                    const threshold = parseInt($('#threshold').val());
                    runBatch(0, batchSize, threshold, data.total_comparisons);
                }).fail(function(xhr) {
                    log('AJAX error during init: ' + xhr.statusText, 'error');
                    isRunning = false;
                    $('#start-detection').prop('disabled', false);
                    $('#stop-detection').hide();
                });
            }
        }

        $('#start-detection').on('click', function() {
            startDetection(0);
        });

        $('#resume-session').on('click', function() {
            $.post(ajaxurl, {action: 'dup_detect_resume', nonce: nonce}, function(response) {
                if (response.success) {
                    startDetection(response.data.current_position);
                }
            });
        });

        $('#clear-session').on('click', function() {
            if (confirm('This will delete all progress and results from the previous session. Continue?')) {
                $.post(ajaxurl, {action: 'dup_detect_clear', nonce: nonce}, function(response) {
                    if (response.success) {
                        $('#session-check').hide();
                        $('#console-output').empty();
                        log('Previous session cleared.', 'info');
                    }
                });
            }
        });

        $('#stop-detection').on('click', function() {
            shouldStop = true;
            log('Stopping after current batch...', 'warning');
        });
    });
    </script>
    <?php
}