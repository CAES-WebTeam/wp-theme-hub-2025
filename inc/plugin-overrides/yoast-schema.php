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
 */
class ACF_Author_Person {
    
    public $context;
    private $author_data;
    private $index;
    
    public function __construct($context, $author_data, $index) {
        $this->context = $context;
        $this->author_data = $author_data;
        $this->index = $index;
    }
    
    public function is_needed() {
        return !empty($this->author_data);
    }
    
    public function generate() {
        // Use context canonical instead of YoastSEO() surface
        $canonical = $this->context->canonical;
        
        if (empty($canonical)) {
            $canonical = get_permalink($this->context->id);
        }
        
        $entry_type = $this->author_data['type'] ?? '';
        $first_name = '';
        $last_name = '';
        $display_name = '';
        $profile_url = '';
        
        if ($entry_type === 'Custom') {
            $custom_user = $this->author_data['custom_user'] ?? $this->author_data['custom'] ?? [];
            $first_name = sanitize_text_field($custom_user['first_name'] ?? '');
            $last_name = sanitize_text_field($custom_user['last_name'] ?? '');
            $display_name = trim("$first_name $last_name");
        } else {
            $user_id = null;
            
            if (isset($this->author_data['user']) && !empty($this->author_data['user'])) {
                $user_id = is_array($this->author_data['user']) ? 
                    ($this->author_data['user']['ID'] ?? null) : 
                    $this->author_data['user'];
            }
            
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
            error_log("ACF Author {$this->index}: No name found");
            return false;
        }
        
        $full_name = !empty($display_name) ? $display_name : trim("$first_name $last_name");
        
        error_log("ACF Author {$this->index}: Generating Person for {$full_name}");
        
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

add_filter('wpseo_schema_graph_pieces', 'add_acf_author_pieces', 11, 2);

function add_acf_author_pieces($pieces, $context) {
    if (!is_singular()) {
        return $pieces;
    }
    
    $post_id = $context->id;
    $authors = get_field('authors', $post_id);
    
    if (empty($authors) || !is_array($authors)) {
        return $pieces;
    }
    
    error_log("Found " . count($authors) . " authors for post {$post_id}");
    
    foreach ($authors as $index => $author) {
        error_log("Adding author piece {$index}");
        $pieces[] = new ACF_Author_Person($context, $author, $index);
    }
    
    return $pieces;
}

add_filter('wpseo_schema_article', 'update_article_with_acf_authors', 10, 2);

function update_article_with_acf_authors($data, $context) {
    $post_id = $context->id;
    $authors = get_field('authors', $post_id);
    
    if (empty($authors) || !is_array($authors)) {
        return $data;
    }
    
    $canonical = $context->canonical;
    if (empty($canonical)) {
        $canonical = get_permalink($post_id);
    }
    
    $author_refs = [];
    
    foreach ($authors as $index => $author) {
        $author_refs[] = ['@id' => $canonical . '#/schema/person/author-' . $index];
    }
    
    error_log("Setting " . count($author_refs) . " author references on Article");
    
    $data['author'] = (count($author_refs) === 1) ? $author_refs[0] : $author_refs;
    
    return $data;
}

add_filter('wpseo_schema_graph_pieces', 'remove_default_yoast_author', 12, 2);

function remove_default_yoast_author($pieces, $context) {
    if (!is_singular()) {
        return $pieces;
    }
    
    $post_id = $context->id;
    $authors = get_field('authors', $post_id);
    
    if (empty($authors) || !is_array($authors)) {
        return $pieces;
    }
    
    return array_filter($pieces, function($piece) {
        return !($piece instanceof Yoast\WP\SEO\Generators\Schema\Author);
    });
}