<?php

// Get event range
$pubs_range = get_field('pubs_range');

// Get series field from block
$selected_series = get_field('series');

// Get number of posts from block; if empty set to default
$number_of_posts = get_field('number_of_posts') ?? 4;

// Get the current post ID if we're on a single event post
$current_post_id = is_singular('publications') ? get_the_ID() : null;

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();


// Initialize the base query arguments
$args = array(
    'post_type' => 'publications',
    'showposts' => $number_of_posts,
    'fields' => 'ids',
    // Exclude the current post from the query if applicable
    'post__not_in' => $current_post_id ? array( $current_post_id ) : array(),
    // Ordering by start date oldest to newest
    'orderby' => array(
        'date' => 'ASC'
    ),
    'order' => 'ASC',
);

// Conditionally add tax_query if selected_series is not empty
if ( !empty( $selected_series ) && is_array( $selected_series ) && ( $pubs_range == 'limited' ) ):
    $args['tax_query'] = array(
        array(
            'taxonomy' => 'series',
            'field' => 'term_id',
            'terms' => $selected_series,
            'operator' => 'IN',
        ),
    );
endif;

// Publications Query
$pubs = get_posts($args);

echo '<div ' . $attrs . '>';

// echo '<div>';

// Loop through Publications
foreach( $pubs as $pub ):

    include('loop.php');

endforeach;

echo '</div>';
?>
