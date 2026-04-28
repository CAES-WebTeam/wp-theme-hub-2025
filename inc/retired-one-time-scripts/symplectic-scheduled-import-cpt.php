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
