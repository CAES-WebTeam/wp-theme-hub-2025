<?php
/**
 * Map ACF Authors to Yoast SEO Schema
 * 
 * This code maps the custom ACF 'authors' repeater field to proper Schema.org markup
 * Handles both WordPress users and custom entries
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add custom authors to Yoast SEO Article schema
 *
 * @param array $data The Schema Article data.
 * @param WP_Post $post The post object.
 * @return array Modified schema data.
 */
add_filter('wpseo_schema_article', 'add_custom_authors_to_schema', 10, 2);

function add_custom_authors_to_schema($data, $post) {
    // Get ACF authors field
    $authors = get_field('authors', $post->ID);
    
    // If we have custom authors, process them
    if (!empty($authors) && is_array($authors)) {
        $author_schemas = [];
        
        foreach ($authors as $item) {
            $person_data = get_person_schema_data($item);
            
            if (!empty($person_data)) {
                $author_schemas[] = $person_data;
            }
        }
        
        // If we have author schemas, replace the default author
        if (!empty($author_schemas)) {
            if (count($author_schemas) === 1) {
                // Single author - use object directly
                $data['author'] = $author_schemas[0];
            } else {
                // Multiple authors - use array
                $data['author'] = $author_schemas;
            }
        }
    }
    
    return $data;
}

/**
 * Remove default Person piece from schema graph when custom authors are present
 * This prevents the WordPress post author from appearing as a separate Person entity
 *
 * @param array $graph The Schema graph array.
 * @param WP_Post $post The post object.
 * @return array Modified graph.
 */
add_filter('wpseo_schema_graph', 'remove_default_person_for_custom_authors', 11, 2);

function remove_default_person_for_custom_authors($graph, $post) {
    // Check if this post has custom authors
    $authors = get_field('authors', $post->ID);
    
    if (!empty($authors) && is_array($authors)) {
        // Remove the default Person piece from the graph
        foreach ($graph as $key => $piece) {
            if (isset($piece['@type']) && $piece['@type'] === 'Person') {
                // Check if this is the default author (not one of our custom authors)
                // The default person usually has an @id like #/schema/person/...
                if (isset($piece['@id']) && strpos($piece['@id'], '#/schema/person/') !== false) {
                    unset($graph[$key]);
                }
            }
        }
        
        // Re-index the array to maintain clean JSON output
        $graph = array_values($graph);
    }
    
    return $graph;
}

/**
 * Generate Schema.org Person data from ACF author item
 *
 * @param array $item Single author item from ACF repeater.
 * @return array|null Schema Person data or null if invalid.
 */
function get_person_schema_data($item) {
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
        
        // Build full name for custom entries
        $display_name = trim("$first_name $last_name");
        
    } else {
        // Handle WordPress user selection
        $user_id = null;
        
        // Check for 'user' key
        if (isset($item['user']) && !empty($item['user'])) {
            $user_id = is_array($item['user']) ? ($item['user']['ID'] ?? null) : $item['user'];
        }
        
        // Fallback: check for numeric values
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
    
    // Use display_name if available, otherwise construct from first/last
    $full_name = !empty($display_name) ? $display_name : trim("$first_name $last_name");
    
    // Build the Person schema
    $person_schema = [
        '@type' => 'Person',
        'name' => $full_name,
    ];
    
    // Add URL if available (for WordPress users)
    if (!empty($profile_url)) {
        $person_schema['url'] = $profile_url;
    }
    
    // Add given/family name if available
    if (!empty($first_name)) {
        $person_schema['givenName'] = $first_name;
    }
    if (!empty($last_name)) {
        $person_schema['familyName'] = $last_name;
    }
    
    return $person_schema;
}