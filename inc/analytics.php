<?php
/**
 * Analytics and Google Tag Manager Configuration
 * 
 * This file handles:
 * - Google Tag Manager initialization
 * - Custom data layer variables for GA4 tracking
 * - Taxonomy and metadata tracking (topics, categories, tags, series, etc.)
 * - Author and expert tracking
 */

// Add Google Tag Manager code to the head (only for non-logged-in users and non-local domains)
function add_gtm_head_block_theme()
{
    // Get current domain
    $current_domain = $_SERVER['HTTP_HOST'];

    // Only load GTM if user is NOT logged in AND domain doesn't contain ".local"
    if (!is_user_logged_in() && strpos($current_domain, '.local') === false) {
?>
        <!-- Google Tag Manager -->
        <script>
            (function(w, d, s, l, i) {
                w[l] = w[l] || [];
                w[l].push({
                    'gtm.start': new Date().getTime(),
                    event: 'gtm.js'
                });
                var f = d.getElementsByTagName(s)[0],
                    j = d.createElement(s),
                    dl = l != 'dataLayer' ? '&l=' + l : '';
                j.async = true;
                j.src =
                    'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
                f.parentNode.insertBefore(j, f);
            })(window, document, 'script', 'dataLayer', 'GTM-MTZTHHB7');
        </script>
        <!-- End Google Tag Manager -->
    <?php
    }
}
add_action('wp_head', 'add_gtm_head_block_theme', 0); // Priority 0 = very top of <head>

// Add Google Tag Manager code to the body (only for non-logged-in users and non-local domains)
function add_gtm_noscript_block_theme()
{
    // Get current domain
    $current_domain = $_SERVER['HTTP_HOST'];

    // Only load GTM if user is NOT logged in AND domain doesn't contain ".local"
    if (!is_user_logged_in() && strpos($current_domain, '.local') === false) {
    ?>
        <!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-MTZTHHB7"
                height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->
    <?php
    }
}
add_action('wp_body_open', 'add_gtm_noscript_block_theme');

/**
 * Push custom data layer variables for GA4 tracking
 * 
 * This function collects taxonomy terms, author data, and expert data
 * and pushes them to the dataLayer for use in GA4/GLS reports.
 */
function push_custom_data_layer()
{
    // Get current domain
    $current_domain = $_SERVER['HTTP_HOST'];

    // Only load for non-logged-in users and non-local domains (same conditions as GTM)
    if (is_user_logged_in() || strpos($current_domain, '.local') !== false) {
        return;
    }

    // Only run on singular post views
    if (!is_singular()) {
        return;
    }

    global $post;
    if (!$post) {
        return;
    }

    $post_type = get_post_type($post->ID);
    $data_layer = array();

    // Always include post type
    $data_layer['post_type'] = $post_type;

    // Helper function to get term slugs as pipe-delimited string
    $get_term_slugs = function ($taxonomy, $post_id) {
        $terms = get_the_terms($post_id, $taxonomy);
        if ($terms && !is_wp_error($terms)) {
            return implode('|', wp_list_pluck($terms, 'slug'));
        }
        return '';
    };

    // Collect taxonomy data based on post type
    switch ($post_type) {
        case 'post':
            // Topics
            $data_layer['content_topics'] = $get_term_slugs('topics', $post->ID);
            
            // Primary Topics (ACF field)
            $primary_topics = get_field('primary_topics', $post->ID);
            if ($primary_topics && is_array($primary_topics)) {
                $data_layer['content_primary_topics'] = implode('|', wp_list_pluck($primary_topics, 'slug'));
            } else {
                $data_layer['content_primary_topics'] = '';
            }
            
            // Categories
            $data_layer['content_categories'] = $get_term_slugs('category', $post->ID);
            
            // Tags
            $data_layer['content_tags'] = $get_term_slugs('post_tag', $post->ID);
            break;

        case 'publications':
            // Topics
            $data_layer['content_topics'] = $get_term_slugs('topics', $post->ID);
            
            // Primary Topics (ACF field)
            $primary_topics = get_field('primary_topics', $post->ID);
            if ($primary_topics && is_array($primary_topics)) {
                $data_layer['content_primary_topics'] = implode('|', wp_list_pluck($primary_topics, 'slug'));
            } else {
                $data_layer['content_primary_topics'] = '';
            }
            
            // Publication Series
            $data_layer['content_publication_series'] = $get_term_slugs('publication_series', $post->ID);
            
            // Publication Categories
            $data_layer['content_publication_categories'] = $get_term_slugs('publication_category', $post->ID);
            
            // Tags
            $data_layer['content_tags'] = $get_term_slugs('post_tag', $post->ID);
            break;

        case 'shorthand_story':
            // Topics
            $data_layer['content_topics'] = $get_term_slugs('topics', $post->ID);
            
            // Primary Topics (ACF field)
            $primary_topics = get_field('primary_topics', $post->ID);
            if ($primary_topics && is_array($primary_topics)) {
                $data_layer['content_primary_topics'] = implode('|', wp_list_pluck($primary_topics, 'slug'));
            } else {
                $data_layer['content_primary_topics'] = '';
            }
            break;

        case 'events':
            // Event Series
            $data_layer['content_event_series'] = $get_term_slugs('event_series', $post->ID);
            
            // Event CAES Departments
            $data_layer['content_event_departments'] = $get_term_slugs('event_caes_departments', $post->ID);
            break;
    }

    /**
     * Helper function to extract names from ACF repeater fields (authors, experts, artists)
     * Handles both User selection and Custom name entries
     */
    $extract_names_from_repeater = function($field_name, $post_id) {
        $repeater_data = get_field($field_name, $post_id);
        $names = array();
        
        if ($repeater_data && is_array($repeater_data)) {
            foreach ($repeater_data as $item) {
                $entry_type = $item['type'] ?? '';
                $full_name = '';
                
                if ($entry_type === 'Custom') {
                    // Handle custom name entry
                    $custom_user = $item['custom_user'] ?? $item['custom'] ?? array();
                    $first_name = sanitize_text_field($custom_user['first_name'] ?? '');
                    $last_name = sanitize_text_field($custom_user['last_name'] ?? '');
                    
                    if (!empty($first_name) || !empty($last_name)) {
                        $full_name = trim("$first_name $last_name");
                    }
                } else {
                    // Handle WordPress user selection
                    $user_id = null;
                    if (isset($item['user']) && !empty($item['user'])) {
                        $user_id = is_array($item['user']) ? ($item['user']['ID'] ?? null) : $item['user'];
                    }
                    
                    // Fallback: search for any numeric value in the item
                    if (empty($user_id) && is_array($item)) {
                        foreach ($item as $key => $value) {
                            if (is_numeric($value) && $value > 0) {
                                $user_id = $value;
                                break;
                            }
                        }
                    }
                    
                    if ($user_id && is_numeric($user_id)) {
                        $display_name = get_the_author_meta('display_name', $user_id);
                        if (!empty($display_name)) {
                            $full_name = $display_name;
                        } else {
                            // Fallback to constructing from first/last name
                            $first_name = get_the_author_meta('first_name', $user_id);
                            $last_name = get_the_author_meta('last_name', $user_id);
                            $full_name = trim("$first_name $last_name");
                        }
                    }
                }
                
                if (!empty($full_name)) {
                    $names[] = $full_name;
                }
            }
        }
        
        return !empty($names) ? implode('|', $names) : '';
    };

    // Get author names based on post type
    if ($post_type === 'publications') {
        // Publications: authors, artists, and translators
        $data_layer['content_authors'] = $extract_names_from_repeater('authors', $post->ID);
        $data_layer['content_artists'] = $extract_names_from_repeater('artists', $post->ID);
        $data_layer['content_translators'] = $extract_names_from_repeater('translator', $post->ID);
    } elseif ($post_type === 'post') {
        // Posts (news): authors, experts, and artists
        $data_layer['content_authors'] = $extract_names_from_repeater('authors', $post->ID);
        $data_layer['content_experts'] = $extract_names_from_repeater('experts', $post->ID);
        $data_layer['content_artists'] = $extract_names_from_repeater('artists', $post->ID);
    } elseif ($post_type === 'shorthand_story') {
        // Shorthand: authors and artists
        $data_layer['content_authors'] = $extract_names_from_repeater('authors', $post->ID);
        $data_layer['content_artists'] = $extract_names_from_repeater('artists', $post->ID);
    } elseif ($post_type === 'events') {
        // Events: use the WordPress post_author as fallback
        $author_id = get_post_field('post_author', $post->ID);
        if ($author_id) {
            $author_name = get_the_author_meta('display_name', $author_id);
            $data_layer['content_authors'] = $author_name ? $author_name : '';
        } else {
            $data_layer['content_authors'] = '';
        }
    }

    // Output the data layer push
    ?>
    <script>
        window.dataLayer = window.dataLayer || [];
        dataLayer.push(<?php echo json_encode($data_layer, JSON_UNESCAPED_SLASHES); ?>);
    </script>
    <?php
}
add_action('wp_head', 'push_custom_data_layer', 5); // Priority 5 = after GTM init (priority 0) but before GTM loads