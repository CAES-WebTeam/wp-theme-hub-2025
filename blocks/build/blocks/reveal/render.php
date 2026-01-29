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

// Calculate Min Height based on per-frame transition speeds
$frame_count = count( $frames );
$speed_multipliers = [
	'slow'   => 1.5,
	'normal' => 1,
	'fast'   => 0.5,
];

// Start with base viewport for first frame
$total_vh = 100;

// Add viewport height for each transition based on its speed
for ( $i = 1; $i < $frame_count; $i++ ) {
	$speed = $frames[ $i ]['transition']['speed'] ?? 'normal';
	$multiplier = $speed_multipliers[ $speed ] ?? 1;
	$total_vh += 100 * $multiplier;
}

$calculated_min_height = $total_vh . 'vh';

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
			'index'             => $index,
			'transition'        => $frame['transition'] ?? [ 'type' => 'fade' ],
			'desktopFocalPoint' => $frame['desktopFocalPoint'] ?? [ 'x' => 0.5, 'y' => 0.5 ],
			'mobileFocalPoint'  => $frame['mobileFocalPoint'] ?? [ 'x' => 0.5, 'y' => 0.5 ],
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
		'style' => sprintf( '--reveal-min-height: %s;', esc_attr( $calculated_min_height ) ),
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
 * Generate duotone filter SVG - matches WordPress core implementation
 *
 * @param array  $duotone  Duotone settings (array of two hex colors).
 * @param string $filter_id Filter identifier.
 * @return array Filter ID and SVG markup.
 */
if ( ! function_exists( 'caes_reveal_get_duotone_filter' ) ) :
function caes_reveal_get_duotone_filter( $duotone, $filter_id ) {
	if ( empty( $duotone ) || ! is_array( $duotone ) || count( $duotone ) < 2 ) {
		return [ '', '' ];
	}

	// Parse the two colors and convert to RGB values (0-1 range)
	$duotone_values = [
		'r' => [],
		'g' => [],
		'b' => [],
	];

	foreach ( $duotone as $color_str ) {
		$color_str = ltrim( $color_str, '#' );
		
		// Handle 3-character hex
		if ( strlen( $color_str ) === 3 ) {
			$color_str = $color_str[0] . $color_str[0] . $color_str[1] . $color_str[1] . $color_str[2] . $color_str[2];
		}

		$duotone_values['r'][] = hexdec( substr( $color_str, 0, 2 ) ) / 255;
		$duotone_values['g'][] = hexdec( substr( $color_str, 2, 2 ) ) / 255;
		$duotone_values['b'][] = hexdec( substr( $color_str, 4, 2 ) ) / 255;
	}

	ob_start();
	?>
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 0 0"
		width="0"
		height="0"
		focusable="false"
		role="none"
		style="visibility: hidden; position: absolute; left: -9999px; overflow: hidden;"
	>
		<defs>
			<filter id="<?php echo esc_attr( $filter_id ); ?>">
				<feColorMatrix
					color-interpolation-filters="sRGB"
					type="matrix"
					values=".299 .587 .114 0 0
					        .299 .587 .114 0 0
					        .299 .587 .114 0 0
					        0 0 0 1 0"
				/>
				<feComponentTransfer color-interpolation-filters="sRGB">
					<feFuncR type="table" tableValues="<?php echo esc_attr( implode( ' ', $duotone_values['r'] ) ); ?>" />
					<feFuncG type="table" tableValues="<?php echo esc_attr( implode( ' ', $duotone_values['g'] ) ); ?>" />
					<feFuncB type="table" tableValues="<?php echo esc_attr( implode( ' ', $duotone_values['b'] ) ); ?>" />
					<feFuncA type="table" tableValues="0 1" />
				</feComponentTransfer>
			</filter>
		</defs>
	</svg>
	<?php
	$svg = ob_get_clean();

	return [ $filter_id, $svg ];
}
endif;
?>

<div <?php echo $wrapper_attributes; ?> data-frames="<?php echo esc_attr( wp_json_encode( $frames_data ) ); ?>">
	<?php
	// Output duotone SVG filters for both desktop and mobile
	foreach ( $frames as $index => $frame ) :
		// Desktop duotone filter (check new property first, then legacy)
		$desktop_duotone = $frame['desktopDuotone'] ?? $frame['duotone'] ?? null;
		if ( ! empty( $desktop_duotone ) ) :
			list( $filter_id, $svg ) = caes_reveal_get_duotone_filter( $desktop_duotone, $block_id . '-' . $index . '-desktop' );
			if ( $svg ) :
				echo $svg;
			endif;
		endif;
		// Mobile duotone filter
		if ( ! empty( $frame['mobileDuotone'] ) ) :
			list( $filter_id, $svg ) = caes_reveal_get_duotone_filter( $frame['mobileDuotone'], $block_id . '-' . $index . '-mobile' );
			if ( $svg ) :
				echo $svg;
			endif;
		endif;
	endforeach;
	?>

	<div class="reveal-background" aria-hidden="true">
		<?php foreach ( $frames as $index => $frame ) :
			$desktop_image       = $frame['desktopImage'] ?? null;
			$mobile_image        = $frame['mobileImage'] ?? null;
			$desktop_focal_point = $frame['desktopFocalPoint'] ?? [ 'x' => 0.5, 'y' => 0.5 ];
			$mobile_focal_point  = $frame['mobileFocalPoint'] ?? [ 'x' => 0.5, 'y' => 0.5 ];
			$transition          = $frame['transition'] ?? [ 'type' => 'fade' ];
			$desktop_duotone     = $frame['desktopDuotone'] ?? $frame['duotone'] ?? null;
			$mobile_duotone      = $frame['mobileDuotone'] ?? null;

			if ( empty( $desktop_image ) ) {
				continue;
			}

			// Build inline styles for the frame container
			$frame_styles = [];
			$frame_styles[] = sprintf( '--desktop-focal-x: %s%%', ( $desktop_focal_point['x'] ?? 0.5 ) * 100 );
			$frame_styles[] = sprintf( '--desktop-focal-y: %s%%', ( $desktop_focal_point['y'] ?? 0.5 ) * 100 );
			$frame_styles[] = sprintf( '--mobile-focal-x: %s%%', ( $mobile_focal_point['x'] ?? 0.5 ) * 100 );
			$frame_styles[] = sprintf( '--mobile-focal-y: %s%%', ( $mobile_focal_point['y'] ?? 0.5 ) * 100 );

			$frame_style_attr = implode( '; ', $frame_styles );

			// Build classes
			$frame_classes = 'reveal-frame';
			if ( $index === 0 ) {
				$frame_classes .= ' is-active';
			}

			// Desktop image styles (with duotone if set)
			$desktop_img_styles = [];
			if ( ! empty( $desktop_duotone ) ) {
				$desktop_img_styles[] = sprintf( 'filter: url(#%s-%d-desktop)', esc_attr( $block_id ), $index );
			}
			$desktop_img_style_attr = ! empty( $desktop_img_styles ) ? implode( '; ', $desktop_img_styles ) : '';

			// Mobile image styles (with duotone if set)
			$mobile_img_styles = [];
			if ( ! empty( $mobile_duotone ) ) {
				$mobile_img_styles[] = sprintf( 'filter: url(#%s-%d-mobile)', esc_attr( $block_id ), $index );
			}
			$mobile_img_style_attr = ! empty( $mobile_img_styles ) ? implode( '; ', $mobile_img_styles ) : '';

			// Determine if we need separate desktop/mobile images (when duotones differ or mobile image exists)
			$has_mobile_image = ! empty( $mobile_image ) && ! empty( $mobile_image['url'] );
			$duotones_differ = $desktop_duotone !== $mobile_duotone;
			$use_separate_images = $has_mobile_image || $duotones_differ;
			$transition_speed = $transition['speed'] ?? 'normal';

			// Get captions
			$desktop_caption = $desktop_image['caption'] ?? '';
			$mobile_caption = $has_mobile_image ? ( $mobile_image['caption'] ?? '' ) : $desktop_caption;
			$has_caption = ! empty( $desktop_caption ) || ! empty( $mobile_caption );
			?>
			<figure
				class="<?php echo esc_attr( $frame_classes ); ?>"
				data-index="<?php echo esc_attr( $index ); ?>"
				data-transition-type="<?php echo esc_attr( $transition['type'] ?? 'fade' ); ?>"
				data-transition-speed="<?php echo esc_attr( $transition_speed ); ?>"
				style="<?php echo esc_attr( $frame_style_attr ); ?>"
			>
				<?php if ( $use_separate_images ) : ?>
					<?php // Desktop image - hidden on mobile ?>
					<img
						class="reveal-frame-desktop"
						src="<?php echo esc_url( set_url_scheme( $desktop_image['url'] ) ); ?>"
						srcset="<?php echo caes_reveal_build_srcset( $desktop_image ); ?>"
						sizes="100vw"
						alt="<?php echo esc_attr( $desktop_image['alt'] ?? '' ); ?>"
						loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>"
						decoding="async"
						<?php if ( $desktop_img_style_attr ) : ?>
							style="<?php echo esc_attr( $desktop_img_style_attr ); ?>"
						<?php endif; ?>
					>
					<?php // Mobile image - hidden on desktop ?>
					<img
						class="reveal-frame-mobile"
						src="<?php echo esc_url( set_url_scheme( $has_mobile_image ? $mobile_image['url'] : $desktop_image['url'] ) ); ?>"
						alt="<?php echo esc_attr( $has_mobile_image ? ( $mobile_image['alt'] ?? '' ) : ( $desktop_image['alt'] ?? '' ) ); ?>"
						loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>"
						decoding="async"
						<?php if ( $mobile_img_style_attr ) : ?>
							style="<?php echo esc_attr( $mobile_img_style_attr ); ?>"
						<?php endif; ?>
					>
				<?php else : ?>
					<?php // Single image when no mobile-specific needs ?>
					<img
						src="<?php echo esc_url( set_url_scheme( $desktop_image['url'] ) ); ?>"
						srcset="<?php echo caes_reveal_build_srcset( $desktop_image ); ?>"
						sizes="100vw"
						alt="<?php echo esc_attr( $desktop_image['alt'] ?? '' ); ?>"
						loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>"
						decoding="async"
						<?php if ( $desktop_img_style_attr ) : ?>
							style="<?php echo esc_attr( $desktop_img_style_attr ); ?>"
						<?php endif; ?>
					>
				<?php endif; ?>

				<?php if ( $has_caption ) : ?>
					<figcaption class="reveal-frame-caption">
						<?php if ( $use_separate_images && $desktop_caption !== $mobile_caption ) : ?>
							<span class="reveal-caption-desktop"><?php echo esc_html( $desktop_caption ); ?></span>
							<span class="reveal-caption-mobile"><?php echo esc_html( $mobile_caption ); ?></span>
						<?php else : ?>
							<?php echo esc_html( $desktop_caption ); ?>
						<?php endif; ?>
					</figcaption>
				<?php endif; ?>
			</figure>
		<?php endforeach; ?>

		<div class="reveal-overlay" style="background-color: <?php echo esc_attr( $overlay_rgba ); ?>;"></div>
	</div>

	<div class="reveal-content">
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
</div>