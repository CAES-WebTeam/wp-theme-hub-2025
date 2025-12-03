<?php
$customWidth =  $attributes['customWidth'] ?? '';
$image = get_template_directory_uri() . '/assets/images/expert-mark.png';
$link = !empty($attributes['link']) ? $attributes['link'] : null;
$tooltip = $attributes['tooltip'] ?? '';
$useMobileVersion = $attributes['useMobileVersion'] ?? false;
?>

<?php if ($useMobileVersion): ?>
	<!-- Mobile Version: Horizontal Card Layout -->
	<div <?php echo get_block_wrapper_attributes(['class' => 'hide-tablet-desktop expert-mark-mobile']); ?> 
		style="background-color:#f4f1ef;padding:var(--wp--preset--spacing--30) var(--wp--preset--spacing--50);display:flex;flex-wrap:nowrap;gap:var(--wp--preset--spacing--40);align-items:center;">
		<?php if ($image): ?>
			<div class="expert-mark-image" style="flex-shrink:0;width:100px;">
				<?php if ($link): ?>
					<a href="<?php echo esc_url($link); ?>">
						<img
							loading="lazy"
							src="<?php echo esc_url($image); ?>"
							alt="Written and Reviewed by Experts"
							style="width:100px;display:block;" />
					</a>
				<?php else: ?>
					<img
						loading="lazy"
						src="<?php echo esc_url($image); ?>"
						alt="Written and Reviewed by Experts"
						style="width:100px;display:block;" />
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<div class="expert-mark-text" style="font-size:0.75rem;line-height:1.2;">
			<?php if ($link): ?>
				<strong>This resource was written and reviewed by experts. </strong><a href="<?php echo esc_url($link); ?>">Learn more about how we produce science you can trust.</a>
			<?php else: ?>
				<strong>This resource was written and reviewed by experts.</strong> Learn more about how we produce science you can trust.
			<?php endif; ?>
		</div>
	</div>
<?php else: ?>
	<!-- Original Version: Tooltip with Image -->
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
<?php endif; ?>