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

// Debug approval status for your events
add_action('wp_footer', function() {
    if (current_user_can('administrator')) {
        echo '<div style="background: lightblue; padding: 20px; margin: 20px; border: 2px solid blue; position: fixed; bottom: 0; left: 0; z-index: 9999; max-width: 600px; max-height: 400px; overflow: auto;">';
        echo '<h3>Event Approval Status Debug</h3>';
        
        // Check the specific events we know exist
        $event_ids = [86577, 86573, 86576, 86569, 86570, 86571, 86572, 86568, 78770];
        
        foreach ($event_ids as $event_id) {
            $post_title = get_the_title($event_id);
            $approval_status = get_post_meta($event_id, '_calendar_approval_status', true);
            
            echo '<h4>' . $post_title . ' (ID: ' . $event_id . ')</h4>';
            echo '<pre style="font-size: 11px; background: white; padding: 5px;">';
            if (empty($approval_status)) {
                echo 'NO APPROVAL STATUS META FIELD';
            } else {
                print_r($approval_status);
            }
            echo '</pre>';
        }
        
        echo '</div>';
    }
});