<?php
// Get the current post ID
$post_id = get_the_ID();

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

$featured_image = get_field('featured_image', $post_id);

// Placeholder logic for when no featured image is set
if (empty($featured_image)) {
    static $counter = 0;
    
    // Pick the next placeholder in order:
    $placeholders = [
        'placeholder-bg-1-athens.jpg',
        'placeholder-bg-1-hedges.jpg',
        'placeholder-bg-1-lake-herrick.jpg',
        'placeholder-bg-1-olympic.jpg',
        'placeholder-bg-2-athens.jpg',
        'placeholder-bg-2-hedges.jpg',
        'placeholder-bg-2-lake-herrick.jpg',
        'placeholder-bg-2-olympic.jpg',
    ];
    $index = $counter % count($placeholders);
    $file = $placeholders[$index];
    $counter++;
    
    // Create placeholder image array to match ACF structure
    $featured_image = [
        'url' => get_template_directory_uri() . '/assets/images/' . $file,
        'alt' => get_the_title($post_id)
    ];
}
?>

<?php if (!empty($featured_image)): ?>
    <?php echo '<div ' . $attrs . '>'; ?>
    <img src="<?php echo $featured_image['url']; ?>" alt="<?php echo $featured_image['alt']; ?>" />
    <?php echo '</div>'; ?>
<?php endif; ?>