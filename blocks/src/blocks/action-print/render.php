<?php
$post_id = get_the_ID();
$page_title = get_the_title($post_id);
$current_url = get_permalink($post_id);
$post_type = get_post_type($post_id);
$path_url = wp_make_link_relative(get_permalink($post_id));

// Get publication number if it exists (only for publications post type)
$publication_number = get_field('publication_number', $post_id);
?>

<div <?php echo get_block_wrapper_attributes(); ?>>
    <button class="caes-hub-action-print__button" 
            onclick="window.print()"
            data-page-title="<?php echo esc_attr($page_title); ?>"
            data-page-url="<?php echo esc_attr($path_url); ?>"
            data-content-type="<?php echo esc_attr($post_type); ?>"
            <?php if ($publication_number): ?>
            data-publication-number="<?php echo esc_attr($publication_number); ?>"
            <?php endif; ?>
            data-action-type="print">
        <span class="label">Print</span>
    </button>
</div>