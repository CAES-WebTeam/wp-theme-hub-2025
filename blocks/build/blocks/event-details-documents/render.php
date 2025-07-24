<?php
// Get the current post ID
$post_id = get_the_ID();

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();
$fontSize = isset($block['headingFontSize']) && !empty($block['headingFontSize']) ? esc_attr($block['headingFontSize']) : '';
$fontUnit = isset($block['headingFontUnit']) ? esc_attr($block['headingFontUnit']) : 'px';

// Generate inline style if font size is set
$style = $fontSize ? ' style="font-size: ' . $fontSize . $fontUnit . ';"' : '';

// DEBUG: Check the documents field structure
$documents = get_field('documents', $post_id);
// error_log('DEBUG: Documents field structure: ' . print_r($documents, true));

?>

<?php 
if (!empty($documents)) {
    echo '<div ' . $attrs . '>';
    echo '<h3 class="event-details-title"' . $style . '>Additional Documents</h3>';
    echo '<div class="event-details-content">';

    foreach ($documents as $index => $item) {
        // error_log('DEBUG: Item ' . $index . ': ' . print_r($item, true));
        
        if ($item['document_type'] == 'link') {
            $link_url = $item['lin']; // Note: field is 'lin' not 'link'
            $link_text = !empty($item['link_label_text']) ? $item['link_label_text'] : $link_url;
            echo '<a href="' . esc_url($link_url) . '">' . esc_html($link_text) . '</a><br />';
        }

        if ($item['document_type'] == 'file') {
            $file_url = $item['file']['url'];
            $file_text = !empty($item['file_label_text']) ? $item['file_label_text'] : $item['file']['title'];
            echo '<a href="' . esc_url($file_url) . '">' . esc_html($file_text) . '</a><br />';
        }
    }

    echo '</div>'; // Close event-details-content
    echo '</div>'; // Close wrapper
}
?>