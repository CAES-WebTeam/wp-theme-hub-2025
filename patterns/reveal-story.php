<?php
/**
 * Title: Reveal Story
 * Slug: caes-theme/reveal-story
 * Description: A scroll-driven reveal block with three frames for immersive storytelling.
 * Categories: featured, media
 * Keywords: reveal, scroll, story, immersive
 * Block Types: caes-hub/reveal
 */

$placeholder_image_1 = get_theme_file_uri( 'assets/images/example-slide.jpg' );
$placeholder_image_2 = get_theme_file_uri( 'assets/images/example-slide-2.jpg' );
?>

<!-- wp:caes-hub/reveal {"frames":[{"id":"frame-pattern-1","desktopImage":{"id":0,"url":"<?php echo esc_url( $placeholder_image_1 ); ?>","alt":"","caption":"","sizes":{}},"mobileImage":{"id":0,"url":"<?php echo esc_url( $placeholder_image_1 ); ?>","alt":"","caption":"","sizes":{}},"desktopFocalPoint":{"x":0.5,"y":0.5},"mobileFocalPoint":{"x":0.5,"y":0.5},"desktopDuotone":null,"mobileDuotone":null,"transition":{"type":"fade","speed":"normal"}},{"id":"frame-pattern-2","desktopImage":{"id":0,"url":"<?php echo esc_url( $placeholder_image_1 ); ?>","alt":"","caption":"","sizes":{}},"mobileImage":{"id":0,"url":"<?php echo esc_url( $placeholder_image_1 ); ?>","alt":"","caption":"","sizes":{}},"desktopFocalPoint":{"x":0.5,"y":0.5},"mobileFocalPoint":{"x":0.5,"y":0.5},"desktopDuotone":null,"mobileDuotone":null,"transition":{"type":"fade","speed":"normal"}},{"id":"frame-pattern-3","desktopImage":{"id":0,"url":"<?php echo esc_url( $placeholder_image_2 ); ?>","alt":"","caption":"","sizes":{}},"mobileImage":{"id":0,"url":"<?php echo esc_url( $placeholder_image_2 ); ?>","alt":"","caption":"","sizes":{}},"desktopFocalPoint":{"x":0.5,"y":0.5},"mobileFocalPoint":{"x":0.5,"y":0.5},"desktopDuotone":null,"mobileDuotone":null,"transition":{"type":"fade","speed":"normal"}}],"overlayOpacity":0} -->
<div class="wp-block-caes-hub-reveal alignfull"><!-- wp:caes-hub/reveal-frames {"frameId":"frame-pattern-1","frameLabel":"Frame 1 Content"} -->
<div class="wp-block-caes-hub-reveal-frames reveal-frame-content" data-frame-index="0"><!-- wp:columns {"verticalAlignment":"center","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}}} -->
<div class="wp-block-columns are-vertically-aligned-center" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--50)"><!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:group {"style":{"elements":{"link":{"color":{"text":"var:preset|color|base"}}},"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"backgroundColor":"olympic","textColor":"base","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-base-color has-olympic-background-color has-text-color has-background has-link-color" style="padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:heading -->
<h2 class="wp-block-heading">Your Story Begins</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Scroll to discover more.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:caes-hub/reveal-frames -->

<!-- wp:caes-hub/reveal-frames {"frameIndex":1,"frameId":"frame-pattern-2","frameLabel":"Frame 2 Content"} -->
<div class="wp-block-caes-hub-reveal-frames reveal-frame-content" data-frame-index="1"><!-- wp:columns {"verticalAlignment":"center","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}}} -->
<div class="wp-block-columns are-vertically-aligned-center" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--50)"><!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:group {"style":{"elements":{"link":{"color":{"text":"var:preset|color|base"}}},"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"backgroundColor":"olympic","textColor":"base","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-base-color has-olympic-background-color has-text-color has-background has-link-color" style="padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:heading -->
<h2 class="wp-block-heading">The Middle Chapter</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Add your content here. This text box appears on the right side of the screen.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:caes-hub/reveal-frames -->

<!-- wp:caes-hub/reveal-frames {"frameIndex":2,"frameId":"frame-pattern-3","frameLabel":"Frame 3 Content"} -->
<div class="wp-block-caes-hub-reveal-frames reveal-frame-content" data-frame-index="2"><!-- wp:columns {"verticalAlignment":"center","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}}} -->
<div class="wp-block-columns are-vertically-aligned-center" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--50)"><!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:group {"style":{"elements":{"link":{"color":{"text":"var:preset|color|base"}}},"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"backgroundColor":"olympic","textColor":"base","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-base-color has-olympic-background-color has-text-color has-background has-link-color" style="padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:heading -->
<h2 class="wp-block-heading">The Conclusion</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Wrap up your story here. This text box appears on the left side of the screen.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:caes-hub/reveal-frames --></div>
<!-- /wp:caes-hub/reveal -->