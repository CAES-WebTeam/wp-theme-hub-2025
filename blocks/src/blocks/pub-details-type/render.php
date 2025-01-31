<?php

// Get the current post ID
$post_id = get_the_ID();

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

// Get the type from ACF fields
$publication_type = get_field('publication_type', $post_id);


// Check if type is set
if ( $publication_type ) {
    echo '<div ' . $attrs . '>';
    echo esc_html( $publication_type['label'] ?? '' );
    echo '</div>';
}
?>
