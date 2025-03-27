<?php
$post_id = get_the_ID();
$post_type = get_post_type($post_id);

// Get ACF field for PDF attachment (expects an array)
$pdf_attachment = get_field('pdf', $post_id);

// Default to admin-post.php
$pdf_url = admin_url('admin-post.php?action=generate_pdf&post_id=' . $post_id);

// Ensure the post type is 'publication' and check if 'url' exists
if ($post_type === 'publications' && isset($pdf_attachment['url']) && !empty($pdf_attachment['url'])) {
    $pdf_url = $pdf_attachment['url']; // Use the actual PDF URL
}

?>

<div <?php echo get_block_wrapper_attributes(); ?>>
    <button class="caes-hub-action-pdf__button" onclick="location.href='<?php echo esc_url($pdf_url); ?>'">
        <span class="label">Save PDF</span>
    </button>
</div>