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


// CAES Admin Tools to keep
require get_template_directory() . '/inc/topic-management.php';


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
require get_template_directory() . '/inc/pub-state-issue-set.php';

// Plugin overrides
require get_template_directory() . '/inc/plugin-overrides/relevanssi-search.php';

/**
 * DEBUGGING FUNCTION FOR SOFT PUBLISH (ERROR LOG VERSION)
 * This function will write diagnostic information to the PHP error log
 * for a single 'shorthand_story' post page when visited by an administrator.
 *
 * To use: 
 * 1. Add this to your functions.php file.
 * 2. Log in as an administrator.
 * 3. Visit the direct URL of the shorthand_story that is giving a 404 error.
 * 4. Check your server's error.log file for the debug output.
 *
 * To disable, simply remove or comment out the add_action line at the bottom.
 */
function debug_soft_publish_query_to_log($posts, $query) {
    // Only run for logged-in admins on the frontend, for a single 'shorthand_story' post
    if ( !is_admin() && $query->is_main_query() && $query->is_singular('shorthand_story') && current_user_can('manage_options') ) {

        // Start building the log message
        $log_message = "--- SOFT PUBLISH DEBUGGER ---" . "\n";
        $log_message .= "URL Visited: " . (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]" . "\n";

        // Check if a post was found by the query
        if ( !empty($posts) ) {
            $found_post = $posts[0];
            $log_message .= "RESULT: Post Found!" . "\n";
            $log_message .= "Post ID: " . $found_post->ID . "\n";
            $log_message .= "Post Title: " . $found_post->post_title . "\n";
            $log_message .= "Post Status: " . $found_post->post_status . "\n";
        } else {
            $log_message .= "RESULT: ERROR! No post was found for this URL." . "\n";
        }

        // Add the query details
        $log_message .= "QUERY ARGS USED:" . "\n";
        // The 'true' parameter returns the output as a string
        $log_message .= print_r($query->query_vars, true);
        $log_message .= "-----------------------------" . "\n";

        // Write everything to the error log
        error_log($log_message);
    }

    return $posts;
}
// To activate the debugger, leave the line below. To deactivate, comment it out.
add_filter('the_posts', 'debug_soft_publish_query_to_log', 10, 2);