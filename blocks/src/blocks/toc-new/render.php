<?php
$post_content = get_post_field('post_content', get_the_ID());
$showSubheadings = $attributes['showSubheadings'];
$title = $attributes['tocHeading'];
$listStyle = $attributes['listStyle'];
$popout = $attributes['popout'];
$topOfContentAnchor = $attributes['topOfContentAnchor'];
$anchorLinkText = $attributes['anchorLinkText'];
$anchorLinkText = $anchorLinkText ? $anchorLinkText : 'Top of Content';
$anchorLinkText = esc_html($anchorLinkText);

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
    data-popout="<?php echo esc_attr($popout); ?>"
    data-top-of-content-anchor="<?php echo esc_attr($topOfContentAnchor); ?>"
    <?php if ($topOfContentAnchor) : ?>
    data-anchor-link-text="<?php echo esc_attr($anchorLinkText); ?>"
    <?php endif; ?>>
    <h2><?php echo esc_html($title); ?></h2>
</div>