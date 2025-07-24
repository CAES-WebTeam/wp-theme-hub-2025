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

// Use the correct field name - online_location instead of location_virtual
$virtual = get_field('online_location', $post_id);
$online_web_address = get_field('online_location_web_address', $post_id);
$online_web_label = get_field('online_location_web_address_label', $post_id);

?>

<?php
if (!empty($virtual) || !empty($online_web_address)) {
    if ($onlineAsSnippet) {
        echo '<div ' . $attrs . '>';
        echo '<h3 class="event-details-title"' . $style . '>Virtual Event</h3>';
        echo '</div>'; // Close wrapper  
        return;
    } else {
        echo '<div ' . $attrs . '>';
        echo '<h3 class="event-details-title"' . $style . '>Online Location</h3>';
        echo '<div class="event-details-content">';
        
        if (!empty($virtual)) {
            echo $virtual;
        }
        
        if (!empty($online_web_address)) {
            $link_text = !empty($online_web_label) ? $online_web_label : $online_web_address;
            echo '<p><a href="' . esc_url($online_web_address) . '">' . esc_html($link_text) . '</a></p>';
        }
        
        echo '</div>'; // Close event-details-content
        echo '</div>'; // Close wrapper   
        return;
    }
}
?>