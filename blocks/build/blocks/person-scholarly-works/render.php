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

$show_heading      = isset($block['showHeading']) ? (bool) $block['showHeading'] : false;
$heading_level     = isset($block['headingLevel']) ? (int) $block['headingLevel'] : 2;
$heading_font_size = isset($block['headingFontSize']) ? $block['headingFontSize'] : '';
$item_font_size    = isset($block['itemFontSize']) ? $block['itemFontSize'] : '';

// Clamp heading level to valid range
$heading_level = max(1, min(6, $heading_level));
$heading_tag   = 'h' . $heading_level;

$heading_style = $heading_font_size ? ' style="' . esc_attr( safecss_filter_attr( 'font-size:' . $heading_font_size ) ) . '"' : '';
$list_style    = $item_font_size ? ' style="' . esc_attr( safecss_filter_attr( 'font-size:' . $item_font_size ) ) . '"' : '';

$attrs = $is_preview ? ' ' : get_block_wrapper_attributes(['class' => 'person-scholarly-works']);

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

    $parts = [];
    if ($year) {
        $parts[] = esc_html('(' . $year . ')');
    }
    if ($journal) {
        $parts[] = esc_html($journal);
    }

    $meta = $parts ? ' ' . implode('. ', $parts) . '.' : '';

    if ($doi) {
        $doi_url = esc_url('https://doi.org/' . ltrim($doi, 'https://doi.org/'));
        echo '<li><a href="' . $doi_url . '"><strong>' . esc_html($title) . '</strong></a>' . $meta . '</li>';
    } else {
        echo '<li><strong>' . esc_html($title) . '</strong>' . $meta . '</li>';
    }
}
echo '</ul>';

echo '</div>';
?>
