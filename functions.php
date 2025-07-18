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
require get_template_directory() . '/inc/events-support.php';
require get_template_directory() . '/inc/publications-support.php';
require get_template_directory() . '/inc/user-support.php';
require get_template_directory() . '/inc/news-support.php';
require get_template_directory() . '/block-variations/index.php';


// Temp include
require get_template_directory() . '/inc/release-date-migration.php';
require get_template_directory() . '/inc/release-date-clear.php';
require get_template_directory() . '/inc/detect-duplicates.php';
// require get_template_directory() . '/inc/import-legacy-slideshows-to-news.php';
require get_template_directory() . '/inc/link-users.php';
// require get_template_directory() . '/inc/tax-renamer.php';
require get_template_directory() . '/inc/bulk-topic-moverr.php';
require get_template_directory() . '/inc/pub-history-update.php';
require get_template_directory() . '/inc/story-association-meta-tools.php';

// Plugin overrides
require get_template_directory() . '/inc/plugin-overrides/relevanssi-live-search.php';