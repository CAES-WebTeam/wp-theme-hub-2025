<?php
// Grab attributes
$element = $block['element'] ?? 'h1';

// Try both camelCase and snake_case for the linkToProfile attribute
$link_to_profile = $block['linkToProfile'] ?? $block['link_to_profile'] ?? false;

// Get user ID - check global first, then fall back to archive page
$author_id = isset($GLOBALS['caes_current_user_id']) ? $GLOBALS['caes_current_user_id'] : get_queried_object_id();

// Get first and last name
$first_name = get_user_meta($author_id, 'first_name', true);
$last_name = get_user_meta($author_id, 'last_name', true);

// Combine names
$full_name = trim($first_name . ' ' . $last_name);

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

// Build the content
$content = '';

// Make sure we have a valid author ID and the link option is explicitly true
if ($link_to_profile === true && !empty($author_id) && is_numeric($author_id)) {
    // Get the author posts URL for the profile link
    $profile_url = get_author_posts_url($author_id);
    $content = '<a href="' . esc_url($profile_url) . '">' . esc_html($full_name) . '</a>';
} else {
    $content = esc_html($full_name);
}

// Output the element with content
echo '<' . esc_html($element) . ' ' . $attrs . '>' . $content . '</' . esc_html($element) . '>';
?>