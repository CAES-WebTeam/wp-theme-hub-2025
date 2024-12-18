<?php
// Get the current post ID
$post_id = get_the_ID();

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

$dateAsSnippet = $block['dateAsSnippet'];
$showTime = $block['showTime'];
$showDate = $block['showDate'];
$heading = $block['heading'];

// Initialize variables
$date = '';
$time = '';

// Set Start Date
if( !empty(get_field('start_date', $post_id)) ):
	$date_object = DateTime::createFromFormat('Ymd', get_field('start_date', $post_id));
	$formatted_date = $date_object ? $date_object->format('F j, Y') : 'Invalid date format';
	$date = $formatted_date;
endif;

// Set End Date
if( !empty(get_field('end_date', $post_id)) ):
	$date_object = DateTime::createFromFormat('Ymd', get_field('end_date', $post_id));
	$formatted_date = $date_object ? $date_object->format('F j, Y') : 'Invalid date format';
	$date = $date.'-'.$formatted_date;
endif;

// Set Start Time
if( !empty(get_field('start_time', $post_id)) ):
	$time = get_field('start_time', $post_id);
endif;

// Set End Time
if( !empty(get_field('end_time', $post_id)) ):
	$time = $time.'-'.get_field('end_time', $post_id);
endif;
?>

<div <?php echo $attrs; ?>>
    <?php if ($heading): ?>
        <?php if (!$showDate && !$showTime): ?>
            <!-- Message when heading is ON, but neither date nor time is selected -->
            <p class="event-details-message">
                <?php echo esc_html__('Please select either "Display date" or "Display time".', 'caes-hub'); ?>
            </p>
        <?php elseif ($dateAsSnippet): ?>
            <!-- Display heading with date as snippet -->
            <h3 class="event-details-title">
                <?php echo !empty($date) ? esc_html($date) : esc_html__('No date available', 'caes-hub'); ?>
            </h3>
        <?php else: ?>
            <!-- Heading with "Date" or "Date & Time" -->
            <h3 class="event-details-title">
                <?php echo esc_html__('Date', 'caes-hub') . ($showTime ? esc_html__(' & Time', 'caes-hub') : ''); ?>
            </h3>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!$dateAsSnippet && ($showDate || $showTime)): ?>
        <!-- Render content -->
        <div class="event-details-content">
            <?php if ($showDate && !empty($date)): ?>
                <?php echo esc_html($date); ?>
            <?php endif; ?>

            <?php if ($showTime && !empty($time)): ?>
                <?php if ($showDate): ?><br /><?php endif; ?>
                <?php echo esc_html($time); ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>