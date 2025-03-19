<?php

// Ensure attributes are available
$showAsButton = isset($block['showAsButton']) ? $block['showAsButton'] : false;

// Get the current post ID
$post_id = get_the_ID();

// Get the related translations
$related_translations = get_field('related_translations', $post_id);

if (!empty($related_translations) && is_array($related_translations)) {
    echo '<div ' . get_block_wrapper_attributes() . '>';
    foreach ($related_translations as $translation) {
        $translation_id = $translation->ID; // Get the post ID
        $language = get_field('language', $translation_id); // Get the language
        $class = $showAsButton ? 'class="button"' : '';

        // Change text if it's a button
        if ($showAsButton) {
            $text = ($language == '2') ? 'Leer en Español' : 'Read in English';
        } else {
            $text = ($language == '2') ? 'Esta publicación también está disponible en español.' : 'This publication is also available in English.';
        }

        echo '<a href="' . esc_url(get_permalink($translation_id)) . '" ' . $class . '>' . esc_html($text) . '</a> ';
    }
    echo '</div>';
}

?>