<?php
/**
 * Symplectic Elements Scheduled Import
 *
 * Daily cron job (midnight) that imports Symplectic Elements data for all
 * WordPress users who have a personnel_id meta field. Includes a control
 * panel page under CAES Tools.
 *
 * Depends on extraction helpers defined in symplectic-individual-user-import.php:
 *   symplectic_import_extract_publication_fields()
 *   symplectic_import_extract_activity_fields()
 *   symplectic_import_extract_teaching_activity_fields()
 *   symplectic_import_is_teaching_term_recent()
 */

define('SYMPLECTIC_SCHED_BATCH_SIZE',  10);
define('SYMPLECTIC_SCHED_MAX_ERRORS',  200);
define('SYMPLECTIC_SCHED_STATE_KEY',   'symplectic_sched_import_state');
define('SYMPLECTIC_SCHED_CRON_HOOK',   'symplectic_sched_import_daily');
define('SYMPLECTIC_SCHED_BATCH_HOOK',  'symplectic_sched_import_batch');

// ============================================================
// Cron scheduling
// ============================================================

add_action('init', 'symplectic_sched_maybe_schedule');

function symplectic_sched_maybe_schedule() {
	if (!wp_next_scheduled(SYMPLECTIC_SCHED_CRON_HOOK)) {
		wp_schedule_event(strtotime('tomorrow midnight'), 'daily', SYMPLECTIC_SCHED_CRON_HOOK);
	}
}

add_action(SYMPLECTIC_SCHED_CRON_HOOK,  'symplectic_sched_daily_trigger');
add_action(SYMPLECTIC_SCHED_BATCH_HOOK, 'symplectic_sched_run_batch');

function symplectic_sched_daily_trigger() {
	symplectic_sched_start_job('cron');
}

// ============================================================
// State management
// ============================================================

function symplectic_sched_default_state() {
	return array(
		'status'          => 'idle',
		'triggered_by'    => null,
		'started_at'      => null,
		'completed_at'    => null,
		'total_users'     => 0,
		'processed_users' => 0,
		'stats'           => array(
			'users_ok'       => 0,
			'users_failed'   => 0,
			'users_skipped'  => 0,
			'fields_written' => 0,
			'fetch_errors'   => 0,
		),
		'errors'          => array(),
		'last_completed'  => null,
	);
}

function symplectic_sched_get_state() {
	$state = get_option(SYMPLECTIC_SCHED_STATE_KEY, null);
	if (!is_array($state)) {
		return symplectic_sched_default_state();
	}
	return $state;
}

// ============================================================
// Job control
// ============================================================

function symplectic_sched_start_job($triggered_by = 'manual') {
	$state = symplectic_sched_get_state();

	if ($state['status'] === 'running') {
		return false;
	}

	$total = count(get_users(array(
		'meta_key' => 'personnel_id',
		'fields'   => 'ID',
		'number'   => -1,
	)));

	if ($total === 0) {
		return false;
	}

	$last_completed      = $state['last_completed'];
	$new_state           = symplectic_sched_default_state();
	$new_state['status']         = 'running';
	$new_state['triggered_by']   = $triggered_by;
	$new_state['started_at']     = time();
	$new_state['total_users']    = $total;
	$new_state['last_completed'] = $last_completed;
	update_option(SYMPLECTIC_SCHED_STATE_KEY, $new_state, false);

	wp_schedule_single_event(time(), SYMPLECTIC_SCHED_BATCH_HOOK);
	return true;
}

function symplectic_sched_run_batch() {
	$state = symplectic_sched_get_state();

	if ($state['status'] !== 'running') {
		return;
	}

	if (!defined('SYMPLECTIC_API_USERNAME') || !defined('SYMPLECTIC_API_PASSWORD') || !defined('CF_810_API_ENDPOINT_KEY')) {
		$state['status']       = 'error';
		$state['completed_at'] = time();
		$state['errors'][]     = 'Missing required constants: SYMPLECTIC_API_USERNAME, SYMPLECTIC_API_PASSWORD, or CF_810_API_ENDPOINT_KEY.';
		update_option(SYMPLECTIC_SCHED_STATE_KEY, $state, false);
		return;
	}

	$users = get_users(array(
		'meta_key' => 'personnel_id',
		'number'   => SYMPLECTIC_SCHED_BATCH_SIZE,
		'offset'   => $state['processed_users'],
		'orderby'  => 'ID',
		'order'    => 'ASC',
	));

	foreach ($users as $user) {
		$personnel_id = get_user_meta($user->ID, 'personnel_id', true);
		if (empty($personnel_id)) {
			$state['stats']['users_skipped']++;
			$state['processed_users']++;
			continue;
		}

		$result = symplectic_sched_import_single_user($user->ID, $personnel_id);
		$state['processed_users']++;

		if ($result['status'] === 'ok') {
			$state['stats']['users_ok']++;
		} elseif ($result['status'] === 'failed') {
			$state['stats']['users_failed']++;
		} else {
			$state['stats']['users_skipped']++;
		}

		$state['stats']['fields_written'] += $result['fields_written'];
		$state['stats']['fetch_errors']   += count($result['fetch_errors']);

		if (!empty($result['error_message']) && count($state['errors']) < SYMPLECTIC_SCHED_MAX_ERRORS) {
			$state['errors'][] = 'User ' . $user->ID . ' (pid:' . $personnel_id . '): ' . $result['error_message'];
		}
		foreach ($result['fetch_errors'] as $fe) {
			if (count($state['errors']) < SYMPLECTIC_SCHED_MAX_ERRORS) {
				$state['errors'][] = 'User ' . $user->ID . ' fetch: ' . $fe;
			}
		}
	}

	if ($state['processed_users'] < $state['total_users'] && !empty($users)) {
		update_option(SYMPLECTIC_SCHED_STATE_KEY, $state, false);
		wp_schedule_single_event(time() + 2, SYMPLECTIC_SCHED_BATCH_HOOK);
	} else {
		$state['status']       = 'complete';
		$state['completed_at'] = time();
		$state['last_completed'] = array(
			'started_at'      => $state['started_at'],
			'completed_at'    => $state['completed_at'],
			'triggered_by'    => $state['triggered_by'],
			'total_users'     => $state['total_users'],
			'processed_users' => $state['processed_users'],
			'stats'           => $state['stats'],
			'errors'          => $state['errors'],
		);
		update_option(SYMPLECTIC_SCHED_STATE_KEY, $state, false);
	}
}

// ============================================================
// Core per-user import
// ============================================================

function symplectic_sched_import_single_user($wp_user_id, $personnel_id) {
	$result = array(
		'status'         => 'failed',
		'fields_written' => 0,
		'fields_failed'  => 0,
		'fetch_errors'   => array(),
		'error_message'  => null,
	);

	$user_acf = 'user_' . $wp_user_id;

	// Step 2: Fetch UGA ID from CAES internal personnel API
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

	// Step 3: Fetch Elements user object
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

	$overview_nodes = $object->xpath('.//api:record/api:native/api:field[@name="overview"]/api:text');
	if (!empty($overview_nodes)) {
		$user_info['overview'] = (string)$overview_nodes[0];
	}

	$elements_user_id = isset($user_info['id']) ? (int)$user_info['id'] : null;

	// Step 4: Fetch relationships (publications, distinctions, courses)
	$publications        = array();
	$activities          = array();
	$teaching_activities = array();
	$fetch_errors        = array();

	if ($elements_user_id) {
		$rel_url    = 'https://uga.elements.symplectic.org:8091/secure-api/v6.13/users/' . $elements_user_id . '/relationships?per-page=100';
		$page_count = 0;
		$max_pages  = 10;
		$has_next   = false;

		do {
			$has_next = false;
			if (++$page_count > $max_pages) break;

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
					$obj_data = array();
					foreach ($rel_obj->attributes() as $k => $v) {
						$obj_data[$k] = (string)$v;
					}
					$category = isset($obj_data['category']) ? $obj_data['category'] : null;

					if ($category === 'publication' && isset($obj_data['href'])) {
						$pub_resp = wp_remote_get($obj_data['href'], $api_args);
						if (is_wp_error($pub_resp)) {
							$fetch_errors[] = 'Pub ' . $obj_data['id'] . ': ' . $pub_resp->get_error_message();
						} elseif (wp_remote_retrieve_response_code($pub_resp) !== 200) {
							$fetch_errors[] = 'Pub ' . $obj_data['id'] . ': HTTP ' . wp_remote_retrieve_response_code($pub_resp);
						} else {
							$pub_xml = simplexml_load_string(wp_remote_retrieve_body($pub_resp));
							if ($pub_xml === false) {
								$xml_errs = array_map(fn($e) => trim($e->message), libxml_get_errors());
								libxml_clear_errors();
								$fetch_errors[] = 'Pub ' . $obj_data['id'] . ' XML: ' . implode('; ', $xml_errs);
							} else {
								$obj_data = array_merge($obj_data, symplectic_import_extract_publication_fields($pub_xml));
							}
						}
						$publications[] = $obj_data;

					} elseif ($category === 'activity'
						&& isset($obj_data['href'])
						&& isset($obj_data['type'])
						&& $obj_data['type'] === 'distinction'
					) {
						$act_resp = wp_remote_get($obj_data['href'], $api_args);
						if (is_wp_error($act_resp)) {
							$fetch_errors[] = 'Activity ' . $obj_data['id'] . ': ' . $act_resp->get_error_message();
						} elseif (wp_remote_retrieve_response_code($act_resp) !== 200) {
							$fetch_errors[] = 'Activity ' . $obj_data['id'] . ': HTTP ' . wp_remote_retrieve_response_code($act_resp);
						} else {
							$act_xml = simplexml_load_string(wp_remote_retrieve_body($act_resp));
							if ($act_xml === false) {
								$xml_errs = array_map(fn($e) => trim($e->message), libxml_get_errors());
								libxml_clear_errors();
								$fetch_errors[] = 'Activity ' . $obj_data['id'] . ' XML: ' . implode('; ', $xml_errs);
							} else {
								$obj_data = array_merge($obj_data, symplectic_import_extract_activity_fields($act_xml));
							}
						}
						$activities[] = $obj_data;

					} elseif ($category === 'teaching-activity'
						&& isset($obj_data['href'])
						&& isset($obj_data['type'])
						&& $obj_data['type'] === 'course-taught'
					) {
						$ta_resp = wp_remote_get($obj_data['href'], $api_args);
						if (is_wp_error($ta_resp)) {
							$fetch_errors[] = 'Teaching ' . $obj_data['id'] . ': ' . $ta_resp->get_error_message();
						} elseif (wp_remote_retrieve_response_code($ta_resp) !== 200) {
							$fetch_errors[] = 'Teaching ' . $obj_data['id'] . ': HTTP ' . wp_remote_retrieve_response_code($ta_resp);
						} else {
							$ta_xml = simplexml_load_string(wp_remote_retrieve_body($ta_resp));
							if ($ta_xml === false) {
								$xml_errs = array_map(fn($e) => trim($e->message), libxml_get_errors());
								libxml_clear_errors();
								$fetch_errors[] = 'Teaching ' . $obj_data['id'] . ' XML: ' . implode('; ', $xml_errs);
							} else {
								$obj_data = array_merge($obj_data, symplectic_import_extract_teaching_activity_fields($ta_xml));
							}
						}
						if (isset($obj_data['term']) && symplectic_import_is_teaching_term_recent($obj_data['term'])) {
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

	// Step 5: Write to ACF fields
	foreach (array(
		'field_elements_user_id',
		'field_elements_overview',
		'field_elements_areas_of_expertise',
		'field_elements_scholarly_works',
		'field_elements_distinctions',
		'field_elements_courses_taught',
	) as $fk) {
		delete_field($fk, $user_acf);
	}

	$fields_written = 0;
	$fields_failed  = 0;

	$write_field = function($field_key, $value) use ($user_acf, &$fields_written, &$fields_failed) {
		if ($value === null || $value === '' || $value === array()) return;
		$r = update_field($field_key, $value, $user_acf);
		$r = ($r !== false) ? true : false;
		if (!$r) {
			$stored = get_field($field_key, $user_acf);
			$r = ($stored !== false && $stored !== null && $stored !== '');
		}
		if ($r) $fields_written++; else $fields_failed++;
	};

	$write_field('field_elements_user_id', $elements_user_id);
	$write_field('field_elements_overview', isset($user_info['overview']) ? $user_info['overview'] : null);

	// Areas of expertise — resolve/create taxonomy terms
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
			$write_field('field_elements_areas_of_expertise', $term_ids);
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
			'pub_citation_count' => isset($pub['citation-count'])  ? (int)$pub['citation-count'] : '',
		);
	}
	if (!empty($pub_rows)) {
		$r = update_field('field_elements_scholarly_works', $pub_rows, $user_acf);
		$r = ($r !== false) ? true : false;
		if (!$r) {
			$stored = get_field('field_elements_scholarly_works', $user_acf);
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
		$r = update_field('field_elements_distinctions', $dist_rows, $user_acf);
		$r = ($r !== false) ? true : false;
		if (!$r) {
			$stored = get_field('field_elements_distinctions', $user_acf);
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
		$r = update_field('field_elements_courses_taught', $course_rows, $user_acf);
		$r = ($r !== false) ? true : false;
		if (!$r) {
			$stored = get_field('field_elements_courses_taught', $user_acf);
			$r = !empty($stored);
		}
		if ($r) $fields_written++; else $fields_failed++;
	}

	$result['status']         = ($fields_failed === 0) ? 'ok' : ($fields_written > 0 ? 'ok' : 'failed');
	$result['fields_written'] = $fields_written;
	$result['fields_failed']  = $fields_failed;
	return $result;
}

// ============================================================
// Admin menu
// ============================================================

add_action('admin_menu', 'symplectic_sched_add_admin_page');

function symplectic_sched_add_admin_page() {
	add_submenu_page(
		'caes-tools',
		'Symplectic Elements Scheduled Import',
		'Scheduled Elements Import',
		'manage_options',
		'symplectic-scheduled-import',
		'symplectic_sched_render_page'
	);
}

// ============================================================
// Scripts and styles
// ============================================================

add_action('admin_enqueue_scripts', 'symplectic_sched_enqueue_scripts');

function symplectic_sched_enqueue_scripts($hook) {
	if ($hook !== 'caes-tools_page_symplectic-scheduled-import') {
		return;
	}

	wp_enqueue_style('wp-admin');
	wp_enqueue_script('jquery');

	wp_add_inline_style('wp-admin', '
		.sched-import-wrapper { max-width: 900px; margin: 20px 0; }
		.sched-panel { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 16px; margin-bottom: 20px; }
		.sched-panel h2 { margin-top: 0; padding-bottom: 8px; border-bottom: 1px solid #e5e5e5; font-size: 14px; text-transform: uppercase; letter-spacing: 0.03em; color: #555; }
		.sched-stat-grid { display: flex; gap: 24px; flex-wrap: wrap; margin: 12px 0; }
		.sched-stat { text-align: center; min-width: 60px; }
		.sched-stat-value { font-size: 28px; font-weight: 700; line-height: 1; }
		.sched-stat-label { font-size: 11px; color: #666; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.05em; }
		.sched-stat-value.ok      { color: #46b450; }
		.sched-stat-value.failed  { color: #dc3232; }
		.sched-stat-value.neutral { color: #0073aa; }
		.sched-progress-wrap { margin: 12px 0; }
		.sched-progress-bar { height: 12px; background: #e0e0e0; border-radius: 6px; overflow: hidden; }
		.sched-progress-fill { height: 100%; background: #0073aa; border-radius: 6px; transition: width 0.4s ease; }
		.sched-progress-label { font-size: 12px; color: #555; margin-top: 4px; }
		.sched-error-list { max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 11px; margin-top: 4px; }
		.sched-error-list div { padding: 3px 0; border-bottom: 1px solid #f5f5f5; color: #dc3232; }
		.sched-meta { font-size: 12px; color: #666; margin-top: 6px; }
		.sched-status-badge { display: inline-block; padding: 3px 10px; border-radius: 3px; font-size: 12px; font-weight: 600; }
		.sched-status-badge.idle     { background: #f0f0f0; color: #555; }
		.sched-status-badge.running  { background: #e5f0fa; color: #0073aa; }
		.sched-status-badge.complete { background: #ecf7ed; color: #46b450; }
		.sched-status-badge.error    { background: #fbeaea; color: #dc3232; }
		@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
		.dashicons.spin { animation: spin 1s linear infinite; display: inline-block; }
		#sched-single-result { margin-top: 16px; }
		.sched-form-group { margin-bottom: 16px; }
		.sched-form-group label { display: block; font-weight: 600; margin-bottom: 4px; }
	');

	wp_add_inline_script('jquery', '
		jQuery(function($) {
			var nonce = ' . json_encode(wp_create_nonce('symplectic_sched_nonce')) . ';
			var pollTimer = null;

			function esc(s) {
				if (s === null || s === undefined) return "";
				return String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;");
			}

			function fmtTs(ts) {
				if (!ts) return "\u2014";
				return new Date(ts * 1000).toLocaleString();
			}

			function fmtDuration(start, end) {
				if (!start || !end) return "";
				var secs = Math.round(end - start);
				if (secs < 60) return secs + "s";
				return Math.floor(secs / 60) + "m " + (secs % 60) + "s";
			}

			function renderStats(s) {
				return "<div class=\"sched-stat-grid\">"
					+ "<div class=\"sched-stat\"><div class=\"sched-stat-value ok\">"      + esc(s.users_ok)       + "</div><div class=\"sched-stat-label\">Users OK</div></div>"
					+ "<div class=\"sched-stat\"><div class=\"sched-stat-value failed\">"  + esc(s.users_failed)   + "</div><div class=\"sched-stat-label\">Failed</div></div>"
					+ "<div class=\"sched-stat\"><div class=\"sched-stat-value neutral\">" + esc(s.users_skipped)  + "</div><div class=\"sched-stat-label\">Skipped</div></div>"
					+ "<div class=\"sched-stat\"><div class=\"sched-stat-value neutral\">" + esc(s.fields_written) + "</div><div class=\"sched-stat-label\">Fields Written</div></div>"
					+ "<div class=\"sched-stat\"><div class=\"sched-stat-value" + (s.fetch_errors > 0 ? " failed" : "") + "\">" + esc(s.fetch_errors) + "</div><div class=\"sched-stat-label\">Fetch Errors</div></div>"
					+ "</div>";
			}

			function renderErrors(errors) {
				if (!errors || !errors.length) return "";
				var html = "<div style=\"margin-top:10px\"><strong style=\"font-size:12px\">Errors (" + esc(errors.length) + "):</strong><div class=\"sched-error-list\">";
				errors.forEach(function(e) { html += "<div>" + esc(e) + "</div>"; });
				return html + "</div></div>";
			}

			function renderCurrentPanel(state) {
				var sc = state.status;
				var badge = sc.charAt(0).toUpperCase() + sc.slice(1);
				if (sc === "running") {
					badge = "<span class=\"dashicons dashicons-update spin\" style=\"font-size:14px;vertical-align:middle\"></span> Running";
				}
				var html = "<div style=\"margin-bottom:12px\">";
				html += "<span class=\"sched-status-badge " + esc(sc) + "\">" + badge + "</span>";
				if (state.triggered_by) html += "&ensp;<span class=\"sched-meta\" style=\"display:inline\">Triggered by: " + esc(state.triggered_by) + "</span>";
				html += "</div>";

				if (sc === "running" || sc === "complete" || sc === "error") {
					var pct = state.total_users > 0 ? Math.round(state.processed_users / state.total_users * 100) : 0;
					html += "<div class=\"sched-progress-wrap\">";
					html += "<div class=\"sched-progress-bar\"><div class=\"sched-progress-fill\" style=\"width:" + pct + "%\"></div></div>";
					html += "<div class=\"sched-progress-label\">" + esc(state.processed_users) + " / " + esc(state.total_users) + " users (" + pct + "%)</div>";
					html += "</div>";
					html += renderStats(state.stats);
					html += "<div class=\"sched-meta\">Started: " + fmtTs(state.started_at);
					if (state.completed_at) {
						html += "&ensp;&mdash;&ensp;Completed: " + fmtTs(state.completed_at);
						html += "&ensp;&mdash;&ensp;Duration: " + fmtDuration(state.started_at, state.completed_at);
					}
					html += "</div>";
					html += renderErrors(state.errors);
				} else {
					html += "<p style=\"color:#666;font-size:13px\">No import is currently running.</p>";
				}
				$("#sched-current-panel").html(html);
			}

			function renderLastCompletedPanel(lc) {
				if (!lc) {
					$("#sched-last-completed-section").hide();
					return;
				}
				$("#sched-last-completed-section").show();
				var html = "<div class=\"sched-meta\">Started: " + fmtTs(lc.started_at);
				html += "&ensp;&mdash;&ensp;Completed: " + fmtTs(lc.completed_at);
				if (lc.started_at && lc.completed_at) html += "&ensp;&mdash;&ensp;Duration: " + fmtDuration(lc.started_at, lc.completed_at);
				if (lc.triggered_by) html += "&ensp;&mdash;&ensp;Triggered by: " + esc(lc.triggered_by);
				html += "</div>";
				html += renderStats(lc.stats);
				html += renderErrors(lc.errors);
				$("#sched-last-completed-panel").html(html);
			}

			function pollStatus() {
				$.ajax({
					url: ajaxurl,
					method: "POST",
					data: { action: "symplectic_sched_status", nonce: nonce },
					success: function(response) {
						if (!response.success) return;
						var state = response.data.state;
						renderCurrentPanel(state);
						renderLastCompletedPanel(state.last_completed);
						if (state.status === "running") {
							$("#sched-trigger-btn").prop("disabled", true).val("Import Running\u2026");
							pollTimer = setTimeout(pollStatus, 3000);
						} else {
							clearTimeout(pollTimer);
							pollTimer = null;
							$("#sched-trigger-btn").prop("disabled", false).val("Trigger Import for All Users");
						}
					}
				});
			}

			// Trigger full import for all users
			$("#sched-trigger-btn").on("click", function() {
				if (!confirm("This will begin a full import of all WordPress users from Elements. Continue?")) return;
				$(this).prop("disabled", true).val("Starting\u2026");
				$.ajax({
					url: ajaxurl,
					method: "POST",
					data: { action: "symplectic_sched_trigger", nonce: nonce },
					success: function(response) {
						if (response.success) {
							setTimeout(pollStatus, 1000);
						} else {
							var msg = (response.data && response.data.error_message) ? response.data.error_message : "Unknown error";
							alert("Failed to start import: " + msg);
							$("#sched-trigger-btn").prop("disabled", false).val("Trigger Import for All Users");
						}
					},
					error: function() {
						alert("AJAX error starting import.");
						$("#sched-trigger-btn").prop("disabled", false).val("Trigger Import for All Users");
					}
				});
			});

			// Single-user import
			$("#sched-single-form").on("submit", function(e) {
				e.preventDefault();
				var pid = $("#sched-single-pid").val().trim();
				if (!pid) { alert("Please enter a Personnel ID."); return; }
				var $btn = $(this).find("input[type=submit]");
				$btn.prop("disabled", true).val("Importing\u2026");
				$("#sched-single-result").html("<p><span class=\"dashicons dashicons-update spin\"></span> Importing\u2026</p>");
				$.ajax({
					url: ajaxurl,
					method: "POST",
					timeout: 300000,
					data: { action: "symplectic_sched_import_single", nonce: nonce, personnel_id: pid },
					success: function(response) {
						$btn.prop("disabled", false).val("Import Single User");
						if (response.success) {
							var d = response.data;
							var sc = d.status === "ok" ? "#46b450" : (d.status === "failed" ? "#dc3232" : "#999");
							var html = "<div style=\"padding:12px;background:#fff;border:1px solid #ccd0d4;border-radius:4px\">";
							html += "<strong style=\"color:" + sc + "\">" + esc(d.status.toUpperCase()) + "</strong>&ensp;";
							html += esc(d.display_name) + " (WP&nbsp;" + esc(d.wp_user_id) + ")";
							html += "<span style=\"color:#666;font-size:12px\">&ensp;&mdash;&ensp;" + esc(d.fields_written) + " fields written";
							if (d.fields_failed) html += ", " + esc(d.fields_failed) + " failed";
							html += "</span>";
							if (d.error_message) html += "<div style=\"color:#dc3232;margin-top:6px;font-size:12px\">" + esc(d.error_message) + "</div>";
							if (d.fetch_errors && d.fetch_errors.length) {
								html += "<div style=\"margin-top:6px;font-size:11px;font-family:monospace;color:#dc3232\">";
								d.fetch_errors.forEach(function(fe) { html += esc(fe) + "<br>"; });
								html += "</div>";
							}
							html += "</div>";
							$("#sched-single-result").html(html);
						} else {
							var msg = (response.data && response.data.error_message) ? response.data.error_message : JSON.stringify(response.data);
							$("#sched-single-result").html("<div class=\"notice notice-error\"><p>" + esc(msg) + "</p></div>");
						}
					},
					error: function(xhr, status, error) {
						$btn.prop("disabled", false).val("Import Single User");
						$("#sched-single-result").html("<div class=\"notice notice-error\"><p>AJAX error: " + esc(error) + "</p></div>");
					}
				});
			});

			// Load initial state on page load
			pollStatus();
		});
	');
}

// ============================================================
// Admin page render
// ============================================================

function symplectic_sched_render_page() {
	$credentials_ok = defined('SYMPLECTIC_API_USERNAME') && defined('SYMPLECTIC_API_PASSWORD') && defined('CF_810_API_ENDPOINT_KEY');
	$next_cron      = wp_next_scheduled(SYMPLECTIC_SCHED_CRON_HOOK);
	?>
	<div class="wrap">
		<h1>Symplectic Elements Scheduled Import</h1>
		<p>Runs daily at midnight. Imports Elements data for every WordPress user who has a personnel ID. Use the controls below to trigger a run manually or import a single user.</p>

		<?php if (!$credentials_ok): ?>
		<div class="notice notice-error">
			<p><strong>Configuration Required:</strong> <code>SYMPLECTIC_API_USERNAME</code>, <code>SYMPLECTIC_API_PASSWORD</code>, and/or <code>CF_810_API_ENDPOINT_KEY</code> are not defined in wp-config.php.</p>
		</div>
		<?php endif; ?>

		<div class="sched-import-wrapper">

			<div class="sched-panel">
				<h2>Current Job Status</h2>
				<div id="sched-current-panel"><p style="color:#999">Loading&hellip;</p></div>
			</div>

			<div class="sched-panel" id="sched-last-completed-section" style="display:none">
				<h2>Last Completed Run</h2>
				<div id="sched-last-completed-panel"></div>
			</div>

			<div class="sched-panel">
				<h2>Manual Controls</h2>
				<p>
					<input type="button" id="sched-trigger-btn" class="button button-primary"
						value="Trigger Import for All Users"
						<?php echo ($credentials_ok) ? '' : 'disabled'; ?>>
				</p>
				<p class="description">
					Starts a fresh import for all WordPress users. Runs in the background via WP-Cron batching
					(<?php echo SYMPLECTIC_SCHED_BATCH_SIZE; ?> users per batch).
				</p>
				<?php if ($next_cron): ?>
				<p class="description">Next scheduled automatic run: <strong><?php echo esc_html(date('Y-m-d H:i:s', $next_cron)); ?></strong></p>
				<?php endif; ?>
			</div>

			<div class="sched-panel">
				<h2>Single-User Import</h2>
				<form id="sched-single-form">
					<div class="sched-form-group">
						<label for="sched-single-pid">Personnel ID</label>
						<input type="text" id="sched-single-pid" placeholder="e.g. 3885" style="width:200px">
						<p class="description">Runs immediately and reports results inline. Does not affect a running batch job.</p>
					</div>
					<input type="submit" class="button button-secondary" value="Import Single User"
						<?php echo $credentials_ok ? '' : 'disabled'; ?>>
				</form>
				<div id="sched-single-result"></div>
			</div>

		</div>
	</div>
	<?php
}

// ============================================================
// AJAX handlers
// ============================================================

add_action('wp_ajax_symplectic_sched_status',        'symplectic_sched_ajax_status');
add_action('wp_ajax_symplectic_sched_trigger',       'symplectic_sched_ajax_trigger');
add_action('wp_ajax_symplectic_sched_import_single', 'symplectic_sched_ajax_import_single');

function symplectic_sched_ajax_status() {
	if (!wp_verify_nonce($_POST['nonce'], 'symplectic_sched_nonce')) {
		wp_send_json_error(array('error_message' => 'Security check failed.'));
	}
	if (!current_user_can('manage_options')) {
		wp_send_json_error(array('error_message' => 'Insufficient permissions.'));
	}
	wp_send_json_success(array('state' => symplectic_sched_get_state()));
}

function symplectic_sched_ajax_trigger() {
	if (!wp_verify_nonce($_POST['nonce'], 'symplectic_sched_nonce')) {
		wp_send_json_error(array('error_message' => 'Security check failed.'));
	}
	if (!current_user_can('manage_options')) {
		wp_send_json_error(array('error_message' => 'Insufficient permissions.'));
	}
	if (!defined('SYMPLECTIC_API_USERNAME') || !defined('SYMPLECTIC_API_PASSWORD') || !defined('CF_810_API_ENDPOINT_KEY')) {
		wp_send_json_error(array('error_message' => 'API credentials not configured in wp-config.php.'));
	}

	$started = symplectic_sched_start_job('manual');
	if (!$started) {
		$state = symplectic_sched_get_state();
		if ($state['status'] === 'running') {
			wp_send_json_error(array('error_message' => 'An import is already running.'));
		} else {
			wp_send_json_error(array('error_message' => 'No WordPress users found with a personnel_id.'));
		}
	}
	wp_send_json_success(array('message' => 'Import queued.'));
}

function symplectic_sched_ajax_import_single() {
	if (!wp_verify_nonce($_POST['nonce'], 'symplectic_sched_nonce')) {
		wp_send_json_error(array('error_message' => 'Security check failed.'));
	}
	if (!current_user_can('manage_options')) {
		wp_send_json_error(array('error_message' => 'Insufficient permissions.'));
	}
	if (!defined('SYMPLECTIC_API_USERNAME') || !defined('SYMPLECTIC_API_PASSWORD') || !defined('CF_810_API_ENDPOINT_KEY')) {
		wp_send_json_error(array('error_message' => 'API credentials not configured in wp-config.php.'));
	}

	$personnel_id = isset($_POST['personnel_id']) ? sanitize_text_field($_POST['personnel_id']) : '';
	if (empty($personnel_id)) {
		wp_send_json_error(array('error_message' => 'Personnel ID is required.'));
	}

	$wp_users = get_users(array('meta_key' => 'personnel_id', 'meta_value' => $personnel_id, 'number' => 1));
	if (empty($wp_users)) {
		wp_send_json_error(array('error_message' => 'No WordPress user found with personnel_id = ' . $personnel_id));
	}

	$wp_user = $wp_users[0];
	@set_time_limit(300);
	$result = symplectic_sched_import_single_user($wp_user->ID, $personnel_id);

	wp_send_json_success(array(
		'status'         => $result['status'],
		'wp_user_id'     => $wp_user->ID,
		'display_name'   => $wp_user->display_name,
		'fields_written' => $result['fields_written'],
		'fields_failed'  => $result['fields_failed'],
		'fetch_errors'   => $result['fetch_errors'],
		'error_message'  => $result['error_message'],
	));
}
