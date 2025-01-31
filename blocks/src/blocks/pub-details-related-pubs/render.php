<?php

// Get the current post ID
$post_id = get_the_ID();

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

// Get the related publications ACF fields
$related_pubs = get_field('related_publications', $post_id);


// Check if related publications is set
if ( $related_pubs ) {
    echo '<div ' . $attrs . '>';
    echo '<h2>Related Publications</h2>';

    // Loop through related publications
    foreach ( $related_pubs as $item ) {
        echo '<div class="related-pubs">';

        $item_id = $item['ID'] ?? $item; 
        $title = get_the_title( $item_id ); 
        $type = get_field( 'type', $item_id );
        $type_value = is_array( $type ) ? $type['value'] : $type; 
        $publication_number = get_field( 'publication_number', $item_id );

        echo esc_html( $title ) . '<br />';
        echo '(<span class="type">' . esc_html( $type_value ) . '</span> '; 
        echo '<span class="publication-number">' . esc_html( $publication_number ) . '</span>)';

        echo '</div>';
    }
    echo '</div>';
}
?>
