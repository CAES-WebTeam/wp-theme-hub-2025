<?php

// Get the publication number
$post_id = get_the_ID();
$pubNumber = get_field('publication_number', $post_id);

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

if ($pubNumber) {
    echo '<p ' . $attrs . '>';
    echo $pubNumber;
    echo '</p>';
}

?>
