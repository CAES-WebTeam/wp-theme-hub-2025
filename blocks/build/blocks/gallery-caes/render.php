<?php
/**
 * Server-side rendering for the CAES gallery block
 */

// Get block attributes
$rows = $attributes['rows'] ?? [];
$crop_images = $attributes['cropImages'] ?? false;
$show_captions = $attributes['showCaptions'] ?? false;
$caption_text_color = $attributes['captionTextColor'] ?? '#ffffff';
$caption_bg_color = $attributes['captionBackgroundColor'] ?? 'rgba(0, 0, 0, 0.7)';
$use_thumbnail_trigger = $attributes['useThumbnailTrigger'] ?? false;

// Early return if no rows
if (empty($rows)) {
    return '';
}

// Collect all images from all rows
$all_images = [];
foreach ($rows as $row) {
    if (!empty($row['images'])) {
        $all_images = array_merge($all_images, $row['images']);
    }
}

// Early return if no images
if (empty($all_images)) {
    return '';
}

// Generate unique ID for this gallery instance
$gallery_id = 'caes-gallery-' . wp_unique_id();

// Get the default wrapper attributes (includes WordPress-added classes and styles)
$default_attributes = get_block_wrapper_attributes(['id' => $gallery_id]);

// Parse the existing class attribute if it exists
$has_class = preg_match('/class="([^"]*)"/', $default_attributes, $matches);
$existing_classes = $has_class ? $matches[1] : '';

// Add our custom classes
$gallery_classes = 'caes-gallery caes-gallery-parvus';
if ($show_captions && !$use_thumbnail_trigger) {
    $gallery_classes .= ' has-caption-overlays';
}
if ($use_thumbnail_trigger) {
    $gallery_classes .= ' has-thumbnail-trigger';
}
$all_classes = trim($existing_classes . ' ' . $gallery_classes);

// Replace or add the class attribute
if ($has_class) {
    $wrapper_attributes = str_replace('class="' . $existing_classes . '"', 'class="' . $all_classes . '"', $default_attributes);
} else {
    $wrapper_attributes = $default_attributes . ' class="' . $all_classes . '"';
}

// Build inline styles for caption colors if captions are enabled
$caption_styles = '';
if ($show_captions && !$use_thumbnail_trigger) {
    $caption_styles = sprintf(
        '--caes-caption-text-color: %s; --caes-caption-bg-color: %s;',
        esc_attr($caption_text_color),
        esc_attr($caption_bg_color)
    );
    
    // Add style attribute to wrapper
    if (strpos($wrapper_attributes, 'style="') !== false) {
        $wrapper_attributes = preg_replace('/style="([^"]*)"/', 'style="$1 ' . $caption_styles . '"', $wrapper_attributes);
    } else {
        $wrapper_attributes .= ' style="' . $caption_styles . '"';
    }
}

// 1x1 transparent placeholder (for hidden gallery links)
$placeholder = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

/**
 * Helper function to build srcset for an image
 */
function caes_gallery_build_srcset($image) {
    // Get URLs (with protocol fix)
    $full_url = set_url_scheme($image['url']);
    $large_url = set_url_scheme($image['sizes']['large']['url'] ?? $image['url']);
    $medium_large_url = set_url_scheme($image['sizes']['medium_large']['url'] ?? $large_url);
    $medium_url = set_url_scheme($image['sizes']['medium']['url'] ?? $medium_large_url);
    
    // Get actual widths
    $full_width = $image['width'] ?? 1600;
    $large_width = $image['sizes']['large']['width'] ?? $full_width;
    $medium_large_width = $image['sizes']['medium_large']['width'] ?? $large_width;
    $medium_width = $image['sizes']['medium']['width'] ?? $medium_large_width;
    
    // Build srcset with actual widths (avoid duplicates)
    $srcset_parts = [];
    $srcset_parts[] = esc_url($medium_url) . ' ' . esc_attr($medium_width) . 'w';
    if ($medium_large_url !== $medium_url) {
        $srcset_parts[] = esc_url($medium_large_url) . ' ' . esc_attr($medium_large_width) . 'w';
    }
    if ($large_url !== $medium_large_url) {
        $srcset_parts[] = esc_url($large_url) . ' ' . esc_attr($large_width) . 'w';
    }
    $srcset_parts[] = esc_url($full_url) . ' ' . esc_attr($full_width) . 'w';
    
    return implode(', ', $srcset_parts);
}
?>

<div <?php echo $wrapper_attributes; ?>>
    <?php if ($use_thumbnail_trigger): ?>
        <!-- Thumbnail Trigger Mode -->
        <div class="gallery-trigger">
            <!-- Hidden gallery links for Parvus -->
            <div class="parvus-gallery" style="display: none;" aria-hidden="true">
                <?php 
                $total_images = count($all_images);
                foreach ($all_images as $index => $image): 
                    $full_url = set_url_scheme($image['url']);
                    $srcset = caes_gallery_build_srcset($image);
                ?>
                    <a href="<?php echo esc_url($full_url); ?>" 
                       class="lightbox"
                       data-srcset="<?php echo $srcset; ?>"
                       data-sizes="(max-width: 75em) 100vw, 75em"
                       <?php if (!empty($image['caption'])): ?>
                       data-caption="<?php echo esc_attr($image['caption']); ?>"
                       <?php endif; ?>>
                        <img src="<?php echo esc_attr($placeholder); ?>" 
                             alt="<?php echo esc_attr($image['alt'] ?? ''); ?>">
                    </a>
                <?php endforeach; ?>
            </div>
            
            <!-- Visible trigger -->
            <div class="gallery-trigger-visible">
                <?php $trigger_url = set_url_scheme($all_images[0]['sizes']['large']['url'] ?? $all_images[0]['url']); ?>
                <img
                    src="<?php echo esc_url($trigger_url); ?>"
                    alt="<?php echo esc_attr($all_images[0]['alt'] ?? ''); ?>"
                    class="gallery-trigger-image"
                    loading="lazy"
                    decoding="async" />
                <button
                    type="button"
                    class="view-gallery-btn"
                    aria-label="<?php esc_attr_e('Open photo gallery lightbox', 'caes-gallery'); ?>">
                    <span class="view-gallery-text"><?php esc_html_e('View Gallery', 'caes-gallery'); ?></span>
                </button>
            </div>
        </div>
    <?php else: ?>
        <!-- Standard Grid Mode -->
        <div class="parvus-gallery">
            <?php foreach ($rows as $row_index => $row): ?>
                <?php 
                $columns = $row['columns'] ?? 3;
                $images = $row['images'] ?? [];
                
                // Skip empty rows
                if (empty($images)) {
                    continue;
                }
                
                $row_classes = 'gallery-row gallery-row-' . esc_attr($columns) . '-cols';
                if ($crop_images) {
                    $row_classes .= ' is-cropped';
                }
                if ($show_captions) {
                    $row_classes .= ' has-captions';
                }
                ?>
                
                <div class="<?php echo esc_attr($row_classes); ?>" 
                     data-columns="<?php echo esc_attr($columns); ?>">
                    
                    <?php 
                    $image_count = count($images);
                    $image_position = 0;
                    foreach ($images as $image): 
                        $image_position++;
                        
                        $full_url = set_url_scheme($image['url']);
                        $large_url = set_url_scheme($image['sizes']['large']['url'] ?? $image['url']);
                        $srcset = caes_gallery_build_srcset($image);
                        
                        // Use large for display thumbnail
                        $display_url = $large_url;
                        
                        // Build aria-label with context
                        $aria_label = sprintf(
                            'View image %d of %d in gallery',
                            $image_position,
                            $image_count
                        );
                        
                        // Add alt text or caption to aria-label if available
                        if (!empty($image['alt'])) {
                            $aria_label .= ': ' . $image['alt'];
                        } elseif (!empty($image['caption'])) {
                            $aria_label .= ': ' . wp_strip_all_tags($image['caption']);
                        }
                        
                        $has_caption = $show_captions && !empty($image['caption']);
                    ?>
                        <div class="gallery-item<?php echo $has_caption ? ' has-caption' : ''; ?>">
                            <a href="<?php echo esc_url($full_url); ?>" 
                               class="lightbox"
                               aria-label="<?php echo esc_attr($aria_label); ?>"
                               data-srcset="<?php echo $srcset; ?>"
                               data-sizes="(max-width: 75em) 100vw, 75em"
                               <?php if (!empty($image['caption'])): ?>
                               data-caption="<?php echo esc_attr($image['caption']); ?>"
                               <?php endif; ?>>
                                <img src="<?php echo esc_url($display_url); ?>" 
                                     alt="<?php echo esc_attr($image['alt'] ?? ''); ?>" 
                                     loading="lazy"
                                     decoding="async" />
                                <?php if ($has_caption): ?>
                                <figcaption class="gallery-caption-overlay">
                                    <?php echo esc_html($image['caption']); ?>
                                </figcaption>
                                <?php endif; ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                    
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>