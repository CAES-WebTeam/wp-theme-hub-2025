<?php
$customWidth =  $attributes['customWidth'] ?? '';
$image = get_template_directory_uri() . '/assets/images/expert-mark.png';
$link = !empty($attributes['link']) ? $attributes['link'] : null;
$tooltip = $attributes['tooltip'] ?? '';
?>
<div <?php echo get_block_wrapper_attributes(); ?> style="max-width: <?php echo esc_attr($customWidth); ?>;" >
	<?php if ($image): ?>
		<div class="tooltip-container">
			<?php if ($link): ?>
				<a
					href="<?php echo esc_url($link); ?>"
					class="tooltip-trigger">
					<img
						loading="lazy"
						src="<?php echo esc_url($image); ?>"
						alt="Written and Reviewed by Experts" />
				<span class="info"><span class="info-text"><?php echo esc_html($tooltip); ?></span></span>
				</a>
			<?php else: ?>
				<img
					loading="lazy"
					src="<?php echo esc_url($image); ?>"
					alt="Written and Reviewed by Experts" />
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>