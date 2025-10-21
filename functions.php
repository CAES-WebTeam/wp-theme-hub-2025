<?php
/*
 *  Author: UGA - CAES OIT, Frankel Agency
 *  URL: hub.caes.uga.edu
 *  Custom functions, support, custom post types and more.
 */

/**
 * Normalize search queries for known short acronyms.
 * Converts variations without hyphens/proper casing to standard format.
 */
function caes_hub_normalize_search_query($query) {
    $query = trim($query);
    
    // Map of search variations to standardized format
    $normalizations = array(
        '4h'  => '4-H',
        '4-h' => '4-H',
        '4 h' => '4-H',
        // Add more as needed
    );
    
    $query_lower = strtolower($query);
    if (isset($normalizations[$query_lower])) {
        return $normalizations[$query_lower];
    }
    
    return $query;
}

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

// Plugin overrides
require get_template_directory() . '/inc/plugin-overrides/relevanssi-search.php';
require get_template_directory() . '/inc/plugin-overrides/yoast-schema.php';
