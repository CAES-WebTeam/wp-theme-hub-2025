<?php
/**
 * Title: Immersive Photo Gallery
 * Slug: caes-theme/immersive-photo-gallery
 * Description: A photo-heavy immersive storytelling layout with cover hero, pullquote, galleries, and inline images.
 * Categories: featured, media, immersive
 * Keywords: immersive, photo, gallery, story
 * Viewport Width: 1400
 */

$placeholder_bg = get_theme_file_uri( 'assets/images/hotdog.jpg' );
$pullquote_bg   = get_theme_file_uri( 'assets/images/Fly-Background.jpg' );
?>

<!-- wp:cover {"url":"<?php echo esc_url( $placeholder_bg ); ?>","dimRatio":50,"overlayColor":"contrast","isUserOverlayColor":true,"minHeight":300,"minHeightUnit":"px","contentPosition":"center center","sizeSlug":"large","align":"full","layout":{"type":"constrained"}} -->
<div class="wp-block-cover alignfull" style="min-height:300px"><img class="wp-block-cover__image-background size-large" alt="" src="<?php echo esc_url( $placeholder_bg ); ?>" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-contrast-background-color has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:post-title {"level":1,"className":"is-style-caes-hub-full-underline","style":{"spacing":{"margin":{"right":"0","left":"0"}},"elements":{"link":{"color":{"text":"var:preset|color|base"}}},"typography":{"fontStyle":"normal","fontWeight":"700","lineHeight":"1.2"}},"textColor":"base","fontFamily":"oswald"} /-->

<!-- wp:paragraph {"style":{"elements":{"link":{"color":{"text":"var:preset|color|base"}}}},"textColor":"base"} -->
<p class="has-base-color has-text-color has-link-color"><em>Written by</em><br><em><strong>Author</strong></em></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"elements":{"link":{"color":{"text":"var:preset|color|base"}}}},"textColor":"base"} -->
<p class="has-base-color has-text-color has-link-color"><em>Illustrated by<br><strong>Artist</strong></em></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div></div>
<!-- /wp:cover -->

<!-- wp:pullquote {"align":"full","style":{"elements":{"link":{"color":{"text":"var:preset|color|base"}}},"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80","left":"var:preset|spacing|80","right":"var:preset|spacing|80"},"margin":{"top":"0","bottom":"0"}},"background":{"backgroundImage":{"url":"<?php echo esc_url( $pullquote_bg ); ?>","id":0,"source":"file","title":"Fly Background"},"backgroundSize":"cover"},"border":{"width":"0px","style":"none","radius":"0px"}},"backgroundColor":"contrast","textColor":"base"} -->
<figure class="wp-block-pullquote alignfull has-base-color has-contrast-background-color has-text-color has-background has-link-color" style="border-style:none;border-width:0px;border-radius:0px;margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80);padding-left:var(--wp--preset--spacing--80)"><blockquote><p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p></blockquote></figure>
<!-- /wp:pullquote -->

<!-- wp:paragraph -->
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Cras lorem leo, faucibus ut lacus eget, venenatis feugiat diam. Maecenas lobortis ante eu finibus tempor. In quam nisl, rhoncus at dui eget, dignissim ultricies quam. Sed ac arcu sed nulla ornare varius. Sed egestas ultricies risus, ut varius mi dapibus dictum. Aliquam sed maximus lectus. Morbi eleifend dui eget mauris hendrerit, tincidunt eleifend nulla pretium.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"spacing":{"padding":{"right":"var:preset|spacing|20","left":"var:preset|spacing|20"}}}} -->
<p style="padding-right:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--20)">Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum. Sed ut perspiciatis unde omnis iste natus error sit voluptatem.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"spacing":{"padding":{"right":"0","left":"var:preset|spacing|20","bottom":"0"}}}} -->
<p style="padding-right:0;padding-bottom:0;padding-left:var(--wp--preset--spacing--20)">Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit.</p>
<!-- /wp:paragraph -->

<!-- wp:caes-hub/pub-details-authors {"displayVersion":"names-and-titles","type":"sources","grid":false,"className":"is-style-caes-hub-compact","style":{"typography":{"lineHeight":"1.3"}}} /-->

<!-- wp:caes-hub/caes-gallery {"rows":[{"columns":2,"images":[{"id":1,"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide.jpg' ); ?>","alt":"","caption":"Lorem ipsum dolor sit amet, consectetur adipiscing elit.","sizes":{"full":{"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide.jpg' ); ?>","height":800,"width":600,"orientation":"portrait"}}},{"id":2,"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide-2.jpg' ); ?>","alt":"","caption":"Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.","sizes":{"full":{"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide-2.jpg' ); ?>","height":800,"width":600,"orientation":"portrait"}}}]}],"showCaptions":true,"align":"wide"} /-->

<!-- wp:image {"id":3,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="<?php echo get_theme_file_uri( 'assets/images/example-slide-3.webp' ); ?>" alt="" class="wp-image-3"/></figure>
<!-- /wp:image -->

<!-- wp:pullquote {"style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}}} -->
<figure class="wp-block-pullquote" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)"><blockquote><p>Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium.</p></blockquote></figure>
<!-- /wp:pullquote -->

<!-- wp:caes-hub/caes-gallery {"rows":[{"columns":3,"images":[{"id":4,"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide.jpg' ); ?>","alt":"","caption":"Ut enim ad minim veniam, quis nostrud.","sizes":{"full":{"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide.jpg' ); ?>","height":600,"width":800,"orientation":"landscape"}}},{"id":5,"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide-2.jpg' ); ?>","alt":"","caption":"Exercitation ullamco laboris nisi ut aliquip.","sizes":{"full":{"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide-2.jpg' ); ?>","height":600,"width":800,"orientation":"landscape"}}},{"id":6,"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide-3.webp' ); ?>","alt":"","caption":"Ex ea commodo consequat duis aute irure.","sizes":{"full":{"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide-3.webp' ); ?>","height":600,"width":800,"orientation":"landscape"}}}]}],"align":"wide","style":{"spacing":{"padding":{"top":"0","bottom":"0"},"margin":{"top":"0","bottom":"0"}}}} /-->

<!-- wp:paragraph {"style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"0"}}}} -->
<p style="padding-top:var(--wp--preset--spacing--60);padding-bottom:0">At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate non provident.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"spacing":{"padding":{"top":"0","bottom":"0"}}}} -->
<p style="padding-top:0;padding-bottom:0">Similique sunt in culpa qui officia deserunt mollitia animi, id est laborum et dolorum fuga. Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"spacing":{"padding":{"bottom":"var:preset|spacing|70"}}}} -->
<p style="padding-bottom:var(--wp--preset--spacing--70)">Temporibus autem quibusdam et aut officiis debitis aut rerum necessitatibus saepe eveniet ut et voluptates repudiandae sint et molestiae non recusandae. Itaque earum rerum hic tenetur a sapiente delectus, ut aut reiciendis voluptatibus maiores alias consequatur aut perferendis doloribus asperiores repellat.</p>
<!-- /wp:paragraph -->

<!-- wp:caes-hub/caes-gallery {"rows":[{"columns":1,"images":[{"id":7,"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide.jpg' ); ?>","alt":"","caption":"Dolor in reprehenderit in voluptate velit esse cillum.","sizes":{"full":{"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide.jpg' ); ?>","height":600,"width":800,"orientation":"landscape"}}}]}],"align":"wide","style":{"spacing":{"padding":{"top":"0","bottom":"0"},"margin":{"top":"0","bottom":"0"}}}} /-->

<!-- wp:paragraph {"style":{"spacing":{"padding":{"top":"0","bottom":"0"}}}} -->
<p style="padding-top:0;padding-bottom:0">Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"spacing":{"padding":{"top":"0","bottom":"0"}}}} -->
<p style="padding-top:0;padding-bottom:0">Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>
<!-- /wp:paragraph -->

<!-- wp:caes-hub/caes-gallery {"rows":[{"columns":3,"images":[{"id":8,"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide-2.jpg' ); ?>","alt":"","caption":"Dolore eu fugiat nulla pariatur excepteur.","sizes":{"full":{"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide-2.jpg' ); ?>","height":600,"width":800,"orientation":"landscape"}}},{"id":9,"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide-3.webp' ); ?>","alt":"","caption":"Sint occaecat cupidatat non proident.","sizes":{"full":{"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide-3.webp' ); ?>","height":600,"width":800,"orientation":"landscape"}}},{"id":10,"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide.jpg' ); ?>","alt":"","caption":"Sunt in culpa qui officia deserunt mollit.","sizes":{"full":{"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide.jpg' ); ?>","height":600,"width":800,"orientation":"landscape"}}}]}],"showCaptions":true,"align":"full","style":{"spacing":{"padding":{"right":"0","left":"0"},"margin":{"right":"0","left":"0"}}}} /-->

<!-- wp:paragraph {"style":{"spacing":{"padding":{"top":"0","bottom":"0"}}}} -->
<p style="padding-top:0;padding-bottom:0">Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"spacing":{"padding":{"top":"0","bottom":"0"}}}} -->
<p style="padding-top:0;padding-bottom:0">Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt.</p>
<!-- /wp:paragraph -->

<!-- wp:caes-hub/caes-gallery {"rows":[{"columns":3,"images":[{"id":11,"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide.jpg' ); ?>","alt":"","caption":"Anim id est laborum sed ut perspiciatis.","sizes":{"full":{"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide.jpg' ); ?>","height":600,"width":800,"orientation":"landscape"}}},{"id":12,"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide-2.jpg' ); ?>","alt":"","caption":"Unde omnis iste natus error sit voluptatem.","sizes":{"full":{"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide-2.jpg' ); ?>","height":600,"width":800,"orientation":"landscape"}}}]},{"columns":3,"images":[{"id":13,"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide-3.webp' ); ?>","alt":"","caption":"Accusantium doloremque laudantium totam.","sizes":{"full":{"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide-3.webp' ); ?>","height":600,"width":800,"orientation":"landscape"}}},{"id":14,"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide.jpg' ); ?>","alt":"","caption":"Rem aperiam eaque ipsa quae ab illo.","sizes":{"full":{"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide.jpg' ); ?>","height":600,"width":800,"orientation":"landscape"}}},{"id":15,"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide-2.jpg' ); ?>","alt":"","caption":"Inventore veritatis et quasi architecto.","sizes":{"full":{"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide-2.jpg' ); ?>","height":600,"width":800,"orientation":"landscape"}}}]},{"columns":3,"images":[{"id":16,"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide-3.webp' ); ?>","alt":"","caption":"Beatae vitae dicta sunt explicabo.","sizes":{"full":{"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide-3.webp' ); ?>","height":600,"width":800,"orientation":"landscape"}}},{"id":17,"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide.jpg' ); ?>","alt":"","caption":"Nemo enim ipsam voluptatem quia.","sizes":{"full":{"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide.jpg' ); ?>","height":600,"width":800,"orientation":"landscape"}}},{"id":18,"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide-2.jpg' ); ?>","alt":"","caption":"Voluptas sit aspernatur aut odit aut.","sizes":{"full":{"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide-2.jpg' ); ?>","height":600,"width":800,"orientation":"landscape"}}}]}],"align":"wide"} /-->

<!-- wp:caes-hub/caes-gallery {"rows":[{"columns":1,"images":[{"id":19,"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide-3.webp' ); ?>","alt":"","caption":"Fugit sed quia consequuntur magni dolores.","sizes":{"full":{"url":"<?php echo get_theme_file_uri( 'assets/images/example-slide-3.webp' ); ?>","height":600,"width":800,"orientation":"landscape"}}}]}],"align":"wide"} /-->

<!-- wp:paragraph {"style":{"spacing":{"padding":{"top":"2.1rem","bottom":"2.1rem"}}}} -->
<p style="padding-top:2.1rem;padding-bottom:2.1rem">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam.</p>
<!-- /wp:paragraph -->