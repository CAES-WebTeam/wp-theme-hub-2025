<?php
// Primary Keyword Block Render Template
// This code is for a block that displays the primary topic.
// It will now fetch the 'primary_topics' ACF field and display the terms.

// Get block attributes
$show_category_icon = $block['showCategoryIcon'] ?? false;
$enable_links = $block['enableLinks'] ?? true;

// Get the current post ID
$post_id = get_the_ID();

// Get the primary topics from ACF for this specific post
// Changed field name from 'primary_keywords' to 'primary_topics'
$primary_topics = get_field('primary_topics', $post_id);

// Determine icon based on post type first, then category
$icon_svg = '';
$icon_is_png = false;
$icon_url = '';
$post_type = get_post_type($post_id); // Define post_type here for broader scope

if ($show_category_icon) {
    
    // Check if post type is publications first
    if ($post_type === 'publications') {
        $icon_path = get_template_directory() . '/assets/images/expert-checkmark.png';
        if (file_exists($icon_path)) {
            $icon_is_png = true;
            $icon_url = get_template_directory_uri() . '/assets/images/expert-checkmark.png';
        }
    } else {
        // Use existing category-based logic for other post types
        $categories = get_the_category($post_id);
        $icon_map = [
            'read' => 'written.svg',
            'listen'   => 'audio.svg',
            'watch'   => 'video.svg',
            'look' => 'gallery.svg',
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
}

// Only render if we have topics OR if we should show an icon OR if it's a publication with no primary topics
if ((!$primary_topics || empty($primary_topics)) && empty($icon_svg) && !$icon_is_png && !($post_type === 'publications')) {
    return; // Original return for non-publication types without content
}

// Get block wrapper attributes
$wrapper_attributes = get_block_wrapper_attributes([
    'class' => 'wp-block-caes-hub-primary-topic' // Changed class name for consistency
]);
?>

<div <?php echo $wrapper_attributes; ?>>
    <div class="primary-topics-wrapper">
        <?php if (!empty($icon_svg) || $icon_is_png): ?>
            <span class="primary-topic-icon" aria-hidden="true">
                <?php if ($icon_is_png): ?>
                    <img src="<?php echo esc_url($icon_url); ?>" alt="" />
                <?php else: ?>
                    <?php echo $icon_svg; ?>
                <?php endif; ?>
            </span>
        <?php endif; ?>

        <?php if ($primary_topics && !empty($primary_topics)): ?>
            <?php foreach ($primary_topics as $index => $topic): ?>
                <span class="primary-topic-item">
                    <?php if ($enable_links): ?>
                        <a href="<?php echo esc_url(get_term_link($topic)); ?>"
                           class="primary-topic-link"
                           rel="tag">
                            <?php echo esc_html($topic->name); ?>
                        </a>
                    <?php else: ?>
                        <span class="primary-topic-text">
                            <?php echo esc_html($topic->name); ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($index < count($primary_topics) - 1): ?>
                        <span class="primary-topic-separator">, </span>
                    <?php endif; ?>
                </span>
            <?php endforeach; ?>
        <?php elseif ($post_type === 'publications'): ?>
            <span class="primary-topic-text">
                Expert Resource
            </span>
        <?php endif; ?>
    </div>
</div>