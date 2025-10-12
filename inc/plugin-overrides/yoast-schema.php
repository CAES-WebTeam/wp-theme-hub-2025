<?php
/**
 * Map ACF Authors to Yoast SEO Schema
 * 
 * This adds custom Person pieces to the graph and references them in the Article
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Modify the entire schema graph to add custom author Person pieces
 *
 * @param array $graph The schema graph.
 * @param \Yoast\WP\SEO\Context\Meta_Tags_Context $context The context.
 * @return array Modified graph.
 */
add_filter('wpseo_schema_graph', 'add_custom_authors_to_graph', 11, 2);

function add_custom_authors_to_graph($graph, $context) {
    $post = get_post($context->id);
    
    if (!$post) {
        return $graph;
    }
    
    // Get ACF authors field
    $authors = get_field('authors', $post->ID);
    
    if (empty($authors) || !is_array($authors)) {
        return $graph;
    }
    
    // Build author Person pieces
    $author_person_pieces = [];
    $author_ids = [];
    
    foreach ($authors as $index => $item) {
        $person_data = get_custom_author_person_data($item, $index, $context->canonical);
        
        if ($person_data) {
            $author_person_pieces[] = $person_data;
            $author_ids[] = ['@id' => $person_data['@id']];
        }
    }
    
    if (empty($author_person_pieces)) {
        return $graph;
    }
    
    // Add Person pieces to the graph
    foreach ($author_person_pieces as $person) {
        $graph[] = $person;
    }
    
    // Update the Article/NewsArticle to reference our custom authors
    foreach ($graph as &$piece) {
        if (isset($piece['@type']) && 
            (in_array($piece['@type'], ['Article', 'NewsArticle', 'BlogPosting']) || 
             (is_array($piece['@type']) && array_intersect($piece['@type'], ['Article', 'NewsArticle', 'BlogPosting'])))) {
            
            // Replace author with our custom author references
            $piece['author'] = (count($author_ids) === 1) ? $author_ids[0] : $author_ids;
        }
        
        // Remove the default caeswp Person piece
        if (isset($piece['@type']) && $piece['@type'] === 'Person' && 
            isset($piece['@id']) && strpos($piece['@id'], '#/schema/person/') !== false &&
            strpos($piece['@id'], 'custom-author') === false) {
            
            // Mark for removal by unsetting the @type
            unset($piece['@type']);
        }
    }
    
    // Filter out any pieces we marked for removal
    $graph = array_filter($graph, function($piece) {
        return isset($piece['@type']);
    });
    
    // Re-index array
    return array_values($graph);
}

/**
 * Generate Person schema data from ACF author item
 *
 * @param array $item Author item from ACF.
 * @param int $index Author index.
 * @param string $canonical Canonical URL.
 * @return array|null Person schema or null.
 */
function get_custom_author_person_data($item, $index, $canonical) {
    if (empty($item)) {
        return null;
    }
    
    $entry_type = $item['type'] ?? '';
    $first_name = '';
    $last_name = '';
    $display_name = '';
    $profile_url = '';
    
    if ($entry_type === 'Custom') {
        // Handle custom user entry
        $custom_user = $item['custom_user'] ?? $item['custom'] ?? [];
        $first_name = sanitize_text_field($custom_user['first_name'] ?? '');
        $last_name = sanitize_text_field($custom_user['last_name'] ?? '');
        $display_name = trim("$first_name $last_name");
        
    } else {
        // Handle WordPress user selection
        $user_id = null;
        
        if (isset($item['user']) && !empty($item['user'])) {
            $user_id = is_array($item['user']) ? ($item['user']['ID'] ?? null) : $item['user'];
        }
        
        if (empty($user_id) && is_array($item)) {
            foreach ($item as $key => $value) {
                if (is_numeric($value) && $value > 0) {
                    $user_id = $value;
                    break;
                }
            }
        }
        
        if ($user_id && is_numeric($user_id) && $user_id > 0) {
            $display_name = get_the_author_meta('display_name', $user_id);
            $first_name = get_the_author_meta('first_name', $user_id);
            $last_name = get_the_author_meta('last_name', $user_id);
            $profile_url = get_author_posts_url($user_id);
        }
    }
    
    // Only create schema if we have a name
    if (empty($display_name) && (empty($first_name) && empty($last_name))) {
        return null;
    }
    
    $full_name = !empty($display_name) ? $display_name : trim("$first_name $last_name");
    
    // Create unique @id for this person
    $person_id = $canonical . '#/schema/person/custom-author-' . $index;
    
    // Build the Person schema
    $person_schema = [
        '@type' => 'Person',
        '@id' => $person_id,
        'name' => $full_name,
    ];
    
    if (!empty($profile_url)) {
        $person_schema['url'] = $profile_url;
    }
    
    if (!empty($first_name)) {
        $person_schema['givenName'] = $first_name;
    }
    
    if (!empty($last_name)) {
        $person_schema['familyName'] = $last_name;
    }
    
    return $person_schema;
}