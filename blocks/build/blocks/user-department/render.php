<?php
// Get user ID - check global first, then fall back to archive page  
$author_id = isset($GLOBALS['caes_current_user_id']) ? $GLOBALS['caes_current_user_id'] : get_queried_object_id();

// Get department using ACF function
$department = get_field('department', 'user_' . $author_id);

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

// Only display if department exists
if ($department) {
    echo '<p ' . $attrs . ' >' . esc_html($department) . '</p>';
}
?>