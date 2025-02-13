<?php

/**
 * Title: Related News
 * Slug: caes-hub/related-news
 * Categories: featured
 * Description: Display news articles related to the current post
 * Keywords: related news, news
 * Block Types: core/query
 */
?>

<!-- wp:group {"metadata":{"name":"Related News"},"align":"wide","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide"><!-- wp:heading {"className":"is-style-caes-hub-section-heading"} -->
    <h2 class="wp-block-heading is-style-caes-hub-section-heading">Related News</h2>
    <!-- /wp:heading -->

    <!-- wp:query {"queryId":4,"query":{"perPage":3,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"namespace":"custom-related-posts","metadata":{"name":"caes-hub-post-list-grid","categories":["featured"],"patternName":"caes-hub/post-grid-1"},"align":"wide","className":"caes-hub-post-list-grid"} -->
    <div class="wp-block-query alignwide caes-hub-post-list-grid"><!-- wp:post-template {"className":"caes-hub-related-news","style":{"spacing":{"blockGap":"var:preset|spacing|60"}},"layout":{"type":"grid","columnCount":3}} -->
        <!-- wp:group {"metadata":{"name":"caes-hub-post-list-grid-item"},"className":"caes-hub-post-list-grid-item","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
        <div class="wp-block-group caes-hub-post-list-grid-item"><!-- wp:post-featured-image {"aspectRatio":"3/2","metadata":{"name":"caes-hub-post-list-img-container"},"className":"caes-hub-post-list-img-container"} /-->

            <!-- wp:group {"metadata":{"name":"caes-hub-post-list-grid-info"},"className":"caes-hub-post-list-grid-info","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"backgroundColor":"base","layout":{"type":"flex","orientation":"vertical","justifyContent":"left","verticalAlignment":"space-between"}} -->
            <div class="wp-block-group caes-hub-post-list-grid-info has-base-background-color has-background" style="padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"default"}} -->
                <div class="wp-block-group"><!-- wp:caes-hub/content-brand /-->

                    <!-- wp:post-title {"isLink":true,"className":"caes-hub-post-list-grid-title","style":{"elements":{"link":{"color":{"text":"var:preset|color|contrast"}}}},"textColor":"contrast","fontSize":"medium"} /-->
                </div>
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
        <!-- /wp:post-template -->
    </div>
    <!-- /wp:query -->
</div>
<!-- /wp:group -->