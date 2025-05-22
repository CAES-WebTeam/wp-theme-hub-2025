<?php
$post_content = get_post_field('post_content', get_the_ID());
$showSubheadings = $attributes['showSubheadings'];
$title = $attributes['tocHeading'];
$listStyle = $attributes['listStyle'];

$pattern = $showSubheadings ? '/<h[2-6][^>]*>.*?<\/h[2-6]>/' : '/<h2[^>]*>.*?<\/h2>/';
if (!preg_match($pattern, $post_content)) {
    return '';
}
?>
<div
    <?php echo get_block_wrapper_attributes(); ?>
    data-show-subheadings="<?php echo esc_attr($showSubheadings); ?>"
    data-list-style="<?php echo esc_attr($listStyle); ?>"
    data-title="<?php echo esc_attr($title); ?>"
>
    <h2><?php echo esc_html($title); ?></h2>
</div>