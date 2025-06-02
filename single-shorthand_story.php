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
	$content .= '</main><!-- /wp:group --><!-- wp:template-part {"slug":"footer","theme":"wp-theme-hub-2025"} /--></div><!-- /wp:column --></div><!-- /wp:columns -->';

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