<?php
// Get person ID -- check global first, then fall back to queried object
$author_id = isset($GLOBALS['caes_current_user_id']) ? $GLOBALS['caes_current_user_id'] : get_queried_object_id();

// Resolve to CPT post ID
$person_post_id = function_exists('resolve_person_post_id') ? resolve_person_post_id($author_id) : null;

if (!$person_post_id) {
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

$link_terms   = isset($block['linkTerms']) ? (bool) $block['linkTerms'] : false;
$show_heading = isset($block['showHeading']) ? (bool) $block['showHeading'] : false;
$attrs        = $is_preview ? ' ' : get_block_wrapper_attributes(['class' => 'person-areas-of-expertise']);

echo '<div ' . $attrs . '>';

if ($show_heading) {
    echo '<h2 class="person-areas-of-expertise__heading">' . esc_html__('Areas of expertise', 'caes-hub') . '</h2>';
}

echo '<div class="person-areas-of-expertise__terms">';
foreach ($terms as $term) {
    $label = esc_html($term->name);
    $class = 'person-expertise-term has-hedges-background-color has-background';
    if ($link_terms) {
        $url = esc_url(get_term_link($term));
        echo '<a href="' . $url . '" class="' . $class . '">' . $label . '</a>';
    } else {
        echo '<span class="' . $class . '">' . $label . '</span>';
    }
}
echo '</div>';

echo '</div>';
?>
