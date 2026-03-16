<?php
/**
 * Person CPT helper functions.
 *
 * Provides resolve_person_data() which accepts either a WP user ID or a
 * caes_hub_person CPT post ID and returns a normalized data array. This
 * lets every rendering context work correctly both before and after the
 * repeater-ID swap (user IDs -> post IDs).
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolve person data from either a caes_hub_person post ID or a WP user ID.
 *
 * Checks for a CPT post first, falls back to user meta. Returns a normalized
 * array so callers don't need to know the source.
 *
 * @param int $id A caes_hub_person post ID or a WP user ID.
 * @return array|false Normalized person data, or false if the ID resolves to nothing.
 */
function resolve_person_data($id) {
    $id = (int) $id;
    if ($id <= 0) {
        return false;
    }

    // Safeguard: if the migration lookup map exists and this ID is a known
    // user ID in the map, always treat it as a user -- even if a
    // caes_hub_person post happens to share the same numeric ID.
    if (function_exists('person_migration_get_map')) {
        $map = person_migration_get_map();
        if (isset($map[$id])) {
            return _resolve_person_from_user($id);
        }
    }

    // Check if this is a caes_hub_person CPT post
    if (get_post_type($id) === 'caes_hub_person') {
        return _resolve_person_from_post($id);
    }

    // Check if this is a WP user
    $user = get_userdata($id);
    if ($user) {
        return _resolve_person_from_user($id, $user);
    }

    return false;
}

/**
 * Build person data from a caes_hub_person post.
 */
function _resolve_person_from_post($post_id) {
    $first_name    = get_post_meta($post_id, 'first_name', true);
    $last_name     = get_post_meta($post_id, 'last_name', true);
    $display_name  = get_post_meta($post_id, 'display_name', true);
    $title         = get_field('public_friendly_title', $post_id);
    if (empty($title)) {
        $title = get_post_meta($post_id, 'title', true);
    }
    $uga_email     = get_post_meta($post_id, 'uga_email', true);
    $profile_url   = get_permalink($post_id);

    $full_name = !empty($display_name) ? $display_name : trim("$first_name $last_name");

    return array(
        'source'       => 'post',
        'id'           => $post_id,
        'first_name'   => $first_name ?: '',
        'last_name'    => $last_name ?: '',
        'display_name' => $display_name ?: '',
        'full_name'    => $full_name,
        'title'        => $title ?: '',
        'email'        => $uga_email ?: '',
        'profile_url'  => $profile_url ?: '',
    );
}

/**
 * Build person data from a WP user.
 */
function _resolve_person_from_user($user_id, $user = null) {
    if (!$user) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
    }

    $first_name   = get_the_author_meta('first_name', $user_id);
    $last_name    = get_the_author_meta('last_name', $user_id);
    $display_name = get_the_author_meta('display_name', $user_id);
    $public_title = get_field('public_friendly_title', 'user_' . $user_id);
    $regular_title = get_the_author_meta('title', $user_id);
    $title        = !empty($public_title) ? $public_title : $regular_title;
    $uga_email    = get_field('field_uga_email_custom', 'user_' . $user_id);
    if (empty($uga_email)) {
        $uga_email = get_the_author_meta('user_email', $user_id);
    }
    $profile_url  = get_author_posts_url($user_id);

    $full_name = !empty($display_name) ? $display_name : trim("$first_name $last_name");

    return array(
        'source'       => 'user',
        'id'           => $user_id,
        'first_name'   => $first_name ?: '',
        'last_name'    => $last_name ?: '',
        'display_name' => $display_name ?: '',
        'full_name'    => $full_name,
        'title'        => $title ?: '',
        'email'        => $uga_email ?: '',
        'profile_url'  => $profile_url ?: '',
    );
}

/**
 * Extract the person ID from an ACF repeater row's 'user' field.
 *
 * Handles both array format (ACF formatted) and scalar (raw ID).
 * Includes the fallback scan for numeric values in the row.
 *
 * @param array $item A single ACF repeater row.
 * @return int|null The person/user ID, or null.
 */
function resolve_person_id_from_repeater_row($item) {
    $id = null;

    if (isset($item['user']) && !empty($item['user'])) {
        $id = is_array($item['user']) ? ($item['user']['ID'] ?? null) : $item['user'];
    }

    // Fallback: scan for numeric values (ACF internal field keys)
    if (empty($id) && is_array($item)) {
        foreach ($item as $key => $value) {
            if ($key === 'type' || $key === 'custom' || $key === 'custom_user') {
                continue;
            }
            if (is_numeric($value) && $value > 0) {
                $id = $value;
                break;
            }
        }
    }

    return $id ? (int) $id : null;
}
