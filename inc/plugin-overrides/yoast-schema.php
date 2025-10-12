<?php
/**
 * Add custom authors (from ACF repeater) to Yoast schema.
 */
add_filter( 'wpseo_schema_graph_pieces', function( $pieces, $context ) {

    // Only for single posts or publications
    if ( ! is_singular( [ 'post', 'publications' ] ) ) {
        return $pieces;
    }

    // Get post ID and repeater data
    $post_id = get_the_ID();
    $authors = get_field( 'authors', $post_id );

    if ( empty( $authors ) ) {
        return $pieces;
    }

    // Build an array of schema Person objects
    $author_graph = [];
    foreach ( $authors as $author ) {
        // Adjust keys to match your ACF field names
        $author_graph[] = [
            '@type' => 'Person',
            'name'  => $author['name'] ?? '',
            'jobTitle' => $author['title'] ?? '',
            'affiliation' => [
                '@type' => 'Organization',
                'name'  => $author['organization'] ?? '',
            ],
            'url' => $author['url'] ?? '',
        ];
    }

    // Create a new custom schema piece
    $pieces[] = new class( $author_graph, $context ) extends \Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece {
        private $authors;
        public function __construct( $authors, $context ) {
            parent::__construct( $context );
            $this->authors = $authors;
        }

        public function generate() {
            return $this->authors;
        }

        public function is_needed() {
            return ! empty( $this->authors );
        }
    };

    return $pieces;
}, 11, 2 );

add_filter( 'wpseo_schema_article', function( $data, $context ) {
    if ( ! is_singular( [ 'post', 'publications' ] ) ) {
        return $data;
    }

    $post_id = get_the_ID();
    $authors = get_field( 'authors', $post_id );

    if ( empty( $authors ) ) {
        return $data;
    }

    // Replace Yoast’s default author with custom ones
    $data['author'] = [];
    foreach ( $authors as $author ) {
        $data['author'][] = [
            '@type' => 'Person',
            'name'  => $author['name'] ?? '',
            'jobTitle' => $author['title'] ?? '',
            'affiliation' => [
                '@type' => 'Organization',
                'name'  => $author['organization'] ?? '',
            ],
            'url' => $author['url'] ?? '',
        ];
    }

    return $data;
}, 11, 2 );
