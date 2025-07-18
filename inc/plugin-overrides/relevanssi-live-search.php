<?php
if (function_exists('relevanssi_live_search_init')) {
    wp_dequeue_style('relevanssi-live-search');
    
    add_filter('relevanssi_live_search_query_args', function ($args) {
        $args['post_status'] = 'publish';
        return $args;
    });
}