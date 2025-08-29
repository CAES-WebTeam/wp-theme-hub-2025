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
require get_template_directory() . '/inc/status-unpublish.php';
require get_template_directory() . '/inc/event-import-tool.php';
require get_template_directory() . '/inc/pub-state-issue-set.php';

// Plugin overrides
require get_template_directory() . '/inc/plugin-overrides/relevanssi-search.php';

// Add to functions.php - Debug hand-picked post block performance
add_action('init', function() {
    if (is_admin()) return;
    
    // Monitor block rendering performance
    add_filter('render_block_caes-hub/hand-picked-post', function($block_content, $block) {
        $start_time = microtime(true);
        $start_memory = memory_get_usage();
        
        error_log("Hand-picked post block started on: " . $_SERVER['REQUEST_URI']);
        
        // Let the block render normally
        $result = $block_content;
        
        $execution_time = (microtime(true) - $start_time) * 1000;
        $memory_used = memory_get_usage() - $start_memory;
        
        error_log(sprintf(
            "Hand-picked post block completed: %.2fms, Memory: %s bytes, URL: %s",
            $execution_time,
            number_format($memory_used),
            $_SERVER['REQUEST_URI']
        ));
        
        // Alert for slow blocks
        if ($execution_time > 1000) { // Over 1 second
            error_log("🚨 SLOW BLOCK ALERT: Hand-picked post block took {$execution_time}ms on " . $_SERVER['REQUEST_URI']);
        } elseif ($execution_time > 500) { // Over 500ms
            error_log("⚠️  Slow block warning: Hand-picked post block took {$execution_time}ms on " . $_SERVER['REQUEST_URI']);
        } else {
            error_log("✅ Fast block: Hand-picked post block took {$execution_time}ms on " . $_SERVER['REQUEST_URI']);
        }
        
        return $result;
    }, 10, 2);
    
    // Also monitor slow queries globally
    add_filter('log_query_custom_data', function($query_data, $query) {
        if (isset($query_data['duration']) && $query_data['duration'] > 1000) {
            error_log("🐌 SLOW QUERY: " . $query_data['duration'] . "ms - " . substr($query, 0, 200) . "...");
        }
        return $query_data;
    }, 10, 2);
});

// Monitor page load times globally
add_action('wp_footer', function() {
    if (is_admin()) return;
    
    static $page_start_time;
    if (!$page_start_time && defined('WP_START_TIMESTAMP')) {
        $page_start_time = WP_START_TIMESTAMP;
    } elseif (!$page_start_time) {
        return; // Can't measure if we don't have start time
    }
    
    $total_time = (microtime(true) - $page_start_time) * 1000;
    $memory_peak = memory_get_peak_usage(true);
    
    error_log(sprintf(
        "📊 Page load complete: %.2fms, Peak Memory: %sMB, URL: %s",
        $total_time,
        number_format($memory_peak / 1048576, 2),
        $_SERVER['REQUEST_URI']
    ));
    
    if ($total_time > 5000) {
        error_log("🚨 VERY SLOW PAGE: {$total_time}ms for " . $_SERVER['REQUEST_URI']);
    } elseif ($total_time > 2000) {
        error_log("⚠️  Slow page: {$total_time}ms for " . $_SERVER['REQUEST_URI']);
    }
});