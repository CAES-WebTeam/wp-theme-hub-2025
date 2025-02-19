<?php
$title = $attributes['tocHeading'];
$showSubheadings = $attributes['showSubheadings'];
$listStyle = $attributes['listStyle'];
?>
<div
    <?php echo get_block_wrapper_attributes(); ?>
    data-show-subheadings="<?php echo $showSubheadings; ?>"
    data-list-style="<?php echo $listStyle; ?>"
    data-title="<?php echo $title; ?>"
>
    <h2><?php echo $title; ?></h2>
</div>