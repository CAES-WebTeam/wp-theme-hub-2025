<?php
/**
 * Title: Reveal Story
 * Slug: caes-theme/reveal-story
 * Description: A scroll-driven reveal block with three frames for immersive storytelling.
 * Categories: featured, media
 * Keywords: reveal, scroll, story, immersive
 * Block Types: caes-hub/reveal
 * Viewport Width: 1400
 */

$placeholder_image_1 = get_theme_file_uri( 'assets/images/example-slide.jpg' );
$placeholder_image_2 = get_theme_file_uri( 'assets/images/example-slide-2.jpg' );
?>

<!-- wp:caes-hub/reveal {"frames":[{"id":"frame-pattern-1","desktopImage":{"id":0,"url":"<?php echo esc_url( $placeholder_image_1 ); ?>","alt":"","caption":"","sizes":[]},"mobileImage":{"id":0,"url":"<?php echo esc_url( $placeholder_image_1 ); ?>","alt":"","caption":"","sizes":[]},"desktopFocalPoint":{"x":0.5,"y":0.5},"mobileFocalPoint":{"x":0.29,"y":0.5},"desktopDuotone":null,"mobileDuotone":null,"transition":{"type":"fade","speed":"normal"}},{"id":"frame-pattern-2","desktopImage":{"id":0,"url":"<?php echo esc_url( $placeholder_image_1 ); ?>","alt":"","caption":"","sizes":[]},"mobileImage":{"id":0,"url":"<?php echo esc_url( $placeholder_image_1 ); ?>","alt":"","caption":"","sizes":[]},"desktopFocalPoint":{"x":0.5,"y":0.5},"mobileFocalPoint":{"x":0.29,"y":0.5},"desktopDuotone":["#0d3b66","#faf0ca"],"mobileDuotone":["#0d3b66","#faf0ca"],"transition":{"type":"fade","speed":"normal"},"duotone":null},{"id":"frame-pattern-3","desktopImage":{"id":0,"url":"<?php echo esc_url( $placeholder_image_2 ); ?>","alt":"","caption":"","sizes":[]},"mobileImage":{"id":0,"url":"<?php echo esc_url( $placeholder_image_2 ); ?>","alt":"","caption":"","sizes":[]},"desktopFocalPoint":{"x":0.5,"y":0.5},"mobileFocalPoint":{"x":0.5,"y":0.5},"desktopDuotone":null,"mobileDuotone":null,"transition":{"type":"left","speed":"normal"}}],"overlayOpacity":0,"metadata":{"categories":["featured","media"],"patternName":"caes-theme/reveal-story","name":"Reveal Story"}} -->
<div class="wp-block-caes-hub-reveal alignfull"><!-- wp:caes-hub/reveal-frames {"frameId":"frame-pattern-1","frameLabel":"Frame 1 Content"} -->
<div class="wp-block-caes-hub-reveal-frames reveal-frame-content" data-frame-index="0"><!-- wp:columns {"verticalAlignment":"center","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}}} -->
<div class="wp-block-columns are-vertically-aligned-center" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--50)"><!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:group {"style":{"elements":{"link":{"color":{"text":"var:preset|color|base"}}},"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|60","right":"var:preset|spacing|60"}}},"backgroundColor":"olympic","textColor":"base","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-base-color has-olympic-background-color has-text-color has-background has-link-color" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--60)"><!-- wp:heading {"fitText":true} -->
<h2 class="wp-block-heading has-fit-text">Your Story Begins</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Scroll to discover more.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:caes-hub/reveal-frames -->

<!-- wp:caes-hub/reveal-frames {"frameIndex":1,"frameId":"frame-pattern-2","frameLabel":"Frame 2 Content"} -->
<div class="wp-block-caes-hub-reveal-frames reveal-frame-content" data-frame-index="1"><!-- wp:columns {"verticalAlignment":"center","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}}} -->
<div class="wp-block-columns are-vertically-aligned-center" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--50)"><!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:group {"style":{"elements":{"link":{"color":{"text":"var:preset|color|base"}}},"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|60","right":"var:preset|spacing|60"}}},"backgroundColor":"olympic","textColor":"base","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-base-color has-olympic-background-color has-text-color has-background has-link-color" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--60)"><!-- wp:heading -->
<h2 class="wp-block-heading">The Middle Chapter</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Add your content here. This text box appears on the right side of the screen.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas ac elit rutrum, fringilla libero in, faucibus nulla. In et neque nulla. Quisque blandit ante in arcu gravida porttitor. Curabitur lacinia ullamcorper vehicula. Nunc posuere ante metus, id porta neque facilisis non. Nullam a nisl laoreet, pellentesque nunc quis, dictum tortor. Donec at mauris turpis. In sit amet dignissim urna, fringilla tempor orci. Nulla efficitur augue sollicitudin augue cursus, non dictum odio feugiat. Mauris auctor sem eu ex vestibulum, et imperdiet neque egestas. Sed pharetra tellus in elit pretium sagittis. Nulla quam tellus, feugiat id dolor eu, facilisis facilisis sem. Donec lobortis enim at risus imperdiet, id dignissim mauris elementum.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Mauris eleifend volutpat finibus. Praesent eget sapien in dui placerat imperdiet non non est. Praesent non quam metus. Duis imperdiet risus at est eleifend posuere. Vivamus lorem ipsum, luctus eu facilisis eget, pretium non quam. Donec vulputate ornare congue. Nam rhoncus gravida odio, eget condimentum dolor sagittis ac. In felis urna, hendrerit nec convallis ac, ornare quis orci. Donec eget lorem nec nisi pharetra rutrum. Ut maximus sem scelerisque eros facilisis, interdum cursus turpis commodo. Maecenas rutrum mauris sit amet molestie scelerisque. Suspendisse facilisis aliquet mattis. Sed et sodales purus. Vivamus ligula leo, scelerisque eget lorem vitae, sollicitudin scelerisque mauris. Aenean vitae hendrerit eros, at tempus orci. Vivamus eros lacus, iaculis vitae vestibulum id, tempor id velit.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Donec at felis libero. Donec lacinia urna posuere ligula elementum, ut facilisis massa sagittis. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Maecenas tincidunt finibus felis vitae imperdiet. Aenean tincidunt dui in congue semper. In hac habitasse platea dictumst. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Sed mi quam, euismod sed ligula sit amet, commodo mattis augue. Fusce volutpat mattis diam quis volutpat. Vivamus porta fringilla tortor, vel laoreet justo eleifend et.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:caes-hub/reveal-frames -->

<!-- wp:caes-hub/reveal-frames {"frameIndex":2,"frameId":"frame-pattern-3","frameLabel":"Frame 3 Content"} -->
<div class="wp-block-caes-hub-reveal-frames reveal-frame-content" data-frame-index="2"><!-- wp:group {"layout":{"type":"constrained","contentSize":"550px"}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"elements":{"link":{"color":{"text":"var:preset|color|base"}}},"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|60","right":"var:preset|spacing|60"}}},"backgroundColor":"olympic","textColor":"base","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-base-color has-olympic-background-color has-text-color has-background has-link-color" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--60)"><!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">The Conclusion</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Wrap up your story here. This text box appears on the left side of the screen.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:caes-hub/reveal-frames --></div>
<!-- /wp:caes-hub/reveal -->