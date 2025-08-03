<?php
// SIMPLE TEST - this should always show up if the block runs
// error_log('COMBINED LOCATION BLOCK: Block is running!');

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
$displayMode = $block['displayMode'] ?? 'auto';
$asSnippet = $block['asSnippet'] ?? false;
$fontSize = isset($block['headingFontSize']) && !empty($block['headingFontSize']) ? esc_attr($block['headingFontSize']) : '';
$fontUnit = isset($block['headingFontUnit']) ? esc_attr($block['headingFontUnit']) : 'px';

// Generate inline style if font size is set
$style = $fontSize ? ' style="font-size: ' . $fontSize . $fontUnit . ';"' : '';

// ===================
// GET PHYSICAL LOCATION DATA
// ===================
$physical_location = '';
$directions_link = '';
$location_details = '';

// Check for Google map data
$google_map = get_field('location_google_map', $post_id);

if (!empty($google_map) && is_array($google_map)) {
    // Retrieve the full address and build the default street address
    $full_address   = $google_map['address'] ?? '';
    $street_number  = $google_map['street_number'] ?? '';
    $street_name    = $google_map['street_name'] ?? '';
    $street_address = trim($street_number . ' ' . $street_name);

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
    
    // If city/state fields are empty, try to parse from full address
    if (empty($city) || empty($state)) {
        // Parse: "Hoke Smith Building, Smith Street, Athens, GA, USA"
        $address_parts = explode(',', $full_address);
        if (count($address_parts) >= 4) {
            $city = trim($address_parts[2]); // "Athens"
            $state_part = trim($address_parts[3]); // "GA USA"
            $state = explode(' ', $state_part)[0]; // "GA"
        }
    }
    
    $line2_parts = [];
    if ($city) $line2_parts[] = $city;
    if ($state) $line2_parts[] = $state;
    if ($postal) $line2_parts[] = $postal;
    
    $line2 = implode(', ', array_filter($line2_parts));
    $country = $google_map['country_short'] ?? '';

    // Create physical location output
    $physical_location_snippet = $line1_snippet;
    $physical_location_full = $line1_full;
    if ($line2) {
        $physical_location_full .= '<br>' . $line2;
    }
    if ($country && $country !== 'US') {
        $physical_location_full .= '<br>' . $country;
    }

    // Create Google Maps link
    if (!empty($google_map['address'])) {
        $directions_link = 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($google_map['address']);
    } elseif (!empty($google_map['lat']) && !empty($google_map['lng'])) {
        $directions_link = 'https://www.google.com/maps/dir/?api=1&destination=' . $google_map['lat'] . ',' . $google_map['lng'];
    }
}

// Get location details
if (!empty(get_field('location_details', $post_id))) {
    $location_details = get_field('location_details', $post_id);
}

// ===================
// GET ONLINE LOCATION DATA
// ===================
$online_location = '';
$virtual = get_field('online_location', $post_id);
$online_web_address = get_field('online_location_web_address', $post_id);
$online_web_label = get_field('online_location_web_address_label', $post_id);

$has_online = !empty($virtual) || !empty($online_web_address);
$has_physical = !empty($physical_location_snippet) || !empty($physical_location_full);

// ===================
// DETERMINE WHAT TO DISPLAY
// ===================
$show_physical = false;
$show_online = false;

switch ($displayMode) {
    case 'physical':
        $show_physical = $has_physical;
        break;
    case 'online':
        $show_online = $has_online;
        break;
    case 'both':
        $show_physical = $has_physical;
        $show_online = $has_online;
        break;
    case 'auto':
    default:
        $show_physical = $has_physical;
        $show_online = $has_online;
        break;
}

// ===================
// OUTPUT THE BLOCK
// ===================
if ($show_physical || $show_online) {
    echo '<div ' . $attrs . '>';

    if ($asSnippet) {
        // SNIPPET MODE
        if ($show_physical && $show_online) {
            echo '<h3 class="event-details-title"' . $style . '>' . wp_kses_post($physical_location_snippet) . ' & Online</h3>';
        } elseif ($show_physical) {
            echo '<h3 class="event-details-title"' . $style . '>' . wp_kses_post($physical_location_snippet) . '</h3>';
        } elseif ($show_online) {
            echo '<h3 class="event-details-title"' . $style . '>Virtual Event</h3>';
        }
    } else {
        // FULL DISPLAY MODE
        if ($show_physical && $show_online) {
            // BOTH LOCATIONS
            echo '<h3 class="event-details-title"' . $style . '>Location</h3>';
            echo '<div class="event-details-content">';
            echo '<p><em>This event is in-person and online.</em></p>';
            
            // Physical location
            echo '<strong>Physical Location:</strong><br>';
            echo wp_kses_post($physical_location_full);
            if (!empty($directions_link)) {
                echo '<br><a href="' . esc_url($directions_link) . '" target="_blank" rel="noopener noreferrer">Get Directions</a>';
            }
            if (!empty($location_details)) {
                echo '<br>' . wp_kses_post($location_details);
            }
            
            echo '<br><br>';
            
            // Online location
            echo '<strong>Online Location:</strong><br>';
            if (!empty($virtual)) {
                echo wp_kses_post($virtual) . '<br>';
            }
            if (!empty($online_web_address)) {
                $link_text = !empty($online_web_label) ? $online_web_label : $online_web_address;
                echo '<a href="' . esc_url($online_web_address) . '">' . esc_html($link_text) . '</a>';
            }
            
            echo '</div>'; // Close event-details-content
            
        } elseif ($show_physical) {
            // PHYSICAL LOCATION ONLY
            echo '<h3 class="event-details-title"' . $style . '>Location</h3>';
            echo '<div class="event-details-content">';
            echo wp_kses_post($physical_location_full);
            if (!empty($directions_link)) {
                echo '<br><a href="' . esc_url($directions_link) . '" target="_blank" rel="noopener noreferrer">Get Directions</a>';
            }
            if (!empty($location_details)) {
                echo '<br>' . wp_kses_post($location_details);
            }
            echo '</div>'; // Close event-details-content
            
        } elseif ($show_online) {
            // ONLINE LOCATION ONLY
            echo '<h3 class="event-details-title"' . $style . '>Online Location</h3>';
            echo '<div class="event-details-content">';
            if (!empty($virtual)) {
                echo wp_kses_post($virtual) . '<br>';
            }
            if (!empty($online_web_address)) {
                $link_text = !empty($online_web_label) ? $online_web_label : $online_web_address;
                echo '<a href="' . esc_url($online_web_address) . '">' . esc_html($link_text) . '</a>';
            }
            echo '</div>'; // Close event-details-content
        }
    }

    echo '</div>'; // Close wrapper
}
?>