<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Modify Yoast SEO schema to add custom field data.
 * This function acts as a router for different post types.
 *
 * @param array $graph The schema graph array.
 * @return array The modified schema graph.
 */
function my_theme_modify_yoast_schema( $graph ) {
    // Ensure we're on a single post and have a graph to work with.
    if ( ! is_singular() || ! is_array( $graph ) ) {
        return $graph;
    }

    $post_type = get_post_type();

    // Route to the correct handler based on post type.
    switch ( $post_type ) {
        case 'publications':
        case 'post':
            $graph = my_theme_add_post_authors_to_schema( $graph );
            break;
    }

    return $graph;
}
add_filter( 'wpseo_schema_graph', 'my_theme_modify_yoast_schema', 99, 1 );

/**
 * Replaces the default author with authors from an ACF repeater field for 'post' post types.
 *
 * @param array $graph The schema graph array.
 * @return array The modified schema graph.
 */
function my_theme_add_post_authors_to_schema( $graph ) {
    $post_id = get_the_ID();
    $authors = get_field( 'authors', $post_id );

    if ( empty( $authors ) ) {
        return $graph;
    }

    $author_schema_pieces = my_theme_generate_person_schema( $authors );

    if ( empty( $author_schema_pieces ) ) {
        return $graph;
    }

    // --- THIS IS THE CORRECTED PART --- //
    // Find the main Article piece in the graph.
    // It could be 'NewsArticle', 'BlogPosting', or a generic 'Article'.
    $article_piece = find_schema_piece_by_type( $graph, 'NewsArticle' );
    if ( ! $article_piece ) {
        $article_piece = find_schema_piece_by_type( $graph, 'BlogPosting' );
    }
    if ( ! $article_piece ) {
        $article_piece = find_schema_piece_by_type( $graph, 'Article' );
    }
    // --- END CORRECTION --- //

    if ( $article_piece ) {
        $author_references = [];
        foreach ( $author_schema_pieces as $person ) {
            $author_references[] = [ '@id' => $person['@id'] ];
        }

        $article_piece['author'] = $author_references;
        $graph['@graph'] = array_merge( $graph['@graph'], $author_schema_pieces );
    }

    return $graph;
}

/**
 * Converts an ACF repeater field of people into an array of 'Person' schema objects.
 *
 * @param array $people_data The raw data from the ACF repeater field.
 * @return array An array of schema-compliant 'Person' objects.
 */
function my_theme_generate_person_schema( $people_data ) {
    $schema_pieces = [];

    if ( ! is_array( $people_data ) ) {
        return $schema_pieces;
    }

    foreach ( $people_data as $item ) {
        $entry_type = $item['type'] ?? '';
        $full_name = '';
        $title = '';
        $profile_url = '';

        if ( $entry_type === 'Custom' ) {
            $custom_user = $item['custom_user'] ?? $item['custom'] ?? [];
            $first_name = sanitize_text_field( $custom_user['first_name'] ?? '' );
            $last_name = sanitize_text_field( $custom_user['last_name'] ?? '' );
            $full_name = trim( "$first_name $last_name" );
            $title = sanitize_text_field( $custom_user['title'] ?? $custom_user['titile'] ?? '' );
            $profile_url = site_url( '/person/' ) . sanitize_title( $full_name ) . '#person';

        } else { // WordPress User
            $user_id = is_array($item['user']) ? ($item['user']['ID'] ?? null) : $item['user'];
            if ( $user_id && is_numeric( $user_id ) && $user_id > 0 ) {
                $full_name = get_the_author_meta( 'display_name', $user_id );
                $profile_url = get_author_posts_url( $user_id );
                $public_title = get_field( 'public_friendly_title', 'user_' . $user_id );
                $regular_title = get_the_author_meta( 'title', $user_id );
                $title = !empty( $public_title ) ? $public_title : $regular_title;
            }
        }

        if ( ! empty( $full_name ) ) {
            $person_schema = [
                '@type' => 'Person',
                '@id'   => $profile_url,
                'name'  => esc_html( $full_name ),
                'url'   => esc_url( $profile_url )
            ];

            if ( ! empty( $title ) ) {
                $person_schema['jobTitle'] = esc_html( $title );
            }

            $schema_pieces[] = $person_schema;
        }
    }

    return $schema_pieces;
}

/**
 * Helper function to find a specific piece in the Yoast schema graph by its @type.
 *
 * @param array  &$graph The schema graph array (passed by reference).
 * @param string $type   The @type to search for (e.g., 'Article', 'WebPage').
 * @return array|null    A reference to the piece if found, otherwise null.
 */
function &find_schema_piece_by_type( &$graph, $type ) {
    $found_piece = null;
    foreach ( $graph['@graph'] as &$piece ) {
        if ( ( is_string( $piece['@type'] ) && $piece['@type'] === $type ) ||
             ( is_array( $piece['@type'] ) && in_array( $type, $piece['@type'] ) ) ) {
            $found_piece = &$piece;
            return $found_piece;
        }
    }
    return $found_piece;
}