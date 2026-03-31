<?php
/**
 * Title: Immersive Cover
 * Slug: caes-theme/immersive-cover
 * Description: A full-width cover block with post title and author bylines for immersive storytelling layouts.
 * Categories: featured, media, immersive
 * Keywords: cover, hero, immersive, title
 * Viewport Width: 1400
 */

$placeholder_bg = get_theme_file_uri( 'assets/images/hotdog.jpg' );
?>

<!-- wp:cover {"url":"<?php echo esc_url( $placeholder_bg ); ?>","dimRatio":50,"overlayColor":"contrast","isUserOverlayColor":true,"minHeight":300,"minHeightUnit":"px","contentPosition":"center center","sizeSlug":"large","align":"full","layout":{"type":"constrained"}} -->
<div class="wp-block-cover alignfull" style="min-height:300px"><img class="wp-block-cover__image-background size-large" alt="" src="<?php echo esc_url( $placeholder_bg ); ?>" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-contrast-background-color has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:post-title {"level":1,"className":"is-style-caes-hub-full-underline","style":{"spacing":{"margin":{"right":"0","left":"0"}},"elements":{"link":{"color":{"text":"var:preset|color|base"}}},"typography":{"fontStyle":"normal","fontWeight":"700","lineHeight":"1.2"}},"textColor":"base","fontFamily":"oswald"} /-->

<!-- wp:caes-hub/pub-details-authors {"displayVersion":"names-only","showHeading":false,"customHeading":"Written by","snippetPrefix":"Written by","snippetPrefixShown":true,"grid":false,"className":"is-style-default","style":{"typography":{"lineHeight":"1","fontStyle":"italic","fontWeight":"400"}}} /-->

<!-- wp:caes-hub/pub-details-authors {"displayVersion":"names-only","showHeading":false,"customHeading":"Written by","type":"artists","snippetPrefix":"Illustrated by","snippetPrefixShown":true,"grid":false,"className":"is-style-default","style":{"typography":{"lineHeight":"1","fontStyle":"italic","fontWeight":"400"}}} /--></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div></div>
<!-- /wp:cover -->
