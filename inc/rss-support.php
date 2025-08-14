<?php

// Suppress default WordPress author in RSS (we handle all authors in ACF function)
function suppress_default_rss_author($author) {
    if (!is_feed()) {
        return $author;
    }
    
    // Always suppress default WordPress author in feeds
    // Our ACF function handles all author display including fallback
    return '';
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
add_filter('the_author', 'suppress_default_rss_author');
add_action('rss2_item', 'add_acf_authors_to_rss');
add_action('rss_item', 'add_acf_authors_to_rss'); // RSS 1.0
add_action('atom_entry', 'add_acf_authors_to_rss'); // Atom feed