<?php
$title = get_field('event_title', $event);

// Clear variables
$date = null;

// Initialize the event date variable
$date = '';

// Get the current date in 'd/m/Y' format
$today = date('d/m/Y');

// Set Start Date
$start_date_raw = get_field('start_date', $event);
if ( !empty($start_date_raw) ) {
    $date_object = DateTime::createFromFormat('Ymd', $start_date_raw);
    $formatted_date = $date_object ? $date_object->format('F j, Y') : 'Invalid date format';
    $date = $formatted_date;
}

// Set End Date
$end_date_raw = get_field('end_date', $event);
if ( !empty($end_date_raw) ) {
    $date_object = DateTime::createFromFormat('Ymd', $end_date_raw);
    $formatted_date = $date_object ? $date_object->format('F j, Y') : 'Invalid date format';
    $date = $date . ' - ' . $formatted_date;
}

// Check and set location if event type is CAES
if( !empty(get_field('location_caes_room', $event)) AND get_field('event_type', $event) == 'CAES' ):
	$location = get_field('location_caes_room', $event);
endif;

// Check and set location if event type is Extension
if( !empty(get_field('location_county_office', $event)) AND get_field('event_type', $event) == 'Extension' ):
	$location = get_field('location_county_office', $event);
endif;

// Get the registration start and end dates from ACF fields
$registration_start = get_field('registration_start_date', $event);
$registration_end = get_field('registration_end_date', $event);

// Convert the dates to DateTime objects for comparison
$start_date = DateTime::createFromFormat('d/m/Y', $registration_start);
$end_date = DateTime::createFromFormat('d/m/Y', $registration_end);
$current_date = DateTime::createFromFormat('d/m/Y', $today);

// Convert the dates to DateTime objects for comparison
if( !empty(get_field('featured_image', $event)) ):
	$featured_image = get_field('featured_image', $event);
endif; 
?>

<div class="wp-block-group caes-hub-content-f-img" style="margin-top: var(--wp--preset--spacing--60);">
	<div class="wp-block-columns" style="gap: 0;">
		<div class="wp-block-column" style="background: #333; color: #fff; display: flex; align-items: center;">

			<div style="padding: var(--wp--preset--spacing--20) var(--wp--preset--spacing--60); box-sizing: border-box; width: 100%;">

				<div class="event-details-date"><?php echo esc_html($date); ?></div>

				<h2 class="post-title" style="text-align: left;"><?php echo esc_html(get_the_title($event)); ?></h2>

				<?php 
				// Check if today's date is between the start and end dates
				if ( $start_date && $end_date && $current_date >= $start_date && $current_date <= $end_date ) {

				    // Get the registration link from ACF
				    $registration_link = get_field('registration_link', $event);

				    // Display the button if the registration link is set
				    if ( $registration_link ) {
				        echo '<a href="' . esc_url( $registration_link ) . '" class="event-registration-button" target="outside">Register Now</a>';
				    }
				}
				?>

				<?php echo !empty($location) ? '<div class="event-detail-location">' . $location . '</div>' : ''; ?>

			</div>
		</div>

		<div class="wp-block-column">

			<div class="event-featured-image" style="background:#f2f2f2;">
				<canvas width="800" height="550"></canvas>';
				<?php if( !empty($featured_image) ): echo '<img src="'.$featured_image['url'].'" alt="'.$featured_image['alt'].'" />'; endif; ?>
			</div>

		</div>

	</div>
</div>