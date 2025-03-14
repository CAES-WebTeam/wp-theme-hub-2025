<?php
/**
 * Title: Full Width Event Feed
 * Slug: caes-hub/full-width-pub-feed
 * Categories: event_feeds
 * Description: Full width event feed with the latest single pub. Adjust settings in the query block.
 * Keywords: events
 * Block Types: core/query
 */
?>



<!-- wp:query {"query":{"perPage":"1","pages":0,"offset":0,"postType":"events","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false,"parents":[],"format":[]},"namespace":"core/posts-list","metadata":{"name":"caes-hub-post-list-grid","categories":["featured"],"patternName":"caes-hub/post-feature"},"align":"wide","className":"caes-hub-post-list-grid","layout":{"type":"default"}} -->
<div class="wp-block-query alignwide caes-hub-post-list-grid"><!-- wp:post-template {"style":{"spacing":{"blockGap":"var:preset|spacing|60"},"elements":{"link":{"color":{"text":"var:preset|color|contrast"}}}},"textColor":"contrast","layout":{"type":"grid","columnCount":1}} -->
<!-- wp:group {"metadata":{"name":"caes-hub-post-list-grid-item"},"className":"caes-hub-post-list-grid-item height-width-100","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"grid","columnCount":2,"minimumColumnWidth":null}} -->
<div class="wp-block-group caes-hub-post-list-grid-item height-width-100"><!-- wp:group {"metadata":{"name":"caes-hub-post-list-grid-info"},"className":"caes-hub-post-list-grid-info","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70","left":"var:preset|spacing|70","right":"var:preset|spacing|70"}}},"backgroundColor":"base","layout":{"type":"flex","orientation":"vertical","justifyContent":"left","verticalAlignment":"space-between"}} -->
<div class="wp-block-group caes-hub-post-list-grid-info has-base-background-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-right:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--70)"><!-- wp:caes-hub/content-brand /-->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)"><!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:caes-hub/event-details-date-time {"dateAsSnippet":true,"headingFontSize":"1"} /-->

<!-- wp:post-title {"isLink":true,"className":"caes-hub-post-list-grid-title","style":{"elements":{"link":{"color":{"text":"var:preset|color|contrast"}}}},"textColor":"contrast","fontSize":"x-large"} /--></div>
<!-- /wp:group -->

<!-- wp:post-excerpt {"excerptLength":50} /--></div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><!-- wp:caes-hub/event-details-location {"locationAsSnippet":true,"headingFontSize":"1"} /-->

<!-- wp:caes-hub/event-details-online-location {"onlineAsSnippet":true,"headingFontSize":"1"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:caes-hub/event-details-featured-image {"className":"caes-hub-post-list-img-container"} /-->

<!-- wp:group {"metadata":{"name":"caes-hub-content-actions"},"className":"caes-hub-content-actions ","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group caes-hub-content-actions"><!-- wp:caes-hub/action-save /-->

<!-- wp:caes-hub/action-share /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query -->