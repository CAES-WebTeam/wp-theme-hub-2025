<?php
$post_id = get_the_ID();
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();
$featured_image = get_field('featured_image', $post_id);

// Use placeholder if no featured image
if (empty($featured_image)) {
    $featured_image = caes_get_placeholder_image($post_id);
}
?>

<?php if (!empty($featured_image)): ?>
    <?php echo '<div ' . $attrs . '>'; ?>
    <img src="<?php echo $featured_image['url']; ?>" alt="<?php echo $featured_image['alt']; ?>" />
    <?php echo '</div>'; ?>
<?php endif; ?>