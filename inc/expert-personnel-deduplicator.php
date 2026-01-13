<?php
/**
 * Duplicate User Detection Utility (Batched Version)
 * Uses AJAX batching to prevent timeouts and displays real-time progress.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ---------------------------------------------------------------------------------
 * 1. Fuzzy Matching Helper Functions
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

function compare_users_by_id($personnel_id, $expert_id, $threshold) {
    $personnel = get_user_by('ID', $personnel_id);
    $expert = get_user_by('ID', $expert_id);
    if (!$personnel || !$expert) return null;

    $reasons = [];
    $confidence_score = 0;

    $p_first = get_user_meta($personnel->ID, 'first_name', true);
    $p_last = get_user_meta($personnel->ID, 'last_name', true);
    $p_display = $personnel->display_name;
    $p_email = get_field('uga_email', 'user_' . $personnel->ID) ?: $personnel->user_email;
    $p_phone = get_field('phone_number', 'user_' . $personnel->ID);

    $e_first = get_user_meta($expert->ID, 'first_name', true);
    $e_last = get_user_meta($expert->ID, 'last_name', true);
    $e_display = $expert->display_name;
    $e_email = get_field('uga_email', 'user_' . $expert->ID) ?: $expert->user_email;
    $e_phone = get_field('phone_number', 'user_' . $expert->ID);

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
            'id' => $personnel->ID,
            'login' => $personnel->user_login,
            'name' => $p_first . ' ' . $p_last,
            'display' => $p_display,
            'email' => $p_email,
            'phone' => $p_phone
        ],
        'expert' => [
            'id' => $expert->ID,
            'login' => $expert->user_login,
            'name' => $e_first . ' ' . $e_last,
            'display' => $e_display,
            'email' => $e_email,
            'phone' => $e_phone
        ]
    ];
}

/**
 * ---------------------------------------------------------------------------------
 * 2. AJAX Handlers
 * ---------------------------------------------------------------------------------
 */

add_action('wp_ajax_dup_detect_init', 'ajax_dup_detect_init');
function ajax_dup_detect_init() {
    check_ajax_referer('dup_detect_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    $personnel_users = get_users(['role' => 'personnel_user', 'number' => -1, 'fields' => 'ID']);
    $expert_users = get_users(['role' => 'expert_user', 'number' => -1, 'fields' => 'ID']);

    $personnel_ids = array_map('intval', $personnel_users);
    $expert_ids = array_map('intval', $expert_users);

    set_transient('dup_detect_personnel', $personnel_ids, HOUR_IN_SECONDS);
    set_transient('dup_detect_experts', $expert_ids, HOUR_IN_SECONDS);
    delete_transient('dup_detect_results');

    $total = count($personnel_ids) * count($expert_ids);

    wp_send_json_success([
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

    $batch_start = intval($_POST['batch_start'] ?? 0);
    $batch_size = intval($_POST['batch_size'] ?? 500);
    $threshold = intval($_POST['threshold'] ?? 40);

    $personnel_ids = get_transient('dup_detect_personnel');
    $expert_ids = get_transient('dup_detect_experts');

    if (!$personnel_ids || !$expert_ids) {
        wp_send_json_error('Session expired. Please restart the detection.');
    }

    $p_count = count($personnel_ids);
    $e_count = count($expert_ids);
    $total = $p_count * $e_count;

    $matches = [];
    $processed = 0;
    $log_messages = [];

    for ($i = $batch_start; $i < $batch_start + $batch_size && $i < $total; $i++) {
        $p_index = intval($i / $e_count);
        $e_index = $i % $e_count;

        if ($p_index >= $p_count || $e_index >= $e_count) break;

        $p_id = $personnel_ids[$p_index];
        $e_id = $expert_ids[$e_index];

        $result = compare_users_by_id($p_id, $e_id, $threshold);
        if ($result) {
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
    $progress = $total > 0 ? round(($new_start / $total) * 100, 1) : 100;

    $existing_results = get_transient('dup_detect_results') ?: [];
    $all_results = array_merge($existing_results, $matches);
    set_transient('dup_detect_results', $all_results, HOUR_IN_SECONDS);

    wp_send_json_success([
        'processed' => $processed,
        'next_start' => $new_start,
        'total' => $total,
        'progress' => $progress,
        'is_complete' => $is_complete,
        'matches_in_batch' => count($matches),
        'total_matches' => count($all_results),
        'log' => $log_messages
    ]);
}

add_action('wp_ajax_dup_detect_results', 'ajax_dup_detect_results');
function ajax_dup_detect_results() {
    check_ajax_referer('dup_detect_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    $results = get_transient('dup_detect_results') ?: [];
    usort($results, function($a, $b) {
        return $b['confidence'] - $a['confidence'];
    });

    // Enrich results with personnel_id, source_expert_id, and writer_id
    foreach ($results as &$result) {
        $p_id = $result['personnel']['id'];
        $e_id = $result['expert']['id'];
        
        $result['personnel']['personnel_id'] = get_field('personnel_id', 'user_' . $p_id) ?: '';
        $result['expert']['source_expert_id'] = get_field('source_expert_id', 'user_' . $e_id) ?: '';
        $result['expert']['writer_id'] = get_field('writer_id', 'user_' . $e_id) ?: '';
    }
    unset($result);

    // Store results for CSV export (keep for 1 hour)
    set_transient('dup_detect_export_results', $results, HOUR_IN_SECONDS);

    delete_transient('dup_detect_personnel');
    delete_transient('dup_detect_experts');
    delete_transient('dup_detect_results');

    wp_send_json_success(['duplicates' => $results]);
}

// CSV Export handler
add_action('admin_init', 'handle_dup_detect_csv_export');
function handle_dup_detect_csv_export() {
    if (!isset($_GET['action']) || $_GET['action'] !== 'export_duplicates_csv') return;
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'export_duplicates_csv')) {
        wp_die('Security check failed');
    }
    if (!current_user_can('manage_options')) {
        wp_die('Permission denied');
    }

    $results = get_transient('dup_detect_export_results');
    if (!$results) {
        wp_die('No export data available. Please run the detection again.');
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=duplicate_users_' . date('Y-m-d_His') . '.csv');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    
    // CSV Header
    fputcsv($output, [
        'personnel_id',
        'expert_source_id',
        'expert_writer_id',
        'confidence',
        'personnel_wp_id',
        'personnel_name',
        'personnel_email',
        'expert_wp_id',
        'expert_name',
        'expert_email',
        'match_reasons'
    ]);

    foreach ($results as $row) {
        fputcsv($output, [
            $row['personnel']['personnel_id'],
            $row['expert']['source_expert_id'],
            $row['expert']['writer_id'],
            $row['confidence'],
            $row['personnel']['id'],
            $row['personnel']['name'],
            $row['personnel']['email'],
            $row['expert']['id'],
            $row['expert']['name'],
            $row['expert']['email'],
            implode(' | ', $row['reasons'])
        ]);
    }

    fclose($output);
    exit;
}

/**
 * ---------------------------------------------------------------------------------
 * 3. Admin Page
 * ---------------------------------------------------------------------------------
 */

add_action('admin_menu', 'add_duplicate_detection_page');
function add_duplicate_detection_page() {
    add_submenu_page(
        'caes-tools',
        'Duplicate User Detection',
        'Duplicate User Detection',
        'manage_options',
        'duplicate-user-detection',
        'duplicate_detection_page_content'
    );
}

function duplicate_detection_page_content() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions.'));
    }
    $nonce = wp_create_nonce('dup_detect_nonce');
    ?>
    <div class="wrap">
        <h1>Duplicate User Detection</h1>
        <p>Compares <strong>Personnel Users</strong> against <strong>Expert Users</strong> to find potential duplicates using fuzzy name matching.</p>

        <div class="card" style="max-width: 800px; padding: 20px; margin: 20px 0;">
            <h2 style="margin-top:0;">Detection Settings</h2>
            <table class="form-table">
                <tr>
                    <th><label for="threshold">Confidence Threshold</label></th>
                    <td>
                        <input type="range" id="threshold" min="10" max="90" value="40" style="width:300px" oninput="document.getElementById('threshold_val').textContent=this.value">
                        <span id="threshold_val" style="font-weight:bold;">40</span>%
                        <p class="description">Only matches at or above this threshold will be shown.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="batch_size">Batch Size</label></th>
                    <td>
                        <select id="batch_size">
                            <option value="250">250 (slower, less server load)</option>
                            <option value="500" selected>500 (balanced)</option>
                            <option value="1000">1000 (faster, more server load)</option>
                            <option value="2000">2000 (fastest)</option>
                        </select>
                        <p class="description">Number of comparisons per batch. Reduce if experiencing timeouts.</p>
                    </td>
                </tr>
            </table>
            <p>
                <button id="start-detection" class="button button-primary button-large">Start Detection</button>
                <button id="stop-detection" class="button button-secondary" style="display:none;">Stop</button>
            </p>
        </div>

        <div id="progress-section" style="display:none; max-width:800px;">
            <h2>Progress</h2>
            <div style="background:#f0f0f0; border-radius:4px; height:30px; margin-bottom:10px;">
                <div id="progress-bar" style="background:#0073aa; height:100%; border-radius:4px; width:0%; transition:width 0.3s;"></div>
            </div>
            <p id="progress-text">Initializing...</p>
            <p><strong>Matches found:</strong> <span id="match-count">0</span></p>
        </div>

        <div id="console-section" style="display:none; max-width:800px; margin-top:20px;">
            <h2>Console Output</h2>
            <div id="console-output" style="background:#1e1e1e; color:#d4d4d4; font-family:monospace; font-size:12px; height:300px; overflow-y:auto; padding:10px; border-radius:4px;"></div>
        </div>

        <div id="results-section" style="display:none; margin-top:20px;">
            <h2>Results</h2>
            <div id="results-summary"></div>
            <div id="results-table"></div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        const nonce = '<?php echo $nonce; ?>';
        let isRunning = false;
        let shouldStop = false;

        function log(message, type = 'info') {
            const $console = $('#console-output');
            const time = new Date().toLocaleTimeString();
            const colors = {info:'#9cdcfe', success:'#4ec9b0', warning:'#dcdcaa', error:'#f14c4c', match:'#ce9178'};
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
                log('Detection stopped by user.', 'warning');
                isRunning = false;
                $('#start-detection').prop('disabled', false);
                $('#stop-detection').hide();
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
                    isRunning = false;
                    $('#start-detection').prop('disabled', false);
                    $('#stop-detection').hide();
                    return;
                }

                const data = response.data;
                updateProgress(data.progress, `Processing: ${data.next_start.toLocaleString()} / ${data.total.toLocaleString()} (${data.progress}%)`);
                $('#match-count').text(data.total_matches);

                if (data.log && data.log.length) {
                    data.log.forEach(l => log(l.message, 'match'));
                }

                if (data.processed > 0 && data.log.length === 0) {
                    log(`Processed batch: ${start.toLocaleString()} - ${data.next_start.toLocaleString()} (${data.matches_in_batch} matches)`, 'info');
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
                isRunning = false;
                $('#start-detection').prop('disabled', false);
                $('#stop-detection').hide();
            });
        }

        function fetchResults() {
            $.post(ajaxurl, {action: 'dup_detect_results', nonce: nonce}, function(response) {
                isRunning = false;
                $('#start-detection').prop('disabled', false);
                $('#stop-detection').hide();

                if (response.success) {
                    log(`Found ${response.data.duplicates.length} potential duplicates.`, 'success');
                    $('#results-section').show();
                    renderResults(response.data.duplicates);
                } else {
                    log('Error fetching results: ' + (response.data || 'Unknown'), 'error');
                }
            });
        }

        $('#start-detection').on('click', function() {
            if (isRunning) return;
            isRunning = true;
            shouldStop = false;

            $(this).prop('disabled', true);
            $('#stop-detection').show();
            $('#progress-section, #console-section').show();
            $('#results-section').hide();
            $('#console-output').empty();
            $('#match-count').text('0');
            updateProgress(0, 'Initializing...');

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
        });

        $('#stop-detection').on('click', function() {
            shouldStop = true;
            log('Stopping after current batch...', 'warning');
        });
    });
    </script>
    <?php
}