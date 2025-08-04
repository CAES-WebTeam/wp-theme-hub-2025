<?php
$post_id = get_the_ID();
$post_type = get_post_type($post_id);

// Initialize $final_pdf_url to null. This will hold the URL if found.
$final_pdf_url = null;
$pdf_source_type = null; // Track whether it's manual or generated

// Only proceed if it's a 'publications' post type
if ($post_type === 'publications') {
    // 1. Try to get a manually uploaded PDF (from the 'pdf' ACF field)
    $manual_pdf_attachment = get_field('pdf', $post_id);

    if (is_array($manual_pdf_attachment) && !empty($manual_pdf_attachment['url'])) {
        $final_pdf_url = $manual_pdf_attachment['url'];
        $pdf_source_type = 'manual';
    } elseif (is_string($manual_pdf_attachment) && filter_var($manual_pdf_attachment, FILTER_VALIDATE_URL)) {
        $final_pdf_url = $manual_pdf_attachment;
        $pdf_source_type = 'manual';
    }

    // 2. If no manual PDF found, try to get the generated PDF (from the 'pdf_download_url' ACF field)
    if (is_null($final_pdf_url)) { // Only check generated if manual wasn't found
        $generated_pdf_url = get_field('pdf_download_url', $post_id);
        if (is_string($generated_pdf_url) && filter_var($generated_pdf_url, FILTER_VALIDATE_URL)) {
            $final_pdf_url = $generated_pdf_url;
            $pdf_source_type = 'generated';
        }
    }
}

// Only display the button if a valid PDF URL was found
if (!is_null($final_pdf_url)) {
    // Get additional data for tracking
    $publication_title = get_the_title($post_id);
    $publication_number = get_field('publication_number', $post_id); // Get the ACF publication number
    $current_url = get_permalink($post_id);
    $pdf_filename = basename(parse_url($final_pdf_url, PHP_URL_PATH));
?>
    <div <?php echo get_block_wrapper_attributes(); ?>>
        <a class="button-link" 
           href="<?php echo esc_url($final_pdf_url); ?>"
           data-pdf-url="<?php echo esc_attr($final_pdf_url); ?>"
           data-publication-number="<?php echo esc_attr($publication_number); ?>"
           data-publication-title="<?php echo esc_attr($publication_title); ?>"
           data-publication-url="<?php echo esc_attr($current_url); ?>"
           data-pdf-filename="<?php echo esc_attr($pdf_filename); ?>"
           data-pdf-source="<?php echo esc_attr($pdf_source_type); ?>"
           data-action-type="pdf_download">
            <span class="label">Save PDF</span>
        </a>
    </div>
<?php
}
?>