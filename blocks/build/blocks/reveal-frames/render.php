<?php
/**
 * Server-side rendering for the Reveal Frames block
 *
 * @package CAES_Reveal
 */

$frame_index = $attributes['frameIndex'] ?? 0;
$frame_label = $attributes['frameLabel'] ?? '';

// Output the frame content wrapper that the parent block expects
printf(
	'<div class="reveal-frame-content" data-frame-index="%d" data-frame-label="%s">%s</div>',
	esc_attr( $frame_index ),
	esc_attr( $frame_label ),
	$content
);