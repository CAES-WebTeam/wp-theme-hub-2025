<?php

$post_id   = get_the_ID();
$direction = $attributes['direction'] ?? 'previous';
$show_num  = $attributes['showPublicationNumber'] ?? true;

// Get the publication's series terms.
$series_terms = wp_get_post_terms($post_id, 'publication_series');

if (is_wp_error($series_terms) || empty($series_terms)) {
    return;
}

$term       = $series_terms[0];
$sorted_ids = get_sorted_series_publication_ids($term->term_id);

if (empty($sorted_ids)) {
    return;
}

$attrs = get_block_wrapper_attributes();

// Find the adjacent publication.
$current_index = array_search($post_id, $sorted_ids);

if ($current_index === false) {
    return;
}

if ($direction === 'previous') {
    $target_index = $current_index - 1;
} else {
    $target_index = $current_index + 1;
}

if ($target_index < 0 || $target_index >= count($sorted_ids)) {
    return;
}

$target_id    = $sorted_ids[$target_index];
$target_title = get_the_title($target_id);
$target_url   = get_permalink($target_id);
$target_num   = get_field('publication_number', $target_id);

$link_text = esc_html($target_title);
if ($show_num && $target_num) {
    $link_text = esc_html($target_num) . '&colon; ' . $link_text;
}

if ($direction === 'previous') {
    printf(
        '<div %s><span class="pub-series-nav__arrow">&larr;</span><a href="%s" class="pub-series-nav__link">%s</a></div>',
        $attrs,
        esc_url($target_url),
        $link_text
    );
} else {
    printf(
        '<div %s><a href="%s" class="pub-series-nav__link">%s</a><span class="pub-series-nav__arrow">&rarr;</span></div>',
        $attrs,
        esc_url($target_url),
        $link_text
    );
}
