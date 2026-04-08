<?php
// Get user ID - check global first, then fall back to archive page
$author_id = isset($GLOBALS['caes_current_user_id']) ? $GLOBALS['caes_current_user_id'] : get_queried_object_id();

// Try CPT post first, fall back to user meta
$person_post_id = function_exists('resolve_person_post_id') ? resolve_person_post_id($author_id) : null;

if ($person_post_id && function_exists('is_person_active') && !is_person_active($person_post_id)) {
    return;
}

$title = $person_post_id ? get_post_meta($person_post_id, 'title', true) : get_user_meta($author_id, 'title', true);

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

echo '<p ' . $attrs . ' >' . esc_html($title) . '</p>';
?>