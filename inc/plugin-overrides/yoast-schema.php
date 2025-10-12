<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Modify Yoast SEO schema to add custom field data.
 * This function acts as a router for different post types.
 *
 * @param array $graph The schema graph array.
 * @return array The modified schema graph.
 */

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
        // --- THIS IS THE CHANGE --- //
        // This now handles both 'publications' and 'post' post types.
        case 'publications':
        case 'post':
            $graph = my_theme_add_post_authors_to_schema( $graph );
            break;
        // You can add more cases here for other CPTs
        // case 'your_custom_post_type':
        //     $graph = my_theme_add_artists_to_schema( $graph );
        //     break;
    }

    return $graph;
}
add_filter( 'wpseo_schema_graph', 'my_theme_modify_yoast_schema', 11, 1 );

/**
 * Replaces the default author with authors from an ACF repeater field for 'post' post types.
 *
 * @param array $graph The schema graph array.
 * @return array The modified schema graph.
 */
function my_theme_add_post_authors_to_schema( $graph ) {
    $post_id = get_the_ID();
    $authors = get_field( 'authors', $post_id );

    // If there are no authors in our custom field, leave the schema as is.
    if ( empty( $authors ) ) {
        return $graph;
    }

    // Generate an array of 'Person' schema pieces from our ACF data.
    $author_schema_pieces = my_theme_generate_person_schema( $authors );

    if ( empty( $author_schema_pieces ) ) {
        return $graph;
    }

    // Find the main Article piece in the graph so we can modify it.
    // We pass a reference (&) so our changes stick.
    $article_piece = find_schema_piece_by_type( $graph, 'Article' );

    if ( $article_piece ) {
        // Create an array of references to our new Person objects.
        // This is how schema pieces are linked together.
        $author_references = [];
        foreach ( $author_schema_pieces as $person ) {
            $author_references[] = [ '@id' => $person['@id'] ];
        }

        // Replace the default author with our array of author references.
        $article_piece['author'] = $author_references;

        // Add our new 'Person' schema pieces to the main graph.
        $graph['@graph'] = array_merge( $graph['@graph'], $author_schema_pieces );
    }

    return $graph;
}

/**
 * Converts an ACF repeater field of people into an array of 'Person' schema objects.
 * This is adapted from the logic in your render.php file.
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
            // For custom users, we create a URL-based ID.
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

        // Only proceed if we successfully got a name.
        if ( ! empty( $full_name ) ) {
            $person_schema = [
                '@type' => 'Person',
                '@id'   => $profile_url, // A unique ID is crucial for linking.
                'name'  => esc_html( $full_name ),
                'url'   => esc_url( $profile_url )
            ];

            if ( ! empty( $title ) ) {
                $person_schema['jobTitle'] = esc_html( $title );
            }
            
            // You could also add 'sameAs' for social media links if you have them.

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
    $found_piece = null; // Use a variable to hold the reference.
    foreach ( $graph['@graph'] as &$piece ) {
        // The @type can be a string or an array of strings.
        if ( ( is_string( $piece['@type'] ) && $piece['@type'] === $type ) || 
             ( is_array( $piece['@type'] ) && in_array( $type, $piece['@type'] ) ) ) {
            $found_piece = &$piece;
            return $found_piece;
        }
    }
    return $found_piece; // Will be null if not found.
}