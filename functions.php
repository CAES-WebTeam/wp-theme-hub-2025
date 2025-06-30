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


/**
 * Debug function to log ACF legacy gallery structure
 * Add this to your functions.php file
 */
function debug_legacy_gallery_structure() {
    // Only run on singular post pages (posts, pages, custom post types)
    if (!is_singular()) {
        return;
    }
    
    // Get the current post ID
    $post_id = get_the_ID();
    
    // Get the ACF repeater field
    $legacy_gallery = get_field('legacy_gallery', $post_id);
    
    // Log the structure
    if ($legacy_gallery) {
        error_log('=== LEGACY GALLERY DEBUG ===');
        error_log('Post ID: ' . $post_id);
        error_log('Post Title: ' . get_the_title($post_id));
        error_log('Gallery Count: ' . count($legacy_gallery));
        error_log('Full Structure: ' . print_r($legacy_gallery, true));
        
        // Log each item individually for clarity
        foreach ($legacy_gallery as $index => $item) {
            error_log("--- Gallery Item {$index} ---");
            error_log('Item structure: ' . print_r($item, true));
            
            // If there's an image, log its details
            if (isset($item['image'])) {
                error_log('Image data type: ' . gettype($item['image']));
                if (is_array($item['image'])) {
                    error_log('Image ID: ' . ($item['image']['ID'] ?? 'not set'));
                    error_log('Image URL: ' . ($item['image']['url'] ?? 'not set'));
                    error_log('Image sizes available: ' . print_r(array_keys($item['image']['sizes'] ?? []), true));
                } else {
                    error_log('Image value: ' . $item['image']);
                }
            }
            
            // Log caption
            if (isset($item['caption'])) {
                error_log('Caption: ' . $item['caption']);
            }
        }
        error_log('=== END LEGACY GALLERY DEBUG ===');
    } else {
        error_log('Legacy gallery field not found or empty for post ID: ' . $post_id);
    }
}

// Hook it to template_redirect so it runs when viewing posts
add_action('template_redirect', 'debug_legacy_gallery_structure');