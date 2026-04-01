<?php
// Get person ID -- check global first, then fall back to queried object
$author_id = isset($GLOBALS['caes_current_user_id']) ? $GLOBALS['caes_current_user_id'] : get_queried_object_id();

// Resolve to CPT post ID
$person_post_id = function_exists('resolve_person_post_id') ? resolve_person_post_id($author_id) : null;

if (!$person_post_id) {
    return;
}

$terms = get_the_terms($person_post_id, 'areas_of_expertise');

if (empty($terms) || is_wp_error($terms)) {
    return;
}

$link_terms = isset($block['linkTerms']) ? (bool) $block['linkTerms'] : false;
$attrs      = $is_preview ? ' ' : get_block_wrapper_attributes(['class' => 'person-areas-of-expertise']);

echo '<div ' . $attrs . '>';
foreach ($terms as $term) {
    $label = esc_html($term->name);
    if ($link_terms) {
        $url   = esc_url(get_term_link($term));
        echo '<a href="' . $url . '" class="person-expertise-term">' . $label . '</a>';
    } else {
        echo '<span class="person-expertise-term">' . $label . '</span>';
    }
}
echo '</div>';
?>
