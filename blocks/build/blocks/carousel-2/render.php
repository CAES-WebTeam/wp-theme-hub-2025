<?php
/**
 * Carousel Block Render Template
 * This code displays posts in a carousel format, fetching a selected category.
 */

// Get attributes with proper defaults to match frontend
$handSelectPosts = $attributes['handSelectPosts'];
$selectedPosts = $attributes['selectedPosts'];
$postType = $attributes['postType'] ?? 'post';
$orderBy = $attributes['orderBy'] ?? 'date_desc';
$categories = $attributes['categories'] ?? [];
$numberOfPosts = $attributes['numberOfPosts'] ?? 5;

// Get today's date
$today = new DateTime();
$timestamp = $today->getTimestamp();

$formattedDateWithAPA = format_date_apa_style($timestamp);

// Initialize selected posts based on hand selection or feed
if ($handSelectPosts) {
    // If hand selecting posts, use the selectedPosts attribute directly
    $selectedPosts = !empty($selectedPosts) ? array_map('intval', $selectedPosts) : [];
} else {
    // If not hand selecting, build query args

    $orderParts = explode('_', $orderBy);
    $orderKey = $orderParts[0] ?? 'date'; // Default to 'date' if missing
    $orderDirection = isset($orderParts[1]) ? strtoupper($orderParts[1]) : 'DESC'; // Default to 'DESC' if missing

    $queryArgs = array(
        'post_type' => !empty($categories) ? array('post', 'publications', 'shorthand_story') : $postType,
        'posts_per_page' => $numberOfPosts,
        'orderby' => $orderKey, // 'date' or 'title'
        'order' => $orderDirection, // 'ASC' or 'DESC'
    );

    // Handle category filtering
    if (!empty($categories)) {
        $queryArgs['category__in'] = $categories;
        $queryArgs['post_type'] = array('post', 'publications', 'shorthand_story');
    }

    // Execute the query
    $query = new WP_Query($queryArgs);
    $selectedPosts = wp_list_pluck($query->posts, 'ID'); // Get the post IDs from the query
}
?>

<div class="wp-block-caes-hub-carousel-2" <?php echo \get_block_wrapper_attributes(); ?>>
    <?php if (!empty($selectedPosts) && is_array($selectedPosts)) { ?>
        <div class="caes-hub-carousel__header">
            <h1 class="caes-hub-carousel__title">Top Stories</h1>
        </div>
        <section class="wp-block-caes-hub-carousel__inner-wrapper" aria-labelledby="carouselTitle">
            <div class="sr-only" id="carouselTitle">
                <h2>Featured</h2>
            </div>
            <ul class="caes-hub-carousel-slides">
                <?php
                foreach ($selectedPosts as $postId) {
                    $post = get_post($postId);
                    if ($post) {
                        // ACF fields (fastest via get_post_meta)
                        $bgColorClass = get_post_meta($postId, 'carousel_caption_bg_color_override', true);
                        $hideExcerpt = get_post_meta($postId, 'carousel_hide_excerpt', true);
                        // Changed from 'primary_keywords' to 'primary_topics'
                        $primary_topic_ids = get_post_meta($postId, 'primary_topics', true); 

                        // Post data
                        $postTitle = esc_html($post->post_title);
                        $postExcerpt = esc_html(get_the_excerpt($postId));
                        $postThumbnail = get_the_post_thumbnail_url($postId, 'full') ?: '';

                        // Build class for content wrapper
                        $contentWrapperClasses = ['caes-hub-carousel-slide__content-wrapper'];
                        if (!empty($bgColorClass) && strtolower($bgColorClass) !== 'none') {
                            $classSlug = strtolower(str_replace(' ', '-', $bgColorClass));
                            $contentWrapperClasses[] = 'bg-' . esc_attr($classSlug);
                        }

                        echo '<li class="caes-hub-carousel-slide">';
                        echo '<div class="' . implode(' ', $contentWrapperClasses) . '">';
                        echo '<div class="caes-hub-carousel-slide__content">';
                        // Changed variable name from $primary_keyword_ids to $primary_topic_ids
                        if ($primary_topic_ids && !empty($primary_topic_ids)) {
                            // Changed class name for consistency
                            echo '<span class="caes-hub-carousel-slide-primary-topic is-style-caes-hub-merriweather-sans-uppercase" style="font-size: 0.875rem; margin-bottom: var(--wp--style--block-gap);">';

                            $topic_names = array(); // Changed variable name
                            // Changed variable name from $primary_keyword_ids to $primary_topic_ids
                            $ids_array = is_array($primary_topic_ids) ? $primary_topic_ids : array($primary_topic_ids);

                            // Changed variable name from $keyword_id to $topic_id
                            foreach ($ids_array as $topic_id) {
                                // Changed taxonomy from 'keywords' to 'topics' and variable name
                                $topic_term = get_term($topic_id, 'topics'); 
                                // Changed variable name
                                if ($topic_term && !is_wp_error($topic_term)) {
                                    $topic_names[] = $topic_term->name; // Changed variable name
                                }
                            }

                            if (!empty($topic_names)) { // Changed variable name
                                echo esc_html(implode(', ', $topic_names)); // Changed variable name
                            }

                            echo '</span>';
                        }
                        echo '<h2>';
                        echo '<a href="' . esc_url(get_permalink($postId)) . '">' . $postTitle . '</a>';
                        echo '</h2>';
                        if (!$hideExcerpt) {
                            echo '<p>' . $postExcerpt . '</p>';
                        }
                        echo '<div class="caes-hub-carousel-read-more is-style-caes-hub-arrow">Read more</div>';
                        echo '</div>';
                        echo '</div>';
                        echo '<div class="caes-hub-carousel-slide__image" style="background-image: url(\'' . esc_url($postThumbnail) . '\');"></div>';
                        echo '</li>';
                    }
                }

                ?>
            </ul>
            <div class="caes-hub-carousel-controls-wrapper">
                <div class="caes-hub-carousel-playpause">
                    <button type="button" class="btn-pause">
                        <span class="sr-only">Pause</span>
                    </button>
                </div>
                <div class="caes-hub-carousel-controls" role="group" aria-label="Carousel controls">
                    <button type="button" class="btn-prev">
                        <span class="sr-only">Previous Slide</span>
                    </button>
                    <span class="carousel-counter" aria-live="polite" aria-atomic="true">1 / <?php echo count($selectedPosts); ?></span>
                    <button type="button" class="btn-next">
                        <span class="sr-only">Next Slide</span>
                    </button>
                </div>
            </div>

        </section>
    <?php
    } else {
        echo 'No posts selected.';
    }
    ?>
</div>