<?php

/**
 * Title: Related News
 * Slug: caes-hub/related-news
 * Categories: story_feeds
 * Description: Display news articles related to the current post
 * Keywords: related news, news
 * Block Types: caes-hub/hand-picked-post
 */
?>

<!-- wp:group {"metadata":{"name":"Related News","categories":["story_feeds"],"patternName":"caes-hub/related-news"},"align":"wide","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide"><!-- wp:heading {"className":"is-style-caes-hub-section-heading"} -->
    <h2 class="wp-block-heading is-style-caes-hub-section-heading">Related News</h2>
    <!-- /wp:heading -->

    <!-- wp:caes-hub/hand-picked-post {"postType":["post","shorthand_story"],"layout":{"allowOrientation":true,"columnCount":3,"minimumColumnWidth":null},"displayLayout":"grid","customGapStep":5,"className":"caes-hub-post-list-grid","style":{"spacing":{"blockGap":"var:preset|spacing|60"}}} -->
    <!-- wp:group {"metadata":{"name":"caes-hub-post-list-grid-item"},"className":"caes-hub-post-list-grid-item","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
    <div class="wp-block-group caes-hub-post-list-grid-item"><!-- wp:post-featured-image {"aspectRatio":"3/2","metadata":{"name":"caes-hub-post-list-img-container"},"className":"caes-hub-post-list-img-container"} /-->

        <!-- wp:group {"metadata":{"name":"caes-hub-post-list-grid-info"},"className":"caes-hub-post-list-grid-info","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"backgroundColor":"base","layout":{"type":"flex","orientation":"vertical","justifyContent":"left","verticalAlignment":"space-between"}} -->
        <div class="wp-block-group caes-hub-post-list-grid-info has-base-background-color has-background" style="padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"default"}} -->
            <div class="wp-block-group"><!-- wp:post-title {"level":3,"isLink":true,"className":"caes-hub-post-list-grid-title","style":{"elements":{"link":{"color":{"text":"var:preset|color|contrast"}}}},"textColor":"contrast","fontSize":"medium"} /--></div>
            <!-- /wp:group -->

            <!-- wp:post-date {"format":"M j, Y","style":{"elements":{"link":{"color":{"text":"var:preset|color|contrast-two"}}}},"textColor":"contrast-two","fontSize":"small"} /-->
        </div>
        <!-- /wp:group -->

        <!-- wp:group {"metadata":{"name":"caes-hub-content-actions"},"className":"caes-hub-content-actions ","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
        <div class="wp-block-group caes-hub-content-actions"><!-- wp:caes-hub/action-save /-->

            <!-- wp:caes-hub/action-share /-->
        </div>
        <!-- /wp:group -->
    </div>
    <!-- /wp:group -->
    <!-- /wp:caes-hub/hand-picked-post -->
</div>
<!-- /wp:group -->