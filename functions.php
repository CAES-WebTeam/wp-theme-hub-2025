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
 * REVISED ONE-TIME SCRIPT: Backfill '_publication_latest_revision_date'
 * ========================================================================
 *
 * This script is designed to be more robust and provides better debugging.
 * It runs on the 'wp_loaded' hook to ensure all plugins like ACF are active.
 *
 * TO RUN:
 * 1. Replace the old script with this one in your theme's functions.php file.
 * 2. Log in as an administrator.
 * 3. Visit: https://yourdomain.com/?update_revision_dates=true
 * 4. Review the detailed on-screen report.
 * 5. *** IMPORTANT: REMOVE THIS CODE AFTER YOU HAVE RUN IT ONCE. ***
 */
add_action('wp_loaded', function () {
    // 1. Security Check: Only proceed if the query var is present and the user is an admin.
    if (!isset($_GET['update_revision_dates']) || !current_user_can('manage_options')) {
        return;
    }

    // 2. Pre-flight Check: Ensure ACF's get_field function exists.
    if (!function_exists('get_field')) {
        wp_die('<h1>Error</h1><p>Advanced Custom Fields (ACF) plugin is not active. The script cannot run without it.</p>');
    }

    // 3. Query all published publications.
    $publications = new WP_Query([
        'post_type'      => 'publications',
        'posts_per_page' => -1, // Process all posts.
        'post_status'    => 'publish',
        'fields'         => 'ids', // More efficient to just get IDs.
    ]);

    $total_found = $publications->post_count;
    if (!$publications->have_posts()) {
        wp_die('<h1>Update Report</h1><p>No publications were found to process.</p>');
    }

    $updated_count = 0;
    $skipped_count = 0;
    $report_html = '<h1>Publication Revision Date Update Report</h1>';
    $report_html .= "<p>Found a total of <strong>{$total_found}</strong> publications to check.</p>";
    $report_html .= '<ul>';

    // 4. Loop through each publication.
    foreach ($publications->posts as $post_id) {
        // We now check if the meta value is NOT empty, allowing us to update posts
        // where the key might exist but has no value.
        if (!empty(get_post_meta($post_id, '_publication_latest_revision_date', true))) {
            $skipped_count++;
            continue;
        }

        $history_rows = get_field('history', $post_id);
        $latest_revision_date = 0;
        $revision_status_keys = [4, 5, 6];

        if (is_array($history_rows)) {
            // 5. Calculate the latest date from the history repeater.
            foreach ($history_rows as $row) {
                $status   = isset($row['status']) ? (int) $row['status'] : 0;
                $date_str = isset($row['date']) ? $row['date'] : '';

                if (in_array($status, $revision_status_keys) && !empty($date_str)) {
                    $current_date = (int) $date_str; // Stored as Ymd
                    if ($current_date > $latest_revision_date) {
                        $latest_revision_date = $current_date;
                    }
                }
            }
        }

        // 6. Save the new date to the post meta.
        if ($latest_revision_date > 0) {
            update_post_meta($post_id, '_publication_latest_revision_date', $latest_revision_date);
            $updated_count++;
            $report_html .= '<li><strong>Updated:</strong> "' . esc_html(get_the_title($post_id)) . '" (ID: ' . $post_id . ') with date ' . $latest_revision_date . '</li>';
        } else {
             // If no valid history was found, we still consider it skipped.
            $skipped_count++;
             $report_html .= '<li><em>Skipped:</em> "' . esc_html(get_the_title($post_id)) . '" (ID: ' . $post_id . ') - No valid history dates found.</li>';
        }
    }

    $report_html .= '</ul>';
    $report_html .= '<h2>Summary</h2>';
    $report_html .= "<p><strong>{$updated_count}</strong> publications were successfully updated.</p>";
    $report_html .= "<p><strong>{$skipped_count}</strong> publications were skipped (already had a date or no history found).</p>";
    $report_html .= '<p style="font-weight: bold; color: red;">Action complete. Please remove this script from your functions.php file now.</p>';

    // 7. Display the final report and stop execution.
    wp_die($report_html);
});