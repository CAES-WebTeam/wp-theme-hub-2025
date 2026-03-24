<?php
/**
 * Title: Immersive Story
 * Slug: caes-theme/immersive
 * Description: A full immersive storytelling layout combining a cover hero, reveal, pullquote, motion scroll, and gallery.
 * Categories: featured, media, immersive
 * Keywords: immersive, story, scroll, reveal, motion
 * Viewport Width: 1400
 */

$placeholder_bg      = get_theme_file_uri( 'assets/images/hotdog.jpg' );
$placeholder_image_1 = get_theme_file_uri( 'assets/images/example-slide.jpg' );
$placeholder_image_2 = get_theme_file_uri( 'assets/images/example-slide-2.jpg' );
$pullquote_bg        = get_theme_file_uri( 'assets/images/Fly-Background.jpg' );
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

<!-- wp:group {"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="padding-top:0;padding-right:var(--wp--preset--spacing--40);padding-bottom:0;padding-left:var(--wp--preset--spacing--40)"><!-- wp:paragraph -->
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Cras lorem leo, faucibus ut lacus eget, venenatis feugiat diam. Maecenas lobortis ante eu finibus tempor. In quam nisl, rhoncus at dui eget, dignissim ultricies quam. Sed ac arcu sed nulla ornare varius. Sed egestas ultricies risus, ut varius mi dapibus dictum. Aliquam sed maximus lectus. Morbi eleifend dui eget mauris hendrerit, tincidunt eleifend nulla pretium.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

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

<!-- wp:caes-hub/pub-details-authors {"displayVersion":"names-and-titles","type":"sources","grid":false,"className":"is-style-caes-hub-compact"} /-->

<!-- wp:paragraph -->
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Cras lorem leo, faucibus ut lacus eget, venenatis feugiat diam. Maecenas lobortis ante eu finibus tempor. In quam nisl, rhoncus at dui eget, dignissim ultricies quam. Sed ac arcu sed nulla ornare varius. Sed egestas ultricies risus, ut varius mi dapibus dictum. Aliquam sed maximus lectus. Morbi eleifend dui eget mauris hendrerit, tincidunt eleifend nulla pretium.</p>
<!-- /wp:paragraph -->

<!-- wp:pullquote {"style":{"background":{"backgroundImage":{"url":"<?php echo esc_url( $pullquote_bg ); ?>","id":0,"source":"file","title":"Fly Background"},"backgroundSize":"cover"},"elements":{"link":{"color":{"text":"var:preset|color|base"}}}},"textColor":"base"} -->
<figure class="wp-block-pullquote has-base-color has-text-color has-link-color"><blockquote><p>Whoo! Hah! Yahoo!</p><cite>Meow-io</cite></blockquote></figure>
<!-- /wp:pullquote -->

<!-- wp:caes-hub/motion-scroll {"slides":[{"id":"slide-demo-1","image":{"id":0,"url":"<?php echo esc_url( $placeholder_image_1 ); ?>","alt":"First demo slide","caption":"This is the first image caption — it appears over the pinned image area.","sizes":[]},"focalPoint":{"x":0.35,"y":0.33},"duotone":null,"caption":"This is the first image caption — it appears over the pinned image area."},{"id":"slide-demo-2","image":{"id":0,"url":"<?php echo esc_url( $placeholder_image_2 ); ?>","alt":"Second demo slide","caption":"A second image with its own caption. Focal points control which part of the image stays visible.","sizes":[]},"focalPoint":{"x":0.5,"y":0.3},"duotone":null,"caption":"A second image with its own caption. Focal points control which part of the image stays visible."},{"id":"slide-demo-3","image":{"id":0,"url":"<?php echo esc_url( $placeholder_image_1 ); ?>","alt":"Third demo slide with duotone","caption":"This image has a duotone filter applied — navy and cream.","sizes":[]},"focalPoint":{"x":0.35,"y":0.33},"duotone":["#0d3b66","#faf0ca"],"caption":"This image has a duotone filter applied — navy and cream."}],"contentBackgroundColor":"#000000","contentTextColor":"#FFFFFF","captionTextColor":"#FFFFFF","contentPadding":{"top":"var:preset|spacing|40","right":"var:preset|spacing|70","bottom":"var:preset|spacing|40","left":"var:preset|spacing|70"}} -->
<!-- wp:heading -->
<h2 class="wp-block-heading">How Motion Scroll Works</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>The content you're reading now sits on the left side in a scrollable column. On the right, images are pinned to the viewport and swap when you scroll past each image marker.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>This layout is ideal for long-form storytelling, research narratives, or any content that benefits from pairing text with large, immersive photography.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>The content background, text color, image area background, and caption colors are all configurable from the block settings panel.</p>
<!-- /wp:paragraph -->

<!-- wp:caes-hub/motion-scroll-image /-->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Scrolling Past Image Markers</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>When you scroll past the image marker above, the pinned image on the right changed. Each marker corresponds to the next slide in the block's configuration.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>You can place as much content as you need between image markers — paragraphs, headings, lists, quotes, or any other blocks. The image simply stays pinned until the next marker appears.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Captions appear as a translucent overlay at the bottom of the image area, giving proper attribution without obstructing the photo.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi at magna interdum, maximus felis vel, ornare elit. In hendrerit suscipit quam ac sollicitudin. Nullam at eros nulla. Morbi orci tellus, cursus nec porta vitae, feugiat sed dui. Duis ex sem, ornare vitae tincidunt ut, aliquet non ex. Proin sit amet dolor id libero facilisis rhoncus quis a lacus. Morbi volutpat justo eget urna eleifend, in ornare erat convallis. Quisque sit amet scelerisque odio, eget mollis mauris.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi at magna interdum, maximus felis vel, ornare elit. In hendrerit suscipit quam ac sollicitudin. Nullam at eros nulla. Morbi orci tellus, cursus nec porta vitae, feugiat sed dui. Duis ex sem, ornare vitae tincidunt ut, aliquet non ex. Proin sit amet dolor id libero facilisis rhoncus quis a lacus. Morbi volutpat justo eget urna eleifend, in ornare erat convallis. Quisque sit amet scelerisque odio, eget mollis mauris.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi at magna interdum, maximus felis vel, ornare elit. In hendrerit suscipit quam ac sollicitudin. Nullam at eros nulla. Morbi orci tellus, cursus nec porta vitae, feugiat sed dui. Duis ex sem, ornare vitae tincidunt ut, aliquet non ex. Proin sit amet dolor id libero facilisis rhoncus quis a lacus. Morbi volutpat justo eget urna eleifend, in ornare erat convallis. Quisque sit amet scelerisque odio, eget mollis mauris.</p>
<!-- /wp:paragraph -->

<!-- wp:caes-hub/motion-scroll-image {"slideIndex":1} /-->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Duotone Filters on Slides</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Just like the Reveal block, Motion Scroll supports duotone filters on individual slides. This final image uses a navy-cream filter to create visual contrast.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Each slide also supports independent focal points, so you control exactly which part of the image stays centered when it's cropped to fit the viewport.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>The block handles responsive layout automatically — on smaller screens, the side-by-side layout stacks vertically so images and content flow naturally.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi at magna interdum, maximus felis vel, ornare elit. In hendrerit suscipit quam ac sollicitudin. Nullam at eros nulla. Morbi orci tellus, cursus nec porta vitae, feugiat sed dui. Duis ex sem, ornare vitae tincidunt ut, aliquet non ex. Proin sit amet dolor id libero facilisis rhoncus quis a lacus. Morbi volutpat justo eget urna eleifend, in ornare erat convallis. Quisque sit amet scelerisque odio, eget mollis mauris.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi at magna interdum, maximus felis vel, ornare elit. In hendrerit suscipit quam ac sollicitudin. Nullam at eros nulla. Morbi orci tellus, cursus nec porta vitae, feugiat sed dui. Duis ex sem, ornare vitae tincidunt ut, aliquet non ex. Proin sit amet dolor id libero facilisis rhoncus quis a lacus. Morbi volutpat justo eget urna eleifend, in ornare erat convallis. Quisque sit amet scelerisque odio, eget mollis mauris.</p>
<!-- /wp:paragraph -->

<!-- wp:caes-hub/motion-scroll-image {"slideIndex":2} /-->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Wrapping Up</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Both blocks are designed for immersive, scroll-driven storytelling within the WordPress block editor. They pair well together — use Reveal for dramatic full-screen moments and Motion Scroll for detailed narrative sections.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi at magna interdum, maximus felis vel, ornare elit. In hendrerit suscipit quam ac sollicitudin. Nullam at eros nulla. Morbi orci tellus, cursus nec porta vitae, feugiat sed dui. Duis ex sem, ornare vitae tincidunt ut, aliquet non ex. Proin sit amet dolor id libero facilisis rhoncus quis a lacus. Morbi volutpat justo eget urna eleifend, in ornare erat convallis. Quisque sit amet scelerisque odio, eget mollis mauris.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi at magna interdum, maximus felis vel, ornare elit. In hendrerit suscipit quam ac sollicitudin. Nullam at eros nulla. Morbi orci tellus, cursus nec porta vitae, feugiat sed dui. Duis ex sem, ornare vitae tincidunt ut, aliquet non ex. Proin sit amet dolor id libero facilisis rhoncus quis a lacus. Morbi volutpat justo eget urna eleifend, in ornare erat convallis. Quisque sit amet scelerisque odio, eget mollis mauris.</p>
<!-- /wp:paragraph -->
<!-- /wp:caes-hub/motion-scroll -->

<!-- wp:caes-hub/caes-gallery {"rows":[{"columns":3,"images":[]}]} /-->
