<?php
// Get the current post ID
$post_id = get_the_ID();
// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

$locationAsSnippet = $block['locationAsSnippet'];

// Check and set location if event type is CAES
if (!empty(get_field('location_caes_room', $post_id)) and get_field('event_type', $post_id) == 'CAES'):
    $location = get_field('location_caes_room', $post_id);
endif;

// Check and set location if event type is Extension
if (!empty(get_field('location_county_office', $post_id)) and get_field('event_type', $post_id) == 'Extension'):
    $location = get_field('location_county_office', $post_id);
endif;
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