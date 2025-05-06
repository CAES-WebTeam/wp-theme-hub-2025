<?php
$customWidth =  $attributes['customWidth'] ?? '';
$image = get_template_directory_uri() . '/assets/images/expert-mark.png';
$link = !empty($attributes['link']) ? $attributes['link'] : null;
$tooltip = $attributes['tooltip'] ?? '';
?>
<div <?php echo get_block_wrapper_attributes(); ?> style="width: <?php echo esc_attr($customWidth); ?>;">
	<?php if ($image): ?>
		<div class="tooltip-container">
			<?php if ($link): ?>
				<a
					href="<?php echo esc_url($link); ?>"
					class="tooltip-trigger"
					tabindex="0"
					aria-describedby="expert-mark-tooltip">
					<img
						loading="lazy"
						src="<?php echo esc_url($image); ?>"
						alt="Written and Reviewed by Experts" />
				</a>
				<div
					role="tooltip"
					id="expert-mark-tooltip"
					class="tooltip">
					<div class="tooltip-content">
						<?php echo esc_html($tooltip); ?>
					</div>
				</div>
			<?php else: ?>
				<img
					loading="lazy"
					src="<?php echo esc_url($image); ?>"
					alt="Written and Reviewed by Experts" />
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>