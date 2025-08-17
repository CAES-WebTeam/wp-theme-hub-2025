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

// Database-level debug for the NPC event
add_action('wp_footer', function() {
    if (current_user_can('administrator')) {
        global $wpdb;
        
        echo '<div style="background: lightgreen; padding: 20px; margin: 20px; border: 2px solid green; position: fixed; bottom: 0; right: 0; z-index: 9999; max-width: 500px;">';
        echo '<h3>Database Debug for NPC Event</h3>';
        
        $event_id = 86574; // NPC event ID
        
        // 1. Check post status
        $post = get_post($event_id);
        echo '<p>Post status: ' . $post->post_status . '</p>';
        echo '<p>Post type: ' . $post->post_type . '</p>';
        
        // 2. Check term relationships in database
        $relationships = $wpdb->get_results($wpdb->prepare("
            SELECT tr.term_taxonomy_id, tt.term_id, t.name 
            FROM {$wpdb->term_relationships} tr
            JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
            WHERE tr.object_id = %d AND tt.taxonomy = 'event_caes_departments'
        ", $event_id));
        
        echo '<h4>Database relationships:</h4>';
        foreach ($relationships as $rel) {
            echo '<p>Term: ' . $rel->name . ' (ID: ' . $rel->term_id . ', taxonomy_id: ' . $rel->term_taxonomy_id . ')</p>';
        }
        
        // 3. Test simple query without any other filters
        $simple_query = new WP_Query(array(
            'post_type' => 'events',
            'post_status' => 'any', // Include all statuses
            'posts_per_page' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => 'event_caes_departments',
                    'field' => 'term_id',
                    'terms' => 1528
                )
            )
        ));
        
        echo '<h4>Simple query (post_status = any):</h4>';
        echo '<p>Found: ' . $simple_query->found_posts . ' posts</p>';
        if ($simple_query->have_posts()) {
            while ($simple_query->have_posts()) {
                $simple_query->the_post();
                echo '<p>' . get_the_title() . ' (Status: ' . get_post_status() . ')</p>';
            }
        }
        wp_reset_postdata();
        
        echo '</div>';
    }
});