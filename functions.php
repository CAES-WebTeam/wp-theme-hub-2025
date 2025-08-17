<?php
/*
 *  Author: UGA - CAES OIT, Frankel Agency
 *  URL: hub.caes.uga.edu
 *  Custom functions, support, custom post types and more.
 */

/*------------------------------------*\
	Load files
\*------------------------------------*/

// TEMPORARY: Disable all query modifications to test
add_action('init', function() {
    if (!is_admin() && current_user_can('administrator')) {
        // Remove all query-related filters temporarily
        remove_all_filters('pre_get_posts');
        remove_all_filters('query_loop_block_query_vars');
        remove_all_filters('posts_pre_query');
        remove_all_filters('the_posts');
    }
}, 999);


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

// Add this to see exactly what the Query Loop block is doing
add_filter('query_loop_block_query_vars', function($query_vars, $block) {
    if (current_user_can('administrator') && isset($query_vars['post_type']) && $query_vars['post_type'] === 'events') {
        echo '<div style="background: yellow; padding: 10px; margin: 10px; border: 1px solid black;">';
        echo '<h4>Query Loop Block Query Vars:</h4>';
        echo '<pre>' . print_r($query_vars, true) . '</pre>';
        echo '</div>';
    }
    return $query_vars;
}, 999, 2);

// Test with EXACT same parameters as the block
add_action('wp_footer', function() {
    if (current_user_can('administrator')) {
        echo '<div style="background: orange; padding: 20px; margin: 20px; border: 2px solid black; position: fixed; top: 0; left: 0; z-index: 9999; max-width: 400px;">';
        echo '<h3>Exact Block Query Test</h3>';
        
        // Use EXACTLY the same parameters as the block
        $test_query = new WP_Query(array(
            'post_type' => 'events',
            'order' => 'DESC',
            'orderby' => 'date',
            'post__not_in' => array(),
            'tax_query' => array(
                array(
                    'taxonomy' => 'event_caes_departments',
                    'terms' => array(1514),
                    'include_children' => '' // Empty string like the block
                )
            ),
            'offset' => 0,
            'posts_per_page' => 9,
            'author__in' => array()
        ));
        
        echo '<p>Found posts: ' . $test_query->found_posts . '</p>';
        echo '<p>Query SQL: ' . $test_query->request . '</p>';
        echo '<p>Posts:</p><ul>';
        if ($test_query->have_posts()) {
            while ($test_query->have_posts()) {
                $test_query->the_post();
                echo '<li>' . get_the_title() . '</li>';
            }
        } else {
            echo '<li>No posts found</li>';
        }
        echo '</ul>';
        wp_reset_postdata();
        
        echo '</div>';
    }
});

// Add this to check the actual database relationships
add_action('wp_footer', function() {
    if (current_user_can('administrator')) {
        echo '<div style="background: pink; padding: 20px; margin: 20px; border: 2px solid black; position: fixed; top: 50px; left: 0; z-index: 9999; max-width: 500px; max-height: 400px; overflow: auto;">';
        echo '<h3>Database Check</h3>';
        
        // Check the actual database relationships
        global $wpdb;
        
        // Get term_taxonomy_id for our term
        $term_info = $wpdb->get_row($wpdb->prepare("
            SELECT t.term_id, t.name, tt.term_taxonomy_id, tt.taxonomy, tt.count 
            FROM {$wpdb->terms} t 
            JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id 
            WHERE t.term_id = %d AND tt.taxonomy = %s
        ", 1514, 'event_caes_departments'));
        
        echo '<h4>Term Info:</h4>';
        echo '<pre>' . print_r($term_info, true) . '</pre>';
        
        if ($term_info) {
            // Get actual post relationships
            $relationships = $wpdb->get_results($wpdb->prepare("
                SELECT tr.object_id, p.post_title, p.post_status
                FROM {$wpdb->term_relationships} tr
                JOIN {$wpdb->posts} p ON tr.object_id = p.ID
                WHERE tr.term_taxonomy_id = %d AND p.post_type = 'events'
                ORDER BY p.post_date DESC
                LIMIT 10
            ", $term_info->term_taxonomy_id));
            
            echo '<h4>Posts with this term (from database):</h4>';
            if (!empty($relationships)) {
                echo '<ul>';
                foreach ($relationships as $rel) {
                    echo '<li>' . $rel->post_title . ' (ID: ' . $rel->object_id . ', Status: ' . $rel->post_status . ')</li>';
                }
                echo '</ul>';
            } else {
                echo '<p>No relationships found in database</p>';
            }
        }
        
        echo '</div>';
    }
});