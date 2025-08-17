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

// One-time script to approve all events for their assigned taxonomies
add_action('wp_footer', function() {
    if (current_user_can('administrator') && isset($_GET['approve_all_events'])) {
        echo '<div style="background: blue; color: white; padding: 20px; margin: 20px; border: 2px solid navy; position: fixed; top: 0; left: 0; z-index: 9999; max-width: 600px; max-height: 400px; overflow: auto;">';
        echo '<h3>Approving All Events...</h3>';
        
        // Get all events
        $all_events = get_posts(array(
            'post_type' => 'events',
            'post_status' => 'publish',
            'numberposts' => -1
        ));
        
        echo '<p>Found ' . count($all_events) . ' events to process...</p>';
        
        $updated_count = 0;
        
        foreach ($all_events as $event) {
            // Get all CAES departments assigned to this event
            $assigned_terms = wp_get_post_terms($event->ID, 'event_caes_departments');
            
            if (!empty($assigned_terms)) {
                // Build approval status array
                $approval_status = array();
                
                foreach ($assigned_terms as $term) {
                    $approval_status[$term->term_id] = 'approved';
                }
                
                // Update the approval status
                update_post_meta($event->ID, '_calendar_approval_status', $approval_status);
                
                echo '<p>✓ Approved: ' . $event->post_title . ' for ' . count($assigned_terms) . ' calendar(s)</p>';
                $updated_count++;
            } else {
                echo '<p>⚠ Skipped: ' . $event->post_title . ' (no calendars assigned)</p>';
            }
        }
        
        echo '<h4>✅ DONE! Updated ' . $updated_count . ' events</h4>';
        echo '<p><strong>You can now remove this code and refresh the page.</strong></p>';
        echo '</div>';
    }
});

// Also auto-approve future events when they're saved
add_action('save_post_events', function($post_id) {
    // Skip during bulk operations and autosaves
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    
    // Get assigned terms
    $assigned_terms = wp_get_post_terms($post_id, 'event_caes_departments');
    
    if (!empty($assigned_terms)) {
        $approval_status = array();
        
        foreach ($assigned_terms as $term) {
            $approval_status[$term->term_id] = 'approved';
        }
        
        update_post_meta($post_id, '_calendar_approval_status', $approval_status);
    }
});