<?php
// Get person ID -- check global first, then fall back to queried object
$author_id = isset($GLOBALS['caes_current_user_id']) ? $GLOBALS['caes_current_user_id'] : get_queried_object_id();

// Resolve to CPT post ID
$person_post_id = function_exists('resolve_person_post_id') ? resolve_person_post_id($author_id) : null;

if (!$person_post_id) {
    return;
}

$distinctions = get_field('elements_distinctions', $person_post_id);

if (empty($distinctions)) {
    return;
}

$show_heading      = isset($attributes['showHeading']) ? (bool) $attributes['showHeading'] : false;
$heading_level     = isset($attributes['headingLevel']) ? (int) $attributes['headingLevel'] : 2;
$heading_font_size = isset($attributes['headingFontSize']) ? $attributes['headingFontSize'] : '';
$item_font_size    = isset($attributes['itemFontSize']) ? $attributes['itemFontSize'] : '';

// Clamp heading level to valid range
$heading_level = max(1, min(6, $heading_level));
$heading_tag   = 'h' . $heading_level;

$heading_style = $heading_font_size ? ' style="' . esc_attr( safecss_filter_attr( 'font-size:' . $heading_font_size ) ) . '"' : '';
$list_style    = $item_font_size ? ' style="' . esc_attr( safecss_filter_attr( 'font-size:' . $item_font_size ) ) . '"' : '';

$attrs = get_block_wrapper_attributes(['class' => 'person-awards']);

echo '<div ' . $attrs . '>';

if ($show_heading) {
    echo '<' . $heading_tag . ' class="wp-block-heading person-awards__heading"' . $heading_style . '>' . esc_html__('Awards and Honors', 'caes-hub') . '</' . $heading_tag . '>';
}

// Sort latest to oldest by distinction_date (year string, descending)
usort($distinctions, function($a, $b) {
    $ya = !empty($a['distinction_date']) ? (int) $a['distinction_date'] : 0;
    $yb = !empty($b['distinction_date']) ? (int) $b['distinction_date'] : 0;
    return $yb - $ya;
});

echo '<ul class="wp-block-list person-awards__list"' . $list_style . '>';
foreach ($distinctions as $item) {
    $title = ! empty($item['distinction_title']) ? $item['distinction_title'] : '';
    $date  = ! empty($item['distinction_date']) ? $item['distinction_date'] : '';

    if (empty($title)) {
        continue;
    }

    $suffix = $date ? ' (' . esc_html($date) . ')' : '';
    echo '<li>' . esc_html($title) . $suffix . '</li>';
}
echo '</ul>';

echo '</div>';
?>
