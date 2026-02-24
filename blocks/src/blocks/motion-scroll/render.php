<?php

/**
 * Server-side rendering for the Motion Scroll block
 *
 * Structure:
 * .caes-motion-scroll
 *   .motion-scroll-images (sticky)
 *     .motion-scroll-slide (multiple, transitioning on scroll)
 *   .motion-scroll-content (scrollable)
 *
 * @package CAES_Motion_Scroll
 */

// Get block attributes
$slides = $attributes['slides'] ?? [];
$content_position = $attributes['contentPosition'] ?? 'left';
$image_display_mode = $attributes['imageDisplayMode'] ?? 'cover';
$content_bg_color = $attributes['contentBackgroundColor'] ?? '';
$content_text_color = $attributes['contentTextColor'] ?? '';
$images_bg_color = $attributes['imagesBackgroundColor'] ?? '#000000';
$caption_text_color = $attributes['captionTextColor'] ?? '#ffffff';
$content_padding = $attributes['contentPadding'] ?? [];

/**
 * Convert spacing preset value to CSS custom property
 * Converts "var:preset|spacing|50" to "var(--wp--preset--spacing--50)"
 */
if (!function_exists('caes_motion_scroll_get_spacing_preset_css_var')) :
	function caes_motion_scroll_get_spacing_preset_css_var($value) {
		if (empty($value)) {
			return null;
		}

		// Check if it's a preset value
		if (is_string($value) && strpos($value, 'var:preset|') === 0) {
			$parts = explode('|', $value);
			if (count($parts) === 3) {
				list(, $type, $slug) = $parts;
				return sprintf('var(--wp--preset--%s--%s)', $type, $slug);
			}
		}

		// Return as-is if it's a regular CSS value
		return $value;
	}
endif;

// Early return if no slides
if (empty($slides)) {
	$wrapper_attributes = get_block_wrapper_attributes([
		'class' => 'caes-motion-scroll content-' . esc_attr($content_position) . ' image-mode-' . esc_attr($image_display_mode),
	]);
	printf(
		'<div %s><div class="motion-scroll-content">%s</div></div>',
		$wrapper_attributes,
		$content
	);
	return;
}

// Generate unique ID for this block instance
$block_id = 'motion-scroll-' . wp_unique_id();

// Get wrapper attributes
$wrapper_attributes = get_block_wrapper_attributes(
	[
		'id'    => $block_id,
		'class' => 'caes-motion-scroll content-' . esc_attr($content_position) . ' image-mode-' . esc_attr($image_display_mode),
		'data-slide-count' => count($slides),
	]
);

/**
 * Helper function to build srcset for an image
 */
if (! function_exists('caes_motion_scroll_build_srcset')) :
	function caes_motion_scroll_build_srcset($image)
	{
		if (empty($image) || empty($image['url'])) {
			return '';
		}

		$srcset_parts = [];
		$full_url         = set_url_scheme($image['url']);
		$large_url        = set_url_scheme($image['sizes']['large']['url'] ?? $image['url']);
		$medium_large_url = set_url_scheme($image['sizes']['medium_large']['url'] ?? $large_url);
		$full_width         = $image['width'] ?? 1920;
		$large_width        = $image['sizes']['large']['width'] ?? $full_width;
		$medium_large_width = $image['sizes']['medium_large']['width'] ?? $large_width;

		$added_urls = [];

		if (! in_array($medium_large_url, $added_urls, true)) {
			$srcset_parts[] = esc_url($medium_large_url) . ' ' . esc_attr($medium_large_width) . 'w';
			$added_urls[]   = $medium_large_url;
		}

		if (! in_array($large_url, $added_urls, true)) {
			$srcset_parts[] = esc_url($large_url) . ' ' . esc_attr($large_width) . 'w';
			$added_urls[]   = $large_url;
		}

		if (! in_array($full_url, $added_urls, true)) {
			$srcset_parts[] = esc_url($full_url) . ' ' . esc_attr($full_width) . 'w';
		}

		return implode(', ', $srcset_parts);
	}
endif;

/**
 * Generate duotone filter SVG
 */
if (! function_exists('caes_motion_scroll_get_duotone_filter')) :
	function caes_motion_scroll_get_duotone_filter($duotone, $filter_id)
	{
		if (empty($duotone) || ! is_array($duotone) || count($duotone) < 2) {
			return ['', ''];
		}

		$duotone_values = [
			'r' => [],
			'g' => [],
			'b' => [],
		];

		foreach ($duotone as $color_str) {
			$color_str = ltrim($color_str, '#');
			if (strlen($color_str) === 3) {
				$color_str = $color_str[0] . $color_str[0] . $color_str[1] . $color_str[1] . $color_str[2] . $color_str[2];
			}
			$duotone_values['r'][] = hexdec(substr($color_str, 0, 2)) / 255;
			$duotone_values['g'][] = hexdec(substr($color_str, 2, 2)) / 255;
			$duotone_values['b'][] = hexdec(substr($color_str, 4, 2)) / 255;
		}

		ob_start();
?>
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 0 0" width="0" height="0" focusable="false" role="none" style="visibility: hidden; position: absolute; left: -9999px; overflow: hidden;">
			<defs>
				<filter id="<?php echo esc_attr($filter_id); ?>">
					<feColorMatrix color-interpolation-filters="sRGB" type="matrix" values=".299 .587 .114 0 0 .299 .587 .114 0 0 .299 .587 .114 0 0 0 0 0 1 0" />
					<feComponentTransfer color-interpolation-filters="sRGB">
						<feFuncR type="table" tableValues="<?php echo esc_attr(implode(' ', $duotone_values['r'])); ?>" />
						<feFuncG type="table" tableValues="<?php echo esc_attr(implode(' ', $duotone_values['g'])); ?>" />
						<feFuncB type="table" tableValues="<?php echo esc_attr(implode(' ', $duotone_values['b'])); ?>" />
						<feFuncA type="table" tableValues="0 1" />
					</feComponentTransfer>
				</filter>
			</defs>
		</svg>
<?php
		$svg = ob_get_clean();
		return [$filter_id, $svg];
	}
endif;
?>

<div <?php echo $wrapper_attributes; ?>>
	<?php
	// Output duotone SVG filters
	foreach ($slides as $index => $slide) :
		if (! empty($slide['duotone'])) :
			list($filter_id, $svg) = caes_motion_scroll_get_duotone_filter($slide['duotone'], $block_id . '-' . $index);
			if ($svg) echo $svg;
		endif;
	endforeach;
	?>

	<!-- Images container (sticky) -->
	<div class="motion-scroll-images" style="background-color: <?php echo esc_attr($images_bg_color); ?>;">
		<?php foreach ($slides as $index => $slide) :
			$image = $slide['image'] ?? null;
			$focal_point = $slide['focalPoint'] ?? ['x' => 0.5, 'y' => 0.5];
			$duotone = $slide['duotone'] ?? null;

			if (empty($image)) {
				continue;
			}

			// Build CSS custom properties for focal point
			$slide_styles = [];
			$slide_styles[] = sprintf('--focal-x: %s%%', ($focal_point['x'] ?? 0.5) * 100);
			$slide_styles[] = sprintf('--focal-y: %s%%', ($focal_point['y'] ?? 0.5) * 100);
			$slide_style_attr = implode('; ', $slide_styles);

			// Image filter styles
			$img_styles = [];
			if (! empty($duotone)) {
				$img_styles[] = sprintf('filter: url(#%s-%d)', esc_attr($block_id), $index);
			}
			$img_style_attr = ! empty($img_styles) ? implode('; ', $img_styles) : '';
		?>
			<div class="motion-scroll-slide<?php echo $index === 0 ? ' is-active' : ''; ?>"
				data-slide-index="<?php echo esc_attr($index); ?>"
				style="<?php echo esc_attr($slide_style_attr); ?>">
				<figure class="motion-scroll-figure">
					<img
						src="<?php echo esc_url(set_url_scheme($image['url'])); ?>"
						srcset="<?php echo caes_motion_scroll_build_srcset($image); ?>"
						sizes="50vw"
						alt="<?php echo esc_attr($image['alt'] ?? ''); ?>"
						loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>"
						decoding="async"
						<?php if ($img_style_attr) : ?>style="<?php echo esc_attr($img_style_attr); ?>" <?php endif; ?>>
					<?php if (! empty($image['caption'])) : ?>
						<figcaption class="motion-scroll-caption" style="color: <?php echo esc_attr($caption_text_color); ?>;">
							<?php echo wp_kses_post($image['caption']); ?>
						</figcaption>
					<?php endif; ?>
				</figure>
			</div>
		<?php endforeach; ?>
	</div>

	<!-- Content container (scrollable) -->
	<div class="motion-scroll-content" style="<?php
		$content_styles = [];
		if ($content_bg_color) {
			$content_styles[] = 'background-color: ' . esc_attr($content_bg_color);
		}
		if ($content_text_color) {
			$content_styles[] = 'color: ' . esc_attr($content_text_color);
		}
		$padding_top = caes_motion_scroll_get_spacing_preset_css_var($content_padding['top'] ?? null);
		if ($padding_top) {
			$content_styles[] = 'padding-top: ' . esc_attr($padding_top);
		}
		$padding_right = caes_motion_scroll_get_spacing_preset_css_var($content_padding['right'] ?? null);
		if ($padding_right) {
			$content_styles[] = 'padding-right: ' . esc_attr($padding_right);
		}
		$padding_bottom = caes_motion_scroll_get_spacing_preset_css_var($content_padding['bottom'] ?? null);
		if ($padding_bottom) {
			$content_styles[] = 'padding-bottom: ' . esc_attr($padding_bottom);
		}
		$padding_left = caes_motion_scroll_get_spacing_preset_css_var($content_padding['left'] ?? null);
		if ($padding_left) {
			$content_styles[] = 'padding-left: ' . esc_attr($padding_left);
		}
		echo implode('; ', $content_styles);
	?>">
		<?php echo $content; ?>
	</div>
</div>
