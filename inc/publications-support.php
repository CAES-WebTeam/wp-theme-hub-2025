<?php

// Load ACF Field Groups
//include_once( get_template_directory() . '/inc/acf-fields/publications-field-group.php' );

// Set ACF field 'state_issue' with options from json
function populate_acf_state_issue_field( $field ) {
	// Set path to json file
	$json_file = get_template_directory() . '/json/publication-state-issue.json';

	if ( file_exists( $json_file ) ) {
		// Get the contents of the json file
		$json_data = file_get_contents( $json_file );
		$issues = json_decode( $json_data, true );

		// Clear existing choices
		$field['choices'] = array();

		// Check if there are issues in the json
		if ( isset( $issues['issues'] ) && is_array( $issues['issues'] ) ) {
			// Loop through the issues and add each name as a select option
			foreach ( $issues['issues'] as $issue ) {
				if ( isset( $issue['name'] ) ) {
					$field['choices'][ sanitize_text_field( $issue['name'] ) ] = sanitize_text_field( $issue['name'] );
				}
			}
		}
	}

	// Return the field to ACF
	return $field;
}
add_filter( 'acf/load_field/name=state_issue', 'populate_acf_state_issue_field' );

// Schedule the cron job for sunsetting publications
add_action( 'wp', function() {
    if ( ! wp_next_scheduled( 'unpublish_expired_publications' ) ) {
        wp_schedule_event( time(), 'daily', 'unpublish_expired_publications' );
    }
});

add_action( 'unpublish_expired_publications', 'unpublish_expired_publications_callback' );
function unpublish_expired_publications_callback() {
    // Get today's date in Ymd format
    $today = date( 'Ymd' );

    // Query publications with sunset_date on or before today
    $query = new WP_Query( [
        'post_type'   => 'publications',
        'meta_key'    => 'sunset_date',
        'meta_value'  => $today,
        'meta_compare' => '<=',
        'post_status' => 'publish',
        'posts_per_page' => -1, // Retrieve all matching posts
    ] );

    if ( $query->have_posts() ) {
        foreach ( $query->posts as $post ) {
            // Unpublish each post by setting its status to 'draft'
            wp_update_post( [
                'ID'          => $post->ID,
                'post_status' => 'draft',
            ] );
        }
    }

    wp_reset_postdata();
}

// Clear scheduled event on theme deactivation
register_deactivation_hook( __FILE__, function() {
    $timestamp = wp_next_scheduled( 'unpublish_expired_publications' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'unpublish_expired_publications' );
    }
});


/****** Publication Dynamic PDF ******/
// Generate PDF fucntionality
function generate_pdf() {
    try {
        // Load TCPDF library
        require_once get_template_directory() . '/inc/tcpdf/tcpdf.php';

        // Initialize TCPDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor(get_bloginfo('name'));
        $pdf->SetTitle('Publication PDF');
        $pdf->SetSubject('ADA Compliant PDF');
        $pdf->SetKeywords('ADA, Compliance, PDF, WordPress');

        // Set margins and font
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->AddPage();

        // Get post data
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        if (!$post_id) {
            wp_die('Invalid post ID');
        }

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'publications') {
            wp_die('Invalid publication');
        }

        // Get custom fields using ACF
        $fields = get_fields($post_id);

        // Add title
        $pdf->SetFont('helvetica', 'B', 16); // Bold font for title
        $pdf->Cell(0, 10, $post->post_title, 0, 1, 'C');

        // Add post content
        $pdf->Ln(10); // Line break
        $pdf->SetFont('helvetica', '', 12); // Regular font for content
        if (is_array($post->post_content)) {
		    $post_content = implode('', $post->post_content);
		} elseif (is_object($post->post_content)) {
		    $post_content = json_encode($post->post_content); // Safely handle unexpected object
		} else {
		    $post_content = $post->post_content; // Use as is if it's a string
		}
		$pdf->writeHTML($post_content, true, false, true, false, '');


        // Output PDF for download
        $file_name = sanitize_title($post->post_title) . '.pdf';
        $pdf->Output($file_name, 'D'); // 'D' forces download

        exit;
    } catch (Exception $e) {
        // Handle errors gracefully
        error_log('PDF Generation Error: ' . $e->getMessage());
        wp_die('An error occurred while generating the PDF. Please try again later.');
    }
}

// Register the action for generating the PDF
add_action('admin_post_generate_pdf', 'generate_pdf');
add_action('admin_post_nopriv_generate_pdf', 'generate_pdf');



/****** IMPORT ACTIONS ******/
// Save Post Actions
add_action('pmxi_saved_post', 'attach_authors_to_repeater', 10, 1);
function attach_authors_to_repeater($post_id) {
    //if (get_post_type($post_id) !== 'publication') return;

    $raw_data = get_field('raw_author_ids', $post_id);
    if (empty($raw_data)) return;

    $rows = explode('|', rtrim($raw_data, '|'));
    $repeater = [];

    foreach ($rows as $row) {
        $row = trim($row);
        if (empty($row)) continue;

        // Convert pseudo-JSON into proper JSON
        $json_str = '{' . $row . '}';
        $data = json_decode($json_str, true);
        if (!isset($data['college_id'])) continue;

        $cid = trim($data['college_id']);
        $is_lead = !empty($data['lead']) && $data['lead'] == '1';
        $is_co = !empty($data['co']) && $data['co'] == '1';

        $users = get_users([
            'meta_key' => 'college_id',
            'meta_value' => $cid,
            'number' => 1
        ]);

        if (!empty($users)) {
            $user_id = $users[0]->ID;

            if ($is_lead && !$lead_author_user_id) {
                $lead_author_user_id = $user_id;
            }

            $repeater[] = [
                'user' => $user_id,
                'lead_author' => $is_lead,
                'co_author' => $is_co
            ];
        }
    }

    if (!empty($repeater)) {
        update_field('authors', $repeater, $post_id);
    }

    // Set post author if we found a lead author
    if ($lead_author_user_id) {
        wp_update_post([
            'ID' => $post_id,
            'post_author' => $lead_author_user_id
        ]);
    }
}


// Clean up content
add_action('pmxi_saved_post', function ($post_id, $xml, $is_update) {
    // Get the post content
    $content = get_post_field('post_content', $post_id);

    // Remove empty <p> tags
    $content = preg_replace('/<p>\s*<\/p>/', '', $content);

    // Update the post content
    wp_update_post([
        'ID'           => $post_id,
        'post_content' => $content,
    ]);
}, 10, 3);


function clean_html($html) {
    $html = preg_replace('/\r\n|\n|\r/', '', $html); // Remove newlines
    return trim($html);
}


// Set Image URL for thumbnail
function get_full_image_url($relative_path) {
    $base_url = "https://secure.caes.uga.edu/extension/publications/images/thumbnail-pub-images/";
    $relative_path = ltrim($relative_path, '/');
    $full_url = $base_url . '/' . $relative_path;

    // Check if the image exists
    $headers = @get_headers($full_url);
    if ($headers && strpos($headers[0], '200')) {
        return $full_url; // Return valid URL
    } else {
        return ''; // Return empty to avoid import errors
    }
}

/*// Assign Keywords
function assign_keywords_to_publications_from_json($json_file_path) {
    if (!file_exists($json_file_path)) {
        print_r("JSON file not found: $json_file_path");
        return;
    }

    $json_data = file_get_contents($json_file_path);
    $json_data = trim($json_data);
    $json_data = preg_replace('/^\xEF\xBB\xBF/', '', $json_data); // Remove BOM
    $json_data = mb_convert_encoding($json_data, 'UTF-8', 'UTF-8'); // Normalize encoding

    $pairs = json_decode($json_data, true);

    if (!$pairs || !is_array($pairs)) {
        print_r("Invalid or empty JSON structure: " . json_last_error_msg());
        return;
    }

    foreach ($pairs as $pair) {
        $pub_id = $pair['PUBLICATION_ID'] ?? null;
        $kw_id  = $pair['KEYWORD_ID'] ?? null;

        if (!$pub_id || !$kw_id) continue;

        // Find the post with matching publication_id (ACF field)
        $posts = get_posts([
            'post_type' => 'publications',
            'meta_key' => 'publication_id',
            'meta_value' => $pub_id,
            'posts_per_page' => 1,
            'fields' => 'ids'
        ]);

        if (empty($posts)) {
            print_r("No publication found for publication_id: $pub_id");
            continue;
        }

        $post_id = $posts[0];

        // Find the keyword term with matching keyword_id (ACF field)
        $terms = get_terms([
            'taxonomy' => 'keywords',
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key' => 'keyword_id',
                    'value' => $kw_id,
                    'compare' => '='
                ]
            ]
        ]);

        if (empty($terms) || is_wp_error($terms)) {
            print_r("No keyword term found for keyword_id: $kw_id");
            continue;
        }

        $term_id = $terms[0]->term_id;

        // Assign the term to the publication
        wp_set_object_terms($post_id, intval($term_id), 'keywords', true);
    }

    print_r("Keyword assignment complete.");
}*/



/****** Custom Publications Permalink ******/
function custom_publications_rewrite_rules() {
    add_rewrite_rule(
        '^publications/([^/]+)/([^/]+)/?$',
        'index.php?post_type=publications&name=$matches[2]',
        'top'
    );
}
add_action('init', 'custom_publications_rewrite_rules');


function custom_publications_query_vars($query_vars) {
    $query_vars[] = 'publication_number';
    return $query_vars;
}
add_filter('query_vars', 'custom_publications_query_vars');


function custom_publications_permalink($post_link, $post) {
    if ($post->post_type === 'publications') {
        $publication_number = get_field('publication_number', $post->ID);

        if ($publication_number) {
            $publication_number = sanitize_title($publication_number);

            return home_url("/publications/{$publication_number}/{$post->post_name}/");
        }
    }
    return $post_link;
}
add_filter('post_type_link', 'custom_publications_permalink', 10, 2);


function custom_publications_parse_request($query) {
    if (!is_admin() && isset($query->query_vars['publication_number'])) {
        $publication_number = sanitize_title($query->query_vars['publication_number']);

        $query->set('meta_query', array(
            array(
                'key'     => 'publication_number',
                'value'   => $publication_number,
                'compare' => '='
            )
        ));
    }
}
add_action('pre_get_posts', 'custom_publications_parse_request');