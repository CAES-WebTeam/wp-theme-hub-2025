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

$wrapper_attributes = get_block_wrapper_attributes([
    'class' => 'lightbox-gallery lightbox-gallery-parvus'
]);
?>

<div <?php echo $wrapper_attributes; ?>>
    <div class="gallery-trigger">
        <!-- Hidden gallery links for Parvus -->
        <div class="parvus-gallery" style="display: none;" aria-hidden="true">
            <?php foreach ($images as $image): ?>
                <a href="<?php echo esc_url($image['url']); ?>" 
                   class="lightbox"
                   aria-label="<?php echo esc_attr($image['alt'] ?: __('Gallery image', 'lightbox-gallery')); ?>"
                   <?php if (!empty($image['caption'])): ?>
                   data-caption="<?php echo esc_attr($image['caption']); ?>"
                   <?php endif; ?>></a>
            <?php endforeach; ?>
        </div>
        
        <!-- Visible trigger -->
        <div class="gallery-trigger-visible">
            <?php $trigger_url = $images[0]['sizes']['large']['url'] ?? $images[0]['url']; ?>
            <img
                src="<?php echo esc_url($trigger_url); ?>"
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