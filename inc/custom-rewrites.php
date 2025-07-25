<?php
/**
 * Custom Permalink and Rewrite Rules
 *
 * This file contains all custom rewrite rules and permalink modifications
 * for various post types and taxonomies.
 */

// ===================================
// REWRITE RULES SECTION
// ===================================

/**
 * Custom rewrite rule for 'post' post type (referred to as 'news').
 * This ensures URLs like /news/slug-of-post/ are correctly routed.
 */
function custom_news_single_rewrite_rule() {
    add_rewrite_rule(
        '^news/([^/]+)/?$',
        'index.php?post_type=post&name=$matches[1]',
        'top'
    );
}
add_action('init', 'custom_news_single_rewrite_rule');

/**
 * Custom rewrite rule for 'events' post type.
 * This ensures URLs like /events/slug-of-event/ are correctly routed.
 * Although user stated it's working, this adds explicit rule.
 */
function custom_events_single_rewrite_rule() {
    add_rewrite_rule(
        '^events/([^/]+)/?$',
        'index.php?post_type=events&name=$matches[1]',
        'top'
    );
}
add_action('init', 'custom_events_single_rewrite_rule');

/**
 * Add topic rewrite rules for each post type (news, publications, shorthand-story).
 */
function custom_topic_rewrite_rules() {
    // Topic archives for each post type
    add_rewrite_rule(
        '^news/topic/([^/]+)/?$',
        'index.php?topics=$matches[1]&post_type=post', // 'post' for news
        'top'
    );
    add_rewrite_rule(
        '^publications/topic/([^/]+)/?$',
        'index.php?topics=$matches[1]&post_type=publications',
        'top'
    );
    add_rewrite_rule(
        '^shorthand-story/topic/([^/]+)/?$',
        'index.php?topics=$matches[1]&post_type=shorthand_story',
        'top'
    );

    // Add pagination support for topic archives
    add_rewrite_rule(
        '^(news|publications|shorthand-story)/topic/([^/]+)/page/([0-9]+)/?$',
        'index.php?topics=$matches[2]&post_type=$matches[1]&paged=$matches[3]',
        'top'
    );
}
add_action('init', 'custom_topic_rewrite_rules');

/**
 * Custom rewrite rules for publications, including publication series and child pages.
 */
function custom_publications_rewrite_rules() {
    // Publication posts rule: e.g. /publications/C1037-23-SP/some-publication/
    add_rewrite_rule(
        '^publications/([A-Za-z]+\d+(?:-[A-Za-z0-9]+)*)/([^/]+)/?$',
        'index.php?post_type=publications&name=$matches[2]',
        'top'
    );

    // Rule to specifically handle the publication series taxonomy URLs
    add_rewrite_rule(
        '^publications/series/([^/]+)/?$',
        'index.php?publication_series=$matches[1]',
        'top'
    );

    // Rule for pagination in taxonomy archives
    add_rewrite_rule(
        '^publications/series/([^/]+)/page/([0-9]+)/?$',
        'index.php?publication_series=$matches[1]&paged=$matches[2]',
        'top'
    );

    // Child pages rule - this handles regular child pages under "publications"
    // This should be last so it doesn't catch /publications/series/
    add_rewrite_rule(
        '^publications/([^/]+)/?$',
        'index.php?pagename=publications/$matches[1]',
        'top'
    );
}
add_action('init', 'custom_publications_rewrite_rules');

// ===================================
// PERMALINK MODIFICATION SECTION
// ===================================

/**
 * Modify the permalink structure for standard 'post' type to include '/news/'.
 */
function custom_news_permalink($post_link, $post) {
    // Check if $post is a valid object before trying to access its properties
    if ( ! is_a( $post, 'WP_Post' ) ) {
        return $post_link;
    }

    if ('post' === $post->post_type) {
        $new_link = home_url('news/' . $post->post_name . '/');
        return $new_link;
    }

    return $post_link;
}
add_filter('post_link', 'custom_news_permalink', 99, 2);

/**
 * Modify the permalink structure for 'events' post type to include '/events/'.
 */
function custom_events_permalink($post_link, $post) {
    if ('events' === $post->post_type) {
        return home_url('events/' . $post->post_name . '/');
    }
    return $post_link;
}
add_filter('post_type_link', 'custom_events_permalink', 10, 2);

/**
 * Modify the permalink structure for publication posts so that the URL includes the publication number.
 * For example, it changes /publications/post-slug/ to /publications/C1248/post-slug/.
 */
function custom_publications_permalink($post_link, $post) {
    if ($post->post_type === 'publications') {
        $publication_number = get_field('publication_number', $post->ID);
        if ($publication_number) {
            $publication_number = str_replace(' ', '', $publication_number);
            return home_url("/publications/{$publication_number}/{$post->post_name}/");
        }
    }
    return $post_link;
}
add_filter('post_type_link', 'custom_publications_permalink', 10, 2);

/**
 * Modify topic links to be post-type specific (e.g., /news/topic/sports/).
 */
function custom_topic_term_link($termlink, $term, $taxonomy) {
    if ($taxonomy !== 'topics') {
        return $termlink;
    }

    global $wp_query;
    $current_post_type = null;

    // Infer post type from current URL path first
    $request_uri = $_SERVER['REQUEST_URI'];

    if (strpos($request_uri, '/publications/') === 0) {
        $current_post_type = 'publications';
    } elseif (strpos($request_uri, '/news/') === 0) {
        $current_post_type = 'post'; // 'post' is the internal post type for 'news'
    } elseif (strpos($request_uri, '/shorthand-story/') === 0) {
        $current_post_type = 'shorthand_story';
    } elseif (strpos($request_uri, '/events/') === 0) { // Added for events topic archive
        $current_post_type = 'events';
    }

    // Fall back to existing WordPress query checks if post type not determined by URL
    if ($current_post_type === null) {
        if (is_singular()) {
            $current_post_type = get_post_type();
        } elseif (is_post_type_archive()) {
            $current_post_type = get_query_var('post_type');
        } elseif (is_home() || is_category() || is_tag()) {
            $current_post_type = 'post';
        }
    }

    // Map post types to URL prefixes
    $url_prefixes = [
        'post'             => 'news',
        'events'           => 'events', // Added for events
        'publications'     => 'publications',
        'shorthand_story'  => 'shorthand-story'
    ];

    if ($current_post_type && isset($url_prefixes[$current_post_type])) {
        return home_url("/{$url_prefixes[$current_post_type]}/topic/{$term->slug}/");
    }

    return $termlink;
}
add_filter('term_link', 'custom_topic_term_link', 10, 3);

// ===================================
// REDIRECTION RULES SECTION (New Section)
// ===================================

/**
 * If a user visits a URL like /publications/C1234, redirect to /publications/C1234/title-slug/
 * by looking up the publication number and obtaining the canonical slug.
 */
function redirect_publications_to_canonical_url()
{
    $requested_path = untrailingslashit(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

    // Only proceed if the URL starts with '/publications/'
    if (strpos($requested_path, '/publications/') === 0) {
        if (preg_match('#^/publications/([A-Za-z0-9-]+)#', $requested_path, $matches)) {
            $publication_number = $matches[1];
            // Add a space before the first digit so the stored value (e.g. "C 1037-23-SP") can match.
            $publication_number_with_space = preg_replace('/([A-Za-z]+)(\d)/', '$1 $2', $publication_number);

            // Query to confirm the publication exists.
            $args = [
                'post_type'      => 'publications',
                'posts_per_page' => 1,
                'meta_query'     => [
                    [
                        'key'   => 'publication_number',
                        'value' => $publication_number_with_space,
                    ],
                ],
            ];
            $query = new WP_Query($args);
            if ($query->have_posts()) {
                $post_slug = $query->posts[0]->post_name;
                $canonical_url = "/publications/{$publication_number}/{$post_slug}/";
                $normalized_requested_path = rtrim($requested_path, '/');
                $normalized_canonical_url = rtrim($canonical_url, '/');

                // If the URL consists only of the publication number (with no slug), then redirect.
                if ($normalized_requested_path === "/publications/{$publication_number}") {
                    wp_redirect(home_url($normalized_canonical_url), 301);
                    exit;
                }
            }
        }
    }
}
add_action('template_redirect', 'redirect_publications_to_canonical_url');