<?php

/**
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */
?>

<?php
$version =  $attributes['version'];
$customWidth =  $attributes['customWidth'];

// Get the current post type
$currentPostType = get_post_type(); 

// Get the custom field value for event_type
$eventType = get_field('event_type'); // ACF custom field for event type

?>

<div <?php echo get_block_wrapper_attributes(); ?> style="width: <?php echo esc_attr($customWidth); ?>;">
	<?php 
	$image = '';

	// Check if the current post type is 'event' and the event type is 'Extension'
	if ($currentPostType === 'events' && !empty($eventType) && $eventType === 'Extension') {
		if ($version === 'dark') {
			$image = get_template_directory_uri() . '/assets/images/Extension_logo_Formal_FC.png';
		} elseif ($version === 'light') {
			$image = get_template_directory_uri() . '/assets/images/Extension_logo_Formal_CW.png';
		}
	} else {
		// Default to CAES logos for all other cases
		if ($version === 'dark') {
			$image = get_template_directory_uri() . '/assets/images/caes-logo-horizontal.png';
		} elseif ($version === 'light') {
			$image = get_template_directory_uri() . '/assets/images/caes-logo-horizontal-cw.png';
		}
	}

	if ($image): ?>
		<img loading="lazy" class="caes-hub-content-logo" src="<?php echo esc_url($image); ?>" alt="<?php echo ($currentPostType === 'events' && $eventType === 'Extension') ? 'UGA Extension' : 'UGA College of Agricultural & Environmental Sciences'; ?>" />
	<?php endif; ?>
</div>

