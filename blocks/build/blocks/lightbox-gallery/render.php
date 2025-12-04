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

// 1x1 transparent placeholder
$placeholder = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
?>

<div <?php echo $wrapper_attributes; ?>>
    <div class="gallery-trigger">
        <!-- Hidden gallery links for Parvus -->
        <div class="parvus-gallery" style="display: none;" aria-hidden="true">
            <?php foreach ($images as $image):
                $medium_url = $image['sizes']['medium_large']['url'] ?? $image['sizes']['large']['url'] ?? $image['url'];
                $large_url = $image['sizes']['large']['url'] ?? $image['url'];
                $full_url = $image['url'];
            ?>
                <a href="<?php echo esc_url($full_url); ?>"
                    class="lightbox"
                    data-srcset="<?php echo esc_url($medium_url); ?> 768w, <?php echo esc_url($large_url); ?> 1024w, <?php echo esc_url($full_url); ?> 1600w"
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