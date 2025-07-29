<?php
// ===================
// PUBLICATION DYNAMIC PDF GENERATION WITH TABLE STYLING
// ===================

// Load TCPDF library FIRST, as MYPDF extends TCPDF.
require_once get_template_directory() . '/inc/tcpdf/tcpdf.php';

/**
 * Format publication number for display
 *
 * @param string $publication_number The original publication number
 * @return string The formatted publication number string
 */
function format_publication_number_for_display($publication_number)
{
    $originalPubNumber = $publication_number;
    $displayPubNumber = $originalPubNumber;
    $pubType = '';

    if ($originalPubNumber) {
        $prefix = strtoupper(substr($originalPubNumber, 0, 2));
        $firstChar = strtoupper(substr($originalPubNumber, 0, 1));

        switch ($prefix) {
            case 'AP':
                $pubType = 'Annual Publication';
                $displayPubNumber = substr($originalPubNumber, 2);
                break;
            case 'TP':
                $pubType = 'Temporary Publication';
                $displayPubNumber = substr($originalPubNumber, 2);
                break;
            default:
                switch ($firstChar) {
                    case 'B':
                        $pubType = 'Bulletin';
                        $displayPubNumber = substr($originalPubNumber, 1);
                        break;
                    case 'C':
                        $pubType = 'Circular';
                        $displayPubNumber = substr($originalPubNumber, 1);
                        break;
                    default:
                        $pubType = 'Publication';
                        break;
                }
                break;
        }
    }

    $displayPubNumber = trim($displayPubNumber);
    $formatted_pub_number_string = '';
    if (!empty($pubType) && !empty($displayPubNumber)) {
        $formatted_pub_number_string = $pubType . ' ' . $displayPubNumber;
    } elseif (!empty($displayPubNumber)) {
        $formatted_pub_number_string = $displayPubNumber;
    }

    return $formatted_pub_number_string;
}

// Extend TCPDF to add custom header and footer
class MYPDF extends TCPDF
{
    protected $publication_title_for_footer;
    protected $publication_number_for_footer;
    protected $post_id;
    protected $last_page_flag = false;

    /**
     * Set the publication title for use in the footer.
     *
     * @param string $title The title of the publication.
     */
    public function setPublicationTitleForFooter($title)
    {
        $this->publication_title_for_footer = $title;
    }

    /**
     * Set the publication number for use in the footer.
     *
     * @param string $number The publication number.
     */
    public function setPublicationNumberForFooter($number)
    {
        $this->publication_number_for_footer = $number;
    }

    /**
     * Set the post ID for use in the footer.
     *
     * @param int $post_id The post ID.
     */
    public function setPostId($post_id)
    {
        $this->post_id = $post_id;
    }

    /**
     * Override Close method to set last page flag
     */
    public function Close()
    {
        $this->last_page_flag = true;
        parent::Close();
    }

    /**
     * Custom page footer for the PDF.
     */
    public function Footer()
    {
        if ($this->last_page_flag) {
            // SPECIAL FOOTER FOR LAST PAGE ONLY
            $this->SetY(-50); // Start 50mm from bottom for special footer
            $y_position = $this->GetY();
            $margins = $this->getMargins();
            $page_width = $this->getPageWidth();
            $content_width = $page_width - $margins['left'] - $margins['right'];

            // Black border line
            $this->Line($margins['left'], $y_position, $page_width - $margins['right'], $y_position);
            $y_position += 2;

            // Publication number (left) and publish history (right)
            $formatted_pub_number_string = format_publication_number_for_display($this->publication_number_for_footer);
            $latest_published_info = get_latest_published_date($this->post_id);
            
            // Status labels for publication history
            $status_labels = [
                1 => 'Unpublished/Removed',
                2 => 'Published',
                4 => 'Published with Minor Revisions',
                5 => 'Published with Major Revisions',
                6 => 'Published with Full Review',
                7 => 'Historic/Archived',
                8 => 'In Review for Minor Revisions',
                9 => 'In Review for Major Revisions',
                10 => 'In Review'
            ];
            
            $publish_history_text = '';
            if (!empty($latest_published_info['date']) && !empty($latest_published_info['status'])) {
                $status_label = isset($status_labels[$latest_published_info['status']]) ? $status_labels[$latest_published_info['status']] : 'Published';
                $publish_history_text = $status_label . ' on ' . date('F j, Y', strtotime($latest_published_info['date']));
            }

            $this->SetFont('georgia', 'B', 8);
            $this->writeHTMLCell($content_width / 2, 5, $margins['left'], $y_position, $formatted_pub_number_string, 0, 0, false, true, 'L');

            $this->SetFont('georgia', '', 8);
            $this->writeHTMLCell($content_width / 2, 5, $margins['left'] + ($content_width / 2), $y_position, $publish_history_text, 0, 0, false, true, 'R');
            $y_position += 5; // Reduced from 8 to 5mm spacing

            // Black border line
            $this->Line($margins['left'], $y_position, $page_width - $margins['right'], $y_position);
            $y_position += 1; // Reduced from 3 to 1mm spacing

            // Footer paragraph
            $footer_paragraph = 'Published by University of Georgia Cooperative Extension. For more information or guidance, contact your local Extension office. <em>The University of Georgia College of Agricultural and Environmental Sciences (working cooperatively with Fort Valley State University, the U.S. Department of Agriculture, and the counties of Georgia) offers its educational programs, assistance, and materials to all people without regard to age, color, disability, genetic information, national origin, race, religion, sex, or veteran status, and is an Equal Opportunity Institution.</em>';

            $this->SetFont('georgia', '', 7);
            $this->writeHTMLCell($content_width, 30, $margins['left'], $y_position, $footer_paragraph, 0, 0, false, true, 'L');
        } else {
            // REGULAR FOOTER FOR ALL OTHER CONTENT PAGES
            // Set position at 15 mm from bottom
            $this->SetY(-15);
            $y_position = $this->GetY(); // Store the exact Y position
            $this->SetFont('georgia', '', 8);

            $footer_text_prefix = 'UGA Cooperative Extension ';
            $formatted_pub_number_string = format_publication_number_for_display($this->publication_number_for_footer);

            $left_content = $footer_text_prefix . $formatted_pub_number_string . ' | <strong>' . $this->publication_title_for_footer . '</strong>';

            $margins = $this->getMargins();
            $page_width = $this->getPageWidth();

            // Left content using writeHTMLCell
            $this->writeHTMLCell($page_width - $margins['left'] - $margins['right'] - 20, 10, $margins['left'], $y_position, $left_content, 0, 0, false, true, 'L');

            // Right content using writeHTMLCell  
            $this->writeHTMLCell(15, 10, $page_width - $margins['right'] - 15, $y_position, $this->getAliasNumPage(), 0, 0, false, true, 'R');
        }
    }

    /**
     * Check if there's enough space on the current page, add page break if needed
     *
     * @param int $height_needed Height needed in user units (mm)
     */
    public function checkSpaceAndBreak($height_needed = 50)
    {
        $margins = $this->getMargins();
        $current_y = $this->GetY();
        $page_height = $this->getPageHeight();
        $bottom_margin = $margins['bottom'];

        // Calculate available space
        $available_space = $page_height - $current_y - $bottom_margin;

        // If not enough space, add a page break
        if ($available_space < $height_needed) {
            $this->AddPage();
        }
    }
}

/**
 * Get the latest published date and status from the publication history
 *
 * @param int $post_id The post ID
 * @return array Array with 'date' and 'status' keys, or empty array if none found
 */
function get_latest_published_date($post_id)
{
    // Get the history field from ACF
    $history = get_field('history', $post_id);

    // Published status IDs
    $published_statuses = [2, 4, 5, 6];

    $latest_date = '';
    $latest_status = 0;
    $latest_timestamp = 0;

    if ($history && is_array($history)) {
        foreach ($history as $item) {
            $status = isset($item['status']) ? intval($item['status']) : 0;
            $date = isset($item['date']) ? $item['date'] : '';

            // Check if this is a published status
            if (in_array($status, $published_statuses) && !empty($date)) {
                // Convert date to timestamp for comparison
                $timestamp = strtotime($date);

                // Keep track of the latest date
                if ($timestamp > $latest_timestamp) {
                    $latest_timestamp = $timestamp;
                    $latest_date = $date;
                    $latest_status = $status;
                }
            }
        }
    }

    return [
        'date' => $latest_date,
        'status' => $latest_status
    ];
}

/**
 * Add CSS styling for tables to ensure proper formatting in PDF
 *
 * @param string $content The HTML content to style
 * @return string The content with CSS styling added
 */
function add_table_styling_for_pdf($content)
{
    // Define CSS styles for tables and images - TCPDF compatible
    $table_css = '
    <style>
        table, table.pdf-table {
            border-collapse: collapse;
            border: 1px solid #333333;
            width: 100%;
            margin: 8px 0px;
            font-family: georgia;
            page-break-inside: avoid;
            page-break-before: auto;
            page-break-after: auto;
        }
        
        table th, table.pdf-table th,
        table td, table.pdf-table td {
            border: 1px solid #333333;
            padding: 4px 6px;
            text-align: left;
            vertical-align: top;
            line-height: 1.3;
            font-size: 10px;
        }
        
        table th, table.pdf-table th {
            background-color: #e8e8e8;
            font-weight: bold;
        }
        
        table tr, table.pdf-table tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        
        /* WordPress editor tables might have these classes */
        .wp-block-table {
            page-break-inside: avoid;
        }
        
        .wp-block-table table {
            border-collapse: collapse;
            border: 1px solid #333333;
            page-break-inside: avoid;
        }
        
        .wp-block-table table th,
        .wp-block-table table td {
            border: 1px solid #333333;
            padding: 4px 6px;
        }
        
        /* Handle figure captions for tables */
        .wp-block-table figcaption,
        table caption {
            font-weight: bold;
            margin-bottom: 5px;
            text-align: left;
            font-size: 11px;
            page-break-after: avoid;
        }
        
        /* UPDATED: Semantic and accessible image styling using TCPDF-compatible CSS */
        .pdf-figure-wrapper {
            margin: 15px 0;
            page-break-inside: avoid;
        }
        
        .pdf-centered-content {
            display: block;
            width: 100%;
        }
        
        .pdf-centered-content img {
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Figure and image handling */
        figure, .wp-block-image {
            page-break-inside: avoid;
            margin: 15px 0;
        }
        
        figure img, .wp-block-image img {
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Enhanced figcaption styling */
        figcaption, .wp-block-image figcaption {
            font-weight: bold;
            margin-top: 10px;
            margin-bottom: 5px;
            text-align: center;
            font-size: 11px;
            page-break-before: avoid;
            page-break-after: avoid;
            line-height: 1.4;
            padding: 0 10px;
            display: block;
            width: 100%;
        }
        
        /* General content flow improvements */
        h1, h2, h3, h4, h5, h6 {
            page-break-after: avoid;
        }
        
        /* Prevent orphaned content */
        p {
            orphans: 3;
            widows: 3;
        }
    </style>';

    // Add the CSS at the beginning of the content
    $styled_content = $table_css . $content;

    return $styled_content;
}

/**
 * Process HTML content for better PDF rendering with page break handling
 * Uses regex instead of DOMDocument to avoid HTML5 tag warnings
 *
 * @param string $content The HTML content to process
 * @param MYPDF $pdf The PDF object for calculating dimensions
 * @return string The processed content
 */
function process_content_for_pdf($content, $pdf)
{
    // Calculate image dimensions once for reuse
    $margins = $pdf->getMargins();
    $available_width = $pdf->getPageWidth() - $margins['left'] - $margins['right'];
    $image_width_mm = $available_width * 0.7;
    $image_width_px = $image_width_mm * 3.78; // Convert mm to pixels (approximate)
    $width_attr = 'width="' . round($image_width_px) . '"';

    // Helper function to process images with semantic markup
    $process_image = function ($img_html) use ($width_attr) {
        // Remove any existing width/height attributes that might override our sizing
        $img_html = preg_replace('/\s*style\s*=\s*["\'][^"\']*["\']/', '', $img_html);
        $img_html = preg_replace('/\s*width\s*=\s*["\']?[^"\'\s>]+["\']?/', '', $img_html);
        $img_html = preg_replace('/\s*height\s*=\s*["\']?[^"\'\s>]+["\']?/', '', $img_html);

        // Add our width attribute and inline centering style
        $img_html = str_replace('<img', '<img ' . $width_attr . ' style="display: block; margin: 0 auto;"', $img_html);

        return $img_html;
    };

    // Process data tables for better page break handling
    $content = preg_replace_callback(
        '/<table([^>]*)>/i',
        function ($matches) {
            $existing_attributes = $matches[1];

            // Check if border attribute already exists
            if (!preg_match('/border\s*=/', $existing_attributes)) {
                $existing_attributes .= ' border="1"';
            }

            // Check if cellpadding attribute already exists
            if (!preg_match('/cellpadding\s*=/', $existing_attributes)) {
                $existing_attributes .= ' cellpadding="4"';
            }

            // Check if cellspacing attribute already exists
            if (!preg_match('/cellspacing\s*=/', $existing_attributes)) {
                $existing_attributes .= ' cellspacing="0"';
            }

            // Add nobr="true" to prevent tables from breaking across pages when possible
            if (!preg_match('/nobr\s*=/', $existing_attributes)) {
                $existing_attributes .= ' nobr="true"';
            }

            // Add or append CSS class
            if (preg_match('/class\s*=\s*["\']([^"\']*)["\']/', $existing_attributes, $class_matches)) {
                $existing_class = $class_matches[1];
                $new_class = trim($existing_class . ' pdf-table');
                $existing_attributes = preg_replace(
                    '/class\s*=\s*["\']([^"\']*)["\']/',
                    'class="' . $new_class . '"',
                    $existing_attributes
                );
            } else {
                $existing_attributes .= ' class="pdf-table"';
            }

            return '<table' . $existing_attributes . '>';
        },
        $content
    );

    // STEP 1: Process WordPress image blocks with semantic markup
    $content = preg_replace_callback(
        '/<figure([^>]*(?:class="[^"]*wp-block-image[^"]*"|wp-block-image)[^>]*)>(.*?)<\/figure>/is',
        function ($matches) use ($process_image) {
            $figure_attributes = $matches[1];
            $figure_content = $matches[2];

            // Process any images inside this figure
            $figure_content = preg_replace_callback(
                '/<img([^>]*)>/i',
                function ($img_matches) use ($process_image) {
                    return $process_image($img_matches[0]);
                },
                $figure_content
            );

            // Process figcaptions with proper semantic markup and centering
            $figure_content = preg_replace_callback(
                '/<figcaption([^>]*)>(.*?)<\/figcaption>/is',
                function ($caption_matches) {
                    $caption_attributes = $caption_matches[1];
                    $caption_content = $caption_matches[2];

                    // Use semantic figcaption with inline centering style
                    return '<figcaption' . $caption_attributes . ' style="display: block; text-align: center; margin: 10px auto 5px auto; font-weight: bold; font-size: 11px; line-height: 1.4; width: 100%;">' .
                        $caption_content .
                        '</figcaption>';
                },
                $figure_content
            );

            // Return semantic figure with page break handling
            return '<tcpdf method="checkSpaceAndBreak" params="80" />' .
                '<figure' . $figure_attributes . ' style="margin: 15px 0; page-break-inside: avoid;">' .
                $figure_content .
                '</figure>';
        },
        $content
    );

    // STEP 2: Handle other figures (not wp-block-image) with semantic markup
    $content = preg_replace_callback(
        '/<figure(?![^>]*(?:class="[^"]*wp-block-image|wp-block-image))([^>]*)>(.*?)<\/figure>/is',
        function ($matches) use ($process_image) {
            $figure_attributes = $matches[1];
            $figure_content = $matches[2];

            // Process any images inside this figure
            $figure_content = preg_replace_callback(
                '/<img([^>]*)>/i',
                function ($img_matches) use ($process_image) {
                    return $process_image($img_matches[0]);
                },
                $figure_content
            );

            // Process figcaptions with proper semantic markup and centering
            $figure_content = preg_replace_callback(
                '/<figcaption([^>]*)>(.*?)<\/figcaption>/is',
                function ($caption_matches) {
                    $caption_attributes = $caption_matches[1];
                    $caption_content = $caption_matches[2];

                    return '<figcaption' . $caption_attributes . ' style="display: block; text-align: center; margin: 10px auto 5px auto; font-weight: bold; font-size: 11px; line-height: 1.4; width: 100%;">' .
                        $caption_content .
                        '</figcaption>';
                },
                $figure_content
            );

            return '<tcpdf method="checkSpaceAndBreak" params="80" />' .
                '<figure' . $figure_attributes . ' style="margin: 15px 0; page-break-inside: avoid;">' .
                $figure_content .
                '</figure>';
        },
        $content
    );

    // STEP 3: Process any remaining standalone images with semantic wrapper
    $content = preg_replace_callback(
        '/<img([^>]*)>/i',
        function ($matches) use ($process_image) {
            $processed_img = $process_image($matches[0]);

            // Wrap standalone images in a semantic div (not a layout table)
            return '<tcpdf method="checkSpaceAndBreak" params="80" />' .
                '<div style="margin: 15px 0; page-break-inside: avoid;">' .
                $processed_img .
                '</div>';
        },
        $content
    );

    // STEP 4: Handle any remaining standalone figcaptions with semantic markup
    $content = preg_replace_callback(
        '/<figcaption([^>]*)>(.*?)<\/figcaption>/is',
        function ($matches) {
            $caption_attributes = $matches[1];
            $caption_content = $matches[2];

            return '<figcaption' . $caption_attributes . ' style="display: block; text-align: center; margin: 10px auto 5px auto; font-weight: bold; font-size: 11px; line-height: 1.4; width: 100%;">' .
                $caption_content .
                '</figcaption>';
        },
        $content
    );

    return $content;
}

/****** Publication Dynamic PDF ******/
/**
 * Generates a PDF version of a publication post type.
 */
function generate_pdf()
{
    try {
        // Get post ID from GET request, validate it.
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        if (!$post_id) {
            wp_die('Invalid post ID.');
        }

        // Retrieve post data and validate post type.
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'publications') {
            wp_die('Invalid publication.');
        }

        // Get custom fields using ACF.
        $fields = get_fields($post_id);

        // --- Dynamic Metadata and Data for PDF Content ---

        // Retrieve the publication title.
        $publication_title = $post->post_title;

        // Retrieve authors from ACF and prepare for PDF metadata and cover page display.
        $authors_data = get_field('authors', $post_id, false);
        $author_names = []; // For PDF metadata.
        $author_lines = []; // For individual author lines in cover display

        if ($authors_data) {
            foreach ($authors_data as $item) {
                $user_id = null;
                // Check for 'user' key (standard ACF user field format).
                if (isset($item['user']) && !empty($item['user'])) {
                    $user_id = is_array($item['user']) ? ($item['user']['ID'] ?? null) : $item['user'];
                }

                // Fallback: Check for numeric values in any field (ACF internal field keys).
                if (empty($user_id) && is_array($item)) {
                    foreach ($item as $key => $value) {
                        if (is_numeric($value) && $value > 0) {
                            $user_id = $value;
                            break;
                        }
                    }
                }

                if ($user_id && is_numeric($user_id)) {
                    $first_name = get_the_author_meta('first_name', $user_id);
                    $last_name = get_the_author_meta('last_name', $user_id);
                    $author_title = get_the_author_meta('title', $user_id); // Assumes a 'title' user meta field.

                    if ($first_name || $last_name) {
                        $full_name = trim("$first_name $last_name");
                        $author_names[] = $full_name;

                        // Build individual author line for cover display
                        $author_line = '<strong>' . esc_html($full_name) . '</strong>';
                        if (!empty($author_title)) {
                            $author_line .= ', ' . esc_html($author_title);
                        }
                        $author_lines[] = $author_line;
                    }
                }
            }
        }

        // Create single paragraph with all authors separated by <br> tags
        $cover_authors_html = '';
        if (!empty($author_lines)) {
            $cover_authors_html = '<p style="text-align: left; margin-bottom: 0px; line-height: 1.3;">' . implode('<br>', $author_lines) . '</p>';
        }
        $formatted_authors = implode(', ', $author_names);

        // Retrieve 'topics' taxonomy terms and format for PDF keywords.
        $topics_terms = get_the_terms($post_id, 'topics');
        $keyword_terms = [];

        if ($topics_terms && !is_wp_error($topics_terms)) {
            foreach ($topics_terms as $term) {
                $keyword_terms[] = $term->name;
            }
        }
        $formatted_keywords = implode(', ', $keyword_terms);
        // Provide fallback keywords if no topics are assigned to the post.
        if (empty($formatted_keywords)) {
            $formatted_keywords = 'Expert Resource';
        }

        // Retrieve 'publication_number' ACF field.
        $publication_number = get_field('publication_number', $post_id);
        // Ensure publication number is an empty string if not set, for cleaner footer output.
        if (empty($publication_number)) {
            $publication_number = '';
        }

        // Retrieve featured image URL for the cover page.
        $featured_image_url = '';
        if (has_post_thumbnail($post_id)) {
            $featured_image_id = get_post_thumbnail_id($post_id);
            // Get the URL for the 'large' image size.
            $featured_image_array = wp_get_attachment_image_src($featured_image_id, 'large');
            if ($featured_image_array) {
                $featured_image_url = $featured_image_array[0];
            }
        }

        // Get the latest published date from history
        $latest_published_info = get_latest_published_date($post_id);
        $latest_published_date = $latest_published_info['date'];

        // --- End: Dynamic Metadata and Data for PDF Content ---

        // Initialize MYPDF object (our extended TCPDF class).
        $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        // Pass dynamic data to the custom footer method.
        $pdf->setPublicationTitleForFooter($publication_title);
        $pdf->setPublicationNumberForFooter($publication_number);
        $pdf->setPostId($post_id);

        // Set PDF document metadata.
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor($formatted_authors);
        $pdf->SetTitle($publication_title);
        $pdf->SetKeywords($formatted_keywords);
        $pdf->SetSubject('ADA Compliant Publication');

        // Define the path to the custom Georgia TrueType font file.
        $fontpath = get_template_directory() . '/assets/fonts/Georgia.ttf';
        TCPDF_FONTS::addTTFfont($fontpath, 'TrueTypeUnicode', '', 32);

        // Load bold Georgia for <strong> tags to work
        $fontpath_bold = get_template_directory() . '/assets/fonts/Georgia-Bold.ttf'; // or GeorgiaBold.ttf
        TCPDF_FONTS::addTTFfont($fontpath_bold, 'TrueTypeUnicode', '', 32);

        // Load italic Georgia for <em> tags to work
        $fontpath_italic = get_template_directory() . '/assets/fonts/Georgia-Italic.ttf'; // or GeorgiaItalic.ttf
        TCPDF_FONTS::addTTFfont($fontpath_italic, 'TrueTypeUnicode', '', 32);

        // Load Georgia bold italic for <strong><em> tags to work
        $fontpath_bold_italic = get_template_directory() . '/assets/fonts/Georgia-Bold-Italic.ttf'; // or GeorgiaBoldItalic.ttf
        TCPDF_FONTS::addTTFfont($fontpath_bold_italic, 'TrueTypeUnicode', '', 32);

        // Set default page margins.
        $pdf->SetMargins(15, 15, 15);
        // Set the default font for the entire document.
        $pdf->SetFont('georgia', '', 12);

        // Disable header and footer for the cover page only.
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        // Add the first page, which serves as the cover.
        $pdf->AddPage();

        // --- Cover Page Content ---

        // Track the current Y position to ensure proper spacing
        $current_y = 0; // Start from very top

        // Embed featured image at top edge-to-edge if available
        if (!empty($featured_image_url)) {
            // Get original image dimensions
            list($width, $height) = getimagesize($featured_image_url);

            // Always scale to full page width (edge-to-edge)
            $img_width_mm = $pdf->getPageWidth();
            $img_height_mm = ($height / $width) * $img_width_mm;

            // Position image at top-left corner for true edge-to-edge
            $pdf->Image($featured_image_url, 0, 0, $img_width_mm, $img_height_mm, '', '', '', false, 300, '', false, false, 0, false, false, false);

            // Update current Y position to be below the image with spacing
            $current_y = $img_height_mm + 20;
        } else {
            // Add some spacing if no featured image is present
            $current_y = 30;
        }

        // Add Extension logo in the white space below image - LEFT ALIGNED
        $extension_logo_path = get_template_directory() . '/assets/images/Extension_logo_Formal_FC.png';
        if (file_exists($extension_logo_path)) {
            // Calculate logo dimensions - 30% of page width
            $logo_width_mm = ($pdf->getPageWidth() - 30) * 0.3;

            // Get original logo dimensions to maintain aspect ratio
            list($logo_orig_width, $logo_orig_height) = getimagesize($extension_logo_path);
            $logo_height_mm = ($logo_orig_height / $logo_orig_width) * $logo_width_mm;

            // Left align the logo (with margin)
            $logo_x = 15; // Standard left margin

            // Add the logo
            $pdf->Image($extension_logo_path, $logo_x, $current_y, $logo_width_mm, $logo_height_mm, '', '', '', false, 300, '', false, false, 0, false, false, false);

            // Update current Y position with tighter spacing
            $current_y += $logo_height_mm + 10;
        }

        // Set Y position for title
        $pdf->SetY($current_y);

        // Display publication title - LEFT ALIGNED
        $pdf->SetFont('georgia', 'B', 24);
        $pdf->MultiCell(0, 10, $post->post_title, 0, 'L', 0, 1, '', '', true, 0, true);

        // Add spacing after title and update position
        $pdf->Ln(10); // Reduced spacing
        $current_y = $pdf->GetY();

        // Display authors - LEFT ALIGNED with tight line spacing
        $pdf->SetFont('georgia', '', 12);
        $pdf->SetY($current_y);

        // Output the authors HTML (already formatted with single <p> tag and <br> separators)
        $pdf->writeHTML($cover_authors_html, true, false, true, false, '');

        // Add published date with publication number if available
        if (!empty($latest_published_date)) {
            $pdf->Ln(8); // Small space after authors
            $pdf->SetFont('georgia', '', 11);

            // Format the publication number for display
            $formatted_pub_number = format_publication_number_for_display($publication_number);

            // Create the publication date text with publication number
            $date_text = '';
            if (!empty($formatted_pub_number)) {
                $date_text = $formatted_pub_number . ' published on ' . esc_html($latest_published_date);
            } else {
                $date_text = 'Published on ' . esc_html($latest_published_date);
            }

            $date_html = '<p style="text-align: left; margin-bottom: 0px; line-height: 1.3;">' . $date_text . '</p>';
            $pdf->writeHTML($date_html, true, false, true, false, '');
        }

        // --- End Cover Page Content ---

        // Add a new page to begin the main content of the publication.
        $pdf->AddPage();
        // Enable footer for all subsequent content pages, but keep header disabled to avoid top border.
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);

        // Add the main post content to the PDF with table styling.
        $pdf->SetFont('georgia', '', 12);

        // *** Configure page break settings for better content flow ***
        // Enable automatic page breaks - use larger bottom margin to account for potential last page footer
        $pdf->SetAutoPageBreak(true, 50); // 50mm bottom margin to accommodate special footer

        // Set image scale factor for consistent rendering
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // Handle various potential formats for WordPress post content.
        if (is_array($post->post_content)) {
            $post_content = implode('', $post->post_content);
        } elseif (is_object($post->post_content)) {
            $post_content = json_encode($post->post_content);
        } else {
            $post_content = $post->post_content;
        }

        // *** NEW: Process the content to add table styling and page break handling ***
        $post_content = process_content_for_pdf($post_content, $pdf);
        $post_content = add_table_styling_for_pdf($post_content);

        $pdf->writeHTML($post_content, true, false, true, false, '');

        // Output the generated PDF file, forcing download.
        $file_name = sanitize_title($post->post_title) . '.pdf';
        $pdf->Output($file_name, 'D');

        exit;
    } catch (Exception $e) {
        // Log any exceptions that occur during PDF generation for debugging.
        error_log('PDF Generation Error: ' . $e->getMessage());
        // Display a user-friendly error message to the user.
        wp_die('An error occurred while generating the PDF. Please try again later.');
    }
}

// Register the action hook for generating the PDF for authenticated users.
add_action('admin_post_generate_pdf', 'generate_pdf');
// Register the action hook for generating the PDF for non-authenticated users.
add_action('admin_post_nopriv_generate_pdf', 'generate_pdf');