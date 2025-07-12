<?php
// Get user ID - check global first, then fall back to archive page  
$author_id = isset($GLOBALS['caes_current_user_id']) ? $GLOBALS['caes_current_user_id'] : get_queried_object_id();

// Get position title
$title = get_user_meta($author_id, 'title', true);

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

echo '<p ' . $attrs . ' >' . esc_html($title) . '</p>';
?>