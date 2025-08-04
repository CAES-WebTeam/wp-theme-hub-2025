<?php
// Get block attributes
$word_limit = isset($block['wordLimit']) ? (int) $block['wordLimit'] : 0;
$show_featured_image = isset($block['showFeaturedImage']) ? $block['showFeaturedImage'] : false;
$conditional_display = isset($block['conditionalDisplay']) ? $block['conditionalDisplay'] : false;

// Get the current post ID
$post_id = get_the_ID();

// Check conditional display
if ($conditional_display) {
    $post_content = get_post_field('post_content', $post_id);
    $post_content = trim(wp_strip_all_tags($post_content));
    
    // If content is not empty, don't display this block
    if (!empty($post_content)) {
        return;
    }
}

// Get the summary
$summary = get_field('summary', $post_id);

// If no summary, don't display anything
if (empty($summary)) {
    return;
}

// Start building output
$output = '';

// Add featured image if enabled
if ($show_featured_image && has_post_thumbnail($post_id)) {
    $featured_image = get_the_post_thumbnail($post_id, 'full', array(
        'style' => 'width: 100%; height: auto; display: block; margin-bottom: 1rem;'
    ));
    $output .= $featured_image;
}

// Process summary based on word limit
if ($word_limit > 0) {
    // Strip tags for word count
    $stripped = wp_strip_all_tags($summary);
    $words = explode(' ', $stripped);

    if (count($words) > $word_limit) {
        $summary = implode(' ', array_slice($words, 0, $word_limit)) . '…';
        // Escape output to avoid broken HTML
        $output .= esc_html($summary);
    } else {
        $output .= wp_kses_post($summary);
    }
} else {
    $output .= wp_kses_post($summary);
}

// Output the final result
echo '<div ' . get_block_wrapper_attributes() . '>' . $output . '</div>';

?>