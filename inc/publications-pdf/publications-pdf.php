<?php
// ===================
// PUBLICATION DYNAMIC PDF GENERATION UTILITY
// This file contains the logic for generating a PDF and saving it to a file.
// It does NOT handle HTTP requests or queuing directly.
// ===================

// Load TCPDF library FIRST, as MYPDF extends TCPDF.
require_once get_template_directory() . '/inc/tcpdf/tcpdf.php';

// Function to format the publication number for display
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

// Function to get the latest published date from the publication history
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

// Function to add table and image styling for PDF generation
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

// Function to process content for PDF generation
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

/**
 * Generates a PDF version of a publication post type and saves it to a file.
 *
 * @param int $post_id The ID of the post to generate PDF for.
 * @return string|false The URL of the generated PDF on success, false on failure.
 */
function generate_publication_pdf_file($post_id)
{
    try {
        // Retrieve post data and validate post type.
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'publications') {
            error_log('PDF Generation: Invalid post or post type for ID: ' . $post_id);
            return false;
        }

        // Get custom fields using ACF.
        $fields = get_fields($post_id);

        // --- Dynamic Metadata and Data for PDF Content ---

        $publication_title = $post->post_title;

        $authors_data = get_field('authors', $post_id, false);
        $author_names = [];
        $author_lines = [];
        if ($authors_data) {
            foreach ($authors_data as $item) {
                $user_id = null;
                if (isset($item['user']) && !empty($item['user'])) {
                    $user_id = is_array($item['user']) ? ($item['user']['ID'] ?? null) : $item['user'];
                }
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
                    $author_title = get_the_author_meta('title', $user_id);

                    if ($first_name || $last_name) {
                        $full_name = trim("$first_name $last_name");
                        $author_names[] = $full_name;
                        $author_line = '<strong>' . esc_html($full_name) . '</strong>';
                        if (!empty($author_title)) {
                            $author_line .= ', ' . esc_html($author_title);
                        }
                        $author_lines[] = $author_line;
                    }
                }
            }
        }
        $cover_authors_html = '';
        if (!empty($author_lines)) {
            $cover_authors_html = '<p style="text-align: left; margin-bottom: 0px; line-height: 1.3;">' . implode('<br>', $author_lines) . '</p>';
        }
        $formatted_authors = implode(', ', $author_names);

        $topics_terms = get_the_terms($post_id, 'topics');
        $keyword_terms = [];
        if ($topics_terms && !is_wp_error($topics_terms)) {
            foreach ($topics_terms as $term) {
                $keyword_terms[] = $term->name;
            }
        }
        $formatted_keywords = implode(', ', $keyword_terms);
        if (empty($formatted_keywords)) {
            $formatted_keywords = 'Expert Resource';
        }

        // Retrieve 'publication_number' ACF field for filename and footer.
        $publication_number = get_field('publication_number', $post_id);
        if (empty($publication_number)) {
            error_log('PDF Generation: No publication number found for post ID: ' . $post_id);
            // Fallback: If no publication number, use post ID for filename to ensure uniqueness.
            $publication_number = 'publication-' . $post_id;
        }

        $featured_image_url = '';
        if (has_post_thumbnail($post_id)) {
            $featured_image_id = get_post_thumbnail_id($post_id);
            $featured_image_array = wp_get_attachment_image_src($featured_image_id, 'large');
            if ($featured_image_array) {
                $featured_image_url = $featured_image_array[0];
            }
        }

        $latest_published_info = get_latest_published_date($post_id);
        $latest_published_date = $latest_published_info['date'];

        // --- End: Dynamic Metadata and Data for PDF Content ---

        // Determine file path and URL based on publication number
        $upload_dir = wp_upload_dir();
        $cache_subdir = '/generated-pub-pdfs/'; // Store PDFs in a dedicated subdirectory within uploads
        $cache_dir_path = $upload_dir['basedir'] . $cache_subdir;
        if (!file_exists($cache_dir_path)) {
            wp_mkdir_p($cache_dir_path); // Create directory if it doesn't exist
        }

        // Sanitize publication number for use as a filename
        $filename = sanitize_file_name($publication_number . '.pdf');
        $file_path = $cache_dir_path . $filename;
        $file_url = $upload_dir['baseurl'] . $cache_subdir . $filename;

        // Initialize MYPDF object.
        $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
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
        $fontpath_bold = get_template_directory() . '/assets/fonts/Georgia-Bold.ttf';
        TCPDF_FONTS::addTTFfont($fontpath_bold, 'TrueTypeUnicode', '', 32);

        // Load italic Georgia for <em> tags to work
        $fontpath_italic = get_template_directory() . '/assets/fonts/Georgia-Italic.ttf';
        TCPDF_FONTS::addTTFfont($fontpath_italic, 'TrueTypeUnicode', '', 32);

        // Load Georgia bold italic for <strong><em> tags to work
        $fontpath_bold_italic = get_template_directory() . '/assets/fonts/Georgia-Bold-Italic.ttf';
        TCPDF_FONTS::addTTFfont($fontpath_bold_italic, 'TrueTypeUnicode', '', 32);

        // Set default page margins.
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetFont('georgia', '', 12);

        // Disable header and footer for the cover page only.
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();

        // --- Cover Page Content ---
        $current_y = 0;
        if (!empty($featured_image_url)) {
            list($width, $height) = getimagesize($featured_image_url);
            $img_width_mm = $pdf->getPageWidth();
            $img_height_mm = ($height / $width) * $img_width_mm;
            $pdf->Image($featured_image_url, 0, 0, $img_width_mm, $img_height_mm, '', '', '', false, 300, '', false, false, 0, false, false, false);
            $current_y = $img_height_mm + 20;
        } else {
            $current_y = 30;
        }

        $extension_logo_path = get_template_directory() . '/assets/images/Extension_logo_Formal_FC.png';
        if (file_exists($extension_logo_path)) {
            $logo_width_mm = ($pdf->getPageWidth() - 30) * 0.3;
            list($logo_orig_width, $logo_orig_height) = getimagesize($extension_logo_path);
            $logo_height_mm = ($logo_orig_height / $logo_orig_width) * $logo_width_mm;
            $logo_x = 15;
            $pdf->Image($extension_logo_path, $logo_x, $current_y, $logo_width_mm, $logo_height_mm, '', '', '', false, 300, '', false, false, 0, false, false, false);
            $current_y += $logo_height_mm + 10;
        }

        $pdf->SetY($current_y);
        $pdf->SetFont('georgia', 'B', 24);
        $pdf->MultiCell(0, 10, $post->post_title, 0, 'L', 0, 1, '', '', true, 0, true);
        $pdf->Ln(10);
        $current_y = $pdf->GetY();

        $pdf->SetFont('georgia', '', 12);
        $pdf->SetY($current_y);
        $pdf->writeHTML($cover_authors_html, true, false, true, false, '');

        if (!empty($latest_published_date)) {
            $pdf->Ln(8);
            $pdf->SetFont('georgia', '', 11);
            $formatted_pub_number = format_publication_number_for_display($publication_number);
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

        $pdf->AddPage();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetFont('georgia', '', 12);
        $pdf->SetAutoPageBreak(true, 50);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        $post_content = $post->post_content;
        // Ensure post content is a string
        if (is_array($post_content)) {
            $post_content = implode('', $post_content);
        } elseif (is_object($post_content)) {
            $post_content = json_encode($post_content);
        }

        $post_content = process_content_for_pdf($post_content, $pdf);
        $post_content = add_table_styling_for_pdf($post_content);

        $pdf->writeHTML($post_content, true, false, true, false, '');

        // Output the generated PDF file to the specified path ('F' mode).
        $pdf->Output($file_path, 'F');

        // Return the URL of the generated file
        return $file_url;

    } catch (Exception $e) {
        error_log('PDF Generation Error for Post ID ' . $post_id . ': ' . $e->getMessage());
        return false; // Indicate failure
    }
}