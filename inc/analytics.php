<?php

/**
 * Analytics and Google Tag Manager Configuration
 * 
 * This file handles:
 * - Google Tag Manager initialization
 * - Custom data layer variables for GA4 tracking:
 * ---- Taxonomy and metadata tracking (topics, categories, tags, series, etc.)
 * ---- Author and expert tracking
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
    $data_layer['content_type'] = $post_type;

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

    // Get author display name
    $author_id = get_post_field('post_author', $post->ID);
    if ($author_id) {
        $author_name = get_the_author_meta('display_name', $author_id);
        $data_layer['content_authors'] = $author_name ? $author_name : '';
    } else {
        $data_layer['content_authors'] = '';
    }

    // Get expert display names from ACF repeater field (for posts, publications, shorthand_story)
    if (in_array($post_type, array('post', 'publications', 'shorthand_story'))) {
        $experts = get_field('experts', $post->ID);
        if ($experts && is_array($experts)) {
            $expert_names = array();
            foreach ($experts as $expert_row) {
                if (!empty($expert_row['user']) && is_numeric($expert_row['user'])) {
                    $expert_name = get_the_author_meta('display_name', $expert_row['user']);
                    if ($expert_name) {
                        $expert_names[] = $expert_name;
                    }
                }
            }
            if (!empty($expert_names)) {
                $data_layer['content_experts'] = implode('|', $expert_names);
            } else {
                $data_layer['content_experts'] = '';
            }
        } else {
            $data_layer['content_experts'] = '';
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