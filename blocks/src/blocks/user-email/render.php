<?php


// Get author ID
$author_id = get_queried_object_id();

// Get email
$email = get_the_author_meta('user_email', $author_id);

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

// If email isn't blank, output the clickable email
// Also check to make sure email doesn't contain "placeholder"
if ($email && !strpos($email, 'placeholder')) {
    echo '<a ' . $attrs . ' href="mailto:' . esc_html($email) . '"><span>' . esc_html($email) . '</span></a>';    
}

// Get all user meta fields
// $user_meta = get_user_meta($author_id);
// echo '<h3>All User Meta:</h3><pre>';
// print_r($user_meta);
// echo '</pre>';

?>
