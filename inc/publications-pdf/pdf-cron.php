<?php
/**
 * Manages WP-Cron events for PDF generation queue processing.
 */

// Ensure this file is called by WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Include the PDF generation utility and queue functions
// require_once get_template_directory() . '/inc/publications-pdf/publications-pdf.php'; // The modified PDF generation logic
require_once get_template_directory() . '/inc/publications-pdf/publications-pdf-mpdf.php'; // The mPDF version
require_once get_template_directory() . '/inc/publications-pdf/pdf-queue.php';   // The queue functions

/**
 * Schedules the PDF generation cron job.
 */
function schedule_pdf_generation_cron() {
    if (!wp_next_scheduled('my_pdf_generation_cron_hook')) {
        // Schedule to run every 5 minutes (adjust as needed)
        wp_schedule_event(time(), 'every_five_minutes', 'my_pdf_generation_cron_hook');
    }
}
// Using 'init' hook to ensure everything is loaded for scheduling
add_action('init', 'schedule_pdf_generation_cron');

/**
 * Adds a custom cron interval for 'every_five_minutes'.
 *
 * @param array $schedules Existing cron schedules.
 * @return array Modified schedules.
 */
function add_five_minute_cron_interval($schedules) {
    $schedules['every_five_minutes'] = array(
        'interval' => 300, // 300 seconds = 5 minutes
        'display'  => esc_html__('Every Five Minutes'),
    );
    return $schedules;
}
add_filter('cron_schedules', 'add_five_minute_cron_interval');

/**
 * Processes a batch of pending PDF generation tasks from the queue.
 */
function process_pdf_generation_queue() {
    // Prevent multiple simultaneous runs if the cron job is slow
    if (get_transient('pdf_generation_lock')) {
        return;
    }
    set_transient('pdf_generation_lock', true, 60 * 5); // Lock for 5 minutes (cron interval)

    $items_to_process = get_pending_pdf_queue_items(3); // Process up to 3 PDFs per cron run

    if (empty($items_to_process)) {
        delete_transient('pdf_generation_lock'); // Remove lock if nothing to do
        return;
    }

    foreach ($items_to_process as $item) {
        $post_id = $item->post_id;
        $queue_item_id = $item->id;
        $post_title = get_the_title($post_id); // For notification

        // Update status to 'processing'
        update_pdf_queue_status($queue_item_id, 'processing', 'Started PDF generation.');

        // Attempt to generate the PDF file
        // $pdf_url = generate_publication_pdf_file($post_id); // This is our modified function
        // Use the mPDF version
        $pdf_url = generate_publication_pdf_file_mpdf($post_id);

        if ($pdf_url) {
            // Update ACF field with the PDF URL
            update_field('pdf_download_url', $pdf_url, $post_id);
            // Update queue status to 'completed'
            update_pdf_queue_status($queue_item_id, 'completed', 'PDF generated successfully. URL: ' . $pdf_url);
            // Set notification for admin
            set_transient('pdf_generation_notice_' . $post_id, array('type' => 'success', 'message' => sprintf('PDF for "%s" generated successfully!', $post_title)), 60 * 60 * 24); // 24 hours
        } else {
            // PDF generation failed
            update_pdf_queue_status($queue_item_id, 'failed', 'PDF generation failed. Check error logs.');
            set_transient('pdf_generation_notice_' . $post_id, array('type' => 'error', 'message' => sprintf('PDF generation for "%s" failed. Please check logs.', $post_title)), 60 * 60 * 24);
        }
    }

    delete_transient('pdf_generation_lock'); // Release lock
}
add_action('my_pdf_generation_cron_hook', 'process_pdf_generation_queue');

/**
 * Clears the PDF generation cron schedule when this theme is deactivated (switched away from).
 */
function deactivate_pdf_generation_cron() {
    $timestamp = wp_next_scheduled('my_pdf_generation_cron_hook');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'my_pdf_generation_cron_hook');
    }
    // Also a good idea to clear the transient lock in case it was active during deactivation
    delete_transient('pdf_generation_lock');
}
// This hook fires when a theme is switched, allowing us to clean up our cron events.
add_action('switch_theme', 'deactivate_pdf_generation_cron');