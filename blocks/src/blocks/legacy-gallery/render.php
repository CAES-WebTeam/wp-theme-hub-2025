<?php
/**
 * Accessible Legacy Gallery Block Render
 * Displays images from ACF legacy_gallery field with filmstrip navigation
 */

// Get the current post ID
$post_id = get_the_ID();

// Get the ACF repeater field
$legacy_gallery = get_field('legacy_gallery', $post_id);

// Check if there's a leading image in the post content (block editor compatible)
$post_content = get_post_field('post_content', $post_id);
$has_leading_image = false;

if (!empty($post_content)) {
    // Parse blocks from the post content
    $blocks = parse_blocks($post_content);
    
    // Find the first non-empty block
    $first_content_block = null;
    foreach ($blocks as $block) {
        // Skip empty blocks and blocks with no blockName
        if (!empty($block['blockName']) && !empty($block['innerHTML'])) {
            $first_content_block = $block;
            break;
        }
    }
    
    // Check if the first content block is an image-related block
    if ($first_content_block) {
        $image_blocks = [
            'core/image',
            'core/gallery', 
            'core/cover',
            'core/media-text'
        ];
        
        $has_leading_image = in_array($first_content_block['blockName'], $image_blocks);
        
        // Check Classic block (freeform) content for leading images
        if (!$has_leading_image && $first_content_block['blockName'] === 'core/freeform') {
            $block_html = trim($first_content_block['innerHTML']);
            $has_leading_image = preg_match('/^\s*(?:<(?:figure|p)[^>]*>)?\s*<img[^>]*>/i', $block_html);
        }
        
        // Also check if it's a paragraph or group block that starts with an image
        if (!$has_leading_image && in_array($first_content_block['blockName'], ['core/paragraph', 'core/group'])) {
            $block_html = trim($first_content_block['innerHTML']);
            $has_leading_image = preg_match('/^\s*<[^>]*>\s*<img[^>]*>/i', $block_html);
        }
    }
}

// Don't display gallery if no images OR if there's a leading image in post content
if (!$legacy_gallery || empty($legacy_gallery) || $has_leading_image) {
    return;
}

$total_images = count($legacy_gallery);
$is_single_image = $total_images === 1;

// Generate unique ID for this gallery instance
$gallery_id = 'legacy-gallery-' . wp_unique_id();

// Build wrapper classes
$wrapper_classes = ['legacy-gallery-block'];
if ($is_single_image) {
    $wrapper_classes[] = 'single-image';
}

$wrapper_attributes = get_block_wrapper_attributes([
    'class' => implode(' ', $wrapper_classes),
    'id' => $gallery_id
]);
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

    <?php if (!$is_single_image): ?>
    <!-- Gallery filmstrip navigation (only show for multiple images) -->
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
            <span data-gallery-sr-current">Showing image 1 of <?php echo $total_images; ?></span>
        </div>
    </nav>
    <?php endif; ?>

    <!-- Additional metadata for screen readers -->
    <div class="gallery-metadata sr-only">
        <?php if ($is_single_image): ?>
            <p>Single gallery image.</p>
        <?php else: ?>
            <p>Image gallery with <?php echo $total_images; ?> images. Use arrow keys or tab to navigate between thumbnails, then press Enter or Space to view the full image.</p>
        <?php endif; ?>
    </div>
</div>