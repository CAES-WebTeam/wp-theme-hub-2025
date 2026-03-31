<?php
// Get user ID - check global first, then fall back to archive page
$author_id = isset($GLOBALS['caes_current_user_id']) ? $GLOBALS['caes_current_user_id'] : get_queried_object_id();

// Try CPT taxonomy first, fall back to user meta
$person_post_id = function_exists('resolve_person_post_id') ? resolve_person_post_id($author_id) : null;

if ($person_post_id) {
    $terms = get_the_terms($person_post_id, 'person_department');
    $department = (!empty($terms) && !is_wp_error($terms)) ? $terms[0]->name : '';
} else {
    $department = get_field('department', 'user_' . $author_id);
}

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

// Only display if department exists
if ($department) {
    echo '<p ' . $attrs . ' >' . esc_html($department) . '</p>';
}
?>
