<?php
$post_id = get_the_ID();
$post_type = get_post_type($post_id);

// Get ACF field for PDF attachment (could be array, string, or null)
$pdf_attachment = get_field('pdf', $post_id);

// Default to admin-post.php handler
$pdf_url = admin_url('admin-post.php?action=generate_pdf&post_id=' . $post_id);

// If post type is 'publications', try to get the actual PDF URL from the field
if ($post_type === 'publications') {
    if (is_array($pdf_attachment) && !empty($pdf_attachment['url'])) {
        $pdf_url = $pdf_attachment['url'];
    } elseif (is_string($pdf_attachment) && filter_var($pdf_attachment, FILTER_VALIDATE_URL)) {
        $pdf_url = $pdf_attachment;
    }
}

?>

<div <?php echo get_block_wrapper_attributes(); ?>>
    <a class="button-link" href="<?php echo esc_url($pdf_url); ?>">
        <span class="label">Save PDF</span>
    </a>
</div>