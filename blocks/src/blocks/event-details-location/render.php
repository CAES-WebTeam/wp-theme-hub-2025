<?php
// Get the current post ID
$post_id = get_the_ID();

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();
$locationAsSnippet = $block['locationAsSnippet'];
$fontSize = isset($block['headingFontSize']) && !empty($block['headingFontSize']) ? esc_attr($block['headingFontSize']) : '';
$fontUnit = isset($block['headingFontUnit']) ? esc_attr($block['headingFontUnit']) : 'px';

// Generate inline style if font size is set
$style = $fontSize ? ' style="font-size: ' . $fontSize . $fontUnit . ';"' : '';

// Is this a custom location?
$location_custom = get_field('location_custom', $post_id);

if ($location_custom) {
    $google_map = get_field('location_google_map', $post_id);

    if (!empty($google_map) && is_array($google_map)) {
        // Build address with line breaks
        $line1 = trim($google_map['street_number'] . ' ' . $google_map['street_name']);
        $line2 = trim($google_map['city'] . ', ' . $google_map['state_short'] . ' ' . $google_map['post_code']);
        $country = $google_map['country_short'] ?? '';
    
        if ($locationAsSnippet) {
            $location = $line1; // Just the street name/number for the snippet
        } else {
            $location = $line1 . '<br>' . $line2;
            if ($country && $country !== 'US') {
                $location .= '<br>' . $country;
            }
        }

        // Create Google Maps link
        if (!empty($google_map['address'])) {
            $directions_link = 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($google_map['address']);
        } elseif (!empty($google_map['lat']) && !empty($google_map['lng'])) {
            $directions_link = 'https://www.google.com/maps/dir/?api=1&destination=' . $google_map['lat'] . ',' . $google_map['lng'];
        }

    }
} else {
    // If CAES
    if (!empty(get_field('location_caes_room', $post_id)) && get_field('event_type', $post_id) == 'CAES') {
        $location = get_field('location_caes_room', $post_id);
    }

    // Or Extension
    if (!empty(get_field('location_county_office', $post_id)) && get_field('event_type', $post_id) == 'Extension') {
        $location = get_field('location_county_office', $post_id);
    }
}

// Check and set location details
if( !empty(get_field('location_details', $post_id)) ):
	$details = get_field('location_details', $post_id);
endif;
?>

<?php
if (!empty($location)) {
    echo '<div ' . $attrs . '>';

    if ($locationAsSnippet) {
        // If locationAsSnippet is true, display the location name as the h3 and skip the content area
        echo '<h3 class="event-details-title"' . $style . '>' . wp_kses_post($location) . '</h3>';
    } else {
        // Default behavior: display the location title and content area
        echo '<h3 class="event-details-title"' . $style . '>Location</h3>';
        echo '<div class="event-details-content">';
        echo wp_kses_post($location);
        if (!empty($directions_link)) {
            echo '<br /><a href="' . esc_url($directions_link) . '" target="_blank" rel="noopener noreferrer">Get Directions</a>';
        }
        if (!empty($details)) {
            echo '<br /> ' . $details;
        }
        echo '</div>'; // Close event-details-content
    }

    echo '</div>'; // Close wrapper
}
?>