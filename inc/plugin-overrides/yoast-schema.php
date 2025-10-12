<?php
/**
 * Map ACF Authors to Yoast SEO Schema (Proper Graph Implementation)
 * 
 * This code properly adds Person pieces to the schema graph and references them
 * in the Article schema using Yoast's graph-based approach
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add custom author Person pieces to the schema graph
 *
 * @param array $pieces The current graph pieces.
 * @param \Yoast\WP\SEO\Context\Meta_Tags_Context $context The context.
 * @return array Modified pieces array.
 */
add_filter('wpseo_schema_graph_pieces', 'add_custom_author_pieces', 11, 2);

function add_custom_author_pieces($pieces, $context) {
    $post = get_post($context->id);
    
    if (!$post) {
        return $pieces;
    }
    
    // Get ACF authors field
    $authors = get_field('authors', $post->ID);
    
    if (!empty($authors) && is_array($authors)) {
        foreach ($authors as $index => $item) {
            $pieces[] = new Custom_Author_Schema_Piece($context, $item, $index);
        }
    }
    
    return $pieces;
}

/**
 * Custom Author Schema Piece Class
 * Extends Yoast's Abstract_Schema_Piece to properly integrate with their graph
 */
class Custom_Author_Schema_Piece implements \Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece {
    
    /**
     * @var \Yoast\WP\SEO\Context\Meta_Tags_Context
     */
    private $context;
    
    /**
     * @var array
     */
    private $author_data;
    
    /**
     * @var int
     */
    private $index;
    
    /**
     * Constructor
     *
     * @param \Yoast\WP\SEO\Context\Meta_Tags_Context $context The context.
     * @param array $author_data The author data from ACF.
     * @param int $index The author index.
     */
    public function __construct($context, $author_data, $index) {
        $this->context = $context;
        $this->author_data = $author_data;
        $this->index = $index;
    }
    
    /**
     * Determines whether this piece should be generated
     *
     * @return bool
     */
    public function is_needed() {
        return !empty($this->author_data);
    }
    
    /**
     * Generate the Person schema for this author
     *
     * @return array|false The schema piece or false.
     */
    public function generate() {
        $person_data = $this->get_person_data();
        
        if (empty($person_data)) {
            return false;
        }
        
        return $person_data;
    }
    
    /**
     * Get the Person schema data from the author item
     *
     * @return array|null
     */
    private function get_person_data() {
        $item = $this->author_data;
        
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
        $person_id = $this->context->canonical . '#/schema/person/custom-author-' . $this->index;
        
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
}

/**
 * Modify Article schema to reference custom author Person pieces
 *
 * @param array $data The Article schema data.
 * @param \Yoast\WP\SEO\Context\Meta_Tags_Context $context The context.
 * @return array Modified schema data.
 */
add_filter('wpseo_schema_article', 'modify_article_authors', 10, 2);

function modify_article_authors($data, $context) {
    $post = get_post($context->id);
    
    if (!$post) {
        return $data;
    }
    
    // Get ACF authors field
    $authors = get_field('authors', $post->ID);
    
    if (!empty($authors) && is_array($authors)) {
        $author_refs = [];
        
        foreach ($authors as $index => $item) {
            // Reference the Person piece by @id
            $person_id = $context->canonical . '#/schema/person/custom-author-' . $index;
            $author_refs[] = ['@id' => $person_id];
        }
        
        if (!empty($author_refs)) {
            // Replace the author with references to our Person pieces
            $data['author'] = (count($author_refs) === 1) ? $author_refs[0] : $author_refs;
        }
    }
    
    return $data;
}

/**
 * Remove default WordPress author Person piece when custom authors exist
 *
 * @param array $pieces The current graph pieces.
 * @param \Yoast\WP\SEO\Context\Meta_Tags_Context $context The context.
 * @return array Modified pieces.
 */
add_filter('wpseo_schema_graph_pieces', 'remove_default_author_piece', 12, 2);

function remove_default_author_piece($pieces, $context) {
    $post = get_post($context->id);
    
    if (!$post) {
        return $pieces;
    }
    
    $authors = get_field('authors', $post->ID);
    
    // If we have custom authors, remove Yoast's default Author piece
    if (!empty($authors) && is_array($authors)) {
        $pieces = array_filter($pieces, function($piece) {
            return !($piece instanceof \Yoast\WP\SEO\Generators\Schema\Author);
        });
    }
    
    return $pieces;
}