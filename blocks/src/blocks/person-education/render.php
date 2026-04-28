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
$highest_only      = !empty($attributes['highestOnly']);

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

// Rank a degree by perceived level (higher = more advanced).
// Heuristic based on common degree-name keywords; falls back to 0 for unknowns.
$rank_degree = function($degree) {
    $name = strtolower(trim($degree['degree_name'] ?? ''));
    if ($name === '') return 0;
    if (preg_match('/\b(ph\.?d|d\.?phil|doctor of philosophy|doctorate|d\.?ed|ed\.?d|d\.?sc|sc\.?d|j\.?d|m\.?d|d\.?v\.?m)\b/', $name)) return 4;
    if (preg_match('/\b(master|m\.?s|m\.?a|m\.?b\.?a|m\.?ed|m\.?eng|m\.?p\.?h|m\.?f\.?a)\b/', $name)) return 3;
    if (preg_match('/\b(bachelor|b\.?s|b\.?a|b\.?eng|b\.?ed|b\.?f\.?a)\b/', $name)) return 2;
    if (preg_match('/\b(associate|a\.?a|a\.?s)\b/', $name)) return 1;
    return 0;
};

// Sort by rank desc, then by year desc; unknowns at the bottom
usort($degrees, function($a, $b) use ($rank_degree) {
    $ra = $rank_degree($a);
    $rb = $rank_degree($b);
    if ($ra !== $rb) return $rb - $ra;
    $ya = !empty($a['degree_year']) ? (int) $a['degree_year'] : 0;
    $yb = !empty($b['degree_year']) ? (int) $b['degree_year'] : 0;
    return $yb - $ya;
});

if ($highest_only) {
    $degrees = array_slice($degrees, 0, 1);
}

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
