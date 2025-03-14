<?php
// Get the current post ID
$post_id = get_the_ID();
// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();
$fontSize = isset($block['headingFontSize']) && !empty($block['headingFontSize']) ? esc_attr($block['headingFontSize']) : '';
$fontUnit = isset($block['headingFontUnit']) ? esc_attr($block['headingFontUnit']) : 'px';

// Generate inline style if font size is set
$style = $fontSize ? ' style="font-size: ' . $fontSize . $fontUnit . ';"' : '';

if( !empty(get_field('parking_info', $post_id)) ):
	$parking = get_field('parking_info', $post_id);
endif; 
?>

<?php 
if (!empty($parking)) {
    echo '<div ' . $attrs . '>';
    echo '<h3 class="event-details-title"' . $style . '>Parking Info</h3>';
    echo '<div class="event-details-content">';
    echo $parking;
    echo '</div>'; // Close event-details-content
    echo '</div>'; // Close wrapper
}
?>