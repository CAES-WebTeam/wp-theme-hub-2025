<?php
// Get the current post ID
$post_id = get_the_ID();

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

if( !empty(get_field('cost', $post_id)) ):
	$cost = '$'.number_format(get_field('cost', $post_id), 2);
endif; 
?>

<?php 
if (!empty($cost)) { 
    echo '<div ' . $attrs . '>';
    echo '<h3 class="event-details-title">Cost</h3>';
    echo '<div class="event-details-content">' . $cost . '</div>';
    echo '</div>';
} 
?>