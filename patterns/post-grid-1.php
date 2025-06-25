<?php
/**
 * Title: Post Grid
 * Slug: caes-hub/post-grid-1
 * Categories: story_feeds
 * Description: Grid displaying posts
 * Keywords: grid, posts
 * Block Types: core/query
 */
?>
<!-- wp:query {"query":{"perPage":3,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"namespace":"core/posts-list","metadata":{"name":"caes-hub-post-list-grid","categories":["story_feeds"],"patternName":"caes-hub/post-grid-1"},"align":"wide","className":"caes-hub-post-list-grid"} -->
<div class="wp-block-query alignwide caes-hub-post-list-grid"><!-- wp:post-template {"style":{"spacing":{"blockGap":"var:preset|spacing|60"}},"layout":{"type":"grid","columnCount":null,"minimumColumnWidth":"24rem"}} -->
<!-- wp:group {"metadata":{"name":"caes-hub-post-list-grid-item"},"className":"caes-hub-post-list-grid-item","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
<div class="wp-block-group caes-hub-post-list-grid-item"><!-- wp:post-featured-image {"aspectRatio":"3/2","metadata":{"name":"caes-hub-post-list-img-container"},"className":"caes-hub-post-list-img-container"} /-->

<!-- wp:group {"metadata":{"name":"caes-hub-post-list-grid-info"},"className":"caes-hub-post-list-grid-info","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"backgroundColor":"base","layout":{"type":"flex","orientation":"vertical","justifyContent":"left","verticalAlignment":"space-between"}} -->
<div class="wp-block-group caes-hub-post-list-grid-info has-base-background-color has-background" style="padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"},"typography":{"fontSize":"1.1rem","lineHeight":"1"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group" style="font-size:1.1rem;line-height:1"><!-- wp:caes-hub/primary-keyword {"showCategoryIcon":true,"enableLinks":false,"name":"caes-hub/primary-keyword","mode":"preview","className":"is-style-caes-hub-oswald-uppercase","style":{"border":{"right":{"color":"var:preset|color|contrast","width":"2px"}},"spacing":{"padding":{"right":"var:preset|spacing|30"}}}} /-->

<!-- wp:post-date {"format":"M j, Y","style":{"typography":{"fontStyle":"light","fontWeight":"300","textTransform":"uppercase"}},"fontFamily":"oswald"} /--></div>
<!-- /wp:group -->

<!-- wp:post-title {"isLink":true,"className":"caes-hub-post-list-grid-title","style":{"elements":{"link":{"color":{"text":"var:preset|color|contrast"}}}},"textColor":"contrast","fontSize":"medium"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"caes-hub-content-actions"},"className":"caes-hub-content-actions ","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group caes-hub-content-actions"><!-- wp:caes-hub/action-save /-->

<!-- wp:caes-hub/action-share /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query -->