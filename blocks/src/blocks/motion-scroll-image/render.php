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
$image = $selected_slide['image'] ?? null;

// Early return if no image
if (empty($image)) {
	return;
}

// Get wrapper attributes
$wrapper_attributes = get_block_wrapper_attributes([
	'class' => 'caes-motion-scroll-image',
]);

$focal_point = $selected_slide['focalPoint'] ?? ['x' => 0.5, 'y' => 0.5];
$duotone = $selected_slide['duotone'] ?? null;

// Build CSS custom properties for focal point
$figure_styles = [];
$figure_styles[] = sprintf('--focal-x: %s%%', ($focal_point['x'] ?? 0.5) * 100);
$figure_styles[] = sprintf('--focal-y: %s%%', ($focal_point['y'] ?? 0.5) * 100);
$figure_style_attr = implode('; ', $figure_styles);

// Image filter styles
$img_styles = [];
if (! empty($duotone)) {
	// Generate a unique filter ID for this instance
	$filter_id = 'motion-scroll-image-' . wp_unique_id();

	// Generate duotone filter (reuse function from parent block if available)
	if (function_exists('caes_motion_scroll_get_duotone_filter')) {
		list($filter_id, $svg) = caes_motion_scroll_get_duotone_filter($duotone, $filter_id);
		if ($svg) {
			echo $svg;
		}
		$img_styles[] = sprintf('filter: url(#%s)', esc_attr($filter_id));
	}
}
$img_style_attr = ! empty($img_styles) ? implode('; ', $img_styles) : '';

// Build srcset (reuse function from parent block if available)
$srcset = '';
if (function_exists('caes_motion_scroll_build_srcset')) {
	$srcset = caes_motion_scroll_build_srcset($image);
}
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
		// Use custom caption if provided, otherwise fall back to image caption
		$caption = $selected_slide['caption'] ?? $image['caption'] ?? '';
		if (! empty($caption)) : ?>
			<figcaption class="motion-scroll-image-caption">
				<?php echo wp_kses_post($caption); ?>
			</figcaption>
		<?php endif; ?>
	</figure>
</div>
