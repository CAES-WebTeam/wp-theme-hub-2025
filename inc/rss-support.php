<?php

// Disable Yoast RSS feed modifications
add_filter('wpseo_enable_rss_modification', '__return_false');
add_filter('wpseo_remove_reply_to_com', '__return_false');

// Custom RSS2 feed with ACF authors
remove_all_actions('do_feed_rss2');
function create_custom_rss2_feed() {
    load_template(get_template_directory() . '/inc/feed-rss2.php');
}
add_action('do_feed_rss2', 'create_custom_rss2_feed', 10, 1);

// Filter taxonomy feeds by post type based on URL
function filter_taxonomy_feeds_by_post_type($query) {
    if (!is_feed() || !$query->is_main_query()) {
        return;
    }
    
    // Only run on taxonomy queries
    if (!$query->is_tax()) {
        return;
    }
    
    // Get the current URL path
    $request_uri = $_SERVER['REQUEST_URI'];
    
    // Determine post type from URL
    $post_type = null;
    if (strpos($request_uri, '/publications/topic/') !== false) {
        $post_type = 'publications';
    } elseif (strpos($request_uri, '/news/topic/') !== false) {
        $post_type = 'post';
    } elseif (strpos($request_uri, '/shorthand-story/topic/') !== false) {
        $post_type = 'shorthand_story';
    } elseif (strpos($request_uri, '/events/topic/') !== false) {
        $post_type = 'events';
    }
    
    // Filter the query if we found a post type
    if ($post_type) {
        $query->set('post_type', $post_type);
        // Ensure we get the right posts
        $query->set('posts_per_page', get_option('posts_per_rss', 10));
    }
}
add_action('pre_get_posts', 'filter_taxonomy_feeds_by_post_type');

// Helper function to get ACF authors (for use in custom feed template)
function get_acf_authors_for_feed($post_id) {
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
    
    return $author_names;
}