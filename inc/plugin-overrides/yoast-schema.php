<?php
/**
 * Plugin Name: Custom Yoast Schema for Authors
 * Description: Replaces the default Yoast author schema with authors from an ACF repeater field, structuring them as proper graph pieces.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

add_filter( 'yoast_seo_development_mode', '__return_true' );



/**
 * 1. Generates and adds new 'Person' pieces to the schema graph for each author in the ACF field.
 *
 * This function hooks into 'wpseo_schema_graph_pieces' to add new, top-level
 * objects to the schema graph before the final graph is assembled.
 *
 * @param array  $pieces  The array of schema pieces.
 * @param string $context The context for which the schema is being generated.
 * @return array The modified array of schema pieces.
 */
function caes_add_author_schema_pieces( $pieces, $context ) {
    $authors = get_field('authors');

    if ( $authors ) {
        foreach ( $authors as $author_row ) {
            $entry_type = $author_row['type'] ?? '';

            if ( $entry_type === 'Custom' ) {
                $custom_user = $author_row['custom_user'] ?? $author_row['custom'] ?? [];
                $first_name = $custom_user['first_name'] ?? '';
                $last_name = $custom_user['last_name'] ?? '';
                $full_name = trim("$first_name $last_name");

                if ( ! empty($full_name) ) {
                    // Use a sanitized name to create a unique ID for the person
                    $person_id = $context->site_url . '#/schema/person/' . md5( strtolower( $full_name ) );
                    $pieces[] = new \Yoast\WP\SEO\Generators\Schema\Person(
                        $person_id,
                        $full_name
                    );
                }
            } else {
                $user_data = $author_row['user'] ?? null;
                $user_id = is_array($user_data) ? ($user_data['ID'] ?? null) : $user_data;
                
                if ( $user_id && is_numeric($user_id) ) {
                    // Yoast's Person generator can directly use a user ID to build the object
                    $pieces[] = new \Yoast\WP\SEO\Generators\Schema\Person( $user_id );
                }
            }
        }
    }

    return $pieces;
}
add_filter( 'wpseo_schema_graph_pieces', 'caes_add_author_schema_pieces', 11, 2 );


/**
 * 2. Modifies the 'Article' piece to reference the custom authors and removes the default author.
 *
 * This function hooks into 'wpseo_schema_article' to change the 'author' property.
 * It replaces the default author with an array of ID references to the 'Person'
 * pieces we generated in the function above.
 *
 * @param array $data The Schema Article data.
 * @return array The modified Schema Article data.
 */
function caes_update_article_author_references( $data ) {
    $authors = get_field('authors');
    $author_references = [];

    if ( $authors ) {
        foreach ( $authors as $author_row ) {
            $entry_type = $author_row['type'] ?? '';

            if ( $entry_type === 'Custom' ) {
                $custom_user = $author_row['custom_user'] ?? $author_row['custom'] ?? [];
                $first_name = $custom_user['first_name'] ?? '';
                $last_name = $custom_user['last_name'] ?? '';
                $full_name = trim("$first_name $last_name");

                if ( ! empty($full_name) ) {
                    $person_id = home_url( '#/schema/person/' . md5( strtolower( $full_name ) ) );
                    $author_references[] = [ '@id' => $person_id ];
                }
            } else {
                $user_data = $author_row['user'] ?? null;
                $user_id = is_array($user_data) ? ($user_data['ID'] ?? null) : $user_data;

                if ( $user_id && is_numeric($user_id) ) {
                    // Yoast generates a predictable ID for its Person objects based on the user ID
                    $author_references[] = [ '@id' => \Yoast\WP\SEO\Generators\Schema\Person::get_user_schema_id( $user_id, get_post() ) ];
                }
            }
        }
    }

    // If we have custom authors, replace the default author with our references.
    if ( ! empty( $author_references ) ) {
        $data['author'] = $author_references;
    }

    return $data;
}
add_filter( 'wpseo_schema_article', 'caes_update_article_author_references', 11, 1 );

/**
 * 3. Explicitly tells Yoast not to output its default 'Person' piece for the post's author.
 *
 * This prevents the extra "caeswp" user from appearing in the graph.
 */
add_filter( 'wpseo_schema_needs_author', '__return_false' );