<?php
// SIMPLE TEST - this should always show up if the block runs
error_log('LOCATION BLOCK: Block is running!');

if ( ! function_exists( 'normalize_address' ) ) {
    function normalize_address($address) {
        $replacements = [
            // Directional abbreviations
            '/\bN\b/i' => 'North',
            '/\bS\b/i' => 'South',
            '/\bE\b/i' => 'East',
            '/\bW\b/i' => 'West',
            // Street type abbreviations
            '/\bSt\b/i'   => 'Street',
            '/\bCir\b/i'  => 'Circle',
            '/\bRd\b/i'   => 'Road',
            '/\bAve\b/i'  => 'Avenue',
            '/\bBlvd\b/i' => 'Boulevard',
            '/\bDr\b/i'   => 'Drive',
            '/\bLn\b/i'   => 'Lane',
            '/\bCtr\b/i'  => 'Center',
        ];
        return preg_replace(array_keys($replacements), array_values($replacements), $address);
    }
}

// Get the current post ID
$post_id = get_the_ID();

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();
$locationAsSnippet = $block['locationAsSnippet'];
$fontSize = isset($block['headingFontSize']) && !empty($block['headingFontSize']) ? esc_attr($block['headingFontSize']) : '';
$fontUnit = isset($block['headingFontUnit']) ? esc_attr($block['headingFontUnit']) : 'px';

// Generate inline style if font size is set
$style = $fontSize ? ' style="font-size: ' . $fontSize . $fontUnit . ';"' : '';

// DEBUG: Check what location fields exist
$location_custom = get_field('location_custom', $post_id);
$event_type = get_field('event_type', $post_id);
$event_location_type = get_field('event_location_type', $post_id);

// DEBUG: List ALL ACF fields to see what location fields exist
$all_fields = get_fields($post_id);
error_log('DEBUG: All fields: ' . print_r($all_fields, true));

// Initialize location variable
$location = '';
$directions_link = '';

// Check for Google map data (regardless of location_custom setting)
$google_map = get_field('location_google_map', $post_id);
error_log('DEBUG: google_map: ' . print_r($google_map, true));

if (!empty($google_map) && is_array($google_map)) {
    error_log('DEBUG: Using Google map location');
    
    // Retrieve the full address and build the default street address
    $full_address   = $google_map['address'] ?? '';
    $street_number  = $google_map['street_number'] ?? '';
    $street_name    = $google_map['street_name'] ?? '';
    $street_address = trim($street_number . ' ' . $street_name);

    // DEBUG: Show individual components
    error_log('DEBUG: full_address: ' . $full_address);
    error_log('DEBUG: street_number: ' . $street_number);
    error_log('DEBUG: street_name: ' . $street_name);
    error_log('DEBUG: city: ' . ($google_map['city'] ?? 'EMPTY'));
    error_log('DEBUG: state_short: ' . ($google_map['state_short'] ?? 'EMPTY'));
    error_log('DEBUG: post_code: ' . ($google_map['post_code'] ?? 'EMPTY'));

    // Attempt to extract a potential building name from the full address
    $address_parts          = explode(',', $full_address);
    $possible_building_name = trim($address_parts[0]);

    // Normalize both values to check for duplicates
    $norm_building = normalize_address($possible_building_name);
    $norm_street   = normalize_address($street_address);

    // Determine snippet and full line display
    if ($possible_building_name && strcasecmp($norm_building, $norm_street) !== 0) {
        // Building name exists and is not just a duplicate of the street address
        $line1_snippet = $possible_building_name;
        $line1_full    = $possible_building_name . '<br>' . $street_address;
    } else {
        // No unique building name provided; use street address only
        $line1_snippet = $street_address;
        $line1_full    = $street_address;
    }

    // Build the second line (city, state, post code)
    $city = $google_map['city'] ?? '';
    $state = $google_map['state_short'] ?? '';
    $postal = $google_map['post_code'] ?? '';
    
    $line2_parts = [];
    if ($city) $line2_parts[] = $city;
    if ($state) $line2_parts[] = $state;
    if ($postal) $line2_parts[] = $postal;
    
    $line2 = implode(', ', array_filter($line2_parts));
    
    error_log('DEBUG: line2: ' . $line2);
    
    $country = $google_map['country_short'] ?? '';

    // Create location output based on snippet vs. full display
    if ($locationAsSnippet) {
        $location = $line1_snippet;
    } else {
        $location = $line1_full;
        if ($line2) {
            $location .= '<br>' . $line2;
        }
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
} else {
    error_log('DEBUG: No Google map data found');
}

// Check and set location details
$details = '';
if (!empty(get_field('location_details', $post_id))) {
    $details = get_field('location_details', $post_id);
}

error_log('DEBUG: Final location: ' . print_r($location, true));
error_log('DEBUG: locationAsSnippet: ' . print_r($locationAsSnippet, true));
?>

<?php
if (!empty($location)) {
    error_log('DEBUG: Displaying location block');
    echo '<div ' . $attrs . '>';

    if ($locationAsSnippet) {
        // For snippet mode, show the single line
        echo '<h3 class="event-details-title"' . $style . '>' . wp_kses_post($location) . '</h3>';
    } else {
        // For full display, output the title and content area
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
} else {
    error_log('DEBUG: Location is empty, not displaying block');
}
?>