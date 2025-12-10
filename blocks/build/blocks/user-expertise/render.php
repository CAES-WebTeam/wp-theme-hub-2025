<?php
/**
 * Render callback for the User Expertise block.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block content.
 * @param WP_Block $block      Block instance.
 * @return string  Rendered block HTML.
 */

// Get attributes with defaults.
$background_color        = $attributes['backgroundColor'] ?? 'hedges';
$text_color              = $attributes['textColor'] ?? '';
$custom_background_color = $attributes['customBackgroundColor'] ?? '';
$custom_text_color       = $attributes['customTextColor'] ?? '';

/**
 * Get the user ID.
 *
 * Checks in order:
 * 1. If on a user archive page, get the queried user
 * 2. If viewing a post type that has an author ACF field, use that
 * 3. Fall back to the post author
 *
 * Adjust this logic based on your specific setup.
 */
$user_id = null;

if ( is_author() ) {
	// On author archive page.
	$user_id = get_queried_object_id();
} elseif ( isset( $block->context['postId'] ) ) {
	// Try to get from a custom ACF user field on the post first.
	// Uncomment and adjust if you have a user relationship field.
	// $user_id = get_field( 'profile_user', $block->context['postId'] );

	// Fall back to post author.
	if ( ! $user_id ) {
		$user_id = get_post_field( 'post_author', $block->context['postId'] );
	}
}

// If still no user, try current author context.
if ( ! $user_id ) {
	$user_id = get_the_author_meta( 'ID' );
}

// Bail if no user found.
if ( ! $user_id ) {
	return '';
}

/**
 * Get expertise terms from ACF field.
 *
 * IMPORTANT: Update 'areas_of_expertise' to match your ACF field name.
 * The field should be a Taxonomy field set to return Term Objects or Term IDs.
 */
$expertise_terms = get_field( 'areas_of_expertise', 'user_' . $user_id );

// Bail if no expertise terms.
if ( empty( $expertise_terms ) ) {
	return '';
}

// Ensure we have an array.
if ( ! is_array( $expertise_terms ) ) {
	$expertise_terms = array( $expertise_terms );
}

// Build wrapper classes.
$wrapper_classes = array( 'wp-block-theme-user-expertise' );

// Get wrapper attributes from block supports.
$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => implode( ' ', $wrapper_classes ),
	)
);

// Build pill classes.
$pill_classes = array( 'wp-block-theme-user-expertise__pill' );

if ( $background_color && ! $custom_background_color ) {
	$pill_classes[] = 'has-' . $background_color . '-background-color';
	$pill_classes[] = 'has-background';
}

if ( $text_color && ! $custom_text_color ) {
	$pill_classes[] = 'has-' . $text_color . '-color';
	$pill_classes[] = 'has-text-color';
}

$pill_class_string = implode( ' ', $pill_classes );

// Build pill inline styles for custom colors.
$pill_styles = array();

if ( $custom_background_color ) {
	$pill_styles[] = 'background-color:' . esc_attr( $custom_background_color );
}

if ( $custom_text_color ) {
	$pill_styles[] = 'color:' . esc_attr( $custom_text_color );
}

$pill_style_string = ! empty( $pill_styles ) ? ' style="' . implode( ';', $pill_styles ) . '"' : '';

// Start output.
$output = '<div ' . $wrapper_attributes . '>';
$output .= '<ul class="wp-block-theme-user-expertise__list" role="list">';

foreach ( $expertise_terms as $term ) {
	// Handle both term objects and term IDs.
	if ( is_numeric( $term ) ) {
		$term = get_term( $term, 'area_of_expertise' );
	}

	// Skip if not a valid term.
	if ( ! $term || is_wp_error( $term ) ) {
		continue;
	}

	$term_name = esc_html( $term->name );
	$term_link = get_term_link( $term, 'area_of_expertise' );

	// If term links are desired, wrap in anchor. Otherwise just use span.
	// Using span by default to match your provided structure.
	$output .= '<li class="' . esc_attr( $pill_class_string ) . '"' . $pill_style_string . '>';
	$output .= $term_name;
	$output .= '</li>';
}

$output .= '</ul>';
$output .= '</div>';

echo $output;