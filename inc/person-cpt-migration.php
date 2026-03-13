<?php
/**
 * Person CPT Migration Tool
 *
 * Admin tool under CAES Tools that migrates personnel_user, expert_user,
 * and content_manager WordPress users to caes_hub_person CPT posts.
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

	// Set linked_wp_user for content managers
	$user_roles = (array) $user->roles;
	if (in_array('content_manager', $user_roles, true)) {
		if ($dry_run) {
			$result['field_log'][] = '[DRY RUN] linked_wp_user = ' . $user_id . ' (content_manager)';
		} else {
			update_post_meta($post_id, 'linked_wp_user', $user_id);
			$fields_written++;
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
		'role__in' => array('personnel_user', 'expert_user', 'content_manager'),
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
		'role__in' => array('personnel_user', 'expert_user', 'content_manager'),
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
		<p>Migrates <code>personnel_user</code>, <code>expert_user</code>, and <code>content_manager</code> WordPress users to <code>caes_hub_person</code> CPT posts.</p>
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
