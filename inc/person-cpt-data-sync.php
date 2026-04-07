<?php
/**
 * People CPT Data Sync
 *
 * Combined admin page for syncing personnel data from the CAES Personnel API
 * and Symplectic Elements data into caes_hub_person CPT posts.
 *
 * Personnel sync: creates/updates person posts from the personnel database.
 * Symplectic sync: imports scholarly works, distinctions, courses, overview,
 * and areas of expertise from Symplectic Elements API.
 *
 * @version 1.0.0
 */
define('PERSON_CPT_DATA_SYNC_VERSION', '1.0.0');

define('PERSONNEL_CPT_BATCH_SIZE', 50);
define('PERSONNEL_CPT_MAX_ERRORS', 200);
define('PERSONNEL_CPT_STATE_KEY', 'personnel_cpt_sync_state');
define('PERSONNEL_CPT_CRON_HOOK', 'personnel_cpt_sync_daily');
define('PERSONNEL_CPT_BATCH_HOOK', 'personnel_cpt_sync_batch');

// ============================================================
// Cron scheduling
// ============================================================

add_action('init', 'personnel_cpt_maybe_schedule');

function personnel_cpt_maybe_schedule() {
	if (!wp_next_scheduled(PERSONNEL_CPT_CRON_HOOK)) {
		wp_schedule_event(strtotime('tomorrow midnight') - 3600, 'daily', PERSONNEL_CPT_CRON_HOOK);
	}
}

add_action(PERSONNEL_CPT_CRON_HOOK, 'personnel_cpt_daily_trigger');
add_action(PERSONNEL_CPT_BATCH_HOOK, 'personnel_cpt_run_batch');

function personnel_cpt_daily_trigger() {
	personnel_cpt_start_job('cron');
}

// ============================================================
// State management
// ============================================================

function personnel_cpt_default_state() {
	return array(
		'status'          => 'idle',
		'triggered_by'    => null,
		'started_at'      => null,
		'completed_at'    => null,
		'total_records'   => 0,
		'processed'       => 0,
		'stats'           => array(
			'created'        => 0,
			'updated'        => 0,
			'marked_inactive' => 0,
			'unpublished'    => 0,
			'expert_unpublished' => 0,
			'reactivated'    => 0,
			'skipped'        => 0,
			'errors'         => 0,
			'fields_written' => 0,
		),
		'errors'          => array(),
		'stop_requested'  => false,
		'last_completed'  => null,
		'_api_data'       => null, // Transient: holds the full API response during a run
	);
}

function personnel_cpt_get_state() {
	$state = get_option(PERSONNEL_CPT_STATE_KEY, null);
	if (!is_array($state)) {
		return personnel_cpt_default_state();
	}
	return $state;
}

// ============================================================
// API helpers
// ============================================================

function personnel_cpt_fetch_api_data() {
	$url = 'https://secure.caes.uga.edu/rest/personnel/Personnel/?returnContactInfoColumns=true';
	$response = wp_remote_get($url, array('timeout' => 60));

	if (is_wp_error($response)) {
		return $response;
	}

	$data = json_decode(wp_remote_retrieve_body($response), true);
	if (!is_array($data)) {
		return new WP_Error('invalid_response', 'Invalid API response from personnel API.');
	}

	return $data;
}

function personnel_cpt_sanitize_record($record) {
	return array(
		'personnel_id'    => intval($record['PERSONNEL_ID'] ?? 0),
		'college_id'      => intval($record['COLLEGEID'] ?? 0),
		'email'           => sanitize_email($record['EMAIL'] ?? ''),
		'first_name'      => sanitize_text_field($record['FNAME'] ?? ''),
		'last_name'       => sanitize_text_field($record['LNAME'] ?? ''),
		'display_name'    => sanitize_text_field($record['NAME'] ?? ''),
		'title'           => sanitize_text_field($record['TITLE'] ?? ''),
		'department'      => sanitize_text_field($record['DEPARTMENT'] ?? ''),
		'program_area'    => sanitize_text_field($record['PROGRAMAREALIST'] ?? ''),
		'phone_number'    => sanitize_text_field($record['PHONE_NUMBER'] ?? ''),
		'cell_phone_number' => sanitize_text_field($record['CELL_PHONE_NUMBER'] ?? ''),
		'fax_number'      => sanitize_text_field($record['FAX_NUMBER'] ?? ''),
		'caes_location_id' => intval($record['CAES_LOCATION_ID'] ?? 0),
		'image_name'      => sanitize_text_field($record['IMAGE'] ?? ''),
		'mailing_address' => sanitize_text_field($record['MAILING_ADDRESS1'] ?? ''),
		'mailing_address2' => sanitize_text_field($record['MAILING_ADDRESS2'] ?? ''),
		'mailing_city'    => sanitize_text_field($record['MAILING_CITY'] ?? ''),
		'mailing_state'   => sanitize_text_field($record['MAILING_STATE'] ?? ''),
		'mailing_zip'     => sanitize_text_field($record['MAILING_ZIP'] ?? ''),
		'shipping_address' => sanitize_text_field($record['SHIPPING_ADDRESS1'] ?? ''),
		'shipping_address2' => sanitize_text_field($record['SHIPPING_ADDRESS2'] ?? ''),
		'shipping_city'   => sanitize_text_field($record['SHIPPING_CITY'] ?? ''),
		'shipping_state'  => sanitize_text_field($record['SHIPPING_STATE'] ?? ''),
		'shipping_zip'    => sanitize_text_field($record['SHIPPING_ZIP'] ?? ''),
	);
}

// ============================================================
// Build lookup of personnel_id => post_id
// ============================================================

function personnel_cpt_build_pid_map() {
	global $wpdb;
	$results = $wpdb->get_results(
		"SELECT post_id, meta_value FROM {$wpdb->postmeta}
		 WHERE meta_key = 'personnel_id' AND meta_value != ''
		 AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'caes_hub_person' AND post_status IN ('publish','draft','private'))",
		OBJECT
	);
	$map = array();
	foreach ($results as $row) {
		$pid = intval($row->meta_value);
		if ($pid > 0) {
			$map[$pid] = intval($row->post_id);
		}
	}
	return $map;
}

// ============================================================
// Core: sync a single personnel record to a CPT post
// ============================================================

function personnel_cpt_sync_single_record($data, $existing_post_id = null) {
	$result = array(
		'status'         => 'ok',
		'action'         => 'none',
		'post_id'        => null,
		'fields_written' => 0,
		'error_message'  => '',
	);

	if ($data['personnel_id'] <= 0) {
		$result['status'] = 'error';
		$result['error_message'] = 'Invalid personnel_id: ' . $data['personnel_id'];
		return $result;
	}

	if (empty($data['first_name']) || empty($data['last_name'])) {
		$result['status'] = 'error';
		$result['error_message'] = 'Missing first/last name for personnel_id ' . $data['personnel_id'];
		return $result;
	}

	$post_id = $existing_post_id;

	// Create new post if needed
	if (!$post_id) {
		$new_post = wp_insert_post(array(
			'post_type'   => 'caes_hub_person',
			'post_title'  => $data['display_name'] ?: ($data['first_name'] . ' ' . $data['last_name']),
			'post_status' => 'publish',
		), true);

		if (is_wp_error($new_post)) {
			$result['status'] = 'error';
			$result['error_message'] = 'Failed to create post: ' . $new_post->get_error_message();
			return $result;
		}

		$post_id = $new_post;
		$result['action'] = 'created';
	} else {
		// Update the post title
		wp_update_post(array(
			'ID'         => $post_id,
			'post_title' => $data['display_name'] ?: ($data['first_name'] . ' ' . $data['last_name']),
		));
		$result['action'] = 'updated';
	}

	$result['post_id'] = $post_id;

	// Simple meta fields to sync
	$meta_fields = array(
		'personnel_id', 'college_id', 'first_name', 'last_name', 'display_name',
		'title', 'phone_number', 'cell_phone_number', 'fax_number',
		'caes_location_id', 'image_name',
		'mailing_address', 'mailing_address2', 'mailing_city', 'mailing_state', 'mailing_zip',
		'shipping_address', 'shipping_address2', 'shipping_city', 'shipping_state', 'shipping_zip',
	);

	// Email goes to uga_email meta
	$email = $data['email'];
	if (!empty($email)) {
		update_post_meta($post_id, 'uga_email', $email);
		$result['fields_written']++;
	}

	foreach ($meta_fields as $field) {
		$value = $data[$field] ?? '';
		if ($value !== '' && $value !== 0 && $value !== false) {
			update_post_meta($post_id, $field, $value);
			$result['fields_written']++;
		}
	}

	// Mark as active
	update_post_meta($post_id, 'is_active', 1);
	$result['fields_written']++;

	// Taxonomy fields
	if (!empty($data['department'])) {
		wp_set_object_terms($post_id, $data['department'], 'person_department');
		$result['fields_written']++;
	}
	if (!empty($data['program_area'])) {
		wp_set_object_terms($post_id, $data['program_area'], 'person_program_area');
		$result['fields_written']++;
	}

	return $result;
}

// ============================================================
// Job control
// ============================================================

function personnel_cpt_start_job($triggered_by = 'manual') {
	$state = personnel_cpt_get_state();

	if ($state['status'] === 'running') {
		return false;
	}

	// Fetch all API data upfront
	$api_data = personnel_cpt_fetch_api_data();
	if (is_wp_error($api_data)) {
		$state = personnel_cpt_default_state();
		$state['status'] = 'error';
		$state['completed_at'] = time();
		$state['errors'][] = 'API fetch failed: ' . $api_data->get_error_message();
		update_option(PERSONNEL_CPT_STATE_KEY, $state, false);
		return false;
	}

	if (empty($api_data)) {
		$state = personnel_cpt_default_state();
		$state['status'] = 'error';
		$state['completed_at'] = time();
		$state['errors'][] = 'API returned 0 records.';
		update_option(PERSONNEL_CPT_STATE_KEY, $state, false);
		return false;
	}

	$last_completed = $state['last_completed'];
	$new_state = personnel_cpt_default_state();
	$new_state['status']         = 'running';
	$new_state['triggered_by']   = $triggered_by;
	$new_state['started_at']     = time();
	$new_state['total_records']  = count($api_data);
	$new_state['last_completed'] = $last_completed;

	// Store API data in a transient (too large for the state option alongside everything else)
	set_transient('personnel_cpt_api_data', $api_data, 3600);

	update_option(PERSONNEL_CPT_STATE_KEY, $new_state, false);
	wp_schedule_single_event(time(), PERSONNEL_CPT_BATCH_HOOK);
	return true;
}

function personnel_cpt_run_batch() {
	$state = personnel_cpt_get_state();

	if ($state['status'] !== 'running') {
		return;
	}

	$api_data = get_transient('personnel_cpt_api_data');
	if (!is_array($api_data) || empty($api_data)) {
		$state['status'] = 'error';
		$state['completed_at'] = time();
		$state['errors'][] = 'API data transient expired or missing during batch run.';
		update_option(PERSONNEL_CPT_STATE_KEY, $state, false);
		return;
	}

	// Build the personnel_id => post_id map once per batch
	$pid_map = personnel_cpt_build_pid_map();

	$offset = $state['processed'];
	$batch = array_slice($api_data, $offset, PERSONNEL_CPT_BATCH_SIZE);

	foreach ($batch as $record) {
		// Check for stop request
		$check = personnel_cpt_get_state();
		if ($check['status'] !== 'running') {
			return;
		}

		$state['processed']++;

		// Validate required fields
		$personnel_id = intval($record['PERSONNEL_ID'] ?? 0);
		if ($personnel_id <= 0 || empty(trim($record['NAME'] ?? '')) || empty(trim($record['FNAME'] ?? '')) || empty(trim($record['LNAME'] ?? ''))) {
			$state['stats']['errors']++;
			if (count($state['errors']) < PERSONNEL_CPT_MAX_ERRORS) {
				$state['errors'][] = 'Record #' . $state['processed'] . ': missing required fields (PERSONNEL_ID, NAME, FNAME, or LNAME)';
			}
			update_option(PERSONNEL_CPT_STATE_KEY, $state, false);
			continue;
		}

		$data = personnel_cpt_sanitize_record($record);
		$existing_post = isset($pid_map[$personnel_id]) ? $pid_map[$personnel_id] : null;

		$sync_result = personnel_cpt_sync_single_record($data, $existing_post);

		if ($sync_result['status'] === 'ok') {
			if ($sync_result['action'] === 'created') {
				$state['stats']['created']++;
				// Update the map for this run so we don't create duplicates
				$pid_map[$personnel_id] = $sync_result['post_id'];
			} else {
				$state['stats']['updated']++;
			}
			$state['stats']['fields_written'] += $sync_result['fields_written'];
		} else {
			$state['stats']['errors']++;
			if (count($state['errors']) < PERSONNEL_CPT_MAX_ERRORS) {
				$state['errors'][] = 'PID ' . $personnel_id . ': ' . $sync_result['error_message'];
			}
		}

		update_option(PERSONNEL_CPT_STATE_KEY, $state, false);
	}

	if ($state['processed'] < $state['total_records'] && !empty($batch)) {
		wp_schedule_single_event(time() + 2, PERSONNEL_CPT_BATCH_HOOK);
	} else {
		// Mark any existing person posts whose personnel_id was NOT in the API as inactive
		$api_pids = array();
		foreach ($api_data as $r) {
			$p = intval($r['PERSONNEL_ID'] ?? 0);
			if ($p > 0) $api_pids[$p] = true;
		}

		// One bulk query: all person post IDs credited on published/private content
		global $wpdb;
		$credited_ids = $wpdb->get_col(
			"SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key REGEXP '^(authors|experts|translator|artists)_[0-9]+_user$'
			 AND p.post_type IN ('post','publications','shorthand_story')
			 AND p.post_status IN ('publish','private')"
		);
		$credited_set = array_flip($credited_ids);

		$inactive_count = 0;
		$unpublished_count = 0;
		$reactivated_count = 0;

		foreach ($pid_map as $pid => $post_id) {
			if (!isset($api_pids[$pid])) {
				// Not in active API -- mark inactive
				$current = get_post_meta($post_id, 'is_active', true);
				if ($current !== '0' && $current !== 0) {
					update_post_meta($post_id, 'is_active', 0);
					$inactive_count++;
				}

				// If not credited on any published/private content, unpublish
				if (!isset($credited_set[(string) $post_id])) {
					if (get_post_status($post_id) === 'publish') {
						wp_update_post(array('ID' => $post_id, 'post_status' => 'draft'));
						$unpublished_count++;
					}
				}
			} else {
				// In active API -- reactivate if previously inactive
				$current = get_post_meta($post_id, 'is_active', true);
				if ($current === '0') {
					update_post_meta($post_id, 'is_active', 1);
					if (get_post_status($post_id) === 'draft') {
						wp_update_post(array('ID' => $post_id, 'post_status' => 'publish'));
					}
					$reactivated_count++;
				}
			}
		}
		// Unpublish expert/writer posts (no personnel_id) that aren't credited anywhere
		$expert_unpublished = 0;
		$expert_posts = $wpdb->get_col(
			"SELECT p.ID FROM {$wpdb->posts} p
			 WHERE p.post_type = 'caes_hub_person' AND p.post_status = 'publish'
			 AND p.ID NOT IN (
			 	SELECT post_id FROM {$wpdb->postmeta}
			 	WHERE meta_key = 'personnel_id' AND meta_value != '' AND meta_value != '0'
			 )"
		);
		foreach ($expert_posts as $ep_id) {
			if (!isset($credited_set[(string) $ep_id])) {
				wp_update_post(array('ID' => intval($ep_id), 'post_status' => 'draft'));
				$expert_unpublished++;
			}
		}

		$state['stats']['marked_inactive'] = $inactive_count;
		$state['stats']['unpublished'] = $unpublished_count;
		$state['stats']['expert_unpublished'] = $expert_unpublished;
		$state['stats']['reactivated'] = $reactivated_count;

		$state['status'] = 'complete';
		$state['completed_at'] = time();
		$state['last_completed'] = array(
			'started_at'     => $state['started_at'],
			'completed_at'   => $state['completed_at'],
			'triggered_by'   => $state['triggered_by'],
			'total_records'  => $state['total_records'],
			'processed'      => $state['processed'],
			'stats'          => $state['stats'],
			'errors'         => $state['errors'],
		);
		$state['_api_data'] = null;
		update_option(PERSONNEL_CPT_STATE_KEY, $state, false);
		delete_transient('personnel_cpt_api_data');

		// If this was a cron run, automatically start the Symplectic sync
		if ($state['triggered_by'] === 'cron') {
			symplectic_cpt_start_job('cron');
		}
	}
}

// ============================================================
// Single-person sync (by college_id, used from admin UI)
// ============================================================

function personnel_cpt_sync_single_by_college_id($college_id, $dry_run = false) {
	$college_id = intval($college_id);
	if ($college_id <= 0) {
		return new WP_Error('invalid_id', 'Invalid College ID.');
	}

	// Try active first
	$url = 'https://secure.caes.uga.edu/rest/personnel/Personnel?collegeID=' . $college_id . '&returnContactInfoColumns=true';
	$response = wp_remote_get($url, array('timeout' => 30));

	if (is_wp_error($response)) {
		return $response;
	}

	$records = json_decode(wp_remote_retrieve_body($response), true);
	$is_active = true;

	// Try inactive if not found
	if (!is_array($records) || empty($records)) {
		$is_active = false;
		$url = 'https://secure.caes.uga.edu/rest/personnel/Personnel?collegeID=' . $college_id . '&returnContactInfoColumns=true&isActive=false';
		$response = wp_remote_get($url, array('timeout' => 30));
		if (is_wp_error($response)) {
			return $response;
		}
		$records = json_decode(wp_remote_retrieve_body($response), true);
	}

	if (!is_array($records) || empty($records)) {
		return new WP_Error('not_found', 'No personnel record found for College ID ' . $college_id);
	}

	$data = personnel_cpt_sanitize_record($records[0]);

	// Find existing post by personnel_id
	$existing_post = null;
	if ($data['personnel_id'] > 0) {
		$posts = get_posts(array(
			'post_type'      => 'caes_hub_person',
			'post_status'    => array('publish', 'draft', 'private'),
			'meta_key'       => 'personnel_id',
			'meta_value'     => $data['personnel_id'],
			'posts_per_page' => 1,
			'fields'         => 'ids',
		));
		if (!empty($posts)) {
			$existing_post = $posts[0];
		}
	}

	if ($dry_run) {
		$data['is_active'] = $is_active;
		$action = $existing_post ? 'would_update' : 'would_create';
		if (!$is_active && $existing_post) {
			$has_content = _person_get_content_count($existing_post, 'post')
				+ _person_get_content_count($existing_post, 'publications')
				+ _person_get_content_count($existing_post, 'shorthand_story');
			$action = $has_content > 0 ? 'would_deactivate' : 'would_unpublish';
		}
		return array(
			'status'        => 'ok',
			'action'        => $action,
			'post_id'       => $existing_post,
			'fields_written' => 0,
			'error_message' => '',
			'preview_data'  => $data,
		);
	}

	$result = personnel_cpt_sync_single_record($data, $existing_post);

	// Handle inactive personnel
	if (!$is_active && $result['status'] === 'ok' && $result['post_id']) {
		$post_id = $result['post_id'];
		update_post_meta($post_id, 'is_active', 0);

		$has_content = _person_get_content_count($post_id, 'post')
			+ _person_get_content_count($post_id, 'publications')
			+ _person_get_content_count($post_id, 'shorthand_story');

		if ($has_content > 0) {
			$result['action'] = 'deactivated';
		} else {
			if (get_post_status($post_id) === 'publish') {
				wp_update_post(array('ID' => $post_id, 'post_status' => 'draft'));
			}
			$result['action'] = 'unpublished';
		}
	}

	return $result;
}

// ============================================================
// Admin menu -- combined page
// ============================================================

add_action('admin_menu', 'person_data_sync_add_page');

function person_data_sync_add_page() {
	add_submenu_page(
		'caes-tools',
		'People CPT Data Sync',
		'People CPT Data Sync',
		'manage_options',
		'person-data-sync',
		'person_data_sync_render_page'
	);
}

// ============================================================
// Scripts and styles
// ============================================================

add_action('admin_enqueue_scripts', 'person_data_sync_enqueue');

function person_data_sync_enqueue($hook) {
	if ($hook !== 'caes-tools_page_person-data-sync') {
		return;
	}

	wp_enqueue_style('wp-admin');
	wp_enqueue_script('jquery');

	wp_add_inline_style('wp-admin', '
		.pds-wrapper { max-width: 1100px; margin: 20px 0; }
		.pds-sections { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
		@media (max-width: 1200px) { .pds-sections { grid-template-columns: 1fr; } }
		.pds-section { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 16px; }
		.pds-section h2 { margin-top: 0; padding-bottom: 8px; border-bottom: 1px solid #e5e5e5; font-size: 14px; text-transform: uppercase; letter-spacing: 0.03em; color: #555; }
		.pds-panel { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 16px; margin-bottom: 20px; }
		.pds-panel h2 { margin-top: 0; padding-bottom: 8px; border-bottom: 1px solid #e5e5e5; font-size: 14px; text-transform: uppercase; letter-spacing: 0.03em; color: #555; }
		.pds-stat-grid { display: flex; gap: 24px; flex-wrap: wrap; margin: 12px 0; }
		.pds-stat { text-align: center; min-width: 60px; }
		.pds-stat-value { font-size: 28px; font-weight: 700; line-height: 1; }
		.pds-stat-label { font-size: 11px; color: #666; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.05em; }
		.pds-stat-value.ok      { color: #46b450; }
		.pds-stat-value.failed  { color: #dc3232; }
		.pds-stat-value.neutral { color: #0073aa; }
		.pds-stat-value.warn    { color: #9a5e00; }
		.pds-progress-wrap { margin: 12px 0; }
		.pds-progress-bar { height: 12px; background: #e0e0e0; border-radius: 6px; overflow: hidden; }
		.pds-progress-fill { height: 100%; background: #0073aa; border-radius: 6px; transition: width 0.4s ease; }
		.pds-progress-label { font-size: 12px; color: #555; margin-top: 4px; }
		.pds-error-list { max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 11px; margin-top: 4px; }
		.pds-error-list div { padding: 3px 0; border-bottom: 1px solid #f5f5f5; color: #dc3232; }
		.pds-meta { font-size: 12px; color: #666; margin-top: 6px; }
		.pds-status-badge { display: inline-block; padding: 3px 10px; border-radius: 3px; font-size: 12px; font-weight: 600; }
		.pds-status-badge.idle     { background: #f0f0f0; color: #555; }
		.pds-status-badge.running  { background: #e5f0fa; color: #0073aa; }
		.pds-status-badge.complete { background: #ecf7ed; color: #46b450; }
		.pds-status-badge.error    { background: #fbeaea; color: #dc3232; }
		.pds-status-badge.stopped  { background: #fff8e5; color: #9a5e00; }
		@keyframes pds-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
		.dashicons.pds-spin { animation: pds-spin 1s linear infinite; display: inline-block; }
		.pds-form-group { margin-bottom: 16px; }
		.pds-form-group label { display: block; font-weight: 600; margin-bottom: 4px; }
	');

	$nonce_personnel = wp_create_nonce('personnel_cpt_sync_nonce');
	$nonce_symplectic = wp_create_nonce('symplectic_cpt_nonce');

	wp_add_inline_script('jquery', '
		jQuery(function($) {
			var nonceP = ' . json_encode($nonce_personnel) . ';
			var nonceS = ' . json_encode($nonce_symplectic) . ';
			var pollTimerP = null;
			var pollTimerS = null;

			function esc(s) {
				if (s === null || s === undefined) return "";
				return String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;");
			}
			function fmtTs(ts) {
				if (!ts) return "---";
				return new Date(ts * 1000).toLocaleString();
			}
			function fmtDuration(start, end) {
				if (!start || !end) return "";
				var secs = Math.round(end - start);
				if (secs < 60) return secs + "s";
				return Math.floor(secs / 60) + "m " + (secs % 60) + "s";
			}
			function renderErrors(errors) {
				if (!errors || !errors.length) return "";
				var html = "<div style=\"margin-top:10px\"><strong style=\"font-size:12px\">Errors (" + esc(errors.length) + "):</strong><div class=\"pds-error-list\">";
				errors.forEach(function(e) { html += "<div>" + esc(e) + "</div>"; });
				return html + "</div></div>";
			}

			// ---- PERSONNEL SYNC ----

			function renderPStats(s) {
				return "<div class=\"pds-stat-grid\">"
					+ "<div class=\"pds-stat\"><div class=\"pds-stat-value ok\">"      + esc(s.created)   + "</div><div class=\"pds-stat-label\">Created</div></div>"
					+ "<div class=\"pds-stat\"><div class=\"pds-stat-value ok\">"      + esc(s.updated)   + "</div><div class=\"pds-stat-label\">Updated</div></div>"
					+ "<div class=\"pds-stat\"><div class=\"pds-stat-value warn\">"    + esc(s.marked_inactive) + "</div><div class=\"pds-stat-label\">Marked Inactive</div></div>"
					+ "<div class=\"pds-stat\"><div class=\"pds-stat-value warn\">"    + esc(s.unpublished || 0) + "</div><div class=\"pds-stat-label\">Unpublished</div></div>"
					+ "<div class=\"pds-stat\"><div class=\"pds-stat-value warn\">"    + esc(s.expert_unpublished || 0) + "</div><div class=\"pds-stat-label\">Experts Unpublished</div></div>"
					+ "<div class=\"pds-stat\"><div class=\"pds-stat-value ok\">"      + esc(s.reactivated || 0) + "</div><div class=\"pds-stat-label\">Reactivated</div></div>"
					+ "<div class=\"pds-stat\"><div class=\"pds-stat-value neutral\">" + esc(s.fields_written) + "</div><div class=\"pds-stat-label\">Fields Written</div></div>"
					+ "<div class=\"pds-stat\"><div class=\"pds-stat-value" + (s.errors > 0 ? " failed" : "") + "\">" + esc(s.errors) + "</div><div class=\"pds-stat-label\">Errors</div></div>"
					+ "</div>";
			}

			function renderPPanel(state, target, btnPrefix) {
				var sc = state.status;
				var badge = sc.charAt(0).toUpperCase() + sc.slice(1);
				if (sc === "running") badge = "<span class=\"dashicons dashicons-update pds-spin\" style=\"font-size:14px;vertical-align:middle\"></span> Running";
				var html = "<div style=\"margin-bottom:12px\">";
				html += "<span class=\"pds-status-badge " + esc(sc) + "\">" + badge + "</span>";
				if (state.triggered_by) html += "&ensp;<span class=\"pds-meta\" style=\"display:inline\">Triggered by: " + esc(state.triggered_by) + "</span>";
				html += "</div>";
				if (sc === "running" || sc === "complete" || sc === "error" || sc === "stopped") {
					var total = state.total_records || state.total_posts || 0;
					var processed = state.processed || state.processed_posts || 0;
					var pct = total > 0 ? Math.round(processed / total * 100) : 0;
					html += "<div class=\"pds-progress-wrap\">";
					html += "<div class=\"pds-progress-bar\"><div class=\"pds-progress-fill\" style=\"width:" + pct + "%\"></div></div>";
					html += "<div class=\"pds-progress-label\">" + esc(processed) + " / " + esc(total) + " (" + pct + "%)</div>";
					html += "</div>";
					html += renderPStats(state.stats);
					html += "<div class=\"pds-meta\">Started: " + fmtTs(state.started_at);
					if (state.completed_at) {
						html += " --- Completed: " + fmtTs(state.completed_at);
						html += " --- Duration: " + fmtDuration(state.started_at, state.completed_at);
					}
					html += "</div>";
					html += renderErrors(state.errors);
				} else {
					html += "<p style=\"color:#666;font-size:13px\">No sync is currently running.</p>";
				}
				$(target).html(html);
			}

			function renderPLastCompleted(lc, target) {
				var $section = $(target).closest(".pds-section").find(".pds-last-completed-section");
				if (!lc) { $section.hide(); return; }
				$section.show();
				var html = "<div class=\"pds-meta\">Started: " + fmtTs(lc.started_at);
				html += " --- Completed: " + fmtTs(lc.completed_at);
				if (lc.started_at && lc.completed_at) html += " --- Duration: " + fmtDuration(lc.started_at, lc.completed_at);
				if (lc.triggered_by) html += " --- Triggered by: " + esc(lc.triggered_by);
				html += "</div>";
				html += renderPStats(lc.stats);
				html += renderErrors(lc.errors);
				$section.find(".pds-last-completed-panel").html(html);
			}

			function pollPersonnel() {
				$.ajax({
					url: ajaxurl, method: "POST",
					data: { action: "personnel_cpt_status", nonce: nonceP },
					success: function(r) {
						if (!r.success) return;
						var state = r.data.state;
						renderPPanel(state, "#pds-personnel-current");
						renderPLastCompleted(state.last_completed, "#pds-personnel-current");
						if (state.status === "running") {
							$("#pds-p-trigger").prop("disabled", true).hide();
							$("#pds-p-stop").show().prop("disabled", false);
							pollTimerP = setTimeout(pollPersonnel, 3000);
						} else {
							clearTimeout(pollTimerP);
							$("#pds-p-trigger").prop("disabled", false).show().val("Sync All Personnel");
							$("#pds-p-stop").hide();
						}
					}
				});
			}

			$("#pds-p-trigger").on("click", function() {
				if (!confirm("Fetch all records from the personnel API and sync to People CPT posts. Continue?")) return;
				$(this).prop("disabled", true).val("Starting...");
				$.ajax({
					url: ajaxurl, method: "POST",
					data: { action: "personnel_cpt_trigger", nonce: nonceP },
					success: function(r) {
						if (r.success) { setTimeout(pollPersonnel, 1000); }
						else { alert("Failed: " + (r.data && r.data.error_message || "Unknown")); $("#pds-p-trigger").prop("disabled", false).val("Sync All Personnel"); }
					},
					error: function() { alert("AJAX error."); $("#pds-p-trigger").prop("disabled", false).val("Sync All Personnel"); }
				});
			});

			$("#pds-p-stop").on("click", function() {
				if (!confirm("Stop the personnel sync?")) return;
				$(this).prop("disabled", true).val("Stopping...");
				$.ajax({
					url: ajaxurl, method: "POST",
					data: { action: "personnel_cpt_stop", nonce: nonceP },
					success: function() { pollPersonnel(); },
					error: function() { alert("AJAX error."); }
				});
			});

			// Single person sync
			function renderPersonnelPreview(data) {
				var fields = [
					["Name", data.display_name], ["Personnel ID", data.personnel_id], ["College ID", data.college_id],
					["Active Status", data.is_active ? "Active" : "Inactive"],
					["Email", data.email], ["Title", data.title], ["Department", data.department],
					["Program Area", data.program_area], ["Phone", data.phone_number]
				];
				var html = "<table style=\"font-size:12px;border-collapse:collapse;width:100%\">";
				fields.forEach(function(f) {
					if (f[1]) html += "<tr><td style=\"padding:3px 8px 3px 0;color:#666;white-space:nowrap;vertical-align:top\">" + esc(f[0]) + "</td><td style=\"padding:3px 0\">" + esc(f[1]) + "</td></tr>";
				});
				html += "</table>";
				return html;
			}

			$("#pds-single-form").on("submit", function(e) {
				e.preventDefault();
				var cid = $("#pds-single-cid").val().trim();
				if (!cid) { alert("Please enter a College ID."); return; }
				var isDryRun = $("#pds-single-dryrun").is(":checked");
				var $btn = $(this).find("input[type=submit]");
				$btn.prop("disabled", true).val(isDryRun ? "Fetching..." : "Syncing...");
				$("#pds-single-result").html("<p><span class=\"dashicons dashicons-update pds-spin\"></span> " + (isDryRun ? "Fetching preview..." : "Syncing...") + "</p>");
				$.ajax({
					url: ajaxurl, method: "POST", timeout: 60000,
					data: { action: "personnel_cpt_sync_single", nonce: nonceP, college_id: cid, dry_run: isDryRun ? 1 : 0 },
					success: function(r) {
						$btn.prop("disabled", false).val("Sync Single Person");
						if (r.success) {
							var d = r.data;
							var sc = d.status === "ok" ? "#46b450" : "#dc3232";
							var html = "<div style=\"padding:12px;background:#fff;border:1px solid #ccd0d4;border-radius:4px\">";
							if (d.dry_run) {
								html += "<strong style=\"color:#0073aa\">DRY RUN</strong>&ensp;";
								html += "<span style=\"color:#666;font-size:12px\">" + esc(d.action) + (d.post_id ? " --- Post #" + esc(d.post_id) + " --- " + esc(d.post_title) : " --- new post") + "</span>";
								if (d.preview_data) {
									html += "<div style=\"margin-top:10px\">" + renderPersonnelPreview(d.preview_data) + "</div>";
								}
							} else {
								html += "<strong style=\"color:" + sc + "\">" + esc(d.status.toUpperCase()) + "</strong>&ensp;";
								html += esc(d.action) + " --- Post #" + esc(d.post_id) + " --- " + esc(d.post_title);
								html += "<span style=\"color:#666;font-size:12px\"> --- " + esc(d.fields_written) + " fields written</span>";
							}
							if (d.error_message) html += "<div style=\"color:#dc3232;margin-top:6px;font-size:12px\">" + esc(d.error_message) + "</div>";
							html += "</div>";
							$("#pds-single-result").html(html);
						} else {
							$("#pds-single-result").html("<div class=\"notice notice-error\"><p>" + esc(r.data && r.data.error_message || "Unknown error") + "</p></div>");
						}
					},
					error: function(x, s, e) {
						$btn.prop("disabled", false).val("Sync Single Person");
						$("#pds-single-result").html("<div class=\"notice notice-error\"><p>AJAX error: " + esc(e) + "</p></div>");
					}
				});
			});

			// ---- SYMPLECTIC SYNC ----

			function renderSStats(s) {
				return "<div class=\"pds-stat-grid\">"
					+ "<div class=\"pds-stat\"><div class=\"pds-stat-value ok\">"      + esc(s.posts_ok)       + "</div><div class=\"pds-stat-label\">Posts OK</div></div>"
					+ "<div class=\"pds-stat\"><div class=\"pds-stat-value failed\">"  + esc(s.posts_failed)   + "</div><div class=\"pds-stat-label\">Failed</div></div>"
					+ "<div class=\"pds-stat\"><div class=\"pds-stat-value neutral\">" + esc(s.posts_skipped)  + "</div><div class=\"pds-stat-label\">Skipped</div></div>"
					+ "<div class=\"pds-stat\"><div class=\"pds-stat-value neutral\">" + esc(s.fields_written) + "</div><div class=\"pds-stat-label\">Fields Written</div></div>"
					+ "<div class=\"pds-stat\"><div class=\"pds-stat-value" + (s.fetch_errors > 0 ? " failed" : "") + "\">" + esc(s.fetch_errors) + "</div><div class=\"pds-stat-label\">Fetch Errors</div></div>"
					+ "</div>";
			}

			function renderSPanel(state) {
				var sc = state.status;
				var badge = sc.charAt(0).toUpperCase() + sc.slice(1);
				if (sc === "running") badge = "<span class=\"dashicons dashicons-update pds-spin\" style=\"font-size:14px;vertical-align:middle\"></span> Running";
				var html = "<div style=\"margin-bottom:12px\">";
				html += "<span class=\"pds-status-badge " + esc(sc) + "\">" + badge + "</span>";
				if (state.triggered_by) html += "&ensp;<span class=\"pds-meta\" style=\"display:inline\">Triggered by: " + esc(state.triggered_by) + "</span>";
				html += "</div>";
				if (sc === "running" || sc === "complete" || sc === "error" || sc === "stopped") {
					var pct = state.total_posts > 0 ? Math.round(state.processed_posts / state.total_posts * 100) : 0;
					html += "<div class=\"pds-progress-wrap\">";
					html += "<div class=\"pds-progress-bar\"><div class=\"pds-progress-fill\" style=\"width:" + pct + "%\"></div></div>";
					html += "<div class=\"pds-progress-label\">" + esc(state.processed_posts) + " / " + esc(state.total_posts) + " posts (" + pct + "%)</div>";
					html += "</div>";
					html += renderSStats(state.stats);
					html += "<div class=\"pds-meta\">Started: " + fmtTs(state.started_at);
					if (state.completed_at) {
						html += " --- Completed: " + fmtTs(state.completed_at);
						html += " --- Duration: " + fmtDuration(state.started_at, state.completed_at);
					}
					html += "</div>";
					html += renderErrors(state.errors);
				} else {
					html += "<p style=\"color:#666;font-size:13px\">No import is currently running.</p>";
				}
				$("#pds-symplectic-current").html(html);
			}

			function renderSLastCompleted(lc) {
				var $section = $("#pds-symplectic-last-completed");
				if (!lc) { $section.hide(); return; }
				$section.show();
				var html = "<div class=\"pds-meta\">Started: " + fmtTs(lc.started_at);
				html += " --- Completed: " + fmtTs(lc.completed_at);
				if (lc.started_at && lc.completed_at) html += " --- Duration: " + fmtDuration(lc.started_at, lc.completed_at);
				if (lc.triggered_by) html += " --- Triggered by: " + esc(lc.triggered_by);
				html += "</div>";
				html += renderSStats(lc.stats);
				html += renderErrors(lc.errors);
				$section.find(".pds-last-completed-panel").html(html);
			}

			function pollSymplectic() {
				$.ajax({
					url: ajaxurl, method: "POST",
					data: { action: "symplectic_cpt_status", nonce: nonceS },
					success: function(r) {
						if (!r.success) return;
						var state = r.data.state;
						renderSPanel(state);
						renderSLastCompleted(state.last_completed);
						if (state.status === "running") {
							$("#pds-s-trigger").prop("disabled", true).hide();
							$("#pds-s-stop").show().prop("disabled", false);
							$("#pds-s-resume").hide();
							pollTimerS = setTimeout(pollSymplectic, 3000);
						} else {
							clearTimeout(pollTimerS);
							$("#pds-s-trigger").prop("disabled", false).show().val("Sync All Elements Data");
							$("#pds-s-stop").hide();
							var canResume = state.status === "stopped" && state.processed_posts > 0 && state.processed_posts < state.total_posts;
							if (canResume) { $("#pds-s-resume").show().prop("disabled", false); } else { $("#pds-s-resume").hide(); }
						}
					}
				});
			}

			$("#pds-s-trigger").on("click", function() {
				if (!confirm("Fetch Symplectic Elements data for all person posts. Continue?")) return;
				$(this).prop("disabled", true).val("Starting...");
				$.ajax({
					url: ajaxurl, method: "POST",
					data: { action: "symplectic_cpt_trigger", nonce: nonceS },
					success: function(r) {
						if (r.success) { setTimeout(pollSymplectic, 1000); }
						else { alert("Failed: " + (r.data && r.data.error_message || "Unknown")); $("#pds-s-trigger").prop("disabled", false).val("Sync All Elements Data"); }
					},
					error: function() { alert("AJAX error."); $("#pds-s-trigger").prop("disabled", false).val("Sync All Elements Data"); }
				});
			});

			$("#pds-s-stop").on("click", function() {
				if (!confirm("Stop the Elements import?")) return;
				$(this).prop("disabled", true).val("Stopping...");
				$.ajax({
					url: ajaxurl, method: "POST",
					data: { action: "symplectic_cpt_stop", nonce: nonceS },
					success: function() { pollSymplectic(); }
				});
			});

			$("#pds-s-resume").on("click", function() {
				$(this).prop("disabled", true).val("Resuming...");
				$.ajax({
					url: ajaxurl, method: "POST",
					data: { action: "symplectic_cpt_resume", nonce: nonceS },
					success: function(r) {
						if (r.success) { setTimeout(pollSymplectic, 1000); }
						else { alert("Failed: " + (r.data && r.data.error_message || "Unknown")); $("#pds-s-resume").prop("disabled", false); }
					}
				});
			});

			// Single Elements import
			function renderElementsPreview(data) {
				var html = "<table style=\"font-size:12px;border-collapse:collapse;width:100%\">";
				html += "<tr><td style=\"padding:3px 8px 3px 0;color:#666\">UGA ID</td><td style=\"padding:3px 0\">" + esc(data.uga_id) + "</td></tr>";
				html += "<tr><td style=\"padding:3px 8px 3px 0;color:#666\">Elements User ID</td><td style=\"padding:3px 0\">" + esc(data.elements_user_id) + "</td></tr>";
				if (data.overview) html += "<tr><td style=\"padding:3px 8px 3px 0;color:#666;vertical-align:top\">Overview</td><td style=\"padding:3px 0;font-size:11px\">" + esc(data.overview) + (data.overview.length >= 300 ? "..." : "") + "</td></tr>";
				if (data.keywords && data.keywords.length) html += "<tr><td style=\"padding:3px 8px 3px 0;color:#666;vertical-align:top\">Keywords</td><td style=\"padding:3px 0\">" + data.keywords.map(esc).join(", ") + "</td></tr>";
				html += "<tr><td style=\"padding:3px 8px 3px 0;color:#666\">Publications</td><td style=\"padding:3px 0\">" + esc(data.publications_count) + " found</td></tr>";
				if (data.publications && data.publications.length) {
					data.publications.forEach(function(p) {
						html += "<tr><td></td><td style=\"padding:2px 0;font-size:11px;color:#333\">" + esc(p.title);
						if (p.journal) html += " <span style=\"color:#666\">(" + esc(p.journal) + ")</span>";
						if (p.year) html += " <span style=\"color:#999\">" + esc(p.year) + "</span>";
						html += "</td></tr>";
					});
				}
				html += "<tr><td style=\"padding:3px 8px 3px 0;color:#666\">Distinctions</td><td style=\"padding:3px 0\">" + esc(data.distinctions_count) + " found</td></tr>";
				html += "<tr><td style=\"padding:3px 8px 3px 0;color:#666\">Courses</td><td style=\"padding:3px 0\">" + esc(data.courses_count) + " found</td></tr>";
				html += "</table>";
				return html;
			}

			$("#pds-single-elements-form").on("submit", function(e) {
				e.preventDefault();
				var pid = $("#pds-single-elements-pid").val().trim();
				if (!pid) { alert("Please enter a Personnel ID."); return; }
				var isDryRun = $("#pds-single-elements-dryrun").is(":checked");
				var $btn = $(this).find("input[type=submit]");
				$btn.prop("disabled", true).val(isDryRun ? "Fetching..." : "Importing...");
				$("#pds-single-elements-result").html("<p><span class=\"dashicons dashicons-update pds-spin\"></span> " + (isDryRun ? "Fetching preview..." : "Importing...") + "</p>");
				$.ajax({
					url: ajaxurl, method: "POST", timeout: 300000,
					data: { action: "symplectic_cpt_import_single", nonce: nonceS, personnel_id: pid, dry_run: isDryRun ? 1 : 0 },
					success: function(r) {
						$btn.prop("disabled", false).val("Import Elements Data");
						if (r.success) {
							var d = r.data;
							var sc = d.status === "ok" ? "#46b450" : (d.status === "failed" ? "#dc3232" : "#999");
							var html = "<div style=\"padding:12px;background:#fff;border:1px solid #ccd0d4;border-radius:4px\">";
							if (d.dry_run) {
								html += "<strong style=\"color:#0073aa\">DRY RUN</strong>&ensp;";
								html += esc(d.post_title) + " (Post " + esc(d.post_id) + ")";
								if (d.preview_data) {
									html += "<div style=\"margin-top:10px\">" + renderElementsPreview(d.preview_data) + "</div>";
								}
								if (d.fetch_errors && d.fetch_errors.length) {
									html += "<div style=\"margin-top:8px;color:#9a5e00;font-size:11px\"><strong>Fetch warnings:</strong> " + d.fetch_errors.map(esc).join("; ") + "</div>";
								}
							} else {
								html += "<strong style=\"color:" + sc + "\">" + esc(d.status.toUpperCase()) + "</strong>&ensp;";
								html += esc(d.post_title) + " (Post " + esc(d.post_id) + ")";
								html += "<span style=\"color:#666;font-size:12px\"> --- " + esc(d.fields_written) + " fields written</span>";
							}
							if (d.error_message) html += "<div style=\"color:#dc3232;margin-top:6px;font-size:12px\">" + esc(d.error_message) + "</div>";
							html += "</div>";
							$("#pds-single-elements-result").html(html);
						} else {
							$("#pds-single-elements-result").html("<div class=\"notice notice-error\"><p>" + esc(r.data && r.data.error_message || "Unknown") + "</p></div>");
						}
					},
					error: function(x, s, e) {
						$btn.prop("disabled", false).val("Import Elements Data");
						$("#pds-single-elements-result").html("<div class=\"notice notice-error\"><p>AJAX error: " + esc(e) + "</p></div>");
					}
				});
			});

			// Clear timestamp -- single
			$("#pds-clear-ts-single").on("click", function() {
				var pid = $("#pds-clear-ts-pid").val().trim();
				if (!pid) { alert("Please enter a Personnel ID."); return; }
				var $btn = $(this).prop("disabled", true).val("Clearing...");
				$("#pds-clear-ts-single-result").html("<p><span class=\"dashicons dashicons-update pds-spin\"></span> Clearing...</p>");
				$.ajax({
					url: ajaxurl, method: "POST",
					data: { action: "symplectic_cpt_clear_timestamp_single", nonce: nonceS, personnel_id: pid },
					success: function(r) {
						$btn.prop("disabled", false).text("Clear Timestamp");
						if (r.success) {
							$("#pds-clear-ts-single-result").html("<div style=\"padding:10px;background:#fff;border:1px solid #ccd0d4;border-radius:4px\"><strong style=\"color:#46b450\">Cleared</strong> &mdash; Post " + esc(r.data.post_id) + " will be fully re-imported on next sync.</div>");
						} else {
							$("#pds-clear-ts-single-result").html("<div class=\"notice notice-error\"><p>" + esc(r.data && r.data.error_message || "Unknown error") + "</p></div>");
						}
					},
					error: function(x, s, e) {
						$btn.prop("disabled", false).text("Clear Timestamp");
						$("#pds-clear-ts-single-result").html("<div class=\"notice notice-error\"><p>AJAX error: " + esc(e) + "</p></div>");
					}
				});
			});

			// Clear timestamps -- all
			$("#pds-clear-ts-all").on("click", function() {
				if (!confirm("Clear sync timestamps for all person posts? They will all be fully re-imported on the next Elements sync.")) return;
				var $btn = $(this).prop("disabled", true).text("Clearing...");
				$("#pds-clear-ts-all-result").html("<p><span class=\"dashicons dashicons-update pds-spin\"></span> Clearing...</p>");
				$.ajax({
					url: ajaxurl, method: "POST",
					data: { action: "symplectic_cpt_clear_timestamps", nonce: nonceS },
					success: function(r) {
						$btn.prop("disabled", false).text("Clear All Timestamps");
						if (r.success) {
							$("#pds-clear-ts-all-result").html("<div style=\"padding:10px;background:#fff;border:1px solid #ccd0d4;border-radius:4px\"><strong style=\"color:#46b450\">Cleared " + esc(r.data.cleared) + " timestamps.</strong> All person posts will be fully re-imported on the next Elements sync.</div>");
						} else {
							$("#pds-clear-ts-all-result").html("<div class=\"notice notice-error\"><p>" + esc(r.data && r.data.error_message || "Unknown error") + "</p></div>");
						}
					},
					error: function(x, s, e) {
						$btn.prop("disabled", false).text("Clear All Timestamps");
						$("#pds-clear-ts-all-result").html("<div class=\"notice notice-error\"><p>AJAX error: " + esc(e) + "</p></div>");
					}
				});
			});

			// Initial poll both
			pollPersonnel();
			pollSymplectic();
		});
	');
}

// ============================================================
// Admin page render
// ============================================================

function person_data_sync_render_page() {
	$symplectic_ok = defined('SYMPLECTIC_API_USERNAME') && defined('SYMPLECTIC_API_PASSWORD') && defined('CF_810_API_ENDPOINT_KEY');
	$next_p_cron = wp_next_scheduled(PERSONNEL_CPT_CRON_HOOK);
	// Symplectic sync runs automatically after personnel sync completes (no independent cron)
	?>
	<div class="wrap">
		<h1>People CPT Data Sync <span style="font-size:12px;color:#999;font-weight:normal">v<?php echo PERSON_CPT_DATA_SYNC_VERSION; ?></span></h1>
		<p>Syncs personnel data from the CAES Personnel API and Symplectic Elements data into <code>caes_hub_person</code> posts. Daily cron runs personnel sync first, then Symplectic sync automatically.</p>

		<div class="pds-wrapper">

			<div class="pds-sections">
				<!-- PERSONNEL SYNC -->
				<div class="pds-section">
					<h2>Personnel API Sync</h2>
					<p class="description">Syncs name, title, department, program area, contact info, and active status from the CAES personnel database.</p>
					<?php if ($next_p_cron): ?>
					<p class="description">Next scheduled run: <strong><?php echo esc_html(date('Y-m-d H:i:s', $next_p_cron)); ?></strong></p>
					<?php endif; ?>

					<div style="margin:16px 0">
						<strong style="font-size:12px;text-transform:uppercase;color:#555">Current Status</strong>
						<div id="pds-personnel-current" style="margin-top:8px"><p style="color:#999">Loading...</p></div>
					</div>

					<div class="pds-last-completed-section" style="display:none;margin:16px 0;padding-top:12px;border-top:1px solid #e5e5e5">
						<strong style="font-size:12px;text-transform:uppercase;color:#555">Last Completed Run</strong>
						<div class="pds-last-completed-panel" style="margin-top:8px"></div>
					</div>

					<div style="margin-top:16px;padding-top:12px;border-top:1px solid #e5e5e5">
						<input type="button" id="pds-p-trigger" class="button button-primary" value="Sync All Personnel">
						<input type="button" id="pds-p-stop" class="button" value="Stop" style="display:none;margin-left:6px">
					</div>
				</div>

				<!-- SYMPLECTIC SYNC -->
				<div class="pds-section">
					<h2>Symplectic Elements Sync</h2>
					<p class="description">Imports scholarly works, distinctions, courses, overview, and areas of expertise from Symplectic Elements.</p>
					<?php if (!$symplectic_ok): ?>
					<div class="notice notice-error" style="margin:8px 0"><p>Missing API credentials in wp-config.php.</p></div>
					<?php endif; ?>
					<p class="description">Runs automatically after personnel sync completes via cron, or manually below.</p>

					<div style="margin:16px 0">
						<strong style="font-size:12px;text-transform:uppercase;color:#555">Current Status</strong>
						<div id="pds-symplectic-current" style="margin-top:8px"><p style="color:#999">Loading...</p></div>
					</div>

					<div id="pds-symplectic-last-completed" style="display:none;margin:16px 0;padding-top:12px;border-top:1px solid #e5e5e5">
						<strong style="font-size:12px;text-transform:uppercase;color:#555">Last Completed Run</strong>
						<div class="pds-last-completed-panel" style="margin-top:8px"></div>
					</div>

					<div style="margin-top:16px;padding-top:12px;border-top:1px solid #e5e5e5">
						<input type="button" id="pds-s-trigger" class="button button-primary" value="Sync All Elements Data"
							<?php echo $symplectic_ok ? '' : 'disabled'; ?>>
						<input type="button" id="pds-s-stop" class="button" value="Stop" style="display:none;margin-left:6px">
						<input type="button" id="pds-s-resume" class="button button-secondary" value="Resume" style="display:none;margin-left:6px">
					</div>
				</div>
			</div>

			<!-- SINGLE PERSON LOOKUP -->
			<div class="pds-panel" style="margin-top:20px">
				<h2>Single Person Lookup</h2>
				<p class="description" style="margin-bottom:16px">Enter a College ID to pull fresh data from both APIs and update (or create) the person's CPT post.</p>

				<div style="display:flex;gap:40px;flex-wrap:wrap">
					<div style="flex:1;min-width:300px">
						<h3 style="margin-top:0;font-size:13px">Personnel Data</h3>
						<form id="pds-single-form">
							<div class="pds-form-group">
								<label for="pds-single-cid">College ID</label>
								<input type="text" id="pds-single-cid" placeholder="e.g. 12345" style="width:200px">
							</div>
							<label style="display:inline-flex;align-items:center;gap:4px;margin-bottom:12px;font-size:12px;cursor:pointer">
								<input type="checkbox" id="pds-single-dryrun" checked> Dry run (preview only)
							</label><br>
							<input type="submit" class="button button-secondary" value="Sync Single Person">
						</form>
						<div id="pds-single-result" style="margin-top:12px"></div>
					</div>

					<div style="flex:1;min-width:300px">
						<h3 style="margin-top:0;font-size:13px">Elements Data</h3>
						<form id="pds-single-elements-form">
							<div class="pds-form-group">
								<label for="pds-single-elements-pid">Personnel ID</label>
								<input type="text" id="pds-single-elements-pid" placeholder="e.g. 3885" style="width:200px">
							</div>
							<label style="display:inline-flex;align-items:center;gap:4px;margin-bottom:12px;font-size:12px;cursor:pointer">
								<input type="checkbox" id="pds-single-elements-dryrun" checked> Dry run (preview only)
							</label><br>
							<input type="submit" class="button button-secondary" value="Import Elements Data"
								<?php echo $symplectic_ok ? '' : 'disabled'; ?>>
						</form>
						<div id="pds-single-elements-result" style="margin-top:12px"></div>
					</div>
				</div>
			</div>

		<!-- CLEAR SYNC TIMESTAMPS -->
			<div class="pds-panel" style="margin-top:20px">
				<h2>Clear Elements Sync Timestamps</h2>
				<p class="description" style="margin-bottom:16px">Clearing a timestamp forces the next sync to re-import all data from Symplectic, bypassing the change-detection check. Use this after adding new fields.</p>

				<div style="display:flex;gap:40px;flex-wrap:wrap">
					<div style="flex:1;min-width:300px">
						<h3 style="margin-top:0;font-size:13px">Single Person</h3>
						<div class="pds-form-group">
							<label for="pds-clear-ts-pid">Personnel ID</label>
							<input type="text" id="pds-clear-ts-pid" placeholder="e.g. 3885" style="width:200px">
						</div>
						<button id="pds-clear-ts-single" class="button button-secondary" <?php echo $symplectic_ok ? '' : 'disabled'; ?>>Clear Timestamp</button>
						<div id="pds-clear-ts-single-result" style="margin-top:12px"></div>
					</div>

					<div style="flex:1;min-width:300px">
						<h3 style="margin-top:0;font-size:13px">All People</h3>
						<p class="description" style="margin-bottom:12px">Clears timestamps for every person post. The next full sync will re-import all Elements data.</p>
						<button id="pds-clear-ts-all" class="button button-secondary" <?php echo $symplectic_ok ? '' : 'disabled'; ?>>Clear All Timestamps</button>
						<div id="pds-clear-ts-all-result" style="margin-top:12px"></div>
					</div>
				</div>
			</div>

		</div>
	</div>
	<?php
}

// ============================================================
// AJAX handlers -- Personnel
// ============================================================

add_action('wp_ajax_personnel_cpt_status', 'personnel_cpt_ajax_status');
add_action('wp_ajax_personnel_cpt_trigger', 'personnel_cpt_ajax_trigger');
add_action('wp_ajax_personnel_cpt_stop', 'personnel_cpt_ajax_stop');
add_action('wp_ajax_personnel_cpt_sync_single', 'personnel_cpt_ajax_sync_single');

function personnel_cpt_check_ajax() {
	if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'personnel_cpt_sync_nonce')) {
		wp_send_json_error(array('error_message' => 'Security check failed.'));
	}
	if (!current_user_can('manage_options')) {
		wp_send_json_error(array('error_message' => 'Insufficient permissions.'));
	}
}

function personnel_cpt_ajax_status() {
	personnel_cpt_check_ajax();
	$state = personnel_cpt_get_state();
	// Don't send the API data blob to the browser
	unset($state['_api_data']);
	wp_send_json_success(array('state' => $state));
}

function personnel_cpt_ajax_trigger() {
	personnel_cpt_check_ajax();
	$started = personnel_cpt_start_job('manual');
	if (!$started) {
		$state = personnel_cpt_get_state();
		if ($state['status'] === 'running') {
			wp_send_json_error(array('error_message' => 'A sync is already running.'));
		} else {
			wp_send_json_error(array('error_message' => 'Failed to start sync. Check error log.'));
		}
	}
	wp_send_json_success(array('message' => 'Personnel sync started.'));
}

function personnel_cpt_ajax_stop() {
	personnel_cpt_check_ajax();
	$state = personnel_cpt_get_state();
	if ($state['status'] !== 'running') {
		wp_send_json_error(array('error_message' => 'No sync is currently running.'));
	}
	$state['status'] = 'stopped';
	$state['stop_requested'] = false;
	update_option(PERSONNEL_CPT_STATE_KEY, $state, false);
	wp_clear_scheduled_hook(PERSONNEL_CPT_BATCH_HOOK);
	wp_send_json_success(array('message' => 'Sync stopped.'));
}

function personnel_cpt_ajax_sync_single() {
	personnel_cpt_check_ajax();
	$college_id = isset($_POST['college_id']) ? intval($_POST['college_id']) : 0;
	if ($college_id <= 0) {
		wp_send_json_error(array('error_message' => 'College ID is required.'));
	}

	$dry_run = !empty($_POST['dry_run']);

	@set_time_limit(60);
	$result = personnel_cpt_sync_single_by_college_id($college_id, $dry_run);

	if (is_wp_error($result)) {
		wp_send_json_error(array('error_message' => $result->get_error_message()));
	}

	$post_title = $result['post_id'] ? get_the_title($result['post_id']) : '';

	$response = array(
		'status'         => $result['status'],
		'action'         => $result['action'],
		'post_id'        => $result['post_id'],
		'post_title'     => $post_title,
		'fields_written' => $result['fields_written'],
		'error_message'  => $result['error_message'],
		'dry_run'        => $dry_run,
	);

	if ($dry_run && isset($result['preview_data'])) {
		$response['preview_data'] = $result['preview_data'];
	}

	wp_send_json_success($response);
}

/**
 * Symplectic Elements Scheduled Import (CPT Version)
 *
 * Daily cron job (midnight) that imports Symplectic Elements data for all
 * caes_hub_person CPT posts that have a personnel_id meta field.
 * Includes a control panel page under CAES Tools.
 *
 * This replaces symplectic-scheduled-import.php after migration is complete.
 * Key differences from the user-targeted version:
 * - Queries caes_hub_person posts instead of WordPress users
 * - Writes to post meta instead of user meta
 * - Fixes missing journal field extraction for scholarly works
 */

define('SYMPLECTIC_CPT_BATCH_SIZE',  10);
define('SYMPLECTIC_CPT_MAX_ERRORS',  200);
define('SYMPLECTIC_CPT_STATE_KEY',   'symplectic_cpt_import_state');
define('SYMPLECTIC_CPT_CRON_HOOK',   'symplectic_cpt_import_daily');
define('SYMPLECTIC_CPT_BATCH_HOOK',  'symplectic_cpt_import_batch');

// ============================================================
// Cron scheduling
// ============================================================

// Symplectic sync is triggered automatically after personnel sync completes (cron),
// or manually from the admin page. No independent cron schedule.
add_action('init', 'symplectic_cpt_unschedule_legacy');

function symplectic_cpt_unschedule_legacy() {
	$ts = wp_next_scheduled(SYMPLECTIC_CPT_CRON_HOOK);
	if ($ts) {
		wp_unschedule_event($ts, SYMPLECTIC_CPT_CRON_HOOK);
	}
}

add_action(SYMPLECTIC_CPT_BATCH_HOOK, 'symplectic_cpt_run_batch');

// ============================================================
// State management
// ============================================================

function symplectic_cpt_default_state() {
	return array(
		'status'          => 'idle',
		'triggered_by'    => null,
		'started_at'      => null,
		'completed_at'    => null,
		'total_posts'     => 0,
		'processed_posts' => 0,
		'stats'           => array(
			'posts_ok'       => 0,
			'posts_failed'   => 0,
			'posts_skipped'  => 0,
			'fields_written' => 0,
			'fetch_errors'   => 0,
		),
		'errors'          => array(),
		'stop_requested'  => false,
		'last_completed'  => null,
	);
}

function symplectic_cpt_get_state() {
	$state = get_option(SYMPLECTIC_CPT_STATE_KEY, null);
	if (!is_array($state)) {
		return symplectic_cpt_default_state();
	}
	return $state;
}

// ============================================================
// Query helpers
// ============================================================

function symplectic_cpt_get_person_posts($offset = 0, $number = -1) {
	return get_posts(array(
		'post_type'      => 'caes_hub_person',
		'post_status'    => 'publish',
		'meta_query'     => array(
			array('key' => 'personnel_id', 'value' => '', 'compare' => '!='),
			array(
				'relation' => 'OR',
				array('key' => 'is_active', 'value' => '1'),
				array('key' => 'is_active', 'compare' => 'NOT EXISTS'),
			),
		),
		'posts_per_page' => $number,
		'offset'         => $offset,
		'orderby'        => 'ID',
		'order'          => 'ASC',
		'fields'         => 'ids',
	));
}

function symplectic_cpt_count_person_posts() {
	$posts = get_posts(array(
		'post_type'      => 'caes_hub_person',
		'post_status'    => 'publish',
		'meta_query'     => array(
			array('key' => 'personnel_id', 'value' => '', 'compare' => '!='),
			array(
				'relation' => 'OR',
				array('key' => 'is_active', 'value' => '1'),
				array('key' => 'is_active', 'compare' => 'NOT EXISTS'),
			),
		),
		'posts_per_page' => -1,
		'fields'         => 'ids',
	));
	return count($posts);
}

// ============================================================
// Job control
// ============================================================

function symplectic_cpt_start_job($triggered_by = 'manual') {
	$state = symplectic_cpt_get_state();

	if ($state['status'] === 'running') {
		return false;
	}

	$total = symplectic_cpt_count_person_posts();

	if ($total === 0) {
		return false;
	}

	$last_completed      = $state['last_completed'];
	$new_state           = symplectic_cpt_default_state();
	$new_state['status']         = 'running';
	$new_state['triggered_by']   = $triggered_by;
	$new_state['started_at']     = time();
	$new_state['total_posts']    = $total;
	$new_state['last_completed'] = $last_completed;
	update_option(SYMPLECTIC_CPT_STATE_KEY, $new_state, false);

	wp_schedule_single_event(time(), SYMPLECTIC_CPT_BATCH_HOOK);
	return true;
}

function symplectic_cpt_resume_job() {
	$state = symplectic_cpt_get_state();
	if ($state['status'] !== 'stopped') {
		return false;
	}
	if ($state['processed_posts'] >= $state['total_posts']) {
		return false;
	}
	$state['status']         = 'running';
	$state['stop_requested'] = false;
	update_option(SYMPLECTIC_CPT_STATE_KEY, $state, false);
	wp_schedule_single_event(time(), SYMPLECTIC_CPT_BATCH_HOOK);
	return true;
}

function symplectic_cpt_run_batch() {
	$state = symplectic_cpt_get_state();

	if ($state['status'] !== 'running') {
		return;
	}

	if (!defined('SYMPLECTIC_API_USERNAME') || !defined('SYMPLECTIC_API_PASSWORD') || !defined('CF_810_API_ENDPOINT_KEY')) {
		$state['status']       = 'error';
		$state['completed_at'] = time();
		$state['errors'][]     = 'Missing required constants: SYMPLECTIC_API_USERNAME, SYMPLECTIC_API_PASSWORD, or CF_810_API_ENDPOINT_KEY.';
		update_option(SYMPLECTIC_CPT_STATE_KEY, $state, false);
		return;
	}

	$post_ids = symplectic_cpt_get_person_posts($state['processed_posts'], SYMPLECTIC_CPT_BATCH_SIZE);

	foreach ($post_ids as $post_id) {
		// Re-read state before each post to detect an external stop.
		$check = symplectic_cpt_get_state();
		if ($check['status'] !== 'running') {
			return;
		}

		$personnel_id = get_post_meta($post_id, 'personnel_id', true);
		if (empty($personnel_id)) {
			$state['stats']['posts_skipped']++;
			$state['processed_posts']++;
			update_option(SYMPLECTIC_CPT_STATE_KEY, $state, false);
			continue;
		}

		$deadline = time() + 180; // 3-minute per-post hard timeout
		$result   = symplectic_cpt_import_single_post($post_id, $personnel_id, $deadline);
		$state['processed_posts']++;

		if ($result['status'] === 'ok') {
			$state['stats']['posts_ok']++;
		} elseif ($result['status'] === 'failed') {
			$state['stats']['posts_failed']++;
		} else {
			$state['stats']['posts_skipped']++;
		}

		$state['stats']['fields_written'] += $result['fields_written'];
		$state['stats']['fetch_errors']   += count($result['fetch_errors']);

		if (!empty($result['error_message']) && count($state['errors']) < SYMPLECTIC_CPT_MAX_ERRORS) {
			$state['errors'][] = 'Post ' . $post_id . ' (pid:' . $personnel_id . '): ' . $result['error_message'];
		}
		foreach ($result['fetch_errors'] as $fe) {
			if (count($state['errors']) < SYMPLECTIC_CPT_MAX_ERRORS) {
				$state['errors'][] = 'Post ' . $post_id . ' fetch: ' . $fe;
			}
		}

		update_option(SYMPLECTIC_CPT_STATE_KEY, $state, false);
	}

	if ($state['processed_posts'] < $state['total_posts'] && !empty($post_ids)) {
		wp_schedule_single_event(time() + 2, SYMPLECTIC_CPT_BATCH_HOOK);
	} else {
		$state['status']       = 'complete';
		$state['completed_at'] = time();
		$state['last_completed'] = array(
			'started_at'      => $state['started_at'],
			'completed_at'    => $state['completed_at'],
			'triggered_by'    => $state['triggered_by'],
			'total_posts'     => $state['total_posts'],
			'processed_posts' => $state['processed_posts'],
			'stats'           => $state['stats'],
			'errors'          => $state['errors'],
		);
		update_option(SYMPLECTIC_CPT_STATE_KEY, $state, false);
	}
}

// ============================================================
// Core per-post import
// ============================================================

function symplectic_cpt_import_single_post($post_id, $personnel_id, $deadline = 0, $dry_run = false) {
	if (!$deadline) $deadline = time() + 180;
	$result = array(
		'status'         => 'failed',
		'fields_written' => 0,
		'fields_failed'  => 0,
		'fetch_errors'   => array(),
		'error_message'  => null,
	);

	// Fetch UGA ID from CAES internal personnel API
	if (time() >= $deadline) {
		$result['error_message'] = 'Timed out (3 min) before CAES API call.';
		return $result;
	}
	$caes_url  = 'https://secure.caes.uga.edu/rest/personnel/getUGAids'
		. '?PersonnelID=' . urlencode($personnel_id)
		. '&APIkey=' . urlencode(CF_810_API_ENDPOINT_KEY);
	$caes_resp = wp_remote_get($caes_url, array('timeout' => 15, 'sslverify' => true));

	if (is_wp_error($caes_resp)) {
		$result['error_message'] = 'CAES API: ' . $caes_resp->get_error_message();
		return $result;
	}
	if (wp_remote_retrieve_response_code($caes_resp) !== 200) {
		$result['error_message'] = 'CAES API HTTP ' . wp_remote_retrieve_response_code($caes_resp);
		return $result;
	}

	$caes_data = json_decode(wp_remote_retrieve_body($caes_resp), true);
	if (empty($caes_data) || !isset($caes_data[0]['UGA_ID']) || empty($caes_data[0]['UGA_ID'])) {
		$result['error_message'] = 'No UGA ID found for personnel_id ' . $personnel_id;
		return $result;
	}

	$uga_id = $caes_data[0]['UGA_ID'];

	// Fetch Elements user object
	if (time() >= $deadline) {
		$result['error_message'] = 'Timed out (3 min) before Elements API call.';
		return $result;
	}
	$api_args = array(
		'headers'   => array('Authorization' => 'Basic ' . base64_encode(SYMPLECTIC_API_USERNAME . ':' . SYMPLECTIC_API_PASSWORD)),
		'timeout'   => 60,
		'sslverify' => true,
	);

	$user_url  = 'https://uga.elements.symplectic.org:8091/secure-api/v6.13/users'
		. '?query=proprietary-id%3D%22' . urlencode($uga_id) . '%22&detail=full';
	$user_resp = wp_remote_get($user_url, $api_args);

	if (is_wp_error($user_resp)) {
		$result['error_message'] = 'Elements user API: ' . $user_resp->get_error_message();
		return $result;
	}
	if (wp_remote_retrieve_response_code($user_resp) !== 200) {
		$result['error_message'] = 'Elements user API HTTP ' . wp_remote_retrieve_response_code($user_resp);
		return $result;
	}

	libxml_use_internal_errors(true);
	$user_xml = simplexml_load_string(wp_remote_retrieve_body($user_resp));
	if ($user_xml === false) {
		libxml_clear_errors();
		$result['error_message'] = 'Elements user XML parse failed';
		return $result;
	}

	$user_xml->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');
	$user_objects = $user_xml->xpath('//api:object[@category="user"]');

	if (empty($user_objects)) {
		$result['error_message'] = 'No user object in Elements for UGA ID ' . $uga_id;
		return $result;
	}

	$object    = $user_objects[0];
	$user_info = array();
	foreach ($object->attributes() as $k => $v) {
		$user_info[$k] = (string)$v;
	}
	$object->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');

	$keyword_nodes = $object->xpath('./api:all-labels/api:keywords/api:keyword');
	if (!empty($keyword_nodes)) {
		$user_info['keywords'] = array();
		foreach ($keyword_nodes as $kw) {
			$user_info['keywords'][] = (string)$kw;
		}
	}

	$fragment_nodes = $object->xpath('./api:public-url-path-fragment');
	if (!empty($fragment_nodes)) {
		$user_info['profile_url_fragment'] = (string)$fragment_nodes[0];
	}

	$overview_nodes = $object->xpath('.//api:record/api:native/api:field[@name="overview"]/api:text');
	if (!empty($overview_nodes)) {
		$user_info['overview'] = (string)$overview_nodes[0];
	}

	$degree_nodes = $object->xpath('.//api:record/api:native/api:field[@name="degrees"]/api:degrees/api:degree[@privacy="public"]');
	if (!empty($degree_nodes)) {
		$user_info['degrees'] = array();
		foreach ($degree_nodes as $deg) {
			$deg->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');
			$name_nodes  = $deg->xpath('./api:name');
			$inst_nodes  = $deg->xpath('./api:institution/api:line[@type="organisation"]');
			$state_nodes = $deg->xpath('./api:institution/api:line[@type="state"]');
			$country_nodes = $deg->xpath('./api:institution/api:line[@type="country"]');
			$year_nodes  = $deg->xpath('./api:end-date/api:year');
			$fos_nodes   = $deg->xpath('./api:field-of-study');
			$user_info['degrees'][] = array(
				'degree_name'           => !empty($name_nodes)    ? (string)$name_nodes[0]    : '',
				'degree_institution'    => !empty($inst_nodes)    ? (string)$inst_nodes[0]    : '',
				'degree_state'          => !empty($state_nodes)   ? (string)$state_nodes[0]   : '',
				'degree_country'        => !empty($country_nodes) ? (string)$country_nodes[0] : '',
				'degree_year'           => !empty($year_nodes)    ? (string)$year_nodes[0]    : '',
				'degree_field_of_study' => !empty($fos_nodes)     ? (string)$fos_nodes[0]     : '',
			);
		}
	}

	$elements_user_id = isset($user_info['id']) ? (int)$user_info['id'] : null;
	$last_modified = isset($user_info['last-modified-when']) ? (string)$user_info['last-modified-when'] : '';

	// Check if the user data has changed since last sync
	if (!$dry_run && $last_modified !== '') {
		$stored_modified = get_post_meta($post_id, '_symplectic_last_modified', true);
		if ($stored_modified === $last_modified) {
			$result['status'] = 'skipped';
			return $result;
		}
	}

	// Fetch relationships (publications, distinctions, courses)
	$publications        = array();
	$activities          = array();
	$teaching_activities = array();
	$fetch_errors        = array();

	if ($elements_user_id) {
		$rel_url    = 'https://uga.elements.symplectic.org:8091/secure-api/v6.13/users/' . $elements_user_id . '/relationships?per-page=25&detail=full';
		$page_count = 0;
		$max_pages  = 10;
		$has_next   = false;

		do {
			$has_next = false;
			if (++$page_count > $max_pages) break;

			if (time() >= $deadline) {
				$fetch_errors[] = 'Timed out (3 min) before relationship page ' . $page_count . '.';
				break;
			}

			$rel_resp = wp_remote_get($rel_url, $api_args);
			if (is_wp_error($rel_resp)) {
				$fetch_errors[] = 'Relationships p' . $page_count . ': ' . $rel_resp->get_error_message();
				break;
			}
			if (wp_remote_retrieve_response_code($rel_resp) !== 200) {
				$fetch_errors[] = 'Relationships p' . $page_count . ': HTTP ' . wp_remote_retrieve_response_code($rel_resp);
				break;
			}

			$rel_xml = simplexml_load_string(wp_remote_retrieve_body($rel_resp));
			if ($rel_xml === false) {
				$xml_errs = array_map(fn($e) => trim($e->message), libxml_get_errors());
				libxml_clear_errors();
				$fetch_errors[] = 'Relationships p' . $page_count . ' XML: ' . implode('; ', $xml_errs);
				break;
			}

			$rel_xml->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');
			$page_objects = $rel_xml->xpath('//api:object');

			if (!empty($page_objects)) {
				foreach ($page_objects as $rel_obj) {
					if (time() >= $deadline) {
						$fetch_errors[] = 'Timed out (3 min) while processing relationships.';
						break 2;
					}
					$obj_data = array();
					foreach ($rel_obj->attributes() as $k => $v) {
						$obj_data[$k] = (string)$v;
					}
					$category = isset($obj_data['category']) ? $obj_data['category'] : null;

					if ($category === 'publication') {
						$rel_obj->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');
						$obj_data = array_merge($obj_data, symplectic_cpt_extract_publication_fields($rel_obj));
						$publications[] = $obj_data;

					} elseif ($category === 'activity'
						&& isset($obj_data['type'])
						&& $obj_data['type'] === 'distinction'
					) {
						$rel_obj->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');
						$obj_data = array_merge($obj_data, symplectic_cpt_extract_activity_fields($rel_obj));
						$activities[] = $obj_data;

					} elseif ($category === 'teaching-activity'
						&& isset($obj_data['type'])
						&& $obj_data['type'] === 'course-taught'
					) {
						$rel_obj->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');
						$obj_data = array_merge($obj_data, symplectic_cpt_extract_teaching_activity_fields($rel_obj));
						if (isset($obj_data['term']) && symplectic_cpt_is_teaching_term_recent($obj_data['term'])) {
							$teaching_activities[] = $obj_data;
						}
					}
				}
				unset($page_objects);
			}

			// Pagination
			$pag_nodes = $rel_xml->xpath('//api:pagination');
			if (!empty($pag_nodes)) {
				$pag = $pag_nodes[0];
				$pag->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');
				$next = $pag->xpath('./api:page[@position="next"]');
				if (!empty($next) && (string)$next[0]['href']) {
					$has_next = true;
					$rel_url  = (string)$next[0]['href'];
				}
			}
			unset($rel_xml);

		} while ($has_next);
	}

	$result['fetch_errors'] = $fetch_errors;

	// Sort publications by citation count desc, keep top 5
	usort($publications, function($a, $b) {
		return (isset($b['citation-count']) ? $b['citation-count'] : -1)
			 - (isset($a['citation-count']) ? $a['citation-count'] : -1);
	});
	$publications = array_slice($publications, 0, 5);

	if ($dry_run) {
		$result['status'] = 'ok';
		$result['fetch_errors'] = $fetch_errors;
		$result['preview_data'] = array(
			'uga_id'              => $uga_id,
			'elements_user_id'    => $elements_user_id,
			'overview'            => isset($user_info['overview']) ? mb_substr($user_info['overview'], 0, 300) : null,
			'keywords'            => isset($user_info['keywords']) ? $user_info['keywords'] : array(),
			'publications_count'  => count($publications),
			'publications'        => array_map(function($p) {
				return array(
					'title'   => isset($p['title']) ? $p['title'] : '',
					'journal' => isset($p['journal']) ? $p['journal'] : '',
					'year'    => isset($p['publication_date']) ? $p['publication_date'] : '',
				);
			}, $publications),
			'distinctions_count'  => count($activities),
			'courses_count'       => count($teaching_activities),
		);
		return $result;
	}

	// Write to ACF fields on the CPT post
	// Clear existing fields first
	foreach (array(
		'field_person_cpt_elements_user_id',
		'field_person_cpt_elements_profile_url_fragment',
		'field_person_cpt_elements_overview',
		'field_person_cpt_elements_areas_of_expertise',
		'field_person_cpt_elements_scholarly_works',
		'field_person_cpt_elements_distinctions',
		'field_person_cpt_elements_courses_taught',
		'field_person_cpt_elements_degrees',
	) as $fk) {
		delete_field($fk, $post_id);
	}

	$fields_written = 0;
	$fields_failed  = 0;

	$write_field = function($field_key, $value) use ($post_id, &$fields_written, &$fields_failed) {
		if ($value === null || $value === '' || $value === array()) return;
		$r = update_field($field_key, $value, $post_id);
		$r = ($r !== false) ? true : false;
		if (!$r) {
			$stored = get_field($field_key, $post_id);
			$r = ($stored !== false && $stored !== null && $stored !== '');
		}
		if ($r) $fields_written++; else $fields_failed++;
	};

	$write_field('field_person_cpt_elements_user_id', $elements_user_id);
	$write_field('field_person_cpt_elements_profile_url_fragment', isset($user_info['profile_url_fragment']) ? $user_info['profile_url_fragment'] : null);
	$write_field('field_person_cpt_elements_overview', isset($user_info['overview']) ? $user_info['overview'] : null);

	// Areas of expertise -- resolve/create taxonomy terms
	if (!empty($user_info['keywords'])) {
		$term_ids = array();
		foreach ($user_info['keywords'] as $keyword) {
			$existing = get_term_by('name', $keyword, 'areas_of_expertise');
			if ($existing && !is_wp_error($existing)) {
				$term_ids[] = $existing->term_id;
			} else {
				$inserted = wp_insert_term($keyword, 'areas_of_expertise');
				if (!is_wp_error($inserted)) {
					$term_ids[] = $inserted['term_id'];
				}
			}
		}
		if (!empty($term_ids)) {
			$write_field('field_person_cpt_elements_areas_of_expertise', $term_ids);
		}
	}

	// Scholarly works repeater
	$pub_rows = array();
	foreach ($publications as $pub) {
		$pub_rows[] = array(
			'pub_title'          => isset($pub['title'])            ? $pub['title']            : '',
			'pub_type'           => isset($pub['type'])             ? $pub['type']             : '',
			'pub_journal'        => isset($pub['journal'])          ? $pub['journal']          : '',
			'pub_doi'            => isset($pub['doi'])              ? $pub['doi']              : '',
			'pub_year'           => isset($pub['publication_date']) ? $pub['publication_date'] : '',
			'pub_authors'        => isset($pub['authors'])          ? $pub['authors']          : '',
			'pub_citation_count' => isset($pub['citation-count'])  ? (int)$pub['citation-count'] : '',
		);
	}
	if (!empty($pub_rows)) {
		$r = update_field('field_person_cpt_elements_scholarly_works', $pub_rows, $post_id);
		$r = ($r !== false) ? true : false;
		if (!$r) {
			$stored = get_field('field_person_cpt_elements_scholarly_works', $post_id);
			$r = !empty($stored);
		}
		if ($r) $fields_written++; else $fields_failed++;
	}

	// Distinctions repeater
	$dist_rows = array();
	foreach ($activities as $act) {
		$dist_rows[] = array(
			'distinction_title'       => isset($act['title'])       ? $act['title']       : '',
			'distinction_date'        => isset($act['date'])        ? $act['date']        : '',
			'distinction_description' => isset($act['description']) ? $act['description'] : '',
		);
	}
	if (!empty($dist_rows)) {
		$r = update_field('field_person_cpt_elements_distinctions', $dist_rows, $post_id);
		$r = ($r !== false) ? true : false;
		if (!$r) {
			$stored = get_field('field_person_cpt_elements_distinctions', $post_id);
			$r = !empty($stored);
		}
		if ($r) $fields_written++; else $fields_failed++;
	}

	// Courses taught repeater (with dedup)
	$course_rows      = array();
	$seen_course_keys = array();
	foreach ($teaching_activities as $ta) {
		$code      = isset($ta['course_code']) ? $ta['course_code'] : '';
		$term      = isset($ta['term'])        ? $ta['term']        : '';
		$dedup_key = $code . '|' . $term;
		if (isset($seen_course_keys[$dedup_key])) continue;
		$seen_course_keys[$dedup_key] = true;
		$course_rows[] = array(
			'course_title' => isset($ta['title']) ? $ta['title'] : '',
			'course_code'  => $code,
			'course_term'  => $term,
		);
	}
	if (!empty($course_rows)) {
		$r = update_field('field_person_cpt_elements_courses_taught', $course_rows, $post_id);
		$r = ($r !== false) ? true : false;
		if (!$r) {
			$stored = get_field('field_person_cpt_elements_courses_taught', $post_id);
			$r = !empty($stored);
		}
		if ($r) $fields_written++; else $fields_failed++;
	}

	// Degrees repeater
	if (!empty($user_info['degrees'])) {
		$r = update_field('field_person_cpt_elements_degrees', $user_info['degrees'], $post_id);
		$r = ($r !== false) ? true : false;
		if (!$r) {
			$stored = get_field('field_person_cpt_elements_degrees', $post_id);
			$r = !empty($stored);
		}
		if ($r) $fields_written++; else $fields_failed++;
	}

	$result['status']         = ($fields_failed === 0) ? 'ok' : ($fields_written > 0 ? 'ok' : 'failed');
	$result['fields_written'] = $fields_written;
	$result['fields_failed']  = $fields_failed;

	// Store last-modified timestamp for change detection on future runs
	if ($result['status'] === 'ok' && $last_modified !== '') {
		update_post_meta($post_id, '_symplectic_last_modified', $last_modified);
	}

	return $result;
}

// Admin page, scripts, AJAX handlers moved to person-cpt-data-sync.php.
// ============================================================
// Extraction helpers
// ============================================================

function symplectic_cpt_extract_publication_fields($pub_xml) {
	$data = array();
	$pub_xml->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');

	$records = $pub_xml->xpath('.//api:record[@format="native" or @format="preferred"]');
	if (empty($records)) return $data;

	// Citation count -- prefer WoS, then Dimensions, then others
	$priority_order = array('wos', 'dimensions', 'dimensions-for-universities', 'scopus', 'epmc');
	$citation       = null;
	$best           = PHP_INT_MAX;

	foreach ($records as $rec) {
		$rec->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');
		$cn = $rec->xpath('./api:citation-count');
		if (!empty($cn)) {
			$p = array_search((string)$rec['source-name'], $priority_order);
			$p = ($p === false) ? PHP_INT_MAX : $p;
			if ($citation === null || $p < $best) {
				$citation = (int)(string)$cn[0];
				$best     = $p;
			}
		}
	}
	if ($citation !== null) $data['citation-count'] = $citation;

	$record = $records[0];
	$record->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');

	foreach ($record->xpath('.//api:field') as $field) {
		$field->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');
		$name = (string)$field['name'];
		$type = (string)$field['type'];
		$val  = null;

		if ($type === 'text') {
			$t = $field->xpath('./api:text');
			if (!empty($t)) $val = (string)$t[0];
		} elseif ($type === 'date') {
			$d = $field->xpath('./api:date');
			if (!empty($d)) {
				$d[0]->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');
				$yn = $d[0]->xpath('./api:year');
				$mn = $d[0]->xpath('./api:month');
				$y  = !empty($yn) ? (string)$yn[0] : '';
				$m  = !empty($mn) ? (int)(string)$mn[0] : 0;
				if ($y) {
					$val = $y;
				}
			}
		}

		if ($val) {
			switch ($name) {
				case 'title':            $data['title']            = $val; break;
				case 'journal':          $data['journal']          = $val; break;
				case 'publication-date': $data['publication_date'] = $val; break;
				case 'doi':              $data['doi']              = $val; break;
			}
		}

		if ($name === 'authors' && (string)$field['type'] === 'person-list') {
			$field->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');
			$persons = $field->xpath('./api:people/api:person');
			$parts   = array();
			foreach ($persons as $person) {
				$person->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');
				$ln = $person->xpath('./api:last-name');
				$in = $person->xpath('./api:initials');
				$last     = !empty($ln) ? (string)$ln[0] : '';
				$initials = !empty($in) ? (string)$in[0] : '';
				if ($last) {
					$parts[] = $initials ? $last . ' ' . $initials : $last;
				}
			}
			if (!empty($parts)) {
				$data['authors'] = implode(', ', $parts);
			}
		}
	}

	return $data;
}

function symplectic_cpt_extract_activity_fields($activity_xml) {
	$data = array();
	$activity_xml->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');

	$records = $activity_xml->xpath('.//api:record[@format="native" or @format="preferred"]');
	if (empty($records)) return $data;

	$record = $records[0];
	$record->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');

	foreach ($record->xpath('.//api:field') as $field) {
		$field->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');
		$name = (string)$field['name'];
		$type = (string)$field['type'];
		$val  = null;

		if ($type === 'text') {
			$t = $field->xpath('./api:text');
			if (!empty($t)) $val = (string)$t[0];
		} elseif ($type === 'date') {
			$d = $field->xpath('./api:date');
			if (!empty($d)) {
				$d[0]->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');
				$yn = $d[0]->xpath('./api:year');
				if (!empty($yn)) $val = (string)$yn[0];
			}
		}

		if ($val) {
			switch ($name) {
				case 'title':        $data['title']       = $val; break;
				case 'name':         if (!isset($data['title'])) $data['title'] = $val; break;
				case 'start-date':
				case 'awarded-date': $data['date']        = $val; break;
				case 'description':  $data['description'] = $val; break;
			}
		}
	}

	return $data;
}

function symplectic_cpt_extract_teaching_activity_fields($teaching_xml) {
	$data = array();
	$teaching_xml->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');

	$records = $teaching_xml->xpath('.//api:record[@format="native" or @format="preferred"]');
	if (empty($records)) return $data;

	$record = $records[0];
	$record->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');

	foreach ($record->xpath('.//api:field') as $field) {
		$field->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');
		$name = (string)$field['name'];
		$type = (string)$field['type'];
		$val  = null;

		if ($type === 'text') {
			$t = $field->xpath('./api:text');
			if (!empty($t)) $val = (string)$t[0];
		}

		if ($val) {
			switch ($name) {
				case 'title':       $data['title']       = $val; break;
				case 'c-term-year': $data['term']        = $val; break;
				case 'course-code': $data['course_code'] = $val; break;
			}
		}
	}

	return $data;
}

function symplectic_cpt_is_teaching_term_recent($term_string) {
	if (!preg_match('/^(Spring|Summer|Fall)\s+(\d{4})$/i', trim($term_string), $matches)) {
		return false;
	}

	$season = ucfirst(strtolower($matches[1]));
	$year   = (int)$matches[2];

	switch ($season) {
		case 'Spring': $end = new DateTime("$year-05-15"); break;
		case 'Summer': $end = new DateTime("$year-08-01"); break;
		case 'Fall':   $ny  = $year + 1; $end = new DateTime("$ny-01-01"); break;
		default: return false;
	}

	$cutoff = new DateTime();
	$cutoff->modify('-1 year');
	return $end >= $cutoff;
}

// ============================================================
// AJAX handlers -- Symplectic Elements
// ============================================================

add_action('wp_ajax_symplectic_cpt_status',        'symplectic_cpt_ajax_status');
add_action('wp_ajax_symplectic_cpt_trigger',       'symplectic_cpt_ajax_trigger');
add_action('wp_ajax_symplectic_cpt_stop',          'symplectic_cpt_ajax_stop');
add_action('wp_ajax_symplectic_cpt_resume',        'symplectic_cpt_ajax_resume');
add_action('wp_ajax_symplectic_cpt_import_single', 'symplectic_cpt_ajax_import_single');
add_action('wp_ajax_symplectic_cpt_clear_timestamps',        'symplectic_cpt_ajax_clear_timestamps');
add_action('wp_ajax_symplectic_cpt_clear_timestamp_single',  'symplectic_cpt_ajax_clear_timestamp_single');

function symplectic_cpt_check_ajax() {
	if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'symplectic_cpt_nonce')) {
		wp_send_json_error(array('error_message' => 'Security check failed.'));
	}
	if (!current_user_can('manage_options')) {
		wp_send_json_error(array('error_message' => 'Insufficient permissions.'));
	}
}

function symplectic_cpt_ajax_status() {
	symplectic_cpt_check_ajax();
	wp_send_json_success(array('state' => symplectic_cpt_get_state()));
}

function symplectic_cpt_ajax_trigger() {
	symplectic_cpt_check_ajax();
	if (!defined('SYMPLECTIC_API_USERNAME') || !defined('SYMPLECTIC_API_PASSWORD') || !defined('CF_810_API_ENDPOINT_KEY')) {
		wp_send_json_error(array('error_message' => 'API credentials not configured in wp-config.php.'));
	}

	$started = symplectic_cpt_start_job('manual');
	if (!$started) {
		$state = symplectic_cpt_get_state();
		if ($state['status'] === 'running') {
			wp_send_json_error(array('error_message' => 'An import is already running.'));
		} else {
			wp_send_json_error(array('error_message' => 'No person posts found with a personnel_id.'));
		}
	}
	wp_send_json_success(array('message' => 'Import queued.'));
}

function symplectic_cpt_ajax_stop() {
	symplectic_cpt_check_ajax();
	$state = symplectic_cpt_get_state();
	if ($state['status'] !== 'running') {
		wp_send_json_error(array('error_message' => 'No import is currently running.'));
	}
	$state['status']         = 'stopped';
	$state['stop_requested'] = false;
	update_option(SYMPLECTIC_CPT_STATE_KEY, $state, false);
	wp_clear_scheduled_hook(SYMPLECTIC_CPT_BATCH_HOOK);
	wp_send_json_success(array('message' => 'Import stopped.'));
}

function symplectic_cpt_ajax_resume() {
	symplectic_cpt_check_ajax();
	if (!defined('SYMPLECTIC_API_USERNAME') || !defined('SYMPLECTIC_API_PASSWORD') || !defined('CF_810_API_ENDPOINT_KEY')) {
		wp_send_json_error(array('error_message' => 'API credentials not configured in wp-config.php.'));
	}
	$resumed = symplectic_cpt_resume_job();
	if (!$resumed) {
		$state = symplectic_cpt_get_state();
		if ($state['status'] !== 'stopped') {
			wp_send_json_error(array('error_message' => 'Import is not in a stopped state (status: ' . $state['status'] . ').'));
		} else {
			wp_send_json_error(array('error_message' => 'No posts remaining to import.'));
		}
	}
	wp_send_json_success(array('message' => 'Import resumed.'));
}

function symplectic_cpt_ajax_import_single() {
	symplectic_cpt_check_ajax();
	if (!defined('SYMPLECTIC_API_USERNAME') || !defined('SYMPLECTIC_API_PASSWORD') || !defined('CF_810_API_ENDPOINT_KEY')) {
		wp_send_json_error(array('error_message' => 'API credentials not configured in wp-config.php.'));
	}

	$personnel_id = isset($_POST['personnel_id']) ? sanitize_text_field($_POST['personnel_id']) : '';
	if (empty($personnel_id)) {
		wp_send_json_error(array('error_message' => 'Personnel ID is required.'));
	}

	$dry_run = !empty($_POST['dry_run']);

	$posts = get_posts(array(
		'post_type'      => 'caes_hub_person',
		'post_status'    => 'publish',
		'meta_key'       => 'personnel_id',
		'meta_value'     => $personnel_id,
		'posts_per_page' => 1,
	));

	if (empty($posts)) {
		wp_send_json_error(array('error_message' => 'No caes_hub_person post found with personnel_id = ' . $personnel_id));
	}

	$post = $posts[0];
	@set_time_limit(300);
	$result = symplectic_cpt_import_single_post($post->ID, $personnel_id, 0, $dry_run);

	$response = array(
		'status'         => $result['status'],
		'post_id'        => $post->ID,
		'post_title'     => $post->post_title,
		'fields_written' => $result['fields_written'],
		'fields_failed'  => $result['fields_failed'],
		'fetch_errors'   => $result['fetch_errors'],
		'error_message'  => $result['error_message'],
		'dry_run'        => $dry_run,
	);

	if ($dry_run && isset($result['preview_data'])) {
		$response['preview_data'] = $result['preview_data'];
	}

	wp_send_json_success($response);
}

function symplectic_cpt_ajax_clear_timestamps() {
	symplectic_cpt_check_ajax();
	$posts = get_posts(array(
		'post_type'      => 'caes_hub_person',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array(array('key' => '_symplectic_last_modified', 'compare' => 'EXISTS')),
	));
	$count = 0;
	foreach ($posts as $post_id) {
		delete_post_meta($post_id, '_symplectic_last_modified');
		$count++;
	}
	wp_send_json_success(array('cleared' => $count));
}

function symplectic_cpt_ajax_clear_timestamp_single() {
	symplectic_cpt_check_ajax();
	$personnel_id = isset($_POST['personnel_id']) ? sanitize_text_field($_POST['personnel_id']) : '';
	if (empty($personnel_id)) {
		wp_send_json_error(array('error_message' => 'Personnel ID is required.'));
	}
	$posts = get_posts(array(
		'post_type'      => 'caes_hub_person',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => 'personnel_id',
		'meta_value'     => $personnel_id,
	));
	if (empty($posts)) {
		wp_send_json_error(array('error_message' => 'No caes_hub_person post found with personnel_id = ' . $personnel_id));
	}
	delete_post_meta($posts[0], '_symplectic_last_modified');
	wp_send_json_success(array('cleared' => 1, 'post_id' => $posts[0]));
}
