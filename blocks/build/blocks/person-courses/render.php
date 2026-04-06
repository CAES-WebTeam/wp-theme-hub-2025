<?php
// Get person ID -- check global first, then fall back to queried object
$author_id = isset($GLOBALS['caes_current_user_id']) ? $GLOBALS['caes_current_user_id'] : get_queried_object_id();

// Resolve to CPT post ID
$person_post_id = function_exists('resolve_person_post_id') ? resolve_person_post_id($author_id) : null;

if (!$person_post_id) {
    return;
}

$courses = get_field('elements_courses_taught', $person_post_id);

if (empty($courses)) {
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

$attrs = get_block_wrapper_attributes(['class' => 'person-courses']);

echo '<div ' . $attrs . '>';

if ($show_heading) {
    echo '<' . $heading_tag . ' class="wp-block-heading person-courses__heading"' . $heading_style . '>' . esc_html__('Courses', 'caes-hub') . '</' . $heading_tag . '>';
}

echo '<ul class="wp-block-list person-courses__list"' . $list_style . '>';
foreach ($courses as $course) {
    $code  = ! empty($course['course_code']) ? esc_html($course['course_code']) . ': ' : '';
    $title = ! empty($course['course_title']) ? esc_html($course['course_title']) : '';
    if (empty($code) && empty($title)) {
        continue;
    }
    echo '<li>' . $code . $title . '</li>';
}
echo '</ul>';

echo '</div>';
?>
