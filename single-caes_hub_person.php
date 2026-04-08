<?php
/**
 * Template switcher for caes_hub_person CPT.
 *
 * Hooks into the block template system to serve
 * single-caes_hub_person-no-symplectic.html for people who do not have
 * Symplectic Elements data (no elements_user_id post meta), and the default
 * single-caes_hub_person.html for those who do.
 *
 * WordPress picks this file up via the standard template hierarchy
 * (single-{post-type}.php), giving us an early hook point before block
 * template resolution runs.
 */

add_filter( 'get_block_templates', function ( $templates, $query, $template_type ) {
	if ( $template_type !== 'wp_template' || ! is_singular( 'caes_hub_person' ) ) {
		return $templates;
	}

	$post_id        = get_the_ID();
	$has_symplectic = get_post_meta( $post_id, 'elements_user_id', true );

	if ( ! $has_symplectic ) {
		foreach ( $templates as &$template ) {
			if ( $template->slug === 'single-caes_hub_person' ) {
				$file = get_template_directory() . '/templates/single-caes_hub_person-no-symplectic.html';
				if ( file_exists( $file ) ) {
					$template->content = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				}
				break;
			}
		}
	}

	return $templates;
}, 10, 3 );
