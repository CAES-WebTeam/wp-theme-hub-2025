<?php
/**
 * Server-side rendering for the CAES gallery block
 */

// Get block attributes
$rows = $attributes['rows'] ?? [];
$crop_images = $attributes['cropImages'] ?? false;

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
$all_classes = trim($existing_classes . ' caes-gallery caes-gallery-parvus');

// Replace or add the class attribute
if ($has_class) {
    $wrapper_attributes = str_replace('class="' . $existing_classes . '"', 'class="' . $all_classes . '"', $default_attributes);
} else {
    $wrapper_attributes = $default_attributes . ' class="' . $all_classes . '"';
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
            ?>
            
            <div class="gallery-row gallery-row-<?php echo esc_attr($columns); ?>-cols<?php echo $crop_images ? ' is-cropped' : ''; ?>" 
                 data-columns="<?php echo esc_attr($columns); ?>">
                
                <?php 
                $image_count = count($images);
                $image_position = 0;
                foreach ($images as $image): 
                    $image_position++;
                    
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
                ?>
                    <div class="gallery-item">
                        <a href="<?php echo esc_url($image['url']); ?>" 
                           class="lightbox"
                           aria-label="<?php echo esc_attr($aria_label); ?>"
                           <?php if (!empty($image['caption'])): ?>
                           data-caption="<?php echo esc_attr($image['caption']); ?>"
                           <?php endif; ?>>
                            <img src="<?php echo esc_url($image['url']); ?>" 
                                 alt="<?php echo esc_attr($image['alt'] ?? ''); ?>" />
                        </a>
                    </div>
                <?php endforeach; ?>
                
            </div>
        <?php endforeach; ?>
    </div>
</div>