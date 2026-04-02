<?php
// Get person ID -- check global first, then fall back to queried object
$author_id = isset($GLOBALS['caes_current_user_id']) ? $GLOBALS['caes_current_user_id'] : get_queried_object_id();

// Resolve to CPT post ID
$person_post_id = function_exists('resolve_person_post_id') ? resolve_person_post_id($author_id) : null;

if (!$person_post_id) {
    return;
}

$address_type = isset($block['addressType']) ? $block['addressType'] : 'mailing';
$prefix       = $address_type === 'shipping' ? 'shipping' : 'mailing';

$line1 = get_post_meta($person_post_id, $prefix . '_address', true);
$line2 = get_post_meta($person_post_id, $prefix . '_address2', true);
$city  = get_post_meta($person_post_id, $prefix . '_city', true);
$state = get_post_meta($person_post_id, $prefix . '_state', true);
$zip   = get_post_meta($person_post_id, $prefix . '_zip', true);

if (empty($line1) && empty($city)) {
    return;
}

$show_heading      = isset($block['showHeading']) ? (bool) $block['showHeading'] : false;
$heading_level     = isset($block['headingLevel']) ? (int) $block['headingLevel'] : 2;
$heading_font_size = isset($block['headingFontSize']) ? $block['headingFontSize'] : '';
$content_font_size = isset($block['contentFontSize']) ? $block['contentFontSize'] : '';

// Clamp heading level to valid range
$heading_level = max(1, min(6, $heading_level));
$heading_tag   = 'h' . $heading_level;

$heading_style = $heading_font_size ? ' style="' . esc_attr( safecss_filter_attr( 'font-size:' . $heading_font_size ) ) . '"' : '';
$content_style = $content_font_size ? ' style="' . esc_attr( safecss_filter_attr( 'font-size:' . $content_font_size ) ) . '"' : '';

$default_heading = $address_type === 'shipping'
    ? esc_html__('Shipping Address', 'caes-hub')
    : esc_html__('Mailing Address', 'caes-hub');

$attrs = $is_preview ? ' ' : get_block_wrapper_attributes(['class' => 'person-address']);

// Build address lines
$lines = array_filter([
    $line1,
    $line2,
    trim(implode(', ', array_filter([$city, $state])) . ($zip ? ' ' . $zip : '')),
]);

echo '<div ' . $attrs . '>';

if ($show_heading) {
    echo '<' . $heading_tag . ' class="wp-block-heading person-address__heading"' . $heading_style . '>' . $default_heading . '</' . $heading_tag . '>';
}

echo '<p class="person-address__content"' . $content_style . '>';
echo implode('<br>', array_map('esc_html', $lines));
echo '</p>';

echo '</div>';
?>
