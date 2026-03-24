<?php
/**
 * Title: Immersive Pullquote
 * Slug: caes-theme/immersive-pullquote
 * Description: A pullquote with a background image from theme assets.
 * Categories: featured, text, immersive
 * Keywords: pullquote, quote, immersive
 * Viewport Width: 1400
 */

$placeholder_image = get_theme_file_uri( 'assets/images/Fly-Background.jpg' );
?>

<!-- wp:pullquote {"style":{"background":{"backgroundImage":{"url":"<?php echo esc_url( $placeholder_image ); ?>","id":0,"source":"file","title":"Placeholder"},"backgroundSize":"cover"},"elements":{"link":{"color":{"text":"var:preset|color|base"}}}},"textColor":"base"} -->
<figure class="wp-block-pullquote has-base-color has-text-color has-link-color"><blockquote><p>Whoo! Hah! Yahoo!</p><cite>Meow-io</cite></blockquote></figure>
<!-- /wp:pullquote -->
