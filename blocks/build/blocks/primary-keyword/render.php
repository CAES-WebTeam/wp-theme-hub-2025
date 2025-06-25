<?php
/**
 * Primary Keyword Block Render Template
 */

// Get block attributes
$show_category_icon = $block['showCategoryIcon'] ?? false;
$enable_links = $block['enableLinks'] ?? true;

// Get the current post ID
$post_id = get_the_ID();

// Get the primary keywords from ACF for this specific post
$primary_keywords = get_field('primary_keywords', $post_id);

// Determine icon based on category
$icon_svg = '';
if ($show_category_icon) {
    $categories = get_the_category($post_id);
    $icon_map = [
        'written' => 'written.svg',
        'audio'   => 'audio.svg',
        'video'   => 'video.svg',
        'gallery' => 'gallery.svg',
    ];

    foreach ($categories as $cat) {
        $slug = $cat->slug;
        if (isset($icon_map[$slug])) {
            $icon_path = get_template_directory() . '/assets/images/' . $icon_map[$slug];
            if (file_exists($icon_path)) {
                $icon_svg = file_get_contents($icon_path);
            }
            break;
        }
    }
}

// Only render if we have keywords OR if we should show an icon
if ((!$primary_keywords || empty($primary_keywords)) && empty($icon_svg)) {
    return;
}

// Get block wrapper attributes
$wrapper_attributes = get_block_wrapper_attributes([
    'class' => 'wp-block-caes-hub-primary-keyword'
]);
?>

<div <?php echo $wrapper_attributes; ?>>
    <div class="primary-keywords-wrapper">
        <?php if (!empty($icon_svg)): ?>
            <span class="primary-keyword-icon" aria-hidden="true">
                <?php echo $icon_svg; ?>
            </span>
        <?php endif; ?>

        <?php if ($primary_keywords && !empty($primary_keywords)): ?>
            <?php foreach ($primary_keywords as $index => $keyword): ?>
                <span class="primary-keyword-item">
                    <?php if ($enable_links): ?>
                        <a href="<?php echo esc_url(get_term_link($keyword)); ?>" 
                           class="primary-keyword-link"
                           rel="tag">
                            <?php echo esc_html($keyword->name); ?>
                        </a>
                    <?php else: ?>
                        <span class="primary-keyword-text">
                            <?php echo esc_html($keyword->name); ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($index < count($primary_keywords) - 1): ?>
                        <span class="primary-keyword-separator">, </span>
                    <?php endif; ?>
                </span>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>