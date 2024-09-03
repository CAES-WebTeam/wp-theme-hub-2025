<?php

/** Enqueue style sheet and JavaScript. */
function caes_hub_styles()
{
	wp_enqueue_style(
		'caes-hub-styles',
		get_theme_file_uri('assets/css/main.min.css'),
		[],
		wp_get_theme()->get('Version')
	);
	wp_enqueue_script('caes-hub-script', get_template_directory_uri() . '/assets/js/main.js', array(), '1.0.0', true);
}
add_action('wp_enqueue_scripts', 'caes_hub_styles');

/** START BLOCKS FOR THEME **/

// Register Blocks
function caes_hub_block_init()
{
	register_block_type(
		__DIR__ . '/theme-blocks/build/header-brand',
		array(
			'render_callback' => 'caes_hub_header_brand_render',
		)
	);
}
add_action('init', 'caes_hub_block_init');

function caes_hub_header_brand_render($attributes, $content, $block)
{
	ob_start();
	require get_template_directory() . '/theme-blocks/build/header-brand/render.php';
	return ob_get_clean();
}


?>