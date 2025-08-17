<?php
/*
 *  Author: UGA - CAES OIT, Frankel Agency
 *  URL: hub.caes.uga.edu
 *  Custom functions, support, custom post types and more.
 */

/*------------------------------------*\
	Load files
\*------------------------------------*/

require get_template_directory() . '/inc/theme-support.php';
require get_template_directory() . '/inc/post-types.php';
require get_template_directory() . '/inc/blocks.php';
require get_template_directory() . '/inc/acf.php';
require get_template_directory() . '/inc/caes-tools.php';
require get_template_directory() . '/inc/publications-support.php';
require get_template_directory() . '/inc/user-support.php';
require get_template_directory() . '/inc/news-support.php';
require get_template_directory() . '/block-variations/index.php';
require get_template_directory() . '/inc/custom-rewrites.php';
require get_template_directory() . '/inc/date-sync-tool.php';
require get_template_directory() . '/inc/rss-support.php';

// Publications PDF generation
require get_template_directory() . '/inc/publications-pdf/publications-pdf.php';
require get_template_directory() . '/inc/publications-pdf/pdf-queue.php';
require get_template_directory() . '/inc/publications-pdf/pdf-cron.php';
require get_template_directory() . '/inc/publications-pdf/pdf-admin.php';

// Events
require get_template_directory() . '/inc/events-support.php';
require get_template_directory() . '/inc/events/events-main.php';


// Temp include
require get_template_directory() . '/inc/release-date-migration.php';
require get_template_directory() . '/inc/release-date-clear.php';
require get_template_directory() . '/inc/detect-duplicates.php';
require get_template_directory() . '/inc/link-users.php';
require get_template_directory() . '/inc/pub-history-update.php';
require get_template_directory() . '/inc/story-association-meta-tools.php';
require get_template_directory() . '/inc/pub-main-import.php';
require get_template_directory() . '/inc/topic-term-fixer.php';
require get_template_directory() . '/inc/retired-one-time-scripts/populate-user-ids-to-stories.php';
require get_template_directory() . '/inc/status-unpublish.php';
require get_template_directory() . '/inc/event-import-tool.php';

// Plugin overrides
require get_template_directory() . '/inc/plugin-overrides/relevanssi-search.php';


// Add this to functions.php or your theme file
add_action('wp_footer', function() {
    if (current_user_can('administrator')) {
        echo '<div style="background: white; padding: 20px; margin: 20px; border: 2px solid red; position: fixed; bottom: 0; right: 0; z-index: 9999; max-width: 500px; max-height: 400px; overflow: auto;">';
        echo '<h3>Debug Info</h3>';
        echo '<pre style="font-size: 11px;">';
        
        // 1. Check if taxonomy exists and is properly configured
        $taxonomy = get_taxonomy('event_caes_departments');
        echo "=== TAXONOMY CONFIG ===\n";
        if ($taxonomy) {
            echo "Taxonomy exists: YES\n";
            echo "Public: " . ($taxonomy->public ? 'YES' : 'NO') . "\n";
            echo "Publicly queryable: " . ($taxonomy->publicly_queryable ? 'YES' : 'NO') . "\n";
            echo "Show in REST: " . ($taxonomy->show_in_rest ? 'YES' : 'NO') . "\n";
            echo "Query var: " . ($taxonomy->query_var ? 'YES' : 'NO') . "\n";
        } else {
            echo "Taxonomy exists: NO\n";
        }
        
        // 2. Check terms
        $terms = get_terms(array(
            'taxonomy' => 'event_caes_departments',
            'hide_empty' => false,
        ));
        echo "\n=== TERMS ===\n";
        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $term) {
                echo "Term: {$term->name} (ID: {$term->term_id})\n";
            }
        } else {
            echo "No terms found or error\n";
        }
        
        // 3. Check events with this taxonomy
        $events_with_terms = get_posts(array(
            'post_type' => 'events',
            'numberposts' => 5,
            'meta_query' => array(),
            'tax_query' => array()
        ));
        echo "\n=== RECENT EVENTS ===\n";
        foreach ($events_with_terms as $event) {
            $event_terms = wp_get_post_terms($event->ID, 'event_caes_departments');
            echo "Event: {$event->post_title}\n";
            if (!empty($event_terms)) {
                echo "  Terms: " . implode(', ', wp_list_pluck($event_terms, 'name')) . "\n";
            } else {
                echo "  Terms: NONE\n";
            }
        }
        
        // 4. Test query manually
        echo "\n=== MANUAL QUERY TEST ===\n";
        $test_query = new WP_Query(array(
            'post_type' => 'events',
            'posts_per_page' => 3,
            'tax_query' => array(
                array(
                    'taxonomy' => 'event_caes_departments',
                    'field'    => 'term_id',
                    'terms'    => !empty($terms) ? $terms[0]->term_id : 0,
                ),
            ),
        ));
        echo "Query for term " . (!empty($terms) ? $terms[0]->name : 'N/A') . ":\n";
        echo "Found posts: " . $test_query->found_posts . "\n";
        if ($test_query->have_posts()) {
            while ($test_query->have_posts()) {
                $test_query->the_post();
                echo "  - " . get_the_title() . "\n";
            }
        }
        wp_reset_postdata();
        
        echo '</pre>';
        echo '</div>';
    }
});

// Also add this to see what queries are actually running
add_action('pre_get_posts', function($query) {
    if (current_user_can('administrator') && !is_admin() && $query->is_main_query()) {
        error_log('Main query vars: ' . print_r($query->query_vars, true));
    }
});

// And this to see block queries specifically
add_filter('query_loop_block_query_vars', function($query_vars, $block) {
    if (current_user_can('administrator') && isset($query_vars['post_type']) && $query_vars['post_type'] === 'events') {
        error_log('Block query vars for events: ' . print_r($query_vars, true));
    }
    return $query_vars;
}, 999, 2);