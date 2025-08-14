<?php

// Completely remove default WordPress author from RSS feeds
function remove_default_rss_author() {
    if (is_feed()) {
        // Filter author functions to return empty
        add_filter('the_author', '__return_empty_string');
        add_filter('get_the_author', '__return_empty_string');
    }
}

// Clean up RSS output to remove empty dc:creator tags
function clean_rss_output() {
    if (is_feed()) {
        ob_start();
    }
}

function finish_rss_cleanup() {
    if (is_feed() && ob_get_level()) {
        $content = ob_get_clean();
        // Remove empty dc:creator tags
        $content = preg_replace('/<dc:creator><!\[CDATA\[\s*\]\]><\/dc:creator>\s*\n?/', '', $content);
        echo $content;
    }
}

// Add ACF authors as dc:creator in RSS feed
function add_acf_authors_to_rss() {
    global $post;
    
    $post_id = $post->ID;
    $authors = get_field('authors', $post_id, false);
    
    $author_names = [];
    
    if ($authors && is_array($authors)) {
        foreach ($authors as $item) {
            $user_id = null;
            $custom_first = '';
            $custom_last = '';
            
            // Check for user selection first
            if (isset($item['user']) && !empty($item['user'])) {
                $user_id = is_array($item['user']) ? ($item['user']['ID'] ?? null) : $item['user'];
            }
            
            // Check for custom group with nested first_name and last_name
            if (isset($item['custom']) && is_array($item['custom'])) {
                $custom_first = $item['custom']['first_name'] ?? '';
                $custom_last = $item['custom']['last_name'] ?? '';
            }
            
            // Fallback: check for numeric values (ACF internal field keys)
            if (empty($user_id) && empty($custom_first) && empty($custom_last) && is_array($item)) {
                foreach ($item as $key => $value) {
                    if (is_numeric($value) && $value > 0) {
                        $user_id = $value;
                        break;
                    }
                }
            }
            
            // Get author name
            if ($user_id && is_numeric($user_id)) {
                $first_name = get_the_author_meta('first_name', $user_id);
                $last_name = get_the_author_meta('last_name', $user_id);
                $author_names[] = trim("$first_name $last_name");
            } elseif (!empty($custom_first) || !empty($custom_last)) {
                $author_names[] = trim("$custom_first $custom_last");
            }
        }
    }
    
    // If no ACF authors found, use fallback
    if (empty($author_names)) {
        $author_names[] = 'The Office of Marketing and Communications';
    }
    
    // Output dc:creator tags
    foreach ($author_names as $name) {
        if (!empty($name)) {
            echo '<dc:creator><![CDATA[' . esc_html($name) . ']]></dc:creator>' . "\n";
        }
    }
}

// Hook into RSS feeds for ACF authors
add_action('template_redirect', 'remove_default_rss_author');
add_action('template_redirect', 'clean_rss_output', 1);
add_action('shutdown', 'finish_rss_cleanup', 999);
add_action('rss2_item', 'add_acf_authors_to_rss', 20); // Run later to override defaults
add_action('rss_item', 'add_acf_authors_to_rss', 20); // RSS 1.0
add_action('atom_entry', 'add_acf_authors_to_rss', 20); // Atom feed