<?php
// Get user ID - check global first, then fall back to archive page
$author_id = isset($GLOBALS['caes_current_user_id']) ? $GLOBALS['caes_current_user_id'] : get_queried_object_id();

// Resolve to CPT post ID
$person_post_id = function_exists('resolve_person_post_id') ? resolve_person_post_id($author_id) : null;

if ($person_post_id && function_exists('is_person_active') && !is_person_active($person_post_id)) {
    return;
}

// Build department list with optional site_url
$departments = [];

if ($person_post_id) {
    // Sync uses wp_set_object_terms() so get_the_terms() is correct here
    $terms = get_the_terms($person_post_id, 'person_department');
    if (!empty($terms) && !is_wp_error($terms)) {
        foreach ($terms as $term) {
            $departments[] = array(
                'name' => $term->name,
                'url'  => get_term_meta($term->term_id, 'site_url', true),
            );
        }
    }
} else {
    $dept = get_field('department', 'user_' . $author_id);
    if (!empty($dept)) {
        $departments[] = array('name' => $dept, 'url' => '');
    }
}

if (empty($departments)) {
    return;
}

$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

$render_dept = function($d) {
    $name = esc_html($d['name']);
    if (!empty($d['url'])) {
        // Force https on output even if stored as http
        $href = preg_replace('#^http://#i', 'https://', $d['url']);
        return '<a href="' . esc_url($href) . '">' . $name . '</a>';
    }
    return $name;
};

if (count($departments) === 1) {
    echo '<p ' . $attrs . '>' . $render_dept($departments[0]) . '</p>';
} else {
    echo '<ul ' . $attrs . '>';
    foreach ($departments as $dept) {
        echo '<li>' . $render_dept($dept) . '</li>';
    }
    echo '</ul>';
}
?>
