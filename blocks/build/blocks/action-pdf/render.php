<?php
$post_id = get_the_ID();
?>

<div <?php echo get_block_wrapper_attributes(); ?>>
    <button class="caes-hub-action-pdf__button" onclick="location.href='<?php echo esc_url(admin_url('admin-post.php?action=generate_pdf&post_id=' . $post_id)); ?>'">
        <span class="label">Save PDF</span>
    </button>
</div>