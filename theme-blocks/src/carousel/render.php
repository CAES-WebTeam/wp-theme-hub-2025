<?php
$selectedPosts = $attributes['selectedPosts'];

// Get today's date
$today = new DateTime(); // Current date and time

// Format the date as "July 12, 2024"
$dateFormatted = $today->format('F j, Y');

// Get the day of the month to append the ordinal suffix
$day = $today->format('j');

// Function to add ordinal suffix
function getOrdinalSuffix($day)
{
    if (in_array(($day % 100), [11, 12, 13])) {
        return 'th'; // Handle special cases for 11, 12, 13
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

// Append the ordinal suffix
$formattedDateWithSuffix = str_replace($day, $day . getOrdinalSuffix($day), $dateFormatted);

// Render content-brand block
$contentBrandBlock = render_block(array(
    'blockName' => 'caes-hub/content-brand',
    'attrs' => array()
));

?>

<div class="wp-block-caes-hub-carousel"
    <?php echo \get_block_wrapper_attributes(); ?>>

    <?php if (!empty($selectedPosts) && is_array($selectedPosts)) { ?>

        <h1>CAES Field Report <?php echo '<span>' . $formattedDateWithSuffix . '</span>' ?></h1>
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
                        // Post excerpt, truncated to 100 characters
                        $postExcerpt = esc_html(get_the_excerpt($postId));
                        $postExcerpt = mb_substr($postExcerpt, 0, 200);
                        if (mb_strlen($postExcerpt) === 200) {
                            $postExcerpt .= '...';
                        }
                        // Featured image
                        $postThumbnail = get_the_post_thumbnail_url($postId, 'full') ?: ''; // Fallback image URL

                        echo '<li class="caes-hub-carousel-slide" style="background-image: url(\'' . esc_url($postThumbnail) . '\');">';
                        echo '<div class="caes-hub-carousel-slide__content">';
                        echo $contentBrandBlock;
                        echo ' <a href="' . esc_url(get_permalink($postId)) . '">';
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
            <ul class="caes-hub-carousel-nav">
                <?php
                foreach ($selectedPosts as $index => $postId) {
                    $post = get_post($postId);
                    if ($post) {
                        $postTitle = esc_html($post->post_title);
                        $shortenedTitle = wp_trim_words($postTitle, 3); // Shorten title to 3 words

                        // Add 'current' class to the first button only
                        $currentClass = ($index === 0) ? 'current' : '';

                        echo '<li>';
                        echo '<button class="' . esc_attr($currentClass) . '" data-slide="' . esc_attr($index) . '">' . $shortenedTitle . '</button>';
                        echo '</li>';
                    }
                }
                ?>
            </ul>

        </section>
    <?php
    } else {
        echo 'No posts selected.';
    }
    ?>
</div>