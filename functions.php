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

// Debug Tifton term and events more thoroughly
add_action('wp_footer', function() {
    if (current_user_can('administrator')) {
        echo '<div style="background: pink; padding: 20px; margin: 20px; border: 2px solid red; position: fixed; top: 50px; right: 0; z-index: 9999; max-width: 500px;">';
        echo '<h3>Tifton Term Debug</h3>';
        
        // 1. Check what the actual term ID is for "Tifton Campus Conference Center"
        $tifton_term = get_term_by('name', 'Tifton Campus Conference Center', 'event_caes_departments');
        if ($tifton_term) {
            echo '<p>Tifton term found: ' . $tifton_term->name . ' (ID: ' . $tifton_term->term_id . ')</p>';
            
            // Check events with this term using the correct ID
            $tifton_events = get_posts(array(
                'post_type' => 'events',
                'numberposts' => 10,
                'tax_query' => array(
                    array(
                        'taxonomy' => 'event_caes_departments',
                        'field' => 'term_id',
                        'terms' => $tifton_term->term_id
                    )
                )
            ));
            
            echo '<p>Events with correct term ID: ' . count($tifton_events) . '</p>';
            foreach ($tifton_events as $event) {
                echo '<p>' . $event->post_title . ' (ID: ' . $event->ID . ')</p>';
            }
        } else {
            echo '<p>Tifton term NOT found by name</p>';
        }
        
        // 2. Check the specific event "NPC Night of the Gladiators" 
        $npc_event = get_posts(array(
            'post_type' => 'events',
            'name' => 'npc-night-of-the-gladiators', // slug
            'numberposts' => 1
        ));
        
        if (!empty($npc_event)) {
            $event_id = $npc_event[0]->ID;
            echo '<h4>NPC Event (ID: ' . $event_id . '):</h4>';
            $terms = wp_get_post_terms($event_id, 'event_caes_departments');
            echo '<p>Terms: ';
            foreach ($terms as $term) {
                echo $term->name . ' (ID: ' . $term->term_id . '), ';
            }
            echo '</p>';
        } else {
            echo '<p>NPC event not found</p>';
        }
        
        echo '</div>';
    }
});