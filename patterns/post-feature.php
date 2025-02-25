<?php

/**
 * Title: Feature Post
 * Slug: caes-hub/post-feature
 * Categories: story_feeds
 * Description: Feature a post
 * Keywords: posts
 * Block Types: core/query
 */
?>

<!-- wp:query {"query":{"perPage":"1","pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"namespace":"core/posts-list","metadata":{"name":"caes-hub-post-list-grid","categories":["featured"],"patternName":"caes-hub/post-grid-1"},"align":"wide","className":"caes-hub-post-list-grid"} -->
<div class="wp-block-query alignwide caes-hub-post-list-grid"><!-- wp:post-template {"style":{"spacing":{"blockGap":"var:preset|spacing|60"}},"layout":{"type":"grid","columnCount":"1"}} -->
    <!-- wp:group {"metadata":{"name":"caes-hub-post-list-grid-item"},"className":"caes-hub-post-list-grid-item height-width-100","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"grid","columnCount":2,"minimumColumnWidth":null}} -->
    <div class="wp-block-group caes-hub-post-list-grid-item height-width-100"><!-- wp:group {"metadata":{"name":"caes-hub-post-list-grid-info"},"className":"caes-hub-post-list-grid-info","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|60","right":"var:preset|spacing|60"}}},"backgroundColor":"base","layout":{"type":"flex","orientation":"vertical","justifyContent":"left","verticalAlignment":"space-between"}} -->
        <div class="wp-block-group caes-hub-post-list-grid-info has-base-background-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--60)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"default"}} -->
            <div class="wp-block-group"><!-- wp:caes-hub/content-brand /-->

                <!-- wp:post-title {"isLink":true,"className":"caes-hub-post-list-grid-title","style":{"elements":{"link":{"color":{"text":"var:preset|color|contrast"}}}},"textColor":"contrast","fontSize":"large"} /-->

                <!-- wp:post-excerpt {"excerptLength":50} /-->
            </div>
            <!-- /wp:group -->

            <!-- wp:post-date {"format":"M j, Y","style":{"elements":{"link":{"color":{"text":"var:preset|color|contrast-two"}}}},"textColor":"contrast-two","fontSize":"small"} /-->
        </div>
        <!-- /wp:group -->

        <!-- wp:post-featured-image {"aspectRatio":"3/2","metadata":{"name":"caes-hub-post-list-img-container"},"className":"caes-hub-post-list-img-container"} /-->

        <!-- wp:group {"metadata":{"name":"caes-hub-content-actions"},"className":"caes-hub-content-actions ","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
        <div class="wp-block-group caes-hub-content-actions"><!-- wp:caes-hub/action-save /-->

            <!-- wp:caes-hub/action-share /-->
        </div>
        <!-- /wp:group -->
    </div>
    <!-- /wp:group -->
    <!-- /wp:post-template -->
</div>
<!-- /wp:query -->
