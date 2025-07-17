<?php
wp_dequeue_style('relevanssi-live-search');


// Addresses a bug in Relevanssi Live Search where it doesn't respect the post status
add_filter('relevanssi_live_search_query_args', function ($args) {
    $args['post_status'] = 'publish'; // or just 'publish', if you don't need attachments in the results
    return $args;
});
