<?php
// Get the current post ID
$post_id = get_the_ID();
$fontSize = isset($block['headingFontSize']) && !empty($block['headingFontSize']) ? esc_attr($block['headingFontSize']) : '';
$fontUnit = isset($block['headingFontUnit']) ? esc_attr($block['headingFontUnit']) : 'px';

// Generate inline style if font size is set
$style = $fontSize ? ' style="font-size: ' . $fontSize . $fontUnit . ';"' : '';

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

if( !empty(get_field('cost', $post_id)) ):
	$cost = '$'.number_format(get_field('cost', $post_id), 2);
endif; 
?>

<?php 
if (!empty($cost)) { 
    echo '<div ' . $attrs . '>';
    echo '<h3 class="event-details-title"' . $style . '>Cost</h3>';
    echo '<div class="event-details-content">' . $cost . '</div>';
    echo '</div>';
} 
?>