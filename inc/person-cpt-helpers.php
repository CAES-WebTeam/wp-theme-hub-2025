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
    $title         = get_post_meta($post_id, 'public_friendly_title', true);
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
 * Given a WP user ID, return the corresponding caes_hub_person post ID (if one exists).
 *
 * Uses the migration map for fast lookup. Returns null if no CPT post is mapped.
 * If the passed ID is already a caes_hub_person post, returns it directly.
 *
 * @param int $id A WP user ID or caes_hub_person post ID.
 * @return int|null The CPT post ID, or null.
 */
function resolve_person_post_id($id) {
    $id = (int) $id;
    if ($id <= 0) {
        return null;
    }

    // Already a CPT post?
    if (get_post_type($id) === 'caes_hub_person') {
        return $id;
    }

    // Check migration map: user ID -> post ID
    if (function_exists('person_migration_get_map')) {
        $map = person_migration_get_map();
        if (isset($map[$id])) {
            return (int) $map[$id];
        }
    }

    return null;
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

// ============================================================
// Admin columns: content counts for People list
// ============================================================

add_filter('manage_caes_hub_person_posts_columns', function ($columns) {
    // Insert after title
    $new = array();
    foreach ($columns as $key => $label) {
        $new[$key] = $label;
        if ($key === 'title') {
            $new['person_posts']        = 'Stories';
            $new['person_publications'] = 'Pubs';
            $new['person_shorthand']    = 'Shorthand';
        }
    }
    return $new;
});

/**
 * Get content count for a person, with lazy caching.
 */
function _person_get_content_count($person_id, $post_type) {
    $cache_key = '_content_count_' . $post_type;
    $cached = get_post_meta($person_id, $cache_key, true);
    if ($cached !== '' && $cached !== false) {
        return (int) $cached;
    }

    global $wpdb;
    $count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT p.ID)
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
         WHERE p.post_type = %s
           AND p.post_status IN ('publish','draft','private')
           AND pm.meta_key REGEXP %s
           AND pm.meta_value = %s",
        $post_type,
        '^(authors|experts|translator|artists)_[0-9]+_user$',
        (string) $person_id
    ));

    update_post_meta($person_id, $cache_key, $count);
    return $count;
}

/**
 * Invalidate content count cache for a person.
 */
function _person_invalidate_content_counts($person_id) {
    delete_post_meta($person_id, '_content_count_post');
    delete_post_meta($person_id, '_content_count_publications');
    delete_post_meta($person_id, '_content_count_shorthand_story');
}

add_action('manage_caes_hub_person_posts_custom_column', function ($column, $post_id) {
    $type_map = array(
        'person_posts'        => 'post',
        'person_publications' => 'publications',
        'person_shorthand'    => 'shorthand_story',
    );

    if (!isset($type_map[$column])) return;

    $post_type = $type_map[$column];
    $count = _person_get_content_count($post_id, $post_type);

    if ($count > 0) {
        $url = admin_url('edit.php?post_type=' . $post_type . '&person_filter=' . $post_id);
        echo '<a href="' . esc_url($url) . '">' . esc_html($count) . '</a>';
    } else {
        echo '<span style="color:#999">0</span>';
    }
}, 10, 2);

// Invalidate person content counts when a post/pub/shorthand is saved
add_action('save_post', function ($post_id, $post) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
    if (!in_array($post->post_type, array('post', 'publications', 'shorthand_story'))) return;

    // Find all person IDs referenced in repeaters on this post
    $repeater_names = array('authors', 'experts', 'translator', 'artists');
    $sub_fields = array('user');
    $person_ids = array();

    foreach ($repeater_names as $rn) {
        $count = (int) get_post_meta($post_id, $rn, true);
        for ($i = 0; $i < $count; $i++) {
            foreach ($sub_fields as $sf) {
                $val = get_post_meta($post_id, $rn . '_' . $i . '_' . $sf, true);
                if (!empty($val) && is_numeric($val)) {
                    $person_ids[(int) $val] = true;
                }
            }
        }
    }

    foreach (array_keys($person_ids) as $pid) {
        if (get_post_type($pid) === 'caes_hub_person') {
            _person_invalidate_content_counts($pid);
        }
    }
}, 10, 2);

// Filter post lists by person_filter param
add_action('pre_get_posts', function ($query) {
    if (!is_admin() || !$query->is_main_query()) return;

    $person_id = isset($_GET['person_filter']) ? (int) $_GET['person_filter'] : 0;
    if (!$person_id) return;

    global $wpdb;
    $post_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT post_id
         FROM {$wpdb->postmeta}
         WHERE meta_key REGEXP %s
           AND meta_value = %s",
        '^(authors|experts|translator|artists)_[0-9]+_user$',
        (string) $person_id
    ));

    if (!empty($post_ids)) {
        $query->set('post__in', $post_ids);
    } else {
        $query->set('post__in', array(0));
    }
});

// Make columns sortable (sort by count isn't practical, but at least present)
add_filter('manage_edit-caes_hub_person_sortable_columns', function ($columns) {
    return $columns;
});
