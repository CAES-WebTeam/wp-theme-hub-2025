<?php
// Get the current post ID
$post_id = get_the_ID();

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

$dateAsSnippet = $block['dateAsSnippet'];
$showTime = $block['showTime'];
$showDate = $block['showDate'];
$heading = $block['heading'];
$fontSize = isset($block['headingFontSize']) && !empty($block['headingFontSize']) ? esc_attr($block['headingFontSize']) : '';
$fontUnit = isset($block['headingFontUnit']) ? esc_attr($block['headingFontUnit']) : 'px';

// Generate inline style if font size is set
$style = $fontSize ? ' style="font-size: ' . $fontSize . $fontUnit . ';"' : '';

// Get date fields
$start_date = get_field('start_date', $post_id);
$end_date = get_field('end_date', $post_id);
$start_time = get_field('start_time', $post_id);
$end_time = get_field('end_time', $post_id);
$publish_display_date = get_field('publish_display_date', $post_id);

// Initialize variables
$date_output = '';
$date = '';
$time = '';
$is_multi_day = false;

// Format start date
if (!empty($start_date)) {
    $start_date_object = DateTime::createFromFormat('Ymd', $start_date);
    $formatted_start_date = $start_date_object ? $start_date_object->format('F j, Y') : 'Invalid date format';
    
    // Check if we have an end date and if it's different from start date
    if (!empty($end_date)) {
        $end_date_object = DateTime::createFromFormat('Ymd', $end_date);
        if ($end_date_object && $start_date_object) {
            // Check if it's a multi-day event
            if ($end_date_object > $start_date_object) {
                $is_multi_day = true;
                
                // For multi-day events with different start/end times, use full explicit format
                if ($showTime && !empty($start_time) && !empty($end_time)) {
                    $date = $formatted_start_date . ' at ' . $start_time . ' – ' . $end_date_object->format('F j, Y') . ' at ' . $end_time;
                } else {
                    // Smart date range formatting for multi-day without times
                    $start_month = $start_date_object->format('n');
                    $start_year = $start_date_object->format('Y');
                    $end_month = $end_date_object->format('n');
                    $end_year = $end_date_object->format('Y');
                    
                    if ($start_year == $end_year && $start_month == $end_month) {
                        // Same month and year: "August 21 – 22, 2025"
                        $date = $start_date_object->format('F j') . ' – ' . $end_date_object->format('j, Y');
                    } elseif ($start_year == $end_year) {
                        // Same year, different month: "August 21 – September 5, 2025"
                        $date = $start_date_object->format('F j') . ' – ' . $end_date_object->format('F j, Y');
                    } else {
                        // Different year: "December 30, 2024 – January 2, 2025"
                        $date = $formatted_start_date . ' – ' . $end_date_object->format('F j, Y');
                    }
                }
            } else {
                // Same day (end date equals start date)
                $date = $formatted_start_date;
            }
        }
    } else {
        // No end date, just use start date
        $date = $formatted_start_date;
    }
}

// Format time for single-day events or multi-day events without the explicit format
if (!$is_multi_day || ($is_multi_day && empty($start_time))) {
    if (!empty($start_time)) {
        $time = $start_time;
        if (!empty($end_time)) {
            $time .= ' – ' . $end_time;
        }
    }
}

// Build date output
if ($showDate && !empty($date)) {
    $date_output .= $date;
}

// Only add time separately if we haven't already included it in the date for multi-day events
if ($showTime && !empty($time)) {
    if ($showDate) $date_output .= '<br />';
    $date_output .= $time;
}
?>

<div <?php echo $attrs; ?>>
    <?php if ($heading): ?>
        <?php if (!$showDate && !$showTime): ?>
            <p class="event-details-message">
                <?php echo esc_html__('Please select either "Display date" or "Display time".', 'caes-hub'); ?>
            </p>
        <?php elseif ($dateAsSnippet): ?>
            <h3 class="event-details-title"<?php echo $style; ?>>
                <?php echo !empty($date) ? esc_html($date) : esc_html__('No date available', 'caes-hub'); ?>
            </h3>
        <?php else: ?>
            <h3 class="event-details-title"<?php echo $style; ?>>
                <?php echo esc_html__('Date', 'caes-hub') . ($showTime ? esc_html__(' & Time', 'caes-hub') : ''); ?>
            </h3>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!$dateAsSnippet && ($showDate || $showTime) && !empty($date_output)): ?>
        <div class="event-details-content">
            <?php echo $date_output; ?>
        </div>
    <?php endif; ?>
</div>