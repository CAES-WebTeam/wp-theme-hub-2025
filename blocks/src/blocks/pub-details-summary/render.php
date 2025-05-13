<?php
// Get block attributes
$word_limit = isset($block['wordLimit']) ? (int) $block['wordLimit'] : 0;

// Get the current post ID
$post_id = get_the_ID();

$summary = get_field('summary', $post_id);

if ( $word_limit > 0 ) {
    // Strip tags for word count
    $stripped = wp_strip_all_tags($summary);
    $words = explode(' ', $stripped);

    if (count($words) > $word_limit) {
        $summary = implode(' ', array_slice($words, 0, $word_limit)) . '…';
        // Escape output to avoid broken HTML
        echo '<div ' . get_block_wrapper_attributes() . '>' . esc_html($summary) . '</div>';
    } else {
        echo '<div ' . get_block_wrapper_attributes() . '>' . wp_kses_post($summary) . '</div>';
    }
} else {
    echo '<div ' . get_block_wrapper_attributes() . '>' . wp_kses_post($summary) . '</div>';
}

?>