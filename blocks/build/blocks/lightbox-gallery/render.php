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

// Prepare images data for JavaScript
$images_data = [];
foreach ($images as $image) {
    $images_data[] = [
        'id' => $image['id'],
        'url' => $image['url'],
        'alt' => $image['alt'] ?? '',
        'caption' => $image['caption'] ?? '',
        'thumbnail' => isset($image['sizes']['thumbnail']['url']) ? $image['sizes']['thumbnail']['url'] : $image['url'],
        'medium' => isset($image['sizes']['medium']['url']) ? $image['sizes']['medium']['url'] : $image['url']
    ];
}

// Enqueue the view script
wp_enqueue_script('lightbox-gallery-view');

// Add images data directly to the HTML as a data attribute
$images_json = wp_json_encode($images_data);

$wrapper_attributes = get_block_wrapper_attributes([
    'class' => 'lightbox-gallery',
    'id' => $gallery_id,
    'data-images' => esc_attr($images_json)
]);
?>

<div <?php echo $wrapper_attributes; ?>>
    <!-- Simplified Single Image Trigger -->
    <div class="gallery-trigger">
        <img
            src="<?php echo esc_url($images_data[0]['url']); ?>"
            alt=""
            class="gallery-trigger-image" />
        <button
            type="button"
            class="view-gallery-btn"
            aria-label="<?php esc_attr_e('Open photo gallery lightbox', 'lightbox-gallery'); ?>">
            <span class="view-gallery-text"><?php esc_html_e('View Gallery', 'lightbox-gallery'); ?></span>
        </button>
    </div>

    <!-- Lightbox Modal -->
    <div
        class="lightbox-modal"
        role="dialog"
        aria-modal="true"
        aria-hidden="true"
        aria-labelledby="<?php echo esc_attr($gallery_id); ?>-title"
        aria-describedby="<?php echo esc_attr($gallery_id); ?>-description">
        <div class="lightbox-backdrop"></div>
        <div class="lightbox-content">
            <div class="lightbox-header">
                <h2 id="<?php echo esc_attr($gallery_id); ?>-title" class="lightbox-title sr-only">
                    <?php esc_html_e('Image Gallery', 'lightbox-gallery'); ?>
                </h2>
                <button
                    type="button"
                    class="lightbox-close"
                    aria-label="<?php esc_attr_e('Close gallery', 'lightbox-gallery'); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="lightbox-body">
                <div class="lightbox-image-container">
                    <img
                        src=""
                        alt=""
                        class="lightbox-image"
                        id="<?php echo esc_attr($gallery_id); ?>-current-image" />
                    <div
                        id="<?php echo esc_attr($gallery_id); ?>-description"
                        class="lightbox-caption"
                        aria-live="polite"></div>

                    <?php if (count($images) > 1): ?>
                        <div class="lightbox-navigation">
                            <button
                                type="button"
                                class="nav-btn prev-btn"
                                aria-label="<?php esc_attr_e('Previous image', 'lightbox-gallery'); ?>">
                                <span aria-hidden="true">&#8249;</span>
                                <span class="sr-only"><?php esc_html_e('Previous', 'lightbox-gallery'); ?></span>
                            </button>
                            <button
                                type="button"
                                class="nav-btn next-btn"
                                aria-label="<?php esc_attr_e('Next image', 'lightbox-gallery'); ?>">
                                <span aria-hidden="true">&#8250;</span>
                                <span class="sr-only"><?php esc_html_e('Next', 'lightbox-gallery'); ?></span>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>