<?php
// Get person ID -- check global first, then fall back to queried object
$author_id = isset($GLOBALS['caes_current_user_id']) ? $GLOBALS['caes_current_user_id'] : get_queried_object_id();

// Resolve to CPT post ID
$person_post_id = function_exists('resolve_person_post_id') ? resolve_person_post_id($author_id) : null;

if (!$person_post_id) {
    return;
}

if (function_exists('is_person_active') && !is_person_active($person_post_id)) {
    return;
}

$degrees = get_field('elements_degrees', $person_post_id);

if (empty($degrees)) {
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

$attrs = get_block_wrapper_attributes(['class' => 'person-education']);

echo '<div ' . $attrs . '>';

if ($show_heading) {
    echo '<' . $heading_tag . ' class="wp-block-heading person-education__heading"' . $heading_style . '>' . esc_html__('Education', 'caes-hub') . '</' . $heading_tag . '>';
}

// Sort latest to oldest; degrees without a year go to the bottom
usort($degrees, function($a, $b) {
    $ya = !empty($a['degree_year']) ? (int) $a['degree_year'] : 0;
    $yb = !empty($b['degree_year']) ? (int) $b['degree_year'] : 0;
    return $yb - $ya;
});

echo '<div class="person-education__list"' . $list_style . '>';
foreach ($degrees as $degree) {
    $name           = ! empty($degree['degree_name'])           ? $degree['degree_name']           : '';
    $field_of_study = ! empty($degree['degree_field_of_study']) ? $degree['degree_field_of_study'] : '';
    $institution    = ! empty($degree['degree_institution'])    ? $degree['degree_institution']    : '';
    $state          = ! empty($degree['degree_state'])          ? $degree['degree_state']          : '';
    $country        = ! empty($degree['degree_country'])        ? $degree['degree_country']        : '';
    $year           = ! empty($degree['degree_year'])           ? $degree['degree_year']           : '';

    if (empty($name) && empty($institution)) {
        continue;
    }

    // "Doctor of Philosophy, Biology/Biological Sciences, General"
    $title_parts = array_filter([$name, $field_of_study]);
    $title = implode(', ', $title_parts);

    // "Utah State University, UT, United States (1992)"
    $location_parts = array_filter([$institution, $state, $country]);
    $institution_line = implode(', ', $location_parts);
    if ($year) {
        $institution_line .= ' (' . $year . ')';
    }

    echo '<div class="person-education__item">';
    if ($title) {
        echo '<p class="person-education__item-title"><strong>' . esc_html($title) . '</strong></p>';
    }
    if ($institution_line) {
        echo '<p class="person-education__item-institution">' . esc_html($institution_line) . '</p>';
    }
    echo '</div>';
}
echo '</div>';

echo '</div>';
?>
