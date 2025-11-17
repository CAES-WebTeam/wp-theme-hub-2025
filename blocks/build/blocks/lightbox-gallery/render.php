<?php
/**
 * Server-side rendering for the lightbox gallery block
 */

// Get block attributes
$images = $attributes['images'] ?? [];
$showFilmStrip = $attributes['showFilmStrip'] ?? true;

// Early return if no images
if (empty($images)) {
    return '';
}

// Generate unique ID for this gallery instance
$gallery_id = 'lightbox-gallery-' . wp_unique_id();

$wrapper_attributes = get_block_wrapper_attributes([
    'class' => 'lightbox-gallery lightbox-gallery-parvus'
]);
?>

<div <?php echo $wrapper_attributes; ?>>
    <div class="gallery-trigger">
        <!-- Hidden gallery images for Parvus -->
        <div class="parvus-gallery" style="display: none;">
            <?php foreach ($images as $image): ?>
                <a href="<?php echo esc_url($image['url']); ?>" 
                   class="lightbox"
                   <?php if (!empty($image['caption'])): ?>
                   data-caption="<?php echo esc_attr($image['caption']); ?>"
                   <?php endif; ?>>
                    <img src="<?php echo esc_url($image['url']); ?>" 
                         alt="<?php echo esc_attr($image['alt'] ?? ''); ?>">
                </a>
            <?php endforeach; ?>
        </div>
        
        <!-- Visible trigger -->
        <div class="gallery-trigger-visible">
            <img
                src="<?php echo esc_url($images[0]['url']); ?>"
                alt="<?php echo esc_attr($images[0]['alt'] ?? ''); ?>"
                class="gallery-trigger-image" />
            <button
                type="button"
                class="view-gallery-btn"
                aria-label="<?php esc_attr_e('Open photo gallery lightbox', 'lightbox-gallery'); ?>">
                <span class="view-gallery-text"><?php esc_html_e('View Gallery', 'lightbox-gallery'); ?></span>
            </button>
        </div>
    </div>
</div>