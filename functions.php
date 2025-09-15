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
 * COMBINED ONE-TIME SCRIPT: Backfill Revision and Publish Dates
 * ========================================================================
 *
 * This script iterates through all existing 'publications' and calculates
 * BOTH the latest revision date and the latest publish date from the
 * 'history' ACF repeater, saving each to its own meta field.
 *
 * TO RUN:
 * 1. Add this code to your theme's functions.php file.
 * 2. Log in as an administrator.
 * 3. Visit: https://yourdomain.com/?update_publication_dates=true
 * 4. Review the detailed on-screen report.
 * 5. *** CRITICAL: REMOVE THIS CODE AFTER YOU HAVE RUN IT ONCE. ***
 */
add_action('wp_loaded', function () {
    // 1. Security Check: Use a new, more descriptive query variable.
    if (!isset($_GET['update_publication_dates']) || !current_user_can('manage_options')) {
        return;
    }

    global $wpdb;

    // 2. Query for all published publication IDs.
    $post_ids = $wpdb->get_col(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'publications' AND post_status = 'publish'"
    );

    $total_found = count($post_ids);
    if (empty($post_ids)) {
        wp_die('<h1>Update Report</h1><p>No publications were found to process.</p>');
    }

    $revision_updated_count = 0;
    $publish_updated_count = 0;
    $report_html = '<h1>Publication Dates Update Report (Revision & Publish)</h1>';
    $report_html .= "<p>Found <strong>{$total_found}</strong> publications to check.</p>";
    $report_html .= '<ul style="font-family: monospace; line-height: 1.6;">';

    // 3. Loop through each publication ID.
    foreach ($post_ids as $post_id) {
        $title = esc_html(get_the_title($post_id));
        $report_html .= "<li><strong>Checking:</strong> \"{$title}\" (ID: {$post_id})";

        // Directly query the postmeta table for the repeater field rows.
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

        // Reconstruct the repeater data from the meta rows.
        $history_data = [];
        foreach ($meta_rows as $meta_row) {
            if (preg_match('/^history_(\d+)_(.*)$/', $meta_row->meta_key, $matches)) {
                $row_index = $matches[1];
                $field_name = $matches[2];
                $history_data[$row_index][$field_name] = $meta_row->meta_value;
            }
        }
        
        $latest_revision_date = 0;
        $latest_publish_date = 0;

        $revision_status_keys = [4, 5, 6]; // Revised, Updated, Republished
        $publish_status_key = [2];         // Published

        // Loop through the history to find the latest date for EACH category.
        if (!empty($history_data)) {
            foreach ($history_data as $row) {
                $status   = isset($row['status']) ? (int) $row['status'] : 0;
                $date_str = isset($row['date']) ? $row['date'] : '';

                if (!empty($date_str)) {
                    $current_date = (int) $date_str;

                    // Check for latest revision date
                    if (in_array($status, $revision_status_keys) && $current_date > $latest_revision_date) {
                        $latest_revision_date = $current_date;
                    }

                    // Check for latest publish date
                    if (in_array($status, $publish_status_key) && $current_date > $latest_publish_date) {
                        $latest_publish_date = $current_date;
                    }
                }
            }
        }

        $report_html .= "<ul>";

        // Save the LATEST REVISION date if found
        if ($latest_revision_date > 0) {
            update_post_meta($post_id, '_publication_latest_revision_date', $latest_revision_date);
            $revision_updated_count++;
            $report_html .= "<li><span style='color: green;'>Updated Revision Date:</span> {$latest_revision_date}</li>";
        } else {
            $report_html .= "<li><span style='color: orange;'>No new revision date found.</span></li>";
        }

        // Save the LATEST PUBLISH date if found
        if ($latest_publish_date > 0) {
            update_post_meta($post_id, '_publication_latest_publish_date', $latest_publish_date);
            $publish_updated_count++;
            $report_html .= "<li><span style='color: green;'>Updated Publish Date:</span> {$latest_publish_date}</li>";
        } else {
            $report_html .= "<li><span style='color: orange;'>No new publish date found.</span></li>";
        }
        
        $report_html .= "</ul></li>";
    }

    $report_html .= '</ul>';
    $report_html .= '<h2>Summary</h2>';
    $report_html .= "<p><strong>{$revision_updated_count}</strong> publications had their 'latest revision date' updated.</p>";
    $report_html .= "<p><strong>{$publish_updated_count}</strong> publications had their 'latest publish date' updated.</p>";
    $report_html .= '<p style="font-weight: bold; color: red;">Action complete. Please remove this script from your functions.php file now.</p>';

    wp_die($report_html);
});