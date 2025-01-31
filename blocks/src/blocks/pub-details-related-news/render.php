<?php

// Get the current post ID
$post_id = get_the_ID();

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

// Get the related news ACF fields
$related_news = get_field('related_news', $post_id);

// Check if related news is set
if ( $related_news ) {
    echo '<div ' . $attrs . '>';
    echo '<h2>Related News</h2>';

    // Loop through related news
    foreach ( $related_news as $item ) {
        echo '<div class="related-news">';

        $item_id = $item['ID'] ?? $item;
        $title = get_the_title( $item_id ); 
        $featured_image = get_the_post_thumbnail( $item_id, 'thumbnail' ); 
        $link = get_permalink( $item_id ); 

        echo '<a href="' . esc_url( $link ) . '" class="related-news-link">';
        echo $featured_image;
        echo '<h3>' . esc_html( $title ) . '</h3>'; /
        echo '</a>';

        echo '</div>';
    }
    echo '</div>';
}

?>
