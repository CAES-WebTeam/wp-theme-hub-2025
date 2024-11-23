<?php
// Get the current post ID
$post_id = get_the_ID();

if( !empty(get_field('cost', $post_id)) ):
	$cost = '$'.number_format(get_field('cost', $post_id), 2);
endif; 
?>

<h3>Cost</h3>

<?php echo !empty($cost) ? '<div class="event-detail-cost">' . $cost . '</div>' : ''; ?>
