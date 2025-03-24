<?php

// Get author ID
$author_id = get_queried_object_id();

// Get block attribute for mobileVersion
$mobile = $block['mobileVersion'];

// Get first and last name
$first_name = get_user_meta($author_id, 'first_name', true);
$last_name = get_user_meta($author_id, 'last_name', true);
$image_name = get_user_meta($author_id, 'image_name', true);

// Determine additional class
$mobile_class = $mobile ? 'mobile-version' : 'desktop-version';

// Attributes for wrapper (append mobile class)
$attrs = get_block_wrapper_attributes(['class' => $mobile_class]);

// Echo the image with the first and last name as alt text
if (!empty($image_name)) {
    echo '<figure ' . $attrs . '>';
    echo '<img src="https://secure.caes.uga.edu/personnel/photos/' . esc_html($image_name) . '.jpg" alt="' . esc_html($first_name) . ' ' . esc_html($last_name) . '" />';
    echo '</figure>';   
}

?>