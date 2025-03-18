<?php

// Get the current post ID
$post_id = get_the_ID();

// Get the related translations
$related_translations = get_field('related_translations', $post_id);

if (!empty($related_translations) && is_array($related_translations)) {
    echo '<div ' . get_block_wrapper_attributes() . '>';
    foreach ($related_translations as $translation) {
        $translation_id = $translation->ID; // Get the post ID
        $language = get_field('language', $translation_id); // Get the language
        if ($language == '2') {   // If the language is Spanish
            echo '<a href="' . get_permalink($translation_id) . '">Esta publicación también está disponible en español.</a> ';
        } elseif ($language == '1') {  // If the language is English
            echo '<a href="' . get_permalink($translation_id) . '">This publication is also available in English.</a> ';
        }
    }
    echo '</div>';
};

?>