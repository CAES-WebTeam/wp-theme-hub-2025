<?php
/**
 * Block render callback for the Print Button.
 *
 * This version dynamically changes its behavior for the 'publications' post type
 * to print an associated PDF file instead of the webpage.
 */

$post_id = get_the_ID();
$post_type = get_post_type($post_id);

// --- Dynamic Print Logic ---

// Default action is the standard browser print.
$print_action = 'window.print()';
$final_pdf_url = null;

// Check for a PDF only if it's a 'publications' post type.
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

    // If we found any valid PDF URL, change the print action to use our custom JS function.
    if (!is_null($final_pdf_url)) {
        $print_action = "printPdf('" . esc_js($final_pdf_url) . "')";
    }
}

// --- End Dynamic Print Logic ---


// Get standard data attributes for the button.
$page_title = get_the_title($post_id);
$current_url = get_permalink($post_id);
$path_url = wp_make_link_relative($current_url);
$publication_number = get_field('publication_number', $post_id);

?>

<?php if (!is_null($final_pdf_url)) : ?>
    <script>
        function printPdf(url) {
            // Create a hidden iframe to load the PDF
            const iframe = document.createElement('iframe');
            iframe.style.position = 'absolute';
            iframe.style.left = '-9999px'; // Position it off-screen
            iframe.src = url;

            // Append the iframe and wait for it to load
            document.body.appendChild(iframe);
            iframe.onload = function() {
                try {
                    iframe.contentWindow.focus(); // Focus on the iframe's content
                    iframe.contentWindow.print(); // Trigger the print dialog for the PDF
                } catch (e) {
                    console.error("Could not print PDF:", e);
                    // Fallback for browsers that might block this action
                    alert("Could not automatically print the PDF. Please try opening and printing it manually.");
                }
                // Clean up by removing the iframe shortly after the print dialog is triggered
                setTimeout(() => document.body.removeChild(iframe), 500);
            };
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