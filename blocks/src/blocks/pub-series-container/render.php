<?php

$post_id     = get_the_ID();
$series_terms = wp_get_post_terms($post_id, 'publication_series');

if (is_wp_error($series_terms) || empty($series_terms)) {
    return;
}

$attrs = get_block_wrapper_attributes();

printf('<div %s>%s</div>', $attrs, $content);
