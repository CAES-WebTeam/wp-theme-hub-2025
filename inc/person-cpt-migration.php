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
		'department',
		'program_area',
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
		'is_active',
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
	$post_types = array('post', 'caes_publication', 'shorthand_story');
	$repeater_meta_keys = array('authors', 'experts', 'translator', 'artists');

	$total = 0;
	foreach ($post_types as $pt) {
		$count = wp_count_posts($pt);
		$total += (isset($count->publish) ? $count->publish : 0)
				+ (isset($count->draft) ? $count->draft : 0)
				+ (isset($count->private) ? $count->private : 0);
	}

	if ($total === 0) return false;

	$last_completed      = $state['last_completed'];
	$new_state           = person_migration_default_state();
	$new_state['status']         = 'running';
	$new_state['mode']           = 'swap_repeaters';
	$new_state['dry_run']        = $dry_run;
	$new_state['started_at']     = time();
	$new_state['total_users']    = $total; // reusing field name for total posts
	$new_state['last_completed'] = $last_completed;
	update_option(PERSON_MIGRATION_STATE_KEY, $new_state, false);

	wp_schedule_single_event(time(), PERSON_MIGRATION_BATCH_HOOK);
	return true;
}

function person_migration_run_swap_batch(&$state) {
	$map = person_migration_get_map();
	$post_types = array('post', 'caes_publication', 'shorthand_story');
	$repeater_names = array('authors', 'experts', 'translator', 'artists');

	$posts = get_posts(array(
		'post_type'      => $post_types,
		'post_status'    => array('publish', 'draft', 'private'),
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
							update_post_meta($post->ID, $meta_key, $map[$old_val]);
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

// ============================================================
// Step 8: Repopulate / revert flat meta fields
// ============================================================

function person_migration_start_flat_meta_job($dry_run = false, $revert = false) {
	$state = person_migration_get_state();
	if ($state['status'] === 'running') {
		return false;
	}

	$post_types = array('post', 'caes_publication', 'shorthand_story');
	$total = 0;
	foreach ($post_types as $pt) {
		$count = wp_count_posts($pt);
		$total += (isset($count->publish) ? $count->publish : 0)
				+ (isset($count->draft) ? $count->draft : 0)
				+ (isset($count->private) ? $count->private : 0);
	}

	if ($total === 0) return false;

	$last_completed      = $state['last_completed'];
	$new_state           = person_migration_default_state();
	$new_state['status']         = 'running';
	$new_state['mode']           = $revert ? 'revert_flat_meta' : 'repopulate_flat_meta';
	$new_state['dry_run']        = $dry_run;
	$new_state['started_at']     = time();
	$new_state['total_users']    = $total;
	$new_state['last_completed'] = $last_completed;
	update_option(PERSON_MIGRATION_STATE_KEY, $new_state, false);

	wp_schedule_single_event(time(), PERSON_MIGRATION_BATCH_HOOK);
	return true;
}

function person_migration_run_flat_meta_batch(&$state, $revert = false) {
	$map = person_migration_get_map();
	$post_types = array('post', 'caes_publication', 'shorthand_story');
	$flat_fields = array('all_author_ids', 'all_expert_ids');

	$posts = get_posts(array(
		'post_type'      => $post_types,
		'post_status'    => array('publish', 'draft', 'private'),
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
		'Person CPT Migration',
		'Person CPT Migration',
		'manage_options',
		'person-cpt-migration',
		'person_migration_render_page'
	);
	add_submenu_page(
		'caes-tools',
		'Merge Duplicate People',
		'Merge Duplicate People',
		'manage_options',
		'person-merge-duplicates',
		'person_migration_render_merge_page'
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
					html += "<div class=\"pmig-progress-label\">" + esc(state.processed_users) + " / " + esc(state.total_users) + " " + itemLabel + " (" + pct + "%)</div>";
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
						$("#pmig-link-cm-btn").prop("disabled", false).val("Link Content Managers");
						if (response.success) {
							var d = response.data;
							var html = "<div class=\"notice notice-success\" style=\"margin:12px 0\"><p>" + esc(d.linked) + " content managers linked, " + esc(d.skipped) + " skipped, " + esc(d.failed) + " failed.</p>";
							if (d.errors && d.errors.length) {
								html += "<ul style=\"font-size:12px;margin-top:4px\">";
								d.errors.forEach(function(e) { html += "<li>" + esc(e) + "</li>"; });
								html += "</ul>";
							}
							html += "</div>";
							$("#pmig-current-panel").prepend(html);
						} else {
							var msg = (response.data && response.data.error_message) ? response.data.error_message : "Unknown error";
							alert("Error: " + msg);
						}
					},
					error: function() {
						$("#pmig-link-cm-btn").prop("disabled", false).val("Link Content Managers");
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

			// Scan for duplicates
			$("#pmig-scan-dupes-btn").on("click", function() {
				var $btn = $(this);
				$btn.prop("disabled", true).val("Scanning...");
				$.ajax({
					url: ajaxurl,
					method: "POST",
					timeout: 120000,
					data: { action: "person_migration_scan_duplicates", nonce: nonce },
					success: function(response) {
						$btn.prop("disabled", false).val("Scan for Duplicates");
						if (response.success) {
							var d = response.data;
							var html = "<div class=\"notice notice-info\" style=\"margin:12px 0\"><p>Scan complete: <strong>" + esc(String(d.duplicate_groups)) + "</strong> duplicate groups found (" + esc(String(d.posts_flagged)) + " posts flagged).</p>";
							if (d.duplicate_groups > 0) {
								html += "<p><a href=\"" + esc(d.merge_url) + "\" class=\"button button-secondary\">Review &amp; Merge Duplicates</a></p>";
							}
							html += "</div>";
							$("#pmig-current-panel").prepend(html);
						} else {
							var msg = (response.data && response.data.error_message) ? response.data.error_message : "Unknown error";
							alert("Error: " + msg);
						}
					},
					error: function() {
						$btn.prop("disabled", false).val("Scan for Duplicates");
						alert("AJAX error.");
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

			pollStatus();
		});
	');
}

// ============================================================
// Admin page render
// ============================================================

function person_migration_render_page() {
	$map          = person_migration_get_map();
	$map_count    = count($map);
	$eligible     = person_migration_count_eligible_users();
	?>
	<div class="wrap">
		<h1>Person CPT Migration</h1>
		<p>Migrates <code>personnel_user</code> and <code>expert_user</code> WordPress users to <code>caes_hub_person</code> CPT posts. Content managers are linked to their existing person posts after migration.</p>
		<p class="description">
			Eligible users: <strong><?php echo esc_html($eligible); ?></strong>
			&ensp;|&ensp;
			Users already migrated (in lookup map): <strong><?php echo esc_html($map_count); ?></strong>
		</p>

		<div class="pmig-wrapper">

			<div class="pmig-panel">
				<h2>Current Job Status</h2>
				<div id="pmig-current-panel"><p style="color:#999">Loading...</p></div>
			</div>

			<div class="pmig-panel" id="pmig-last-completed-section" style="display:none">
				<h2>Last Completed Run</h2>
				<div id="pmig-last-completed-panel"></div>
			</div>

			<div class="pmig-panel">
				<h2>Bulk Operations</h2>
				<div class="pmig-btn-group" style="margin-bottom: 12px;">
					<label><input type="checkbox" id="pmig-dry-run" checked> Dry Run</label>
				</div>
				<div class="pmig-btn-group">
					<input type="button" id="pmig-migrate-btn" class="button button-primary pmig-action-btn" value="Migrate Users to CPT">
					<input type="button" id="pmig-swap-btn" class="button pmig-action-btn" value="Swap Repeater IDs">
					<input type="button" id="pmig-flat-meta-btn" class="button pmig-action-btn" value="Repopulate Flat Meta">
					<input type="button" id="pmig-revert-btn" class="button pmig-action-btn" value="Revert Flat Meta">
					<input type="button" id="pmig-link-cm-btn" class="button pmig-action-btn" value="Link Content Managers">
					<input type="button" id="pmig-scan-dupes-btn" class="button" value="Scan for Duplicates">
					<input type="button" id="pmig-delete-all-btn" class="button" style="color:#a00" value="Delete All Person Posts">
					<input type="button" id="pmig-stop-btn" class="button" value="Stop" style="display:none">
					<input type="button" id="pmig-resume-btn" class="button button-secondary" value="Resume" style="display:none">
				</div>
				<p class="description" style="margin-top:8px">
					Runs in the background via WP-Cron batching (<?php echo PERSON_MIGRATION_BATCH_SIZE; ?> per batch).
					Dry run is enabled by default -- uncheck to write data.
				</p>
			</div>

			<div class="pmig-panel">
				<h2>Single-User Migration</h2>
				<form id="pmig-single-form">
					<div style="margin-bottom:12px">
						<label for="pmig-single-user-id" style="font-weight:600;display:block;margin-bottom:4px">WordPress User ID</label>
						<input type="number" id="pmig-single-user-id" placeholder="e.g. 42" style="width:200px" min="1">
						<label style="margin-left:12px"><input type="checkbox" id="pmig-single-dry-run" checked> Dry Run</label>
					</div>
					<input type="submit" class="button button-secondary" value="Migrate Single User">
					<p class="description">Runs immediately and reports results inline. Does not affect a running bulk job.</p>
				</form>
				<div id="pmig-single-result"></div>
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
add_action('wp_ajax_person_migration_link_content_managers', 'person_migration_ajax_link_content_managers');
add_action('wp_ajax_person_migration_delete_all',            'person_migration_ajax_delete_all');
add_action('wp_ajax_person_migration_scan_duplicates',       'person_migration_ajax_scan_duplicates');
add_action('wp_ajax_person_migration_merge',                 'person_migration_ajax_merge');

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

function person_migration_ajax_link_content_managers() {
	person_migration_check_ajax();
	@set_time_limit(120);

	$content_managers = get_users(array(
		'role'    => 'content_manager',
		'fields'  => 'all',
		'number'  => -1,
	));

	$linked  = 0;
	$skipped = 0;
	$failed  = 0;
	$errors  = array();

	foreach ($content_managers as $cm) {
		$personnel_id = get_user_meta($cm->ID, 'personnel_id', true);
		if (empty($personnel_id)) {
			$errors[] = 'User ' . $cm->ID . ' (' . $cm->display_name . '): no personnel_id, skipped.';
			$skipped++;
			continue;
		}

		// Find the person post by personnel_id
		$posts = get_posts(array(
			'post_type'      => 'caes_hub_person',
			'post_status'    => 'publish',
			'meta_key'       => 'personnel_id',
			'meta_value'     => $personnel_id,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		));

		if (empty($posts)) {
			$errors[] = 'User ' . $cm->ID . ' (' . $cm->display_name . '): no person post found for personnel_id ' . $personnel_id . '.';
			$failed++;
			continue;
		}

		$post_id = $posts[0];

		// Check if already linked
		$existing = get_post_meta($post_id, 'linked_wp_user', true);
		if (!empty($existing) && (int)$existing === $cm->ID) {
			$skipped++;
			continue;
		}

		update_post_meta($post_id, 'linked_wp_user', $cm->ID);

		// Also add to the lookup map
		$map = person_migration_get_map();
		$map[$cm->ID] = $post_id;
		update_option(PERSON_MIGRATION_MAP_KEY, $map, false);

		$linked++;
	}

	wp_send_json_success(array(
		'linked'  => $linked,
		'skipped' => $skipped,
		'failed'  => $failed,
		'errors'  => $errors,
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

// ============================================================
// Duplicate detection
// ============================================================

define('PERSON_MIGRATION_DUPES_KEY', 'person_cpt_duplicate_groups');

function person_migration_ajax_scan_duplicates() {
	person_migration_check_ajax();
	@set_time_limit(120);

	$posts = get_posts(array(
		'post_type'      => 'caes_hub_person',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	));

	// Build indexes by email and by name
	$by_email = array();
	$by_name  = array();

	foreach ($posts as $post_id) {
		$email = strtolower(trim(get_post_meta($post_id, 'uga_email', true)));
		$first = strtolower(trim(get_post_meta($post_id, 'first_name', true)));
		$last  = strtolower(trim(get_post_meta($post_id, 'last_name', true)));

		if (!empty($email)) {
			$by_email[$email][] = $post_id;
		}
		if (!empty($first) && !empty($last)) {
			$name_key = $first . '|' . $last;
			$by_name[$name_key][] = $post_id;
		}
	}

	// Merge into duplicate groups (sets of post IDs)
	// Use a union-find approach: group any posts that share email OR name
	$post_to_group = array();
	$groups        = array();
	$group_counter = 0;

	$indexes = array($by_email, $by_name);
	foreach ($indexes as $index) {
		foreach ($index as $key => $post_ids) {
			if (count($post_ids) < 2) continue;

			// Find if any of these posts already belong to a group
			$existing_group = null;
			foreach ($post_ids as $pid) {
				if (isset($post_to_group[$pid])) {
					$existing_group = $post_to_group[$pid];
					break;
				}
			}

			if ($existing_group === null) {
				$existing_group = $group_counter++;
				$groups[$existing_group] = array();
			}

			foreach ($post_ids as $pid) {
				if (!in_array($pid, $groups[$existing_group])) {
					$groups[$existing_group][] = $pid;
				}
				$post_to_group[$pid] = $existing_group;
			}
		}
	}

	// Filter to only groups with 2+ posts
	$duplicate_groups = array();
	foreach ($groups as $group) {
		if (count($group) >= 2) {
			sort($group);
			$duplicate_groups[] = $group;
		}
	}

	// Store the duplicate groups and set meta flags on posts
	// First clear old flags
	$old_flagged = get_posts(array(
		'post_type'      => 'caes_hub_person',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_key'       => '_duplicate_group',
	));
	foreach ($old_flagged as $pid) {
		delete_post_meta($pid, '_duplicate_group');
	}

	// Set new flags
	$posts_flagged = 0;
	foreach ($duplicate_groups as $gi => $group) {
		foreach ($group as $pid) {
			update_post_meta($pid, '_duplicate_group', $gi);
			$posts_flagged++;
		}
	}

	update_option(PERSON_MIGRATION_DUPES_KEY, $duplicate_groups, false);

	wp_send_json_success(array(
		'duplicate_groups' => count($duplicate_groups),
		'posts_flagged'    => $posts_flagged,
		'merge_url'        => admin_url('admin.php?page=person-merge-duplicates'),
	));
}

// ============================================================
// Admin list column: duplicate flag
// ============================================================

add_filter('manage_caes_hub_person_posts_columns', 'person_migration_add_dupe_column');
function person_migration_add_dupe_column($columns) {
	$columns['duplicate'] = 'Duplicate';
	return $columns;
}

add_action('manage_caes_hub_person_posts_custom_column', 'person_migration_dupe_column_content', 10, 2);
function person_migration_dupe_column_content($column, $post_id) {
	if ($column !== 'duplicate') return;
	$group = get_post_meta($post_id, '_duplicate_group', true);
	if ($group !== '' && $group !== false) {
		$merge_url = admin_url('admin.php?page=person-merge-duplicates&group=' . intval($group));
		echo '<a href="' . esc_url($merge_url) . '" style="color:#d63638;font-weight:600">Duplicate</a>';
	}
}

// ============================================================
// Merge duplicates review page
// ============================================================

function person_migration_render_merge_page() {
	$duplicate_groups = get_option(PERSON_MIGRATION_DUPES_KEY, array());
	$viewing_group    = isset($_GET['group']) ? intval($_GET['group']) : null;
	$nonce            = wp_create_nonce('person_migration_nonce');

	?>
	<div class="wrap">
		<h1>Merge Duplicate People</h1>

		<?php if ($viewing_group !== null && isset($duplicate_groups[$viewing_group])): ?>
			<?php
			$group_post_ids = $duplicate_groups[$viewing_group];
			$all_fields     = array_merge(
				array('first_name', 'last_name', 'display_name'),
				person_migration_get_simple_fields()
			);
			$repeater_fields = person_migration_get_repeater_fields();
			$taxonomy_fields = person_migration_get_taxonomy_fields();
			?>
			<p><a href="<?php echo esc_url(admin_url('admin.php?page=person-merge-duplicates')); ?>">&larr; Back to all groups</a></p>
			<h2>Duplicate Group #<?php echo esc_html($viewing_group + 1); ?></h2>
			<p class="description">Review the records below. Choose which post to keep, then click Merge. Data from the other post(s) will fill in any empty fields on the keeper, then the duplicates will be trashed.</p>

			<form id="pmig-merge-form">
				<table class="widefat striped" style="margin-top:12px">
					<thead>
						<tr>
							<th style="width:200px">Field</th>
							<?php foreach ($group_post_ids as $pid): ?>
								<th>
									Post #<?php echo esc_html($pid); ?>
									<br><small><?php echo esc_html(get_the_title($pid)); ?></small>
									<br><label><input type="radio" name="keep_post" value="<?php echo esc_attr($pid); ?>" <?php checked($pid, $group_post_ids[0]); ?>> Keep this one</label>
									<br><a href="<?php echo esc_url(get_edit_post_link($pid)); ?>" target="_blank">Edit</a>
								</th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><strong>Post Status</strong></td>
							<?php foreach ($group_post_ids as $pid): ?>
								<td><?php echo esc_html(get_post_status($pid)); ?></td>
							<?php endforeach; ?>
						</tr>
						<tr>
							<td><strong>Source User Role</strong></td>
							<?php foreach ($group_post_ids as $pid):
								// Try to find the original user from the map
								$map = person_migration_get_map();
								$source_user_id = array_search($pid, $map);
								$role = '';
								if ($source_user_id) {
									$u = get_userdata($source_user_id);
									if ($u) $role = implode(', ', $u->roles);
								}
							?>
								<td><?php echo esc_html($role ?: 'Unknown'); ?> <?php echo $source_user_id ? '(User ' . esc_html($source_user_id) . ')' : ''; ?></td>
							<?php endforeach; ?>
						</tr>
						<?php foreach ($all_fields as $field_name): ?>
							<?php
							$values = array();
							$has_diff = false;
							foreach ($group_post_ids as $pid) {
								$val = get_post_meta($pid, $field_name, true);
								$values[$pid] = $val;
							}
							$non_empty = array_filter($values, function($v) { return $v !== '' && $v !== false && $v !== null; });
							if (empty($non_empty)) continue; // skip entirely empty fields
							$unique_vals = array_unique($non_empty);
							$has_diff = count($unique_vals) > 1;
							?>
							<tr<?php echo $has_diff ? ' style="background:#fff8e5"' : ''; ?>>
								<td><strong><?php echo esc_html($field_name); ?></strong><?php echo $has_diff ? ' <span style="color:#dba617">&#9679;</span>' : ''; ?></td>
								<?php foreach ($group_post_ids as $pid): ?>
									<td><?php
										$v = $values[$pid];
										if (is_array($v)) {
											echo esc_html(json_encode($v));
										} else {
											echo esc_html(mb_substr((string)$v, 0, 120));
											if (mb_strlen((string)$v) > 120) echo '...';
										}
									?></td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>

						<?php foreach ($repeater_fields as $rep_name => $sub_fields): ?>
							<tr>
								<td><strong><?php echo esc_html($rep_name); ?></strong> (repeater)</td>
								<?php foreach ($group_post_ids as $pid): ?>
									<td><?php echo esc_html((int)get_post_meta($pid, $rep_name, true)); ?> rows</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>

						<?php foreach ($taxonomy_fields as $field_name => $taxonomy): ?>
							<tr>
								<td><strong><?php echo esc_html($field_name); ?></strong> (taxonomy)</td>
								<?php foreach ($group_post_ids as $pid):
									$terms = wp_get_object_terms($pid, $taxonomy, array('fields' => 'names'));
								?>
									<td><?php echo esc_html(is_array($terms) ? implode(', ', $terms) : ''); ?></td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<input type="hidden" name="group_index" value="<?php echo esc_attr($viewing_group); ?>">
				<p style="margin-top:16px">
					<button type="submit" class="button button-primary" id="pmig-merge-btn">Merge into Selected Post</button>
				</p>
			</form>

			<div id="pmig-merge-result"></div>

			<script>
			jQuery(function($) {
				var nonce = <?php echo wp_json_encode($nonce); ?>;

				$("#pmig-merge-form").on("submit", function(e) {
					e.preventDefault();
					var keepPost = $("input[name=keep_post]:checked").val();
					var groupIndex = $("input[name=group_index]").val();
					if (!keepPost) { alert("Select which post to keep."); return; }
					if (!confirm("Merge duplicates into post #" + keepPost + "? The other post(s) will be trashed.")) return;

					$("#pmig-merge-btn").prop("disabled", true).text("Merging...");
					$.ajax({
						url: ajaxurl,
						method: "POST",
						timeout: 60000,
						data: {
							action: "person_migration_merge",
							nonce: nonce,
							keep_post: keepPost,
							group_index: groupIndex
						},
						success: function(response) {
							$("#pmig-merge-btn").prop("disabled", false).text("Merge into Selected Post");
							if (response.success) {
								var d = response.data;
								var html = "<div class='notice notice-success' style='margin:12px 0'><p>Merged! Kept post #" + d.kept + ". Fields filled from donors: " + d.fields_filled + ". Content references updated: " + (d.refs_updated || 0) + ". Posts trashed: " + d.trashed.join(", ") + ".</p>";
								if (d.log && d.log.length) {
									html += "<ul style='font-size:12px;margin-top:4px'>";
									d.log.forEach(function(l) { html += "<li>" + $("<span>").text(l).html() + "</li>"; });
									html += "</ul>";
								}
								html += "</div>";
								$("#pmig-merge-result").html(html);
							} else {
								var msg = (response.data && response.data.error_message) ? response.data.error_message : "Unknown error";
								alert("Error: " + msg);
							}
						},
						error: function() {
							$("#pmig-merge-btn").prop("disabled", false).text("Merge into Selected Post");
							alert("AJAX error.");
						}
					});
				});
			});
			</script>

		<?php else: ?>
			<?php if (empty($duplicate_groups)): ?>
				<p>No duplicate groups found. Run "Scan for Duplicates" from the <a href="<?php echo esc_url(admin_url('admin.php?page=person-cpt-migration')); ?>">Person CPT Migration</a> page first.</p>
			<?php else: ?>
				<p><?php echo count($duplicate_groups); ?> duplicate group(s) found. Click a group to review and merge.</p>
				<table class="widefat striped" style="margin-top:12px">
					<thead>
						<tr>
							<th>Group</th>
							<th>People</th>
							<th>Matching On</th>
							<th>Action</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($duplicate_groups as $gi => $group): ?>
							<?php
							$names = array();
							$emails = array();
							foreach ($group as $pid) {
								$names[]  = get_the_title($pid) . ' (#' . $pid . ')';
								$e = get_post_meta($pid, 'uga_email', true);
								if ($e) $emails[] = $e;
							}
							$match_reasons = array();
							$unique_emails = array_unique(array_map('strtolower', $emails));
							if (count($emails) > count($unique_emails) || (count($unique_emails) === 1 && count($emails) > 1)) {
								$match_reasons[] = 'Email: ' . implode(', ', array_unique($emails));
							}
							// Check name match
							$name_keys = array();
							foreach ($group as $pid) {
								$f = strtolower(trim(get_post_meta($pid, 'first_name', true)));
								$l = strtolower(trim(get_post_meta($pid, 'last_name', true)));
								if ($f && $l) $name_keys[] = $f . ' ' . $l;
							}
							$unique_names = array_unique($name_keys);
							if (count($name_keys) > count($unique_names) || (count($unique_names) === 1 && count($name_keys) > 1)) {
								$match_reasons[] = 'Name: ' . implode(', ', array_unique($name_keys));
							}
							if (empty($match_reasons)) $match_reasons[] = 'Name/email overlap';
							?>
							<tr>
								<td><?php echo esc_html($gi + 1); ?></td>
								<td><?php echo esc_html(implode(' / ', $names)); ?></td>
								<td><?php echo esc_html(implode('; ', $match_reasons)); ?></td>
								<td><a href="<?php echo esc_url(admin_url('admin.php?page=person-merge-duplicates&group=' . $gi)); ?>" class="button button-small">Review</a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}

// ============================================================
// AJAX: merge duplicate posts
// ============================================================

function person_migration_ajax_merge() {
	person_migration_check_ajax();

	$keep_post   = intval($_POST['keep_post']);
	$group_index = intval($_POST['group_index']);

	$duplicate_groups = get_option(PERSON_MIGRATION_DUPES_KEY, array());
	if (!isset($duplicate_groups[$group_index])) {
		wp_send_json_error(array('error_message' => 'Invalid group index.'));
	}

	$group = $duplicate_groups[$group_index];
	if (!in_array($keep_post, $group)) {
		wp_send_json_error(array('error_message' => 'Selected post is not in this duplicate group.'));
	}

	$donor_ids    = array_diff($group, array($keep_post));
	$log          = array();
	$fields_filled = 0;

	// Simple fields: fill empty fields on keeper from donors
	$all_fields = array_merge(
		array('first_name', 'last_name', 'display_name'),
		person_migration_get_simple_fields()
	);

	foreach ($all_fields as $field_name) {
		$keeper_val = get_post_meta($keep_post, $field_name, true);
		if (!empty($keeper_val)) continue;

		foreach ($donor_ids as $donor_id) {
			$donor_val = get_post_meta($donor_id, $field_name, true);
			if (!empty($donor_val)) {
				update_post_meta($keep_post, $field_name, $donor_val);
				// Copy ACF reference key if it exists
				$ref = get_post_meta($donor_id, '_' . $field_name, true);
				if ($ref !== '' && $ref !== false) {
					update_post_meta($keep_post, '_' . $field_name, $ref);
				}
				$display = is_array($donor_val) ? json_encode($donor_val) : mb_substr((string)$donor_val, 0, 80);
				$log[] = $field_name . ': filled from post #' . $donor_id . ' = "' . $display . '"';
				$fields_filled++;
				break;
			}
		}
	}

	// Repeater fields: fill if keeper has 0 rows
	$repeater_fields = person_migration_get_repeater_fields();
	foreach ($repeater_fields as $rep_name => $sub_fields) {
		$keeper_count = (int) get_post_meta($keep_post, $rep_name, true);
		if ($keeper_count > 0) continue;

		foreach ($donor_ids as $donor_id) {
			$donor_count = (int) get_post_meta($donor_id, $rep_name, true);
			if ($donor_count === 0) continue;

			update_post_meta($keep_post, $rep_name, $donor_count);
			$rep_ref = get_post_meta($donor_id, '_' . $rep_name, true);
			if ($rep_ref !== '' && $rep_ref !== false) {
				update_post_meta($keep_post, '_' . $rep_name, $rep_ref);
			}
			for ($i = 0; $i < $donor_count; $i++) {
				foreach ($sub_fields as $sub) {
					$meta_key = $rep_name . '_' . $i . '_' . $sub;
					$val = get_post_meta($donor_id, $meta_key, true);
					if ($val !== '' && $val !== false && $val !== null) {
						update_post_meta($keep_post, $meta_key, $val);
					}
					$ref_key = '_' . $meta_key;
					$ref_val = get_post_meta($donor_id, $ref_key, true);
					if ($ref_val !== '' && $ref_val !== false) {
						update_post_meta($keep_post, $ref_key, $ref_val);
					}
				}
			}
			$log[] = $rep_name . ': ' . $donor_count . ' rows copied from post #' . $donor_id;
			$fields_filled++;
			break;
		}
	}

	// Taxonomy fields: merge terms from donors
	$taxonomy_fields = person_migration_get_taxonomy_fields();
	foreach ($taxonomy_fields as $field_name => $taxonomy) {
		$keeper_terms = wp_get_object_terms($keep_post, $taxonomy, array('fields' => 'ids'));
		foreach ($donor_ids as $donor_id) {
			$donor_terms = wp_get_object_terms($donor_id, $taxonomy, array('fields' => 'ids'));
			if (!empty($donor_terms) && !is_wp_error($donor_terms)) {
				$merged = array_unique(array_merge(
					is_array($keeper_terms) ? $keeper_terms : array(),
					$donor_terms
				));
				wp_set_object_terms($keep_post, array_map('intval', $merged), $taxonomy);
				$new_count = count($merged) - count($keeper_terms);
				if ($new_count > 0) {
					$log[] = $field_name . ': added ' . $new_count . ' terms from post #' . $donor_id;
					$fields_filled++;
				}
			}
		}
	}

	// Update the lookup map: point all donor user IDs to the keeper post
	$map = person_migration_get_map();
	foreach ($donor_ids as $donor_id) {
		$donor_user_id = array_search($donor_id, $map);
		if ($donor_user_id !== false) {
			$map[$donor_user_id] = $keep_post;
		}
	}
	update_option(PERSON_MIGRATION_MAP_KEY, $map, false);

	// Sweep content references: update any posts/pubs/stories that reference
	// donor post IDs in repeater sub-fields or flat meta to point to the keeper.
	// This handles the case where the repeater swap has already run, or where
	// flat meta has already been repopulated with CPT post IDs.
	$content_post_types = array('post', 'caes_publication', 'shorthand_story');
	$repeater_names     = array('authors', 'experts', 'translator', 'artists');
	$sub_field_names    = array('user', 'author', 'expert');
	$flat_fields        = array('all_author_ids', 'all_expert_ids');
	$refs_updated       = 0;

	$content_posts = get_posts(array(
		'post_type'      => $content_post_types,
		'post_status'    => array('publish', 'draft', 'private'),
		'posts_per_page' => -1,
		'fields'         => 'ids',
	));

	foreach ($content_posts as $cp_id) {
		// Check repeater sub-fields for donor post IDs
		foreach ($repeater_names as $repeater_name) {
			$count = (int) get_post_meta($cp_id, $repeater_name, true);
			if ($count <= 0) continue;

			for ($i = 0; $i < $count; $i++) {
				foreach ($sub_field_names as $sub) {
					$meta_key = $repeater_name . '_' . $i . '_' . $sub;
					$val = get_post_meta($cp_id, $meta_key, true);
					if (!empty($val) && in_array((int)$val, array_map('intval', $donor_ids))) {
						update_post_meta($cp_id, $meta_key, $keep_post);
						$refs_updated++;
					}
				}
			}
		}

		// Check flat meta fields for donor post IDs
		foreach ($flat_fields as $flat_field) {
			$raw = get_post_meta($cp_id, $flat_field, true);
			if (empty($raw)) continue;

			$ids = maybe_unserialize($raw);
			if (!is_array($ids)) {
				$ids = array_filter(array_map('trim', explode(',', $raw)));
			}

			$changed = false;
			$new_ids = array();
			foreach ($ids as $id) {
				if (in_array((int)$id, array_map('intval', $donor_ids))) {
					$new_ids[] = (string)$keep_post;
					$changed = true;
				} else {
					$new_ids[] = $id;
				}
			}

			if ($changed) {
				$new_ids = array_unique($new_ids);
				update_post_meta($cp_id, $flat_field, implode(',', $new_ids));
				$refs_updated++;
			}
		}
	}

	if ($refs_updated > 0) {
		$log[] = 'Updated ' . $refs_updated . ' content references (repeaters/flat meta) from donor IDs to keeper';
	}

	// Trash donor posts
	$trashed = array();
	foreach ($donor_ids as $donor_id) {
		wp_trash_post($donor_id);
		delete_post_meta($donor_id, '_duplicate_group');
		$trashed[] = $donor_id;
	}

	// Clean up the keeper's duplicate flag
	delete_post_meta($keep_post, '_duplicate_group');

	// Remove this group from stored duplicate groups
	unset($duplicate_groups[$group_index]);
	$duplicate_groups = array_values($duplicate_groups);
	update_option(PERSON_MIGRATION_DUPES_KEY, $duplicate_groups, false);

	wp_send_json_success(array(
		'kept'          => $keep_post,
		'trashed'       => $trashed,
		'fields_filled' => $fields_filled,
		'refs_updated'  => $refs_updated,
		'log'           => $log,
	));
}
