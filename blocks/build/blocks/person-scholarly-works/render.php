<?php
// Get person ID -- check global first, then fall back to queried object
$author_id = isset($GLOBALS['caes_current_user_id']) ? $GLOBALS['caes_current_user_id'] : get_queried_object_id();

// Resolve to CPT post ID
$person_post_id = function_exists('resolve_person_post_id') ? resolve_person_post_id($author_id) : null;

if (!$person_post_id) {
    return;
}

$works = get_field('elements_scholarly_works', $person_post_id);

if (empty($works)) {
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

$attrs = get_block_wrapper_attributes(['class' => 'person-scholarly-works']);

echo '<div ' . $attrs . '>';

if ($show_heading) {
    echo '<' . $heading_tag . ' class="wp-block-heading person-scholarly-works__heading"' . $heading_style . '>' . esc_html__('Scholarly Works', 'caes-hub') . '</' . $heading_tag . '>';
}

echo '<ul class="wp-block-list person-scholarly-works__list"' . $list_style . '>';
foreach ($works as $work) {
    $title   = ! empty($work['pub_title']) ? $work['pub_title'] : '';
    $journal = ! empty($work['pub_journal']) ? $work['pub_journal'] : '';
    $year    = ! empty($work['pub_year']) ? $work['pub_year'] : '';
    $doi     = ! empty($work['pub_doi']) ? $work['pub_doi'] : '';

    if (empty($title)) {
        continue;
    }

    $authors = ! empty($work['pub_authors']) ? $work['pub_authors'] : '';

    $meta_parts = array_filter([$journal, $year ? '(' . $year . ')' : '']);
    $meta = $meta_parts ? ', ' . implode(', ', $meta_parts) . '.' : '';

    $allowed = [ 'i' => [], 'em' => [], 'b' => [], 'strong' => [], 'sup' => [], 'sub' => [] ];
    $safe_title = wp_kses($title, $allowed);

    echo '<li>';
    if ($doi) {
        $doi_url = esc_url('https://doi.org/' . ltrim($doi, 'https://doi.org/'));
        echo '<a href="' . $doi_url . '">' . $safe_title . '</a>';
    } else {
        echo $safe_title;
    }
    echo esc_html($meta);
    if ($authors) {
        echo '<br><span class="person-scholarly-works__authors">' . esc_html($authors) . '</span>';
    }
    echo '</li>';
}
echo '</ul>';

echo '</div>';
?>
