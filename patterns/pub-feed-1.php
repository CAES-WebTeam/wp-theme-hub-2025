<?php
/**
 * Title: Publications Feed
 * Slug: caes-hub/pub-feed-1
 * Categories: pub_feeds
 * Description: Displays a list of publications
 * Keywords: publications
 * Block Types: core/query
 */
?>
<!-- wp:query {"queryId":3,"query":{"postType":"publications","perPage":4,"offset":0},"namespace":"pubs-feed","metadata":{"categories":["featured"],"patternName":"caes-hub/pub-feed-1","name":"Publications Feed"},"className":"caes-hub-post-list-grid"} -->
<div class="wp-block-query caes-hub-post-list-grid"><!-- wp:post-template {"style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"grid","columnCount":1}} -->
<!-- wp:group {"className":"caes-hub-post-list-grid-item caes-hub-post-list-grid-horizontal","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group caes-hub-post-list-grid-item caes-hub-post-list-grid-horizontal"><!-- wp:post-featured-image {"aspectRatio":"3/2","metadata":{"name":"caes-hub-post-list-img-container"},"className":"caes-hub-post-list-img-container"} /-->

<!-- wp:group {"metadata":{"name":"caes-hub-post-list-grid-info"},"className":"caes-hub-post-list-grid-info","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|60","right":"var:preset|spacing|60"}}},"backgroundColor":"base","layout":{"type":"flex","orientation":"vertical","justifyContent":"left","verticalAlignment":"space-between"}} -->
<div class="wp-block-group caes-hub-post-list-grid-info has-base-background-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--60)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:group {"className":"caes-hub-post-list-mobile-column","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"top"}} -->
<div class="wp-block-group caes-hub-post-list-mobile-column"><!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:caes-hub/pub-details-number {"fontSize":"small"} /-->

<!-- wp:post-title {"isLink":true,"className":"caes-hub-post-list-grid-title","style":{"elements":{"link":{"color":{"text":"var:preset|color|contrast"}}}},"textColor":"contrast","fontSize":"large"} /--></div>
<!-- /wp:group -->

<!-- wp:caes-hub/pub-details-status /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:caes-hub/pub-details-summary /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group"><!-- wp:caes-hub/pub-details-authors {"authorsAsSnippet":true} /-->

<!-- wp:paragraph -->
<p>|</p>
<!-- /wp:paragraph -->

<!-- wp:post-date {"format":"M j, Y"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:post-template -->

<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|60","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--60);margin-bottom:0"><!-- wp:query-pagination {"layout":{"type":"flex","justifyContent":"center"}} -->
<!-- wp:query-pagination-previous /-->

<!-- wp:query-pagination-numbers /-->

<!-- wp:query-pagination-next /-->
<!-- /wp:query-pagination -->

<!-- wp:spacer {"height":"10px"} -->
<div style="height:10px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer --></div>
<!-- /wp:group --></div>
<!-- /wp:query -->