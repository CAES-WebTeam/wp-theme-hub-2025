<?php

/**
 * Server-side rendering for the Motion Scroll block
 *
 * Structure:
 * .caes-motion-scroll
 *   .motion-scroll-media (sticky, holds all frame images)
 *     .motion-scroll-frame (position: absolute, opacity controlled by JS)
 *   .motion-scroll-content (scrollable, holds all frame content sections)
 *     .motion-scroll-section
 *
 * @package CAES_Motion_Scroll
 */

// Get block attributes
$frames           = $attributes['frames'] ?? [];
$content_position = $attributes['contentPosition'] ?? 'left';
$media_width      = $attributes['mediaWidth'] ?? 50;
$overlay_color    = $attributes['overlayColor'] ?? '#000000';
$overlay_opacity  = $attributes['overlayOpacity'] ?? 0;

// Early return if no frames
if (empty($frames)) {
	$wrapper_attributes = get_block_wrapper_attributes(['class' => 'caes-motion-scroll']);
	printf(
		'<div %s><div class="motion-scroll-content">%s</div></div>',
		$wrapper_attributes,
		$content
	);
	return;
}

// Generate unique ID for this block instance
$block_id = 'motion-scroll-' . wp_unique_id();

// Calculate overlay rgba
$hex = ltrim($overlay_color, '#');
$r   = hexdec(substr($hex, 0, 2));
$g   = hexdec(substr($hex, 2, 2));
$b   = hexdec(substr($hex, 4, 2));
$overlay_rgba = sprintf('rgba(%d, %d, %d, %s)', $r, $g, $b, $overlay_opacity / 100);

// Calculate grid columns based on content position
$content_width = 100 - $media_width;
$grid_columns = $content_position === 'left' 
	? "{$content_width}% {$media_width}%" 
	: "{$media_width}% {$content_width}%";

// Get wrapper attributes
$wrapper_attributes = get_block_wrapper_attributes(
	[
		'id'                    => $block_id,
		'class'                 => 'caes-motion-scroll',
		'data-content-position' => $content_position,
		'data-frame-count'      => count($frames),
		'style'                 => "--motion-scroll-media-width: {$media_width}%; --motion-scroll-content-width: {$content_width}%;",
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
		$full_width         = $image['width'] ?? 1200;
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

/**
 * Parse inner blocks to extract frame-specific content
 */
if (! function_exists('caes_motion_scroll_parse_frame_content')) :
	function caes_motion_scroll_parse_frame_content($content)
	{
		$frame_contents = [];

		if (empty($content)) {
			return $frame_contents;
		}

		libxml_use_internal_errors(true);
		$doc = new DOMDocument();
		$doc->loadHTML('<?xml encoding="UTF-8"><div id="parse-wrapper">' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		libxml_clear_errors();

		$xpath = new DOMXPath($doc);
		$frame_divs = $xpath->query('//div[contains(@class, "motion-scroll-frame-content")]');

		foreach ($frame_divs as $div) {
			$frame_index = $div->getAttribute('data-frame-index');
			if ($frame_index !== '') {
				$inner_html = '';
				foreach ($div->childNodes as $child) {
					$inner_html .= $doc->saveHTML($child);
				}
				$frame_contents[(int) $frame_index] = $inner_html;
			}
		}

		return $frame_contents;
	}
endif;

// Parse frame-specific content from inner blocks
$frame_contents = caes_motion_scroll_parse_frame_content($content);
?>

<div <?php echo $wrapper_attributes; ?>>
	<?php
	// Output duotone SVG filters
	foreach ($frames as $index => $frame) :
		$desktop_duotone = $frame['desktopDuotone'] ?? $frame['duotone'] ?? null;
		if (! empty($desktop_duotone)) :
			list($filter_id, $svg) = caes_motion_scroll_get_duotone_filter($desktop_duotone, $block_id . '-' . $index . '-desktop');
			if ($svg) echo $svg;
		endif;
		if (! empty($frame['mobileDuotone'])) :
			list($filter_id, $svg) = caes_motion_scroll_get_duotone_filter($frame['mobileDuotone'], $block_id . '-' . $index . '-mobile');
			if ($svg) echo $svg;
		endif;
	endforeach;
	?>

	<!-- Media column (sticky) -->
	<div class="motion-scroll-media">
		<?php foreach ($frames as $index => $frame) :
			$desktop_image       = $frame['desktopImage'] ?? null;
			$mobile_image        = $frame['mobileImage'] ?? null;
			$desktop_focal_point = $frame['desktopFocalPoint'] ?? ['x' => 0.5, 'y' => 0.5];
			$mobile_focal_point  = $frame['mobileFocalPoint'] ?? ['x' => 0.5, 'y' => 0.5];
			$desktop_duotone     = $frame['desktopDuotone'] ?? $frame['duotone'] ?? null;
			$mobile_duotone      = $frame['mobileDuotone'] ?? null;
			$transition_type     = $frame['transition']['type'] ?? 'fade';
			$transition_speed    = $frame['transition']['speed'] ?? 'normal';

			if (empty($desktop_image)) {
				continue;
			}

			// Build CSS custom properties for focal points
			$frame_styles = [];
			$frame_styles[] = sprintf('--desktop-focal-x: %s%%', ($desktop_focal_point['x'] ?? 0.5) * 100);
			$frame_styles[] = sprintf('--desktop-focal-y: %s%%', ($desktop_focal_point['y'] ?? 0.5) * 100);
			$frame_styles[] = sprintf('--mobile-focal-x: %s%%', ($mobile_focal_point['x'] ?? 0.5) * 100);
			$frame_styles[] = sprintf('--mobile-focal-y: %s%%', ($mobile_focal_point['y'] ?? 0.5) * 100);
			$frame_style_attr = implode('; ', $frame_styles);

			// Desktop image filter styles
			$desktop_img_styles = [];
			if (! empty($desktop_duotone)) {
				$desktop_img_styles[] = sprintf('filter: url(#%s-%d-desktop)', esc_attr($block_id), $index);
			}
			$desktop_img_style_attr = ! empty($desktop_img_styles) ? implode('; ', $desktop_img_styles) : '';

			// Mobile image filter styles
			$mobile_img_styles = [];
			if (! empty($mobile_duotone)) {
				$mobile_img_styles[] = sprintf('filter: url(#%s-%d-mobile)', esc_attr($block_id), $index);
			}
			$mobile_img_style_attr = ! empty($mobile_img_styles) ? implode('; ', $mobile_img_styles) : '';

			// Determine if we need separate mobile/desktop images
			$has_mobile_image = ! empty($mobile_image) && ! empty($mobile_image['url']);
			$duotones_differ = $desktop_duotone !== $mobile_duotone;
			$use_separate_images = $has_mobile_image || $duotones_differ;

			// Captions
			$desktop_caption_text = $desktop_image['captionText'] ?? $desktop_image['caption'] ?? '';
			$desktop_caption_link = $desktop_image['captionLink'] ?? '';
			$mobile_caption_text = $has_mobile_image 
				? ($mobile_image['captionText'] ?? $mobile_image['caption'] ?? '') 
				: $desktop_caption_text;
			$mobile_caption_link = $has_mobile_image 
				? ($mobile_image['captionLink'] ?? '') 
				: $desktop_caption_link;
			$has_caption = ! empty($desktop_caption_text) || ! empty($mobile_caption_text);
		?>
			<div class="motion-scroll-frame<?php echo $index === 0 ? ' is-active' : ''; ?>"
				data-frame-index="<?php echo esc_attr($index); ?>"
				data-transition="<?php echo esc_attr($transition_type); ?>"
				data-speed="<?php echo esc_attr($transition_speed); ?>">
				<figure class="motion-scroll-figure" style="<?php echo esc_attr($frame_style_attr); ?>">
					<?php if ($use_separate_images) : ?>
						<img
							class="motion-scroll-img-desktop"
							src="<?php echo esc_url(set_url_scheme($desktop_image['url'])); ?>"
							srcset="<?php echo caes_motion_scroll_build_srcset($desktop_image); ?>"
							sizes="<?php echo esc_attr($media_width); ?>vw"
							alt="<?php echo esc_attr($desktop_image['alt'] ?? ''); ?>"
							loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>"
							decoding="async"
							<?php if ($desktop_img_style_attr) : ?>style="<?php echo esc_attr($desktop_img_style_attr); ?>" <?php endif; ?>>
						<img
							class="motion-scroll-img-mobile"
							src="<?php echo esc_url(set_url_scheme($has_mobile_image ? $mobile_image['url'] : $desktop_image['url'])); ?>"
							alt="<?php echo esc_attr($has_mobile_image ? ($mobile_image['alt'] ?? '') : ($desktop_image['alt'] ?? '')); ?>"
							loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>"
							decoding="async"
							<?php if ($mobile_img_style_attr) : ?>style="<?php echo esc_attr($mobile_img_style_attr); ?>" <?php endif; ?>>
					<?php else : ?>
						<img
							src="<?php echo esc_url(set_url_scheme($desktop_image['url'])); ?>"
							srcset="<?php echo caes_motion_scroll_build_srcset($desktop_image); ?>"
							sizes="<?php echo esc_attr($media_width); ?>vw"
							alt="<?php echo esc_attr($desktop_image['alt'] ?? ''); ?>"
							loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>"
							decoding="async"
							<?php if ($desktop_img_style_attr) : ?>style="<?php echo esc_attr($desktop_img_style_attr); ?>" <?php endif; ?>>
					<?php endif; ?>

					<?php if ($overlay_opacity > 0) : ?>
						<div class="motion-scroll-overlay" style="background-color: <?php echo esc_attr($overlay_rgba); ?>;"></div>
					<?php endif; ?>

					<?php if ($has_caption) : ?>
						<figcaption class="motion-scroll-caption">
							<?php if ($use_separate_images && ($desktop_caption_text !== $mobile_caption_text || $desktop_caption_link !== $mobile_caption_link)) : ?>
								<span class="motion-scroll-caption-desktop">
									<?php if (! empty($desktop_caption_link)) : ?>
										<a href="<?php echo esc_url($desktop_caption_link); ?>"><?php echo esc_html($desktop_caption_text); ?></a>
									<?php else : ?>
										<?php echo esc_html($desktop_caption_text); ?>
									<?php endif; ?>
								</span>
								<span class="motion-scroll-caption-mobile">
									<?php if (! empty($mobile_caption_link)) : ?>
										<a href="<?php echo esc_url($mobile_caption_link); ?>"><?php echo esc_html($mobile_caption_text); ?></a>
									<?php else : ?>
										<?php echo esc_html($mobile_caption_text); ?>
									<?php endif; ?>
								</span>
							<?php else : ?>
								<?php if (! empty($desktop_caption_link)) : ?>
									<a href="<?php echo esc_url($desktop_caption_link); ?>"><?php echo esc_html($desktop_caption_text); ?></a>
								<?php else : ?>
									<?php echo esc_html($desktop_caption_text); ?>
								<?php endif; ?>
							<?php endif; ?>
						</figcaption>
					<?php endif; ?>
				</figure>
			</div>
		<?php endforeach; ?>
	</div>

	<!-- Content column (scrollable) -->
	<div class="motion-scroll-content">
		<?php foreach ($frames as $index => $frame) :
			$desktop_image = $frame['desktopImage'] ?? null;

			if (empty($desktop_image)) {
				continue;
			}
		?>
			<section class="motion-scroll-section" data-frame-index="<?php echo esc_attr($index); ?>">
				<div class="motion-scroll-section-inner">
					<?php if (isset($frame_contents[$index])) : ?>
						<?php echo $frame_contents[$index]; ?>
					<?php endif; ?>
				</div>
			</section>
		<?php endforeach; ?>
	</div>
</div>
