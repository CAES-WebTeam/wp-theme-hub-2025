<?php

// Get today's date
$today = date('Ymd');

// Get event range
$event_range = get_field('event_range');

// Get series field from block
$selected_series = get_field('series');

// Get the current post ID if we're on a single event post
$current_post_id = is_singular('events') ? get_the_ID() : null;

// Initialize the base query arguments
$args = array(
    'post_type' => 'events',
    'showposts' => 1,
    'fields' => 'ids',
    // Exclude the current post from the query if applicable
    'post__not_in' => $current_post_id ? array( $current_post_id ) : array(),
    // Ordering by start date oldest to newest
    'orderby' => array(
        'meta_value' => 'ASC',
        'menu_order' => 'ASC',
        'title' => 'ASC'
    ),
    'order' => 'ASC',
    'meta_key' => 'start_date',
    'meta_query' => array(
        'relation' => 'AND',
        // Check start and end dates
        array(
            'relation' => 'OR',
            array(
                'key' => 'start_date',
                'value' => $today,
                'compare' => '>=',
                'type' => 'DATE'
            ),
            array(
                'key' => 'end_date',
                'value' => $today,
                'compare' => '>=',
                'type' => 'DATE'
            )
        ),
        // Check publish date
        array(
            'key' => 'publish_display_date',
            'value' => $today,
            'compare' => '<=',
            'type' => 'DATE'
        ),
        // Exclude events where hide_event is true
        array(
            'key' => 'hide_event',
            'value' => '1',
            'compare' => '!=',
            'type' => 'NUMERIC'
        )
    )
);

// Conditionally add tax_query if selected_series is not empty
if ( !empty( $selected_series ) && is_array( $selected_series ) && ( $event_range == 'limited' ) ):
    $args['tax_query'] = array(
        array(
            'taxonomy' => 'series',
            'field' => 'term_id',
            'terms' => $selected_series,
            'operator' => 'IN',
        ),
    );
endif;

// Events Query
$events = get_posts($args);

// If restricted, override $events
if ( $event_range == 'specific' && get_field('events') ):
    $events = get_field('events');

    // Initialize an empty array to store the valid events.
    $filtered_events = array();

    // Get today's date in the correct format (Ymd for ACF date fields).
    $today = date('Ymd');

    // Loop through each event post ID.
    foreach ( $events as $event ):

        // Skip the current event if it's the same as the current post
        if ( $event == $current_post_id ) {
            continue;
        }

        $hide_event = get_field('hide_event', $event);
        $start_date = get_field('start_date', $event);
        $end_date = get_field('end_date', $event);
        $publish_date = get_field('publish_display_date', $event);

        // Skip if post should be hidden or shouldn't be published yet
        if ( $hide_event || empty($publish_date) || $publish_date > $today ) {
            continue;
        }

        // Check for start and end dates.
        if ( !$end_date ) {
            // If the start date is today or in the future, include it
            if ( $start_date >= $today ) {
                $filtered_events[] = array(
                    'event' => $event,
                    'start_date' => $start_date
                );
            }
        } else {
            // If the end date exists, check if the event is still active
            if ( $end_date >= $today ) {
                $filtered_events[] = array(
                    'event' => $event,
                    'start_date' => $start_date
                );
            }
        }
    endforeach;

    // Sort the filtered events by start_date in ascending order.
    usort($filtered_events, function ($a, $b) {
        return $a['start_date'] <=> $b['start_date']; // Ascending order comparison
    });

    // Extract only the event IDs from the sorted array.
    $events = array_column($filtered_events, 'event');
endif;

echo '<div class="events-block">';

// Loop through Events
foreach( $events as $event ): 

    include('loop.php');

endforeach;

echo '</div>';
?>