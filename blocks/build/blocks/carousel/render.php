<?php
$handSelectPosts = $attributes['handSelectPosts'];
$selectedPosts = $attributes['selectedPosts'];
$postType = $attributes['postType'] ?? 'post';
$orderBy = $attributes['orderBy'] ?? 'date_desc';
$categories = $attributes['categories'] ?? [];
$numberOfPosts = $attributes['numberOfPosts'] ?? 5;

// Get today's date
$today = new DateTime();
$dateFormatted = $today->format('F j, Y');
$day = $today->format('j');

// Function to add ordinal suffix
function getOrdinalSuffix($day)
{
    if (in_array(($day % 100), [11, 12, 13])) {
        return 'th';
    }
    switch ($day % 10) {
        case 1:
            return 'st';
        case 2:
            return 'nd';
        case 3:
            return 'rd';
        default:
            return 'th';
    }
}

$formattedDateWithSuffix = str_replace($day, $day . getOrdinalSuffix($day), $dateFormatted);

// Render content-brand block
// Commenting out on 3/14/2025 per OMC's request to not have logos on news feeds
// $contentBrandBlock = render_block(array(
//     'blockName' => 'caes-hub/content-brand',
//     'attrs' => array()
// ));

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
        'post_type' => $postType,
        'posts_per_page' => $numberOfPosts,
        'orderby' => $orderKey, // 'date' or 'title'
        'order' => $orderDirection, // 'ASC' or 'DESC'
    );

    // Handle category filtering
    if (!empty($categories)) {
        $queryArgs['category__in'] = $categories;
    }

    // Execute the query
    $query = new WP_Query($queryArgs);
    $selectedPosts = wp_list_pluck($query->posts, 'ID'); // Get the post IDs from the query
}
?>

<div class="wp-block-caes-hub-carousel" <?php echo \get_block_wrapper_attributes(); ?>>
    <?php if (!empty($selectedPosts) && is_array($selectedPosts)) { ?>
        <h1>CAES Field Report <span><?php echo $formattedDateWithSuffix; ?></span></h1>
        <section class="wp-block-caes-hub-carousel__inner-wrapper" aria-labelledby="carouselTitle">
            <div class="sr-only" id="carouselTitle">
                <h2>Featured</h2>
            </div>
            <ul class="caes-hub-carousel-slides">
                <?php
                foreach ($selectedPosts as $postId) {
                    $post = get_post($postId);
                    if ($post) {
                        // Post title
                        $postTitle = esc_html($post->post_title);
                        // Post excerpt, truncated to 200 characters
                        $postExcerpt = esc_html(get_the_excerpt($postId));
                        $postExcerpt = mb_substr($postExcerpt, 0, 200);
                        if (mb_strlen($postExcerpt) === 200) {
                            $postExcerpt .= '...';
                        }
                        // Featured image
                        $postThumbnail = get_the_post_thumbnail_url($postId, 'full') ?: '';

                        echo '<li class="caes-hub-carousel-slide" style="background-image: url(\'' . esc_url($postThumbnail) . '\');">';
                        echo '<div class="caes-hub-carousel-slide__content">';
                        echo '<a href="' . esc_url(get_permalink($postId)) . '">';
                        echo '<h2>' . $postTitle . '</h2>';
                        echo '</a>';
                        echo '<p>' . $postExcerpt . '</p>';
                        echo '</div>';
                        echo '</li>';
                    }
                }
                ?>
            </ul>
            <ul class="caes-hub-carousel-controls">
                <li class="prev"><button type="button" class="btn-prev"><span class="sr-only">Previous</span></button></li>
                <li class="play pause"><button type="button" class="btn-pause"><span class="sr-only">Pause</span></button></li>
                <li class="next"><button type="button" class="btn-next"><span class="sr-only">Next</span></button></li>
            </ul>
            <div class="caes-hub-carousel-nav">
                <ul>
                    <?php
                    foreach ($selectedPosts as $index => $postId) {
                        $post = get_post($postId);
                        if ($post) {
                            $postTitle = esc_html($post->post_title);
                            $shortenedTitle = wp_trim_words($postTitle, 3);

                            $currentClass = ($index === 0) ? 'current' : '';

                            echo '<li>';
                            echo '<button class="' . esc_attr($currentClass) . '" data-slide="' . esc_attr($index) . '">' . $shortenedTitle . '</button>';
                            echo '</li>';
                        }
                    }
                    ?>
                </ul>
            </div>
        </section>
    <?php
    } else {
        echo 'No posts selected.';
    }
    ?>
</div>