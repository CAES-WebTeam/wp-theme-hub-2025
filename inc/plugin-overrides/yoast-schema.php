<?php
/**
 * Plugin Name: Custom Yoast Schema for Authors
 * Description: Replaces the default Yoast author schema with authors from an ACF repeater field.
 * Version: 2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Filters the entire Yoast SEO schema graph to replace the default author
 * with a list of authors from an ACF repeater field.
 *
 * This function hooks into the final schema array, giving us complete control to:
 * 1. Find the Article and its default author.
 * 2. Remove the default author's 'Person' object from the graph.
 * 3. Build and add new 'Person' objects for each custom author.
 * 4. Update the Article to reference the new custom authors.
 *
 * @param array $data The entire schema graph data array.
 * @return array The modified schema graph data.
 */
function caes_replace_yoast_author_schema($data) {
    
    // Proceed only if we're on a singular page and the graph exists.
    if (!is_singular() || !isset($data['@graph'])) {
        return $data;
    }

    $authors = get_field('authors');

    // If there are no ACF authors for this post, do nothing.
    if (empty($authors)) {
        return $data;
    }

    $article_key = null;
    $default_author_id = null;

    // --- Step 1: Find the Article piece and get the ID of its default author. ---
    foreach ($data['@graph'] as $key => $piece) {
        if (isset($piece['@type']) && str_contains($piece['@type'], 'Article')) {
            $article_key = $key;
            if (isset($piece['author']['@id'])) {
                $default_author_id = $piece['author']['@id'];
            }
            break; // Stop once we've found the article.
        }
    }
    
    // If we couldn't find an article, there's nothing to do.
    if ($article_key === null) {
        return $data;
    }

    // --- Step 2: Remove the default author's 'Person' object from the graph. ---
    if ($default_author_id !== null) {
        $data['@graph'] = array_filter($data['@graph'], function($piece) use ($default_author_id) {
            // Keep all pieces EXCEPT the one whose @id matches the default author's ID.
            return !isset($piece['@id']) || $piece['@id'] !== $default_author_id;
        });
    }

    $new_author_references = [];

    // --- Step 3: Build and add new 'Person' objects for each custom author. ---
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
                // Generate a stable, unique ID based on the user's nicename.
                $person_id = home_url('/#/schema/person/' . get_the_author_meta('user_nicename', $user_id));
                $person_piece = [
                    '@type' => 'Person',
                    '@id'   => $person_id,
                    'name'  => get_the_author_meta('display_name', $user_id),
                    'url'   => get_author_posts_url($user_id),
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
    
    // --- Step 4: Update the Article to reference the new custom authors. ---
    if (!empty($new_author_references)) {
        $data['@graph'][$article_key]['author'] = $new_author_references;
    }
    
    // Re-index the array to prevent JSON errors from removed pieces.
    $data['@graph'] = array_values($data['@graph']);
    
    return $data;
}

add_filter('wpseo_schema_data', 'caes_replace_yoast_author_schema', 99, 1);

add_filter( 'yoast_seo_development_mode', '__return_true' );

