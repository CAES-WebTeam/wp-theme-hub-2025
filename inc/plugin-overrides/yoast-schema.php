<?php
/**
 * Yoast SEO & ACF Repeater Authors Integration - v3.0 (Stable)
 *
 * This file uses a single, reliable filter to replace the default Yoast author 
 * with a list of authors from an ACF repeater field named 'authors'.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('wpseo_schema_graph_pieces', 'caes_acf_replace_author_schema', 11, 2);

/**
 * Finds authors in an ACF field, validates them, and replaces the default
 * Yoast author schema in one pass.
 *
 * @param array  $pieces  The array of schema pieces from Yoast.
 * @param object $context The Yoast schema context object.
 * @return array The modified array of schema pieces.
 */
function caes_acf_replace_author_schema($pieces, $context) {
    // 1. Get ACF authors for the current post.
    $acf_authors = get_field('authors', $context->id);

    // If the ACF field is empty or not an array, do nothing and return the original schema.
    if (empty($acf_authors) || !is_array($acf_authors)) {
        return $pieces;
    }

    // 2. Process and validate the ACF authors into a clean list.
    $valid_authors = [];
    $canonical_url = $context->canonical ?: get_permalink($context->id);

    foreach ($acf_authors as $index => $author_data) {
        $details = [
            'name'       => '',
            'givenName'  => '',
            'familyName' => '',
            'url'        => '',
        ];
        $entry_type = $author_data['type'] ?? 'User';

        if ($entry_type === 'Custom') {
            $custom = $author_data['custom_user'] ?? $author_data['custom'] ?? [];
            $details['givenName'] = $custom['first_name'] ?? '';
            $details['familyName'] = $custom['last_name'] ?? '';
        } else { // 'User' type
            $user_id = null;
            if (!empty($author_data['user'])) {
                // Handle both array and ID formats from ACF User field.
                $user_id = is_array($author_data['user']) ? ($author_data['user']['ID'] ?? null) : $author_data['user'];
            }
            if ($user_id && is_numeric($user_id)) {
                $details['name']       = get_the_author_meta('display_name', $user_id);
                $details['givenName']  = get_the_author_meta('first_name', $user_id);
                $details['familyName'] = get_the_author_meta('last_name', $user_id);
                $details['url']        = get_author_posts_url($user_id);
            }
        }

        // If a display name wasn't found, construct one from first/last name.
        if (empty($details['name'])) {
            $details['name'] = trim($details['givenName'] . ' ' . $details['familyName']);
        }

        // IMPORTANT: Only add the author if they have a name.
        if (!empty($details['name'])) {
            $details['@id'] = $canonical_url . '#/schema/person/author-' . $index;
            $valid_authors[] = $details;
        }
    }

    // If, after checking all rows, no valid authors were found, do nothing.
    if (empty($valid_authors)) {
        return $pieces;
    }

    // 3. Modify the schema graph.
    $article_piece_index = -1;
    
    // Loop through the existing schema pieces to find the Article and remove the default Author.
    foreach ($pieces as $index => $piece) {
        // Use Yoast's official class name to find and remove the default author.
        if (is_a($piece, 'Yoast\WP\SEO\Generators\Schema\Author')) {
            unset($pieces[$index]);
        }
        // Find the main Article piece so we can update its author list later.
        if (isset($piece['@type']) && in_array('NewsArticle', (array) $piece['@type'])) {
            $article_piece_index = $index;
        }
    }
    
    // 4. Add our new, valid authors to the schema graph.
    foreach ($valid_authors as $author) {
        $author_piece = [
            '@type' => 'Person',
            '@id'   => $author['@id'],
            'name'  => $author['name'],
        ];
        
        if (!empty($author['givenName']))  $author_piece['givenName']  = $author['givenName'];
        if (!empty($author['familyName'])) $author_piece['familyName'] = $author['familyName'];
        if (!empty($author['url']))        $author_piece['url']        = $author['url'];
        
        $pieces[] = $author_piece;
    }

    // 5. Update the Article piece to reference our new authors.
    if ($article_piece_index !== -1) {
        $author_refs = array_map(function ($author) {
            return ['@id' => $author['@id']];
        }, $valid_authors);

        // Format correctly for one or multiple authors.
        $pieces[$article_piece_index]['author'] = (count($author_refs) === 1) ? $author_refs[0] : $author_refs;
    }

    // Return the final, modified graph (re-indexing the array is crucial).
    return array_values($pieces);
}