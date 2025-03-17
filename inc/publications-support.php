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
add_action('pmxi_saved_post', function ($post_id, $xml, $is_update) {

    if (get_post_type($post_id) !== 'publications') {
        return;
    }

    $author_text = get_field('AUTHOR_TEXT', $post_id); 
    if (empty($author_text)) {
        return;
    }

    // Split the AUTHOR_TEXT into individual names
    $authors = explode(',', $author_text);

    // Initialize the ACF repeater data
    $repeater_data = [];

    foreach ($authors as $author) {
        // Trim whitespace and split into first and last name
        $author = trim($author);
        [$last_name, $first_name] = array_map('trim', explode(' ', $author, 2));

        // Try to find a matching user
        $user = get_users([
            'search'         => "*{$first_name} {$last_name}*",
            'search_columns' => ['display_name', 'first_name', 'last_name'],
            'number'         => 1,
        ]);

        if (!empty($user)) {
            // user matched so set it with ID
            $repeater_data[] = [
                'user'        => $user[0]->ID,
                'custom_user' => '', 
            ];
        } else {
            // No user found so create a custom user
            $repeater_data[] = [
                'user'        => '', 
                'custom_user' => [
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                ],
            ];
        }
    }

    // Save the repeater field to the post
    update_field('authors', $repeater_data, $post_id);
}, 10, 3);


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

// Custom Publications Parse Request
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

// Add subtitle to publications if it is used
function append_subtitle_to_title($title, $id) {
    if (is_admin()) {
        return $title;
    }
    if (get_post_type($id) === 'publications') { 
        $subtitle = get_post_meta($id, 'subtitle', true); // Using get_post_meta instead of get_field because it's a simple text field, and this is more performant
        if (!empty($subtitle) && is_singular('publications')) {
            $title .= ': <br/><span style="font-size:0.8em;display:inline-block;margin-top:var(--wp--preset--spacing--30)">' . esc_html($subtitle) . '</span>';
        } elseif (!empty($subtitle)) {
            $title .= ': ' . esc_html($subtitle);
        }
    }

    return $title;
}
add_filter('the_title', 'append_subtitle_to_title', 10, 2);