<?php
/**
 * Render callback for the User Education block.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block content.
 * @param WP_Block $block      Block instance.
 * @return string  Rendered block HTML.
 */

// Get heading attributes.
$heading_text        = $attributes['headingText'] ?? '';
$heading_level       = $attributes['headingLevel'] ?? 'h2';
$heading_font_size   = $attributes['headingFontSize'] ?? '';
$heading_font_family = $attributes['headingFontFamily'] ?? '';

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
 * Get degrees from ACF repeater field.
 */
$degrees = get_field( 'degrees', 'user_' . $user_id );

// Bail if no degrees.
if ( empty( $degrees ) ) {
	return '';
}

// Build heading classes and styles.
$heading_classes = array( 'wp-block-caes-hub-user-education__heading' );

if ( $heading_font_family ) {
	$heading_classes[] = 'has-' . $heading_font_family . '-font-family';
}

$heading_class_string = implode( ' ', $heading_classes );

$heading_styles = array();

if ( $heading_font_size ) {
	$heading_styles[] = 'font-size:' . esc_attr( $heading_font_size );
}

$heading_style_string = ! empty( $heading_styles ) ? ' style="' . implode( ';', $heading_styles ) . '"' : '';

// Validate heading level.
$allowed_tags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p' );
if ( ! in_array( $heading_level, $allowed_tags, true ) ) {
	$heading_level = 'h2';
}

// Get wrapper attributes from block supports.
$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'wp-block-caes-hub-user-education',
	)
);

// Output the block.
$output = '<div ' . $wrapper_attributes . '>';

// Output heading if text is provided.
if ( ! empty( $heading_text ) ) {
	$output .= '<' . $heading_level . ' class="' . esc_attr( $heading_class_string ) . '"' . $heading_style_string . '>';
	$output .= esc_html( $heading_text );
	$output .= '</' . $heading_level . '>';
}

$output .= '<div class="wp-block-caes-hub-user-education__list">';

foreach ( $degrees as $degree ) {
	$degree_name    = $degree['degree_name'] ?? '';
	$field_of_study = $degree['field_of_study'] ?? '';
	$institution    = $degree['institution'] ?? '';
	$state          = $degree['state'] ?? '';
	$country        = $degree['country'] ?? '';
	$year           = $degree['year'] ?? '';

	// Skip if no degree name.
	if ( empty( $degree_name ) ) {
		continue;
	}

	// Build the degree title line.
	$title_parts = array_filter( array( $degree_name, $field_of_study ) );
	$title_line  = implode( ', ', $title_parts );

	// Build the location/year line.
	$location_parts = array_filter( array( $institution, $state, $country ) );
	$location_line  = implode( ', ', $location_parts );

	if ( $year ) {
		$location_line .= ' (' . esc_html( $year ) . ')';
	}

	$output .= '<p class="wp-block-caes-hub-user-education__degree">';
	$output .= '<strong>' . esc_html( $title_line ) . '</strong>';

	if ( ! empty( $location_line ) ) {
		$output .= '<br />';
		$output .= esc_html( trim( $location_line, ', ' ) );
	}

	$output .= '</p>';
}

$output .= '</div>';
$output .= '</div>';

echo $output;
