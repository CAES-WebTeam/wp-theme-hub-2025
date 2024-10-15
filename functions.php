<?php

/** Editor Styles **/

function caes_hub_editor_styles()
{
	add_editor_style('./assets/css/editor.css');
}
add_action('after_setup_theme', 'caes_hub_editor_styles');

/** Enqueue style sheet and JavaScript. */
function caes_hub_styles()
{
	wp_enqueue_style(
		'caes-hub-styles',
		get_theme_file_uri('assets/css/main.css'),
		[],
		wp_get_theme()->get('Version')
	);
	wp_enqueue_script('caes-hub-script', get_template_directory_uri() . '/assets/js/main.js', array(), '1.0.0', true);
}
add_action('wp_enqueue_scripts', 'caes_hub_styles');

// Adds custom style choices to core blocks with add-block-styles.js

function add_block_style()
{
	wp_enqueue_script(
		'add-block-style',
		get_theme_file_uri() . '/assets/js/add-block-styles.js',
		array('wp-blocks', 'wp-dom-ready', 'wp-edit-post')
	);
}
add_action('enqueue_block_editor_assets', 'add_block_style');

/* Remove Default Block Patterns */

function remove_default_block_patterns()
{
	remove_theme_support('core-block-patterns');
}
add_action('after_setup_theme', 'remove_default_block_patterns');

/** Unregister API Patterns */

add_filter( 'should_load_remote_block_patterns', '__return_false' );


/** START CUSTOM BLOCKS FOR THEME **/

// Register Blocks
function caes_hub_block_init()
{
	register_block_type(
		__DIR__ . '/theme-blocks/build/header-brand',
		array(
			'render_callback' => 'caes_hub_header_brand_render',
		)
	);
	register_block_type(
		__DIR__ . '/theme-blocks/build/content-brand',
		array(
			'render_callback' => 'caes_hub_content_brand_render',
		)
	);
	register_block_type(
		__DIR__ . '/theme-blocks/build/uga-footer',
		array(
			'render_callback' => 'caes_hub_uga_footer_render',
		)
	);

	register_block_type(
		__DIR__ . '/theme-blocks/build/action-print',
		array(
			'render_callback' => 'caes_hub_action_print',
		)
	);

	register_block_type(
		__DIR__ . '/theme-blocks/build/action-share',
		array(
			'render_callback' => 'caes_hub_action_share',
		)
	);

	register_block_type(
		__DIR__ . '/theme-blocks/build/action-save',
		array(
			'render_callback' => 'caes_hub_action_save',
		)
	);

	register_block_type(
		__DIR__ . '/theme-blocks/build/post-filter',
		array(
			'render_callback' => 'caes_hub_post_filter',
		)
	);

	register_block_type(
		__DIR__ . '/theme-blocks/build/carousel',
		array(
			'render_callback' => 'caes_hub_carousel',
		)
	);
};

add_action('init', 'caes_hub_block_init');

function caes_hub_header_brand_render($attributes, $content, $block)
{
	ob_start();
	require get_template_directory() . '/theme-blocks/build/header-brand/render.php';
	return ob_get_clean();
};
function caes_hub_content_brand_render($attributes, $content, $block)
{
	ob_start();
	require get_template_directory() . '/theme-blocks/build/content-brand/render.php';
	return ob_get_clean();
}
function caes_hub_uga_footer_render($attributes, $content, $block)
{
	ob_start();
	require get_template_directory() . '/theme-blocks/build/uga-footer/render.php';
	return ob_get_clean();
};

function caes_hub_action_print($attributes, $content, $block) {
	ob_start();
	require get_template_directory() . '/theme-blocks/build/action-print/render.php';
	return ob_get_clean();
}

function caes_hub_action_share($attributes, $content, $block) {
	ob_start();
	require get_template_directory() . '/theme-blocks/build/action-share/render.php';
	return ob_get_clean();
}

function caes_hub_action_save($attributes, $content, $block) {
	ob_start();
	require get_template_directory() . '/theme-blocks/build/action-save/render.php';
	return ob_get_clean();
}

function caes_hub_post_filter($attributes, $content, $block) {
	ob_start();
	require get_template_directory() . '/theme-blocks/build/post-filter/render.php';
	return ob_get_clean();
}

function caes_hub_carousel($attributes, $content, $block) {
	ob_start();
	require get_template_directory() . '/theme-blocks/build/carousel/render.php';
	return ob_get_clean();
}