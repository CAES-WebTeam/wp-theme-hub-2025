<?php

/**
 * Add ACF Authors to Yoast SEO Schema
 */

if (!defined('ABSPATH')) exit;

class ACF_Author_Person
{

    public $context;
    private $author_data;
    private $index;

    public function __construct($context, $author_data, $index)
    {
        $this->context = $context;
        $this->author_data = $author_data;
        $this->index = $index;
    }

    public function is_needed()
    {
        return true; // Always try to generate
    }

    public function generate()
    {
        $canonical = $this->context->canonical ?: get_permalink($this->context->id);
        $author = $this->author_data; // Use a simpler variable name

        $entry_type = $author['type'] ?? '';
        $user_id = null;
        $first_name = '';
        $last_name = '';
        $display_name = '';
        $profile_url = '';

        // Custom entry
        if ($entry_type === 'Custom') {
            $custom = $author['custom_user'] ?? $author['custom'] ?? [];
            $first_name = $custom['first_name'] ?? '';
            $last_name = $custom['last_name'] ?? '';
        }
        // WordPress user
        else {
            // --- START: CORRECTED USER LOOKUP LOGIC ---
            // (Copied from render.php)
            if (isset($author['user']) && !empty($author['user'])) {
                $user_id = is_array($author['user']) ? ($author['user']['ID'] ?? null) : $author['user'];
            }

            if (empty($user_id) && is_array($author)) {
                foreach ($author as $key => $value) {
                    if (is_numeric($value) && $value > 0) {
                        $user_id = $value;
                        break;
                    }
                }
            }
            // --- END: CORRECTED USER LOOKUP LOGIC ---

            if ($user_id && is_numeric($user_id) && $user_id > 0) {
                $display_name = get_the_author_meta('display_name', $user_id);
                $first_name = get_the_author_meta('first_name', $user_id);
                $last_name = get_the_author_meta('last_name', $user_id);
                $profile_url = get_author_posts_url($user_id);
            }
        }

        $name = !empty($display_name) ? $display_name : trim("$first_name $last_name");

        if (!$name) {
            return false;
        }

        $data = [
            '@type' => 'Person',
            '@id'   => $canonical . '#/schema/person/author-' . $this->index,
            'name'  => $name,
        ];

        if ($profile_url) $data['url'] = $profile_url;
        if ($first_name) $data['givenName'] = $first_name;
        if ($last_name) $data['familyName'] = $last_name;

        return $data;
    }
}

add_filter('wpseo_schema_graph_pieces', 'add_acf_author_pieces', 11, 2);
function add_acf_author_pieces($pieces, $context)
{
    $authors = get_field('authors', $context->id);
    if (!$authors || !is_array($authors)) return $pieces;

    // --- DEBUGGING LINE ---
    error_log('ACF Authors Data for Post ' . $context->id . ': ' . print_r($authors, true));

    foreach ($authors as $index => $author) {
        $pieces[] = new ACF_Author_Person($context, $author, $index);
    }

    return $pieces;
}

add_filter('wpseo_schema_article', 'update_article_authors', 10, 2);
function update_article_authors($data, $context) {
    $authors = get_field('authors', $context->id);
    if (!$authors || !is_array($authors)) {
        return $data;
    }

    $canonical = $context->canonical ?: get_permalink($context->id);
    $refs = [];

    foreach ($authors as $index => $author) {
        // --- Start: Validation Logic ---
        // This logic is now mirrored from your generate() function
        $name = '';
        $entry_type = $author['type'] ?? '';

        if ($entry_type === 'Custom') {
            $custom = $author['custom_user'] ?? $author['custom'] ?? [];
            $first_name = $custom['first_name'] ?? '';
            $last_name = $custom['last_name'] ?? '';
            $name = trim($first_name . ' ' . $last_name);
        } else {
            $user_id = null;
            if (isset($author['user'])) {
                $user_id = is_array($author['user']) ? ($author['user']['ID'] ?? null) : $author['user'];
            }

            if (empty($user_id) && is_array($author)) {
                foreach ($author as $value) {
                    if (is_numeric($value) && $value > 0) {
                        $user_id = $value;
                        break;
                    }
                }
            }
            
            if ($user_id) {
                $display_name = get_the_author_meta('display_name', $user_id);
                if ($display_name) {
                    $name = $display_name;
                } else {
                    $first_name = get_the_author_meta('first_name', $user_id);
                    $last_name = get_the_author_meta('last_name', $user_id);
                    $name = trim($first_name . ' ' . $last_name);
                }
            }
        }
        // --- End: Validation Logic ---

        // Only add the reference if a name was found.
        if (!empty($name)) {
            $refs[] = ['@id' => $canonical . '#/schema/person/author-' . $index];
        }
    }

    if (empty($refs)) {
        return $data; // Return original data if no valid authors were found
    }

    $data['author'] = count($refs) === 1 ? $refs[0] : $refs;
    return $data;
}

add_filter('wpseo_schema_graph_pieces', 'remove_default_author', 12, 2);
function remove_default_author($pieces, $context)
{
    $authors = get_field('authors', $context->id);
    if (!$authors || !is_array($authors)) return $pieces;

    return array_filter($pieces, function ($piece) {
        $class = get_class($piece);
        return strpos($class, 'Author') === false || strpos($class, 'ACF_Author') !== false;
    });
}
