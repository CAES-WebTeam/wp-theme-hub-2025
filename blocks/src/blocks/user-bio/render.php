<?php
// Get user ID - check global first, then fall back to archive page  
$author_id = isset($GLOBALS['caes_current_user_id']) ? $GLOBALS['caes_current_user_id'] : get_queried_object_id();

// Get the display option from block attributes
$display_option = isset($block['displayOption']) ? $block['displayOption'] : 'bio';

// Get the appropriate content based on display option
if ($display_option === 'tagline') {
    // Get tagline using ACF function
    $content = get_field('tagline', 'user_' . $author_id);
} else {
    // Get biography using WordPress user meta
    $content = get_the_author_meta('description', $author_id);
}

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

// Only display if content exists
if ($content) {
    echo '<p ' . $attrs . ' >' . esc_html($content) . '</p>';
}
?>