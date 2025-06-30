<?php
/**
 * Accessible Legacy Gallery Block Render
 * Displays images from ACF legacy_gallery field with filmstrip navigation
 */

// Get the current post ID
$post_id = get_the_ID();

// Get the ACF repeater field
$legacy_gallery = get_field('legacy_gallery', $post_id);

// Generate unique ID for this gallery instance
$gallery_id = 'legacy-gallery-' . wp_unique_id();

$wrapper_attributes = get_block_wrapper_attributes([
    'class' => 'legacy-gallery-block',
    'id' => $gallery_id
]);

if (!$legacy_gallery || empty($legacy_gallery)) {
    return;
}

$total_images = count($legacy_gallery);
?>

<div <?php echo $wrapper_attributes; ?> style="--texture-url: url('<?php echo get_template_directory_uri(); ?>/assets/images/texture.jpg');">
    <!-- Gallery main display area -->
    <div class="gallery-main" role="img" aria-live="polite">
        <figure class="gallery-figure">
            <?php 
            $first_image = $legacy_gallery[0]['image'];
            $first_caption = $legacy_gallery[0]['caption'] ?? '';
            ?>
            <img 
                class="gallery-main-image"
                src="<?php echo esc_url($first_image['url']); ?>"
                alt="<?php echo esc_attr($first_image['alt'] ?: 'Gallery image 1 of ' . $total_images); ?>"
                data-gallery-main
                width="<?php echo esc_attr($first_image['width']); ?>"
                height="<?php echo esc_attr($first_image['height']); ?>"
            />
            
            <?php if ($first_caption): ?>
                <figcaption class="gallery-caption" data-gallery-caption>
                    <?php echo wp_kses_post($first_caption); ?>
                </figcaption>
            <?php else: ?>
                <figcaption class="gallery-caption sr-only" data-gallery-caption>
                    Image <?php echo 1; ?> of <?php echo $total_images; ?>
                </figcaption>
            <?php endif; ?>
        </figure>
    </div>

    <!-- Gallery filmstrip navigation -->
    <nav class="gallery-filmstrip" aria-label="Gallery navigation">
        <div class="filmstrip-container">
            <ul class="filmstrip-list" role="tablist" aria-label="Gallery images">
                <?php foreach ($legacy_gallery as $index => $item): ?>
                    <?php 
                    $image = $item['image'];
                    $caption = $item['caption'] ?? '';
                    $is_first = $index === 0;
                    $image_number = $index + 1;
                    ?>
                    <li class="filmstrip-item" role="presentation">
                        <button 
                            class="filmstrip-thumb<?php echo $is_first ? ' active' : ''; ?>"
                            role="tab"
                            aria-selected="<?php echo $is_first ? 'true' : 'false'; ?>"
                            aria-controls="<?php echo $gallery_id; ?>-image"
                            aria-label="Show image <?php echo $image_number; ?> of <?php echo $total_images; ?><?php echo $caption ? ': ' . wp_strip_all_tags($caption) : ''; ?>"
                            data-gallery-thumb="<?php echo $index; ?>"
                            data-image-url="<?php echo esc_url($image['url']); ?>"
                            data-image-alt="<?php echo esc_attr($image['alt'] ?: 'Gallery image ' . $image_number . ' of ' . $total_images); ?>"
                            data-image-width="<?php echo esc_attr($image['width']); ?>"
                            data-image-height="<?php echo esc_attr($image['height']); ?>"
                            data-image-caption="<?php echo esc_attr($caption); ?>"
                            tabindex="<?php echo $is_first ? '0' : '-1'; ?>"
                        >
                            <img 
                                src="<?php echo esc_url($image['sizes']['thumbnail']); ?>"
                                alt=""
                                class="thumb-image"
                                width="150"
                                height="150"
                                loading="lazy"
                            />
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <!-- Screen reader navigation info -->
        <div class="gallery-sr-info sr-only" aria-live="polite" aria-atomic="true">
            <span data-gallery-sr-current>Showing image 1 of <?php echo $total_images; ?></span>
        </div>
    </nav>

    <!-- Additional metadata for screen readers -->
    <div class="gallery-metadata sr-only">
        <p>Image gallery with <?php echo $total_images; ?> <?php echo $total_images === 1 ? 'image' : 'images'; ?>. Use arrow keys or tab to navigate between thumbnails, then press Enter or Space to view the full image.</p>
    </div>
</div>