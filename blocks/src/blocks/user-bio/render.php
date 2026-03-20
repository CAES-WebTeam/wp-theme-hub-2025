<?php
// Get user ID - check global first, then fall back to archive page
$author_id = isset($GLOBALS['caes_current_user_id']) ? $GLOBALS['caes_current_user_id'] : get_queried_object_id();

// Try CPT post first, fall back to user meta
$person_post_id = function_exists('resolve_person_post_id') ? resolve_person_post_id($author_id) : null;

// Get the display option and fallback setting from block attributes
$display_option = isset($block['displayOption']) ? $block['displayOption'] : 'bio';
$enable_fallback = isset($block['enableFallback']) ? $block['enableFallback'] : false;

// Get the appropriate content based on display option
if ($display_option === 'tagline') {
    $content = $person_post_id ? get_post_meta($person_post_id, 'tagline', true) : get_field('tagline', 'user_' . $author_id);
    if (empty($content) && $enable_fallback) {
        $content = $person_post_id ? get_post_meta($person_post_id, 'description', true) : get_the_author_meta('description', $author_id);
    }
} else {
    $content = $person_post_id ? get_post_meta($person_post_id, 'description', true) : get_the_author_meta('description', $author_id);
    if (empty($content) && $enable_fallback) {
        $content = $person_post_id ? get_post_meta($person_post_id, 'tagline', true) : get_field('tagline', 'user_' . $author_id);
    }
}

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

// Only display if content exists and is not empty
if (!empty($content)) {
    echo '<p ' . $attrs . ' >' . esc_html($content) . '</p>';
}
?>