<?php

// Get author ID
$author_id = isset($GLOBALS['caes_current_user_id']) ? $GLOBALS['caes_current_user_id'] : get_queried_object_id();

// Resolve to CPT post ID
$person_post_id = function_exists('resolve_person_post_id') ? resolve_person_post_id($author_id) : null;

if (!$person_post_id) {
    return;
}

if (function_exists('is_person_active') && !is_person_active($person_post_id)) {
    return;
}

$websites = get_field('elements_websites', $person_post_id);

if (empty($websites) || !is_array($websites)) {
    return;
}

$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

echo '<ul ' . $attrs . '>';
foreach ($websites as $site) {
    $url   = isset($site['website_url']) ? $site['website_url'] : '';
    $label = isset($site['website_label']) ? trim($site['website_label']) : '';
    if (empty($url)) {
        continue;
    }
    // Force https on output even if stored as http
    $href = preg_replace('#^http://#i', 'https://', $url);
    $text = $label !== '' ? $label : $href;
    echo '<li><a href="' . esc_url($href) . '">' . esc_html($text) . '</a></li>';
}
echo '</ul>';
?>
