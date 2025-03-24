<?php

// Get author ID
$author_id = get_queried_object_id();

// Get position title
$title = get_user_meta($author_id, 'title', true);

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

// Set the element based on the block attribute
echo '<p ' . $attrs . ' >' . esc_html($title) . '</p>';


// Get all user meta fields
// $user_meta = get_user_meta($author_id);
// echo '<h3>All User Meta:</h3><pre>';
// print_r($user_meta);
// echo '</pre>';

?>
