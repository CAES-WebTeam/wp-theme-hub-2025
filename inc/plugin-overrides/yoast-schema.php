<?php
/**
 * Add ACF Authors to Yoast SEO Schema Graph
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom Author Person Schema Piece
 * Follows Yoast's schema piece pattern
 */
class ACF_Author_Person {
    
    /**
     * @var object Context with canonical, post_id, etc.
     */
    public $context;
    
    /**
     * @var array Author data from ACF
     */
    private $author_data;
    
    /**
     * @var int Author index
     */
    private $index;
    
    /**
     * Constructor
     *
     * @param object $context Yoast context object
     * @param array $author_data Single author from ACF repeater
     * @param int $index Author index for unique ID
     */
    public function __construct($context, $author_data, $index) {
        $this->context = $context;
        $this->author_data = $author_data;
        $this->index = $index;
    }
    
    /**
     * Determines whether this piece should be added to the graph
     *
     * @return bool
     */
    public function is_needed() {
        return !empty($this->author_data);
    }
    
    /**
     * Generates the Person schema piece
     *
     * @return array|false Person schema or false
     */
    public function generate() {
        $canonical = YoastSEO()->meta->for_current_page()->canonical;
        
        $entry_type = $this->author_data['type'] ?? '';
        $first_name = '';
        $last_name = '';
        $display_name = '';
        $profile_url = '';
        
        if ($entry_type === 'Custom') {
            // Handle custom entry
            $custom_user = $this->author_data['custom_user'] ?? $this->author_data['custom'] ?? [];
            $first_name = sanitize_text_field($custom_user['first_name'] ?? '');
            $last_name = sanitize_text_field($custom_user['last_name'] ?? '');
            $display_name = trim("$first_name $last_name");
        } else {
            // Handle WordPress user
            $user_id = null;
            
            if (isset($this->author_data['user']) && !empty($this->author_data['user'])) {
                $user_id = is_array($this->author_data['user']) ? 
                    ($this->author_data['user']['ID'] ?? null) : 
                    $this->author_data['user'];
            }
            
            // Fallback: find numeric user ID
            if (empty($user_id) && is_array($this->author_data)) {
                foreach ($this->author_data as $value) {
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
        
        // Must have a name
        if (empty($display_name) && empty($first_name) && empty($last_name)) {
            return false;
        }
        
        $full_name = !empty($display_name) ? $display_name : trim("$first_name $last_name");
        
        // Build Person schema with required @id
        $data = [
            '@type' => 'Person',
            '@id' => $canonical . '#/schema/person/author-' . $this->index,
            'name' => $full_name,
        ];
        
        if (!empty($profile_url)) {
            $data['url'] = $profile_url;
        }
        
        if (!empty($first_name)) {
            $data['givenName'] = $first_name;
        }
        
        if (!empty($last_name)) {
            $data['familyName'] = $last_name;
        }
        
        return $data;
    }
}

/**
 * Add custom author Person pieces to the graph
 */
add_filter('wpseo_schema_graph_pieces', 'add_acf_author_pieces', 11, 2);

function add_acf_author_pieces($pieces, $context) {
    // Only run on singular posts
    if (!is_singular()) {
        return $pieces;
    }
    
    $post_id = YoastSEO()->meta->for_current_page()->post_id;
    $authors = get_field('authors', $post_id);
    
    if (empty($authors) || !is_array($authors)) {
        return $pieces;
    }
    
    // Add a Person piece for each author
    foreach ($authors as $index => $author) {
        $pieces[] = new ACF_Author_Person($context, $author, $index);
    }
    
    return $pieces;
}

/**
 * Update the Article to reference our custom author Person pieces
 */
add_filter('wpseo_schema_article', 'update_article_with_acf_authors', 10, 2);

function update_article_with_acf_authors($data, $context) {
    $post_id = YoastSEO()->meta->for_current_page()->post_id;
    $authors = get_field('authors', $post_id);
    
    if (empty($authors) || !is_array($authors)) {
        return $data;
    }
    
    $canonical = YoastSEO()->meta->for_current_page()->canonical;
    $author_refs = [];
    
    foreach ($authors as $index => $author) {
        $author_refs[] = ['@id' => $canonical . '#/schema/person/author-' . $index];
    }
    
    // Set author field
    $data['author'] = (count($author_refs) === 1) ? $author_refs[0] : $author_refs;
    
    return $data;
}

/**
 * Remove Yoast's default author Person piece when we have custom authors
 */
add_filter('wpseo_schema_graph_pieces', 'remove_default_yoast_author', 12, 2);

function remove_default_yoast_author($pieces, $context) {
    if (!is_singular()) {
        return $pieces;
    }
    
    $post_id = YoastSEO()->meta->for_current_page()->post_id;
    $authors = get_field('authors', $post_id);
    
    if (empty($authors) || !is_array($authors)) {
        return $pieces;
    }
    
    // Remove the default Author piece
    return array_filter($pieces, function($piece) {
        return !($piece instanceof Yoast\WP\SEO\Generators\Schema\Author);
    });
}