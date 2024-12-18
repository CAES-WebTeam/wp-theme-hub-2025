<?php
$post_id = get_the_ID();
function generate_ics($post_id)
{
    // Get event details
    $event_title = html_entity_decode(get_the_title($post_id), ENT_QUOTES, 'UTF-8'); // Decode HTML entities
    $event_start = get_field('start_date', $post_id); // Start date
    $event_end = get_field('end_date', $post_id); // End date
    $event_start_time = get_field('start_time', $post_id); // Start time
    $event_end_time = get_field('end_time', $post_id); // End time

    // Check if it's an all-day event (no start or end time)
    $is_all_day = empty($event_start_time) && empty($event_end_time);

    if ($is_all_day) {
        // All-day event: Use only dates, omit times
        $dtstart = date('Ymd', strtotime($event_start));
        $dtend = $event_end ? date('Ymd', strtotime($event_end . ' +1 day')) : date('Ymd', strtotime($event_start . ' +1 day'));
    } else {
        // Timed event: Include local time without 'Z'
        $start_datetime = $event_start . ' ' . ($event_start_time ?: '00:00');
        $end_datetime = ($event_end ?: $event_start) . ' ' . ($event_end_time ?: '23:59');
        $dtstart = date('Ymd\THis', strtotime($start_datetime));
        $dtend = date('Ymd\THis', strtotime($end_datetime));
    }

    // Check and set location based on event type
    if (!empty(get_field('location_caes_room', $post_id)) && get_field('event_type', $post_id) == 'CAES') {
        $location = html_entity_decode(get_field('location_caes_room', $post_id), ENT_QUOTES, 'UTF-8');
    } elseif (!empty(get_field('location_county_office', $post_id)) && get_field('event_type', $post_id) == 'Extension') {
        $location = html_entity_decode(get_field('location_county_office', $post_id), ENT_QUOTES, 'UTF-8');
    } else {
        $location = ''; // Default to empty if no location
    }

    // Get and process the description
    $raw_description = get_field('description', $post_id);
    $event_description = html_entity_decode(strip_tags($raw_description), ENT_QUOTES, 'UTF-8'); // Strip HTML tags and decode entities

    // Escape ICS special characters
    $event_title = escape_ics_text($event_title);
    $location = escape_ics_text($location);
    $event_description = escape_ics_text($event_description);

    // Convert to ICS format
    $ics_content = "BEGIN:VCALENDAR\r\n";
    $ics_content .= "VERSION:2.0\r\n";
    $ics_content .= "BEGIN:VEVENT\r\n";
    $ics_content .= "SUMMARY:" . $event_title . "\r\n";

    // Add DTSTART and DTEND
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


/**
 * Escape special characters for ICS file content.
 */
function escape_ics_text($text)
{
    $text = str_replace("\\", "\\\\", $text); // Escape backslashes
    $text = str_replace(",", "\\,", $text);  // Escape commas
    $text = str_replace(";", "\\;", $text);  // Escape semicolons
    $text = str_replace("\n", "\\n", $text); // Escape newlines
    $text = str_replace("\r", "\\n", $text); // Escape carriage returns
    return $text;
}


function event_ics_file_url($post_id)
{
    $ics_content = generate_ics($post_id);
    $file_path = wp_upload_dir()['path'] . "/event-" . $post_id . ".ics";

    // Write the ICS content to a file
    file_put_contents($file_path, $ics_content);

    return wp_upload_dir()['url'] . "/event-" . $post_id . ".ics";
}

// Get the ICS file URL
$ics_file_url = event_ics_file_url($post_id);

?>

<div <?php echo get_block_wrapper_attributes(); ?>>
    <button class="caes-hub-action-ics__button" data-ics-url="<?php echo esc_url($ics_file_url); ?>"><span class="label">Add to Calendar</span></button>
</div>