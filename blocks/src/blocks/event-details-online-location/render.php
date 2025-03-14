<?php
// Get the current post ID
$post_id = get_the_ID();
// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();
$onlineAsSnippet = $block['onlineAsSnippet'];
$fontSize = isset($block['headingFontSize']) && !empty($block['headingFontSize']) ? esc_attr($block['headingFontSize']) : '';
$fontUnit = isset($block['headingFontUnit']) ? esc_attr($block['headingFontUnit']) : 'px';

// Generate inline style if font size is set
$style = $fontSize ? ' style="font-size: ' . $fontSize . $fontUnit . ';"' : '';

if (!empty(get_field('location_virtual', $post_id))):
    $virtual = get_field('location_virtual', $post_id);
endif;
?>

<?php
if (!empty($virtual)) {
    if ($onlineAsSnippet) {
        echo '<div ' . $attrs . '>';
        echo '<h3 class="event-details-title"' . $style . '>Virtual Event</h3>';
        echo '</div>'; // Close wrapper  
        return;
    } else {
        echo '<div ' . $attrs . '>';
        echo '<h3 class="event-details-title"' . $style . '>Online Location</h3>';
        echo '<div class="event-details-content">';
        echo $virtual;
        echo '</div>'; // Close event-details-content
        echo '</div>'; // Close wrapper   
        return;
    }
}
?>