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
require get_template_directory() . '/inc/publications-support.php';
require get_template_directory() . '/inc/user-support.php';
require get_template_directory() . '/inc/news-support.php';
require get_template_directory() . '/block-variations/index.php';
require get_template_directory() . '/inc/custom-rewrites.php';

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
require get_template_directory() . '/inc/bulk-topic-moverr.php';
require get_template_directory() . '/inc/pub-history-update.php';
require get_template_directory() . '/inc/story-association-meta-tools.php';
require get_template_directory() . '/inc/pub-main-import.php';

// Plugin overrides
require get_template_directory() . '/inc/plugin-overrides/relevanssi-search.php';

// Add this to events-support.php temporarily for debugging
add_action('save_post', 'debug_calendar_save_process', 1, 3);
function debug_calendar_save_process($post_id, $post, $update) {
    if ($post->post_type !== 'events') {
        return;
    }
    
    $current_user_id = get_current_user_id();
    $post_author_id = get_post_field('post_author', $post_id);
    
    error_log("=== SAVE POST DEBUG ===");
    error_log("Post ID: {$post_id}");
    error_log("Current User: {$current_user_id}");
    error_log("Post Author: {$post_author_id}");
    error_log("Post Status: " . $post->post_status);
    error_log("Is AJAX: " . (defined('DOING_AJAX') && DOING_AJAX ? 'Yes' : 'No'));
    
    // Check what's in POST data for the calendar field
    if (isset($_POST['acf'])) {
        error_log("ACF POST data exists");
        foreach ($_POST['acf'] as $key => $value) {
            $field_object = get_field_object($key);
            if ($field_object && $field_object['name'] === 'caes_department') {
                error_log("Calendar field in POST: " . print_r($value, true));
            }
        }
    } else {
        error_log("No ACF POST data found");
    }
    
    // Check existing value
    $existing_calendars = get_field('caes_department', $post_id);
    error_log("Existing calendars before save: " . print_r($existing_calendars, true));
}