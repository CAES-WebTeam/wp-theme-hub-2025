<?php
$post_id = get_the_ID();
$post_type = get_post_type($post_id);

// Initialize $final_pdf_url to null. This will hold the URL if found.
$final_pdf_url = null;

// Only proceed if it's a 'publications' post type
if ($post_type === 'publications') {
    // 1. Try to get a manually uploaded PDF (from the 'pdf' ACF field)
    $manual_pdf_attachment = get_field('pdf', $post_id);

    if (is_array($manual_pdf_attachment) && !empty($manual_pdf_attachment['url'])) {
        $final_pdf_url = $manual_pdf_attachment['url'];
    } elseif (is_string($manual_pdf_attachment) && filter_var($manual_pdf_attachment, FILTER_VALIDATE_URL)) {
        $final_pdf_url = $manual_pdf_attachment;
    }

    // 2. If no manual PDF found, try to get the generated PDF (from the 'pdf_download_url' ACF field)
    if (is_null($final_pdf_url)) { // Only check generated if manual wasn't found
        $generated_pdf_url = get_field('pdf_download_url', $post_id);
        if (is_string($generated_pdf_url) && filter_var($generated_pdf_url, FILTER_VALIDATE_URL)) {
            $final_pdf_url = $generated_pdf_url;
        }
    }
}

// Only display the button if a valid PDF URL was found
if (!is_null($final_pdf_url)) {
?>
    <div <?php echo get_block_wrapper_attributes(); ?>>
        <a class="button-link" href="<?php echo esc_url($final_pdf_url); ?>">
            <span class="label">Save PDF</span>
        </a>
    </div>
<?php
}
?>