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

/**
 * Add this to functions.php to debug the alt text issue
 * This will log exactly what happens to alt text during save
 */

// 1. Log every time alt text is accessed or modified
add_action('updated_post_meta', 'log_alt_text_changes', 10, 4);
add_action('added_post_meta', 'log_alt_text_changes', 10, 4);

function log_alt_text_changes($meta_id, $object_id, $meta_key, $meta_value) {
    if ($meta_key === '_wp_attachment_image_alt') {
        $post = get_post($object_id);
        $current_user = wp_get_current_user();
        
        error_log("=== ALT TEXT CHANGE ===");
        error_log("Attachment ID: $object_id");
        error_log("Post type: " . ($post ? $post->post_type : 'unknown'));
        error_log("New alt text: '$meta_value'");
        error_log("Current user: " . $current_user->display_name . " (ID: " . $current_user->ID . ")");
        error_log("Backtrace: " . wp_debug_backtrace_summary());
        error_log("========================");
    }
}

// 2. Hook into ACF save process specifically
add_action('acf/save_post', 'debug_acf_save_attachments', 5);

function debug_acf_save_attachments($post_id) {
    // Get the ACF image field value
    $image_field = get_field('featured_image', $post_id); // Adjust field name if needed
    
    if ($image_field) {
        $attachment_id = is_array($image_field) ? $image_field['ID'] : $image_field;
        $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        
        error_log("=== ACF SAVE DEBUG ===");
        error_log("Event ID: $post_id");
        error_log("Attachment ID: $attachment_id");
        error_log("Alt text at ACF save: '$alt_text'");
        error_log("ACF field data: " . print_r($image_field, true));
        error_log("======================");
    }
}

// 3. JavaScript debugging - add this to admin footer
add_action('admin_footer', 'add_alt_text_js_debug');

function add_alt_text_js_debug() {
    global $typenow, $pagenow;
    
    if ($typenow === 'events' && in_array($pagenow, ['post.php', 'post-new.php'])) {
        ?>
        <script>
        jQuery(document).ready(function($) {
            console.log('Alt text debugging loaded');
            
            // Monitor ACF image field changes
            if (typeof acf !== 'undefined') {
                // Log when ACF fields are loaded
                acf.addAction('ready', function() {
                    console.log('ACF ready - checking image fields');
                    
                    // Find ACF image fields
                    $('[data-type="image"]').each(function() {
                        console.log('Found ACF image field:', this);
                    });
                });
                
                // Monitor field changes
                acf.addAction('change', function(e) {
                    console.log('ACF field changed:', e);
                });
                
                // Monitor before form submit
                $('#post').on('submit', function() {
                    console.log('=== FORM SUBMITTING ===');
                    
                    // Check ACF image field data
                    $('[data-name="featured_image"]').each(function() { // Adjust field name
                        var fieldData = acf.getField($(this)).val();
                        console.log('Image field data on submit:', fieldData);
                        
                        if (fieldData && fieldData.alt) {
                            console.log('Alt text on submit:', fieldData.alt);
                        }
                    });
                });
            }
            
            // Monitor WordPress media modal
            if (typeof wp !== 'undefined' && wp.media) {
                // Hook into media modal events
                wp.media.view.Modal.prototype.on = function(event, callback) {
                    console.log('Media modal event:', event);
                    return wp.Backbone.View.prototype.on.apply(this, arguments);
                };
            }
        });
        </script>
        <?php
    }
}

// 4. Check if ACF is overriding attachment data
add_filter('acf/update_value/type=image', 'debug_acf_image_update', 10, 3);

function debug_acf_image_update($value, $post_id, $field) {
    error_log("=== ACF IMAGE UPDATE ===");
    error_log("Field: " . $field['name']);
    error_log("Post ID: $post_id");
    error_log("Value: " . print_r($value, true));
    
    // If it's an attachment ID, check its alt text
    if (is_numeric($value)) {
        $alt_text = get_post_meta($value, '_wp_attachment_image_alt', true);
        error_log("Attachment alt text: '$alt_text'");
    }
    
    error_log("========================");
    
    return $value;
}

add_action('admin_footer', 'debug_alt_text_clearing');

function debug_alt_text_clearing() {
    global $typenow, $pagenow;
    
    if ($typenow === 'events' && in_array($pagenow, ['post.php', 'post-new.php'])) {
        ?>
        <script>
        jQuery(document).ready(function($) {
            console.log('Alt text debugging active');
            
            // Monitor when media modals open
            $(document).on('DOMNodeInserted', function(e) {
                if ($(e.target).hasClass('media-modal') || $(e.target).find('.media-modal').length) {
                    console.log('Media modal detected, setting up field monitoring');
                    
                    setTimeout(function() {
                        // Find alt text field
                        var $altField = $('.attachment-details input[data-setting="alt"], input[name="attachments[alt]"], .setting.alt input');
                        
                        if ($altField.length) {
                            console.log('Found alt field:', $altField);
                            console.log('Initial value:', $altField.val());
                            
                            // Monitor changes to this field
                            $altField.on('change input', function() {
                                console.log('Alt field changed to:', $(this).val());
                            });
                            
                            // Watch for field being cleared
                            var originalVal = $altField.val();
                            var checkCount = 0;
                            var checkInterval = setInterval(function() {
                                var currentVal = $altField.val();
                                checkCount++;
                                
                                if (currentVal !== originalVal) {
                                    console.log('ALT TEXT CHANGED! From "' + originalVal + '" to "' + currentVal + '" after ' + (checkCount * 100) + 'ms');
                                    clearInterval(checkInterval);
                                    
                                    // Try to trace what cleared it
                                    console.trace('Alt text was cleared');
                                }
                                
                                if (checkCount > 50) { // Stop after 5 seconds
                                    clearInterval(checkInterval);
                                }
                            }, 100);
                        }
                    }, 500);
                }
            });
            
            // Also monitor ACF image modals
            if (typeof acf !== 'undefined') {
                acf.addAction('ready', function() {
                    console.log('ACF ready - monitoring image fields');
                });
                
                // Monitor when ACF image modal opens
                $(document).on('click', '.acf-image-uploader .edit-image', function() {
                    console.log('ACF image edit clicked');
                    
                    setTimeout(function() {
                        var $modal = $('.media-modal:visible');
                        if ($modal.length) {
                            console.log('ACF modal is open, monitoring alt field');
                            
                            var $altField = $modal.find('input[data-setting="alt"]');
                            if ($altField.length) {
                                console.log('ACF modal alt field found:', $altField.val());
                                
                                // Watch this field too
                                setTimeout(function() {
                                    if ($altField.val() === '') {
                                        console.log('ACF modal alt field was cleared!');
                                        console.trace();
                                    }
                                }, 1000);
                            }
                        }
                    }, 1000);
                });
            }
        });
        </script>
        <?php
    }
}