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
 * Custom rewrite rules for news (post type) including categories, tags, and single posts.
 * Order matters - more specific rules should come first.
 */
function custom_news_rewrite_rules() {
    // Category archives under /news/
    add_rewrite_rule(
        '^news/category/([^/]+)/?$',
        'index.php?category_name=$matches[1]',
        'top'
    );
    add_rewrite_rule(
        '^news/category/([^/]+)/page/([0-9]+)/?$',
        'index.php?category_name=$matches[1]&paged=$matches[2]',
        'top'
    );
    
    // Tag archives under /news/
    add_rewrite_rule(
        '^news/tag/([^/]+)/?$',
        'index.php?tag=$matches[1]',
        'top'
    );
    add_rewrite_rule(
        '^news/tag/([^/]+)/page/([0-9]+)/?$',
        'index.php?tag=$matches[1]&paged=$matches[2]',
        'top'
    );
    
    // Single news posts - now more specific to avoid conflicts
    // This should come after category/tag rules
    add_rewrite_rule(
        '^news/([^/]+)/?$',
        'index.php?post_type=post&name=$matches[1]',
        'top'
    );
}
add_action('init', 'custom_news_rewrite_rules');

// Custom rewrite rules for the event series taxonomy
function custom_events_rewrite_rules()
{
    // Rewrite rule for the event submission page
    // add_rewrite_rule(
    //     '^events/submit-an-event/?$',
    //     'index.php?pagename=events/submit-an-event',
    //     'top'
    // );

    // Single events rule (corrected)
    // add_rewrite_rule(
    //     '^events/([^/]+)/?$',
    //     'index.php?events=$matches[1]',
    //     'top'
    // );

    // Event series rules
    add_rewrite_rule(
        '^events/series/([^/]+)/?$',
        'index.php?event_series=$matches[1]',
        'top'
    );
    add_rewrite_rule(
        '^events/series/([^/]+)/page/([0-9]+)/?$',
        'index.php?event_series=$matches[1]&paged=$matches[2]',
        'top'
    );

    // CAES departments rules
    add_rewrite_rule(
        '^events/departments/([^/]+)/?$',
        'index.php?event_caes_departments=$matches[1]',
        'top'
    );
    add_rewrite_rule(
        '^events/departments/([^/]+)/page/([0-9]+)/?$',
        'index.php?event_caes_departments=$matches[1]&paged=$matches[2]',
        'top'
    );
}
add_action('init', 'custom_events_rewrite_rules');

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

    // Don't modify permalink for drafts, previews, or admin contexts
    if ($post->post_type === 'post') {
        // Skip if it's a draft or auto-draft
        if (in_array($post->post_status, ['draft', 'auto-draft', 'pending'])) {
            return $post_link;
        }
        
        // Skip if we're in admin or this is a preview
        if (is_admin() || isset($_GET['preview'])) {
            return $post_link;
        }
        
        // Skip if post_name is empty (common for drafts)
        if (empty($post->post_name)) {
            return $post_link;
        }

        $new_link = home_url('news/' . $post->post_name . '/');
        return $new_link;
    }

    return $post_link;
}
add_filter('post_link', 'custom_news_permalink', 99, 2);

/**
 * Modify category and tag links to include '/news/' prefix for post categories/tags.
 */
function custom_news_category_tag_links($termlink, $term, $taxonomy) {
    // Only modify category and post_tag taxonomies
    if (in_array($taxonomy, ['category', 'post_tag'])) {
        $prefix = ($taxonomy === 'category') ? 'news/category' : 'news/tag';
        return home_url("/{$prefix}/{$term->slug}/");
    }
    return $termlink;
}
add_filter('term_link', 'custom_news_category_tag_links', 10, 3);

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

/**
 * If a user visits a URL like /news/10345/, redirect to /news/post-slug/
 * by looking up the ACF 'id' field and obtaining the canonical slug.
 */
function redirect_news_id_to_canonical_url() {
    // Only run on frontend
    if (is_admin()) return;
    
    $requested_path = untrailingslashit(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    
    // Check if URL matches /news/{number}/ pattern
    if (preg_match('#^/news/(\d+)/?$#', $requested_path, $matches)) {
        $story_id = $matches[1];
        
        // Check cache first
        $cache_key = "news_id_redirect_{$story_id}";
        $post_slug = wp_cache_get($cache_key);
        
        if ($post_slug === false) {
            // Use get_posts() with optimized parameters and exact matching
            $posts = get_posts([
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'numberposts'    => 1,
                'fields'         => 'ids', // Only get IDs first
                'meta_query'     => [
                    [
                        'key'     => 'id',
                        'value'   => $story_id,
                        'compare' => '=',
                        'type'    => 'CHAR' // Force string comparison for exact match
                    ]
                ],
                'no_found_rows'  => true, // Skip pagination calculations
                'update_post_meta_cache' => false, // Skip meta cache
                'update_post_term_cache' => false, // Skip term cache
            ]);
            
            if ($posts) {
                $post_slug = get_post_field('post_name', $posts[0]);
            } else {
                $post_slug = 'not_found';
            }
            
            // Cache result for 1 hour
            wp_cache_set($cache_key, $post_slug, '', 3600);
        }
        
        if ($post_slug && $post_slug !== 'not_found') {
            $canonical_url = "/news/{$post_slug}/";
            
            // Perform 301 redirect
            wp_redirect(home_url($canonical_url), 301);
            exit;
        }
    }
}
add_action('template_redirect', 'redirect_news_id_to_canonical_url');


// Redirect old caes-departments URLs to new departments URLs
function redirect_old_department_urls() {
    // Only run on frontend
    if (is_admin()) return;
    
    // Check if we're on the old department taxonomy URL
    if (is_tax('event_caes_departments')) {
        $current_url = $_SERVER['REQUEST_URI'];
        
        // Check if URL contains the old format
        if (strpos($current_url, '/events/caes-departments/') !== false) {
            // Replace old path with new path
            $new_url = str_replace('/events/caes-departments/', '/events/departments/', $current_url);
            
            // Perform 301 redirect
            wp_redirect(home_url($new_url), 301);
            exit;
        }
    }
}
add_action('template_redirect', 'redirect_old_department_urls');

// Redirect /blog/features/ URLs to /features/
function redirect_blog_features_to_features() {
    // Only run on frontend
    if (is_admin()) return;
    $requested_path = untrailingslashit(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    // Check if URL starts with '/blog/features/'
    if (strpos($requested_path, '/blog/features/') === 0) {
        // Extract everything after '/blog/features/'
        $remaining_path = substr($requested_path, strlen('/blog/features'));
        
        // Build new URL with /features/ prefix
        $new_url = '/features' . $remaining_path;
        
        // Ensure trailing slash consistency
        if (substr($_SERVER['REQUEST_URI'], -1) === '/' && substr($new_url, -1) !== '/') {
            $new_url .= '/';
        }
        
        // Perform 301 redirect
        wp_redirect(home_url($new_url), 301);
        exit;
    }
}
add_action('template_redirect', 'redirect_blog_features_to_features');

// Redirect /news/features/ URLs to /features/
function redirect_news_features_to_features() {
    // Only run on frontend and skip previews
    if (is_admin() || isset($_GET['preview'])) return;
    
    $requested_path = untrailingslashit(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    
    // Check if URL starts with '/news/features/'
    if (strpos($requested_path, '/news/features/') === 0) {
        // Extract everything after '/news/features/'
        $remaining_path = substr($requested_path, strlen('/news/features'));
        
        // Build new URL with /features/ prefix
        $new_url = '/features' . $remaining_path;
        
        // Ensure trailing slash consistency
        if (substr($_SERVER['REQUEST_URI'], -1) === '/' && substr($new_url, -1) !== '/') {
            $new_url .= '/';
        }
        
        // Perform 301 redirect
        wp_redirect(home_url($new_url), 301);
        exit;
    }
}
add_action('template_redirect', 'redirect_news_features_to_features');

// Redirect /feature/ URLs to /features/ (singular to plural)
function redirect_feature_to_features() {
    // Only run on frontend and skip previews
    if (is_admin() || isset($_GET['preview'])) return;
    
    $requested_path = untrailingslashit(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    
    // Check if URL starts with '/feature/'
    if (strpos($requested_path, '/feature/') === 0) {
        // Extract everything after '/feature/'
        $remaining_path = substr($requested_path, strlen('/feature'));
        
        // Build new URL with /features/ prefix
        $new_url = '/features' . $remaining_path;
        
        // Ensure trailing slash consistency
        if (substr($_SERVER['REQUEST_URI'], -1) === '/' && substr($new_url, -1) !== '/') {
            $new_url .= '/';
        }
        
        // Perform 301 redirect
        wp_redirect(home_url($new_url), 301);
        exit;
    }
}
add_action('template_redirect', 'redirect_feature_to_features');