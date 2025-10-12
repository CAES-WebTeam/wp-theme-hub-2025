<?php
/**
 * Plugin Name: Custom Yoast Schema for Authors
 * Description: Replaces the default Yoast author schema with authors from an ACF repeater field, structuring them as proper graph pieces.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter( 'yoast_seo_development_mode', '__return_true' );



/**
 * Filters the entire Yoast SEO schema graph to replace the default author
 * with a list of authors from an ACF repeater field.
 *
 * @param array $data The entire schema graph data.
 * @return array The modified schema graph data.
 */
function caes_filter_yoast_schema_data($data) {
    // Proceed only if we're on a singular post and the graph exists.
    if (!is_singular('post') || !isset($data['@graph'])) {
        return $data;
    }

    $authors = get_field('authors');

    // If there are no ACF authors, do nothing.
    if (empty($authors)) {
        return $data;
    }

    $new_author_references = [];
    $default_author_id = null;
    $article_key = null;

    // First, let's find the article and the default author to remove them.
    foreach ($data['@graph'] as $key => $piece) {
        if ($piece['@type'] === 'Person' && isset($piece['name'])) {
            // Store the ID of the default Person piece so we can remove it.
            $default_author_id = $piece['@id'];
        }
        if (str_contains($piece['@type'], 'Article')) {
            // Store the array key of the Article piece.
            $article_key = $key;
        }
    }
    
    // If we found a default author, remove that piece from the graph.
    if ($default_author_id !== null) {
        $data['@graph'] = array_filter($data['@graph'], function($piece) use ($default_author_id) {
            return !isset($piece['@id']) || $piece['@id'] !== $default_author_id;
        });
    }

    // Now, build the new author pieces and references.
    foreach ($authors as $author_row) {
        $person_piece = null;
        $entry_type = $author_row['type'] ?? '';

        if ($entry_type === 'Custom') {
            $custom_user = $author_row['custom_user'] ?? $author_row['custom'] ?? [];
            $full_name = trim(($custom_user['first_name'] ?? '') . ' ' . ($custom_user['last_name'] ?? ''));

            if (!empty($full_name)) {
                $person_id = home_url('/#/schema/person/' . md5(strtolower($full_name)));
                $person_piece = [
                    '@type' => 'Person',
                    '@id'   => $person_id,
                    'name'  => $full_name,
                ];
            }
        } else {
            $user_data = $author_row['user'] ?? null;
            $user_id = is_array($user_data) ? ($user_data['ID'] ?? null) : $user_data;

            if ($user_id && is_numeric($user_id)) {
                $person_id = home_url('/#/schema/person/' . get_the_author_meta('user_nicename', $user_id));
                $person_piece = [
                    '@type' => 'Person',
                    '@id'   => $person_id,
                    'name'  => get_the_author_meta('display_name', $user_id),
                    'url'   => get_author_posts_url($user_id),
                    // You could add more details here if needed, like 'image'.
                ];
            }
        }
        
        if ($person_piece) {
            // Add the full Person piece to the graph.
            $data['@graph'][] = $person_piece;
            // Add a reference to this piece for the article.
            $new_author_references[] = ['@id' => $person_piece['@id']];
        }
    }
    
    // Finally, if we have new authors and found the article, update the article's author property.
    if (!empty($new_author_references) && $article_key !== null) {
        $data['@graph'][$article_key]['author'] = $new_author_references;
    }

    // Re-index the array to prevent JSON errors.
    $data['@graph'] = array_values($data['@graph']);
    
    return $data;
}

add_filter('wpseo_schema_data', 'caes_filter_yoast_schema_data', 99, 1);