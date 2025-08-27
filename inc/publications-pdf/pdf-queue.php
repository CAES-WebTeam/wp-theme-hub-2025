<?php
/**
 * Handles the PDF generation queue using a custom database table.
 */

// Ensure this file is called by WordPress
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Creates the custom database table for the PDF generation queue.
 */
function create_pdf_generation_queue_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pdf_generation_queue';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        post_id bigint(20) NOT NULL,
        status varchar(20) DEFAULT 'pending' NOT NULL,
        queued_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        completed_at datetime,
        message text,
        PRIMARY KEY (id),
        UNIQUE KEY post_id (post_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
add_action('after_setup_theme', 'create_pdf_generation_queue_table'); // Or 'init' hook

/**
 * Inserts or updates a post in the PDF generation queue.
 * If a post_id is already in the queue, its status is updated to 'pending'.
 *
 * @param int    $post_id The ID of the post.
 * @param string $status  The desired status ('pending', 'processing', 'completed', 'failed').
 * @param string $message An optional message for the queue item.
 * @return bool True on success, false on failure.
 */
function insert_or_update_pdf_queue($post_id, $status = 'pending', $message = '') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pdf_generation_queue';

    // Check if the post_id already exists in the queue
    $existing_item = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table_name WHERE post_id = %d", $post_id));

    $data = array(
        'post_id'    => $post_id,
        'status'     => $status,
        'message'    => $message,
    );
    $format = array('%d', '%s', '%s');

    if ($existing_item) {
        // Update existing item
        $data['queued_at'] = current_time('mysql'); // Reset queue time
        $data['completed_at'] = null; // Clear completion time on re-queue
        $format[] = '%s';
        $format[] = '%s';
        $result = $wpdb->update($table_name, $data, array('id' => $existing_item->id), $format, array('%d'));
    } else {
        // Insert new item
        $result = $wpdb->insert($table_name, $data, $format);
    }

    if ($result === false) {
        error_log('PDF Queue Error: Failed to insert or update post ID ' . $post_id . ' with status ' . $status . ': ' . $wpdb->last_error);
        return false;
    }
    return true;
}

/**
 * Retrieves the next batch of pending PDF generation tasks from the queue.
 *
 * @param int $limit The maximum number of tasks to retrieve.
 * @return array An array of queue objects.
 */
function get_pending_pdf_queue_items($limit = 5) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pdf_generation_queue';

    // Order by queued_at to process older requests first
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE status = 'pending' ORDER BY queued_at ASC LIMIT %d",
        $limit
    ));

    return $items;
}

/**
 * Updates the status of a specific queue item.
 *
 * @param int    $id      The ID of the queue item (from the queue table).
 * @param string $status  The new status.
 * @param string $message Optional message.
 * @return bool True on success, false on failure.
 */
function update_pdf_queue_status($id, $status, $message = '') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pdf_generation_queue';

    $data = array('status' => $status);
    $format = array('%s');

    if (in_array($status, ['completed', 'failed'])) {
        $data['completed_at'] = current_time('mysql');
        $format[] = '%s';
    } else {
        $data['completed_at'] = null; // Clear if status is changed from completed/failed
        $format[] = '%s';
    }

    if (!empty($message)) {
        $data['message'] = $message;
        $format[] = '%s';
    }

    $result = $wpdb->update(
        $table_name,
        $data,
        array('id' => $id),
        $format,
        array('%d')
    );

    if ($result === false) {
        error_log('PDF Queue Error: Failed to update queue item ID ' . $id . ' to status ' . $status . ': ' . $wpdb->last_error);
        return false;
    }
    return true;
}

/**
 * Removes a post from the PDF generation queue.
 *
 * @param int $post_id The ID of the post to remove.
 * @return bool True on successful removal, false otherwise.
 */
function remove_from_pdf_queue($post_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pdf_generation_queue';

    $result = $wpdb->delete(
        $table_name,
        array('post_id' => $post_id),
        array('%d')
    );

    return $result !== false;
}

/**
 * Removes multiple posts from the PDF generation queue.
 *
 * @param array $post_ids An array of post IDs to remove.
 * @return int|false The number of rows deleted, or false on error.
 */
function remove_multiple_from_pdf_queue($post_ids) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pdf_generation_queue';

    if (empty($post_ids)) {
        return 0;
    }

    // Sanitize all IDs to be integers
    $sanitized_ids = array_map('intval', $post_ids);
    $ids_placeholder = implode(',', $sanitized_ids);

    $query = "DELETE FROM {$table_name} WHERE post_id IN ({$ids_placeholder})";
    
    $result = $wpdb->query($query);

    return $result;
}