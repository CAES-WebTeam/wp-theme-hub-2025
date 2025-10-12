<?php

// Pretty print for debugging
add_filter( 'yoast_seo_development_mode', '__return_true' );

/**
 * Remove the author from the Yoast SEO Article schema.
 */
add_filter( 'wpseo_schema_article', 'remove_author_from_schema' );

function remove_author_from_schema( $data ) {
    if ( isset( $data['author'] ) ) {
        unset( $data['author'] );
    }
    return $data;
}

/**
 * Disable the Person schema.
 */
add_filter( 'wpseo_schema_needs_author', '__return_false' );