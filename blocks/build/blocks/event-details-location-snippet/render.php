<?php
// Get the current post ID
$post_id = get_the_ID();
// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();
$locationAsSnippet = $block['locationAsSnippet'];
$location_custom = get_field('location_custom', $post_id);
if ($location_custom) {
    // Use the Google Maps selected location
    $google_map = get_field('location_google_map', $post_id);
    
    if (!empty($google_map) && is_array($google_map)) {
        $location = $google_map['address'];
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
?>


<?php
if (!empty($location)) {
    echo '<div ' . $attrs . '>';

    if ($locationAsSnippet) {
        // If locationAsSnippet is true, display the location name as the h3 and skip the content area
        echo '<h3 class="event-details-title">' . esc_html($location) . '</h3>';
    } else {
        // Default behavior: display the location title and content area
        echo '<h3 class="event-details-title">Location</h3>';
        echo '<div class="event-details-content">';
        echo esc_html($location);
        echo '</div>'; // Close event-details-content
    }

    echo '</div>'; // Close wrapper
}
?>