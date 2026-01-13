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
        'stats' => $stats,
        'log' => $log,
        'dry_run' => $dry_run
    ]);
}

/**
 * ---------------------------------------------------------------------------------
 * 6. Admin Page
 * ---------------------------------------------------------------------------------
 */

add_action('admin_menu', 'add_user_merge_page');
function add_user_merge_page() {
    add_submenu_page(
        'caes-tools',
        'User Merge & Cleanup',
        'User Merge & Cleanup',
        'manage_options',
        'user-merge-cleanup',
        'user_merge_page_content'
    );
}

function user_merge_page_content() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Insufficient permissions.'));
    }
    $nonce = wp_create_nonce('user_merge_nonce');
    ?>
    <div class="wrap">
        <h1>User Merge & Cleanup</h1>
        <p>This utility reassigns content from <strong>Expert Users</strong> to their corresponding <strong>Personnel Users</strong>, then optionally deletes the Expert User accounts.</p>

        <div class="card" style="max-width:900px; padding:20px; margin:20px 0;">
            <h2 style="margin-top:0;">Step 1: Upload CSV</h2>
            <p>Upload a CSV file containing user pairs. The CSV must have these columns:</p>
            <ul>
                <li><code>personnel_id</code> - The Personnel ID of the target Personnel User</li>
                <li><code>expert_source_id</code> and/or <code>expert_writer_id</code> - Identifiers for the Expert User to merge</li>
            </ul>
            <p><em>Tip: Export this CSV from the <a href="<?php echo admin_url('admin.php?page=duplicate-user-detection'); ?>">Duplicate User Detection</a> tool.</em></p>
            
            <div style="margin:15px 0;">
                <input type="file" id="csv-file" accept=".csv">
                <button id="parse-csv" class="button button-secondary" style="margin-left:10px;">Parse CSV</button>
            </div>
            
            <div id="csv-preview" style="display:none; margin-top:15px; padding:15px; background:#f9f9f9; border:1px solid #ddd;">
                <h3 style="margin-top:0;">CSV Preview</h3>
                <div id="csv-stats"></div>
                <div id="csv-errors" style="max-height:150px; overflow-y:auto;"></div>
            </div>
        </div>

        <div id="merge-settings" class="card" style="max-width:900px; padding:20px; margin:20px 0; display:none;">
            <h2 style="margin-top:0;">Step 2: Configure & Run</h2>
            
            <table class="form-table">
                <tr>
                    <th><label for="dry-run">Dry Run Mode</label></th>
                    <td>
                        <input type="checkbox" id="dry-run" checked>
                        <label for="dry-run">Preview changes without modifying the database</label>
                        <p class="description" style="color:#d63638;"><strong>Recommended:</strong> Always run in dry mode first to verify changes.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="delete-experts">Delete Expert Users</label></th>
                    <td>
                        <input type="checkbox" id="delete-experts" checked>
                        <label for="delete-experts">Delete Expert User accounts after reassigning their content</label>
                        <p class="description">Any WordPress posts authored by the Expert will be reassigned to the Personnel User.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="batch-size">Batch Size</label></th>
                    <td>
                        <select id="batch-size">
                            <option value="1">1 pair (slowest, most detailed)</option>
                            <option value="3">3 pairs</option>
                            <option value="5" selected>5 pairs (recommended)</option>
                            <option value="10">10 pairs (faster)</option>
                        </select>
                    </td>
                </tr>
            </table>

            <p>
                <button id="start-merge" class="button button-primary button-large">Start Merge Process</button>
                <button id="stop-merge" class="button button-secondary" style="display:none;">Stop</button>
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
            <div id="console-output" style="background:#1e1e1e; color:#d4d4d4; font-family:monospace; font-size:12px; height:400px; overflow-y:auto; padding:10px; border-radius:4px;"></div>
        </div>

        <div id="completion-section" style="display:none; max-width:900px; margin-top:20px;">
            <div class="notice notice-success" style="padding:15px;">
                <h3 style="margin-top:0;">✅ Process Complete</h3>
                <div id="final-stats"></div>
            </div>
        </div>
    </div>

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

        // Run merge batch
        function runBatch() {
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
                dry_run: $('#dry-run').is(':checked') ? 'true' : 'false',
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
                    runBatch();
                }
            }).fail(function(xhr) {
                log('AJAX error: ' + xhr.statusText, 'error');
                isRunning = false;
                $('#start-merge').prop('disabled', false);
                $('#stop-merge').hide();
            });
        }

        // Start merge
        $('#start-merge').on('click', function() {
            if (isRunning) return;

            const isDryRun = $('#dry-run').is(':checked');
            if (!isDryRun) {
                if (!confirm('⚠️ WARNING: You are about to make PERMANENT changes to the database.\n\nThis will:\n- Update user references in posts\n' + ($('#delete-experts').is(':checked') ? '- DELETE Expert User accounts\n' : '') + '\nAre you sure you want to continue?')) {
                    return;
                }
            }

            isRunning = true;
            shouldStop = false;
            totalStats = {posts: 0, deleted: 0, errors: 0};

            $(this).prop('disabled', true);
            $('#stop-merge').show();
            $('#progress-section, #console-section').show();
            $('#completion-section').hide();
            $('#console-output').empty();
            $('#stat-posts, #stat-deleted, #stat-errors').text('0');
            updateProgress(0, 'Starting...');

            const mode = isDryRun ? 'DRY RUN' : 'LIVE';
            log(`🚀 Starting merge process in ${mode} mode...`, 'info');

            runBatch();
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