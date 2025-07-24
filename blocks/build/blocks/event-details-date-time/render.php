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

// Get date type
$date_type = get_field('event_date_type', $post_id);

// Initialize variables
$date_output = '';

if ($date_type === 'single') {
    // Handle single event
    $date = '';
    $time = '';
    
    // Set Start Date
    if (!empty(get_field('start_date', $post_id))) :
        $date_object = DateTime::createFromFormat('Ymd', get_field('start_date', $post_id));
        $formatted_date = $date_object ? $date_object->format('F j, Y') : 'Invalid date format';
        $date = $formatted_date;
    endif;

    // Set End Date
    if (!empty(get_field('end_date', $post_id))) :
        $date_object = DateTime::createFromFormat('Ymd', get_field('end_date', $post_id));
        $formatted_date = $date_object ? $date_object->format('F j, Y') : 'Invalid date format';
        $date = $date . '-' . $formatted_date;
    endif;

    // Set Start Time
    if (!empty(get_field('start_time', $post_id))) :
        $time = get_field('start_time', $post_id);
    endif;

    // Set End Time
    if (!empty(get_field('end_time', $post_id))) :
        $time = $time . '-' . get_field('end_time', $post_id);
    endif;
    
    // Build single date output
    if ($showDate && !empty($date)) {
        $date_output .= $date;
    }
    if ($showTime && !empty($time)) {
        if ($showDate) $date_output .= '<br />';
        $date_output .= $time;
    }
    
} elseif ($date_type === 'multi') {
    // Handle multiday event
    $multi_dates = get_field('date_and_time', $post_id);
    
    if (!empty($multi_dates) && is_array($multi_dates)) {
        foreach ($multi_dates as $date_entry) {
            $session_date = '';
            $session_time = '';
            
            // Format date
            if (!empty($date_entry['start_date_copy'])) {
                $date_object = DateTime::createFromFormat('Ymd', $date_entry['start_date_copy']);
                $session_date = $date_object ? $date_object->format('F j, Y') : 'Invalid date';
            }
            
            // Format time
            if (!empty($date_entry['start_time_copy'])) {
                $session_time = $date_entry['start_time_copy'];
                if (!empty($date_entry['end_time_copy'])) {
                    $session_time .= '-' . $date_entry['end_time_copy'];
                }
            }
            
            // Build this session's output
            $session_output = '';
            if ($showDate && !empty($session_date)) {
                $session_output .= $session_date;
            }
            if ($showTime && !empty($session_time)) {
                if ($showDate) $session_output .= '<br />';
                $session_output .= $session_time;
            }
            
            if (!empty($session_output)) {
                $date_output .= '<p>' . $session_output . '</p>';
            }
        }
    }
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
                <?php 
                // For snippet mode, show "Multiple dates" for multi events
                if ($date_type === 'multi') {
                    echo esc_html__('Multiple dates', 'caes-hub');
                } else {
                    echo !empty($date) ? esc_html($date) : esc_html__('No date available', 'caes-hub');
                }
                ?>
            </h3>
        <?php else: ?>
            <h3 class="event-details-title"<?php echo $style; ?>>
                <?php 
                if ($date_type === 'multi') {
                    echo esc_html__('Dates', 'caes-hub');
                } else {
                    echo esc_html__('Date', 'caes-hub') . ($showTime ? esc_html__(' & Time', 'caes-hub') : '');
                }
                ?>
            </h3>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!$dateAsSnippet && ($showDate || $showTime) && !empty($date_output)): ?>
        <div class="event-details-content">
            <?php echo $date_output; ?>
        </div>
    <?php endif; ?>
</div>