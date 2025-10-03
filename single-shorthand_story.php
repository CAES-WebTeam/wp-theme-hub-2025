<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php wp_title('|', true, 'right'); ?></title>
	<?php
	// Check for the post before generating content
	if (have_posts()) {
		the_post();
		$meta = get_post_meta($post->ID);
	}

	$content = '<!-- wp:columns {"style":{"spacing":{"blockGap":{"top":"0","left":"0"},"margin":{"top":"0","bottom":"0"}}}} -->
<div class="wp-block-columns" style="margin-top:0;margin-bottom:0"><!-- wp:column {"width":"222px","className":"caes-hub-left","backgroundColor":"base"} --><div class="wp-block-column caes-hub-left has-base-background-color has-background" style="flex-basis:222px"><!-- wp:template-part {"slug":"sidebar","theme":"wp-theme-hub-2025","area":"uncategorized","className":"caes-hub-sidebar-wrapper"} /--></div>
<!-- /wp:column --><!-- wp:column {"width":"","className":"caes-hub-right has-background","backgroundColor":"base-two"} --><div class="wp-block-column caes-hub-right has-background has-base-two-background-color"><!-- wp:group {"tagName":"main","metadata":{"name":"caes-hub-main-wrapper"},"className":"caes-hub-main-wrapper caes-hub-shorthand","style":{"spacing":{"blockGap":"0"}}} --><main class="wp-block-group caes-hub-main-wrapper caes-hub-shorthand">';
	$content .= get_shorthandinfo($meta, 'story_body');
	$content .= '<div id="extraHTML">';
	$content .= get_shorthandinfo($meta, 'extra_html');
	$content .= '</div>';
	$content .= '</main><!-- /wp:group -->';
	
	// Wrap Related Content and Footer in a group with no gap
	$content .= '<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<div class="wp-block-group">';
	
	// Add Related Content section
	$content .= '<!-- wp:group {"metadata":{"name":"caes-hub-content-footer"},"className":"caes-hub-content-footer","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}},"elements":{"link":{"color":{"text":"var:preset|color|base"}}}},"backgroundColor":"olympic","textColor":"base","layout":{"type":"constrained","contentSize":"1400px"}} -->
<div class="wp-block-group caes-hub-content-footer has-base-color has-olympic-background-color has-text-color has-background has-link-color" style="padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:heading {"className":"is-style-caes-hub-section-heading"} -->
<h2 class="wp-block-heading is-style-caes-hub-section-heading">Related Content</h2>
<!-- /wp:heading -->

<!-- wp:caes-hub/hand-picked-post {"postType":["post","publications","shorthand_story"],"displayLayout":"grid","customGapStep":5,"gridItemPosition":"auto","gridAutoColumnWidth":24,"className":"caes-hub-post-list-grid"} -->
<!-- wp:group {"metadata":{"name":"caes-hub-post-list-grid-item"},"className":"caes-hub-post-list-grid-item","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
<div class="wp-block-group caes-hub-post-list-grid-item"><!-- wp:post-featured-image {"aspectRatio":"3/2","metadata":{"name":"caes-hub-post-list-img-container"},"className":"caes-hub-post-list-img-container"} /-->

<!-- wp:group {"metadata":{"name":"caes-hub-post-list-grid-info"},"className":"caes-hub-post-list-grid-info","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"backgroundColor":"base","layout":{"type":"flex","orientation":"vertical","justifyContent":"left","verticalAlignment":"space-between"}} -->
<div class="wp-block-group caes-hub-post-list-grid-info has-base-background-color has-background" style="padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"},"typography":{"fontSize":"1.1rem","lineHeight":"1"},"elements":{"link":{"color":{"text":"var:preset|color|contrast"}}}},"textColor":"contrast","layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group has-contrast-color has-text-color has-link-color" style="font-size:1.1rem;line-height:1"><!-- wp:caes-hub/primary-topic {"showCategoryIcon":true,"enableLinks":false,"name":"caes-hub/primary-topic","mode":"preview","className":"is-style-caes-hub-oswald-uppercase","style":{"border":{"right":{"color":"var:preset|color|contrast","width":"1px"},"top":[],"bottom":[],"left":[]},"spacing":{"padding":{"right":"var:preset|spacing|30"}},"elements":{"link":{"color":{"text":"var:preset|color|contrast"}}}},"textColor":"contrast"} /-->

<!-- wp:post-date {"format":"M j, Y","style":{"typography":{"fontStyle":"light","fontWeight":"300","textTransform":"uppercase"}},"fontFamily":"oswald"} /--></div>
<!-- /wp:group -->

<!-- wp:post-title {"level":3,"isLink":true,"className":"caes-hub-post-list-grid-title","style":{"elements":{"link":{"color":{"text":"var:preset|color|contrast"}}}},"textColor":"contrast","fontSize":"large"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"caes-hub-content-actions"},"className":"caes-hub-content-actions ","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group caes-hub-content-actions"><!-- wp:caes-hub/action-save /-->

<!-- wp:caes-hub/action-share /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:caes-hub/hand-picked-post --></div>
<!-- /wp:group -->';
	
	// Add footer template part
	$content .= '<!-- wp:template-part {"slug":"footer","theme":"wp-theme-hub-2025"} /-->';
	
	// Close wrapper group
	$content .= '</div><!-- /wp:group -->';
	
	// Close column and columns
	$content .= '</div><!-- /wp:column --></div><!-- /wp:columns -->';

	$final_content = do_blocks($content);

	wp_head(); ?>
</head>

<body <?php body_class(); ?>>
	<?php
	// Now we can safely check for password protection and process content
	if (post_password_required($post->ID)) {
		get_shorthand_password_form();
	} else {
		// Output the final content generated above
		echo $final_content;

		// Output additional styles
	?>
		<style type="text/css">
			<?php echo get_shorthandoption('sh_css'); ?>
		</style>
	<?php
	}
	?>
	<?php wp_footer(); ?>
</body>

</html>