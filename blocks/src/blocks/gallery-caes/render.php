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

// Early return if no rows
if (empty($rows)) {
    return '';
}

// Check if any row has images
$has_images = false;
foreach ($rows as $row) {
    if (!empty($row['images'])) {
        $has_images = true;
        break;
    }
}

if (!$has_images) {
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
if ($show_captions) {
    $gallery_classes .= ' has-caption-overlays';
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
if ($show_captions) {
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
?>

<div <?php echo $wrapper_attributes; ?>>
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
                    
                    // Get URLs for different sizes (with protocol fix)
                    $full_url = set_url_scheme($image['url']);
                    $large_url = set_url_scheme($image['sizes']['large']['url'] ?? $image['url']);
                    $medium_large_url = set_url_scheme($image['sizes']['medium_large']['url'] ?? $large_url);
                    $medium_url = set_url_scheme($image['sizes']['medium']['url'] ?? $medium_large_url);
                    
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
                           data-srcset="<?php echo esc_url($medium_url); ?> 480w, <?php echo esc_url($medium_large_url); ?> 768w, <?php echo esc_url($large_url); ?> 1024w, <?php echo esc_url($full_url); ?> 1600w"
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
</div>