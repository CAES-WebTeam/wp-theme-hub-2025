<?php
// ===================
// PUBLICATION DYNAMIC PDF GENERATION UTILITY - mPDF VERSION
// This file contains the logic for generating a PDF using mPDF instead of TCPDF.
// ===================

require_once get_template_directory() . '/vendor/autoload.php';

use Mpdf\Mpdf;
use Mpdf\HTMLParserMode;

// Reuse your existing helper functions (these don't need TCPDF)
// Keep: normalize_hyphens_for_pdf, format_publication_number_for_display, get_latest_published_date

function normalize_hyphens_for_pdf($content)
{
    $replacements = [
        "\u{2010}" => '-', // Hyphen
        "\u{2013}" => '-', // En Dash (replaces 'ÃƒÂ¢Ã¢â€šÂ¬')
        "\u{2014}" => '-', // Em Dash (replaces 'ÃƒÆ'Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬')
    ];
    $content = str_replace(array_keys($replacements), array_values($replacements), $content);
    return $content;
}

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

function get_latest_published_date($post_id)
{
    $history = get_field('history', $post_id);
    $published_statuses = [2, 4, 5, 6];

    $latest_date = '';
    $latest_status = 0;
    $latest_timestamp = 0;

    if ($history && is_array($history)) {
        foreach ($history as $item) {
            $status = isset($item['status']) ? intval($item['status']) : 0;
            $date = isset($item['date']) ? $item['date'] : '';

            if (in_array($status, $published_statuses) && !empty($date)) {
                $timestamp = strtotime($date);
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

// Enhanced table and content processing for mPDF
function process_content_for_mpdf($content)
{
    // This revised function intelligently identifies legacy image containers
    // by their structure: a div containing an image followed by loose caption text.
    $content = preg_replace_callback(
        '/<div class="(left|right|center|alignleft|alignright|aligncenter)" style="width: (\d+)px;">\s*(<img[^>]+>)(.*?)<\/div>/is',
        function ($matches) {
            $alignment_class = $matches[1];
            // Normalize WordPress alignment classes
            if ($alignment_class == 'alignleft' || $alignment_class == 'left') {
                $alignment_class = 'left';
            } elseif ($alignment_class == 'alignright' || $alignment_class == 'right') {
                $alignment_class = 'right';
            } else {
                $alignment_class = 'center';
            }

            $width = $matches[2];
            $image_tag = $matches[3];
            // The caption is any text that follows the image inside the div.
            $caption_text = trim(strip_tags($matches[4], '<a><em><strong><i><b>'));

            // SAFETY CHECK: If there's no caption text, it's likely just a layout div.
            // In that case, we return the original HTML to avoid breaking anything.
            if (empty($caption_text)) {
                return $matches[0];
            }

            // Rebuild the HTML into our new, correct structure.
            $html = '<div class="image-caption-wrapper ' . $alignment_class . '" style="width: ' . $width . 'px;">';
            // We add the image and then wrap the loose text in a paragraph tag for proper styling and wrapping.
            $html .= $image_tag;
            $html .= '<p class="wp-caption-text">' . $caption_text . '</p>';
            $html .= '</div>';

            return $html;
        },
        $content
    );

    // Clean up any empty paragraphs that might have been left behind.
    $content = str_replace(['<p></p>', '<p>&nbsp;</p>'], '', $content);

    return $content;
}

// Get CSS for mPDF with improved accessibility and spacing
function get_mpdf_styles()
{
    // Increased font sizes for cover page elements and added image alignment styles.
    $styles = '
        body { font-family: "georgia", serif; font-size: 13px; line-height: 1.6; color: #333; }
        
        h1, h2, h3, h4, h5, h6 { font-family: "georgia", serif; color: #000; }
        h1 { font-size: 24px; font-weight: bold; margin: 24px 0 12px 0; }
        h2 { font-size: 20px; font-weight: bold; margin: 22px 0 10px 0; }
        h3 { font-size: 18px; font-weight: bold; margin: 20px 0 8px 0; }
        h4 { font-size: 16px; font-weight: bold; margin: 18px 0 6px 0; }
        h5 { font-size: 15px; font-weight: bold; margin: 16px 0 4px 0; }
        h6 { font-size: 14px; font-weight: bold; margin: 14px 0 4px 0; }

        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        table th, table td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }
        table th { background-color: #f2f2f2; font-weight: bold; font-size: 13px; }

        .footer-spacer { height: 50px; }
        .page-break { page-break-before: always; }

        /* Styles for Image and Caption Alignment */
        .image-caption-wrapper {
            margin-bottom: 15px; /* Space below caption */
        }
        .image-caption-wrapper.center {
            text-align: center; /* Center the caption text */
            margin-left: auto;
            margin-right: auto;
        }
        .image-caption-wrapper img {
            margin-bottom: 5px; /* Space between image and caption */
        }
        .wp-caption-text {
            font-size: 12px;
            color: #555;
            line-height: 1.4;
            text-align: left; /* Default text alignment */
        }
        .image-caption-wrapper.center .wp-caption-text {
            text-align: center; /* Center caption text only when wrapper is centered */
        }
    ';
    return $styles;
}

// Generate regular footer HTML for content pages - improved accessibility
function generate_regular_footer_html($post_id, $publication_title, $publication_number)
{
    $formatted_pub_number_string = format_publication_number_for_display($publication_number);
    $footer_text_prefix = 'UGA Cooperative Extension ';
    $left_content = $footer_text_prefix . $formatted_pub_number_string . ' | <strong>' . $publication_title . '</strong>';

    return '
    <table width="100%" style="font-size: 10px; font-family: georgia; border: none; border-collapse: collapse; margin: 0; padding: 2px 0;">
        <tr>
            <td style="text-align: left; width: 85%; border: none; line-height: 1.2; margin: 0; padding: 0;">' . $left_content . '</td>
            <td style="text-align: right; width: 15%; border: none; line-height: 1.2; margin: 0; padding: 0;">{PAGENO}</td>
        </tr>
    </table>';
}

// Generate special last page footer HTML - improved accessibility and spacing
function generate_last_page_footer_html($post_id, $publication_number)
{
    $formatted_pub_number_string = format_publication_number_for_display($publication_number);
    $latest_published_info = get_latest_published_date($post_id);

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

    $permalink_url = get_permalink($post_id);
    $permalink_text = '';
    if (!empty($permalink_url)) {
        $permalink_text = 'The permalink for this UGA Extension publication is <a href="' . esc_url($permalink_url) . '">' . esc_html($permalink_url) . '</a>';
    }

    $footer_paragraph = 'Published by University of Georgia Cooperative Extension. For more information or guidance, contact your local Extension office. <em>The University of Georgia College of Agricultural and Environmental Sciences (working cooperatively with Fort Valley State University, the U.S. Department of Agriculture, and the counties of Georgia) offers its educational programs, assistance, and materials to all people without regard to age, color, disability, genetic information, national origin, race, religion, sex, or veteran status, and is an Equal Opportunity Institution.</em>';

    return '
    <div style="font-size: 9px; text-align: center; margin: 2px 0; font-family: georgia; line-height: 1.2;">' . $permalink_text . '</div>
    <hr style="border: 0; border-top: 1px solid #000; margin: 1px 0;">
    <table width="100%" style="font-size: 10px; font-family: georgia; margin: 1px 0; border: none; border-collapse: collapse;">
        <tr>
            <td style="text-align: left; width: 50%; font-weight: bold; border: none; line-height: 1.2; padding: 0;">' . $formatted_pub_number_string . '</td>
            <td style="text-align: right; width: 50%; border: none; line-height: 1.2; padding: 0;">' . $publish_history_text . '</td>
        </tr>
    </table>
    <hr style="border: 0; border-top: 1px solid #000; margin: 1px 0;">
    <div style="font-size: 9px; text-align: left; line-height: 1.3; font-family: georgia; margin: 2px 0;">' . $footer_paragraph . '</div>';
}

/**
 * Generates a PDF version of a publication using mPDF with improved accessibility and footer spacing
 */
function generate_publication_pdf_file_mpdf($post_id)
{
    try {
        // error_log("mPDF DEBUG: Starting PDF generation for post ID: $post_id");

        // Retrieve and validate post
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'publications') {
            // error_log('mPDF Generation: Invalid post or post type for ID: ' . $post_id);
            return false;
        }

        // error_log("mPDF DEBUG: Post validated successfully");

        // Gather all the data (same as TCPDF version)
        $publication_title = $post->post_title;
        $publication_number = get_field('publication_number', $post_id);

        if (empty($publication_number)) {
            // error_log('mPDF Generation: No publication number found for post ID: ' . $post_id);
            $publication_number = 'publication-' . $post_id;
        }

        // Get authors data
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

        $formatted_authors = implode(', ', $author_names);
        // error_log("mPDF DEBUG: Authors processed - found " . count($author_names) . " authors");

        // Get topics for keywords
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

        // Get featured image
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

        // error_log("mPDF DEBUG: All data gathering complete");

        // File path setup (same as TCPDF)
        $upload_dir = wp_upload_dir();
        $cache_subdir = '/generated-pub-pdfs/';
        $cache_dir_path = $upload_dir['basedir'] . $cache_subdir;
        if (!file_exists($cache_dir_path)) {
            wp_mkdir_p($cache_dir_path);
        }

        $filename = sanitize_file_name($publication_number . '.pdf');
        $file_path = $cache_dir_path . $filename;
        $file_url = $upload_dir['baseurl'] . $cache_subdir . $filename;

        // error_log("mPDF DEBUG: File paths set - $filename");

        // Initialize mPDF with improved margins
        // error_log("mPDF DEBUG: Initializing mPDF instance");
        // Get default configurations
        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];

        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf = new Mpdf([
            'fontDir' => array_merge($fontDirs, [
                get_template_directory() . '/assets/fonts',
            ]),
            'fontdata' => $fontData + [
                'georgia' => [
                    'R' => 'Georgia.ttf',
                    'B' => 'Georgia-Bold.ttf',
                    'I' => 'Georgia-Italic.ttf',
                    'BI' => 'Georgia-Bold-Italic.ttf'
                ]
            ],
            'default_font' => 'georgia',
            'format' => 'Letter',
            'orientation' => 'P',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 35,    // Increased for better footer spacing
            'margin_header' => 0,
            'margin_footer' => 15     // Increased for footer clearance
        ]);
        // error_log("mPDF DEBUG: mPDF instance created successfully");

        // Set metadata
        $mpdf->SetCreator('UGA Extension');
        $mpdf->SetAuthor($formatted_authors);
        $mpdf->SetTitle($publication_title);
        $mpdf->SetKeywords($formatted_keywords);
        $mpdf->SetSubject('ADA Compliant Publication');

        // Add custom styles with improved accessibility
        // error_log("mPDF DEBUG: Processing CSS styles");
        $css = get_mpdf_styles();
        $mpdf->WriteHTML($css, HTMLParserMode::HEADER_CSS);
        // error_log("mPDF DEBUG: CSS styles applied");

        // COVER PAGE - No header/footer
        // error_log("mPDF DEBUG: Starting cover page generation");
        $mpdf->SetHTMLHeader('');
        $mpdf->SetHTMLFooter('');

        // Simple HTML approach that works with mPDF
        if (!empty($featured_image_url)) {
            $cover_html = '
            <div style="text-align: center; margin: 0 -15mm;">
                <img src="' . $featured_image_url . '" style="width: 100%; height: auto; max-width: none; margin-top: -15mm;">
            </div>
            <div style="margin-top: 15mm;">';
        } else {
            $cover_html = '<div style="margin-top: 30px;">';
        }

        // Extension logo
        $extension_logo_path = get_template_directory() . '/assets/images/Extension_logo_Formal_FC.png';
        if (file_exists($extension_logo_path)) {
            $cover_html .= '<img src="' . $extension_logo_path . '" style="width: 30%; height: auto; margin-bottom: 10px;">';
        }

        // Title
        $cover_html .= '<h1 style="font-size: 32px; font-weight: bold; margin: 20px 0 20px 0; line-height: 1.2;">' . esc_html($publication_title) . '</h1>';

        // Authors
        if (!empty($author_lines)) {
            $cover_html .= '<div style="margin: 10px 0; line-height: 1.3; font-size: 18px;">' . implode('<br>', $author_lines) . '</div>';
        }

        // Publication date and number
        if (!empty($latest_published_date)) {
            $formatted_pub_number = format_publication_number_for_display($publication_number);
            $date_text = '';
            if (!empty($formatted_pub_number)) {
                $date_text = $formatted_pub_number . ' published on ' . esc_html($latest_published_date);
            } else {
                $date_text = 'Published on ' . esc_html($latest_published_date);
            }
            $cover_html .= '<div style="margin: 15px 0; font-size: 11px;">' . $date_text . '</div>';
        }

        $cover_html .= '</div>';

        // Close the additional div for the featured image case
        if (!empty($featured_image_url)) {
            $cover_html .= '</div>';
        }

        // error_log("mPDF DEBUG: Cover HTML built, writing to PDF");
        $mpdf->WriteHTML($cover_html);
        // error_log("mPDF DEBUG: Cover page written successfully");

        // CONTENT PAGES - With regular footer
        // error_log("mPDF DEBUG: Starting content pages setup");
        $mpdf->AddPage();

        // Set regular footer for content pages
        $regular_footer = generate_regular_footer_html($post_id, $publication_title, $publication_number);
        $mpdf->SetHTMLFooter($regular_footer);
        // error_log("mPDF DEBUG: Regular footer set");

        // Process and add main content
        // error_log("mPDF DEBUG: Processing post content");
        $post_content = $post->post_content;
        if (is_array($post_content)) {
            $post_content = implode('', $post_content);
        } elseif (is_object($post_content)) {
            $post_content = json_encode($post_content);
        }

        $processed_content = process_content_for_mpdf($post_content);

        // Add spacing at the end of content to prevent footer overlap
        $processed_content .= '<div class="footer-spacer"></div>';

        // error_log("mPDF DEBUG: Content processed, writing to PDF");
        $mpdf->WriteHTML($processed_content);
        // error_log("mPDF DEBUG: Main content written successfully");

        // LAST PAGE - Add special footer
        // Force a new page for the special footer
        // error_log("mPDF DEBUG: Creating last page with special footer");
        // $mpdf->WriteHTML('<div class="page-break"></div>');

        // Set special last page footer
        $last_page_footer = generate_last_page_footer_html($post_id, $publication_number);
        $mpdf->SetHTMLFooter($last_page_footer);
        // error_log("mPDF DEBUG: Last page footer set");

        // Save the PDF
        // error_log("mPDF DEBUG: Starting PDF output to file: $file_path");
        $mpdf->Output($file_path, 'F');
        // error_log("mPDF DEBUG: PDF successfully saved to file");

        // error_log("mPDF DEBUG: PDF generation completed successfully for post ID: $post_id");
        return $file_url;
    } catch (Exception $e) {
        // error_log('mPDF Generation Error for Post ID ' . $post_id . ': ' . $e->getMessage());
        return false;
    }
}
