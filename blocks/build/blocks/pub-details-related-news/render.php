<?php

// Get the current post ID
$post_id = get_the_ID();

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

// Get the related news ACF fields
$related_news = get_field('related_news', $post_id);

// Check if related news is set, otherwise fallback to latest four posts
if (!$related_news) {
    // Query the latest 4 posts
    $args = array(
        'posts_per_page' => 4,
        'post__not_in' => array($post_id), // Exclude the current post
        'orderby' => 'date', // Order by date
        'order' => 'DESC' // Most recent first
    );
    $latest_posts = new WP_Query($args);
    if ($latest_posts->have_posts()) {
        // Set $related_news to the latest posts
        $related_news = $latest_posts->posts;
    }
}

// Check if related news (or fallback posts) are set
if ($related_news) {
    echo '<div ' . $attrs . '>';
    echo '<h2 class="wp-block-heading is-style-caes-hub-section-heading">Related News</h2>';
    echo '<div class="caes-hub-post-list-grid faux-caes-hub-post-list-grid">';
    
    // Count the number of related news items
    $post_count = count($related_news);

    // Set the ul class based on the number of posts
    if ($post_count <= 2) {
        echo '<ul>';
    } elseif ($post_count == 3) {
        echo '<ul class="caes-hub-post-column-3">';
    } else {
        echo '<ul class="caes-hub-post-column-4">';
    }

    // Loop through related news
    foreach ($related_news as $item) {
        echo '<li><div class="caes-hub-post-list-grid-item">';

        $item_id = is_object($item) ? $item->ID : $item;
        $title = get_the_title($item_id);
        $featured_image = get_the_post_thumbnail($item_id, 'large');
        $link = get_permalink($item_id);
        echo '<figure class="caes-hub-post-list-img-container">' . $featured_image . '</figure>';
        echo '<div class="wp-block-group is-layout-flow caes-hub-post-list-grid-info">';
        
        // Brand block
        $block_name = 'caes-hub/content-brand';
        $block_attributes = array(
            'customWidth' => '110px',
        );
        echo render_block( array(
            'blockName' => $block_name,
            'attrs'     => $block_attributes
        ) );
        
        echo '<h2 class="caes-hub-post-list-grid-title has-text-color has-contrast-color has-medium-font-size">';
        echo '<a href="' . esc_url($link) . '" class="caes-hub-post-list-grid-title">' . esc_html($title) . '</a>';
        echo '</h2>';
        echo '</div>';

        echo '</div></li>';
    }
    echo '</ul>';
    echo '</div>';
    echo '</div>';
}
?>
