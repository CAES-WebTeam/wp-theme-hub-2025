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

$term_ids = get_field('elements_areas_of_expertise', $person_post_id);

if (empty($term_ids)) {
    return;
}

$terms = array_filter(array_map(function($id) {
    return get_term($id, 'areas_of_expertise');
}, (array) $term_ids), function($t) {
    return $t && ! is_wp_error($t);
});

if (empty($terms)) {
    return;
}

$link_terms        = isset($attributes['linkTerms']) ? (bool) $attributes['linkTerms'] : false;
$show_heading      = isset($attributes['showHeading']) ? (bool) $attributes['showHeading'] : false;
$heading_level     = isset($attributes['headingLevel']) ? (int) $attributes['headingLevel'] : 2;
$term_font_size    = isset($attributes['termFontSize']) ? $attributes['termFontSize'] : '1.3rem';
$heading_font_size = isset($attributes['headingFontSize']) ? $attributes['headingFontSize'] : '';

// Clamp heading level to valid range
$heading_level = max(1, min(6, $heading_level));
$heading_tag   = 'h' . $heading_level;

$term_style    = $term_font_size ? ' style="' . esc_attr( safecss_filter_attr( 'font-size:' . $term_font_size ) ) . '"' : '';
$heading_style = $heading_font_size ? ' style="' . esc_attr( safecss_filter_attr( 'font-size:' . $heading_font_size ) ) . '"' : '';

$attrs = get_block_wrapper_attributes(['class' => 'person-areas-of-expertise']);

echo '<div ' . $attrs . '>';

if ($show_heading) {
    echo '<' . $heading_tag . ' class="wp-block-heading person-areas-of-expertise__heading"' . $heading_style . '>' . esc_html__('Areas of expertise', 'caes-hub') . '</' . $heading_tag . '>';
}

echo '<div class="person-areas-of-expertise__terms">';
foreach ($terms as $term) {
    $label = esc_html($term->name);
    $class = 'person-expertise-term has-hedges-background-color has-background';
    if ($link_terms) {
        $url = esc_url(get_term_link($term));
        echo '<a href="' . $url . '" class="' . $class . '"' . $term_style . '>' . $label . '</a>';
    } else {
        echo '<span class="' . $class . '"' . $term_style . '>' . $label . '</span>';
    }
}
echo '</div>';

echo '</div>';
?>
