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

add_filter('should_load_remote_block_patterns', '__return_false');

/** START CUSTOM BLOCKS FOR THEME **/

function caes_hub_register_blocks()
{
	register_block_type(__DIR__ . '/blocks/build/blocks/action-print');
	register_block_type(__DIR__ . '/blocks/build/blocks/action-save');
	register_block_type(__DIR__ . '/blocks/build/blocks/action-share');
	register_block_type(__DIR__ . '/blocks/build/blocks/carousel');
	register_block_type(__DIR__ . '/blocks/build/blocks/content-brand');
	register_block_type(__DIR__ . '/blocks/build/blocks/header-brand');
	register_block_type(__DIR__ . '/blocks/build/blocks/toc');
	register_block_type(__DIR__ . '/blocks/build/blocks/uga-footer');
}
add_action('init', 'caes_hub_register_blocks');