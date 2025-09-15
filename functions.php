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
 * FINAL CORRECTED SCRIPT: Verify Raw DB Values and Backfill Dates
 * ========================================================================
 *
 * CORRECTION: This version fixes a critical bug where (int) casting was
 * only reading the year from a full 'YYYY-MM-DD' date string. It now
 * correctly parses the full date, ensuring accurate sorting.
 *
 * TO RUN:
 * 1. Replace the old script with this one.
 * 2. Log in as an administrator and visit: https://yourdomain.com/?verify_publication_dates=true
 * 3. Review the corrected on-screen report.
 * 4. *** CRITICAL: REMOVE THIS CODE AFTER YOU HAVE RUN IT ONCE. ***
 */
add_action('wp_loaded', function () {
    // 1. Security Check
    if (!isset($_GET['verify_publication_dates']) || !current_user_can('manage_options')) {
        return;
    }

    global $wpdb;

    // 2. Query for all published publication IDs.
    $post_ids = $wpdb->get_col(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'publications' AND post_status = 'publish'"
    );

    if (empty($post_ids)) {
        wp_die('<h1>Update Report</h1><p>No publications were found to process.</p>');
    }

    $report_html = '<h1>Publication Dates - Raw Database Verification & Update (Corrected)</h1>';
    $report_html .= '<ul style="font-family: monospace; line-height: 1.6;">';

    // 3. Loop through each publication ID.
    foreach ($post_ids as $post_id) {
        $title = esc_html(get_the_title($post_id));
        $report_html .= "<li><strong>Checking:</strong> \"{$title}\" (ID: {$post_id})";

        // 4. Directly query the postmeta table for the repeater field rows.
        $meta_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
                $post_id,
                'history_%'
            )
        );

        if (empty($meta_rows)) {
            $report_html .= " - <span style='color: red;'>SKIPPED (No 'history' meta found)</span></li>";
            continue;
        }

        // --- Verification Section ---
        $report_html .= "<div style='margin-left: 20px; font-size: 12px; background: #f1f1f1; padding: 10px; border: 1px solid #ccc;'>";
        $report_html .= "<strong>Raw Database Values Found:</strong><pre>";
        $history_data = [];
        foreach ($meta_rows as $meta_row) {
            $report_html .= esc_html($meta_row->meta_key) . " => '" . esc_html($meta_row->meta_value) . "'\n";
            if (preg_match('/^history_(\d+)_(.*)$/', $meta_row->meta_key, $matches)) {
                $history_data[$matches[1]][$matches[2]] = $meta_row->meta_value;
            }
        }
        $report_html .= "</pre></div>";
        // --- End Verification Section ---

        $latest_revision_date = 0;
        $latest_publish_date = 0;
        $revision_status_keys = [4, 5, 6];
        $publish_status_key = [2];

        if (!empty($history_data)) {
            foreach ($history_data as $row) {
                $status = isset($row['status']) ? (int) $row['status'] : 0;
                $date_str = isset($row['date']) ? $row['date'] : '';

                if (!empty($date_str)) {
                    // *** THE FIX: Properly convert the date string to a numeric YYYYMMDD format. ***
                    // 1. Remove all non-numeric characters.
                    $numeric_date_str = preg_replace('/[^0-9]/', '', $date_str);
                    // 2. Take the first 8 characters (the date part) and convert to an integer.
                    $current_date = (int) substr($numeric_date_str, 0, 8);

                    if (in_array($status, $revision_status_keys) && $current_date > $latest_revision_date) {
                        $latest_revision_date = $current_date;
                    }
                    if (in_array($status, $publish_status_key) && $current_date > $latest_publish_date) {
                        $latest_publish_date = $current_date;
                    }
                }
            }
        }

        // 6. Report on and save the calculated dates.
        $report_html .= "<div style='margin-left: 20px;'>";
        if ($latest_revision_date > 0) {
            update_post_meta($post_id, '_publication_latest_revision_date', $latest_revision_date);
            $report_html .= "↳ <span style='color: green;'>Calculated & Saved Revision Date:</span> {$latest_revision_date}<br/>";
        } else {
            $report_html .= "↳ <span style='color: orange;'>No valid revision date was calculated.</span><br/>";
        }

        if ($latest_publish_date > 0) {
            update_post_meta($post_id, '_publication_latest_publish_date', $latest_publish_date);
            $report_html .= "↳ <span style='color: green;'>Calculated & Saved Publish Date:</span> {$latest_publish_date}";
        } else {
            $report_html .= "↳ <span style='color: orange;'>No valid publish date was calculated.</span>";
        }
        $report_html .= "</div></li>";
    }

    $report_html .= '</ul>';
    $report_html .= '<h2>Action Complete</h2>';
    $report_html .= '<p style="font-weight: bold; color: red;">You can now see the corrected values above. Please remove this script from your functions.php file now.</p>';

    wp_die($report_html);
});