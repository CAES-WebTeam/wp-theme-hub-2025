<?php

/**
 * Title: Grid
 * Slug: caes-hub/grid-1
 * Categories: content_patterns
 * Description: Basic grid pattern
 * Keywords: grid, layout
 * Block Types: core/group
 */

$image_url = esc_url( get_theme_file_uri( 'assets/images/example-slide-3.webp' ) );
?>

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|70","right":"var:preset|spacing|70"}},"elements":{"link":{"color":{"text":"var:preset|color|base"}}}},"backgroundColor":"contrast","textColor":"base","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-base-color has-contrast-background-color has-text-color has-background has-link-color" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--70)"><!-- wp:group {"align":"full","layout":{"type":"constrained","contentSize":"1200px"}} -->
<div class="wp-block-group alignfull"><!-- wp:heading {"textAlign":"center","className":"is-style-default","style":{"typography":{"lineHeight":"1.2"}}} -->
<h2 class="wp-block-heading has-text-align-center is-style-default" style="line-height:1.2">Grid Section</h2>
<!-- /wp:heading -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|60","padding":{"right":"var:preset|spacing|50","left":"var:preset|spacing|50"}}},"layout":{"type":"grid","columnCount":null,"minimumColumnWidth":"20.00rem"}} -->
<div class="wp-block-group" style="padding-right:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:cover {"url":"<?php echo $image_url; ?>","hasParallax":true,"dimRatio":70,"overlayColor":"contrast","isUserOverlayColor":true,"minHeight":400,"contentPosition":"center center","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|60","right":"var:preset|spacing|60"}},"shadow":"var:preset|shadow|small-dark","border":{"color":"#ffffff82","width":"1px"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-cover has-parallax has-border-color" style="border-color:#ffffff82;border-width:1px;padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--60);box-shadow:var(--wp--preset--shadow--small-dark);min-height:400px"><div role="img" class="wp-block-cover__image-background has-parallax" style="background-position:50% 50%;background-image:url(<?php echo $image_url; ?>)"></div><span aria-hidden="true" class="wp-block-cover__background has-contrast-background-color has-background-dim-70 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:spacer {"height":"200px"} -->
<div style="height:200px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:group {"style":{"typography":{"lineHeight":"1.3"},"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="line-height:1.3"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:heading {"style":{"typography":{"lineHeight":"1"}}} -->
<h2 class="wp-block-heading" style="line-height:1">Athens</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:buttons {"layout":{"type":"flex"}} -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-caes-hub-red-border","style":{"spacing":{"padding":{"left":"var:preset|spacing|40","right":"var:preset|spacing|40","top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}},"shadow":"var:preset|shadow|large-dark"}} -->
<div class="wp-block-button is-style-caes-hub-red-border"><a class="wp-block-button__link wp-element-button" style="padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--40);box-shadow:var(--wp--preset--shadow--large-dark)">Read more</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div></div>
<!-- /wp:cover -->

<!-- wp:cover {"url":"<?php echo $image_url; ?>","hasParallax":true,"dimRatio":70,"overlayColor":"contrast","isUserOverlayColor":true,"minHeight":400,"contentPosition":"center center","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|60","right":"var:preset|spacing|60"}},"shadow":"var:preset|shadow|small-dark","border":{"color":"#ffffff82","width":"1px"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-cover has-parallax has-border-color" style="border-color:#ffffff82;border-width:1px;padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--60);box-shadow:var(--wp--preset--shadow--small-dark);min-height:400px"><div role="img" class="wp-block-cover__image-background has-parallax" style="background-position:50% 50%;background-image:url(<?php echo $image_url; ?>)"></div><span aria-hidden="true" class="wp-block-cover__background has-contrast-background-color has-background-dim-70 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:spacer {"height":"200px"} -->
<div style="height:200px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:group {"style":{"typography":{"lineHeight":"1.3"},"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="line-height:1.3"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:heading {"style":{"typography":{"lineHeight":"1"}}} -->
<h2 class="wp-block-heading" style="line-height:1">Griffin</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:buttons {"layout":{"type":"flex"}} -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-caes-hub-red-border","style":{"spacing":{"padding":{"left":"var:preset|spacing|40","right":"var:preset|spacing|40","top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}},"shadow":"var:preset|shadow|large-dark"}} -->
<div class="wp-block-button is-style-caes-hub-red-border"><a class="wp-block-button__link wp-element-button" style="padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--40);box-shadow:var(--wp--preset--shadow--large-dark)">Read more</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div></div>
<!-- /wp:cover -->

<!-- wp:cover {"url":"<?php echo $image_url; ?>","hasParallax":true,"dimRatio":70,"overlayColor":"contrast","isUserOverlayColor":true,"minHeight":400,"contentPosition":"center center","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|60","right":"var:preset|spacing|60"}},"shadow":"var:preset|shadow|small-dark","border":{"color":"#ffffff82","width":"1px"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-cover has-parallax has-border-color" style="border-color:#ffffff82;border-width:1px;padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--60);box-shadow:var(--wp--preset--shadow--small-dark);min-height:400px"><div role="img" class="wp-block-cover__image-background has-parallax" style="background-position:50% 50%;background-image:url(<?php echo $image_url; ?>)"></div><span aria-hidden="true" class="wp-block-cover__background has-contrast-background-color has-background-dim-70 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:spacer {"height":"200px"} -->
<div style="height:200px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:group {"style":{"typography":{"lineHeight":"1.3"},"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="line-height:1.3"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:heading {"style":{"typography":{"lineHeight":"1"}}} -->
<h2 class="wp-block-heading" style="line-height:1">Tifton</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:buttons {"layout":{"type":"flex"}} -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-caes-hub-red-border","style":{"spacing":{"padding":{"left":"var:preset|spacing|40","right":"var:preset|spacing|40","top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}},"shadow":"var:preset|shadow|large-dark"}} -->
<div class="wp-block-button is-style-caes-hub-red-border"><a class="wp-block-button__link wp-element-button" style="padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--40);box-shadow:var(--wp--preset--shadow--large-dark)">Read more</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div></div>
<!-- /wp:cover --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
