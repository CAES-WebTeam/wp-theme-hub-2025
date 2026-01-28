<?php
/**
 * Server-side rendering for the Reveal block
 *
 * @package CAES_Reveal
 */

// Get block attributes
$frames          = $attributes['frames'] ?? [];
$overlay_color   = $attributes['overlayColor'] ?? '#000000';
$overlay_opacity = $attributes['overlayOpacity'] ?? 30;
$min_height      = $attributes['minHeight'] ?? '100vh';

// Early return if no frames - but still output content
if ( empty( $frames ) ) {
	$wrapper_attributes = get_block_wrapper_attributes( [ 'class' => 'caes-reveal' ] );
	printf(
		'<div %s><div class="reveal-content">%s</div></div>',
		$wrapper_attributes,
		$content
	);
	return;
}

// Generate unique ID for this block instance
$block_id = 'reveal-' . wp_unique_id();

// Calculate overlay rgba
$hex = ltrim( $overlay_color, '#' );
$r   = hexdec( substr( $hex, 0, 2 ) );
$g   = hexdec( substr( $hex, 2, 2 ) );
$b   = hexdec( substr( $hex, 4, 2 ) );
$overlay_rgba = sprintf( 'rgba(%d, %d, %d, %s)', $r, $g, $b, $overlay_opacity / 100 );

// Prepare frames data for JS
$frames_data = array_map(
	function ( $frame, $index ) {
		return [
			'index'      => $index,
			'transition' => $frame['transition'] ?? [ 'type' => 'fade', 'speed' => 500 ],
			'focalPoint' => $frame['focalPoint'] ?? [ 'x' => 0.5, 'y' => 0.5 ],
		];
	},
	$frames,
	array_keys( $frames )
);

// Get wrapper attributes
$wrapper_classes = 'caes-reveal';
$wrapper_attributes = get_block_wrapper_attributes(
	[
		'id'    => $block_id,
		'class' => $wrapper_classes,
		'style' => sprintf( '--reveal-min-height: %s;', esc_attr( $min_height ) ),
	]
);

/**
 * Helper function to build srcset for an image
 *
 * @param array $image Image data array.
 * @return string Srcset attribute value.
 */
if ( ! function_exists( 'caes_reveal_build_srcset' ) ) :
function caes_reveal_build_srcset( $image ) {
	if ( empty( $image ) || empty( $image['url'] ) ) {
		return '';
	}

	$srcset_parts = [];

	// Get URLs with protocol fix
	$full_url         = set_url_scheme( $image['url'] );
	$large_url        = set_url_scheme( $image['sizes']['large']['url'] ?? $image['url'] );
	$medium_large_url = set_url_scheme( $image['sizes']['medium_large']['url'] ?? $large_url );

	// Get actual widths
	$full_width         = $image['width'] ?? 1920;
	$large_width        = $image['sizes']['large']['width'] ?? $full_width;
	$medium_large_width = $image['sizes']['medium_large']['width'] ?? $large_width;

	// Build srcset avoiding duplicates
	$added_urls = [];

	if ( ! in_array( $medium_large_url, $added_urls, true ) ) {
		$srcset_parts[] = esc_url( $medium_large_url ) . ' ' . esc_attr( $medium_large_width ) . 'w';
		$added_urls[]   = $medium_large_url;
	}

	if ( ! in_array( $large_url, $added_urls, true ) ) {
		$srcset_parts[] = esc_url( $large_url ) . ' ' . esc_attr( $large_width ) . 'w';
		$added_urls[]   = $large_url;
	}

	if ( ! in_array( $full_url, $added_urls, true ) ) {
		$srcset_parts[] = esc_url( $full_url ) . ' ' . esc_attr( $full_width ) . 'w';
	}

	return implode( ', ', $srcset_parts );
}
endif;

/**
 * Generate duotone filter CSS
 *
 * @param array  $duotone  Duotone settings.
 * @param string $frame_id Frame identifier.
 * @return array Filter ID and CSS.
 */
if ( ! function_exists( 'caes_reveal_get_duotone_filter' ) ) :
function caes_reveal_get_duotone_filter( $duotone, $frame_id ) {
	if ( empty( $duotone ) || ! is_array( $duotone ) ) {
		return [ '', '' ];
	}

	$filter_id = 'duotone-' . $frame_id;

	// Parse colors - duotone is array of two hex colors
	$colors = $duotone;
	if ( count( $colors ) < 2 ) {
		return [ '', '' ];
	}

	// Convert hex to RGB values (0-1 range)
	$shadow_hex    = ltrim( $colors[0], '#' );
	$highlight_hex = ltrim( $colors[1], '#' );

	$shadow_r    = hexdec( substr( $shadow_hex, 0, 2 ) ) / 255;
	$shadow_g    = hexdec( substr( $shadow_hex, 2, 2 ) ) / 255;
	$shadow_b    = hexdec( substr( $shadow_hex, 4, 2 ) ) / 255;
	$highlight_r = hexdec( substr( $highlight_hex, 0, 2 ) ) / 255;
	$highlight_g = hexdec( substr( $highlight_hex, 2, 2 ) ) / 255;
	$highlight_b = hexdec( substr( $highlight_hex, 4, 2 ) ) / 255;

	$svg = sprintf(
		'<svg xmlns="http://www.w3.org/2000/svg" style="display:none;">
			<filter id="%s">
				<feColorMatrix type="matrix" values="
					%f %f 0 0 %f
					%f %f 0 0 %f
					%f %f 0 0 %f
					0 0 0 1 0
				"/>
			</filter>
		</svg>',
		esc_attr( $filter_id ),
		$highlight_r - $shadow_r, $shadow_r, 0, $shadow_r,
		$highlight_g - $shadow_g, $shadow_g, 0, $shadow_g,
		$highlight_b - $shadow_b, $shadow_b, 0, $shadow_b
	);

	return [ $filter_id, $svg ];
}
endif;
?>

<div <?php echo $wrapper_attributes; ?> data-frames="<?php echo esc_attr( wp_json_encode( $frames_data ) ); ?>">
	<?php
	// Output duotone SVG filters
	foreach ( $frames as $index => $frame ) :
		if ( ! empty( $frame['duotone'] ) ) :
			list( $filter_id, $svg ) = caes_reveal_get_duotone_filter( $frame['duotone'], $block_id . '-' . $index );
			if ( $svg ) :
				echo $svg;
			endif;
		endif;
	endforeach;
	?>

	<div class="reveal-background" aria-hidden="true">
		<?php foreach ( $frames as $index => $frame ) :
			$desktop_image = $frame['desktopImage'] ?? null;
			$mobile_image  = $frame['mobileImage'] ?? null;
			$focal_point   = $frame['focalPoint'] ?? [ 'x' => 0.5, 'y' => 0.5 ];
			$transition    = $frame['transition'] ?? [ 'type' => 'fade', 'speed' => 500 ];
			$duotone       = $frame['duotone'] ?? null;

			if ( empty( $desktop_image ) ) {
				continue;
			}

			// Build inline styles
			$frame_styles = [];
			$frame_styles[] = sprintf( '--focal-x: %s%%', ( $focal_point['x'] ?? 0.5 ) * 100 );
			$frame_styles[] = sprintf( '--focal-y: %s%%', ( $focal_point['y'] ?? 0.5 ) * 100 );
			$frame_styles[] = sprintf( '--transition-speed: %dms', $transition['speed'] ?? 500 );

			// Duotone filter
			if ( ! empty( $duotone ) ) {
				$filter_id = 'duotone-' . $block_id . '-' . $index;
				$frame_styles[] = sprintf( 'filter: url(#%s)', esc_attr( $filter_id ) );
			}

			$frame_style_attr = implode( '; ', $frame_styles );

			// Build classes
			$frame_classes = 'reveal-frame';
			if ( $index === 0 ) {
				$frame_classes .= ' is-active';
			}
			?>
			<div
				class="<?php echo esc_attr( $frame_classes ); ?>"
				data-index="<?php echo esc_attr( $index ); ?>"
				data-transition-type="<?php echo esc_attr( $transition['type'] ?? 'fade' ); ?>"
				data-transition-speed="<?php echo esc_attr( $transition['speed'] ?? 500 ); ?>"
				style="<?php echo esc_attr( $frame_style_attr ); ?>"
			>
				<picture>
					<?php if ( ! empty( $mobile_image ) && ! empty( $mobile_image['url'] ) ) : ?>
						<source
							media="(max-width: 768px)"
							srcset="<?php echo esc_url( set_url_scheme( $mobile_image['url'] ) ); ?>"
						>
					<?php endif; ?>
					<img
						src="<?php echo esc_url( set_url_scheme( $desktop_image['url'] ) ); ?>"
						srcset="<?php echo caes_reveal_build_srcset( $desktop_image ); ?>"
						sizes="100vw"
						alt="<?php echo esc_attr( $desktop_image['alt'] ?? '' ); ?>"
						loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>"
						decoding="async"
					>
				</picture>
			</div>
		<?php endforeach; ?>

		<div class="reveal-overlay" style="background-color: <?php echo esc_attr( $overlay_rgba ); ?>;"></div>
	</div>

	<div class="reveal-content">
		<?php echo $content; ?>
	</div>
</div>