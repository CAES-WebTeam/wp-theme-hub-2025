<?php
// Get the current post ID
$post_id = get_the_ID();

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

?>

<?php 
if (!empty(get_field('documents', $post_id))) {
    echo '<div ' . $attrs . '>';
    echo '<h3 class="event-details-title">Additional Documents</h3>';
    echo '<div class="event-details-content">';

    foreach (get_field('documents', $post_id) as $item) {
        if ($item['document_type'] == 'link') {
            echo '<a href="' . esc_url($item['link']) . '">' . esc_html($item['link']) . '</a><br />';
        }

        if ($item['document_type'] == 'file') {
            echo '<a href="' . esc_url($item['file']['url']) . '">' . esc_html($item['file']['title']) . '</a><br />';
        }
    }

    echo '</div>'; // Close event-details-content
    echo '</div>'; // Close wrapper
}
?>