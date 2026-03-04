<?php

/**
 * Server-side rendering for the Motion Scroll Image block
 *
 * @package CAES_Motion_Scroll
 */

// Get block attributes and context
$slide_index = $attributes['slideIndex'] ?? 0;
$slides = $block->context['caes-hub/motion-scroll-slides'] ?? [];

// Get the selected slide
$selected_slide = $slides[$slide_index] ?? null;

// Prefer mobile-specific fields; fall back to desktop fields
$has_mobile_image = !empty($selected_slide['mobileImage']);
$image   = $has_mobile_image
	? $selected_slide['mobileImage']
	: ($selected_slide['image'] ?? null);
$duotone = !empty($selected_slide['mobileDuotone'])
	? $selected_slide['mobileDuotone']
	: ($selected_slide['duotone'] ?? null);

// Mobile always uses center crop; desktop image respects focalPoint
$focal_point = $has_mobile_image
	? ['x' => 0.5, 'y' => 0.5]
	: ($selected_slide['focalPoint'] ?? ['x' => 0.5, 'y' => 0.5]);

// Early return if no image
if (empty($image)) {
	return;
}

// Get wrapper attributes
$wrapper_attributes = get_block_wrapper_attributes([
	'class' => 'caes-motion-scroll-image',
]);

// Build CSS custom properties for focal point
$figure_styles = [];
$figure_styles[] = sprintf('--focal-x: %s%%', ($focal_point['x'] ?? 0.5) * 100);
$figure_styles[] = sprintf('--focal-y: %s%%', ($focal_point['y'] ?? 0.5) * 100);
$figure_style_attr = implode('; ', $figure_styles);

// Shared helper: build srcset string
if (! function_exists('caes_motion_scroll_build_srcset')) :
	function caes_motion_scroll_build_srcset($image) {
		if (empty($image) || empty($image['url'])) {
			return '';
		}
		$srcset_parts       = [];
		$full_url           = set_url_scheme($image['url']);
		$large_url          = set_url_scheme($image['sizes']['large']['url'] ?? $image['url']);
		$medium_large_url   = set_url_scheme($image['sizes']['medium_large']['url'] ?? $large_url);
		$full_width         = $image['width'] ?? 1920;
		$large_width        = $image['sizes']['large']['width'] ?? $full_width;
		$medium_large_width = $image['sizes']['medium_large']['width'] ?? $large_width;
		$added_urls         = [];
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

// Shared helper: generate duotone SVG filter
if (! function_exists('caes_motion_scroll_get_duotone_filter')) :
	function caes_motion_scroll_get_duotone_filter($duotone, $filter_id) {
		if (empty($duotone) || ! is_array($duotone) || count($duotone) < 2) {
			return ['', ''];
		}
		$duotone_values = ['r' => [], 'g' => [], 'b' => []];
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

// Image filter styles
$img_styles = [];
if (! empty($duotone)) {
	$filter_id = 'motion-scroll-image-' . wp_unique_id();
	list($filter_id, $svg) = caes_motion_scroll_get_duotone_filter($duotone, $filter_id);
	if ($svg) {
		echo $svg;
	}
	$img_styles[] = sprintf('filter: url(#%s)', esc_attr($filter_id));
}
$img_style_attr = ! empty($img_styles) ? implode('; ', $img_styles) : '';

// Build srcset
$srcset = caes_motion_scroll_build_srcset($image);
?>

<div <?php echo $wrapper_attributes; ?>>
	<figure class="motion-scroll-image-figure" style="<?php echo esc_attr($figure_style_attr); ?>">
		<img
			src="<?php echo esc_url(set_url_scheme($image['url'])); ?>"
			<?php if ($srcset) : ?>srcset="<?php echo $srcset; ?>"<?php endif; ?>
			sizes="100vw"
			alt="<?php echo esc_attr($image['alt'] ?? ''); ?>"
			loading="lazy"
			decoding="async"
			<?php if ($img_style_attr) : ?>style="<?php echo esc_attr($img_style_attr); ?>" <?php endif; ?>>
		<?php
		// If a mobile image is set, use mobileCaption; otherwise use desktop caption.
		$caption = $has_mobile_image
			? ($selected_slide['mobileCaption'] ?? '')
			: ($selected_slide['caption'] ?? ($image['caption'] ?? ''));
		if (! empty($caption)) : ?>
			<figcaption class="motion-scroll-image-caption">
				<?php echo wp_kses_post($caption); ?>
			</figcaption>
		<?php endif; ?>
	</figure>
</div>
