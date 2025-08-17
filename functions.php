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

// Check Tifton events specifically
add_action('wp_footer', function() {
    if (current_user_can('administrator')) {
        echo '<div style="background: yellow; padding: 20px; margin: 20px; border: 2px solid orange; position: fixed; top: 0; right: 0; z-index: 9999; max-width: 400px;">';
        echo '<h3>Tifton Events Debug</h3>';
        
        // Get events assigned to Tifton Campus Conference Center (term ID 1528)
        $tifton_events = get_posts(array(
            'post_type' => 'events',
            'numberposts' => 10,
            'tax_query' => array(
                array(
                    'taxonomy' => 'event_caes_departments',
                    'field' => 'term_id',
                    'terms' => 1528
                )
            )
        ));
        
        echo '<p>Found ' . count($tifton_events) . ' events with Tifton term</p>';
        
        foreach ($tifton_events as $event) {
            $approval_status = get_post_meta($event->ID, '_calendar_approval_status', true);
            echo '<h4>' . $event->post_title . ' (ID: ' . $event->ID . ')</h4>';
            echo '<pre style="font-size: 10px; background: white; padding: 3px;">';
            if (empty($approval_status)) {
                echo 'NO APPROVAL STATUS';
            } else {
                print_r($approval_status);
            }
            echo '</pre>';
        }
        
        echo '</div>';
    }
});