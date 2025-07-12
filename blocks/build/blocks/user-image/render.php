<?php

// Get author ID
$author_id = isset($GLOBALS['caes_current_user_id']) ? $GLOBALS['caes_current_user_id'] : get_queried_object_id();

// Get block attributes
$mobile = $block['mobileVersion'] ?? false;
$aspect_ratio = $block['aspectRatio'] ?? 'auto';
$width = $block['width'] ?? 100;
$width_unit = $block['widthUnit'] ?? '%';
$full_height = $block['fullHeight'] ?? false;

// Get first and last name
$first_name = get_user_meta($author_id, 'first_name', true);
$last_name = get_user_meta($author_id, 'last_name', true);
$image_name = get_user_meta($author_id, 'image_name', true);

// Determine additional class
$mobile_class = $mobile ? 'mobile-version' : 'desktop-version';

// Generate inline styles for the figure
$figure_styles = [];
if ($full_height) {
    $figure_styles[] = 'width: 100%';
    $figure_styles[] = 'height: 100%';
} else {
    $figure_styles[] = 'width: ' . esc_attr($width . $width_unit);
}

// Generate inline styles for the image
$image_styles = [];
if ($full_height) {
    $image_styles[] = 'width: 100%';
    $image_styles[] = 'height: 100%';
    $image_styles[] = 'object-fit: cover';
} else {
    if ($aspect_ratio !== 'auto') {
        $image_styles[] = 'aspect-ratio: ' . esc_attr($aspect_ratio);
        $image_styles[] = 'object-fit: cover';
    }
}

// Attributes for wrapper (append mobile class and styles)
$attrs = get_block_wrapper_attributes([
    'class' => $mobile_class,
    'style' => implode('; ', $figure_styles)
]);

// Build image style attribute
$image_style_attr = !empty($image_styles) ? 'style="' . esc_attr(implode('; ', $image_styles)) . '"' : '';

// Echo the image with the first and last name as alt text
if (!empty($image_name)) {
    echo '<figure ' . $attrs . '>';
    echo '<img src="https://secure.caes.uga.edu/personnel/photos/' . esc_html($image_name) . '.jpg" alt="' . esc_html($first_name) . ' ' . esc_html($last_name) . '" ' . $image_style_attr . ' />';
    echo '</figure>';   
}

?>