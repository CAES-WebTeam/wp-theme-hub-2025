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

// Plugin overrides
require get_template_directory() . '/inc/plugin-overrides/relevanssi-search.php';

add_action('admin_init', function() {
    // Change this to the post type you want to inspect
    $post_type_slug = 'shorthand_story';

    // Check if we are on an admin page and the post type exists
    if (is_admin() && post_type_exists($post_type_slug)) {
        $post_type_object = get_post_type_object($post_type_slug);

        // Use a unique query parameter to trigger the debug output
        if (isset($_GET['debug_cpt'])) {
            echo '<pre>';
            echo '<strong>Post Type Details for: ' . esc_html($post_type_slug) . '</strong><br><br>';

            echo 'Publicly Queryable: ';
            var_dump($post_type_object->publicly_queryable);

            echo 'Public: ';
            var_dump($post_type_object->public);

            echo 'Exclude From Search: ';
            var_dump($post_type_object->exclude_from_search);
            echo '</pre>';
            die(); // Stop everything to show the results clearly
        }
    }
});