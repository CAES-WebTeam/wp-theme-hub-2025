<?php

// Grab attributes
$info = $attributes['info'] ?? '';

echo '<p>' . esc_html($info) . '</p>';

// Get author ID
// $author_id = get_queried_object_id();

// Get first and last name
// $first_name = get_user_meta($author_id, 'first_name', true);
// $last_name = get_user_meta($author_id, 'last_name', true);

// Attributes for wrapper
// $attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

// Set the element based on the block attribute
// echo '<' . esc_html($element) . ' ' . $attrs . ' >' . esc_html($first_name) . ' ' . esc_html($last_name) . '</' . esc_html($element) . '>';


// Get all user meta fields
// $user_meta = get_user_meta($author_id);
// echo '<h3>All User Meta:</h3><pre>';
// print_r($user_meta);
// echo '</pre>';

?>
