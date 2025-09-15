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
 * FINAL ONE-TIME SCRIPT: Backfill '_publication_latest_revision_date'
 * ========================================================================
 *
 * This version uses a direct database query ($wpdb) to bypass ACF's
 * functions, which can be unreliable in this context. This is the most
 * robust method for a one-time bulk update.
 *
 * TO RUN:
 * 1. Replace the old script with this one in your theme's functions.php.
 * 2. Log in as an administrator.
 * 3. Visit: https://yourdomain.com/?update_revision_dates=true
 * 4. Review the detailed on-screen report.
 * 5. *** CRITICAL: REMOVE THIS CODE AFTER YOU HAVE RUN IT ONCE. ***
 */
add_action('wp_loaded', function () {
    // 1. Security Check
    if (!isset($_GET['update_revision_dates']) || !current_user_can('manage_options')) {
        return;
    }

    // Bring the global WordPress database object into scope.
    global $wpdb;

    // 2. Query for all published publication IDs.
    $post_ids = $wpdb->get_col(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'publications' AND post_status = 'publish'"
    );

    $total_found = count($post_ids);
    if (empty($post_ids)) {
        wp_die('<h1>Update Report</h1><p>No publications were found to process.</p>');
    }

    $updated_count = 0;
    $skipped_count = 0;
    $report_html = '<h1>Publication Revision Date Update Report (Direct DB Method)</h1>';
    $report_html .= "<p>Found a total of <strong>{$total_found}</strong> publications to check.</p>";
    $report_html .= '<ul style="font-family: monospace; line-height: 1.6;">';

    // 3. Loop through each publication ID.
    foreach ($post_ids as $post_id) {
        $title = esc_html(get_the_title($post_id));
        $report_html .= "<li><strong>Checking:</strong> \"{$title}\" (ID: {$post_id})";

        // Skip if the meta key already has a non-empty value.
        if (!empty(get_post_meta($post_id, '_publication_latest_revision_date', true))) {
            $skipped_count++;
            $report_html .= " - <span style='color: orange;'>SKIPPED (Already has a date)</span></li>";
            continue;
        }

        // 4. Directly query the postmeta table for the repeater field rows.
        // ACF repeater data is stored with keys like 'history_0_status', 'history_1_date', etc.
        $meta_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
                $post_id,
                'history_%' // The wildcard finds all sub-fields of the 'history' repeater
            )
        );

        if (empty($meta_rows)) {
            $skipped_count++;
            $report_html .= " - <span style='color: red;'>SKIPPED (No 'history' meta found in DB)</span></li>";
            continue;
        }

        // 5. Reconstruct the repeater data and find the latest date.
        $history_data = [];
        foreach ($meta_rows as $meta_row) {
            // Extracts the row index (0, 1, 2...) and field name (status, date)
            if (preg_match('/^history_(\d+)_(.*)$/', $meta_row->meta_key, $matches)) {
                $row_index = $matches[1];
                $field_name = $matches[2];
                $history_data[$row_index][$field_name] = $meta_row->meta_value;
            }
        }
        
        $latest_revision_date = 0;
        $revision_status_keys = [4, 5, 6]; // The statuses we care about.

        if (!empty($history_data)) {
            foreach ($history_data as $row) {
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
            $report_html .= " - <span style='color: green;'>UPDATED with date: {$latest_revision_date}</span></li>";
        } else {
            $skipped_count++;
            $report_html .= " - <span style='color: red;'>SKIPPED (No valid history dates found after checking DB)</span></li>";
        }
    }

    $report_html .= '</ul>';
    $report_html .= '<h2>Summary</h2>';
    $report_html .= "<p><strong>{$updated_count}</strong> publications were successfully updated.</p>";
    $report_html .= "<p><strong>{$skipped_count}</strong> publications were skipped.</p>";
    $report_html .= '<p style="font-weight: bold; color: red;">Action complete. Please remove this script from your functions.php file now.</p>';

    // 7. Display the final report and stop execution.
    wp_die($report_html);
});