<?php
// Get person ID -- check global first, then fall back to queried object
$author_id = isset($GLOBALS['caes_current_user_id']) ? $GLOBALS['caes_current_user_id'] : get_queried_object_id();

// Resolve to CPT post ID
$person_post_id = function_exists('resolve_person_post_id') ? resolve_person_post_id($author_id) : null;

if (!$person_post_id) {
    return;
}

$content = get_field('elements_overview', $person_post_id);

if (empty($content)) {
    return;
}

$show_heading      = isset($attributes['showHeading']) ? (bool) $attributes['showHeading'] : false;
$heading_level     = isset($attributes['headingLevel']) ? (int) $attributes['headingLevel'] : 2;
$heading_font_size = isset($attributes['headingFontSize']) ? $attributes['headingFontSize'] : '';

// Clamp heading level to valid range
$heading_level = max(1, min(6, $heading_level));
$heading_tag   = 'h' . $heading_level;

$heading_style = $heading_font_size ? ' style="' . esc_attr( safecss_filter_attr( 'font-size:' . $heading_font_size ) ) . '"' : '';

$attrs = get_block_wrapper_attributes(['class' => 'person-overview']);

echo '<div ' . $attrs . '>';

if ($show_heading) {
    echo '<' . $heading_tag . ' class="wp-block-heading person-overview__heading"' . $heading_style . '>' . esc_html__('About', 'caes-hub') . '</' . $heading_tag . '>';
}

echo '<div class="person-overview__content">' . wp_kses_post(wpautop($content)) . '</div>';

echo '</div>';
?>
