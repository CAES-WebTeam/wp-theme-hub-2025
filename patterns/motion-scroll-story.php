<?php
/**
 * Title: Motion Scroll Story
 * Slug: caes-theme/motion-scroll-story
 * Description: A scroll-driven motion block with three frames for immersive storytelling.
 * Categories: featured, media
 * Keywords: motion, scroll, story, immersive
 * Block Types: caes-hub/motion-scroll
 * Viewport Width: 1400
 */

$placeholder_image_1 = get_theme_file_uri( 'assets/images/example-slide.jpg' );
$placeholder_image_2 = get_theme_file_uri( 'assets/images/example-slide-2.jpg' );
?>
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