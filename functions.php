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
require get_template_directory() . '/inc/detect-duplicates.php';
require get_template_directory() . '/inc/pub-sunset-tool.php';

// Plugin overrides
require get_template_directory() . '/inc/plugin-overrides/relevanssi-search.php';

/**
 * ========================================================================
 * ONE-TIME SCRIPT: Backfill the '_publication_latest_revision_date' meta.
 * ========================================================================
 *
 * This script iterates through all existing 'publications' and calculates
 * the latest revision date from the 'history' ACF repeater field, saving
 * it to a meta field for efficient querying.
 *
 * TO RUN:
 * 1. Add this code to your theme's functions.php file.
 * 2. Log in as an administrator.
 * 3. Visit your website's homepage and add '?update_revision_dates=true' to the URL.
 * Example: https://yourdomain.com/?update_revision_dates=true
 * 4. Review the on-screen report to ensure it completed successfully.
 * 5. *** IMPORTANT: REMOVE THIS CODE AFTER YOU HAVE RUN IT ONCE. ***
 */
add_action('init', function () {
    // 1. Security Check: Only run if the query var is set and user is an admin.
    if (!isset($_GET['update_revision_dates']) || !current_user_can('manage_options')) {
        return;
    }

    // 2. Query all existing publications that are published.
    $publications = new WP_Query([
        'post_type'      => 'publications',
        'posts_per_page' => -1, // Get all of them
        'post_status'    => 'publish',
        'fields'         => 'ids', // We only need the post IDs for efficiency
    ]);

    if (!$publications->have_posts()) {
        wp_die('<h1>Update Complete</h1><p>No publications were found to process.</p>');
    }

    $updated_count = 0;
    $skipped_count = 0;
    $report_html = '<h1>Publication Revision Date Update Report</h1>';
    $report_html .= '<ul>';

    // 3. Loop through each publication ID.
    foreach ($publications->posts as $post_id) {
        // Check if the meta field already has a value. If so, skip it.
        if (get_post_meta($post_id, '_publication_latest_revision_date', true)) {
            $skipped_count++;
            continue;
        }

        // Get the 'history' repeater field from ACF.
        $history_rows = get_field('history', $post_id);
        $latest_revision_date = 0;
        $revision_status_keys = [4, 5, 6]; // The revision statuses we care about.

        if (is_array($history_rows)) {
            // 4. Calculate the latest date from the history repeater.
            foreach ($history_rows as $row) {
                $status   = isset($row['status']) ? (int) $row['status'] : 0;
                $date_str = isset($row['date']) ? $row['date'] : '';

                if (in_array($status, $revision_status_keys) && !empty($date_str)) {
                    $current_date = (int) $date_str;
                    if ($current_date > $latest_revision_date) {
                        $latest_revision_date = $current_date;
                    }
                }
            }
        }

        // 5. Save the calculated date to the post's meta.
        if ($latest_revision_date > 0) {
            update_post_meta($post_id, '_publication_latest_revision_date', $latest_revision_date);
            $updated_count++;
            $report_html .= '<li>Updated: "' . get_the_title($post_id) . '" (ID: ' . $post_id . ') with date ' . $latest_revision_date . '</li>';
        } else {
            // If no valid history was found, we can consider it "skipped".
            $skipped_count++;
        }
    }

    $report_html .= '</ul>';
    $report_html .= '<h2>Summary</h2>';
    $report_html .= "<p><strong>{$updated_count}</strong> publications were successfully updated.</p>";
    $report_html .= "<p><strong>{$skipped_count}</strong> publications were skipped (already had a date or no history found).</p>";
    $report_html .= '<p><strong>Action complete. You should now remove this script from your functions.php file.</strong></p>';

    // 6. Display the final report and stop execution.
    wp_die($report_html);
});