<?php
// Get the current post ID
$post_id = get_the_ID();

// Get the summary field from ACF
$summary = get_field('summary', $post_id);
?>

<div <?php echo get_block_wrapper_attributes(); ?>>
    <?php echo $summary; ?>
</div>