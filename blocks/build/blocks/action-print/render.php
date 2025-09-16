<?php
/**
 * Block render callback for the Print Button.
 *
 * This version opens the publication's PDF in a new tab and triggers the print dialog.
 * This is a simpler, more reliable cross-browser solution.
 */

$post_id = get_the_ID();
$post_type = get_post_type($post_id);

// --- Dynamic Print Logic ---

$print_action = 'window.print()'; // Default action for non-publication pages
$final_pdf_url = null;

if ($post_type === 'publications') {
    // 1. Prioritize the manually uploaded PDF from the 'pdf' ACF field.
    $manual_pdf_attachment = get_field('pdf', $post_id);
    if (is_array($manual_pdf_attachment) && !empty($manual_pdf_attachment['url'])) {
        $final_pdf_url = $manual_pdf_attachment['url'];
    } elseif (is_string($manual_pdf_attachment) && filter_var($manual_pdf_attachment, FILTER_VALIDATE_URL)) {
        $final_pdf_url = $manual_pdf_attachment;
    }

    // 2. If no manual PDF, fall back to the generated PDF from the 'pdf_download_url' ACF field.
    if (is_null($final_pdf_url)) {
        $generated_pdf_url = get_field('pdf_download_url', $post_id);
        if (is_string($generated_pdf_url) && filter_var($generated_pdf_url, FILTER_VALIDATE_URL)) {
            $final_pdf_url = $generated_pdf_url;
        }
    }

    if (!is_null($final_pdf_url)) {
        // Force the URL to be HTTPS to prevent mixed-content errors.
        $secure_pdf_url = 'https:' . str_replace(['http:', 'https:'], '', $final_pdf_url);
        // Set the action to call our new, simplified JavaScript function.
        $print_action = "openAndPrintPdf('" . esc_js($secure_pdf_url) . "')";
    }
}
// --- End Dynamic Print Logic ---

$page_title = get_the_title($post_id);
$path_url = wp_make_link_relative(get_permalink($post_id));
$publication_number = get_field('publication_number', $post_id);

?>

<?php if (!is_null($final_pdf_url)) : ?>
    <script>
        function openAndPrintPdf(url) {
            // Open the PDF in a new tab.
            const newWindow = window.open(url, '_blank');
            
            // Focus on the new window and trigger print after a short delay.
            if (newWindow) {
                newWindow.onload = function() {
                    // A timeout gives the browser's PDF viewer a moment to load the file.
                    setTimeout(function() {
                        newWindow.focus();
                        newWindow.print();
                    }, 500); // 500ms delay
                };
            } else {
                // This will happen if the user has a popup blocker.
                alert("Could not open the PDF. Please check your browser's popup blocker settings.");
            }
        }
    </script>
<?php endif; ?>

<div <?php echo get_block_wrapper_attributes(); ?>>
    <button class="caes-hub-action-print__button" 
            onclick="<?php echo $print_action; // This is now dynamic ?>"
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