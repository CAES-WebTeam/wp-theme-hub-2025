<?php
/**
 * events-frontend-queries.php
 *
 * Helper functions for querying events on the front-end with approval system support.
 * This file provides easy-to-use functions for getting events that are approved
 * for specific calendars.
 *
 * @package YourThemeName/Events
 */

/**
 * Get events for a specific calendar that are approved for that calendar
 *
 * @param int $calendar_term_id The term ID of the calendar
 * @param array $args Additional WP_Query arguments
 * @return WP_Query
 */
function get_approved_events_for_calendar($calendar_term_id, $args = array()) {
    $default_args = array(
        'post_type' => 'events',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => '_calendar_approval_status',
                'value' => serialize(strval($calendar_term_id)) . ';s:8:"approved"',
                'compare' => 'LIKE'
            )
        ),
        'tax_query' => array(
            array(
                'taxonomy' => 'event_caes_departments',
                'field' => 'term_id',
                'terms' => $calendar_term_id
            )
        )
    );
    
    // Merge with custom arguments
    $query_args = wp_parse_args($args, $default_args);
    
    return new WP_Query($query_args);
}

/**
 * Get events for multiple calendars (approved for any of those calendars)
 *
 * @param array $calendar_term_ids Array of calendar term IDs
 * @param array $args Additional WP_Query arguments
 * @return WP_Query
 */
function get_approved_events_for_calendars($calendar_term_ids, $args = array()) {
    if (empty($calendar_term_ids) || !is_array($calendar_term_ids)) {
        return new WP_Query(['post_type' => 'events', 'posts_per_page' => 0]);
    }
    
    // Build meta query for approval status - event must be approved for at least one calendar
    $meta_query = array('relation' => 'OR');
    foreach ($calendar_term_ids as $term_id) {
        $meta_query[] = array(
            'key' => '_calendar_approval_status',
            'value' => serialize(strval($term_id)) . ';s:8:"approved"',
            'compare' => 'LIKE'
        );
    }
    
    $default_args = array(
        'post_type' => 'events',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => $meta_query,
        'tax_query' => array(
            array(
                'taxonomy' => 'event_caes_departments',
                'field' => 'term_id',
                'terms' => $calendar_term_ids,
                'operator' => 'IN'
            )
        )
    );
    
    // Merge with custom arguments
    $query_args = wp_parse_args($args, $default_args);
    
    return new WP_Query($query_args);
}

/**
 * Get all published events (unfiltered by calendar approval)
 * Useful for "all events" feeds
 *
 * @param array $args Additional WP_Query arguments
 * @return WP_Query
 */
function get_all_published_events($args = array()) {
    $default_args = array(
        'post_type' => 'events',
        'post_status' => 'publish',
        'posts_per_page' => -1
    );
    
    // Merge with custom arguments
    $query_args = wp_parse_args($args, $default_args);
    
    return new WP_Query($query_args);
}

/**
 * Check if an event is approved for a specific calendar
 *
 * @param int $post_id The event post ID
 * @param int $calendar_term_id The calendar term ID
 * @return bool
 */
function is_event_approved_for_calendar($post_id, $calendar_term_id) {
    $approval_status = get_post_meta($post_id, '_calendar_approval_status', true);
    
    if (!is_array($approval_status)) {
        return false;
    }
    
    return isset($approval_status[$calendar_term_id]) && $approval_status[$calendar_term_id] === 'approved';
}

/**
 * Get which calendars an event is approved for
 *
 * @param int $post_id The event post ID
 * @return array Array of approved calendar term IDs
 */
function get_approved_calendars_for_event($post_id) {
    $approval_status = get_post_meta($post_id, '_calendar_approval_status', true);
    $approved_calendars = array();
    
    if (is_array($approval_status)) {
        foreach ($approval_status as $term_id => $status) {
            if ($status === 'approved') {
                $approved_calendars[] = intval($term_id);
            }
        }
    }
    
    return $approved_calendars;
}

/**
 * Get events by calendar slug (more user-friendly than term ID)
 *
 * @param string $calendar_slug The calendar term slug
 * @param array $args Additional WP_Query arguments
 * @return WP_Query|false
 */
function get_approved_events_by_calendar_slug($calendar_slug, $args = array()) {
    $calendar = get_term_by('slug', $calendar_slug, 'event_caes_departments');
    
    if (!$calendar || is_wp_error($calendar)) {
        return false;
    }
    
    return get_approved_events_for_calendar($calendar->term_id, $args);
}

/**
 * Shortcode for displaying events from a specific calendar
 * Usage: [events_calendar slug="marketing" limit="5"]
 *
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function events_calendar_shortcode($atts) {
    $atts = shortcode_atts(array(
        'slug' => '',
        'limit' => 10,
        'order' => 'ASC',
        'orderby' => 'meta_value',
        'meta_key' => 'event_date', // Assuming you have an event date field
    ), $atts, 'events_calendar');
    
    if (empty($atts['slug'])) {
        return '<p>Error: Calendar slug is required</p>';
    }
    
    $query_args = array(
        'posts_per_page' => intval($atts['limit']),
        'order' => sanitize_text_field($atts['order']),
        'orderby' => sanitize_text_field($atts['orderby'])
    );
    
    if (!empty($atts['meta_key'])) {
        $query_args['meta_key'] = sanitize_text_field($atts['meta_key']);
    }
    
    $events = get_approved_events_by_calendar_slug($atts['slug'], $query_args);
    
    if (!$events || !$events->have_posts()) {
        return '<p>No approved events found for this calendar.</p>';
    }
    
    ob_start();
    echo '<div class="events-calendar-list">';
    
    while ($events->have_posts()) {
        $events->the_post();
        ?>
        <div class="event-item">
            <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
            <div class="event-excerpt"><?php the_excerpt(); ?></div>
            <?php if (function_exists('get_field') && get_field('event_date')): ?>
                <div class="event-date">Date: <?php echo get_field('event_date'); ?></div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    echo '</div>';
    wp_reset_postdata();
    
    return ob_get_clean();
}
add_shortcode('events_calendar', 'events_calendar_shortcode');

/**
 * =============================================================================
 * QUERY LOOP BLOCK INTEGRATION
 * =============================================================================
 * 
 * These functions make the approval system work with WordPress Query Loop blocks
 * Users can add custom query vars to filter by approved calendars
 */

/**
 * Register custom query vars for use in Query Loop blocks
 */
function register_events_query_vars($vars) {
    $vars[] = 'approved_calendar';
    $vars[] = 'approved_calendars';
    $vars[] = 'require_approval';
    return $vars;
}
add_filter('query_vars', 'register_events_query_vars');

/**
 * Modify queries to handle our custom calendar approval logic
 * This makes Query Loop blocks work with approval filtering
 */
function modify_events_query_for_approvals($query) {
    // Only modify public queries for events post type
    if (is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'events') {
        return;
    }
    
    // Check if we're on a taxonomy archive page (calendar-specific context)
    if (is_tax('event_caes_departments')) {
        $current_term = get_queried_object();
        if ($current_term) {
            // Automatically filter to show only events approved for this calendar
            $meta_query = $query->get('meta_query') ?: [];
            $meta_query[] = [
                'key' => '_calendar_approval_status',
                'value' => serialize(strval($current_term->term_id)) . ';s:8:"approved"',
                'compare' => 'LIKE'
            ];
            $query->set('meta_query', $meta_query);
            return; // Exit early - we've handled the taxonomy context
        }
    }
    
    // Check if we need to filter by approved calendar (URL parameters)
    $approved_calendar = get_query_var('approved_calendar');
    $approved_calendars = get_query_var('approved_calendars');
    $require_approval = get_query_var('require_approval');
    
    // If any approval filtering is requested
    if (!empty($approved_calendar) || !empty($approved_calendars) || !empty($require_approval)) {
        $meta_query = $query->get('meta_query') ?: [];
        $tax_query = $query->get('tax_query') ?: [];
        
        // Single calendar approval filter
        if (!empty($approved_calendar)) {
            // Get term ID from slug if needed
            if (!is_numeric($approved_calendar)) {
                $term = get_term_by('slug', $approved_calendar, 'event_caes_departments');
                $approved_calendar = $term ? $term->term_id : 0;
            }
            
            if ($approved_calendar) {
                $meta_query[] = [
                    'key' => '_calendar_approval_status',
                    'value' => serialize(strval($approved_calendar)) . ';s:8:"approved"',
                    'compare' => 'LIKE'
                ];
                
                $tax_query[] = [
                    'taxonomy' => 'event_caes_departments',
                    'field' => 'term_id',
                    'terms' => $approved_calendar
                ];
            }
        }
        
        // Multiple calendars approval filter
        if (!empty($approved_calendars)) {
            $calendar_ids = [];
            $calendar_list = is_array($approved_calendars) ? $approved_calendars : explode(',', $approved_calendars);
            
            foreach ($calendar_list as $calendar) {
                $calendar = trim($calendar);
                if (is_numeric($calendar)) {
                    $calendar_ids[] = $calendar;
                } else {
                    // Convert slug to term ID
                    $term = get_term_by('slug', $calendar, 'event_caes_departments');
                    if ($term) {
                        $calendar_ids[] = $term->term_id;
                    }
                }
            }
            
            if (!empty($calendar_ids)) {
                // Build meta query for approval status
                $approval_meta_query = ['relation' => 'OR'];
                foreach ($calendar_ids as $term_id) {
                    $approval_meta_query[] = [
                        'key' => '_calendar_approval_status',
                        'value' => serialize(strval($term_id)) . ';s:8:"approved"',
                        'compare' => 'LIKE'
                    ];
                }
                $meta_query[] = $approval_meta_query;
                
                $tax_query[] = [
                    'taxonomy' => 'event_caes_departments',
                    'field' => 'term_id',
                    'terms' => $calendar_ids,
                    'operator' => 'IN'
                ];
            }
        }
        
        // Generic "require approval" filter (must be approved for at least one calendar)
        if (!empty($require_approval) && $require_approval !== 'false') {
            $meta_query[] = [
                'key' => '_calendar_approval_status',
                'value' => 's:8:"approved"',
                'compare' => 'LIKE'
            ];
        }
        
        if (!empty($meta_query)) {
            $query->set('meta_query', $meta_query);
        }
        
        if (!empty($tax_query)) {
            $query->set('tax_query', $tax_query);
        }
    }
}
add_action('pre_get_posts', 'modify_events_query_for_approvals');

/**
 * Also hook into WP_Query for non-main queries (like Query Loop blocks)
 */
function modify_wp_query_for_approvals($query) {
    // Skip admin and non-events queries
    if (is_admin() || $query->get('post_type') !== 'events') {
        return;
    }
    
    // Check if query has taxonomy filtering - auto-apply approval filtering
    $tax_query = $query->get('tax_query');
    if (!empty($tax_query)) {
        foreach ($tax_query as $tax_clause) {
            if (isset($tax_clause['taxonomy']) && $tax_clause['taxonomy'] === 'event_caes_departments') {
                // This query is filtering by calendar taxonomy - apply approval logic
                $meta_query = $query->get('meta_query') ?: [];
                
                if (isset($tax_clause['terms'])) {
                    $terms = is_array($tax_clause['terms']) ? $tax_clause['terms'] : [$tax_clause['terms']];
                    
                    if (count($terms) === 1) {
                        // Single calendar - must be approved for this calendar
                        $term_id = is_numeric($terms[0]) ? $terms[0] : get_term_by('slug', $terms[0], 'event_caes_departments')->term_id;
                        $meta_query[] = [
                            'key' => '_calendar_approval_status',
                            'value' => serialize(strval($term_id)) . ';s:8:"approved"',
                            'compare' => 'LIKE'
                        ];
                    } else {
                        // Multiple calendars - must be approved for at least one
                        $approval_meta_query = ['relation' => 'OR'];
                        foreach ($terms as $term) {
                            $term_id = is_numeric($term) ? $term : get_term_by('slug', $term, 'event_caes_departments')->term_id;
                            $approval_meta_query[] = [
                                'key' => '_calendar_approval_status',
                                'value' => serialize(strval($term_id)) . ';s:8:"approved"',
                                'compare' => 'LIKE'
                            ];
                        }
                        $meta_query[] = $approval_meta_query;
                    }
                    
                    $query->set('meta_query', $meta_query);
                    return; // Exit early - we've handled this
                }
            }
        }
    }
    
    // Check for our custom query vars in the query object
    $approved_calendar = $query->get('approved_calendar');
    $approved_calendars = $query->get('approved_calendars');
    $require_approval = $query->get('require_approval');
    
    if (!empty($approved_calendar) || !empty($approved_calendars) || !empty($require_approval)) {
        $meta_query = $query->get('meta_query') ?: [];
        $tax_query = $query->get('tax_query') ?: [];
        
        // Single calendar approval filter
        if (!empty($approved_calendar)) {
            if (!is_numeric($approved_calendar)) {
                $term = get_term_by('slug', $approved_calendar, 'event_caes_departments');
                $approved_calendar = $term ? $term->term_id : 0;
            }
            
            if ($approved_calendar) {
                $meta_query[] = [
                    'key' => '_calendar_approval_status',
                    'value' => serialize(strval($approved_calendar)) . ';s:8:"approved"',
                    'compare' => 'LIKE'
                ];
                
                $tax_query[] = [
                    'taxonomy' => 'event_caes_departments',
                    'field' => 'term_id',
                    'terms' => $approved_calendar
                ];
            }
        }
        
        // Multiple calendars
        if (!empty($approved_calendars)) {
            $calendar_ids = [];
            $calendar_list = is_array($approved_calendars) ? $approved_calendars : explode(',', $approved_calendars);
            
            foreach ($calendar_list as $calendar) {
                $calendar = trim($calendar);
                if (is_numeric($calendar)) {
                    $calendar_ids[] = $calendar;
                } else {
                    $term = get_term_by('slug', $calendar, 'event_caes_departments');
                    if ($term) {
                        $calendar_ids[] = $term->term_id;
                    }
                }
            }
            
            if (!empty($calendar_ids)) {
                $approval_meta_query = ['relation' => 'OR'];
                foreach ($calendar_ids as $term_id) {
                    $approval_meta_query[] = [
                        'key' => '_calendar_approval_status',
                        'value' => serialize(strval($term_id)) . ';s:8:"approved"',
                        'compare' => 'LIKE'
                    ];
                }
                $meta_query[] = $approval_meta_query;
                
                $tax_query[] = [
                    'taxonomy' => 'event_caes_departments',
                    'field' => 'term_id',
                    'terms' => $calendar_ids,
                    'operator' => 'IN'
                ];
            }
        }
        
        // Generic approval requirement
        if (!empty($require_approval) && $require_approval !== 'false') {
            $meta_query[] = [
                'key' => '_calendar_approval_status',
                'value' => 's:8:"approved"',
                'compare' => 'LIKE'
            ];
        }
        
        if (!empty($meta_query)) {
            $query->set('meta_query', $meta_query);
        }
        
        if (!empty($tax_query)) {
            $query->set('tax_query', $tax_query);
        }
    }
}
add_action('parse_query', 'modify_wp_query_for_approvals');

/**
 * Example usage functions - you can use these as templates
 */

/**
 * Example: Get Marketing calendar events
 */
function example_get_marketing_events() {
    $marketing_term = get_term_by('slug', 'marketing', 'event_caes_departments');
    if ($marketing_term) {
        return get_approved_events_for_calendar($marketing_term->term_id, array(
            'posts_per_page' => 5,
            'meta_key' => 'event_date',
            'orderby' => 'meta_value',
            'order' => 'ASC'
        ));
    }
    return false;
}

/**
 * Example: Get events for current user's calendars
 */
function example_get_my_calendar_events() {
    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        return false;
    }
    
    // Get calendars this user can submit to
    $user_calendars = get_user_submit_calendars($current_user_id);
    
    if (!empty($user_calendars)) {
        return get_approved_events_for_calendars($user_calendars, array(
            'posts_per_page' => 10,
            'meta_key' => 'event_date',
            'orderby' => 'meta_value',
            'order' => 'ASC'
        ));
    }
    
    return false;
}