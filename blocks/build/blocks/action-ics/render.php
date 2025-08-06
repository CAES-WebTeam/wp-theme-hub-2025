<?php
$post_id = get_the_ID();

function generate_ics($post_id)
{
    // Get event details
    $event_title = html_entity_decode(get_the_title($post_id), ENT_QUOTES, 'UTF-8');
    $event_start = get_field('start_date', $post_id);
    $event_end = get_field('end_date', $post_id);
    $event_start_time = get_field('start_time', $post_id);
    $event_end_time = get_field('end_time', $post_id);

    // Check if it's an all-day event (no start or end time)
    $is_all_day = empty($event_start_time) && empty($event_end_time);

    // Apply the same date logic as the display component
    $start_date_object = null;
    $end_date_object = null;
    $is_date_range = false;

    // Parse start date
    if (!empty($event_start)) {
        $start_date_object = DateTime::createFromFormat('Ymd', $event_start);
        
        // Check if we have an end date and if it's different from start date
        if (!empty($event_end)) {
            $end_date_object = DateTime::createFromFormat('Ymd', $event_end);
            if ($end_date_object && $start_date_object) {
                // Compare dates to see if it's a range (end date is after start date)
                if ($end_date_object > $start_date_object) {
                    $is_date_range = true;
                }
            }
        }
    }

    // Generate ICS dates based on the simplified logic
    if ($is_all_day) {
        // All-day event: Use only dates, omit times
        if ($start_date_object) {
            $dtstart = $start_date_object->format('Ymd');
            
            if ($is_date_range && $end_date_object) {
                // For all-day events, end date should be the day after the actual end date
                $end_date_plus_one = clone $end_date_object;
                $end_date_plus_one->modify('+1 day');
                $dtend = $end_date_plus_one->format('Ymd');
            } else {
                // Single day all-day event
                $single_day_plus_one = clone $start_date_object;
                $single_day_plus_one->modify('+1 day');
                $dtend = $single_day_plus_one->format('Ymd');
            }
        } else {
            // Fallback if date parsing fails
            $dtstart = date('Ymd', strtotime($event_start));
            $dtend = date('Ymd', strtotime($event_start . ' +1 day'));
        }
    } else {
        // Timed event: Include local time without 'Z'
        if ($start_date_object) {
            $start_datetime = $start_date_object->format('Y-m-d') . ' ' . ($event_start_time ?: '00:00');
            
            if ($is_date_range && $end_date_object) {
                $end_datetime = $end_date_object->format('Y-m-d') . ' ' . ($event_end_time ?: '23:59');
            } else {
                // Same day event
                $end_datetime = $start_date_object->format('Y-m-d') . ' ' . ($event_end_time ?: '23:59');
            }
            
            $dtstart = date('Ymd\THis', strtotime($start_datetime));
            $dtend = date('Ymd\THis', strtotime($end_datetime));
        } else {
            // Fallback if date parsing fails
            $start_datetime = $event_start . ' ' . ($event_start_time ?: '00:00');
            $end_datetime = ($event_end ?: $event_start) . ' ' . ($event_end_time ?: '23:59');
            $dtstart = date('Ymd\THis', strtotime($start_datetime));
            $dtend = date('Ymd\THis', strtotime($end_datetime));
        }
    }

    // Get location from Google Maps field
    $location = '';
    $event_location_type = get_field('event_location_type', $post_id);
    
    $google_map = get_field('location_google_map', $post_id);
    $online_address = get_field('online_location_web_address', $post_id);
    
    $physical_location = '';
    $online_location = '';
    
    // Get physical location if exists
    if (!empty($google_map) && is_array($google_map)) {
        $physical_location = $google_map['address'] ?? '';
    }
    
    // Get online location if exists
    if (!empty($online_address)) {
        $online_location = $online_address;
    }
    
    // Combine locations based on event type
    if ($event_location_type === 'Both' && $physical_location && $online_location) {
        $location = $physical_location . ' / Online: ' . $online_location;
    } elseif ($physical_location) {
        $location = $physical_location;
    } elseif ($online_location) {
        $location = $online_location;
    }

    // Get and process the description
    $raw_description = get_field('description', $post_id);
    $event_description = html_entity_decode(strip_tags($raw_description), ENT_QUOTES, 'UTF-8');

    // Escape ICS special characters
    $event_title = escape_ics_text($event_title);
    $location = escape_ics_text($location);
    $event_description = escape_ics_text($event_description);

    // Convert to ICS format
    $ics_content = "BEGIN:VCALENDAR\r\n";
    $ics_content .= "VERSION:2.0\r\n";
    $ics_content .= "BEGIN:VEVENT\r\n";
    $ics_content .= "SUMMARY:" . $event_title . "\r\n";
    $ics_content .= "DTSTART:" . $dtstart . "\r\n";
    $ics_content .= "DTEND:" . $dtend . "\r\n";

    // Only add location if available
    if (!empty($location)) {
        $ics_content .= "LOCATION:" . $location . "\r\n";
    }

    // Add description if available
    if (!empty($event_description)) {
        $ics_content .= "DESCRIPTION:" . $event_description . "\r\n";
    }

    $ics_content .= "END:VEVENT\r\n";
    $ics_content .= "END:VCALENDAR\r\n";

    return $ics_content;
}

function escape_ics_text($text)
{
    $text = str_replace("\\", "\\\\", $text);
    $text = str_replace(",", "\\,", $text);
    $text = str_replace(";", "\\;", $text);
    $text = str_replace("\n", "\\n", $text);
    $text = str_replace("\r", "\\n", $text);
    return $text;
}

// Handle download request
if (isset($_GET['download_ics']) && $_GET['download_ics'] == $post_id) {
    $ics_content = generate_ics($post_id);
    $filename = 'event-' . $post_id . '.ics';
    
    // Set proper headers for download
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($ics_content));
    
    echo $ics_content;
    exit;
}

// Create download URL instead of file
$download_url = add_query_arg(['download_ics' => $post_id], get_permalink($post_id));

// Get event data for tracking
$event_title = get_the_title($post_id);
$event_date = get_field('start_date', $post_id);
$current_url = get_permalink($post_id);
$path_url = wp_make_link_relative(get_permalink($post_id));
?>

<div <?php echo get_block_wrapper_attributes(); ?>>
    <button class="caes-hub-action-ics__button"
            data-ics-url="<?php echo esc_attr($download_url); ?>"
            data-event-title="<?php echo esc_attr($event_title); ?>"
            data-event-date="<?php echo esc_attr($event_date); ?>"
            data-event-url="<?php echo esc_attr($path_url); ?>"
            data-action-type="calendar_download">
        <span class="label">Add to Calendar</span>
    </button>
</div>