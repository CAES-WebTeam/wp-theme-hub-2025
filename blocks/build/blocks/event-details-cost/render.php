<?php
// Get the current post ID
$post_id = get_the_ID();
$fontSize = isset($attributes['headingFontSize']) && !empty($attributes['headingFontSize']) ? esc_attr($attributes['headingFontSize']) : '';
$fontUnit = isset($attributes['headingFontUnit']) ? esc_attr($attributes['headingFontUnit']) : 'px';

// Generate inline style if font size is set
$style = $fontSize ? ' style="font-size: ' . $fontSize . $fontUnit . ';"' : '';

// Attributes for wrapper
$attrs = get_block_wrapper_attributes();

// Get the cost field and validate it
$cost_field = get_field('cost', $post_id);
$cost = '';

if( !empty($cost_field) && is_numeric($cost_field) ):
	$cost = number_format(floatval($cost_field), 2);
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