<?php
/**
 * Duplicate User Detection Utility
 * Identifies potential duplicate users between Personnel Users and Expert Users
 * using fuzzy name matching and other heuristics.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ---------------------------------------------------------------------------------
 * 1. Fuzzy Matching Helper Functions
 * ---------------------------------------------------------------------------------
 */

/**
 * Normalizes a name string for comparison
 * Removes special characters, extra spaces, converts to lowercase
 */
function normalize_name($name) {
    if (empty($name)) return '';
    $name = strtolower(trim($name));
    $name = preg_replace('/[^a-z0-9\s]/', '', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    return $name;
}

/**
 * Calculates similarity between two strings using multiple methods
 * Returns an array with different similarity scores
 */
function calculate_similarity($str1, $str2) {
    if (empty($str1) || empty($str2)) {
        return ['levenshtein' => 0, 'similar_text' => 0, 'soundex' => false, 'metaphone' => false];
    }
    
    $norm1 = normalize_name($str1);
    $norm2 = normalize_name($str2);
    
    if (empty($norm1) || empty($norm2)) {
        return ['levenshtein' => 0, 'similar_text' => 0, 'soundex' => false, 'metaphone' => false];
    }
    
    // Levenshtein distance (lower is better, convert to percentage)
    $max_len = max(strlen($norm1), strlen($norm2));
    $lev_distance = levenshtein($norm1, $norm2);
    $lev_similarity = $max_len > 0 ? (1 - ($lev_distance / $max_len)) * 100 : 0;
    
    // Similar text percentage
    similar_text($norm1, $norm2, $similar_percent);
    
    // Soundex comparison (phonetic)
    $soundex_match = soundex($norm1) === soundex($norm2);
    
    // Metaphone comparison (phonetic, more accurate than soundex)
    $metaphone_match = metaphone($norm1) === metaphone($norm2);
    
    return [
        'levenshtein' => round($lev_similarity, 2),
        'similar_text' => round($similar_percent, 2),
        'soundex' => $soundex_match,
        'metaphone' => $metaphone_match
    ];
}

/**
 * Compares two users and returns match details
 */
function compare_users($personnel, $expert) {
    $matches = [];
    $reasons = [];
    $confidence_score = 0;
    
    // Get user data
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
    
    // 1. Exact name match (highest confidence)
    $p_full = normalize_name($p_first . ' ' . $p_last);
    $e_full = normalize_name($e_first . ' ' . $e_last);
    
    if (!empty($p_full) && !empty($e_full) && $p_full === $e_full) {
        $confidence_score += 50;
        $reasons[] = "EXACT full name match: '{$p_first} {$p_last}'";
    }
    
    // 2. First name comparison
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
    
    // 3. Last name comparison (weighted higher)
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
    
    // 4. Display name comparison
    $display_sim = calculate_similarity($p_display, $e_display);
    if ($display_sim['levenshtein'] >= 90) {
        $confidence_score += 10;
        $reasons[] = "Display name high match ({$display_sim['levenshtein']}%): '{$p_display}' vs '{$e_display}'";
    }
    
    // 5. Email comparison (excluding spoofed emails)
    $p_email_clean = preg_replace('/@.*\.spoofed$/', '', $p_email);
    $e_email_clean = preg_replace('/@.*\.spoofed$/', '', $e_email);
    $p_is_spoofed = preg_match('/\.spoofed$/', $p_email) || preg_match('/@placeholder\./', $p_email);
    $e_is_spoofed = preg_match('/\.spoofed$/', $e_email) || preg_match('/@placeholder\./', $e_email);
    
    if (!$p_is_spoofed && !$e_is_spoofed && !empty($p_email) && !empty($e_email)) {
        if (strtolower($p_email) === strtolower($e_email)) {
            $confidence_score += 25;
            $reasons[] = "EXACT email match: '{$p_email}'";
        } else {
            // Compare email local parts
            $p_local = explode('@', $p_email)[0];
            $e_local = explode('@', $e_email)[0];
            $email_sim = calculate_similarity($p_local, $e_local);
            if ($email_sim['levenshtein'] >= 85) {
                $confidence_score += 10;
                $reasons[] = "Email local part similar ({$email_sim['levenshtein']}%): '{$p_local}' vs '{$e_local}'";
            }
        }
    }
    
    // 6. Phone number comparison
    if (!empty($p_phone) && !empty($e_phone)) {
        $p_phone_clean = preg_replace('/[^0-9]/', '', $p_phone);
        $e_phone_clean = preg_replace('/[^0-9]/', '', $e_phone);
        if (!empty($p_phone_clean) && !empty($e_phone_clean) && $p_phone_clean === $e_phone_clean) {
            $confidence_score += 20;
            $reasons[] = "EXACT phone match: '{$p_phone}'";
        }
    }
    
    // 7. Check for name inversions (first/last swapped)
    $inv_first = calculate_similarity($p_first, $e_last);
    $inv_last = calculate_similarity($p_last, $e_first);
    if ($inv_first['levenshtein'] >= 85 && $inv_last['levenshtein'] >= 85) {
        $confidence_score += 15;
        $reasons[] = "Possible name inversion: '{$p_first} {$p_last}' vs '{$e_first} {$e_last}'";
    }
    
    // 8. Check for nickname patterns (e.g., "Robert" vs "Bob")
    $nickname_matches = check_nickname_match($p_first, $e_first);
    if ($nickname_matches && $last_sim['levenshtein'] >= 85) {
        $confidence_score += 12;
        $reasons[] = "Possible nickname match: '{$p_first}' may be nickname of '{$e_first}' (or vice versa)";
    }
    
    return [
        'confidence' => min($confidence_score, 100),
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
 * Check for common nickname patterns
 */
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
 * ---------------------------------------------------------------------------------
 * 2. Live Output Functions
 * ---------------------------------------------------------------------------------
 */

function dup_output_message($message, $type = 'info') {
    $timestamp = date('H:i:s');
    $formatted = "[{$timestamp}] {$message}";
    error_log("DUP_DETECT: " . $message);
    
    $class = $type === 'error' ? 'notice-error' : ($type === 'success' ? 'notice-success' : ($type === 'warning' ? 'notice-warning' : 'notice-info'));
    echo '<div class="notice ' . $class . '"><p>' . esc_html($formatted) . '</p></div>';
    if (ob_get_level() > 0) { ob_flush(); }
    flush();
}

/**
 * ---------------------------------------------------------------------------------
 * 3. Main Detection Function
 * ---------------------------------------------------------------------------------
 */

function detect_duplicate_users($confidence_threshold = 40) {
    global $wpdb;
    
    dup_output_message("🔍 Starting duplicate user detection...", 'info');
    dup_output_message("Confidence threshold set to: {$confidence_threshold}%", 'info');
    
    // Fetch all Personnel Users
    dup_output_message("Fetching Personnel Users...", 'info');
    $personnel_users = get_users([
        'role' => 'personnel_user',
        'number' => -1,
    ]);
    $personnel_count = count($personnel_users);
    dup_output_message("Found {$personnel_count} Personnel Users", 'info');
    
    // Fetch all Expert Users
    dup_output_message("Fetching Expert Users...", 'info');
    $expert_users = get_users([
        'role' => 'expert_user',
        'number' => -1,
    ]);
    $expert_count = count($expert_users);
    dup_output_message("Found {$expert_count} Expert Users", 'info');
    
    if ($personnel_count === 0 || $expert_count === 0) {
        dup_output_message("⚠️ Not enough users to compare. Need at least 1 of each type.", 'warning');
        return [];
    }
    
    $total_comparisons = $personnel_count * $expert_count;
    dup_output_message("Will perform {$total_comparisons} comparisons...", 'info');
    
    $potential_duplicates = [];
    $comparison_count = 0;
    $progress_interval = max(1, floor($total_comparisons / 20));
    
    foreach ($personnel_users as $personnel) {
        $p_name = $personnel->display_name ?: $personnel->user_login;
        
        foreach ($expert_users as $expert) {
            $comparison_count++;
            
            if ($comparison_count % $progress_interval === 0) {
                $progress = round(($comparison_count / $total_comparisons) * 100);
                dup_output_message("Progress: {$progress}% ({$comparison_count}/{$total_comparisons} comparisons)", 'info');
            }
            
            $result = compare_users($personnel, $expert);
            
            if ($result['confidence'] >= $confidence_threshold) {
                $potential_duplicates[] = $result;
                $e_name = $expert->display_name ?: $expert->user_login;
                dup_output_message("🎯 Potential match found: '{$p_name}' (Personnel #{$personnel->ID}) <-> '{$e_name}' (Expert #{$expert->ID}) - Confidence: {$result['confidence']}%", 'warning');
            }
        }
    }
    
    // Sort by confidence (highest first)
    usort($potential_duplicates, function($a, $b) {
        return $b['confidence'] - $a['confidence'];
    });
    
    $dup_count = count($potential_duplicates);
    dup_output_message("✅ Detection complete! Found {$dup_count} potential duplicate pairs.", 'success');
    
    return $potential_duplicates;
}

/**
 * ---------------------------------------------------------------------------------
 * 4. Results Display Function
 * ---------------------------------------------------------------------------------
 */

function display_duplicate_results($duplicates) {
    if (empty($duplicates)) {
        echo '<div class="notice notice-success"><p><strong>No potential duplicates found!</strong></p></div>';
        return;
    }
    
    $count = count($duplicates);
    echo '<div class="duplicate-results" style="margin-top: 20px;">';
    echo '<h2>Potential Duplicates Found: ' . $count . '</h2>';
    echo '<p><em>Results sorted by confidence score (highest first). Review each match carefully before taking action.</em></p>';
    
    // Summary by confidence level
    $high = $medium = $low = 0;
    foreach ($duplicates as $dup) {
        if ($dup['confidence'] >= 70) $high++;
        elseif ($dup['confidence'] >= 50) $medium++;
        else $low++;
    }
    
    echo '<div style="background: #f9f9f9; padding: 15px; margin-bottom: 20px; border: 1px solid #ddd;">';
    echo '<strong>Summary:</strong> ';
    echo '<span style="color: #d63638;">High confidence (70%+): ' . $high . '</span> | ';
    echo '<span style="color: #dba617;">Medium confidence (50-69%): ' . $medium . '</span> | ';
    echo '<span style="color: #2271b1;">Lower confidence (<50%): ' . $low . '</span>';
    echo '</div>';
    
    echo '<table class="widefat striped" style="margin-top: 10px;">';
    echo '<thead>';
    echo '<tr>';
    echo '<th style="width: 60px;">Conf.</th>';
    echo '<th>Personnel User</th>';
    echo '<th>Expert User</th>';
    echo '<th>Match Reasons</th>';
    echo '<th style="width: 100px;">Actions</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($duplicates as $index => $dup) {
        $conf = $dup['confidence'];
        $conf_color = $conf >= 70 ? '#d63638' : ($conf >= 50 ? '#dba617' : '#2271b1');
        $conf_bg = $conf >= 70 ? '#fcf0f1' : ($conf >= 50 ? '#fcf9e8' : '#f0f6fc');
        
        $p = $dup['personnel'];
        $e = $dup['expert'];
        
        echo '<tr style="background-color: ' . $conf_bg . ';">';
        
        // Confidence
        echo '<td style="text-align: center; font-weight: bold; color: ' . $conf_color . ';">' . $conf . '%</td>';
        
        // Personnel User
        echo '<td>';
        echo '<strong>' . esc_html($p['name']) . '</strong><br>';
        echo '<small>ID: ' . $p['id'] . ' | Login: ' . esc_html($p['login']) . '</small><br>';
        echo '<small>Email: ' . esc_html($p['email']) . '</small><br>';
        if (!empty($p['phone'])) echo '<small>Phone: ' . esc_html($p['phone']) . '</small>';
        echo '</td>';
        
        // Expert User
        echo '<td>';
        echo '<strong>' . esc_html($e['name']) . '</strong><br>';
        echo '<small>ID: ' . $e['id'] . ' | Login: ' . esc_html($e['login']) . '</small><br>';
        echo '<small>Email: ' . esc_html($e['email']) . '</small><br>';
        if (!empty($e['phone'])) echo '<small>Phone: ' . esc_html($e['phone']) . '</small>';
        echo '</td>';
        
        // Reasons
        echo '<td><ul style="margin: 0; padding-left: 15px; font-size: 12px;">';
        foreach ($dup['reasons'] as $reason) {
            echo '<li>' . esc_html($reason) . '</li>';
        }
        echo '</ul></td>';
        
        // Actions
        $p_edit = admin_url('user-edit.php?user_id=' . $p['id']);
        $e_edit = admin_url('user-edit.php?user_id=' . $e['id']);
        echo '<td>';
        echo '<a href="' . esc_url($p_edit) . '" target="_blank" class="button button-small">View Personnel</a><br><br>';
        echo '<a href="' . esc_url($e_edit) . '" target="_blank" class="button button-small">View Expert</a>';
        echo '</td>';
        
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}

/**
 * ---------------------------------------------------------------------------------
 * 5. Admin Page Registration
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
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    $threshold = isset($_GET['threshold']) ? intval($_GET['threshold']) : 40;
    $threshold = max(10, min(90, $threshold));
    
    // Check if running detection
    if (isset($_GET['action']) && $_GET['action'] === 'run_detection') {
        ob_start();
        
        echo '<div class="wrap">';
        echo '<h1>Duplicate User Detection</h1>';
        echo '<p><strong>Running duplicate detection with confidence threshold: ' . $threshold . '%</strong></p>';
        
        echo '<style>
            .detection-output { background: #f0f0f0; border: 1px solid #ccc; max-height: 400px; overflow-y: auto; padding: 15px; margin: 20px 0; font-family: monospace; font-size: 12px; }
            .detection-output .notice { margin: 5px 0; padding: 8px 12px; }
            .detection-output .notice-error { border-left: 4px solid #d63638; background: #fcf2f2; }
            .detection-output .notice-success { border-left: 4px solid #00a32a; background: #f0f6fc; }
            .detection-output .notice-warning { border-left: 4px solid #dba617; background: #fcf9e8; }
            .detection-output .notice-info { border-left: 4px solid #72aee6; background: #f0f6fc; }
            .duplicate-results table { font-size: 13px; }
            .duplicate-results td, .duplicate-results th { vertical-align: top; padding: 10px; }
        </style>';
        
        echo '<div class="detection-output">';
        ob_flush(); flush();
        
        $duplicates = detect_duplicate_users($threshold);
        
        echo '</div>';
        
        display_duplicate_results($duplicates);
        
        $back_url = admin_url('admin.php?page=duplicate-user-detection');
        echo '<p style="margin-top: 20px;"><a href="' . esc_url($back_url) . '" class="button button-primary">← Back to Detection Settings</a></p>';
        echo '</div>';
        
        ob_end_flush();
        exit;
    }
    
    // Display the settings form
    echo '<div class="wrap">';
    echo '<h1>Duplicate User Detection</h1>';
    echo '<p>This tool compares all <strong>Personnel Users</strong> against all <strong>Expert Users</strong> to identify potential duplicates using fuzzy name matching and other heuristics.</p>';
    
    echo '<div class="card" style="max-width: 800px; padding: 20px; margin: 20px 0;">';
    echo '<h2>How It Works</h2>';
    echo '<ul>';
    echo '<li><strong>Name Matching:</strong> Compares first names, last names, and display names using Levenshtein distance and phonetic algorithms (Soundex, Metaphone)</li>';
    echo '<li><strong>Email Comparison:</strong> Checks for matching non-spoofed email addresses</li>';
    echo '<li><strong>Phone Matching:</strong> Compares phone numbers after removing formatting</li>';
    echo '<li><strong>Nickname Detection:</strong> Recognizes common nickname patterns (e.g., "Robert" vs "Bob")</li>';
    echo '<li><strong>Name Inversion:</strong> Detects if first/last names may have been swapped</li>';
    echo '</ul>';
    echo '</div>';
    
    echo '<form method="get" action="' . admin_url('admin.php') . '">';
    echo '<input type="hidden" name="page" value="duplicate-user-detection">';
    echo '<input type="hidden" name="action" value="run_detection">';
    
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th scope="row"><label for="threshold">Confidence Threshold</label></th>';
    echo '<td>';
    echo '<input type="range" id="threshold" name="threshold" min="10" max="90" value="' . $threshold . '" style="width: 300px;" oninput="document.getElementById(\'threshold_val\').textContent = this.value">';
    echo ' <span id="threshold_val" style="font-weight: bold;">' . $threshold . '</span>%';
    echo '<p class="description">Only matches with confidence at or above this threshold will be shown.<br>';
    echo '<strong>Recommended:</strong> Start with 40% to see more matches, then increase if too many false positives.</p>';
    echo '</td>';
    echo '</tr>';
    echo '</table>';
    
    echo '<p class="submit">';
    echo '<input type="submit" class="button button-primary button-large" value="Run Duplicate Detection">';
    echo '</p>';
    echo '</form>';
    
    echo '<div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px; background: #fff8e5; border-left: 4px solid #dba617;">';
    echo '<h3 style="margin-top: 0;">⚠️ Important Notes</h3>';
    echo '<ul>';
    echo '<li>This process may take several minutes depending on the number of users.</li>';
    echo '<li>Results require <strong>manual review</strong> - not all matches are true duplicates.</li>';
    echo '<li>High confidence (70%+) matches are more likely to be true duplicates.</li>';
    echo '<li>Lower confidence matches may be coincidental name similarities.</li>';
    echo '<li>This tool does not modify any data - it only identifies potential matches for review.</li>';
    echo '</ul>';
    echo '</div>';
    
    echo '</div>';
}