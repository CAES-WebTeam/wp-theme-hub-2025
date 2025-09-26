<?php
/**
 * Custom REST API endpoint for events data
 * Add this to your source site's functions.php or create as a plugin
 * 
 * Usage examples:
 * /wp-json/caes/v1/events
 * /wp-json/caes/v1/events?caes_department=agriculture
 * /wp-json/caes/v1/events?caes_department=15 (using term ID)
 * /wp-json/caes/v1/events?per_page=20&start_date=2024-01-01
 * /wp-json/caes/v1/events?caes_department=extension&page=2
 */

// Register custom REST endpoint
add_action('rest_api_init', 'register_events_api_endpoint');

function register_events_api_endpoint() {
    register_rest_route('caes/v1', '/events', array(
        'methods' => 'GET',
        'callback' => 'get_events_api_data',
        'permission_callback' => '__return_true', // Make public - adjust security as needed
        'args' => array(
            'caes_department' => array(
                'type' => 'string',
                'description' => 'Filter by CAES department slug or ID',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'per_page' => array(
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 100,
            ),
            'page' => array(
                'type' => 'integer', 
                'default' => 1,
                'minimum' => 1,
            ),
            'start_date' => array(
                'type' => 'string',
                'description' => 'Filter events from this date (YYYY-MM-DD)',
                'validate_callback' => 'validate_date_format',
            ),
            'end_date' => array(
                'type' => 'string', 
                'description' => 'Filter events until this date (YYYY-MM-DD)',
                'validate_callback' => 'validate_date_format',
            )
        ),
    ));
}

function validate_date_format($param, $request, $key) {
    return empty($param) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
}

function get_events_api_data($request) {
    $parameters = $request->get_params();
    
    // Build query args
    $args = array(
        'post_type' => 'events',
        'post_status' => 'publish',
        'posts_per_page' => $parameters['per_page'],
        'paged' => $parameters['page'],
        'meta_query' => array(),
        'tax_query' => array(),
    );
    
    // Filter by department if provided
    if (!empty($parameters['caes_department'])) {
        $args['tax_query'][] = array(
            'taxonomy' => 'event_caes_departments',
            'field' => is_numeric($parameters['caes_department']) ? 'term_id' : 'slug',
            'terms' => $parameters['caes_department'],
        );
    }
    
    // Date filtering
    if (!empty($parameters['start_date']) || !empty($parameters['end_date'])) {
        $date_query = array('relation' => 'OR');
        
        // Check both start_date and end_date fields for events in range
        $date_conditions = array('relation' => 'OR');
        
        if (!empty($parameters['start_date'])) {
            $start_date = str_replace('-', '', $parameters['start_date']);
            $date_conditions[] = array(
                'key' => 'start_date',
                'value' => $start_date,
                'compare' => '>=',
                'type' => 'DATE'
            );
            $date_conditions[] = array(
                'key' => 'end_date', 
                'value' => $start_date,
                'compare' => '>=',
                'type' => 'DATE'
            );
        }
        
        if (!empty($parameters['end_date'])) {
            $end_date = str_replace('-', '', $parameters['end_date']);
            $date_conditions[] = array(
                'key' => 'start_date',
                'value' => $end_date,
                'compare' => '<=', 
                'type' => 'DATE'
            );
        }
        
        $args['meta_query'][] = $date_conditions;
    }
    
    // Order by start date
    $args['meta_key'] = 'start_date';
    $args['orderby'] = 'meta_value';
    $args['order'] = 'ASC';
    
    $query = new WP_Query($args);
    $events = array();
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            
            // Get featured image
            $featured_image = null;
            $acf_featured_image = get_field('featured_image', $post_id);
            
            if ($acf_featured_image) {
                $featured_image = array(
                    'url' => $acf_featured_image['url'],
                    'alt' => $acf_featured_image['alt'],
                    'sizes' => $acf_featured_image['sizes']
                );
            } elseif (has_post_thumbnail($post_id)) {
                $attachment_id = get_post_thumbnail_id($post_id);
                $featured_image = array(
                    'url' => wp_get_attachment_image_url($attachment_id, 'full'),
                    'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
                    'sizes' => wp_get_attachment_metadata($attachment_id)['sizes'] ?? null
                );
            }
            
            // Format dates
            $start_date = get_field('start_date', $post_id);
            $end_date = get_field('end_date', $post_id);
            $start_time = get_field('start_time', $post_id);
            $end_time = get_field('end_time', $post_id);
            
            // Get departments
            $departments = wp_get_post_terms($post_id, 'event_caes_departments', array('fields' => 'names'));
            
            // Build event data
            $event_data = array(
                'id' => $post_id,
                'title' => get_the_title(),
                'slug' => get_post_field('post_name', $post_id),
                'permalink' => get_permalink($post_id),
                'description' => get_field('description', $post_id),
                'excerpt' => wp_trim_words(strip_tags(get_field('description', $post_id)), 30),
                'featured_image' => $featured_image,
                'dates' => array(
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'formatted' => format_event_date_display($start_date, $end_date, $start_time, $end_time)
                ),
                'location' => array(
                    'type' => get_field('event_location_type', $post_id),
                    'address' => get_field('location_google_map', $post_id),
                    'online_url' => get_field('online_location_web_address', $post_id),
                    'online_label' => get_field('online_location_web_address_label', $post_id)
                ),
                'registration' => array(
                    'cost' => get_field('cost', $post_id),
                    'link' => get_field('registration_link', $post_id),
                    'info' => get_field('registration_info', $post_id)
                ),
                'departments' => $departments,
                'external_url' => get_field('event_page_external_address', $post_id),
                'last_modified' => get_the_modified_time('c', $post_id)
            );
            
            $events[] = $event_data;
        }
    }
    
    wp_reset_postdata();
    
    // Return response with pagination info
    return new WP_REST_Response(array(
        'events' => $events,
        'pagination' => array(
            'current_page' => $parameters['page'],
            'per_page' => $parameters['per_page'],
            'total_events' => $query->found_posts,
            'total_pages' => $query->max_num_pages
        )
    ), 200);
}

function format_event_date_display($start_date, $end_date, $start_time, $end_time) {
    if (empty($start_date)) return '';
    
    $start_formatted = DateTime::createFromFormat('Ymd', $start_date)->format('F j, Y');
    
    if (!empty($end_date) && $end_date !== $start_date) {
        $end_formatted = DateTime::createFromFormat('Ymd', $end_date)->format('F j, Y');
        $date_string = $start_formatted . ' - ' . $end_formatted;
    } else {
        $date_string = $start_formatted;
    }
    
    if (!empty($start_time)) {
        $date_string .= ' at ' . $start_time;
        if (!empty($end_time)) {
            $date_string .= ' - ' . $end_time;
        }
    }
    
    return $date_string;
}

// Optional: Add CORS headers if consuming site is on different domain
add_action('rest_api_init', 'add_cors_http_header');
function add_cors_http_header() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function($value) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET');
        header('Access-Control-Allow-Credentials: true');
        return $value;
    });
}