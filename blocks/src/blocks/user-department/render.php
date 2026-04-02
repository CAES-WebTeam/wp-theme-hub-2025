<?php
// Get user ID - check global first, then fall back to archive page
$author_id = isset($GLOBALS['caes_current_user_id']) ? $GLOBALS['caes_current_user_id'] : get_queried_object_id();

// Resolve to CPT post ID
$person_post_id = function_exists('resolve_person_post_id') ? resolve_person_post_id($author_id) : null;

// Build department list
$departments = [];

if ($person_post_id) {
    // ACF taxonomy field with save_terms=0 -- use get_field() not get_the_terms()
    $term_ids = get_field('personnel_department', $person_post_id);
    if (!empty($term_ids)) {
        foreach ((array) $term_ids as $id) {
            $term = get_term($id, 'person_department');
            if ($term && !is_wp_error($term)) {
                $departments[] = $term->name;
            }
        }
    }
} else {
    $dept = get_field('department', 'user_' . $author_id);
    if (!empty($dept)) {
        $departments[] = $dept;
    }
}

if (empty($departments)) {
    return;
}

$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

if (count($departments) === 1) {
    echo '<p ' . $attrs . '>' . esc_html($departments[0]) . '</p>';
} else {
    echo '<ul ' . $attrs . '>';
    foreach ($departments as $dept) {
        echo '<li>' . esc_html($dept) . '</li>';
    }
    echo '</ul>';
}
?>
