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
                    $pieces[] = new \Yoast\WP\SEO\Generators\Schema\Person( $user_id );
                }
            }
        }
    }

    return $pieces;
}
add_filter( 'wpseo_schema_graph_pieces', 'caes_add_author_schema_pieces', 11, 2 );


/**
 * 2. Modifies the 'Article' piece to reference the custom authors.
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
                    // **FIXED LINE:** Instantiate the Person object to get its generated ID.
                    $person_piece = new \Yoast\WP\SEO\Generators\Schema\Person( $user_id );
                    $author_references[] = [ '@id' => $person_piece->get_id() ];
                }
            }
        }
    }

    if ( ! empty( $author_references ) ) {
        $data['author'] = $author_references;
    }

    return $data;
}
add_filter( 'wpseo_schema_article', 'caes_update_article_author_references', 11, 1 );

/**
 * 3. Explicitly tells Yoast not to output its default 'Person' piece for the post's author.
 */
add_filter( 'wpseo_schema_needs_author', '__return_false' );