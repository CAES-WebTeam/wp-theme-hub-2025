<?php
// ===================
// PUBLICATION DYNAMIC PDF GENERATION UTILITY - mPDF VERSION
// This file contains the logic for generating a PDF using mPDF instead of TCPDF.
// ===================

require_once get_template_directory() . '/vendor/autoload.php';

use Mpdf\Mpdf;
use Mpdf\HTMLParserMode;

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

// Process images to ensure they have proper dimensions for mPDF
function ensure_image_dimensions($content)
{
    // Match all img tags
    return preg_replace_callback(
        '/<img([^>]*)>/i',
        function ($matches) {
            $attributes = $matches[1];

            // Check if width is already set (either as attribute or in style)
            $has_width_attr = preg_match('/\bwidth\s*=\s*["\']?\d+/i', $attributes);
            $has_width_style = preg_match('/style\s*=\s*["\'][^"\']*width\s*:/i', $attributes);

            // If width is already defined, return unchanged
            if ($has_width_attr || $has_width_style) {
                return $matches[0];
            }

            // Extract src to get actual dimensions
            if (preg_match('/src\s*=\s*["\']([^"\']+)["\']/i', $attributes, $src_match)) {
                $src = $src_match[1];

                // Try to get actual image dimensions
                $dimensions = @getimagesize($src);

                if ($dimensions && isset($dimensions[0]) && isset($dimensions[1])) {
                    $actual_width = $dimensions[0];
                    $actual_height = $dimensions[1];

                    // Calculate max width based on page width (letter = 8.5", margins = 30mm total ≈ 1.18")
                    // Usable width ≈ 7.32" ≈ 555px at 72dpi, but let's use a safe max
                    $max_width = 550;

                    if ($actual_width > $max_width) {
                        // Scale down proportionally
                        $scale = $max_width / $actual_width;
                        $new_width = $max_width;
                        $new_height = round($actual_height * $scale);
                    } else {
                        // Use actual dimensions
                        $new_width = $actual_width;
                        $new_height = $actual_height;
                    }

                    // Add width and height attributes
                    return '<img' . $attributes . ' width="' . $new_width . '" height="' . $new_height . '">';
                } else {
                    // Fallback: set a reasonable max-width style if we can't get dimensions
                    // Check if there's an existing style attribute
                    if (preg_match('/style\s*=\s*["\']([^"\']*)["\']/', $attributes, $style_match)) {
                        $existing_style = rtrim($style_match[1], ';');
                        $new_style = $existing_style . '; max-width: 100%; height: auto;';
                        $attributes = preg_replace('/style\s*=\s*["\'][^"\']*["\']/', 'style="' . $new_style . '"', $attributes);
                    } else {
                        $attributes .= ' style="max-width: 100%; height: auto;"';
                    }
                    return '<img' . $attributes . '>';
                }
            }

            // If no src found, return unchanged
            return $matches[0];
        },
        $content
    );
}

// Calculate appropriate title font size based on length
function calculate_title_font_size($title, $has_subtitle = false)
{
    $length = mb_strlen($title);

    // Base sizes and thresholds
    $base_size = 32;
    $min_size = 18;

    // Adjust thresholds if there's a subtitle (titles with subtitles need smaller fonts sooner)
    if ($has_subtitle) {
        // With subtitle, start reducing earlier
        if ($length <= 40) {
            return $base_size;
        } elseif ($length <= 60) {
            return 28;
        } elseif ($length <= 80) {
            return 24;
        } elseif ($length <= 100) {
            return 22;
        } elseif ($length <= 130) {
            return 20;
        } else {
            return $min_size;
        }
    } else {
        // Without subtitle, we have more room
        if ($length <= 50) {
            return $base_size;
        } elseif ($length <= 70) {
            return 28;
        } elseif ($length <= 90) {
            return 24;
        } elseif ($length <= 120) {
            return 22;
        } elseif ($length <= 150) {
            return 20;
        } else {
            return $min_size;
        }
    }
}

// Enhanced table and content processing for mPDF
function process_content_for_mpdf($content)
{
    // 0. ENSURE ALL IMAGES HAVE DIMENSIONS
    $content = ensure_image_dimensions($content);

    // 1. IMAGE HANDLING
    // This logic intelligently identifies legacy image containers
    $content = preg_replace_callback(
        '/<div class="(left|right|center|alignleft|alignright|aligncenter)" style="width: (\d+)px;">.*?(<img[^>]+>)(.*?)<\/div>/is',
        function ($matches) {
            $alignment_class = $matches[1];
            if ($alignment_class == 'alignleft' || $alignment_class == 'left') {
                $alignment_class = 'left';
            } elseif ($alignment_class == 'alignright' || $alignment_class == 'right') {
                $alignment_class = 'right';
            } else {
                $alignment_class = 'center';
            }

            $width = $matches[2];
            $image_tag = $matches[3];
            $caption_text = trim(strip_tags($matches[4], '<a><em><strong><i><b>'));

            if (empty($caption_text)) {
                return $matches[0];
            }

            $html = '<div class="image-caption-wrapper ' . $alignment_class . '" style="width: ' . $width . 'margin-top: 15px; margin-bottom: 15px;">';
            $html .= $image_tag;
            $html .= '<p class="wp-caption-text">' . $caption_text . '</p>';
            $html .= '</div>';

            return $html;
        },
        $content
    );

    // Clean up empty paragraphs
    $content = str_replace(['<p></p>', '<p>&nbsp;</p>'], '', $content);

    // 2. TABLE HANDLING
    // Add content-table class to all tables
    $content = preg_replace_callback('/<table([^>]*)>/i', function ($matches) {
        $attributes = $matches[1];
        if (preg_match('/class=["\']([^"\']*)["\']/', $attributes, $classMatch)) {
            $newAttributes = preg_replace('/class=["\']([^"\']*)["\']/', 'class="$1 content-table"', $attributes);
            return '<table' . $newAttributes . '>';
        } else {
            return '<table' . $attributes . ' class="content-table">';
        }
    }, $content);

    // 2.5 CONVERT TABLE FIGCAPTIONS TO PROPER CAPTIONS
    // WordPress/Gutenberg outputs figcaption for table captions - convert for accessibility
    $content = preg_replace_callback(
        '/<figure[^>]*class="[^"]*wp-block-table[^"]*"[^>]*>(.*?)<\/figure>/is',
        function ($matches) {
            $figure_content = $matches[1];

            // Extract figcaption content if present
            if (preg_match('/<figcaption[^>]*>(.*?)<\/figcaption>/is', $figure_content, $caption_match)) {
                $caption_text = $caption_match[1];

                // Remove figcaption from content
                $figure_content = preg_replace('/<figcaption[^>]*>.*?<\/figcaption>/is', '', $figure_content);

                // Insert caption inside the table, right after opening tag
                $figure_content = preg_replace(
                    '/(<table[^>]*>)/i',
                    '$1<caption>' . $caption_text . '</caption>',
                    $figure_content
                );
            }

            return $figure_content; // Return without the figure wrapper
        },
        $content
    );

    // Force tradegothic on captions via inline style (debug test)
    $content = preg_replace(
        '/<caption>/i',
        '<caption style="font-family: oswald, sans-serif;">',
        $content
    );

    // 3. MATHML → Styled span (mPDF ignores MathML tags but renders text)
    $content = preg_replace_callback(
        '/<math[^>]*>(.*?)<\/math>/is',
        function ($matches) {
            // Strip all MathML tags, keep text content
            $text = strip_tags($matches[1]);
            // Clean up extra whitespace
            $text = preg_replace('/\s+/', ' ', trim($text));
            return '<span style="font-family: tradegothic, sans-serif;">' . $text . '</span>';
        },
        $content
    );

    return $content;
}

// Get CSS for mPDF with improved accessibility and spacing
function get_mpdf_styles()
{
    // Increased font sizes for cover page elements and added image alignment styles.
    $styles = '
        body { font-family: "georgia", serif; font-size: 16px; line-height: 1.6; color: #000; }
        
        h1, h2, h3, h4, h5, h6 { font-family: "georgia", serif; color: #000; }
        h1 { font-size: 24px; font-weight: bold; margin: 24px 0 12px 0; }
        h2 { font-size: 20px; font-weight: bold; margin: 22px 0 10px 0; }
        h3 { font-size: 18px; font-weight: bold; margin: 20px 0 8px 0; }
        h4 { font-size: 16px; font-weight: bold; margin: 18px 0 6px 0; }
        h5 { font-size: 15px; font-weight: bold; margin: 16px 0 4px 0; }
        h6 { font-size: 14px; font-weight: bold; margin: 14px 0 4px 0; }

        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        table.content-table, table.content-table th, table.content-table td { font-family: "tradegothic", sans-serif; line-height: 1.1; padding: 8px; }
        table th, table td { border: 1px solid #ddd; padding:8px; text-align: left; font-size: 12px; }
        table th { background-color: #f2f2f2; font-weight: bold; font-size: 16px; }
        table caption, table figcaption { font-family: "oswald", sans-serif; text-align: center; font-size: 16px; margin-bottom: 8px; font-weight: 300; }

        /* UPDATED MATH STYLES */
        /* Target the parent container, the class, and specific MathML children */
        math, .math,
        math *, .math * { 
            font-family: "tradegothic", sans-serif !important; 
        }
        
        /* Explicitly target standard MathML tags to override defaults */
        mi, mo, mn, mtext, ms {
            font-family: "tradegothic", sans-serif !important;
        }

        .footer-spacer { height: 50px; }
        .page-break { page-break-before: always; }

        /* Styles for Image and Caption Alignment */
        .image-caption-wrapper {
            margin-top: 15px;
            margin-bottom: 15px;
        }
        .image-caption-wrapper.center {
            text-align: center;
            margin-left: auto;
            margin-right: auto;
        }
        .image-caption-wrapper img {
            margin-bottom: 5px;
        }
        .wp-caption-text {
            font-size: 12px;
            color: #000;
            line-height: 1.4;
            text-align: left;
        }
        .image-caption-wrapper.center .wp-caption-text {
            text-align: center;
        }

        figure.wp-block-image {
            margin-top: 15px;
            margin-bottom: 15px;
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
    <div style="font-size: 10px; text-align: center; padding-bottom: 15px; font-family: georgia; line-height: 1.2;">' . $permalink_text . '</div>
    <hr style="border: 0; border-top: 1px solid #000; margin: 1px 0;">
    <table width="100%" style="font-size: 11px; font-family: georgia; margin: 1px 0; border: none; border-collapse: collapse;">
        <tr>
            <td style="text-align: left; width: 50%; font-weight: bold; border: none; line-height: 1.2; padding: 0;">' . $formatted_pub_number_string . '</td>
            <td style="text-align: right; width: 50%; border: none; line-height: 1.2; padding: 0;">' . $publish_history_text . '</td>
        </tr>
    </table>
    <hr style="border: 0; border-top: 1px solid #000; margin: 1px 0;">
    <div style="font-size: 10px; text-align: left; line-height: 1.3; font-family: georgia; margin: 2px 0;">' . $footer_paragraph . '</div>';
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
        $subtitle = get_post_meta($post_id, 'subtitle', true);
        $has_subtitle = !empty($subtitle);
        if ($has_subtitle) {
            $publication_title .= ': ' . $subtitle;
        }
        $publication_number = get_field('publication_number', $post_id);

        if (empty($publication_number)) {
            // error_log('mPDF Generation: No publication number found for post ID: ' . $post_id);
            $publication_number = 'publication-' . $post_id;
        }

        // Get authors data - IMPORTANT: Don't use false parameter, we need formatted data like render.php
        $authors_data = get_field('authors', $post_id);
        $author_names = [];
        $author_lines = [];

        if ($authors_data) {
            foreach ($authors_data as $index => $item) {
                // Check the type field to determine if this is a user or custom entry
                $entry_type = $item['type'] ?? '';

                $first_name = '';
                $last_name = '';
                $author_title = '';
                $full_name = '';

                if ($entry_type === 'Custom') {
                    // Handle custom user entry - check both possible field names
                    $custom_user = $item['custom_user'] ?? $item['custom'] ?? [];
                    $first_name = sanitize_text_field($custom_user['first_name'] ?? '');
                    $last_name = sanitize_text_field($custom_user['last_name'] ?? '');
                    $author_title = sanitize_text_field($custom_user['title'] ?? $custom_user['titile'] ?? '');
                } else {
                    // Handle WordPress user selection (existing logic)
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
                        $display_name = get_the_author_meta('display_name', $user_id);
                        $first_name = get_the_author_meta('first_name', $user_id);
                        $last_name = get_the_author_meta('last_name', $user_id);
                        $public_title = get_field('public_friendly_title', 'user_' . $user_id);
                        $regular_title = get_the_author_meta('title', $user_id);
                        $author_title = !empty($public_title) ? $public_title : $regular_title;

                        // Use display_name if available, otherwise construct from first/last
                        $full_name = !empty($display_name) ? $display_name : trim("$first_name $last_name");
                    }
                }

                // Only proceed if we have at least a name
                if (!empty($first_name) || !empty($last_name)) {
                    // If full_name wasn't set (for custom users), construct it
                    if (empty($full_name)) {
                        $full_name = trim("$first_name $last_name");
                    }

                    $author_names[] = $full_name;
                    $author_line = '<strong>' . esc_html($full_name) . '</strong>';
                    if (!empty($author_title)) {
                        $author_line .= ', ' . esc_html($author_title);
                    }
                    $author_lines[] = $author_line;
                }
            }
        }

        // Format author names with proper grammar (commas and 'and')
        $formatted_authors = '';
        if (!empty($author_names)) {
            $count = count($author_names);
            if ($count === 1) {
                $formatted_authors = $author_names[0];
            } elseif ($count === 2) {
                $formatted_authors = $author_names[0] . ' and ' . $author_names[1];
            } else {
                $last = array_pop($author_names);
                $formatted_authors = implode(', ', $author_names) . ', and ' . $last;
            }
        }


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
                ],
                'tradegothic' => [
                    'R' => 'TradeGothicLTStd.ttf',
                    'B' => 'TradeGothicLTStd-Bold.ttf',
                    'I' => 'TradeGothicLTStd-Obl.ttf',
                    'BI' => 'TradeGothicLTStd-BoldObl.ttf'
                ],
                'oswald' => [
                    'R' => 'Oswald-Light.ttf',
                    'B' => 'Oswald-SemiBold.ttf',
                    'I' => 'Oswald-Light.ttf',
                    'BI' => 'Oswald-SemiBold.ttf'
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
        $mpdf->SetHTMLHeader('');
        $mpdf->SetHTMLFooter('');

        // Get featured image
        $featured_image_url = '';
        $featured_image_dimensions = null;
        if (has_post_thumbnail($post_id)) {
            $featured_image_id = get_post_thumbnail_id($post_id);
            $featured_image_array = wp_get_attachment_image_src($featured_image_id, 'large');
            if ($featured_image_array) {
                $featured_image_url = $featured_image_array[0];
                // Store width and height from WordPress
                $featured_image_dimensions = [
                    'width' => $featured_image_array[1],
                    'height' => $featured_image_array[2]
                ];
            }
        }

        // Build featured image on cover page, if it exists
        if (!empty($featured_image_url)) {
            // 1. Define Page Width (Letter is 8.5 inches)
            // We use the full width because the CSS (left: -15mm, right: -15mm) pulls it to the edges.
            $page_width_mm = 215.9; // 8.5 * 25.4

            // 2. Calculate Height based on Aspect Ratio
            $container_height_mm = $page_width_mm * (2 / 3);

            $cover_html = '
    <div style="position: absolute; top: 0; left: -15mm; right: -15mm; height: ' . $container_height_mm . 'mm; overflow: hidden;">
        <img src="' . $featured_image_url . '" style="width: 100%; height: auto;">
    </div>
    <div style="margin-top: ' . $container_height_mm . 'mm;">';
        } else {
            $cover_html = '<div style="margin-top: 30px;">';
        }

        // Extension logo
        $extension_logo_path = get_template_directory() . '/assets/images/Extension_logo_Formal_FC.png';
        if (file_exists($extension_logo_path)) {
               $cover_html .= '<img src="' . $extension_logo_path . '" style="width: 30%; height: auto; margin-bottom: 10px; margin-top:' . $container_height_mm . 'mm">';
        }

        // Title with dynamic font size
        $title_font_size = calculate_title_font_size($publication_title, $has_subtitle);
        $cover_html .= '<h1 style="font-size: ' . $title_font_size . 'px; font-weight: bold; margin: 20px 0 20px 0; line-height: 1.2;">' . esc_html($publication_title) . '</h1>';

        // Authors
        if (!empty($author_lines)) {
            $cover_html .= '<div style="margin: 10px 0; line-height: 1.3; font-size: 16px;">' . implode('<br>', $author_lines) . '</div>';
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
            $cover_html .= '<div style="margin: 15px 0; font-size: 14px;">' . $date_text . '</div>';
        }

        $cover_html .= '</div>';

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

        $mpdf->WriteHTML($processed_content);

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
