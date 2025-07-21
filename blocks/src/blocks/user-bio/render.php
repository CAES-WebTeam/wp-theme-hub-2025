<?php
// Get user ID - check global first, then fall back to archive page  
$author_id = isset($GLOBALS['caes_current_user_id']) ? $GLOBALS['caes_current_user_id'] : get_queried_object_id();

// Get the display option and fallback setting from block attributes
$display_option = isset($block['displayOption']) ? $block['displayOption'] : 'bio';
$enable_fallback = isset($block['enableFallback']) ? $block['enableFallback'] : false;

// Get the appropriate content based on display option
if ($display_option === 'tagline') {
    // Get tagline first
    $content = get_field('tagline', 'user_' . $author_id);
    
    // If tagline is empty and fallback is enabled, try biography
    if (empty($content) && $enable_fallback) {
        $content = get_the_author_meta('description', $author_id);
    }
} else {
    // Get biography first
    $content = get_the_author_meta('description', $author_id);
    
    // If biography is empty and fallback is enabled, try tagline
    if (empty($content) && $enable_fallback) {
        $content = get_field('tagline', 'user_' . $author_id);
    }
}

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

// Only display if content exists and is not empty
if (!empty($content)) {
    echo '<p ' . $attrs . ' >' . esc_html($content) . '</p>';
}
?>