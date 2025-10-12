<?php
/**
 * Yoast SEO & ACF Repeater Authors Integration
 *
 * This file completely replaces the default Yoast author schema with authors
 * from an ACF repeater field named 'authors'.
 *
 * @version 2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main class to manage the integration.
 */
final class CAES_ACF_Yoast_Authors {

    /**
     * The post context provided by Yoast.
     * @var object
     */
    private $context;

    /**
     * A processed list of valid authors found in the ACF field.
     * @var array
     */
    private $valid_authors = [];

    /**
     * Initializes the hooks.
     */
    public function __construct() {
        // Run our logic before other filters.
        add_action('wpseo_frontend_presenters', [$this, 'init']);
    }

    /**
     * Sets up the context and processes authors for the current page.
     * This runs once before any schema filters.
     *
     * @param array $presenters The array of Yoast presenters.
     */
    public function init($presenters) {
        // Find the post context from the presenters.
        foreach ($presenters as $presenter) {
            if (isset($presenter->context)) {
                $this->context = $presenter->context;
                break;
            }
        }

        if (!$this->context || !isset($this->context->id)) {
            return;
        }

        $this->process_authors();

        // If we found valid ACF authors, activate our schema modifications.
        if (!empty($this->valid_authors)) {
            add_filter('wpseo_schema_graph_pieces', [$this, 'remove_default_author_piece'], 11, 2);
            add_filter('wpseo_schema_graph_pieces', [$this, 'add_custom_author_pieces'], 12, 2);
            add_filter('wpseo_schema_article', [$this, 'update_article_author_references'], 11, 2);
        }
    }

    /**
     * Fetches authors from ACF and processes them into a clean, validated list.
     */
    private function process_authors() {
        $authors_raw = get_field('authors', $this->context->id);

        if (empty($authors_raw) || !is_array($authors_raw)) {
            return;
        }

        foreach ($authors_raw as $index => $author_data) {
            $author_details = $this->get_author_details($author_data);

            // Only add authors who have a valid name.
            if (!empty($author_details['name'])) {
                $author_details['@id'] = $this->context->canonical . '#/schema/person/author-' . $index;
                $this->valid_authors[] = $author_details;
            }
        }
    }

    /**
     * Takes a single row from the ACF repeater and returns a structured array of author details.
     *
     * @param array $author_data The raw data from an ACF repeater row.
     * @return array A structured array with name, URL, etc.
     */
    private function get_author_details($author_data) {
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
        } else {
            $user_id = null;
            if (!empty($author_data['user'])) {
                $user_id = is_array($author_data['user']) ? ($author_data['user']['ID'] ?? null) : $author_data['user'];
            }
            
            if ($user_id && is_numeric($user_id)) {
                $details['name']       = get_the_author_meta('display_name', $user_id);
                $details['givenName']  = get_the_author_meta('first_name', $user_id);
                $details['familyName'] = get_the_author_meta('last_name', $user_id);
                $details['url']        = get_author_posts_url($user_id);
            }
        }
        
        // If display name is not set, construct it from first/last name.
        if (empty($details['name'])) {
            $details['name'] = trim($details['givenName'] . ' ' . $details['familyName']);
        }
        
        return $details;
    }

    /**
     * STEP 1: Removes Yoast's default Author schema piece.
     */
    public function remove_default_author_piece($pieces, $context) {
        return array_filter($pieces, function ($piece) {
            return !is_a($piece, 'Yoast\WP\SEO\Generators\Schema\Author');
        });
    }

    /**
     * STEP 2: Adds our valid ACF authors as new Person schema pieces.
     */
    public function add_custom_author_pieces($pieces, $context) {
        foreach ($this->valid_authors as $author) {
            $schema_piece = [
                '@type' => 'Person',
                '@id'   => $author['@id'],
                'name'  => $author['name'],
            ];
            
            if (!empty($author['givenName']))  $schema_piece['givenName']  = $author['givenName'];
            if (!empty($author['familyName'])) $schema_piece['familyName'] = $author['familyName'];
            if (!empty($author['url']))        $schema_piece['url']        = $author['url'];
            
            $pieces[] = $schema_piece;
        }
        return $pieces;
    }

    /**
     * STEP 3: Updates the Article schema to reference our new Person pieces.
     */
    public function update_article_author_references($data, $context) {
        $author_refs = array_map(function ($author) {
            return ['@id' => $author['@id']];
        }, $this->valid_authors);

        if (count($author_refs) === 1) {
            $data['author'] = $author_refs[0];
        } else {
            $data['author'] = $author_refs;
        }
        
        return $data;
    }
}

// Instantiate the class to start the process.
new CAES_ACF_Yoast_Authors();