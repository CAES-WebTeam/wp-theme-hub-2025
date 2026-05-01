<?php
/**
 * Person CPT Migration Tool
 *
 * Admin tool under CAES Tools that migrates personnel_user, expert_user,
 * WordPress users to caes_hub_person CPT posts. Content managers are not
 * migrated separately since they already exist as personnel users; instead,
 * their WP accounts are linked to existing person posts after migration.
 *
 * Follows the async batch architecture of symplectic-scheduled-import.php:
 * - State persisted in a WP option
 * - WP-Cron batching via wp_schedule_single_event
 * - Stop/resume support
 * - Single-user and bulk modes
 * - Dry-run toggle
 */

define('PERSON_MIGRATION_BATCH_SIZE', 50);
define('PERSON_MIGRATION_MAX_ERRORS', 500);
define('PERSON_MIGRATION_STATE_KEY',  'person_cpt_migration_state');
define('PERSON_MIGRATION_BATCH_HOOK', 'person_cpt_migration_batch');
define('PERSON_MIGRATION_MAP_KEY',    'person_cpt_migration_user_post_map');
define('PERSON_MIGRATION_CHECKLIST_KEY', 'person_cpt_migration_checklist');

// ============================================================
// State management
// ============================================================

function person_migration_default_state() {
	return array(
		'status'          => 'idle',
		'mode'            => null, // 'migrate' | 'swap_repeaters' | 'repopulate_flat_meta' | 'revert_flat_meta'
		'dry_run'         => false,
		'started_at'      => null,
		'completed_at'    => null,
		'total_users'     => 0,
		'processed_users' => 0,
		'stats'           => array(
			'users_ok'       => 0,
			'users_failed'   => 0,
			'users_skipped'  => 0,
			'fields_written' => 0,
			'posts_created'  => 0,
		),
		'errors'          => array(),
		'stop_requested'  => false,
		'last_completed'  => null,
	);
}

function person_migration_get_state() {
	$state = get_option(PERSON_MIGRATION_STATE_KEY, null);
	if (!is_array($state)) {
		return person_migration_default_state();
	}
	return $state;
}

function person_migration_get_map() {
	$map = get_option(PERSON_MIGRATION_MAP_KEY, array());
	return is_array($map) ? $map : array();
}

// ============================================================
// Field definitions: what to copy from user meta to CPT post meta
// ============================================================

function person_migration_get_simple_fields() {
	return array(
		// Personnel group
		'personnel_id',
		'college_id',
		'uga_email',
		'title',
		'phone_number',
		'cell_phone_number',
		'fax_number',
		'caes_location_id',
		'image_name',
		'mailing_address',
		'mailing_address2',
		'mailing_city',
		'mailing_state',
		'mailing_zip',
		'shipping_address',
		'shipping_address2',
		'shipping_city',
		'shipping_state',
		'shipping_zip',
		'is_active',
		// Symplectic group
		'elements_user_id',
		'elements_overview',
		// Editorial group
		'public_friendly_title',
		// Expert/Source group
		'source_expert_id',
		'description',
		'area_of_expertise',
		'is_source',
		'is_expert',
		// Writer group
		'writer_id',
		'tagline',
		'coverage_area',
		'is_proofer',
		'is_media_contact',
	);
}

function person_migration_get_repeater_fields() {
	return array(
		'elements_scholarly_works' => array('pub_title', 'pub_type', 'pub_journal', 'pub_doi', 'pub_year', 'pub_citation_count'),
		'elements_distinctions'   => array('distinction_title', 'distinction_date', 'distinction_description'),
		'elements_courses_taught' => array('course_title', 'course_code', 'course_term'),
	);
}

function person_migration_get_taxonomy_fields() {
	return array(
		'elements_areas_of_expertise' => 'areas_of_expertise',
	);
}

// ============================================================
// Core: migrate a single user to a CPT post
// ============================================================

function person_migration_migrate_single_user($user_id, $dry_run = false) {
	$result = array(
		'status'         => 'failed',
		'fields_written' => 0,
		'post_id'        => null,
		'error_message'  => null,
		'field_log'      => array(),
	);

	$user = get_userdata($user_id);
	if (!$user) {
		$result['error_message'] = 'User ID ' . $user_id . ' not found.';
		return $result;
	}

	$user_acf = 'user_' . $user_id;

	// Determine the display name from WP user fields
	$first_name   = $user->first_name ?: '';
	$last_name    = $user->last_name ?: '';
	$display_name = $user->display_name ?: trim($first_name . ' ' . $last_name);
	if (empty($display_name)) {
		$display_name = $user->user_login;
	}

	// Check if this user was already migrated
	$map = person_migration_get_map();
	if (isset($map[$user_id])) {
		$existing_post = get_post($map[$user_id]);
		if ($existing_post && $existing_post->post_type === 'caes_hub_person') {
			$result['error_message'] = 'Already migrated to post ID ' . $map[$user_id] . '. Skipping.';
			$result['status'] = 'skipped';
			$result['post_id'] = $map[$user_id];
			return $result;
		}
	}

	if ($dry_run) {
		$result['field_log'][] = '[DRY RUN] Would create CPT post: "' . $display_name . '"';
	}

	// Create the CPT post
	$post_id = null;
	if (!$dry_run) {
		$post_data = array(
			'post_type'   => 'caes_hub_person',
			'post_title'  => $display_name,
			'post_status' => 'publish',
			'post_name'   => sanitize_title($display_name),
		);
		$post_id = wp_insert_post($post_data, true);
		if (is_wp_error($post_id)) {
			$result['error_message'] = 'Failed to create post: ' . $post_id->get_error_message();
			return $result;
		}
		$result['post_id'] = $post_id;

		// Debug: verify the post was actually created as publish
		$actual_status = get_post_status($post_id);
		if ($actual_status !== 'publish') {
			$result['field_log'][] = 'WARNING: Post created but status is "' . $actual_status . '" instead of "publish" (post_id=' . $post_id . ', title="' . $display_name . '")';
		}
	}

	$fields_written = 0;
	$target = $dry_run ? null : $post_id;

	// Write name fields (from WP user core fields, not ACF)
	$name_fields = array(
		'first_name'   => $first_name,
		'last_name'    => $last_name,
		'display_name' => $display_name,
	);
	foreach ($name_fields as $field_name => $value) {
		if (!empty($value)) {
			if ($dry_run) {
				$result['field_log'][] = '[DRY RUN] ' . $field_name . ' = "' . mb_substr($value, 0, 80) . '"';
			} else {
				update_post_meta($post_id, $field_name, $value);
				$fields_written++;
			}
		}
	}

	// Copy simple ACF fields
	$simple_fields = person_migration_get_simple_fields();
	foreach ($simple_fields as $field_name) {
		$value = get_user_meta($user_id, $field_name, true);
		if ($value !== '' && $value !== false && $value !== null) {
			if ($dry_run) {
				$display_val = is_array($value) ? json_encode($value) : mb_substr((string)$value, 0, 80);
				$result['field_log'][] = '[DRY RUN] ' . $field_name . ' = "' . $display_val . '"';
			} else {
				update_post_meta($post_id, $field_name, $value);
				$fields_written++;
			}
		}
	}

	// Assign department and program_area as taxonomy terms
	$tax_mappings = array(
		'department'   => 'person_department',
		'program_area' => 'person_program_area',
	);
	foreach ($tax_mappings as $meta_key => $taxonomy) {
		$value = get_user_meta($user_id, $meta_key, true);
		if (!empty($value)) {
			$value = trim($value);
			if ($dry_run) {
				$result['field_log'][] = '[DRY RUN] ' . $taxonomy . ' = "' . mb_substr($value, 0, 80) . '"';
			} else {
				wp_set_object_terms($post_id, $value, $taxonomy);
				$fields_written++;
			}
		}
	}

	// Copy repeater fields (stored as serialized meta)
	$repeater_fields = person_migration_get_repeater_fields();
	foreach ($repeater_fields as $repeater_name => $sub_fields) {
		$count = (int) get_user_meta($user_id, $repeater_name, true);
		if ($count > 0) {
			if ($dry_run) {
				$result['field_log'][] = '[DRY RUN] ' . $repeater_name . ': ' . $count . ' rows';
			} else {
				update_post_meta($post_id, $repeater_name, $count);
				for ($i = 0; $i < $count; $i++) {
					foreach ($sub_fields as $sub) {
						$meta_key = $repeater_name . '_' . $i . '_' . $sub;
						$val = get_user_meta($user_id, $meta_key, true);
						if ($val !== '' && $val !== false && $val !== null) {
							update_post_meta($post_id, $meta_key, $val);
						}
						// Also copy the ACF reference key (_{field_name} pattern)
						$ref_key = '_' . $meta_key;
						$ref_val = get_user_meta($user_id, $ref_key, true);
						if ($ref_val !== '' && $ref_val !== false) {
							update_post_meta($post_id, $ref_key, $ref_val);
						}
					}
				}
				// Copy the ACF reference for the repeater itself
				$repeater_ref = '_' . $repeater_name;
				$repeater_ref_val = get_user_meta($user_id, $repeater_ref, true);
				if ($repeater_ref_val !== '' && $repeater_ref_val !== false) {
					update_post_meta($post_id, $repeater_ref, $repeater_ref_val);
				}
				$fields_written++;
			}
		}
	}

	// Copy taxonomy fields
	$taxonomy_fields = person_migration_get_taxonomy_fields();
	foreach ($taxonomy_fields as $field_name => $taxonomy) {
		$term_ids = get_user_meta($user_id, $field_name, true);
		if (!empty($term_ids) && is_array($term_ids)) {
			if ($dry_run) {
				$result['field_log'][] = '[DRY RUN] ' . $field_name . ': ' . count($term_ids) . ' terms';
			} else {
				$int_ids = array_map('intval', $term_ids);
				wp_set_object_terms($post_id, $int_ids, $taxonomy);
				update_post_meta($post_id, $field_name, $term_ids);
				// Copy the ACF reference
				$ref_val = get_user_meta($user_id, '_' . $field_name, true);
				if ($ref_val !== '' && $ref_val !== false) {
					update_post_meta($post_id, '_' . $field_name, $ref_val);
				}
				$fields_written++;
			}
		}
	}

	// Copy ACF reference keys for simple fields
	if (!$dry_run) {
		foreach ($simple_fields as $field_name) {
			$ref_key = '_' . $field_name;
			$ref_val = get_user_meta($user_id, $ref_key, true);
			if ($ref_val !== '' && $ref_val !== false) {
				update_post_meta($post_id, $ref_key, $ref_val);
			}
		}
	}

	// Update the lookup map
	if (!$dry_run && $post_id) {
		$map[$user_id] = $post_id;
		update_option(PERSON_MIGRATION_MAP_KEY, $map, false);
	}

	$result['status']         = 'ok';
	$result['fields_written'] = $fields_written;
	if ($dry_run) {
		$result['field_log'][] = '[DRY RUN] Total fields that would be written: ~' . count(array_filter($result['field_log']));
	}

	return $result;
}

// ============================================================
// Get all users eligible for migration
// ============================================================

function person_migration_get_eligible_users($offset = 0, $number = -1) {
	$args = array(
		'role__in' => array('personnel_user', 'expert_user'),
		'orderby'  => 'ID',
		'order'    => 'ASC',
		'fields'   => 'ID',
		'number'   => $number,
		'offset'   => $offset,
	);
	return get_users($args);
}

function person_migration_count_eligible_users() {
	$args = array(
		'role__in' => array('personnel_user', 'expert_user'),
		'fields'   => 'ID',
		'number'   => -1,
	);
	return count(get_users($args));
}

// ============================================================
// Job control (bulk migration)
// ============================================================

function person_migration_start_job($dry_run = false) {
	$state = person_migration_get_state();

	if ($state['status'] === 'running') {
		return false;
	}

	$total = person_migration_count_eligible_users();
	if ($total === 0) {
		return false;
	}

	$last_completed      = $state['last_completed'];
	$new_state           = person_migration_default_state();
	$new_state['status']         = 'running';
	$new_state['mode']           = 'migrate';
	$new_state['dry_run']        = $dry_run;
	$new_state['started_at']     = time();
	$new_state['total_users']    = $total;
	$new_state['last_completed'] = $last_completed;
	update_option(PERSON_MIGRATION_STATE_KEY, $new_state, false);

	wp_schedule_single_event(time(), PERSON_MIGRATION_BATCH_HOOK);
	return true;
}

function person_migration_resume_job() {
	$state = person_migration_get_state();
	if ($state['status'] !== 'stopped') {
		return false;
	}
	if ($state['processed_users'] >= $state['total_users']) {
		return false;
	}
	$state['status']         = 'running';
	$state['stop_requested'] = false;
	update_option(PERSON_MIGRATION_STATE_KEY, $state, false);
	wp_schedule_single_event(time(), PERSON_MIGRATION_BATCH_HOOK);
	return true;
}

add_action(PERSON_MIGRATION_BATCH_HOOK, 'person_migration_run_batch');

function person_migration_run_batch() {
	$state = person_migration_get_state();

	if ($state['status'] !== 'running') {
		return;
	}

	$mode = isset($state['mode']) ? $state['mode'] : 'migrate';

	if ($mode === 'migrate') {
		person_migration_run_migrate_batch($state);
	} elseif ($mode === 'swap_repeaters') {
		person_migration_run_swap_batch($state);
	} elseif ($mode === 'repopulate_flat_meta') {
		person_migration_run_flat_meta_batch($state, false);
	} elseif ($mode === 'revert_flat_meta') {
		person_migration_run_flat_meta_batch($state, true);
	}
}

function person_migration_run_migrate_batch(&$state) {
	$users = person_migration_get_eligible_users($state['processed_users'], PERSON_MIGRATION_BATCH_SIZE);

	foreach ($users as $user_id) {
		$check = person_migration_get_state();
		if ($check['status'] !== 'running') {
			return;
		}

		$result = person_migration_migrate_single_user($user_id, $state['dry_run']);
		$state['processed_users']++;

		if ($result['status'] === 'ok') {
			$state['stats']['users_ok']++;
			if (!$state['dry_run']) {
				$state['stats']['posts_created']++;
			}
		} elseif ($result['status'] === 'skipped') {
			$state['stats']['users_skipped']++;
		} else {
			$state['stats']['users_failed']++;
		}

		$state['stats']['fields_written'] += $result['fields_written'];

		if (!empty($result['error_message']) && $result['status'] !== 'skipped' && count($state['errors']) < PERSON_MIGRATION_MAX_ERRORS) {
			$state['errors'][] = 'User ' . $user_id . ': ' . $result['error_message'];
		}

		// In dry-run mode, capture per-user field logs so admins can review what would happen
		if ($state['dry_run'] && !empty($result['field_log']) && count($state['errors']) < PERSON_MIGRATION_MAX_ERRORS) {
			$user_info = get_userdata($user_id);
			$label = $user_info ? $user_info->display_name . ' (ID ' . $user_id . ')' : 'User ' . $user_id;
			$state['errors'][] = '--- ' . $label . ' ---';
			foreach ($result['field_log'] as $log_line) {
				if (count($state['errors']) >= PERSON_MIGRATION_MAX_ERRORS) break;
				$state['errors'][] = $log_line;
			}
		}

		update_option(PERSON_MIGRATION_STATE_KEY, $state, false);
	}

	if ($state['processed_users'] < $state['total_users'] && !empty($users)) {
		wp_schedule_single_event(time() + 2, PERSON_MIGRATION_BATCH_HOOK);
	} else {
		$state['status']       = 'complete';
		$state['completed_at'] = time();
		$state['last_completed'] = array(
			'started_at'      => $state['started_at'],
			'completed_at'    => $state['completed_at'],
			'mode'            => $state['mode'],
			'dry_run'         => $state['dry_run'],
			'total_users'     => $state['total_users'],
			'processed_users' => $state['processed_users'],
			'stats'           => $state['stats'],
			'errors'          => $state['errors'],
		);
		update_option(PERSON_MIGRATION_STATE_KEY, $state, false);
	}
}

// ============================================================
// Step 7: Swap repeater IDs (user IDs -> CPT post IDs)
// ============================================================

function person_migration_start_swap_job($dry_run = false) {
	$state = person_migration_get_state();
	if ($state['status'] === 'running') {
		return false;
	}

	$map = person_migration_get_map();
	if (empty($map)) {
		return false;
	}

	// Count posts that have repeater fields to swap
	$post_types = array('post', 'publications', 'shorthand_story');
	$repeater_meta_keys = array('authors', 'experts', 'translator', 'artists');

	$total = 0;
	$type_counts = array();
	foreach ($post_types as $pt) {
		$count = wp_count_posts($pt);
		$pt_total = (isset($count->publish) ? $count->publish : 0)
				+ (isset($count->draft) ? $count->draft : 0)
				+ (isset($count->private) ? $count->private : 0);
		$type_counts[$pt] = $pt_total;
		$total += $pt_total;
	}

	if ($total === 0) return false;

	$last_completed      = $state['last_completed'];
	$new_state           = person_migration_default_state();
	$new_state['status']         = 'running';
	$new_state['mode']           = 'swap_repeaters';
	$new_state['dry_run']        = $dry_run;
	$new_state['started_at']     = time();
	$new_state['total_users']    = $total; // reusing field name for total posts
	$new_state['type_counts']    = $type_counts;
	$new_state['last_completed'] = $last_completed;
	update_option(PERSON_MIGRATION_STATE_KEY, $new_state, false);

	wp_schedule_single_event(time(), PERSON_MIGRATION_BATCH_HOOK);
	return true;
}

function person_migration_run_swap_batch(&$state) {
	$map = person_migration_get_map();
	$post_types = array('post', 'publications', 'shorthand_story');
	$repeater_names = array('authors', 'experts', 'translator', 'artists');

	$posts = get_posts(array(
		'post_type'      => $post_types,
		'post_status'    => array('publish', 'draft', 'private', 'future'),
		'posts_per_page' => PERSON_MIGRATION_BATCH_SIZE,
		'offset'         => $state['processed_users'],
		'orderby'        => 'ID',
		'order'          => 'ASC',
	));

	foreach ($posts as $post) {
		$check = person_migration_get_state();
		if ($check['status'] !== 'running') {
			return;
		}

		$swaps_made = 0;
		foreach ($repeater_names as $repeater_name) {
			$count = (int) get_post_meta($post->ID, $repeater_name, true);
			if ($count <= 0) continue;

			for ($i = 0; $i < $count; $i++) {
				// The sub-field that holds the user ID varies by repeater
				// authors/experts/translator/artists repeaters use a 'user' sub-field
				$sub_field_candidates = array('user', 'author', 'expert');
				foreach ($sub_field_candidates as $sub) {
					$meta_key = $repeater_name . '_' . $i . '_' . $sub;
					$old_val = get_post_meta($post->ID, $meta_key, true);
					if (!empty($old_val) && isset($map[$old_val])) {
						if ($state['dry_run']) {
							if (count($state['errors']) < PERSON_MIGRATION_MAX_ERRORS) {
								$state['errors'][] = '[DRY RUN] Post ' . $post->ID . ': ' . $meta_key . ' would change from user ' . $old_val . ' to post ' . $map[$old_val];
							}
						} else {
							// Back up original user ID before overwriting
							update_post_meta($post->ID, $meta_key . '_backup', $old_val);
							update_post_meta($post->ID, $meta_key, $map[$old_val]);
							if (count($state['errors']) < PERSON_MIGRATION_MAX_ERRORS) {
								$state['errors'][] = 'Post ' . $post->ID . ': ' . $meta_key . ' changed from user ' . $old_val . ' to post ' . $map[$old_val];
							}
						}
						$swaps_made++;
					}
				}
			}
		}

		$state['processed_users']++;
		if ($swaps_made > 0) {
			$state['stats']['users_ok']++;
			$state['stats']['fields_written'] += $swaps_made;
		} else {
			$state['stats']['users_skipped']++;
		}

		update_option(PERSON_MIGRATION_STATE_KEY, $state, false);
	}

	if ($state['processed_users'] < $state['total_users'] && !empty($posts)) {
		wp_schedule_single_event(time() + 2, PERSON_MIGRATION_BATCH_HOOK);
	} else {
		$state['status']       = 'complete';
		$state['completed_at'] = time();
		$state['last_completed'] = array(
			'started_at'      => $state['started_at'],
			'completed_at'    => $state['completed_at'],
			'mode'            => $state['mode'],
			'dry_run'         => $state['dry_run'],
			'total_users'     => $state['total_users'],
			'processed_users' => $state['processed_users'],
			'stats'           => $state['stats'],
			'errors'          => $state['errors'],
		);
		update_option(PERSON_MIGRATION_STATE_KEY, $state, false);
	}
}

// Verify swap: sample posts and report whether repeater IDs point to CPT posts, users, or nothing
function person_migration_ajax_verify_swap() {
	person_migration_check_ajax();
	@set_time_limit(180);

	global $wpdb;
	$post_types_in = "'post','publications','shorthand_story'";
	$max_flagged   = 500;
	$max_details   = 100;

	// 1. Get all repeater "_user" entries in one SQL query (post_id, meta_key, meta_value)
	$entries = $wpdb->get_results(
		"SELECT pm.post_id, pm.meta_key, pm.meta_value
		 FROM {$wpdb->postmeta} pm
		 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE pm.meta_key REGEXP '^(authors|experts|translator|artists)_[0-9]+_user$'
		 AND p.post_type IN ({$post_types_in})
		 AND p.post_status IN ('publish','draft','private','future')
		 AND pm.meta_value IS NOT NULL AND pm.meta_value != ''",
		ARRAY_A
	);

	// 2. Get all "_type" entries to filter out 'custom' rows
	$type_rows = $wpdb->get_results(
		"SELECT pm.post_id, pm.meta_key, pm.meta_value AS type_value
		 FROM {$wpdb->postmeta} pm
		 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE pm.meta_key REGEXP '^(authors|experts|translator|artists)_[0-9]+_type$'
		 AND p.post_type IN ({$post_types_in})
		 AND p.post_status IN ('publish','draft','private','future')",
		ARRAY_A
	);
	$custom_keys = array();
	foreach ($type_rows as $tr) {
		if (strtolower($tr['type_value']) === 'custom') {
			$user_key = preg_replace('/_type$/', '_user', $tr['meta_key']);
			$custom_keys[$tr['post_id'] . '|' . $user_key] = true;
		}
	}
	unset($type_rows);

	// 3. Collect distinct candidate IDs
	$ids = array();
	foreach ($entries as $row) {
		$v = $row['meta_value'];
		if (!is_numeric($v)) continue;
		$ids[(int) $v] = true;
	}
	$ids = array_keys($ids);

	// 4. Batch-resolve which IDs are caes_hub_person posts vs WP users vs unknown (one query each)
	$person_set = array();
	$user_set   = array();
	if (!empty($ids)) {
		$placeholders = implode(',', array_map('intval', $ids));
		$person_rows = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type='caes_hub_person' AND ID IN ({$placeholders})");
		foreach ($person_rows as $pid) {
			$person_set[(int) $pid] = true;
		}
		$user_rows = $wpdb->get_col("SELECT ID FROM {$wpdb->users} WHERE ID IN ({$placeholders})");
		foreach ($user_rows as $uid) {
			$user_set[(int) $uid] = true;
		}
	}
	unset($ids);

	// 5. Build user lookup batch (display_name + roles) only for IDs we'll actually flag
	$user_data_cache = array();
	$user_ids_to_lookup = array();
	foreach ($entries as $row) {
		$v = (int) $row['meta_value'];
		if (isset($user_set[$v]) && !isset($person_set[$v])) {
			$user_ids_to_lookup[$v] = true;
		}
	}
	$user_ids_to_lookup = array_keys($user_ids_to_lookup);
	if (!empty($user_ids_to_lookup) && count($user_ids_to_lookup) <= 5000) {
		$placeholders = implode(',', array_map('intval', $user_ids_to_lookup));
		$user_meta = $wpdb->get_results(
			"SELECT u.ID, u.display_name, um.meta_value AS caps
			 FROM {$wpdb->users} u
			 LEFT JOIN {$wpdb->usermeta} um ON um.user_id = u.ID AND um.meta_key='wp_capabilities'
			 WHERE u.ID IN ({$placeholders})",
			ARRAY_A
		);
		foreach ($user_meta as $um) {
			$caps = maybe_unserialize($um['caps']);
			$roles = is_array($caps) ? array_keys($caps) : array();
			$user_data_cache[(int) $um['ID']] = array(
				'display_name' => $um['display_name'],
				'roles'        => implode(', ', $roles),
			);
		}
		unset($user_meta);
	}

	// 6. Walk entries and tally
	$post_ids_count = 0;
	$user_ids_count = 0;
	$unknown_count  = 0;
	$custom_count   = count($custom_keys); // approximate -- counted once per type='custom' entry
	$details        = array();
	$flagged        = array();
	$total_posts    = array();

	foreach ($entries as $row) {
		$pid = (int) $row['post_id'];
		$total_posts[$pid] = true;

		// Skip rows whose type was 'custom'
		if (isset($custom_keys[$pid . '|' . $row['meta_key']])) {
			continue;
		}

		$v = $row['meta_value'];
		if (!is_numeric($v)) continue;
		$v = (int) $v;
		if ($v <= 0) continue;

		preg_match('/^([a-z]+)_([0-9]+)_user$/', $row['meta_key'], $m);
		$rname = $m[1] ?? 'unknown';
		$index = $m[2] ?? '?';

		if (isset($person_set[$v])) {
			$post_ids_count++;
			$label = 'CPT post #' . $v;
			if (count($details) < $max_details) {
				$details[] = 'Post #' . $pid . ' > ' . $rname . '[' . $index . '] = ' . $label;
			}
		} elseif (isset($user_set[$v])) {
			$user_ids_count++;
			$u = $user_data_cache[$v] ?? array('display_name' => 'user #' . $v, 'roles' => '');
			$label = 'WP user #' . $v . ' (' . $u['display_name'] . ' -- roles: ' . $u['roles'] . ')';
			if (count($flagged) < $max_flagged) {
				$flagged[] = array(
					'text'      => 'Post #' . $pid . ' > ' . $rname . '[' . $index . '] = ' . $label,
					'post_id'   => $pid,
					'user_id'   => $v,
					'user_name' => $u['display_name'],
					'roles'     => $u['roles'],
				);
			}
			if (count($details) < $max_details) {
				$details[] = 'Post #' . $pid . ' > ' . $rname . '[' . $index . '] = ' . $label;
			}
		} else {
			$unknown_count++;
			$label = 'Unknown ID ' . $v;
			if (count($flagged) < $max_flagged) {
				$flagged[] = array(
					'text'    => 'Post #' . $pid . ' > ' . $rname . '[' . $index . '] = ' . $label,
					'post_id' => $pid,
				);
			}
			if (count($details) < $max_details) {
				$details[] = 'Post #' . $pid . ' > ' . $rname . '[' . $index . '] = ' . $label;
			}
		}
	}

	wp_send_json_success(array(
		'total_posts' => count($total_posts),
		'post_ids'    => $post_ids_count,
		'user_ids'    => $user_ids_count,
		'unknown_ids' => $unknown_count,
		'custom'      => $custom_count,
		'flagged'     => $flagged,
		'details'     => $details,
	));
}

// Revert swap: restore original user IDs from _backup meta keys
function person_migration_ajax_revert_swap() {
	person_migration_check_ajax();

	$post_types = array('post', 'publications', 'shorthand_story');
	$repeater_names = array('authors', 'experts', 'translator', 'artists');
	$sub_field_candidates = array('user', 'author', 'expert');

	$posts = get_posts(array(
		'post_type'      => $post_types,
		'post_status'    => array('publish', 'draft', 'private', 'future'),
		'posts_per_page' => -1,
		'fields'         => 'ids',
	));

	$restored = 0;
	$posts_touched = 0;

	foreach ($posts as $pid) {
		$post_restored = 0;
		foreach ($repeater_names as $rname) {
			$count = (int) get_post_meta($pid, $rname, true);
			if ($count <= 0) continue;

			for ($i = 0; $i < $count; $i++) {
				foreach ($sub_field_candidates as $sub) {
					$backup_key = $rname . '_' . $i . '_' . $sub . '_backup';
					$backup_val = get_post_meta($pid, $backup_key, true);
					if (!empty($backup_val)) {
						$meta_key = $rname . '_' . $i . '_' . $sub;
						update_post_meta($pid, $meta_key, $backup_val);
						delete_post_meta($pid, $backup_key);
						$post_restored++;
					}
				}
			}
		}
		if ($post_restored > 0) {
			$restored += $post_restored;
			$posts_touched++;
		}
	}

	wp_send_json_success(array(
		'restored'      => $restored,
		'posts_touched' => $posts_touched,
	));
}

// ============================================================
// Step 7c: Resolve flagged users (manual mapping)
// ============================================================

/**
 * Search person CPT posts by name.
 */
function person_migration_ajax_search_persons() {
	person_migration_check_ajax();

	$search = sanitize_text_field($_POST['search'] ?? '');
	if (strlen($search) < 2) {
		wp_send_json_success(array('results' => array()));
	}

	$posts = get_posts(array(
		'post_type'      => 'caes_hub_person',
		'post_status'    => 'publish',
		's'              => $search,
		'posts_per_page' => 10,
		'orderby'        => 'title',
		'order'          => 'ASC',
	));

	$results = array();
	foreach ($posts as $p) {
		$results[] = array(
			'id'    => $p->ID,
			'title' => $p->post_title,
		);
	}

	wp_send_json_success(array('results' => $results));
}

/**
 * Resolve flagged users by mapping them to person CPT posts and swapping all references.
 * Expects $_POST['mappings'] as JSON: [ { user_id: 123, person_post_id: 456 }, ... ]
 */
function person_migration_ajax_resolve_flagged() {
	person_migration_check_ajax();

	$mappings_json = stripslashes($_POST['mappings'] ?? '[]');
	$mappings = json_decode($mappings_json, true);

	if (empty($mappings) || !is_array($mappings)) {
		wp_send_json_error(array('error_message' => 'No mappings provided.'));
	}

	// Validate all mappings first
	foreach ($mappings as $m) {
		$uid = intval($m['user_id'] ?? 0);
		$pid = intval($m['person_post_id'] ?? 0);
		if (!$uid || !$pid) {
			wp_send_json_error(array('error_message' => "Invalid mapping: user_id={$uid}, person_post_id={$pid}"));
		}
		if (get_post_type($pid) !== 'caes_hub_person') {
			wp_send_json_error(array('error_message' => "Post #{$pid} is not a caes_hub_person."));
		}
	}

	// Add to lookup map
	$map = get_option('person_cpt_migration_user_post_map', array());
	foreach ($mappings as $m) {
		$map[intval($m['user_id'])] = intval($m['person_post_id']);
	}
	update_option('person_cpt_migration_user_post_map', $map);

	// Swap all references for these user IDs (SQL-based to avoid memory blow-up)
	@set_time_limit(180);
	global $wpdb;
	$user_ids_to_resolve = array_map(function($m) { return intval($m['user_id']); }, $mappings);
	$user_id_list        = implode(',', array_map('intval', $user_ids_to_resolve));
	$post_types_in       = "'post','publications','shorthand_story'";
	$swapped             = 0;

	if (!empty($user_id_list)) {
		// Find every postmeta row where the value is one of the user IDs we want to swap
		$rows = $wpdb->get_results(
			"SELECT pm.meta_id, pm.post_id, pm.meta_key, pm.meta_value
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key REGEXP '^(authors|experts|translator|artists)_[0-9]+_user$'
			 AND p.post_type IN ({$post_types_in})
			 AND p.post_status IN ('publish','draft','private','future')
			 AND CAST(pm.meta_value AS UNSIGNED) IN ({$user_id_list})",
			ARRAY_A
		);

		foreach ($rows as $row) {
			$old_val = (int) $row['meta_value'];
			if (!isset($map[$old_val])) continue;
			$new_val = (int) $map[$old_val];
			$post_id = (int) $row['post_id'];
			$meta_key = $row['meta_key'];

			update_post_meta($post_id, $meta_key . '_backup', $old_val);
			update_post_meta($post_id, $meta_key, $new_val);
			$swapped++;
		}
		unset($rows);
	}

	wp_send_json_success(array(
		'swapped'  => $swapped,
		'mappings' => count($mappings),
		'message'  => "Resolved {$swapped} references across " . count($mappings) . " user(s).",
	));
}

/**
 * Audit: compare People CPT count to migration map size.
 */
function person_migration_ajax_count_audit() {
	person_migration_check_ajax();

	$map = person_migration_get_map();
	$total_migrated = count($map); // original user->post mappings created

	$expected = $total_migrated;

	// Actual count of published + draft + private caes_hub_person posts
	$actual_counts = wp_count_posts('caes_hub_person');
	$actual = (int) $actual_counts->publish + (int) $actual_counts->draft + (int) $actual_counts->private;
	$trashed = (int) $actual_counts->trash;

	$diff = $actual - $expected;

	// Find post IDs in the map that don't exist in any status
	$missing_posts = array();
	$status_counts = array();
	$unique_post_ids = array_unique(array_values($map));
	foreach ($unique_post_ids as $post_id) {
		$status = get_post_status((int) $post_id);
		if ($status === false) {
			// Find which user ID(s) map to this post
			$user_ids = array_keys(array_filter($map, function($pid) use ($post_id) {
				return (int) $pid === (int) $post_id;
			}));
			$missing_posts[] = array(
				'post_id'  => (int) $post_id,
				'user_ids' => $user_ids,
			);
		} else {
			$status_counts[$status] = ($status_counts[$status] ?? 0) + 1;
		}
	}

	wp_send_json_success(array(
		'total_migrated'   => $total_migrated,
		'unique_post_ids'  => count($unique_post_ids),
		'expected'         => $expected,
		'actual'           => $actual,
		'trashed'          => $trashed,
		'diff'             => $diff,
		'status_counts'    => $status_counts,
		'missing_posts'    => $missing_posts,
	));
}

/**
 * Audit: verify flat meta fields contain CPT post IDs, not WP user IDs.
 */
function person_migration_ajax_flat_meta_audit() {
	person_migration_check_ajax();
	@set_time_limit(180);

	global $wpdb;
	$post_types_in = "'post','publications','shorthand_story'";
	$max_flagged   = 500;

	// 1. Pull all flat meta entries in one query
	$rows = $wpdb->get_results(
		"SELECT pm.post_id, pm.meta_key, pm.meta_value
		 FROM {$wpdb->postmeta} pm
		 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE pm.meta_key IN ('all_author_ids', 'all_expert_ids')
		 AND p.post_type IN ({$post_types_in})
		 AND p.post_status IN ('publish','draft','private','future')
		 AND pm.meta_value IS NOT NULL AND pm.meta_value != ''",
		ARRAY_A
	);

	// 2. Parse all IDs and collect distinct candidates + per-row tuples
	$candidates = array();
	$entries    = array(); // [post_id, field, id]
	$posts_seen = array();
	foreach ($rows as $row) {
		$cp_id = (int) $row['post_id'];
		$ff    = $row['meta_key'];
		$raw   = maybe_unserialize($row['meta_value']);
		$ids   = is_array($raw) ? $raw : array_filter(array_map('trim', explode(',', (string) $raw)));
		foreach ($ids as $id) {
			$id = (int) $id;
			if ($id <= 0) continue;
			$candidates[$id] = true;
			$entries[] = array($cp_id, $ff, $id);
			$posts_seen[$cp_id] = true;
		}
	}
	unset($rows);
	$candidate_ids = array_keys($candidates);

	// 3. Resolve which IDs are caes_hub_person posts vs WP users (batch)
	$person_set = array();
	$user_set   = array();
	if (!empty($candidate_ids)) {
		$placeholders = implode(',', array_map('intval', $candidate_ids));
		$person_rows = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type='caes_hub_person' AND ID IN ({$placeholders})");
		foreach ($person_rows as $pid) {
			$person_set[(int) $pid] = true;
		}
		$user_rows = $wpdb->get_results(
			"SELECT ID, display_name FROM {$wpdb->users} WHERE ID IN ({$placeholders})",
			ARRAY_A
		);
		foreach ($user_rows as $u) {
			$user_set[(int) $u['ID']] = $u['display_name'];
		}
	}

	// 4. Pre-fetch titles only for affected content posts
	$post_titles = array();
	if (!empty($posts_seen)) {
		$placeholders = implode(',', array_map('intval', array_keys($posts_seen)));
		$title_rows = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE ID IN ({$placeholders})", ARRAY_A);
		foreach ($title_rows as $tr) {
			$post_titles[(int) $tr['ID']] = $tr['post_title'];
		}
	}

	// 5. Walk entries and tally
	$posts_checked  = count($posts_seen);
	$ids_checked    = 0;
	$cpt_ids        = 0;
	$user_ids_found = array();
	$not_found_ids  = array();

	foreach ($entries as $e) {
		list($cp_id, $ff, $id) = $e;
		$ids_checked++;

		if (isset($person_set[$id])) {
			$cpt_ids++;
		} elseif (isset($user_set[$id])) {
			if (count($user_ids_found) < $max_flagged) {
				$user_ids_found[] = array(
					'content_id'    => $cp_id,
					'content_title' => $post_titles[$cp_id] ?? '',
					'field'         => $ff,
					'id'            => $id,
					'type'          => 'user',
					'name'          => $user_set[$id],
				);
			}
		} else {
			if (count($not_found_ids) < $max_flagged) {
				$not_found_ids[] = array(
					'content_id'    => $cp_id,
					'content_title' => $post_titles[$cp_id] ?? '',
					'field'         => $ff,
					'id'            => $id,
				);
			}
		}
	}

	wp_send_json_success(array(
		'posts_checked'  => $posts_checked,
		'ids_checked'    => $ids_checked,
		'cpt_ids'        => $cpt_ids,
		'user_ids'       => $user_ids_found,
		'not_found'      => $not_found_ids,
	));
}


/**
 * Audit: find all WP users with no roles that are referenced in content repeater fields.
 */
function person_migration_ajax_roleless_audit() {
	person_migration_check_ajax();

	$post_types = array('post', 'publications', 'shorthand_story');
	$repeater_names = array('authors', 'experts', 'translator', 'artists');

	// Step 1: Get all users with no roles
	global $wpdb;
	$all_users = $wpdb->get_results(
		"SELECT u.ID, u.display_name, u.user_email, u.user_login
		 FROM {$wpdb->users} u
		 LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = '{$wpdb->prefix}capabilities'
		 WHERE um.meta_value IS NULL
		    OR um.meta_value = 'a:0:{}'
		    OR um.meta_value = ''
		 ORDER BY u.display_name"
	);

	if (empty($all_users)) {
		wp_send_json_success(array('users' => array(), 'message' => 'No roleless users found.'));
	}

	$roleless_ids = wp_list_pluck($all_users, 'ID');
	$roleless_map = array();
	foreach ($all_users as $u) {
		$roleless_map[$u->ID] = $u;
	}

	// Step 2: Scan all content for references to these users
	$posts = get_posts(array(
		'post_type'      => $post_types,
		'post_status'    => array('publish', 'draft', 'private', 'future'),
		'posts_per_page' => -1,
		'fields'         => 'ids',
	));

	$found_users = array(); // user_id => array of refs

	foreach ($posts as $pid) {
		foreach ($repeater_names as $rname) {
			$count = (int) get_post_meta($pid, $rname, true);
			if ($count <= 0) continue;

			for ($i = 0; $i < $count; $i++) {
				$val = (int) get_post_meta($pid, $rname . '_' . $i . '_user', true);
				if ($val && in_array($val, $roleless_ids)) {
					if (!isset($found_users[$val])) {
						$found_users[$val] = array(
							'user_id'      => $val,
							'display_name' => $roleless_map[$val]->display_name,
							'user_login'   => $roleless_map[$val]->user_login,
							'user_email'   => $roleless_map[$val]->user_email,
							'refs'         => array(),
						);
					}
					$post_title = get_the_title($pid);
					$post_type  = get_post_type($pid);
					$found_users[$val]['refs'][] = array(
						'post_id'    => $pid,
						'post_title' => $post_title,
						'post_type'  => $post_type,
						'field'      => $rname,
						'index'      => $i,
					);
				}
			}
		}
	}

	// Also report roleless users NOT referenced in any content
	$unreferenced = array();
	foreach ($all_users as $u) {
		if (!isset($found_users[$u->ID])) {
			$unreferenced[] = array(
				'user_id'      => $u->ID,
				'display_name' => $u->display_name,
				'user_login'   => $u->user_login,
				'user_email'   => $u->user_email,
			);
		}
	}

	wp_send_json_success(array(
		'referenced'   => array_values($found_users),
		'unreferenced' => $unreferenced,
		'total_roleless' => count($all_users),
		'total_referenced' => count($found_users),
		'total_unreferenced' => count($unreferenced),
	));
}

// Step 7b: Update ACF field types (User -> Post Object)
// ============================================================

/**
 * Find ACF sub-fields named 'user' inside the authors/experts/translator/artists
 * repeaters and change their type from 'user' to 'post_object' targeting caes_hub_person.
 * This makes the admin editor show a person CPT picker instead of a user picker.
 */
function person_migration_ajax_update_field_types() {
	person_migration_check_ajax();

	$direction = isset($_POST['direction']) ? sanitize_text_field($_POST['direction']) : 'to_post_object';
	$repeater_names = array('authors', 'experts', 'translator', 'artists');
	$log = array();
	$updated = 0;

	// ACF stores fields as posts with post_type 'acf-field'.
	// Sub-fields have their parent field as post_parent.
	// We need to find repeater fields by name, then find their 'user' sub-fields.

	foreach ($repeater_names as $rname) {
		// Find the repeater field post(s) -- ACF stores the field name in post_excerpt
		global $wpdb;
		$repeater_ids = $wpdb->get_col($wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'acf-field' AND post_status = 'publish' AND post_excerpt = %s",
			$rname
		));
		$repeaters = array_map('get_post', $repeater_ids);
		$repeaters = array_filter($repeaters);

		foreach ($repeaters as $repeater_post) {
			// Find sub-fields named 'user' under this repeater
			$sub_field_ids = $wpdb->get_col($wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'acf-field' AND post_status = 'publish' AND post_parent = %d AND post_excerpt = 'user'",
				$repeater_post->ID
			));
			$sub_fields = array_map('get_post', $sub_field_ids);
			$sub_fields = array_filter($sub_fields);

			foreach ($sub_fields as $sf) {
				$settings = maybe_unserialize($sf->post_content);
				if (!is_array($settings)) {
					$log[] = 'Warning: could not parse settings for field ' . $sf->ID . ' (' . $rname . ' > user)';
					continue;
				}

				$current_type = $settings['type'] ?? '';

				if ($direction === 'to_post_object') {
					if ($current_type === 'post_object') {
						$log[] = $rname . ' > user (field ' . $sf->ID . '): already post_object, skipped';
						continue;
					}
					// Back up original settings
					update_post_meta($sf->ID, '_acf_field_settings_backup', $sf->post_content);

					$settings['type'] = 'post_object';
					$settings['post_type'] = array('caes_hub_person');
					$settings['return_format'] = 'id';
					$settings['multiple'] = 0;
					$settings['allow_null'] = isset($settings['allow_null']) ? $settings['allow_null'] : 1;
					// Remove user-specific settings
					unset($settings['role']);

					wp_update_post(array(
						'ID'           => $sf->ID,
						'post_content' => maybe_serialize($settings),
					));

					$log[] = $rname . ' > user (field ' . $sf->ID . '): changed from ' . $current_type . ' to post_object (caes_hub_person)';
					$updated++;

					// Also update the sibling 'type' button group label: "User" -> "Person" (display only, stored value stays "User")
					$type_field_ids = $wpdb->get_col($wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'acf-field' AND post_status = 'publish' AND post_parent = %d AND post_excerpt = 'type' LIMIT 1",
						$repeater_post->ID
					));
					$type_fields = array_map('get_post', $type_field_ids);
					$type_fields = array_filter($type_fields);
					if (!empty($type_fields)) {
						$tf = $type_fields[0];
						$tf_settings = maybe_unserialize($tf->post_content);
						if (is_array($tf_settings) && isset($tf_settings['choices']['User'])) {
							update_post_meta($tf->ID, '_acf_field_settings_backup', $tf->post_content);
							$tf_settings['choices']['User'] = 'Person';
							wp_update_post(array(
								'ID'           => $tf->ID,
								'post_content' => maybe_serialize($tf_settings),
							));
							$log[] = $rname . ' > type (field ' . $tf->ID . '): relabeled "User" to "Person"';
						}
					}

				} elseif ($direction === 'to_user') {
					if ($current_type === 'user') {
						$log[] = $rname . ' > user (field ' . $sf->ID . '): already user type, skipped';
						continue;
					}
					// Restore from backup if available
					$backup = get_post_meta($sf->ID, '_acf_field_settings_backup', true);
					if (!empty($backup)) {
						wp_update_post(array(
							'ID'           => $sf->ID,
							'post_content' => $backup,
						));
						delete_post_meta($sf->ID, '_acf_field_settings_backup');
						$log[] = $rname . ' > user (field ' . $sf->ID . '): restored to user type from backup';
					} else {
						$settings['type'] = 'user';
						$settings['return_format'] = 'array';
						unset($settings['post_type']);
						wp_update_post(array(
							'ID'           => $sf->ID,
							'post_content' => maybe_serialize($settings),
						));
						$log[] = $rname . ' > user (field ' . $sf->ID . '): reverted to user type (no backup found)';
					}
					$updated++;

					// Also revert the sibling 'type' button group label
					$type_field_ids = $wpdb->get_col($wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'acf-field' AND post_status = 'publish' AND post_parent = %d AND post_excerpt = 'type' LIMIT 1",
						$repeater_post->ID
					));
					$type_fields = array_map('get_post', $type_field_ids);
					$type_fields = array_filter($type_fields);
					if (!empty($type_fields)) {
						$tf = $type_fields[0];
						$tf_backup = get_post_meta($tf->ID, '_acf_field_settings_backup', true);
						if (!empty($tf_backup)) {
							wp_update_post(array(
								'ID'           => $tf->ID,
								'post_content' => $tf_backup,
							));
							delete_post_meta($tf->ID, '_acf_field_settings_backup');
							$log[] = $rname . ' > type (field ' . $tf->ID . '): restored label from backup';
						}
					}
				}
			}

			if (empty($sub_fields)) {
				$log[] = $rname . ' (repeater ' . $repeater_post->ID . '): no "user" sub-field found';
			}
		}

		if (empty($repeaters)) {
			$log[] = $rname . ': no repeater field found in ACF';
		}
	}

	// Clear ACF cache so changes take effect immediately
	if (function_exists('acf_clear_cache')) {
		acf_clear_cache();
	}
	wp_cache_flush();

	wp_send_json_success(array(
		'direction' => $direction,
		'updated'   => $updated,
		'log'       => $log,
	));
}

// ============================================================
// Step 8: Repopulate / revert flat meta fields
// ============================================================

function person_migration_start_flat_meta_job($dry_run = false, $revert = false) {
	$state = person_migration_get_state();
	if ($state['status'] === 'running') {
		return false;
	}

	$post_types = array('post', 'publications', 'shorthand_story');
	$total = 0;
	$type_counts = array();
	foreach ($post_types as $pt) {
		$count = wp_count_posts($pt);
		$pt_total = (isset($count->publish) ? $count->publish : 0)
				+ (isset($count->draft) ? $count->draft : 0)
				+ (isset($count->private) ? $count->private : 0);
		$type_counts[$pt] = $pt_total;
		$total += $pt_total;
	}

	if ($total === 0) return false;

	$last_completed      = $state['last_completed'];
	$new_state           = person_migration_default_state();
	$new_state['status']         = 'running';
	$new_state['mode']           = $revert ? 'revert_flat_meta' : 'repopulate_flat_meta';
	$new_state['dry_run']        = $dry_run;
	$new_state['started_at']     = time();
	$new_state['total_users']    = $total;
	$new_state['type_counts']    = $type_counts;
	$new_state['last_completed'] = $last_completed;
	update_option(PERSON_MIGRATION_STATE_KEY, $new_state, false);

	wp_schedule_single_event(time(), PERSON_MIGRATION_BATCH_HOOK);
	return true;
}

function person_migration_run_flat_meta_batch(&$state, $revert = false) {
	$map = person_migration_get_map();
	$post_types = array('post', 'publications', 'shorthand_story');
	$flat_fields = array('all_author_ids', 'all_expert_ids');

	$posts = get_posts(array(
		'post_type'      => $post_types,
		'post_status'    => array('publish', 'draft', 'private', 'future'),
		'posts_per_page' => PERSON_MIGRATION_BATCH_SIZE,
		'offset'         => $state['processed_users'],
		'orderby'        => 'ID',
		'order'          => 'ASC',
	));

	foreach ($posts as $post) {
		$check = person_migration_get_state();
		if ($check['status'] !== 'running') {
			return;
		}

		$updated = false;
		foreach ($flat_fields as $field) {
			$backup_field = '_' . $field . '_backup';

			if ($revert) {
				// Revert: copy backup back to original
				$backup_val = get_post_meta($post->ID, $backup_field, true);
				if ($backup_val !== '' && $backup_val !== false) {
					if ($state['dry_run']) {
						if (count($state['errors']) < PERSON_MIGRATION_MAX_ERRORS) {
							$state['errors'][] = '[DRY RUN] Post ' . $post->ID . ': would revert ' . $field . ' from backup';
						}
					} else {
						update_post_meta($post->ID, $field, $backup_val);
					}
					$updated = true;
				}
			} else {
				// Forward: backup original, then swap IDs
				$original = get_post_meta($post->ID, $field, true);
				if (empty($original)) continue;

				// Back up the original value
				if (!$state['dry_run']) {
					update_post_meta($post->ID, $backup_field, $original);
				}

				// Swap user IDs to post IDs in the serialized array
				$ids = maybe_unserialize($original);
				if (!is_array($ids)) {
					// It might be a comma-separated string
					$ids = array_filter(array_map('trim', explode(',', $original)));
				}

				$new_ids = array();
				$changed = false;
				foreach ($ids as $id) {
					if (isset($map[$id])) {
						$new_ids[] = $map[$id];
						$changed = true;
					} else {
						$new_ids[] = $id;
					}
				}

				if ($changed) {
					if ($state['dry_run']) {
						if (count($state['errors']) < PERSON_MIGRATION_MAX_ERRORS) {
							$state['errors'][] = '[DRY RUN] Post ' . $post->ID . ': ' . $field . ' would swap ' . count(array_intersect_key(array_flip($ids), $map)) . ' IDs';
						}
					} else {
						update_post_meta($post->ID, $field, $new_ids);
					}
					$updated = true;
				}
			}
		}

		$state['processed_users']++;
		if ($updated) {
			$state['stats']['users_ok']++;
			$state['stats']['fields_written']++;
		} else {
			$state['stats']['users_skipped']++;
		}

		update_option(PERSON_MIGRATION_STATE_KEY, $state, false);
	}

	if ($state['processed_users'] < $state['total_users'] && !empty($posts)) {
		wp_schedule_single_event(time() + 2, PERSON_MIGRATION_BATCH_HOOK);
	} else {
		$state['status']       = 'complete';
		$state['completed_at'] = time();
		$state['last_completed'] = array(
			'started_at'      => $state['started_at'],
			'completed_at'    => $state['completed_at'],
			'mode'            => $state['mode'],
			'dry_run'         => $state['dry_run'],
			'total_users'     => $state['total_users'],
			'processed_users' => $state['processed_users'],
			'stats'           => $state['stats'],
			'errors'          => $state['errors'],
		);
		update_option(PERSON_MIGRATION_STATE_KEY, $state, false);
	}
}

// ============================================================
// Admin menu
// ============================================================

add_action('admin_menu', 'person_migration_add_admin_page');

function person_migration_add_admin_page() {
	add_submenu_page(
		'caes-tools',
		'People User to CPT Migration',
		'People User to CPT Migration',
		'manage_options',
		'person-cpt-migration',
		'person_migration_render_page'
	);
}

// ============================================================
// Scripts and styles
// ============================================================

add_action('admin_enqueue_scripts', 'person_migration_enqueue_scripts');

function person_migration_enqueue_scripts($hook) {
	if ($hook !== 'caes-tools_page_person-cpt-migration') {
		return;
	}

	wp_enqueue_style('wp-admin');
	wp_enqueue_script('jquery');

	wp_add_inline_style('wp-admin', '
		.pmig-wrapper { max-width: 900px; margin: 20px 0; }
		.pmig-panel { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 16px; margin-bottom: 20px; }
		.pmig-panel h2 { margin-top: 0; padding-bottom: 8px; border-bottom: 1px solid #e5e5e5; font-size: 14px; text-transform: uppercase; letter-spacing: 0.03em; color: #555; }
		.pmig-stat-grid { display: flex; gap: 24px; flex-wrap: wrap; margin: 12px 0; }
		.pmig-stat { text-align: center; min-width: 60px; }
		.pmig-stat-value { font-size: 28px; font-weight: 700; line-height: 1; }
		.pmig-stat-label { font-size: 11px; color: #666; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.05em; }
		.pmig-stat-value.ok      { color: #46b450; }
		.pmig-stat-value.failed  { color: #dc3232; }
		.pmig-stat-value.neutral { color: #0073aa; }
		.pmig-progress-wrap { margin: 12px 0; }
		.pmig-progress-bar { height: 12px; background: #e0e0e0; border-radius: 6px; overflow: hidden; }
		.pmig-progress-fill { height: 100%; background: #0073aa; border-radius: 6px; transition: width 0.4s ease; }
		.pmig-progress-label { font-size: 12px; color: #555; margin-top: 4px; }
		.pmig-error-list { max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 11px; margin-top: 4px; }
		.pmig-error-list div { padding: 3px 0; border-bottom: 1px solid #f5f5f5; color: #dc3232; }
		.pmig-error-list div.info { color: #0073aa; }
		.pmig-meta { font-size: 12px; color: #666; margin-top: 6px; }
		.pmig-status-badge { display: inline-block; padding: 3px 10px; border-radius: 3px; font-size: 12px; font-weight: 600; }
		.pmig-status-badge.idle     { background: #f0f0f0; color: #555; }
		.pmig-status-badge.running  { background: #e5f0fa; color: #0073aa; }
		.pmig-status-badge.complete { background: #ecf7ed; color: #46b450; }
		.pmig-status-badge.error    { background: #fbeaea; color: #dc3232; }
		.pmig-status-badge.stopped  { background: #fff8e5; color: #9a5e00; }
		@keyframes pmig-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
		.dashicons.pmig-spin { animation: pmig-spin 1s linear infinite; display: inline-block; }
		.pmig-section-divider { border-top: 2px solid #e5e5e5; margin: 8px 0 16px; }
		.pmig-btn-group { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
		.pmig-btn-group label { font-weight: 600; margin-right: 4px; }
	');

	wp_add_inline_script('jquery', '
		jQuery(function($) {
			var nonce = ' . json_encode(wp_create_nonce('person_migration_nonce')) . ';
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

			function modeLabel(mode, dryRun) {
				var labels = {
					"migrate": "User Migration",
					"swap_repeaters": "Repeater ID Swap",
					"repopulate_flat_meta": "Flat Meta Repopulation",
					"revert_flat_meta": "Flat Meta Revert"
				};
				var label = labels[mode] || mode || "Unknown";
				if (dryRun) label += " (DRY RUN)";
				return label;
			}

			function renderStats(s, mode) {
				var itemLabel = (mode === "migrate") ? "Users" : "Posts";
				return "<div class=\"pmig-stat-grid\">"
					+ "<div class=\"pmig-stat\"><div class=\"pmig-stat-value ok\">"      + esc(s.users_ok)       + "</div><div class=\"pmig-stat-label\">" + esc(itemLabel) + " OK</div></div>"
					+ "<div class=\"pmig-stat\"><div class=\"pmig-stat-value failed\">"  + esc(s.users_failed)   + "</div><div class=\"pmig-stat-label\">Failed</div></div>"
					+ "<div class=\"pmig-stat\"><div class=\"pmig-stat-value neutral\">" + esc(s.users_skipped)  + "</div><div class=\"pmig-stat-label\">Skipped</div></div>"
					+ "<div class=\"pmig-stat\"><div class=\"pmig-stat-value neutral\">" + esc(s.fields_written) + "</div><div class=\"pmig-stat-label\">Fields Written</div></div>"
					+ (mode === "migrate" ? "<div class=\"pmig-stat\"><div class=\"pmig-stat-value neutral\">" + esc(s.posts_created) + "</div><div class=\"pmig-stat-label\">Posts Created</div></div>" : "")
					+ "</div>";
			}

			function renderErrors(errors) {
				if (!errors || !errors.length) return "";
				var html = "<div style=\"margin-top:10px\"><strong style=\"font-size:12px\">Log (" + esc(errors.length) + "):</strong><div class=\"pmig-error-list\">";
				errors.forEach(function(e) {
					var cls = e.indexOf("[DRY RUN]") === 0 ? "info" : "";
					html += "<div class=\"" + cls + "\">" + esc(e) + "</div>";
				});
				return html + "</div></div>";
			}

			function renderCurrentPanel(state) {
				var sc = state.status;
				var badge = sc.charAt(0).toUpperCase() + sc.slice(1);
				if (sc === "running") {
					badge = "<span class=\"dashicons dashicons-update pmig-spin\" style=\"font-size:14px;vertical-align:middle\"></span> Running";
				}
				var html = "<div style=\"margin-bottom:12px\">";
				html += "<span class=\"pmig-status-badge " + esc(sc) + "\">" + badge + "</span>";
				if (state.mode) html += "&ensp;<span class=\"pmig-meta\" style=\"display:inline\">" + esc(modeLabel(state.mode, state.dry_run)) + "</span>";
				html += "</div>";

				if (sc === "running" || sc === "complete" || sc === "error" || sc === "stopped") {
					var pct = state.total_users > 0 ? Math.round(state.processed_users / state.total_users * 100) : 0;
					var itemLabel = (state.mode === "migrate") ? "users" : "posts";
					html += "<div class=\"pmig-progress-wrap\">";
					html += "<div class=\"pmig-progress-bar\"><div class=\"pmig-progress-fill\" style=\"width:" + pct + "%\"></div></div>";
					var breakdownStr = "";
				if (state.type_counts && itemLabel === "posts") {
					var parts = [];
					var labels = {"post": "posts", "publications": "pubs", "shorthand_story": "stories"};
					for (var pt in state.type_counts) {
						if (state.type_counts.hasOwnProperty(pt)) {
							parts.push(esc(state.type_counts[pt]) + " " + (labels[pt] || pt));
						}
					}
					if (parts.length) breakdownStr = " (" + parts.join(", ") + ")";
				}
				html += "<div class=\"pmig-progress-label\">" + esc(state.processed_users) + " / " + esc(state.total_users) + " " + itemLabel + breakdownStr + " (" + pct + "%)</div>";
					html += "</div>";
					html += renderStats(state.stats, state.mode);
					html += "<div class=\"pmig-meta\">Started: " + fmtTs(state.started_at);
					if (state.completed_at) {
						html += " --- Completed: " + fmtTs(state.completed_at);
						html += " --- Duration: " + fmtDuration(state.started_at, state.completed_at);
					}
					html += "</div>";
					html += renderErrors(state.errors);
				} else {
					html += "<p style=\"color:#666;font-size:13px\">No job is currently running.</p>";
				}
				$("#pmig-current-panel").html(html);
			}

			function renderLastCompletedPanel(lc) {
				if (!lc) {
					$("#pmig-last-completed-section").hide();
					return;
				}
				$("#pmig-last-completed-section").show();
				var html = "<div class=\"pmig-meta\">" + esc(modeLabel(lc.mode, lc.dry_run));
				html += " --- Started: " + fmtTs(lc.started_at);
				html += " --- Completed: " + fmtTs(lc.completed_at);
				if (lc.started_at && lc.completed_at) html += " --- Duration: " + fmtDuration(lc.started_at, lc.completed_at);
				html += "</div>";
				html += renderStats(lc.stats, lc.mode);
				html += renderErrors(lc.errors);
				$("#pmig-last-completed-panel").html(html);
			}

			function updateButtons(state) {
				if (state.status === "running") {
					$(".pmig-action-btn").prop("disabled", true);
					$("#pmig-stop-btn").show().prop("disabled", false).val("Stop");
					$("#pmig-resume-btn").hide();
				} else {
					$(".pmig-action-btn").prop("disabled", false);
					$("#pmig-stop-btn").hide();
					var canResume = state.status === "stopped"
						&& state.processed_users > 0
						&& state.processed_users < state.total_users;
					if (canResume) {
						$("#pmig-resume-btn").show().prop("disabled", false)
							.val("Resume (" + esc(state.processed_users) + " / " + esc(state.total_users) + " done)");
					} else {
						$("#pmig-resume-btn").hide();
					}
				}
			}

			function pollStatus() {
				$.ajax({
					url: ajaxurl,
					method: "POST",
					data: { action: "person_migration_status", nonce: nonce },
					success: function(response) {
						if (!response.success) return;
						var state = response.data.state;
						renderCurrentPanel(state);
						renderLastCompletedPanel(state.last_completed);
						updateButtons(state);
						if (state.status === "running") {
							pollTimer = setTimeout(pollStatus, 3000);
						} else {
							clearTimeout(pollTimer);
							pollTimer = null;
						}
					}
				});
			}

			function doAction(action, extraData, confirmMsg) {
				if (confirmMsg && !confirm(confirmMsg)) return;
				var data = $.extend({ action: action, nonce: nonce }, extraData || {});
				$.ajax({
					url: ajaxurl,
					method: "POST",
					data: data,
					success: function(response) {
						if (response.success) {
							setTimeout(pollStatus, 1000);
						} else {
							var msg = (response.data && response.data.error_message) ? response.data.error_message : "Unknown error";
							alert("Error: " + msg);
							pollStatus();
						}
					},
					error: function() {
						alert("AJAX error.");
						pollStatus();
					}
				});
			}

			// Bulk migration
			$("#pmig-migrate-btn").on("click", function() {
				var dryRun = $("#pmig-dry-run").is(":checked");
				var msg = dryRun
					? "Start a DRY RUN migration of all eligible users? No data will be written."
					: "Start migrating all eligible users to CPT posts? This will create new posts.";
				doAction("person_migration_trigger", { dry_run: dryRun ? 1 : 0 }, msg);
			});

			// Swap repeaters
			$("#pmig-swap-btn").on("click", function() {
				var dryRun = $("#pmig-dry-run").is(":checked");
				var msg = dryRun
					? "Start a DRY RUN repeater ID swap? No data will be written."
					: "Start swapping user IDs to post IDs in all repeater fields? This modifies post data.";
				doAction("person_migration_swap", { dry_run: dryRun ? 1 : 0 }, msg);
			});

			// Verify swap
			$("#pmig-swap-verify-btn").on("click", function() {
				$(this).prop("disabled", true).val("Checking...");
				$.ajax({
					url: ajaxurl,
					method: "POST",
					data: { action: "person_migration_verify_swap", nonce: nonce },
					success: function(response) {
						$("#pmig-swap-verify-btn").prop("disabled", false).val("Verify");
						if (response.success) {
							var d = response.data;
							var html = "<div class=\"notice notice-info\" style=\"margin:12px 0\">";
							html += "<p><strong>Swap Verification</strong> (scanned " + esc(String(d.total_posts)) + " posts)</p>";
							html += "<p>CPT posts: <strong>" + esc(d.post_ids) + "</strong> | WP users: <strong style=\"" + (d.user_ids > 0 ? "color:#d63638" : "") + "\">" + esc(d.user_ids) + "</strong> | Not found: <strong style=\"" + (d.unknown_ids > 0 ? "color:#d63638" : "") + "\">" + esc(d.unknown_ids) + "</strong> | Custom entries: <strong>" + esc(d.custom || 0) + "</strong></p>";
							if (d.flagged && d.flagged.length) {
								// Group flagged items by user_id
								var userGroups = {};
								d.flagged.forEach(function(item) {
									var key = item.user_id || ("unknown_" + item.post_id);
									if (!userGroups[key]) {
										userGroups[key] = {
											user_id: item.user_id || 0,
											user_name: item.user_name || "Unknown",
											roles: item.roles || "",
											refs: []
										};
									}
									userGroups[key].refs.push(item);
								});

								html += "<div style=\"font-size:12px;margin-top:8px;border:2px solid #d63638;padding:12px;background:#fef1f1\">";
								html += "<strong style=\"color:#d63638\">Flagged -- " + d.flagged.length + " references across " + Object.keys(userGroups).length + " user(s) still pointing to WP users:</strong>";
								html += "<p style=\"margin:6px 0 10px;color:#555\">For each user below, search for the matching Person post. Then click <strong>Apply All Mappings</strong> to swap them.</p>";
								html += "<table style=\"width:100%;border-collapse:collapse;margin-top:6px\">";
								html += "<thead><tr style=\"text-align:left;border-bottom:2px solid #ccc\"><th style=\"padding:4px 8px\">WP User</th><th style=\"padding:4px 8px\">Roles</th><th style=\"padding:4px 8px\">Refs</th><th style=\"padding:4px 8px\">Map to Person</th></tr></thead><tbody>";

								Object.keys(userGroups).forEach(function(key) {
									var g = userGroups[key];
									html += "<tr data-user-id=\"" + esc(String(g.user_id)) + "\" style=\"border-bottom:1px solid #ddd\">";
									html += "<td style=\"padding:6px 8px\">" + esc(g.user_name) + " <span style=\"color:#888\">(#" + esc(String(g.user_id)) + ")</span>";
									if (g.user_id) html += " <a href=\"" + ajaxurl.replace("/admin-ajax.php", "/user-edit.php?user_id=" + g.user_id) + "\" target=\"_blank\" style=\"font-size:11px\">view</a>";
									html += "</td>";
									html += "<td style=\"padding:6px 8px\">" + (g.roles ? esc(g.roles) : "<em style=\"color:#999\">none</em>") + "</td>";
									html += "<td style=\"padding:6px 8px\">" + g.refs.length + " post(s)</td>";
									html += "<td style=\"padding:6px 8px\">";
									html += "<div style=\"position:relative;display:inline-block;width:280px\">";
									html += "<input type=\"text\" class=\"pmig-resolve-search\" data-user-id=\"" + esc(String(g.user_id)) + "\" placeholder=\"Search person posts...\" value=\"" + esc(g.user_name) + "\" style=\"width:100%;padding:4px 6px\">";
									html += "<div class=\"pmig-resolve-results\" style=\"display:none;position:absolute;z-index:100;background:#fff;border:1px solid #ccc;max-height:200px;overflow-y:auto;width:100%;box-shadow:0 2px 6px rgba(0,0,0,.15)\"></div>";
									html += "<input type=\"hidden\" class=\"pmig-resolve-post-id\" data-user-id=\"" + esc(String(g.user_id)) + "\" value=\"\">";
									html += "<span class=\"pmig-resolve-chosen\" style=\"display:none;margin-left:6px;color:#00a32a\"></span>";
									html += "</div>";
									html += "</td></tr>";

									// Expandable ref list
									html += "<tr style=\"border-bottom:1px solid #eee\"><td colspan=\"4\" style=\"padding:0 8px 6px\">";
									html += "<details><summary style=\"cursor:pointer;font-size:11px;color:#666\">" + g.refs.length + " affected post(s)</summary>";
									html += "<div style=\"font-size:11px;margin-top:2px;padding:4px;background:#fff\">";
									g.refs.forEach(function(item) {
										html += "<div>" + esc(item.text) + " &mdash; <a href=\"" + ajaxurl.replace("/admin-ajax.php", "/post.php?post=" + item.post_id + "&action=edit") + "\" target=\"_blank\">Edit</a></div>";
									});
									html += "</div></details></td></tr>";
								});

								html += "</tbody></table>";
								html += "<div style=\"margin-top:12px;text-align:right\">";
								html += "<input type=\"button\" id=\"pmig-resolve-apply\" class=\"button button-primary\" value=\"Apply All Mappings\" style=\"margin-right:8px\">";
								html += "<span id=\"pmig-resolve-status\" style=\"color:#555\"></span>";
								html += "</div></div>";
							}
							if (d.details && d.details.length) {
								html += "<details style=\"margin-top:8px\"><summary style=\"cursor:pointer;font-size:12px\">Sample details (" + d.details.length + " entries)</summary>";
								html += "<div style=\"font-size:12px;max-height:300px;overflow-y:auto;margin-top:4px;border:1px solid #ddd;padding:8px;background:#f9f9f9\">";
								d.details.forEach(function(line) { html += "<div>" + esc(line) + "</div>"; });
								html += "</div></details>";
							}
							html += "</div>";
							var $step = $("#pmig-swap-verify-btn").closest(".pmig-step");
							$step.find(".pmig-step-result").remove();
							$step.append("<div class=\"pmig-step-result\">" + html + "</div>");
							// Auto-search for each flagged user name
							$step.find(".pmig-resolve-search").each(function() {
								$(this).trigger("input");
							});
						} else {
							alert("Error: " + ((response.data && response.data.error_message) || "Unknown"));
						}
					},
					error: function() {
						$("#pmig-swap-verify-btn").prop("disabled", false).val("Verify");
						alert("AJAX error.");
					}
				});
			});

			// Resolve flagged: search person posts
			var resolveSearchTimer = null;
			$(document).on("input", ".pmig-resolve-search", function() {
				var $input = $(this);
				var $wrapper = $input.parent();
				var $results = $wrapper.find(".pmig-resolve-results");
				var query = $input.val().trim();

				clearTimeout(resolveSearchTimer);
				if (query.length < 2) { $results.hide(); return; }

				resolveSearchTimer = setTimeout(function() {
					$.ajax({
						url: ajaxurl,
						method: "POST",
						data: { action: "person_migration_search_persons", nonce: nonce, search: query },
						success: function(response) {
							if (!response.success || !response.data.results.length) {
								$results.html("<div style=\"padding:6px;color:#999\">No results</div>").show();
								return;
							}
							var rhtml = "";
							response.data.results.forEach(function(p) {
								rhtml += "<div class=\"pmig-resolve-result-item\" data-post-id=\"" + p.id + "\" style=\"padding:6px 8px;cursor:pointer;border-bottom:1px solid #eee\">";
								rhtml += esc(p.title) + " <span style=\"color:#888\">(#" + p.id + ")</span></div>";
							});
							$results.html(rhtml).show();
						}
					});
				}, 300);
			});

			// Resolve flagged: select a person post from results
			$(document).on("click", ".pmig-resolve-result-item", function() {
				var $item = $(this);
				var postId = $item.data("post-id");
				var postTitle = $item.text();
				var $wrapper = $item.closest("td").find(".pmig-resolve-search").parent();
				var userId = $wrapper.find(".pmig-resolve-search").data("user-id");

				$wrapper.find(".pmig-resolve-search").val(postTitle).css("border-color", "#00a32a");
				$wrapper.find(".pmig-resolve-post-id").val(postId);
				$wrapper.find(".pmig-resolve-chosen").text("-> #" + postId).show();
				$wrapper.find(".pmig-resolve-results").hide();
			});

			// Resolve flagged: close dropdown when clicking outside
			$(document).on("click", function(e) {
				if (!$(e.target).closest(".pmig-resolve-search, .pmig-resolve-results").length) {
					$(".pmig-resolve-results").hide();
				}
			});

			// Resolve flagged: apply all mappings
			$(document).on("click", "#pmig-resolve-apply", function() {
				var mappings = [];
				$(".pmig-resolve-post-id").each(function() {
					var postId = parseInt($(this).val());
					var userId = parseInt($(this).data("user-id"));
					if (postId && userId) {
						mappings.push({ user_id: userId, person_post_id: postId });
					}
				});

				if (mappings.length === 0) {
					alert("No mappings selected. Search and select a person post for at least one user.");
					return;
				}

				var total = $(".pmig-resolve-post-id").length;
				if (mappings.length < total && !confirm(mappings.length + " of " + total + " users mapped. Unmapped users will be skipped. Continue?")) {
					return;
				}

				var $btn = $(this);
				$btn.prop("disabled", true).val("Applying...");
				$("#pmig-resolve-status").text("");

				$.ajax({
					url: ajaxurl,
					method: "POST",
					data: { action: "person_migration_resolve_flagged", nonce: nonce, mappings: JSON.stringify(mappings) },
					success: function(response) {
						$btn.prop("disabled", false).val("Apply All Mappings");
						if (response.success) {
							$("#pmig-resolve-status").html("<span style=\"color:#00a32a\">" + esc(response.data.message) + " Re-run Verify to confirm.</span>");
						} else {
							$("#pmig-resolve-status").html("<span style=\"color:#d63638\">Error: " + esc(response.data.error_message || "Unknown") + "</span>");
						}
					},
					error: function() {
						$btn.prop("disabled", false).val("Apply All Mappings");
						$("#pmig-resolve-status").html("<span style=\"color:#d63638\">AJAX error.</span>");
					}
				});
			});

			// Revert swap
			$("#pmig-swap-revert-btn").on("click", function() {
				if (!confirm("This will revert ACF field types back to User AND restore all original user IDs from backup. Continue?")) return;
				var $btn = $(this);
				$btn.prop("disabled", true).val("Reverting field types...");

				// Step 1: Revert field types first
				$.ajax({
					url: ajaxurl,
					method: "POST",
					data: { action: "person_migration_update_field_types", nonce: nonce, direction: "to_user" },
					success: function(ftResponse) {
						// Step 2: Revert the swap data
						$btn.val("Reverting swap data...");
						$.ajax({
							url: ajaxurl,
							method: "POST",
							data: { action: "person_migration_revert_swap", nonce: nonce },
							success: function(response) {
								$btn.prop("disabled", false).val("Revert Swap");
								if (response.success) {
									var d = response.data;
									var ft = ftResponse.success ? ftResponse.data : null;
									var html = "<div class=\"notice notice-warning\" style=\"margin:12px 0\">";
									html += "<p><strong>Full revert complete.</strong></p>";
									if (ft) {
										html += "<p>ACF field types: " + esc(ft.updated) + " fields reverted to User type.</p>";
									}
									html += "<p>Repeater data: restored " + esc(d.restored) + " values across " + esc(d.posts_touched) + " posts.</p>";
									html += "</div>";
									var $step = $btn.closest(".pmig-step");
									$step.find(".pmig-step-result").remove();
									$step.append("<div class=\"pmig-step-result\">" + html + "</div>");
								} else {
									alert("Swap revert error: " + ((response.data && response.data.error_message) || "Unknown"));
								}
							},
							error: function() {
								$btn.prop("disabled", false).val("Revert Swap");
								alert("AJAX error reverting swap data.");
							}
						});
					},
					error: function() {
						$btn.prop("disabled", false).val("Revert Swap");
						alert("AJAX error reverting field types.");
					}
				});
			});

			// Update ACF field types
			function doFieldTypeAction(direction, btn, label) {
				$(btn).prop("disabled", true).val("Working...");
				$.ajax({
					url: ajaxurl,
					method: "POST",
					data: { action: "person_migration_update_field_types", nonce: nonce, direction: direction },
					success: function(response) {
						$(btn).prop("disabled", false).val(label);
						if (response.success) {
							var d = response.data;
							var cssClass = direction === "to_post_object" ? "notice-success" : "notice-warning";
							var html = "<div class=\"notice " + cssClass + "\" style=\"margin:12px 0\">";
							html += "<p><strong>" + (direction === "to_post_object" ? "Field types updated." : "Field types reverted.") + "</strong> " + esc(d.updated) + " fields changed.</p>";
							if (d.log && d.log.length) {
								html += "<div style=\"font-size:12px;max-height:200px;overflow-y:auto;margin-top:8px;border:1px solid #ddd;padding:8px;background:#f9f9f9\">";
								d.log.forEach(function(line) { html += "<div>" + esc(line) + "</div>"; });
								html += "</div>";
							}
							html += "</div>";
							var $step = $(btn).closest(".pmig-step");
							$step.find(".pmig-step-result").remove();
							$step.append("<div class=\"pmig-step-result\">" + html + "</div>");
						} else {
							alert("Error: " + ((response.data && response.data.error_message) || "Unknown"));
						}
					},
					error: function() {
						$(btn).prop("disabled", false).val(label);
						alert("AJAX error.");
					}
				});
			}

			$("#pmig-field-types-btn").on("click", function() {
				if (!confirm("Change repeater user sub-fields from User type to Post Object (caes_hub_person)? This affects the admin editor pickers.")) return;
				doFieldTypeAction("to_post_object", this, "Update to Post Object");
			});

			$("#pmig-field-types-revert-btn").on("click", function() {
				if (!confirm("Revert repeater user sub-fields back to User type? This restores the original admin editor pickers.")) return;
				doFieldTypeAction("to_user", this, "Revert to User");
			});

			// Repopulate flat meta
			$("#pmig-flat-meta-btn").on("click", function() {
				var dryRun = $("#pmig-dry-run").is(":checked");
				var msg = dryRun
					? "Start a DRY RUN flat meta repopulation? No data will be written."
					: "Repopulate flat meta fields with CPT post IDs? Originals will be backed up.";
				doAction("person_migration_flat_meta", { dry_run: dryRun ? 1 : 0 }, msg);
			});

			// Revert flat meta
			$("#pmig-revert-btn").on("click", function() {
				doAction("person_migration_revert_flat_meta", {}, "Revert flat meta fields to their backed-up values?");
			});

			// User Feed block swap helpers
			function pmigUserFeedRequest(dryRun) {
				var btn = dryRun ? $("#pmig-user-feed-scan-btn") : $("#pmig-user-feed-swap-btn");
				btn.prop("disabled", true).val(dryRun ? "Scanning..." : "Swapping...");
				$.ajax({
					url: ajaxurl,
					method: "POST",
					timeout: 120000,
					data: { action: "person_migration_user_feed_swap", nonce: nonce, dry_run: dryRun ? 1 : 0 },
					success: function(response) {
						btn.prop("disabled", false).val(dryRun ? "Dry Run" : "Run");
						if (response.success) {
							var d = response.data;
							var cls = dryRun ? "notice-info" : "notice-success";
							var html = "<div class=\"notice " + cls + "\" style=\"margin:12px 0\"><p>" + esc(d.affected) + " post(s) affected, " + esc(d.swapped) + " ID(s) swapped, " + esc(d.not_in_map) + " ID(s) not in map.</p>";
							if (d.details && d.details.length) {
								html += "<table class=\"widefat\" style=\"margin-top:8px\"><thead><tr><th>Post</th><th>Changes</th></tr></thead><tbody>";
								d.details.forEach(function(r) {
									html += "<tr><td><a href=\"" + esc(r.edit_url) + "\" target=\"_blank\">" + esc(r.title) + "</a> <span style=\"color:#999\">(#" + esc(r.post_id) + ")</span></td><td style=\"font-size:12px\">" + esc(r.summary) + "</td></tr>";
								});
								html += "</tbody></table>";
							}
							if (d.warnings && d.warnings.length) {
								html += "<ul style=\"font-size:12px;margin-top:4px;color:#b32d2e\">";
								d.warnings.forEach(function(w) { html += "<li>" + esc(w) + "</li>"; });
								html += "</ul>";
							}
							html += "</div>";
							var $step = btn.closest(".pmig-step");
							$step.find(".pmig-step-result").remove();
							$step.append("<div class=\"pmig-step-result\">" + html + "</div>");
						} else {
							alert("Error: " + ((response.data && response.data.error_message) ? response.data.error_message : "Unknown error"));
						}
					},
					error: function() {
						btn.prop("disabled", false).val(dryRun ? "Dry Run" : "Run");
						alert("AJAX error.");
					}
				});
			}

			$("#pmig-user-feed-scan-btn").on("click", function() { pmigUserFeedRequest(true); });
			$("#pmig-user-feed-swap-btn").on("click", function() {
				if (!confirm("Swap userIds in all user-feed blocks? Original post content will be backed up.")) return;
				pmigUserFeedRequest(false);
			});
			$("#pmig-user-feed-revert-btn").on("click", function() {
				if (!confirm("Revert all user-feed block changes? This restores post_content from the backup.")) return;
				$(this).prop("disabled", true).val("Reverting...");
				var self = this;
				$.ajax({
					url: ajaxurl,
					method: "POST",
					timeout: 120000,
					data: { action: "person_migration_user_feed_revert", nonce: nonce },
					success: function(response) {
						$(self).prop("disabled", false).val("Revert");
						if (response.success) {
							var d = response.data;
							var $step = $(self).closest(".pmig-step");
							$step.find(".pmig-step-result").remove();
							$step.append("<div class=\"pmig-step-result\"><div class=\"notice notice-success\" style=\"margin:12px 0\"><p>" + esc(d.reverted) + " post(s) reverted.</p></div></div>");
						} else {
							alert("Error: " + ((response.data && response.data.error_message) ? response.data.error_message : "Unknown error"));
						}
					},
					error: function() { $(self).prop("disabled", false).val("Revert"); alert("AJAX error."); }
				});
			});

			// Link content managers
			$("#pmig-link-cm-btn").on("click", function() {
				if (!confirm("Link content manager WP accounts to their existing person posts by matching on personnel_id?")) return;
				$(this).prop("disabled", true).val("Linking...");
				$.ajax({
					url: ajaxurl,
					method: "POST",
					timeout: 120000,
					data: { action: "person_migration_link_content_managers", nonce: nonce },
					success: function(response) {
						$("#pmig-link-cm-btn").prop("disabled", false).val("Run");
						if (response.success) {
							var d = response.data;
							var html = "<div class=\"notice notice-success\" style=\"margin:12px 0\"><p>" + esc(d.linked) + " content managers linked, " + esc(d.skipped) + " skipped, " + esc(d.failed) + " failed.</p>";
							if (d.errors && d.errors.length) {
								html += "<ul style=\"font-size:12px;margin-top:4px\">";
								d.errors.forEach(function(e) { html += "<li>" + esc(e) + "</li>"; });
								html += "</ul>";
							}
							html += "</div>";
							if (d.needs_manual && d.needs_manual.length) {
								html += "<div class=\"notice notice-warning\" style=\"margin:12px 0\"><p><strong>Manual linking needed for " + d.needs_manual.length + " content manager(s):</strong></p>";
								html += "<p style=\"font-size:12px;color:#666\">For each CM below, click the Edit link for the correct person post, find the <code>linked_wp_user</code> field, and select their WP user account from the dropdown.</p>";
								html += "<table class=\"widefat\" style=\"margin-top:8px\"><thead><tr><th>Content Manager</th><th>Reason</th><th>Candidate Person Posts</th></tr></thead><tbody>";
								d.needs_manual.forEach(function(m) {
									html += "<tr><td>" + esc(m.name) + " <span style=\"color:#999\">(user #" + esc(m.user_id) + ")</span></td>";
									html += "<td style=\"font-size:12px\">" + esc(m.reason) + "</td><td>";
									if (m.candidates && m.candidates.length) {
										m.candidates.forEach(function(c, i) {
											if (i > 0) html += " | ";
											html += "<a href=\"" + esc(c.edit_url) + "\" target=\"_blank\">" + esc(c.title) + "</a>";
										});
									} else {
										html += "<em>No candidates found</em>";
									}
									html += "</td></tr>";
								});
								html += "</tbody></table></div>";
							}
							// Show result right after the link CM step card
							var $step = $("#pmig-link-cm-btn").closest(".pmig-step");
							$step.find(".pmig-step-result").remove();
							$step.append("<div class=\"pmig-step-result\">" + html + "</div>");
						} else {
							var msg = (response.data && response.data.error_message) ? response.data.error_message : "Unknown error";
							alert("Error: " + msg);
						}
					},
					error: function() {
						$("#pmig-link-cm-btn").prop("disabled", false).val("Run");
						alert("AJAX error.");
					}
				});
			});

			// Stop
			$("#pmig-stop-btn").on("click", function() {
				doAction("person_migration_stop", {}, "Stop the current job? Progress will be saved.");
			});

			// Resume
			$("#pmig-resume-btn").on("click", function() {
				doAction("person_migration_resume", {});
			});

			// Single-user migration
			$("#pmig-single-form").on("submit", function(e) {
				e.preventDefault();
				var userId = $("#pmig-single-user-id").val().trim();
				if (!userId) { alert("Please enter a User ID."); return; }
				var dryRun = $("#pmig-single-dry-run").is(":checked") ? 1 : 0;
				var $btn = $(this).find("input[type=submit]");
				$btn.prop("disabled", true).val("Migrating...");
				$("#pmig-single-result").html("<p><span class=\"dashicons dashicons-update pmig-spin\"></span> Processing...</p>");
				$.ajax({
					url: ajaxurl,
					method: "POST",
					timeout: 60000,
					data: { action: "person_migration_single", nonce: nonce, user_id: userId, dry_run: dryRun },
					success: function(response) {
						$btn.prop("disabled", false).val("Migrate Single User");
						if (response.success) {
							var d = response.data;
							var sc = d.status === "ok" ? "#46b450" : (d.status === "failed" ? "#dc3232" : "#999");
							var html = "<div style=\"padding:12px;background:#fff;border:1px solid #ccd0d4;border-radius:4px\">";
							html += "<strong style=\"color:" + sc + "\">" + esc(d.status.toUpperCase()) + "</strong>&ensp;";
							html += esc(d.display_name) + " (User " + esc(d.user_id) + ")";
							if (d.post_id) html += " &rarr; Post " + esc(d.post_id);
							html += "<span style=\"color:#666;font-size:12px\"> --- " + esc(d.fields_written) + " fields written</span>";
							if (d.error_message) html += "<div style=\"color:#dc3232;margin-top:6px;font-size:12px\">" + esc(d.error_message) + "</div>";
							if (d.field_log && d.field_log.length) {
								html += "<div style=\"margin-top:8px;font-size:11px;font-family:monospace;max-height:300px;overflow-y:auto\">";
								d.field_log.forEach(function(l) { html += "<div style=\"padding:2px 0;color:#0073aa\">" + esc(l) + "</div>"; });
								html += "</div>";
							}
							html += "</div>";
							$("#pmig-single-result").html(html);
						} else {
							var msg = (response.data && response.data.error_message) ? response.data.error_message : JSON.stringify(response.data);
							$("#pmig-single-result").html("<div class=\"notice notice-error\"><p>" + esc(msg) + "</p></div>");
						}
					},
					error: function(xhr, status, error) {
						$btn.prop("disabled", false).val("Migrate Single User");
						$("#pmig-single-result").html("<div class=\"notice notice-error\"><p>AJAX error: " + esc(error) + "</p></div>");
					}
				});
			});

			// Person CPT count audit (inline buttons)
			$(".pmig-count-audit-inline-btn").on("click", function() {
				var $btn = $(this);
				var $results = $btn.closest(".pmig-verify-step").find(".pmig-count-audit-inline-results");
				$btn.prop("disabled", true).val("Auditing...");
				$.ajax({
					url: ajaxurl,
					method: "POST",
					data: { action: "person_migration_count_audit", nonce: nonce },
					success: function(response) {
						$btn.prop("disabled", false).val("Run Count Audit");
						if (response.success) {
							var d = response.data;
							var status = d.diff === 0 ? "color:#46b450;font-weight:600" : "color:#d63638;font-weight:600";
							var html = "<table class=\"widefat\" style=\"max-width:500px\">";
							html += "<tr><td>Users in migration map</td><td><strong>" + d.total_migrated + "</strong></td></tr>";
							html += "<tr><td>Unique post IDs in map</td><td><strong>" + d.unique_post_ids + "</strong></td></tr>";
							html += "<tr><td>Expected People posts</td><td><strong>" + d.expected + "</strong></td></tr>";
							html += "<tr><td>Actual People posts (published/draft/private)</td><td><strong>" + d.actual + "</strong></td></tr>";
							if (d.trashed > 0) {
								html += "<tr><td>Trashed People posts</td><td><strong>" + d.trashed + "</strong></td></tr>";
							}
							html += "<tr><td>Actual + Trashed</td><td><strong>" + (d.actual + d.trashed) + "</strong></td></tr>";
							html += "<tr><td>Difference</td><td style=\"" + status + "\">" + (d.diff === 0 ? "0 (match)" : d.diff) + "</td></tr>";
							html += "</table>";
							if (d.missing_posts && d.missing_posts.length > 0) {
								html += "<h4 style=\"margin:12px 0 4px\">Missing posts (" + d.missing_posts.length + " post IDs in map that no longer exist):</h4>";
								html += "<table class=\"widefat\" style=\"max-width:600px;font-size:12px\">";
								html += "<thead><tr><th>Post ID</th><th>Mapped from user ID(s)</th></tr></thead>";
								d.missing_posts.forEach(function(m) {
									html += "<tr><td>#" + m.post_id + "</td><td>" + m.user_ids.join(", ") + "</td></tr>";
								});
								html += "</table>";
							}
							if (d.diff !== 0 && (!d.missing_posts || d.missing_posts.length === 0)) {
								html += "<p style=\"color:#d63638\">Expected and actual counts do not match. Difference of <strong>" + d.diff + "</strong>. Check for manually created or deleted posts.</p>";
							}
							$results.html(html);
						} else {
							$results.html("<p style=\"color:red\">" + (response.data?.error_message || "Error") + "</p>");
						}
					},
					error: function() {
						$btn.prop("disabled", false).val("Run Count Audit");
						$results.html("<p style=\"color:red\">Request failed.</p>");
					}
				});
			});

			// Flat meta ID audit (inline)
			$(".pmig-flat-meta-audit-inline-btn").on("click", function() {
				var $btn = $(this);
				var $results = $btn.closest(".pmig-verify-step").find(".pmig-flat-meta-audit-inline-results");
				$btn.prop("disabled", true).val("Scanning...");
				$.ajax({
					url: ajaxurl,
					method: "POST",
					timeout: 300000,
					data: { action: "person_migration_flat_meta_audit", nonce: nonce },
					success: function(response) {
						$btn.prop("disabled", false).val("Run Flat Meta Audit");
						if (response.success) {
							var d = response.data;
							var html = "<p>Checked <strong>" + d.ids_checked + "</strong> IDs across <strong>" + d.posts_checked + "</strong> content items. <strong>" + d.cpt_ids + "</strong> are CPT post IDs.</p>";
							if (d.user_ids.length === 0 && d.not_found.length === 0) {
								html += "<div class=\"notice notice-success\" style=\"margin:8px 0\"><p>All flat meta IDs are valid CPT post IDs.</p></div>";
							} else {
								if (d.user_ids.length > 0) {
									html += "<div class=\"notice notice-error\" style=\"margin:8px 0\"><p><strong>" + d.user_ids.length + "</strong> WP user IDs found (should be CPT post IDs):</p></div>";
									html += "<table class=\"widefat striped\" style=\"font-size:12px\"><thead><tr><th>Content</th><th>Field</th><th>ID</th><th>User Name</th></tr></thead><tbody>";
									d.user_ids.forEach(function(u) {
										html += "<tr><td><a href=\"post.php?post=" + u.content_id + "&action=edit\">" + $("<span>").text(u.content_title).html() + "</a></td>";
										html += "<td>" + $("<span>").text(u.field).html() + "</td>";
										html += "<td style=\"color:#d63638\">" + u.id + "</td>";
										html += "<td>" + $("<span>").text(u.name).html() + "</td></tr>";
									});
									html += "</tbody></table>";
								}
								if (d.not_found.length > 0) {
									html += "<div class=\"notice notice-warning\" style=\"margin:8px 0\"><p><strong>" + d.not_found.length + "</strong> IDs that resolve to neither a CPT post nor a user:</p></div>";
									html += "<table class=\"widefat striped\" style=\"font-size:12px\"><thead><tr><th>Content</th><th>Field</th><th>ID</th></tr></thead><tbody>";
									d.not_found.forEach(function(n) {
										html += "<tr><td><a href=\"post.php?post=" + n.content_id + "&action=edit\">" + $("<span>").text(n.content_title).html() + "</a></td>";
										html += "<td>" + $("<span>").text(n.field).html() + "</td>";
										html += "<td style=\"color:#b32d2e\">" + n.id + "</td></tr>";
									});
									html += "</tbody></table>";
								}
							}
							$results.html(html);
						} else {
							$results.html("<p style=\"color:red\">" + (response.data?.error_message || "Error") + "</p>");
						}
					},
					error: function() {
						$btn.prop("disabled", false).val("Run Flat Meta Audit");
						$results.html("<p style=\"color:red\">Request failed or timed out.</p>");
					}
				});
			});

			// Roleless users audit
			$("#pmig-roleless-audit-btn").on("click", function() {
				var $btn = $(this);
				$btn.prop("disabled", true).val("Scanning...");
				$("#pmig-roleless-results").html("<p>Scanning all posts for roleless user references... this may take a moment.</p>");
				$.ajax({
					url: ajaxurl,
					method: "POST",
					data: { action: "person_migration_roleless_audit", nonce: nonce },
					timeout: 300000,
					success: function(response) {
						$btn.prop("disabled", false).val("Scan for Roleless Users");
						if (!response.success) {
							$("#pmig-roleless-results").html("<p style=\"color:#d63638\">Error: " + esc(response.data.error_message || "Unknown") + "</p>");
							return;
						}
						var d = response.data;
						var html = "<div class=\"notice notice-info\" style=\"margin:0\">";
						html += "<p><strong>" + esc(String(d.total_roleless)) + "</strong> total roleless users | <strong style=\"color:#d63638\">" + esc(String(d.total_referenced)) + "</strong> referenced in content | <strong>" + esc(String(d.total_unreferenced)) + "</strong> not referenced</p>";

						if (d.referenced.length) {
							html += "<h3 style=\"margin:12px 0 6px;font-size:13px\">Referenced in content (need attention):</h3>";
							html += "<table style=\"width:100%;border-collapse:collapse;font-size:12px\">";
							html += "<thead><tr style=\"border-bottom:2px solid #ccc;text-align:left\"><th style=\"padding:4px 8px\">User</th><th style=\"padding:4px 8px\">Login</th><th style=\"padding:4px 8px\">Email</th><th style=\"padding:4px 8px\">Content Refs</th></tr></thead><tbody>";
							d.referenced.forEach(function(u) {
								html += "<tr style=\"border-bottom:1px solid #eee\">";
								html += "<td style=\"padding:6px 8px\">" + esc(u.display_name) + " <span style=\"color:#888\">(#" + esc(String(u.user_id)) + ")</span>";
								html += " <a href=\"" + ajaxurl.replace("/admin-ajax.php", "/user-edit.php?user_id=" + u.user_id) + "\" target=\"_blank\" style=\"font-size:11px\">edit</a></td>";
								html += "<td style=\"padding:6px 8px\">" + esc(u.user_login) + "</td>";
								html += "<td style=\"padding:6px 8px\">" + esc(u.user_email) + "</td>";
								html += "<td style=\"padding:6px 8px\">";
								html += "<details><summary style=\"cursor:pointer\">" + u.refs.length + " reference(s)</summary>";
								html += "<div style=\"margin-top:4px\">";
								u.refs.forEach(function(r) {
									html += "<div>" + esc(r.post_type) + " #" + esc(String(r.post_id)) + " \"" + esc(r.post_title) + "\" > " + esc(r.field) + "[" + r.index + "]";
									html += " <a href=\"" + ajaxurl.replace("/admin-ajax.php", "/post.php?post=" + r.post_id + "&action=edit") + "\" target=\"_blank\">edit</a></div>";
								});
								html += "</div></details></td></tr>";
							});
							html += "</tbody></table>";
						}

						if (d.unreferenced.length) {
							html += "<details style=\"margin-top:12px\"><summary style=\"cursor:pointer;font-size:12px\">" + esc(String(d.unreferenced.length)) + " roleless users NOT referenced in any content</summary>";
							html += "<div style=\"font-size:11px;max-height:300px;overflow-y:auto;margin-top:4px;border:1px solid #ddd;padding:8px;background:#f9f9f9\">";
							d.unreferenced.forEach(function(u) {
								html += "<div>" + esc(u.display_name) + " (#" + esc(String(u.user_id)) + ") - " + esc(u.user_login) + " - " + esc(u.user_email);
								html += " <a href=\"" + ajaxurl.replace("/admin-ajax.php", "/user-edit.php?user_id=" + u.user_id) + "\" target=\"_blank\">edit</a></div>";
							});
							html += "</div></details>";
						}

						html += "</div>";
						$("#pmig-roleless-results").html(html);
					},
					error: function() {
						$btn.prop("disabled", false).val("Scan for Roleless Users");
						$("#pmig-roleless-results").html("<p style=\"color:#d63638\">AJAX error or timeout.</p>");
					}
				});
			});

			// Delete all person posts (batched)
			$("#pmig-delete-all-btn").on("click", function() {
				if (!confirm("This will permanently delete ALL caes_hub_person posts and clear the migration lookup map. This cannot be undone. Are you sure?")) return;
				if (!confirm("Are you REALLY sure? This will delete every person CPT post.")) return;
				var $btn = $(this);
				var totalDeleted = 0;
				$btn.prop("disabled", true);
				$("#pmig-current-panel").prepend("<div id=\"pmig-delete-progress\" class=\"notice notice-warning\" style=\"margin:12px 0\"><p><span class=\"dashicons dashicons-update pmig-spin\"></span> Deleting... <span id=\"pmig-delete-count\">0</span> deleted so far...</p></div>");

				function deleteBatch() {
					$.ajax({
						url: ajaxurl,
						method: "POST",
						timeout: 120000,
						data: { action: "person_migration_delete_all", nonce: nonce },
						success: function(response) {
							if (response.success) {
								totalDeleted += response.data.deleted_batch;
								$("#pmig-delete-count").text(totalDeleted);
								if (!response.data.done) {
									$("#pmig-delete-progress p").html("<span class=\"dashicons dashicons-update pmig-spin\"></span> Deleting... " + esc(String(totalDeleted)) + " deleted, " + esc(String(response.data.remaining)) + " remaining...");
									deleteBatch();
								} else {
									$btn.prop("disabled", false);
									$("#pmig-delete-progress").html("<p>" + esc(String(totalDeleted)) + " person posts permanently deleted. Lookup map and migration state cleared.</p>");
								}
							} else {
								$btn.prop("disabled", false);
								var msg = (response.data && response.data.error_message) ? response.data.error_message : "Unknown error";
								$("#pmig-delete-progress").html("<p>Error: " + esc(msg) + " (" + esc(String(totalDeleted)) + " deleted before error)</p>");
							}
						},
						error: function() {
							$btn.prop("disabled", false);
							$("#pmig-delete-progress").html("<p>AJAX error. " + esc(String(totalDeleted)) + " deleted before error. You can click again to continue.</p>");
						}
					});
				}
				deleteBatch();
			});

			// Checklist toggles
			$(".pmig-checklist-toggle").on("change", function() {
				var step = $(this).data("step");
				var checked = $(this).is(":checked") ? 1 : 0;
				$.ajax({
					url: ajaxurl,
					method: "POST",
					data: { action: "person_migration_checklist_toggle", nonce: nonce, step: step, checked: checked },
					success: function(response) {
						if (response.success) {
							// Refresh to update prerequisite states
							location.reload();
						}
					}
				});
			});

			// Reset everything
			$("#pmig-reset-all-btn").on("click", function() {
				if (!confirm("This will clear ALL migration state: checklist progress, lookup map, and job state. Are you sure?")) return;
				if (!confirm("This does NOT delete person posts (use the other button for that). It resets the dashboard to a fresh state. Continue?")) return;
				var $btn = $(this);
				$btn.prop("disabled", true).val("Resetting...");
				$.ajax({
					url: ajaxurl,
					method: "POST",
					data: { action: "person_migration_reset_all", nonce: nonce },
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert("Error: " + (response.data && response.data.error_message || "Unknown"));
							$btn.prop("disabled", false).val("Reset Everything");
						}
					},
					error: function() {
						alert("AJAX error.");
						$btn.prop("disabled", false).val("Reset Everything");
					}
				});
			});

			pollStatus();
		});
	');
}

// ============================================================
// Admin page render
// ============================================================

function person_migration_get_checklist() {
	$checklist = get_option(PERSON_MIGRATION_CHECKLIST_KEY, array());
	return is_array($checklist) ? $checklist : array();
}

function person_migration_get_dashboard_status() {
	$map       = person_migration_get_map();
	$map_count = count($map);
	$eligible  = person_migration_count_eligible_users();
	$state     = person_migration_get_state();
	$checklist = person_migration_get_checklist();

	$person_count = 0;
	$counts = wp_count_posts('caes_hub_person');
	if ($counts && isset($counts->publish)) {
		$person_count = (int)$counts->publish;
	}

	// Detect linked content managers
	$cm_linked = 0;
	$cm_total  = 0;
	$cms = get_users(array('role__in' => array('content_manager', 'event_submitter', 'event_approver'), 'fields' => 'all', 'number' => -1));
	$cms = array_filter($cms, function($u) { return !in_array($u->user_login, array('ashwhee'), true); });
	$cm_total = count($cms);
	foreach ($cms as $cm_obj) {
		$cm_id = $cm_obj->ID;
		// Check if any person post has linked_wp_user set to this CM
		$linked_posts = get_posts(array(
			'post_type' => 'caes_hub_person', 'post_status' => 'publish',
			'meta_key' => 'linked_wp_user', 'meta_value' => $cm_id,
			'posts_per_page' => 1, 'fields' => 'ids',
		));
		if (!empty($linked_posts)) $cm_linked++;
	}

	return array(
		'eligible'      => $eligible,
		'map_count'     => $map_count,
		'person_count'  => $person_count,
		'state'         => $state,
		'checklist'     => $checklist,
		'cm_total'      => $cm_total,
		'cm_linked'     => $cm_linked,
	);
}

function person_migration_render_page() {
	$ds           = person_migration_get_dashboard_status();
	$map_count    = $ds['map_count'];
	$eligible     = $ds['eligible'];
	$person_count = $ds['person_count'];
	$checklist    = $ds['checklist'];
	$cm_total     = $ds['cm_total'];
	$cm_linked    = $ds['cm_linked'];

	// Determine step statuses
	$step5_done  = $person_count > 0 && $map_count > 0;
	$step6_done  = !empty($checklist['step6']);
	$step7_done  = !empty($checklist['step7']);
	$step8a_done = !empty($checklist['step8a']);
	$step8_done  = !empty($checklist['step8']);
	$step9_done  = !empty($checklist['step9']);

	?>
	<div class="wrap">
		<h1>Person CPT Migration Dashboard <small style="font-size:12px;color:#888">v2025.03.18b</small></h1>
		<p>Tracks the full migration of <code>personnel_user</code> and <code>expert_user</code> WordPress users to <code>caes_hub_person</code> CPT posts.</p>

		<div class="pmig-wrapper">

			<!-- Job Status Panel (shared by all async operations) -->
			<div class="pmig-panel">
				<h2>Current Job Status</h2>
				<div id="pmig-current-panel"><p style="color:#999">Loading...</p></div>
				<div style="margin-top:8px">
					<input type="button" id="pmig-stop-btn" class="button" value="Stop" style="display:none">
					<input type="button" id="pmig-resume-btn" class="button button-secondary" value="Resume" style="display:none">
				</div>
			</div>

			<div class="pmig-panel" id="pmig-last-completed-section" style="display:none">
				<h2>Last Completed Run</h2>
				<div id="pmig-last-completed-panel"></div>
			</div>

			<!-- ============ PHASE 2: DATA MIGRATION ============ -->
			<div class="pmig-panel">
				<h2>Phase 2: Data Migration</h2>
				<p class="description" style="margin-bottom:16px">Execute these steps in order. Each step is only enabled when its prerequisites are met.</p>
				<div class="pmig-btn-group" style="margin-bottom:16px">
					<label><input type="checkbox" id="pmig-dry-run" checked> <strong>Dry Run</strong> (no data written)</label>
				</div>

				<!-- Step 5: Migrate Users to CPT -->
				<div class="pmig-step" style="margin-bottom:20px;padding:12px;border:1px solid #e5e5e5;border-radius:4px;<?php echo $step5_done ? 'border-left:4px solid #46b450;' : 'border-left:4px solid #0073aa;'; ?>">
					<div style="display:flex;justify-content:space-between;align-items:center">
						<div>
							<strong>Migrate Users to CPT</strong>
							<span class="pmig-status-badge <?php echo $step5_done ? 'complete' : 'idle'; ?>" style="margin-left:8px"><?php echo $step5_done ? 'Complete' : 'Not Started'; ?></span>
							<p class="description" style="margin:4px 0 0">Create a <code>caes_hub_person</code> post for every personnel_user and expert_user. Eligible: <strong><?php echo esc_html($eligible); ?></strong> | Migrated: <strong><?php echo esc_html($map_count); ?></strong> | CPT posts: <strong><?php echo esc_html($person_count); ?></strong></p>
						</div>
						<div class="pmig-btn-group">
							<input type="button" id="pmig-migrate-btn" class="button button-primary pmig-action-btn" value="Run">
						</div>
					</div>
					<!-- Single-user testing -->
					<details style="margin-top:10px">
						<summary style="cursor:pointer;font-size:12px;color:#0073aa">Single-user test</summary>
						<form id="pmig-single-form" style="margin-top:8px">
							<div style="margin-bottom:8px">
								<input type="number" id="pmig-single-user-id" placeholder="User ID" style="width:120px" min="1">
								<label style="margin-left:8px"><input type="checkbox" id="pmig-single-dry-run" checked> Dry Run</label>
								<input type="submit" class="button button-small" value="Migrate Single User">
							</div>
						</form>
						<div id="pmig-single-result"></div>
					</details>
				</div>

				<!-- Verify 5v: Person CPT Count Audit -->
				<div class="pmig-step pmig-verify-step" style="margin-bottom:20px;margin-left:24px;padding:10px 12px;border:1px solid #e5e5e5;border-radius:4px;border-left:4px solid #f0b849;background:#fffdf5">
					<div style="display:flex;justify-content:space-between;align-items:center">
						<div>
							<strong>Verify: Person CPT Count Audit</strong>
							<p class="description" style="margin:4px 0 0">Confirm map size matches People posts created. Difference should be 0.</p>
						</div>
						<div class="pmig-btn-group">
							<input type="button" class="button pmig-count-audit-inline-btn" value="Run Count Audit" <?php echo !$step5_done ? 'disabled' : ''; ?>>
						</div>
					</div>
					<div class="pmig-count-audit-inline-results" style="margin-top:8px"></div>
				</div>

				<!-- Step 6: Link Content Managers -->
				<div class="pmig-step" style="margin-bottom:20px;padding:12px;border:1px solid #e5e5e5;border-radius:4px;<?php echo $step6_done ? 'border-left:4px solid #46b450;' : 'border-left:4px solid #ccc;'; ?>">
					<div style="display:flex;justify-content:space-between;align-items:center">
						<div>
							<strong>Link Content Managers</strong>
							<span class="pmig-status-badge <?php echo $step6_done ? 'complete' : 'idle'; ?>" style="margin-left:8px"><?php echo $step6_done ? 'Complete' : 'Not Started'; ?></span>
							<p class="description" style="margin:4px 0 0">Match content manager WP accounts to person posts by personnel_id. CMs: <strong><?php echo esc_html($cm_total); ?></strong> | Linked: <strong><?php echo esc_html($cm_linked); ?></strong></p>
						</div>
						<div>
							<input type="button" id="pmig-link-cm-btn" class="button pmig-action-btn" value="Run" <?php echo !$step5_done ? 'disabled' : ''; ?>>
							<?php if ($step6_done): ?>
								<label style="font-size:11px;margin-left:4px"><input type="checkbox" class="pmig-checklist-toggle" data-step="step6" checked> Done</label>
							<?php else: ?>
								<label style="font-size:11px;margin-left:4px"><input type="checkbox" class="pmig-checklist-toggle" data-step="step6"> Mark done</label>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<!-- Step 7: Swap Repeater IDs -->
				<div class="pmig-step" style="margin-bottom:20px;padding:12px;border:1px solid #e5e5e5;border-radius:4px;<?php echo $step7_done ? 'border-left:4px solid #46b450;' : 'border-left:4px solid #ccc;'; ?>">
					<div style="display:flex;justify-content:space-between;align-items:center">
						<div>
							<strong>Swap Repeater IDs</strong>
							<span class="pmig-status-badge <?php echo $step7_done ? 'complete' : 'idle'; ?>" style="margin-left:8px"><?php echo $step7_done ? 'Complete' : 'Not Started'; ?></span>
							<p class="description" style="margin:4px 0 0">Replace user IDs with CPT post IDs in all repeater fields (authors, experts, translator, artists) across posts, pubs, and shorthand stories.</p>
						</div>
						<div class="pmig-btn-group">
							<input type="button" id="pmig-swap-btn" class="button pmig-action-btn" value="Run" <?php echo !$step5_done ? 'disabled' : ''; ?>>
							<input type="button" id="pmig-swap-verify-btn" class="button" value="Verify" <?php echo !$step5_done ? 'disabled' : ''; ?>>
							<input type="button" id="pmig-swap-revert-btn" class="button" value="Revert Swap" <?php echo !$step5_done ? 'disabled' : ''; ?> style="color:#b32d2e">
							<?php if ($step7_done): ?>
								<label style="font-size:11px;margin-left:4px"><input type="checkbox" class="pmig-checklist-toggle" data-step="step7" checked> Done</label>
							<?php else: ?>
								<label style="font-size:11px;margin-left:4px"><input type="checkbox" class="pmig-checklist-toggle" data-step="step7"> Mark done</label>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<!-- Step 7b: Update ACF Field Types -->
				<?php $step7b_done = !empty($checklist['step7b']); ?>
				<div class="pmig-step" style="margin-bottom:20px;padding:12px;border:1px solid #e5e5e5;border-radius:4px;<?php echo $step7b_done ? 'border-left:4px solid #46b450;' : 'border-left:4px solid #ccc;'; ?>">
					<div style="display:flex;justify-content:space-between;align-items:center">
						<div>
							<strong>Update ACF Field Types</strong>
							<span class="pmig-status-badge <?php echo $step7b_done ? 'complete' : 'idle'; ?>" style="margin-left:8px"><?php echo $step7b_done ? 'Complete' : 'Not Started'; ?></span>
							<p class="description" style="margin:4px 0 0">Change repeater sub-fields from User picker to Person CPT picker. Run immediately after the swap so editors see the correct data.</p>
						</div>
						<div class="pmig-btn-group">
							<input type="button" id="pmig-field-types-btn" class="button" value="Update to Post Object" <?php echo !$step7_done ? 'disabled' : ''; ?>>
							<input type="button" id="pmig-field-types-revert-btn" class="button" value="Revert to User" <?php echo !$step7b_done ? 'disabled' : ''; ?> style="color:#b32d2e">
							<?php if ($step7b_done): ?>
								<label style="font-size:11px;margin-left:4px"><input type="checkbox" class="pmig-checklist-toggle" data-step="step7b" checked> Done</label>
							<?php else: ?>
								<label style="font-size:11px;margin-left:4px"><input type="checkbox" class="pmig-checklist-toggle" data-step="step7b"> Mark done</label>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<!-- Step 8a: Swap User Feed Block Attributes -->
				<div class="pmig-step" style="margin-bottom:20px;padding:12px;border:1px solid #e5e5e5;border-radius:4px;<?php echo $step8a_done ? 'border-left:4px solid #46b450;' : 'border-left:4px solid #ccc;'; ?>">
					<div style="display:flex;justify-content:space-between;align-items:center">
						<div>
							<strong>Swap User Feed Block Attributes</strong>
							<span class="pmig-status-badge <?php echo $step8a_done ? 'complete' : 'idle'; ?>" style="margin-left:8px"><?php echo $step8a_done ? 'Complete' : 'Not Started'; ?></span>
							<p class="description" style="margin:4px 0 0">Scan all post content for <code>caes-hub/user-feed</code> blocks and swap <code>userIds</code> from WP user IDs to CPT post IDs. Original post content is backed up before overwriting.</p>
						</div>
						<div class="pmig-btn-group">
							<input type="button" id="pmig-user-feed-scan-btn" class="button" value="Dry Run" <?php echo !$step7_done ? 'disabled' : ''; ?>>
							<input type="button" id="pmig-user-feed-swap-btn" class="button pmig-action-btn" value="Run" <?php echo !$step7_done ? 'disabled' : ''; ?>>
							<input type="button" id="pmig-user-feed-revert-btn" class="button" value="Revert" <?php echo !$step8a_done ? 'disabled' : ''; ?> style="color:#b32d2e">
							<?php if ($step8a_done): ?>
								<label style="font-size:11px;margin-left:4px"><input type="checkbox" class="pmig-checklist-toggle" data-step="step8a" checked> Done</label>
							<?php else: ?>
								<label style="font-size:11px;margin-left:4px"><input type="checkbox" class="pmig-checklist-toggle" data-step="step8a"> Mark done</label>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<!-- Step 9: Repopulate Flat Meta -->
				<div class="pmig-step" style="margin-bottom:20px;padding:12px;border:1px solid #e5e5e5;border-radius:4px;<?php echo $step9_done ? 'border-left:4px solid #46b450;' : 'border-left:4px solid #ccc;'; ?>">
					<div style="display:flex;justify-content:space-between;align-items:center">
						<div>
							<strong>Repopulate Flat Meta</strong>
							<span class="pmig-status-badge <?php echo $step9_done ? 'complete' : 'idle'; ?>" style="margin-left:8px"><?php echo $step9_done ? 'Complete' : 'Not Started'; ?></span>
							<p class="description" style="margin:4px 0 0">Rebuild all_author_ids and all_expert_ids with CPT post IDs. Originals are backed up for revert.</p>
						</div>
						<div class="pmig-btn-group">
							<input type="button" id="pmig-flat-meta-btn" class="button pmig-action-btn" value="Run" <?php echo !$step7_done ? 'disabled' : ''; ?>>
							<input type="button" id="pmig-revert-btn" class="button" value="Revert" <?php echo !$step9_done ? 'disabled' : ''; ?>>
							<?php if ($step9_done): ?>
								<label style="font-size:11px;margin-left:4px"><input type="checkbox" class="pmig-checklist-toggle" data-step="step9" checked> Done</label>
							<?php else: ?>
								<label style="font-size:11px;margin-left:4px"><input type="checkbox" class="pmig-checklist-toggle" data-step="step9"> Mark done</label>
							<?php endif; ?>
						</div>
					</div>
				</div>
				<!-- Verify 9v: Flat Meta ID Audit -->
				<div class="pmig-step pmig-verify-step" style="margin-bottom:20px;margin-left:24px;padding:10px 12px;border:1px solid #e5e5e5;border-radius:4px;border-left:4px solid #f0b849;background:#fffdf5">
					<div style="display:flex;justify-content:space-between;align-items:center">
						<div>
							<strong>Verify: Flat Meta ID Audit</strong>
							<p class="description" style="margin:4px 0 0">Confirm all_author_ids and all_expert_ids contain CPT post IDs, not WP user IDs.</p>
						</div>
						<div class="pmig-btn-group">
							<input type="button" class="button pmig-flat-meta-audit-inline-btn" value="Run Flat Meta Audit" <?php echo !$step9_done ? 'disabled' : ''; ?>>
						</div>
					</div>
					<div class="pmig-flat-meta-audit-inline-results" style="margin-top:8px"></div>
				</div>

				<!-- Flush Permalinks reminder -->
				<?php $step_flush_done = !empty($checklist['step_flush_permalinks']); ?>
				<div class="pmig-step" style="margin-bottom:20px;padding:12px;border:1px solid #e5e5e5;border-radius:4px;<?php echo $step_flush_done ? 'border-left:4px solid #46b450;' : 'border-left:4px solid #ccc;'; ?>">
					<div style="display:flex;justify-content:space-between;align-items:center">
						<div>
							<strong>Flush Permalinks</strong>
							<span class="pmig-status-badge <?php echo $step_flush_done ? 'complete' : 'idle'; ?>" style="margin-left:8px"><?php echo $step_flush_done ? 'Complete' : 'Not Started'; ?></span>
							<p class="description" style="margin:4px 0 0">Go to <strong>Settings &gt; Permalinks</strong> and click <strong>Save Changes</strong> so WordPress picks up the new CPT rewrite rules. Person profile URLs will 404 until this is done.</p>
						</div>
						<div>
							<?php if ($step_flush_done): ?>
								<label style="font-size:11px;margin-left:4px"><input type="checkbox" class="pmig-checklist-toggle" data-step="step_flush_permalinks" checked> Done</label>
							<?php else: ?>
								<label style="font-size:11px;margin-left:4px"><input type="checkbox" class="pmig-checklist-toggle" data-step="step_flush_permalinks"> Mark done</label>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<!-- Run WP-CLI personnel + Symplectic sync -->
				<?php $step_cli_sync_done = !empty($checklist['step_cli_sync']); ?>
				<div class="pmig-step" style="margin-bottom:20px;padding:12px;border:1px solid #e5e5e5;border-radius:4px;<?php echo $step_cli_sync_done ? 'border-left:4px solid #46b450;' : 'border-left:4px solid #ccc;'; ?>">
					<div style="display:flex;justify-content:space-between;align-items:center">
						<div>
							<strong>Run Personnel &amp; Symplectic Sync (WP-CLI)</strong>
							<span class="pmig-status-badge <?php echo $step_cli_sync_done ? 'complete' : 'idle'; ?>" style="margin-left:8px"><?php echo $step_cli_sync_done ? 'Complete' : 'Not Started'; ?></span>
							<p class="description" style="margin:4px 0 0">SSH into the site and run the imports detached so they survive disconnects. Watch via <code>tail -f sync.log</code> or the <strong>People CPT Data Sync</strong> dashboard.</p>
							<pre style="background:#f6f7f7;border:1px solid #ddd;padding:8px;margin:6px 0 0;font-size:11px;line-height:1.5;white-space:pre-wrap">cd ~/public
nohup wp caes person-sync all &gt; sync.log 2&gt;&amp;1 &amp;
tail -f sync.log

# Or run individually:
wp caes person-sync personnel
wp caes person-sync symplectic

# If a previous run is stuck:
wp caes person-sync reset</pre>
						</div>
						<div>
							<?php if ($step_cli_sync_done): ?>
								<label style="font-size:11px;margin-left:4px"><input type="checkbox" class="pmig-checklist-toggle" data-step="step_cli_sync" checked> Done</label>
							<?php else: ?>
								<label style="font-size:11px;margin-left:4px"><input type="checkbox" class="pmig-checklist-toggle" data-step="step_cli_sync"> Mark done</label>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<!-- Tag dept heads + Science You Can Trust feed -->
				<?php $step_dept_heads_feed_done = !empty($checklist['step_dept_heads_feed']); ?>
				<div class="pmig-step" style="margin-bottom:20px;padding:12px;border:1px solid #e5e5e5;border-radius:4px;<?php echo $step_dept_heads_feed_done ? 'border-left:4px solid #46b450;' : 'border-left:4px solid #ccc;'; ?>">
					<div style="display:flex;justify-content:space-between;align-items:center">
						<div>
							<strong>Tag Department Heads &amp; Update Science You Can Trust</strong>
							<span class="pmig-status-badge <?php echo $step_dept_heads_feed_done ? 'complete' : 'idle'; ?>" style="margin-left:8px"><?php echo $step_dept_heads_feed_done ? 'Complete' : 'Not Started'; ?></span>
							<p class="description" style="margin:4px 0 0">
								Tag each department head with a <code>person_tag</code> (e.g. <em>department-head</em>) via the Editorial section of their person edit screen.
								Then update the <a href="https://fieldreport.caes.uga.edu/publications/science-you-can-trust/" target="_blank" rel="noopener">Science You Can Trust page</a> to use a <strong>caes-hub/user-feed</strong> block in <em>By tag</em> mode pointing at that tag.
							</p>
						</div>
						<div>
							<?php if ($step_dept_heads_feed_done): ?>
								<label style="font-size:11px;margin-left:4px"><input type="checkbox" class="pmig-checklist-toggle" data-step="step_dept_heads_feed" checked> Done</label>
							<?php else: ?>
								<label style="font-size:11px;margin-left:4px"><input type="checkbox" class="pmig-checklist-toggle" data-step="step_dept_heads_feed"> Mark done</label>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>

			<!-- ============ PHASE 3: UPDATE SYNC INFRASTRUCTURE ============ -->
			<div class="pmig-panel">
				<h2>Phase 3: Update Sync Infrastructure</h2>
				<p class="description" style="margin:0 0 8px;color:#46b450;font-size:12px"><span class="dashicons dashicons-yes-alt" style="font-size:14px;vertical-align:middle"></span> Complete -- these are baked into the deployed theme code.</p>
				<?php
				$phase3_steps = array(
					'step10' => 'Rewrite sync_personnel_users() / sync_personnel_users2() to target CPT posts',
					'step11' => 'Update CAES Tools admin page for new sync targets',
					'step12' => 'Rewrite Symplectic import files to target CPT posts (verify year data)',
					'step13' => 'Retire import_news_experts() and import_news_writers()',
				);
				foreach ($phase3_steps as $key => $label): ?>
					<div style="padding:6px 0;border-bottom:1px solid #f0f0f0;color:#666">
						<span class="dashicons dashicons-yes" style="font-size:16px;color:#46b450;vertical-align:middle;margin-right:4px"></span>
						<?php echo esc_html($label); ?>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- ============ PHASE 4: UPDATE FRONT-END CODE ============ -->
			<div class="pmig-panel">
				<h2>Phase 4: Update Front-End Code</h2>
				<p class="description" style="margin:0 0 8px;color:#46b450;font-size:12px"><span class="dashicons dashicons-yes-alt" style="font-size:14px;vertical-align:middle"></span> Complete -- these are baked into the deployed theme code.</p>
				<?php
				$phase4_steps = array(
					'step14' => 'Create resolve_person_post_id() helper function',
					'step15' => 'Update 8 user blocks to read from CPT post meta',
					'step16' => 'Update pub-details-authors block for CPT posts',
					'step17' => 'Update update_flat_author_ids_meta() and update_flat_expert_ids_meta()',
					'step18' => 'Update block-variations/index.php for is_singular(caes_hub_person)',
				);
				foreach ($phase4_steps as $key => $label): ?>
					<div style="padding:6px 0;border-bottom:1px solid #f0f0f0;color:#666">
						<span class="dashicons dashicons-yes" style="font-size:16px;color:#46b450;vertical-align:middle;margin-right:4px"></span>
						<?php echo esc_html($label); ?>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- ============ PHASE 5: URL STRUCTURE AND TEMPLATES ============ -->
			<div class="pmig-panel">
				<h2>Phase 5: URL Structure and Templates</h2>
				<p class="description" style="margin:0 0 8px;color:#46b450;font-size:12px"><span class="dashicons dashicons-yes-alt" style="font-size:14px;vertical-align:middle"></span> Complete -- baked into the deployed theme code. <strong>Reminder:</strong> redirects only kick in after the &quot;Flush Permalinks&quot; step in Phase 2.</p>
				<?php
				$phase5_steps = array(
					'step19' => 'Set up /person/{post_id}/{slug}/ rewrite rules and permalink filter',
					'step20' => 'Create single-caes_hub_person.html template (author.html / author-2.html removal handled in Phase 6)',
					'step21' => 'Add redirect for old /person/{user_id}/{slug}/ URLs via the migration map',
					'step22' => 'Add 301 redirect from old /author/username/ URLs to new CPT URLs',
				);
				foreach ($phase5_steps as $key => $label): ?>
					<div style="padding:6px 0;border-bottom:1px solid #f0f0f0;color:#666">
						<span class="dashicons dashicons-yes" style="font-size:16px;color:#46b450;vertical-align:middle;margin-right:4px"></span>
						<?php echo esc_html($label); ?>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- ============ PHASE 6: CLEANUP ============ -->
			<div class="pmig-panel">
				<h2>Phase 6: Cleanup</h2>
				<?php
				$phase6_steps = array(
					'step27' => 'Bulk-delete old personnel/expert user accounts (optional)',
					'step24' => 'Remove personnel_user and expert_user role definitions',
					'step23' => 'Delete old user-targeted ACF field groups',
					'step25' => 'Update content_manager_map_meta_cap filter (remove edit_user case, keep unfiltered_html)',
					'step26' => 'Remove user profile accordion JS',
				);
				foreach ($phase6_steps as $key => $label):
					$is_step27 = ($key === 'step27');
				?>
					<div style="padding:6px 0;border-bottom:1px solid #f0f0f0">
						<label>
							<input type="checkbox" class="pmig-checklist-toggle" data-step="<?php echo esc_attr($key); ?>" <?php checked(!empty($checklist[$key])); ?>>
							<?php echo esc_html($label); ?>
						</label>
						<?php if ($is_step27): ?>
							<div style="margin:8px 0 4px 24px;padding:10px 12px;border-left:3px solid #f0b849;background:#fffdf5;font-size:12px;line-height:1.5">
								<strong>Recipe (run via SSH on the live container):</strong>
								<ol style="margin:6px 0 0 16px;padding:0">
									<li><strong>Snapshot users + their CAES IDs to TSV</strong> (so you can cross-reference back to the personnel/expert databases later if needed). Use your saved snapshot commands file -- run the two <code>wp db query</code> blocks via SSH to produce <code>personnel_users_backup.tsv</code> and <code>expert_users_backup.tsv</code>.</li>
									<li><strong>Check counts</strong> and verify no overlap with content_manager / event roles:
										<pre style="background:#f6f7f7;border:1px solid #ddd;padding:6px;margin:4px 0;font-size:11px;white-space:pre-wrap">echo "personnel_user: $(wp user list --role=personnel_user --format=count)"
echo "expert_user: $(wp user list --role=expert_user --format=count)"

# Overlap check -- count users who hold BOTH a legacy role AND an active staff role.
# Should return 0. If &gt; 0, run the SELECT version below to see who they are and
# remove just the legacy role from those accounts before doing the bulk delete.
wp db query "
SELECT COUNT(*)
FROM wp_users u
JOIN wp_usermeta um ON um.user_id = u.ID AND um.meta_key='wp_capabilities'
WHERE (um.meta_value LIKE '%personnel_user%' OR um.meta_value LIKE '%expert_user%')
  AND (um.meta_value LIKE '%content_manager%' OR um.meta_value LIKE '%event_submitter%' OR um.meta_value LIKE '%event_approver%')
"

# If overlap is &gt; 0, list them and remove the legacy role from each:
wp db query "
SELECT u.ID, u.user_login, u.user_email, u.display_name, um.meta_value AS roles
FROM wp_users u
JOIN wp_usermeta um ON um.user_id = u.ID AND um.meta_key='wp_capabilities'
WHERE (um.meta_value LIKE '%personnel_user%' OR um.meta_value LIKE '%expert_user%')
  AND (um.meta_value LIKE '%content_manager%' OR um.meta_value LIKE '%event_submitter%' OR um.meta_value LIKE '%event_approver%')
"
# Then for each one:
# wp user remove-role &lt;USER_ID&gt; personnel_user
# wp user remove-role &lt;USER_ID&gt; expert_user</pre>
									</li>
									<li><strong>Reassign any authored content to admin user 1</strong> on delete (in case any of these accounts are listed as <code>post_author</code> on posts/pubs/stories):
										<pre style="background:#f6f7f7;border:1px solid #ddd;padding:6px;margin:4px 0;font-size:11px;white-space:pre-wrap">wp user list --role=personnel_user --field=ID | xargs -I {} wp user delete {} --reassign=1 --yes
wp user list --role=expert_user --field=ID | xargs -I {} wp user delete {} --reassign=1 --yes</pre>
									</li>
								</ol>
								<p style="margin:6px 0 0;color:#8a6d3b">
									<strong>Safety notes:</strong> only targets <code>personnel_user</code> and <code>expert_user</code> roles by name. Content managers, event submitters, event approvers, administrators, editors, and subscribers are untouched. Run in staging first; verify the People CPT, repeater authors, and the front end still look right before running on prod.
								</p>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- ============ UTILITIES: ROLELESS USERS ============ -->
			<div class="pmig-panel">
				<h2>Utilities: Roleless Users Audit</h2>
				<p class="description">Find WP users with no roles that are referenced in content repeater fields (authors, experts, translator, artists). These are ghost accounts that may need cleanup.</p>
				<div class="pmig-btn-group" style="margin-top:8px">
					<input type="button" id="pmig-roleless-audit-btn" class="button" value="Scan for Roleless Users">
				</div>
				<div id="pmig-roleless-results" style="margin-top:12px"></div>
			</div>

			<!-- ============ RESET ============ -->
			<div class="pmig-panel" style="border-color:#dc3232">
				<h2 style="color:#dc3232">Reset</h2>
				<p class="description">Clears all migration state, the lookup map, and checklist progress. Pair with a fresh prod-to-staging database copy for a clean restart.</p>
				<div class="pmig-btn-group" style="margin-top:8px">
					<input type="button" id="pmig-delete-all-btn" class="button" style="color:#a00" value="Delete All Person Posts">
					<input type="button" id="pmig-reset-all-btn" class="button" style="color:#a00" value="Reset Everything">
				</div>
			</div>

		</div>
	</div>
	<?php
}

// ============================================================
// AJAX handlers
// ============================================================

add_action('wp_ajax_person_migration_status',            'person_migration_ajax_status');
add_action('wp_ajax_person_migration_trigger',           'person_migration_ajax_trigger');
add_action('wp_ajax_person_migration_stop',              'person_migration_ajax_stop');
add_action('wp_ajax_person_migration_resume',            'person_migration_ajax_resume');
add_action('wp_ajax_person_migration_single',            'person_migration_ajax_single');
add_action('wp_ajax_person_migration_swap',              'person_migration_ajax_swap');
add_action('wp_ajax_person_migration_flat_meta',         'person_migration_ajax_flat_meta');
add_action('wp_ajax_person_migration_revert_flat_meta',  'person_migration_ajax_revert_flat_meta');
add_action('wp_ajax_person_migration_verify_swap',           'person_migration_ajax_verify_swap');
add_action('wp_ajax_person_migration_revert_swap',           'person_migration_ajax_revert_swap');
add_action('wp_ajax_person_migration_update_field_types',    'person_migration_ajax_update_field_types');
add_action('wp_ajax_person_migration_user_feed_swap',   'person_migration_ajax_user_feed_swap');
add_action('wp_ajax_person_migration_user_feed_revert', 'person_migration_ajax_user_feed_revert');
add_action('wp_ajax_person_migration_link_content_managers', 'person_migration_ajax_link_content_managers');
add_action('wp_ajax_person_migration_delete_all',            'person_migration_ajax_delete_all');
add_action('wp_ajax_person_migration_checklist_toggle',      'person_migration_ajax_checklist_toggle');
add_action('wp_ajax_person_migration_reset_all',             'person_migration_ajax_reset_all');
add_action('wp_ajax_person_migration_search_persons',        'person_migration_ajax_search_persons');
add_action('wp_ajax_person_migration_resolve_flagged',       'person_migration_ajax_resolve_flagged');
add_action('wp_ajax_person_migration_count_audit',           'person_migration_ajax_count_audit');
add_action('wp_ajax_person_migration_flat_meta_audit',       'person_migration_ajax_flat_meta_audit');
add_action('wp_ajax_person_migration_roleless_audit',        'person_migration_ajax_roleless_audit');

function person_migration_check_ajax() {
	if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'person_migration_nonce')) {
		wp_send_json_error(array('error_message' => 'Security check failed.'));
	}
	if (!current_user_can('manage_options')) {
		wp_send_json_error(array('error_message' => 'Insufficient permissions.'));
	}
}

function person_migration_ajax_status() {
	person_migration_check_ajax();
	wp_send_json_success(array('state' => person_migration_get_state()));
}

function person_migration_ajax_trigger() {
	person_migration_check_ajax();
	$dry_run = !empty($_POST['dry_run']);
	$started = person_migration_start_job($dry_run);
	if (!$started) {
		$state = person_migration_get_state();
		if ($state['status'] === 'running') {
			wp_send_json_error(array('error_message' => 'A job is already running.'));
		} else {
			wp_send_json_error(array('error_message' => 'No eligible users found.'));
		}
	}
	wp_send_json_success(array('message' => 'Migration queued.'));
}

function person_migration_ajax_stop() {
	person_migration_check_ajax();
	$state = person_migration_get_state();
	if ($state['status'] !== 'running') {
		wp_send_json_error(array('error_message' => 'No job is currently running.'));
	}
	$state['status']         = 'stopped';
	$state['stop_requested'] = false;
	update_option(PERSON_MIGRATION_STATE_KEY, $state, false);
	wp_clear_scheduled_hook(PERSON_MIGRATION_BATCH_HOOK);
	wp_send_json_success(array('message' => 'Job stopped.'));
}

function person_migration_ajax_resume() {
	person_migration_check_ajax();
	$resumed = person_migration_resume_job();
	if (!$resumed) {
		$state = person_migration_get_state();
		if ($state['status'] !== 'stopped') {
			wp_send_json_error(array('error_message' => 'Job is not in a stopped state (status: ' . $state['status'] . ').'));
		} else {
			wp_send_json_error(array('error_message' => 'No items remaining to process.'));
		}
	}
	wp_send_json_success(array('message' => 'Job resumed.'));
}

function person_migration_ajax_single() {
	person_migration_check_ajax();

	$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
	if ($user_id <= 0) {
		wp_send_json_error(array('error_message' => 'Valid User ID is required.'));
	}

	$dry_run = !empty($_POST['dry_run']);
	$user = get_userdata($user_id);
	if (!$user) {
		wp_send_json_error(array('error_message' => 'User ID ' . $user_id . ' not found.'));
	}

	@set_time_limit(60);
	$result = person_migration_migrate_single_user($user_id, $dry_run);

	wp_send_json_success(array(
		'status'         => $result['status'],
		'user_id'        => $user_id,
		'display_name'   => $user->display_name,
		'post_id'        => $result['post_id'],
		'fields_written' => $result['fields_written'],
		'error_message'  => $result['error_message'],
		'field_log'      => $result['field_log'],
	));
}

function person_migration_ajax_swap() {
	person_migration_check_ajax();
	$dry_run = !empty($_POST['dry_run']);
	$started = person_migration_start_swap_job($dry_run);
	if (!$started) {
		$state = person_migration_get_state();
		if ($state['status'] === 'running') {
			wp_send_json_error(array('error_message' => 'A job is already running.'));
		} else {
			wp_send_json_error(array('error_message' => 'No lookup map found or no posts to process. Run the migration first.'));
		}
	}
	wp_send_json_success(array('message' => 'Repeater swap queued.'));
}

function person_migration_ajax_flat_meta() {
	person_migration_check_ajax();
	$dry_run = !empty($_POST['dry_run']);
	$started = person_migration_start_flat_meta_job($dry_run, false);
	if (!$started) {
		$state = person_migration_get_state();
		if ($state['status'] === 'running') {
			wp_send_json_error(array('error_message' => 'A job is already running.'));
		} else {
			wp_send_json_error(array('error_message' => 'No posts to process.'));
		}
	}
	wp_send_json_success(array('message' => 'Flat meta repopulation queued.'));
}

function person_migration_ajax_revert_flat_meta() {
	person_migration_check_ajax();
	$started = person_migration_start_flat_meta_job(false, true);
	if (!$started) {
		$state = person_migration_get_state();
		if ($state['status'] === 'running') {
			wp_send_json_error(array('error_message' => 'A job is already running.'));
		} else {
			wp_send_json_error(array('error_message' => 'No posts to process.'));
		}
	}
	wp_send_json_success(array('message' => 'Flat meta revert queued.'));
}

/**
 * Scan all post_content for caes-hub/user-feed blocks and swap userIds
 * from WP user IDs to CPT post IDs using the migration lookup map.
 * Backs up original post_content to _user_feed_block_backup meta before writing.
 */
function person_migration_ajax_user_feed_swap() {
	person_migration_check_ajax();
	@set_time_limit(120);

	$dry_run = !empty($_POST['dry_run']);
	$map     = person_migration_get_map();

	if (empty($map)) {
		wp_send_json_error(array('error_message' => 'Lookup map is empty. Run the migration first.'));
	}

	global $wpdb;
	$posts = $wpdb->get_results(
		"SELECT ID, post_title, post_content FROM {$wpdb->posts}
		 WHERE post_status NOT IN ('trash','auto-draft')
		 AND post_content LIKE '%caes-hub/user-feed%'"
	);

	$affected   = 0;
	$swapped    = 0;
	$not_in_map = 0;
	$details    = array();
	$warnings   = array();

	foreach ($posts as $post) {
		// Parse all user-feed block attribute JSON from post_content
		preg_match_all('/<!-- wp:caes-hub\/user-feed ({.*?}) -->/', $post->post_content, $matches);
		if (empty($matches[1])) {
			continue;
		}

		$new_content  = $post->post_content;
		$post_swapped = 0;
		$post_skipped = 0;
		$changes      = array();

		foreach ($matches[0] as $i => $original_comment) {
			$attrs = json_decode($matches[1][$i], true);
			if (!isset($attrs['userIds']) || !is_array($attrs['userIds'])) {
				continue;
			}

			$new_ids     = array();
			$changed     = false;
			foreach ($attrs['userIds'] as $uid) {
				$uid = (int) $uid;
				if (isset($map[$uid])) {
					$new_ids[] = (int) $map[$uid];
					$changes[] = 'user ' . $uid . ' -> post ' . $map[$uid];
					$post_swapped++;
					$changed = true;
				} else {
					// Already a CPT post ID or genuinely missing -- keep as-is
					$new_ids[] = $uid;
					if (get_post_type($uid) !== 'caes_hub_person') {
						$warnings[] = 'Post #' . $post->ID . ' (' . $post->post_title . '): user ID ' . $uid . ' not in map';
						$post_skipped++;
					}
				}
			}

			if ($changed) {
				$attrs['userIds'] = $new_ids;
				$new_comment      = '<!-- wp:caes-hub/user-feed ' . wp_json_encode($attrs) . ' -->';
				$new_content      = str_replace($original_comment, $new_comment, $new_content);
			}
		}

		if ($post_swapped === 0) {
			continue;
		}

		$affected++;
		$swapped    += $post_swapped;
		$not_in_map += $post_skipped;

		$details[] = array(
			'post_id'  => $post->ID,
			'title'    => $post->post_title,
			'edit_url' => get_edit_post_link($post->ID, 'raw'),
			'summary'  => implode(', ', $changes),
		);

		if (!$dry_run) {
			// Back up original post_content before overwriting
			if (!get_post_meta($post->ID, '_user_feed_block_backup', true)) {
				update_post_meta($post->ID, '_user_feed_block_backup', $post->post_content);
			}
			$wpdb->update($wpdb->posts, array('post_content' => $new_content), array('ID' => $post->ID));
			clean_post_cache($post->ID);
		}
	}

	wp_send_json_success(array(
		'affected'   => $affected,
		'swapped'    => $swapped,
		'not_in_map' => $not_in_map,
		'details'    => $details,
		'warnings'   => $warnings,
		'dry_run'    => $dry_run,
	));
}

/**
 * Revert user-feed block post_content from the _user_feed_block_backup meta key.
 */
function person_migration_ajax_user_feed_revert() {
	person_migration_check_ajax();
	@set_time_limit(120);

	global $wpdb;
	$post_ids = $wpdb->get_col(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_user_feed_block_backup'"
	);

	$reverted = 0;
	foreach ($post_ids as $post_id) {
		$backup = get_post_meta($post_id, '_user_feed_block_backup', true);
		if (!empty($backup)) {
			$wpdb->update($wpdb->posts, array('post_content' => $backup), array('ID' => $post_id));
			delete_post_meta($post_id, '_user_feed_block_backup');
			clean_post_cache($post_id);
			$reverted++;
		}
	}

	wp_send_json_success(array('reverted' => $reverted));
}

function person_migration_ajax_link_content_managers() {
	person_migration_check_ajax();
	@set_time_limit(120);

	$content_managers = get_users(array(
		'role__in' => array('content_manager', 'event_submitter', 'event_approver'),
		'fields'   => 'all',
		'number'   => -1,
	));

	$linked  = 0;
	$skipped = 0;
	$failed  = 0;
	$errors  = array();

	$needs_manual = array(); // CMs that couldn't be auto-linked

	// Users to skip entirely (by login)
	$skip_logins = array('ashwhee');

	foreach ($content_managers as $cm) {
		if (in_array($cm->user_login, $skip_logins, true)) {
			$skipped++;
			continue;
		}

		// Check if this CM already has a linked person post
		$already_linked = false;
		$existing_posts = get_posts(array(
			'post_type'      => 'caes_hub_person',
			'post_status'    => 'any',
			'meta_key'       => 'linked_wp_user',
			'meta_value'     => (string) $cm->ID,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		));
		if (!empty($existing_posts)) {
			$skipped++;
			continue;
		}

		// Debug: check all person posts that have linked_wp_user set and look for this user
		$debug_posts = get_posts(array(
			'post_type'      => 'caes_hub_person',
			'post_status'    => 'any',
			'meta_key'       => 'linked_wp_user',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		));
		$debug_matches = array();
		foreach ($debug_posts as $dp) {
			$raw = get_post_meta($dp, 'linked_wp_user', true);
			if ($raw == $cm->ID || (is_array($raw) && in_array($cm->ID, $raw))) {
				$debug_matches[] = array('post_id' => $dp, 'raw_value' => $raw, 'type' => gettype($raw));
			}
		}
		// Also search by display_name to find her person post
		$debug_name_posts = get_posts(array(
			'post_type' => 'caes_hub_person', 'post_status' => 'any',
			's' => $cm->display_name, 'posts_per_page' => 5, 'fields' => 'ids',
		));
		$debug_name_meta = array();
		foreach ($debug_name_posts as $dnp) {
			$debug_name_meta[] = array(
				'post_id' => $dnp,
				'title' => get_the_title($dnp),
				'linked_wp_user_raw' => get_post_meta($dnp, 'linked_wp_user', true),
				'linked_wp_user_type' => gettype(get_post_meta($dnp, 'linked_wp_user', true)),
			);
		}
		if (!empty($debug_matches) || $cm->ID == 4782) {
			$errors[] = 'DEBUG user ' . $cm->ID . ' (' . $cm->display_name . '): meta_query found ' . count($existing_posts) . ' posts. Brute-force found ' . count($debug_matches) . ' matches: ' . json_encode($debug_matches) . '. Name search: ' . json_encode($debug_name_meta);
		}

		$post_id = null;

		// Try matching by personnel_id first
		$personnel_id = get_user_meta($cm->ID, 'personnel_id', true);
		if (!empty($personnel_id)) {
			$posts = get_posts(array(
				'post_type'      => 'caes_hub_person',
				'post_status'    => 'publish',
				'meta_key'       => 'personnel_id',
				'meta_value'     => $personnel_id,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			));
			if (!empty($posts)) {
				$post_id = $posts[0];
			}
		}

		// Fall back to matching by first_name + last_name
		if (!$post_id) {
			$first = $cm->first_name;
			$last  = $cm->last_name;
			if (!empty($first) && !empty($last)) {
				$posts = get_posts(array(
					'post_type'      => 'caes_hub_person',
					'post_status'    => 'publish',
					'posts_per_page' => 5,
					'fields'         => 'ids',
					'meta_query'     => array(
						'relation' => 'AND',
						array('key' => 'first_name', 'value' => $first, 'compare' => '='),
						array('key' => 'last_name',  'value' => $last,  'compare' => '='),
					),
				));
				if (count($posts) === 1) {
					$post_id = $posts[0]; // Exact single match
				} elseif (count($posts) > 1) {
					// Multiple matches -- needs manual review
					$needs_manual[] = array(
						'user_id'   => $cm->ID,
						'name'      => $cm->display_name,
						'reason'    => 'Multiple person posts match this name',
						'candidates' => array_map(function($pid) {
							return array('post_id' => $pid, 'title' => get_the_title($pid), 'edit_url' => get_edit_post_link($pid, 'raw'));
						}, $posts),
					);
					$failed++;
					continue;
				}
			}
		}

		if (!$post_id) {
			$needs_manual[] = array(
				'user_id'    => $cm->ID,
				'name'       => $cm->display_name,
				'reason'     => 'No person post found (no personnel_id, no name match)',
				'candidates' => array(),
			);
			$failed++;
			continue;
		}

		// Check if this person post is already linked to a different user
		$existing = get_post_meta($post_id, 'linked_wp_user', true);
		if (!empty($existing) && (int)$existing !== $cm->ID) {
			$needs_manual[] = array(
				'user_id'    => $cm->ID,
				'name'       => $cm->display_name,
				'reason'     => 'Person post #' . $post_id . ' already linked to user ' . $existing,
				'candidates' => array(array('post_id' => $post_id, 'title' => get_the_title($post_id), 'edit_url' => get_edit_post_link($post_id, 'raw'))),
			);
			$failed++;
			continue;
		}

		update_post_meta($post_id, 'linked_wp_user', $cm->ID);

		$map = person_migration_get_map();
		$map[$cm->ID] = $post_id;
		update_option(PERSON_MIGRATION_MAP_KEY, $map, false);

		$linked++;
		$errors[] = 'Linked user ' . $cm->ID . ' (' . $cm->display_name . ') to person post #' . $post_id;
	}

	wp_send_json_success(array(
		'linked'       => $linked,
		'skipped'      => $skipped,
		'failed'       => $failed,
		'errors'       => $errors,
		'needs_manual' => $needs_manual,
	));
}

function person_migration_ajax_delete_all() {
	person_migration_check_ajax();

	$state = person_migration_get_state();
	if ($state['status'] === 'running') {
		wp_send_json_error(array('error_message' => 'Cannot delete while a job is running. Stop the job first.'));
	}

	@set_time_limit(60);

	$batch_size = 100;

	// Get next batch of posts to delete
	$posts = get_posts(array(
		'post_type'      => 'caes_hub_person',
		'post_status'    => 'any',
		'posts_per_page' => $batch_size,
		'fields'         => 'ids',
	));

	$deleted_this_batch = 0;
	foreach ($posts as $post_id) {
		$result = wp_delete_post($post_id, true);
		if ($result) {
			$deleted_this_batch++;
		}
	}

	// Count how many remain
	$remaining = 0;
	$counts = wp_count_posts('caes_hub_person');
	if ($counts) {
		foreach ($counts as $status_count) {
			$remaining += (int) $status_count;
		}
	}

	$done = ($remaining === 0);

	// Only clear state/map when fully done
	if ($done) {
		delete_option(PERSON_MIGRATION_MAP_KEY);
		delete_option(PERSON_MIGRATION_STATE_KEY);
	}

	wp_send_json_success(array(
		'deleted_batch' => $deleted_this_batch,
		'remaining'     => $remaining,
		'done'          => $done,
	));
}



function person_migration_ajax_checklist_toggle() {
	person_migration_check_ajax();

	$step    = sanitize_text_field($_POST['step']);
	$checked = !empty($_POST['checked']);

	$checklist = person_migration_get_checklist();
	if ($checked) {
		$checklist[$step] = time();
	} else {
		unset($checklist[$step]);
	}
	update_option(PERSON_MIGRATION_CHECKLIST_KEY, $checklist, false);

	wp_send_json_success();
}

function person_migration_ajax_reset_all() {
	person_migration_check_ajax();

	delete_option(PERSON_MIGRATION_STATE_KEY);
	delete_option(PERSON_MIGRATION_MAP_KEY);
	delete_option(PERSON_MIGRATION_CHECKLIST_KEY);

	wp_send_json_success();
}

