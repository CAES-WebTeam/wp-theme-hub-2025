<?php
/**
 * Block render callback for the Print Button.
 *
 * This version uses PDF.js for reliable, cross-browser PDF printing.
 */

$post_id = get_the_ID();
$post_type = get_post_type($post_id);

// --- Dynamic Print Logic ---

$print_action = 'window.print()';
$final_pdf_url = null;

if ($post_type === 'publications') {
    // 1. Check for manual PDF
    $manual_pdf_attachment = get_field('pdf', $post_id);
    if (is_array($manual_pdf_attachment) && !empty($manual_pdf_attachment['url'])) {
        $final_pdf_url = $manual_pdf_attachment['url'];
    } elseif (is_string($manual_pdf_attachment) && filter_var($manual_pdf_attachment, FILTER_VALIDATE_URL)) {
        $final_pdf_url = $manual_pdf_attachment;
    }

    // 2. Fall back to generated PDF
    if (is_null($final_pdf_url)) {
        $generated_pdf_url = get_field('pdf_download_url', $post_id);
        if (is_string($generated_pdf_url) && filter_var($generated_pdf_url, FILTER_VALIDATE_URL)) {
            $final_pdf_url = $generated_pdf_url;
        }
    }

    if (!is_null($final_pdf_url)) {
        // Force URL to be HTTPS to prevent mixed-content errors
        $secure_pdf_url = 'https:' . str_replace(['http:', 'https:'], '', $final_pdf_url);
        // Update the action to call our new, reliable print function
        $print_action = "printPdfWithLibrary('" . esc_js($secure_pdf_url) . "')";
    }
}
// --- End Dynamic Print Logic ---

$page_title = get_the_title($post_id);
$path_url = wp_make_link_relative(get_permalink($post_id));
$publication_number = get_field('publication_number', $post_id);

?>

<?php if (!is_null($final_pdf_url)) : ?>
    <div id="pdf-print-container" style="display: none;"></div>

    <style>
        @media print {
            /* Hide everything on the page */
            body > *:not(#pdf-print-container) {
                display: none !important;
            }
            /* Show our PDF container and ensure its contents are visible */
            #pdf-print-container, #pdf-print-container * {
                display: block !important;
            }
        }
    </style>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.11.338/pdf.min.js"></script>

    <script>
        // Set the workerSrc for PDF.js
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.11.338/pdf.worker.min.js';

        let isPrinting = false; // Flag to prevent multiple print jobs

        async function printPdfWithLibrary(url) {
            if (isPrinting) {
                console.log("Print job already in progress.");
                return;
            }
            
            isPrinting = true;
            const printContainer = document.getElementById('pdf-print-container');
            printContainer.innerHTML = 'Loading PDF for printing...'; // Provide user feedback
            printContainer.style.display = 'block'; // Show the container briefly

            try {
                // Load the PDF document
                const loadingTask = pdfjsLib.getDocument(url);
                const pdf = await loadingTask.promise;
                
                // Clear loading message
                printContainer.innerHTML = ''; 

                // Loop through all pages of the PDF
                for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
                    const page = await pdf.getPage(pageNum);
                    const viewport = page.getViewport({ scale: 1.5 }); // Adjust scale for quality

                    // Create a canvas for each page
                    const canvas = document.createElement('canvas');
                    const context = canvas.getContext('2d');
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;

                    // Render the page into the canvas
                    const renderContext = {
                        canvasContext: context,
                        viewport: viewport
                    };
                    await page.render(renderContext).promise;

                    // Append the rendered page (canvas) to our print container
                    printContainer.appendChild(canvas);
                }

                // All pages are rendered, now trigger the print dialog
                window.print();

            } catch (error) {
                console.error('Error while preparing PDF for printing:', error);
                alert('Sorry, there was an error loading the PDF for printing.');
            } finally {
                // Clean up after printing
                printContainer.innerHTML = '';
                printContainer.style.display = 'none';
                isPrinting = false;
            }
        }
    </script>
<?php endif; ?>

<div <?php echo get_block_wrapper_attributes(); ?>>
    <button class="caes-hub-action-print__button" 
            onclick="<?php echo $print_action; ?>"
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