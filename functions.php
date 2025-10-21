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
require get_template_directory() . '/inc/plugin-overrides/relevanssi-search.php';
require get_template_directory() . '/inc/plugin-overrides/yoast-schema.php';
require get_template_directory() . '/inc/blocks.php';
require get_template_directory() . '/inc/acf.php';
require get_template_directory() . '/inc/caes-tools.php';
require get_template_directory() . '/inc/publications-support.php';
require get_template_directory() . '/inc/user-support.php';
require get_template_directory() . '/inc/news-support.php';
require get_template_directory() . '/block-variations/index.php';
require get_template_directory() . '/inc/custom-rewrites.php';
require get_template_directory() . '/inc/rss-support.php';
// require get_template_directory() . '/inc/multisite-script-protect.php';

// Publications PDF generation
require get_template_directory() . '/inc/publications-pdf/publications-pdf-mpdf.php';
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


add_action('transition_post_status', 'log_post_status_change_detailed', 10, 3);
function log_post_status_change_detailed($new_status, $old_status, $post) {
    if ($old_status != $new_status) {
        $log_data = array();
        
        // Basic info
        $log_data['post_id'] = $post->ID;
        $log_data['post_title'] = $post->post_title;
        $log_data['status_change'] = "{$old_status} -> {$new_status}";
        $log_data['timestamp'] = current_time('mysql');
        
        // What triggered it?
        $log_data['context'] = array();
        
        if (wp_doing_cron()) {
            $log_data['context'][] = 'WP_CRON';
        }
        
        if (wp_doing_ajax()) {
            $log_data['context'][] = 'AJAX';
            $log_data['ajax_action'] = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'unknown';
        }
        
        if (defined('REST_REQUEST') && REST_REQUEST) {
            $log_data['context'][] = 'REST_API';
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            $log_data['context'][] = 'AUTOSAVE';
        }
        
        // Current user
        $current_user = wp_get_current_user();
        $log_data['user'] = $current_user->ID ? $current_user->user_login : 'No user (system/cron)';
        
        // Current action/filter
        $log_data['current_action'] = current_action();
        
        // Get the call stack to see what function/plugin triggered this
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
        $log_data['call_stack'] = array();
        
        foreach ($backtrace as $i => $trace) {
            if (isset($trace['file']) && isset($trace['function'])) {
                // Make file path relative to WP root for readability
                $file = str_replace(ABSPATH, '', $trace['file']);
                $log_data['call_stack'][] = sprintf(
                    "#%d %s() in %s:%d",
                    $i,
                    $trace['function'],
                    $file,
                    $trace['line']
                );
                
                // Only log first 10 levels to keep it manageable
                if ($i >= 9) break;
            }
        }
        
        // Check if this is related to duplication
        $duplicate_meta = get_post_meta($post->ID, '_dp_original', true); // Common duplicate meta key
        if ($duplicate_meta) {
            $log_data['is_duplicate'] = 'YES - Original post ID: ' . $duplicate_meta;
        }
        
        // Request URI (helpful for tracking)
        if (isset($_SERVER['REQUEST_URI'])) {
            $log_data['request_uri'] = $_SERVER['REQUEST_URI'];
        }
        
        // Format and write to log
        $log_message = "\n=== POST STATUS CHANGE ===" . 
                      "\nPost: #{$log_data['post_id']} - {$log_data['post_title']}" .
                      "\nChange: {$log_data['status_change']}" .
                      "\nTime: {$log_data['timestamp']}" .
                      "\nUser: {$log_data['user']}" .
                      "\nContext: " . (empty($log_data['context']) ? 'Normal request' : implode(', ', $log_data['context']));
        
        if (isset($log_data['ajax_action'])) {
            $log_message .= "\nAJAX Action: {$log_data['ajax_action']}";
        }
        
        if (isset($log_data['is_duplicate'])) {
            $log_message .= "\nDuplicate Info: {$log_data['is_duplicate']}";
        }
        
        if (isset($log_data['request_uri'])) {
            $log_message .= "\nRequest URI: {$log_data['request_uri']}";
        }
        
        $log_message .= "\nCurrent Action: {$log_data['current_action']}" .
                       "\n\nCall Stack:";
        
        foreach ($log_data['call_stack'] as $trace_line) {
            $log_message .= "\n  " . $trace_line;
        }
        
        $log_message .= "\n=========================\n";
        
        error_log($log_message);
    }
}