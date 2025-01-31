<?php

// Get the current post ID
$post_id = get_the_ID();

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

// Get the type from ACF fields
$authors = get_field('authors', $post_id);


// Check if authors are set
if ( $authors ) {
    echo '<div ' . $attrs . '>';
    echo '<h2>Authors</h2>';

    // Loop through authors
    foreach ( $authors as $item ) {
        $type = $item['type']; 
        $user = $item['user'];
        $custom = $item['custom_user'];
        
        echo '<div class="author">';
        
        if ( $type === 'user' && $user ) {
            // Wordpress user
            $user_id = $user; 
            $first_name = get_the_author_meta( 'first_name', $user_id );
            $last_name = get_the_author_meta( 'last_name', $user_id );
            $title = get_the_author_meta( 'title', $user_id );
            $profile_url = get_author_posts_url( $user_id );

            echo '<a href="' . esc_url( $profile_url ) . '" class="author-link">';
            echo esc_html( $first_name . ' ' . $last_name );
            if ( $title ) {
                echo ' - ' . esc_html( $title );
            }
            echo '</a>';
        } elseif ( $type === 'custom' && $custom ) {
            // Custom author
            $first_name = $custom['first_name'] ?? '';
            $last_name = $custom['last_name'] ?? '';
            $title = $custom['title'] ?? '';

            echo '<span class="custom-author">';
            echo esc_html( $first_name . ' ' . $last_name );
            if ( $title ) {
                echo ' - ' . esc_html( $title );
            }
            echo '</span>';
        }

        echo '</div>';
    }
    echo '</div>';
}
?>
