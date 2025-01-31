<?php

// Get the current post ID
$post_id = get_the_ID();

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

// Get the references ACF field
$references = get_field('references', $post_id);


// Check if references is set
if ( $references ) {
    echo '<div ' . $attrs . '>';
    echo '<h2>References</h2>';

	// Loop through references
    foreach ( $references as $item ) {
        $title = $item['title'];
        $text = $item['text'];
        $url = esc_url( $item['url'] );
        echo '<div class="reference">';
        echo $title;
        echo $text;
        if( $url ){ echo '<a href="'.$url.'" target="outside">'.$url.'</a>'; }
        echo '</div>';
    }
    echo '</div>';
}
?>
