<?php

/**
 * Custom Permalink and Rewrite Rules
 *
 * This file contains all custom rewrite rules and permalink modifications
 * for various post types and taxonomies.
 */

// ===================================
// FIX FOR /news/ SUB-PAGE CONFLICTS
// ===================================

/**
 * Intercept requests for specific pages under /news/ before WordPress can perform a faulty redirect.
 * This is the most reliable way to handle these specific conflicts.
 */
function caes_intercept_special_news_pages()
{
    // Define the paths of the special pages that need protection.
    $special_pages = [
        'news/latest',
        'news/topics',
        'news/features',
    ];

    // Get the current request path
    $current_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

    // Check if the current request is for one of our special pages.
    if (in_array($current_path, $special_pages)) {

        // Find the page using its path (e.g., 'news/latest')
        $page = get_page_by_path($current_path);

        if ($page) {
            // If the page exists, set up the global query to correctly identify it.
            // This makes all template tags like the_title() and the_content() work correctly.
            global $wp_query, $post;
            $post = $page;
            $wp_query->is_page = true;
            $wp_query->is_singular = true;
            $wp_query->is_home = false;
            $wp_query->is_archive = false;
            $wp_query->queried_object = $post;
            $wp_query->queried_object_id = $post->ID;
            $wp_query->set('page_id', $post->ID);

            setup_postdata($post);

            // Determine which template to load (page.php or a custom template)
            $template = get_page_template();
            if (!$template) {
                $template = get_index_template();
            }

            // Load the template
            include($template);

            // Stop WordPress from doing anything else. This is critical.
            exit();
        }
    }
}
// Use a priority of 1 to run before WordPress's own redirect_canonical function (which runs at priority 10)
add_action('template_redirect', 'caes_intercept_special_news_pages', 1);


// ===================================
// REWRITE RULES SECTION
// ===================================

/**
 * Custom rewrite rules for news (post type) including categories, tags, and single posts.
 * Order matters - more specific rules should come first.
 */
function custom_news_rewrite_rules()
{
    // PRIORITY 1: Add rules for our specific pages. This helps WordPress recognize them as valid.
    add_rewrite_rule(
        '^news/latest/?$',
        'index.php?pagename=news/latest',
        'top'
    );
    add_rewrite_rule(
        '^news/topics/?$',
        'index.php?pagename=news/topics',
        'top'
    );
    add_rewrite_rule(
        '^news/features/?$',
        'index.php?pagename=news/features',
        'top'
    );

    // PRIORITY 2: Handle specific taxonomy patterns.
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

    // PRIORITY 3 (FALLBACK): Handle any other slug as a single post.
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
        '^events/caes-departments/([^/]+)/?$',
        'index.php?event_caes_departments=$matches[1]',
        'top'
    );
    add_rewrite_rule(
        '^events/caes-departments/([^/]+)/page/([0-9]+)/?$',
        'index.php?event_caes_departments=$matches[1]&paged=$matches[2]',
        'top'
    );
}
add_action('init', 'custom_events_rewrite_rules');

/**
 * Add topic rewrite rules for each post type (news, publications, shorthand-story).
 */
function custom_topic_rewrite_rules()
{
    // Topic archives for each post type
    add_rewrite_rule(
        '^publications/topic/([^/]+)/?$',
        'index.php?topics=$matches[1]&post_type=publications',
        'top'
    );
    add_rewrite_rule(
        '^news/topic/([^/]+)/?$',
        'index.php?topics=$matches[1]&post_type=post',
        'top'
    );
    add_rewrite_rule(
        '^shorthand-story/topic/([^/]+)/?$',
        'index.php?topics=$matches[1]&post_type=shorthand_story',
        'top'
    );

    // ADD THESE FEED RULES:
    add_rewrite_rule(
        '^publications/topic/([^/]+)/feed/?$',
        'index.php?topics=$matches[1]&post_type=publications&feed=rss2',
        'top'
    );
    add_rewrite_rule(
        '^news/topic/([^/]+)/feed/?$',
        'index.php?topics=$matches[1]&post_type=post&feed=rss2',
        'top'
    );
    add_rewrite_rule(
        '^shorthand-story/topic/([^/]+)/feed/?$',
        'index.php?topics=$matches[1]&post_type=shorthand_story&feed=rss2',
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
 * Custom rewrite rules for publications
 */
function custom_publications_rewrite_rules()
{
    // Publication series ARCHIVE (base URL) - must be first
    add_rewrite_rule(
        '^publications/series/?$',
        'index.php?post_type=publications&publication_series=',
        'top'
    );
    
    // Publication series rules FIRST
    add_rewrite_rule(
        '^publications/series/([^/]+)/?$',
        'index.php?publication_series=$matches[1]',
        'top'
    );
    add_rewrite_rule(
        '^publications/series/([^/]+)/page/([0-9]+)/?$',
        'index.php?publication_series=$matches[1]&paged=$matches[2]',
        'top'
    );
    
    // Publication posts rule: more specific to exclude reserved words
    add_rewrite_rule(
        '^publications/(?!series|topic)([A-Za-z]+[0-9][A-Za-z0-9-]*)/([^/]+)/?$',
        'index.php?post_type=publications&name=$matches[2]',
        'top'
    );

    // Child pages rule
    add_rewrite_rule(
        '^publications/([^/]+)/?$',
        'index.php?pagename=publications/$matches[1]',
        'top'
    );
}

function debug_rewrite_rules() {
    if (isset($_GET['debug_rewrites'])) {
        global $wp_rewrite;
        echo '<pre>';
        echo "Current rewrite rules:\n";
        print_r($wp_rewrite->wp_rewrite_rules());
        echo "\n\nCurrent request: " . $_SERVER['REQUEST_URI'] . "\n";
        echo "Parsed URL: " . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) . "\n";
        echo '</pre>';
        exit;
    }
}
add_action('init', 'debug_rewrite_rules', 999);

function debug_taxonomy_query() {
    if (strpos($_SERVER['REQUEST_URI'], '/publications/series/') === 0) {
        global $wp_query;
        echo '<pre>';
        echo "Request URI: " . $_SERVER['REQUEST_URI'] . "\n";
        echo "Query vars: " . print_r($wp_query->query_vars, true) . "\n";
        echo "Is tax: " . (is_tax() ? 'YES' : 'NO') . "\n";
        echo "Is tax publication_series: " . (is_tax('publication_series') ? 'YES' : 'NO') . "\n";
        echo "Queried object: " . print_r($wp_query->queried_object, true) . "\n";
        echo "Found posts: " . $wp_query->found_posts . "\n";
        echo '</pre>';
        
        // Check if term exists
        $term = get_term_by('slug', '2023-georgia-ag-forecast', 'publication_series');
        echo '<pre>Term exists: ' . ($term ? 'YES' : 'NO') . '</pre>';
        if ($term) {
            echo '<pre>Term: ' . print_r($term, true) . '</pre>';
        }
    }
}
add_action('wp', 'debug_taxonomy_query');

/**
 * Custom rewrite rules for person (author) pages
 */
function custom_person_rewrite_rules()
{
    // Single person page: /person/123/display-name/
    add_rewrite_rule(
        '^person/([0-9]+)/([^/]+)/?$',
        'index.php?author=$matches[1]',
        'top'
    );

    // Paginated person pages: /person/123/display-name/page/2/
    add_rewrite_rule(
        '^person/([0-9]+)/([^/]+)/page/([0-9]+)/?$',
        'index.php?author=$matches[1]&paged=$matches[3]',
        'top'
    );
}
add_action('init', 'custom_person_rewrite_rules');

// ===================================
// PERMALINK MODIFICATION SECTION
// ===================================

/**
 * Modify the permalink structure for standard 'post' type to include '/news/'.
 */
function custom_news_permalink($post_link, $post)
{
    // Check if $post is a valid object before trying to access its properties
    if (! is_a($post, 'WP_Post')) {
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
function custom_news_category_tag_links($termlink, $term, $taxonomy)
{
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
function custom_publications_permalink($post_link, $post)
{
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
function custom_topic_term_link($termlink, $term, $taxonomy)
{
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

/**
 * Modify author links to use /person/ID/display-name/ format
 */
function custom_person_author_link($link, $author_id)
{
    $user = get_userdata($author_id);
    if (!$user) {
        return $link;
    }

    // Get display name and sanitize it for URL
    $display_name = $user->display_name;
    $display_name_slug = sanitize_title($display_name);

    // If display name is empty, fall back to user_nicename
    if (empty($display_name_slug)) {
        $display_name_slug = $user->user_nicename;
    }

    return home_url("/person/{$author_id}/{$display_name_slug}/");
}
add_filter('author_link', 'custom_person_author_link', 10, 2);

// ===================================
// REDIRECTION RULES SECTION
// ===================================

/**
 * If a user visits a URL like /publications/C1234, redirect to /publications/C1234/title-slug/
 * by looking up the publication number and obtaining the canonical slug.
 */
function redirect_publications_to_canonical_url()
{
    // Do not run this redirect logic in the admin or on a publication series archive page.
    if (is_admin() || is_tax('publication_series')) {
        return;
    }

    $requested_path = untrailingslashit(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

    // Only proceed if the URL starts with '/publications/'
    if (strpos($requested_path, '/publications/') === 0) {
        // Further check to ensure it is not a series archive.
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
function redirect_news_id_to_canonical_url()
{
    // Only run on frontend
    if (is_admin()) return;

    $requested_path = untrailingslashit(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

    // Check if URL matches /news/{number}/ pattern
    if (preg_match('#^/news/(\d+)/?$#', $requested_path, $matches)) {
        $story_id = $matches[1];

        // Query to find post with matching ACF 'id' field
        $args = [
            'post_type'      => 'post',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => 'id',
                    'value' => $story_id,
                ],
            ],
        ];

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            $found_post = $query->posts[0];
            $post_slug = $found_post->post_name;
            $canonical_url = "/news/{$post_slug}/";

            // Perform 301 redirect
            wp_redirect(home_url($canonical_url), 301);
            exit;
        }
    }
}
add_action('init', 'redirect_news_id_to_canonical_url');

/**
 * Redirect root-level post URLs to include /news/ prefix
 * e.g., /some-post-slug/ → /news/some-post-slug/
 */
function redirect_root_posts_to_news()
{
    // Only run on frontend and skip previews
    if (is_admin() || isset($_GET['preview'])) return;

    $requested_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

    // Only proceed if this is a root-level request (no forward slashes)
    if (empty($requested_path) || strpos($requested_path, '/') !== false) {
        return;
    }

    // Check if there's a published post with this slug
    $args = array(
        'name' => $requested_path,
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => 1
    );

    $posts = get_posts($args);

    if (!empty($posts)) {
        // This slug matches a post, redirect to /news/ version
        $new_url = home_url("/news/{$requested_path}/");
        wp_redirect($new_url, 301);
        exit;
    }
}
add_action('template_redirect', 'redirect_root_posts_to_news');

/**
 * Redirect old /author/username/ URLs to new /person/ID/display-name/ format
 */
function redirect_author_to_person()
{
    // Only run on frontend and skip previews
    if (is_admin() || isset($_GET['preview'])) return;

    $requested_path = untrailingslashit(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

    // Check if URL matches /author/username/ or /author/username/page/X/ pattern
    if (preg_match('#^/author/([^/]+)(?:/page/([0-9]+))?/?$#', $requested_path, $matches)) {
        $username = $matches[1];
        $page_num = isset($matches[2]) ? $matches[2] : null;

        // Look up user by username/nicename
        $user = get_user_by('slug', $username);

        if ($user) {
            // Generate new URL
            $display_name_slug = sanitize_title($user->display_name);
            if (empty($display_name_slug)) {
                $display_name_slug = $user->user_nicename;
            }

            $new_url = "/person/{$user->ID}/{$display_name_slug}/";

            // Add pagination if present
            if ($page_num) {
                $new_url .= "page/{$page_num}/";
            }

            // Perform 301 redirect
            wp_redirect(home_url($new_url), 301);
            exit;
        }
    }
}
add_action('template_redirect', 'redirect_author_to_person');

// Redirect /blog/features/ URLs to /features/
function redirect_blog_features_to_features()
{
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
function redirect_news_features_to_features()
{
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
function redirect_feature_to_features()
{
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

// Redirect /features/*/index.html URLs to /features/*/ (remove index.html)
function redirect_features_remove_index_html()
{
    // Only run on frontend and skip previews
    if (is_admin() || isset($_GET['preview'])) return;

    $requested_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    // Check if URL is under /features/ and ends with index.html
    if (strpos($requested_path, '/features/') === 0 && substr($requested_path, -11) === '/index.html') {
        // Remove the /index.html part
        $new_path = substr($requested_path, 0, -10); // Remove 'index.html' but keep the trailing slash

        // Perform 301 redirect
        wp_redirect(home_url($new_path), 301);
        exit;
    }
}
add_action('template_redirect', 'redirect_features_remove_index_html');

/**
 * If a user visits a URL like /publications/topic/57/15428/, redirect to /publications/topic/actual-slug/
 * by looking up the topic term's type_id and topic_id ACF fields.
 */
function redirect_topic_ids_to_canonical_url()
{
    // Only run on frontend
    if (is_admin()) return;

    $requested_path = untrailingslashit(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

    // Check if URL matches /publications/topic/{number}/{number}/ pattern
    if (preg_match('#^/publications/topic/(\d+)/(\d+)/?$#', $requested_path, $matches)) {
        $type_id = $matches[1];
        $topic_id = $matches[2];

        // Get all topics terms
        $terms = get_terms([
            'taxonomy' => 'topics',
            'hide_empty' => false,
        ]);

        // Look for term with matching ACF fields
        foreach ($terms as $term) {
            $term_type_id = get_field('type_id', $term);
            $term_topic_id = get_field('topic_id', $term);

            if ($term_type_id == $type_id && $term_topic_id == $topic_id) {
                $canonical_url = "/publications/topic/{$term->slug}/";

                // Perform 301 redirect
                wp_redirect(home_url($canonical_url), 301);
                exit;
            }
        }
    }
}
add_action('init', 'redirect_topic_ids_to_canonical_url');


// ===================================
// EXTERNAL URL FOR NEWS POSTS
// ===================================

/**
 * Replace permalink with ACF external URL if it is set and valid for a news post.
 */
function custom_external_story_url($url, $post = null)
{
    if (! $post instanceof WP_Post) {
        $post = get_post($post);
    }

    // Only apply to 'post' post type
    if (! $post || $post->post_type !== 'post') {
        return $url;
    }

    // Use get_post_meta for better performance than get_field
    $external_url = get_post_meta($post->ID, 'external_story_url', true);

    // If the external URL exists and is a valid URL format, return it.
    if ($external_url && filter_var($external_url, FILTER_VALIDATE_URL)) {
        return esc_url($external_url);
    }

    // Otherwise, return the original URL passed to the function.
    return $url;
}
// Apply this filter *after* the custom_news_permalink filter (which has a priority of 99)
add_filter('post_link', 'custom_external_story_url', 100, 2);

// ===================================
// EXTERNAL URL FOR EVENTS
// ===================================

/**
 * Replace permalink with ACF external URL if it is set and valid for an event.
 */
function custom_external_event_url($url, $post = null)
{
    if (! $post instanceof WP_Post) {
        $post = get_post($post);
    }

    // Only apply to 'events' post type
    if (! $post || $post->post_type !== 'events') {
        return $url;
    }

    // Use get_post_meta for better performance than get_field
    $external_url = get_post_meta($post->ID, 'event_page_external_address', true);

    // If the external URL exists and is a valid URL format, return it.
    if ($external_url && filter_var($external_url, FILTER_VALIDATE_URL)) {
        return esc_url($external_url);
    }

    // Otherwise, return the original URL passed to the function.
    return $url;
}
// Apply this filter to custom post type links
add_filter('post_type_link', 'custom_external_event_url', 100, 2);