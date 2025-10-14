<?php
// Get block attributes
$word_limit = isset($block['wordLimit']) ? (int) $block['wordLimit'] : 0;
$show_featured_image = isset($block['showFeaturedImage']) ? $block['showFeaturedImage'] : false;
$conditional_display = isset($block['conditionalDisplay']) ? $block['conditionalDisplay'] : false;

// Get the current post ID
$post_id = get_the_ID();
$post_type = get_post_type($post_id);

// Check conditional display
$is_conditional_summary = false;
if ($conditional_display) {
    $post_content = get_post_field('post_content', $post_id);
    $post_content = trim(wp_strip_all_tags($post_content));
    
    // If content is not empty, don't display this block
    if (!empty($post_content)) {
        return;
    }
    $is_conditional_summary = true;
}

// Get the summary
$summary = get_field('summary', $post_id);

// If no summary, don't display anything
if (empty($summary)) {
    return;
}

// Start building output
$output = '';

// Add featured image if enabled
if ($show_featured_image && has_post_thumbnail($post_id)) {
    $featured_image = get_the_post_thumbnail($post_id, 'full', array(
        'style' => 'width: 100%; height: auto; display: block; margin-bottom: 1rem;'
    ));
    $output .= $featured_image;
}

// Process summary based on word limit
if ($word_limit > 0) {
    // Strip tags for word count
    $stripped = wp_strip_all_tags($summary);
    $words = explode(' ', $stripped);

    if (count($words) > $word_limit) {
        $summary = implode(' ', array_slice($words, 0, $word_limit)) . '…';
        // Escape output to avoid broken HTML
        $output .= esc_html($summary);
    } else {
        $output .= wp_kses_post($summary);
    }
} else {
    $output .= wp_kses_post($summary);
}

// DEBUG: Add diagnostic information
$output .= '<!-- DEBUG INFO: ';
$output .= 'Is Conditional: ' . ($is_conditional_summary ? 'YES' : 'NO') . ' | ';
$output .= 'Post Type: ' . $post_type . ' | ';

// Check for PDF if this is a conditional summary display
$final_pdf_url = null;
$pdf_source_type = null;

if ($is_conditional_summary && $post_type === 'publications') {
    // 1. Try to get a manually uploaded PDF
    $manual_pdf_attachment = get_field('pdf', $post_id);
    $output .= 'Manual PDF Field: ' . (empty($manual_pdf_attachment) ? 'EMPTY' : 'HAS VALUE') . ' | ';

    if (is_array($manual_pdf_attachment) && !empty($manual_pdf_attachment['url'])) {
        $final_pdf_url = $manual_pdf_attachment['url'];
        $pdf_source_type = 'manual';
    } elseif (is_string($manual_pdf_attachment) && filter_var($manual_pdf_attachment, FILTER_VALIDATE_URL)) {
        $final_pdf_url = $manual_pdf_attachment;
        $pdf_source_type = 'manual';
    }

    // 2. If no manual PDF found, try generated PDF
    if (is_null($final_pdf_url)) {
        $generated_pdf_url = get_field('pdf_download_url', $post_id);
        $output .= 'Generated PDF Field: ' . (empty($generated_pdf_url) ? 'EMPTY' : 'HAS VALUE') . ' | ';
        
        if (is_string($generated_pdf_url) && filter_var($generated_pdf_url, FILTER_VALIDATE_URL)) {
            $final_pdf_url = $generated_pdf_url;
            $pdf_source_type = 'generated';
        }
    }
}

$output .= 'Final PDF URL: ' . ($final_pdf_url ? 'FOUND' : 'NOT FOUND');
$output .= ' -->';

// Check if PDF download is disabled
$disable_pdf_download = get_field('disable_pdf_download', $post_id);

// Add PDF button if available AND not disabled
if (!is_null($final_pdf_url) && !$disable_pdf_download) {
    $publication_title = get_the_title($post_id);
    $publication_number = get_field('publication_number', $post_id);
    $path_url = wp_make_link_relative(get_permalink($post_id));
    $pdf_filename = basename(parse_url($final_pdf_url, PHP_URL_PATH));
    
    $output .= '<div style="margin-top: 1.5rem;">';
    $output .= '<a class="button-link"';
    $output .= 'href="' . esc_url($final_pdf_url) . '" ';
    $output .= 'data-pdf-url="' . esc_attr($final_pdf_url) . '" ';
    $output .= 'data-publication-number="' . esc_attr($publication_number) . '" ';
    $output .= 'data-publication-title="' . esc_attr($publication_title) . '" ';
    $output .= 'data-publication-url="' . esc_attr($path_url) . '" ';
    $output .= 'data-pdf-filename="' . esc_attr($pdf_filename) . '" ';
    $output .= 'data-pdf-source="' . esc_attr($pdf_source_type) . '" ';
    $output .= 'data-action-type="pdf_download">';
    $output .= '<span class="label">Download Full PDF</span>';
    $output .= '</a>';
    $output .= '</div>';
}

// Output the final result
echo '<div ' . get_block_wrapper_attributes() . '>' . $output . '</div>';
?>