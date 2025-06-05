<?php

/**
 * Title: Event Grid
 * Slug: caes-hub/event-grid-1
 * Categories: event_feeds
 * Description: Grid displaying upcoming events
 * Keywords: events
 * Block Types: core/query
 */
?>

<!-- wp:heading {"className":"is-style-caes-hub-section-heading"} -->
<h2 class="wp-block-heading is-style-caes-hub-section-heading">Upcoming Events</h2>
<!-- /wp:heading -->

<!-- wp:query {"queryId":5,"query":{"postType":"events","offset":0,"filterByDate":true,"perPage":6},"namespace":"upcoming-events","className":"caes-hub-post-list-grid"} -->
<div class="wp-block-query caes-hub-post-list-grid"><!-- wp:post-template {"layout":{"type":"grid","columnCount":null,"minimumColumnWidth":"24rem"}} -->
    <!-- wp:group {"metadata":{"name":"caes-hub-post-list-grid-item"},"className":"caes-hub-post-list-grid-item","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
    <div class="wp-block-group caes-hub-post-list-grid-item"><!-- wp:caes-hub/event-details-featured-image {"className":"caes-hub-post-list-img-container"} /-->

        <!-- wp:group {"metadata":{"name":"caes-hub-post-list-grid-info"},"className":"caes-hub-post-list-grid-info","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"backgroundColor":"base","layout":{"type":"flex","orientation":"vertical","justifyContent":"left","verticalAlignment":"space-between"}} -->
        <div class="wp-block-group caes-hub-post-list-grid-info has-base-background-color has-background" style="padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"default"}} -->
            <div class="wp-block-group"><!-- wp:post-title {"isLink":true,"className":"caes-hub-post-list-grid-title","style":{"elements":{"link":{"color":{"text":"var:preset|color|contrast"}}}},"textColor":"contrast","fontSize":"medium"} /-->

                <!-- wp:caes-hub/event-details-date-time {"heading":false} /-->
            </div>
            <!-- /wp:group -->
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