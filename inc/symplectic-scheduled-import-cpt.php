<?php
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

add_action('init', 'symplectic_cpt_maybe_schedule');

function symplectic_cpt_maybe_schedule() {
	if (!wp_next_scheduled(SYMPLECTIC_CPT_CRON_HOOK)) {
		wp_schedule_event(strtotime('tomorrow midnight'), 'daily', SYMPLECTIC_CPT_CRON_HOOK);
	}
}

add_action(SYMPLECTIC_CPT_CRON_HOOK,  'symplectic_cpt_daily_trigger');
add_action(SYMPLECTIC_CPT_BATCH_HOOK, 'symplectic_cpt_run_batch');

function symplectic_cpt_daily_trigger() {
	symplectic_cpt_start_job('cron');
}

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
		'meta_key'       => 'personnel_id',
		'meta_compare'   => '!=',
		'meta_value'     => '',
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
		'meta_key'       => 'personnel_id',
		'meta_compare'   => '!=',
		'meta_value'     => '',
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

function symplectic_cpt_import_single_post($post_id, $personnel_id, $deadline = 0) {
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

	$overview_nodes = $object->xpath('.//api:record/api:native/api:field[@name="overview"]/api:text');
	if (!empty($overview_nodes)) {
		$user_info['overview'] = (string)$overview_nodes[0];
	}

	$elements_user_id = isset($user_info['id']) ? (int)$user_info['id'] : null;

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

	// Write to ACF fields on the CPT post
	// Clear existing fields first
	foreach (array(
		'field_person_cpt_elements_user_id',
		'field_person_cpt_elements_overview',
		'field_person_cpt_elements_areas_of_expertise',
		'field_person_cpt_elements_scholarly_works',
		'field_person_cpt_elements_distinctions',
		'field_person_cpt_elements_courses_taught',
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

	$result['status']         = ($fields_failed === 0) ? 'ok' : ($fields_written > 0 ? 'ok' : 'failed');
	$result['fields_written'] = $fields_written;
	$result['fields_failed']  = $fields_failed;
	return $result;
}

// ============================================================
// Admin menu
// ============================================================

add_action('admin_menu', 'symplectic_cpt_add_admin_page');

function symplectic_cpt_add_admin_page() {
	add_submenu_page(
		'caes-tools',
		'Symplectic Elements Import (People CPT)',
		'Elements Import (CPT)',
		'manage_options',
		'symplectic-cpt-import',
		'symplectic_cpt_render_page'
	);
}

// ============================================================
// Scripts and styles
// ============================================================

add_action('admin_enqueue_scripts', 'symplectic_cpt_enqueue_scripts');

function symplectic_cpt_enqueue_scripts($hook) {
	if ($hook !== 'caes-tools_page_symplectic-cpt-import') {
		return;
	}

	wp_enqueue_style('wp-admin');
	wp_enqueue_script('jquery');

	wp_add_inline_style('wp-admin', '
		.scpt-wrapper { max-width: 900px; margin: 20px 0; }
		.scpt-panel { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 16px; margin-bottom: 20px; }
		.scpt-panel h2 { margin-top: 0; padding-bottom: 8px; border-bottom: 1px solid #e5e5e5; font-size: 14px; text-transform: uppercase; letter-spacing: 0.03em; color: #555; }
		.scpt-stat-grid { display: flex; gap: 24px; flex-wrap: wrap; margin: 12px 0; }
		.scpt-stat { text-align: center; min-width: 60px; }
		.scpt-stat-value { font-size: 28px; font-weight: 700; line-height: 1; }
		.scpt-stat-label { font-size: 11px; color: #666; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.05em; }
		.scpt-stat-value.ok      { color: #46b450; }
		.scpt-stat-value.failed  { color: #dc3232; }
		.scpt-stat-value.neutral { color: #0073aa; }
		.scpt-progress-wrap { margin: 12px 0; }
		.scpt-progress-bar { height: 12px; background: #e0e0e0; border-radius: 6px; overflow: hidden; }
		.scpt-progress-fill { height: 100%; background: #0073aa; border-radius: 6px; transition: width 0.4s ease; }
		.scpt-progress-label { font-size: 12px; color: #555; margin-top: 4px; }
		.scpt-error-list { max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 11px; margin-top: 4px; }
		.scpt-error-list div { padding: 3px 0; border-bottom: 1px solid #f5f5f5; color: #dc3232; }
		.scpt-meta { font-size: 12px; color: #666; margin-top: 6px; }
		.scpt-status-badge { display: inline-block; padding: 3px 10px; border-radius: 3px; font-size: 12px; font-weight: 600; }
		.scpt-status-badge.idle     { background: #f0f0f0; color: #555; }
		.scpt-status-badge.running  { background: #e5f0fa; color: #0073aa; }
		.scpt-status-badge.complete { background: #ecf7ed; color: #46b450; }
		.scpt-status-badge.error    { background: #fbeaea; color: #dc3232; }
		.scpt-status-badge.stopped  { background: #fff8e5; color: #9a5e00; }
		@keyframes scpt-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
		.dashicons.scpt-spin { animation: scpt-spin 1s linear infinite; display: inline-block; }
		#scpt-single-result { margin-top: 16px; }
		.scpt-form-group { margin-bottom: 16px; }
		.scpt-form-group label { display: block; font-weight: 600; margin-bottom: 4px; }
	');

	wp_add_inline_script('jquery', '
		jQuery(function($) {
			var nonce = ' . json_encode(wp_create_nonce('symplectic_cpt_nonce')) . ';
			var pollTimer = null;

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

			function renderStats(s) {
				return "<div class=\"scpt-stat-grid\">"
					+ "<div class=\"scpt-stat\"><div class=\"scpt-stat-value ok\">"      + esc(s.posts_ok)       + "</div><div class=\"scpt-stat-label\">Posts OK</div></div>"
					+ "<div class=\"scpt-stat\"><div class=\"scpt-stat-value failed\">"  + esc(s.posts_failed)   + "</div><div class=\"scpt-stat-label\">Failed</div></div>"
					+ "<div class=\"scpt-stat\"><div class=\"scpt-stat-value neutral\">" + esc(s.posts_skipped)  + "</div><div class=\"scpt-stat-label\">Skipped</div></div>"
					+ "<div class=\"scpt-stat\"><div class=\"scpt-stat-value neutral\">" + esc(s.fields_written) + "</div><div class=\"scpt-stat-label\">Fields Written</div></div>"
					+ "<div class=\"scpt-stat\"><div class=\"scpt-stat-value" + (s.fetch_errors > 0 ? " failed" : "") + "\">" + esc(s.fetch_errors) + "</div><div class=\"scpt-stat-label\">Fetch Errors</div></div>"
					+ "</div>";
			}

			function renderErrors(errors) {
				if (!errors || !errors.length) return "";
				var html = "<div style=\"margin-top:10px\"><strong style=\"font-size:12px\">Errors (" + esc(errors.length) + "):</strong><div class=\"scpt-error-list\">";
				errors.forEach(function(e) { html += "<div>" + esc(e) + "</div>"; });
				return html + "</div></div>";
			}

			function renderCurrentPanel(state) {
				var sc = state.status;
				var badge = sc.charAt(0).toUpperCase() + sc.slice(1);
				if (sc === "running") {
					badge = "<span class=\"dashicons dashicons-update scpt-spin\" style=\"font-size:14px;vertical-align:middle\"></span> Running";
				} else if (sc === "stopped") {
					badge = "Stopped";
				}
				var html = "<div style=\"margin-bottom:12px\">";
				html += "<span class=\"scpt-status-badge " + esc(sc) + "\">" + badge + "</span>";
				if (state.triggered_by) html += "&ensp;<span class=\"scpt-meta\" style=\"display:inline\">Triggered by: " + esc(state.triggered_by) + "</span>";
				html += "</div>";

				if (sc === "running" || sc === "complete" || sc === "error" || sc === "stopped") {
					var pct = state.total_posts > 0 ? Math.round(state.processed_posts / state.total_posts * 100) : 0;
					html += "<div class=\"scpt-progress-wrap\">";
					html += "<div class=\"scpt-progress-bar\"><div class=\"scpt-progress-fill\" style=\"width:" + pct + "%\"></div></div>";
					html += "<div class=\"scpt-progress-label\">" + esc(state.processed_posts) + " / " + esc(state.total_posts) + " person posts (" + pct + "%)</div>";
					html += "</div>";
					html += renderStats(state.stats);
					html += "<div class=\"scpt-meta\">Started: " + fmtTs(state.started_at);
					if (state.completed_at) {
						html += " --- Completed: " + fmtTs(state.completed_at);
						html += " --- Duration: " + fmtDuration(state.started_at, state.completed_at);
					}
					html += "</div>";
					html += renderErrors(state.errors);
				} else {
					html += "<p style=\"color:#666;font-size:13px\">No import is currently running.</p>";
				}
				$("#scpt-current-panel").html(html);
			}

			function renderLastCompletedPanel(lc) {
				if (!lc) {
					$("#scpt-last-completed-section").hide();
					return;
				}
				$("#scpt-last-completed-section").show();
				var html = "<div class=\"scpt-meta\">Started: " + fmtTs(lc.started_at);
				html += " --- Completed: " + fmtTs(lc.completed_at);
				if (lc.started_at && lc.completed_at) html += " --- Duration: " + fmtDuration(lc.started_at, lc.completed_at);
				if (lc.triggered_by) html += " --- Triggered by: " + esc(lc.triggered_by);
				html += "</div>";
				html += renderStats(lc.stats);
				html += renderErrors(lc.errors);
				$("#scpt-last-completed-panel").html(html);
			}

			function pollStatus() {
				$.ajax({
					url: ajaxurl,
					method: "POST",
					data: { action: "symplectic_cpt_status", nonce: nonce },
					success: function(response) {
						if (!response.success) return;
						var state = response.data.state;
						renderCurrentPanel(state);
						renderLastCompletedPanel(state.last_completed);
						if (state.status === "running") {
							$("#scpt-trigger-btn").prop("disabled", true).hide();
							$("#scpt-stop-btn").show().prop("disabled", false).val("Stop Import");
							$("#scpt-resume-btn").hide();
							pollTimer = setTimeout(pollStatus, 3000);
						} else {
							clearTimeout(pollTimer);
							pollTimer = null;
							$("#scpt-trigger-btn").prop("disabled", false).show().val("Trigger Import for All People");
							$("#scpt-stop-btn").hide();
							var canResume = state.status === "stopped"
								&& state.processed_posts > 0
								&& state.processed_posts < state.total_posts;
							if (canResume) {
								$("#scpt-resume-btn").show().prop("disabled", false)
									.val("Resume Import (" + esc(state.processed_posts) + " / " + esc(state.total_posts) + " done)");
							} else {
								$("#scpt-resume-btn").hide();
							}
						}
					}
				});
			}

			// Trigger full import
			$("#scpt-trigger-btn").on("click", function() {
				if (!confirm("This will begin a full Elements import for all person CPT posts with a personnel ID. Continue?")) return;
				$(this).prop("disabled", true).val("Starting...");
				$.ajax({
					url: ajaxurl,
					method: "POST",
					data: { action: "symplectic_cpt_trigger", nonce: nonce },
					success: function(response) {
						if (response.success) {
							setTimeout(pollStatus, 1000);
						} else {
							var msg = (response.data && response.data.error_message) ? response.data.error_message : "Unknown error";
							alert("Failed to start import: " + msg);
							$("#scpt-trigger-btn").prop("disabled", false).val("Trigger Import for All People");
						}
					},
					error: function() {
						alert("AJAX error starting import.");
						$("#scpt-trigger-btn").prop("disabled", false).val("Trigger Import for All People");
					}
				});
			});

			// Stop import
			$("#scpt-stop-btn").on("click", function() {
				if (!confirm("Stop the import? Progress will be saved and you can resume later.")) return;
				$(this).prop("disabled", true).val("Stopping...");
				$.ajax({
					url: ajaxurl,
					method: "POST",
					data: { action: "symplectic_cpt_stop", nonce: nonce },
					success: function(response) {
						pollStatus();
					},
					error: function() {
						alert("AJAX error stopping import.");
						$("#scpt-stop-btn").prop("disabled", false).val("Stop Import");
					}
				});
			});

			// Resume import
			$("#scpt-resume-btn").on("click", function() {
				$(this).prop("disabled", true).val("Resuming...");
				$.ajax({
					url: ajaxurl,
					method: "POST",
					data: { action: "symplectic_cpt_resume", nonce: nonce },
					success: function(response) {
						if (response.success) {
							setTimeout(pollStatus, 1000);
						} else {
							var msg = (response.data && response.data.error_message) ? response.data.error_message : "Unknown error";
							alert("Failed to resume: " + msg);
							$("#scpt-resume-btn").prop("disabled", false);
						}
					},
					error: function() {
						alert("AJAX error resuming import.");
						$("#scpt-resume-btn").prop("disabled", false);
					}
				});
			});

			// Single-post import
			$("#scpt-single-form").on("submit", function(e) {
				e.preventDefault();
				var pid = $("#scpt-single-pid").val().trim();
				if (!pid) { alert("Please enter a Personnel ID."); return; }
				var $btn = $(this).find("input[type=submit]");
				$btn.prop("disabled", true).val("Importing...");
				$("#scpt-single-result").html("<p><span class=\"dashicons dashicons-update scpt-spin\"></span> Importing...</p>");
				$.ajax({
					url: ajaxurl,
					method: "POST",
					timeout: 300000,
					data: { action: "symplectic_cpt_import_single", nonce: nonce, personnel_id: pid },
					success: function(response) {
						$btn.prop("disabled", false).val("Import Single Person");
						if (response.success) {
							var d = response.data;
							var sc = d.status === "ok" ? "#46b450" : (d.status === "failed" ? "#dc3232" : "#999");
							var html = "<div style=\"padding:12px;background:#fff;border:1px solid #ccd0d4;border-radius:4px\">";
							html += "<strong style=\"color:" + sc + "\">" + esc(d.status.toUpperCase()) + "</strong>&ensp;";
							html += esc(d.post_title) + " (Post " + esc(d.post_id) + ")";
							html += "<span style=\"color:#666;font-size:12px\"> --- " + esc(d.fields_written) + " fields written";
							if (d.fields_failed) html += ", " + esc(d.fields_failed) + " failed";
							html += "</span>";
							if (d.error_message) html += "<div style=\"color:#dc3232;margin-top:6px;font-size:12px\">" + esc(d.error_message) + "</div>";
							if (d.fetch_errors && d.fetch_errors.length) {
								html += "<div style=\"margin-top:6px;font-size:11px;font-family:monospace;color:#dc3232\">";
								d.fetch_errors.forEach(function(fe) { html += esc(fe) + "<br>"; });
								html += "</div>";
							}
							html += "</div>";
							$("#scpt-single-result").html(html);
						} else {
							var msg = (response.data && response.data.error_message) ? response.data.error_message : JSON.stringify(response.data);
							$("#scpt-single-result").html("<div class=\"notice notice-error\"><p>" + esc(msg) + "</p></div>");
						}
					},
					error: function(xhr, status, error) {
						$btn.prop("disabled", false).val("Import Single Person");
						$("#scpt-single-result").html("<div class=\"notice notice-error\"><p>AJAX error: " + esc(error) + "</p></div>");
					}
				});
			});

			// Load initial state
			pollStatus();
		});
	');
}

// ============================================================
// Admin page render
// ============================================================

function symplectic_cpt_render_page() {
	$credentials_ok = defined('SYMPLECTIC_API_USERNAME') && defined('SYMPLECTIC_API_PASSWORD') && defined('CF_810_API_ENDPOINT_KEY');
	$next_cron      = wp_next_scheduled(SYMPLECTIC_CPT_CRON_HOOK);
	$total_posts    = symplectic_cpt_count_person_posts();
	?>
	<div class="wrap">
		<h1>Symplectic Elements Import (People CPT)</h1>
		<p>Runs daily at midnight. Imports Elements data for every <code>caes_hub_person</code> post that has a personnel ID.
		   Use the controls below to trigger a run manually or import a single person.</p>
		<p class="description">Person posts with personnel ID: <strong><?php echo esc_html($total_posts); ?></strong></p>

		<?php if (!$credentials_ok): ?>
		<div class="notice notice-error">
			<p><strong>Configuration Required:</strong> <code>SYMPLECTIC_API_USERNAME</code>, <code>SYMPLECTIC_API_PASSWORD</code>, and/or <code>CF_810_API_ENDPOINT_KEY</code> are not defined in wp-config.php.</p>
		</div>
		<?php endif; ?>

		<div class="scpt-wrapper">

			<div class="scpt-panel">
				<h2>Current Job Status</h2>
				<div id="scpt-current-panel"><p style="color:#999">Loading...</p></div>
			</div>

			<div class="scpt-panel" id="scpt-last-completed-section" style="display:none">
				<h2>Last Completed Run</h2>
				<div id="scpt-last-completed-panel"></div>
			</div>

			<div class="scpt-panel">
				<h2>Manual Controls</h2>
				<p>
					<input type="button" id="scpt-trigger-btn" class="button button-primary"
						value="Trigger Import for All People"
						<?php echo ($credentials_ok) ? '' : 'disabled'; ?>>
					<input type="button" id="scpt-stop-btn" class="button" value="Stop Import"
						style="display:none;margin-left:6px">
					<input type="button" id="scpt-resume-btn" class="button button-secondary" value="Resume Import"
						style="display:none;margin-left:6px">
				</p>
				<p class="description">
					Starts a fresh import for all person posts. Runs in the background via WP-Cron batching
					(<?php echo SYMPLECTIC_CPT_BATCH_SIZE; ?> posts per batch).
				</p>
				<?php if ($next_cron): ?>
				<p class="description">Next scheduled automatic run: <strong><?php echo esc_html(date('Y-m-d H:i:s', $next_cron)); ?></strong></p>
				<?php endif; ?>
			</div>

			<div class="scpt-panel">
				<h2>Single-Person Import</h2>
				<form id="scpt-single-form">
					<div class="scpt-form-group">
						<label for="scpt-single-pid">Personnel ID</label>
						<input type="text" id="scpt-single-pid" placeholder="e.g. 3885" style="width:200px">
						<p class="description">Finds the matching <code>caes_hub_person</code> post by personnel_id meta, then imports Elements data. Does not affect a running batch job.</p>
					</div>
					<input type="submit" class="button button-secondary" value="Import Single Person"
						<?php echo $credentials_ok ? '' : 'disabled'; ?>>
				</form>
				<div id="scpt-single-result"></div>
			</div>

		</div>
	</div>
	<?php
}

// ============================================================
// AJAX handlers
// ============================================================

add_action('wp_ajax_symplectic_cpt_status',        'symplectic_cpt_ajax_status');
add_action('wp_ajax_symplectic_cpt_trigger',       'symplectic_cpt_ajax_trigger');
add_action('wp_ajax_symplectic_cpt_stop',          'symplectic_cpt_ajax_stop');
add_action('wp_ajax_symplectic_cpt_resume',        'symplectic_cpt_ajax_resume');
add_action('wp_ajax_symplectic_cpt_import_single', 'symplectic_cpt_ajax_import_single');

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

	// Find the caes_hub_person post by personnel_id meta
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
	$result = symplectic_cpt_import_single_post($post->ID, $personnel_id);

	wp_send_json_success(array(
		'status'         => $result['status'],
		'post_id'        => $post->ID,
		'post_title'     => $post->post_title,
		'fields_written' => $result['fields_written'],
		'fields_failed'  => $result['fields_failed'],
		'fetch_errors'   => $result['fetch_errors'],
		'error_message'  => $result['error_message'],
	));
}

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
				$y = (string)$d[0]->year; $m = (string)$d[0]->month; $day = (string)$d[0]->day;
				if ($y) { $val = $y; if ($m) { $val = $m . '/' . $val; if ($day) $val = $day . '/' . $val; } }
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
				$y = (string)$d[0]->year; $m = (string)$d[0]->month; $day = (string)$d[0]->day;
				if ($y) { $val = $y; if ($m) { $val = $m . '/' . $val; if ($day) $val = $day . '/' . $val; } }
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
