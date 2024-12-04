<?php
$title = get_field('event_title', $event);

// Clear variables
$location = null;
$start_date = null;
$end_date = null;
$hide_event = false;

// Set event scope
if($for_you):
	/*$targetLat = get_field('google_map', $event)['lat'];
	$targetLon = get_field('google_map', $event)['lng'];*/
	$targetLat = 35.7796;
	$targetLon = -78.6382;
	$radius = get_field('event_scope_radius', $event);

	$userLocation = getUserLocation();

	if ($userLocation) {
		$isInRadius = isWithinRadius(
			$userLocation['latitude'],
			$userLocation['longitude'],
			$targetLat,
			$targetLon,
			$radius
		);

		$hide_event = $isInRadius ? false : true;
	}
endif;

// Check and set location if event type is CAES
if( !empty(get_field('location_caes_room', $event)) AND get_field('event_type', $event) == 'CAES' ):
	$location = get_field('location_caes_room', $event);
endif;

// Check and set location if event type is Extension
if( !empty(get_field('location_county_office', $event)) AND get_field('event_type', $event) == 'Extension' ):
	$location = get_field('location_county_office', $event);
endif;

// Set Start Date
if( !empty(get_field('start_date', $event)) ):
	$date_object = DateTime::createFromFormat('Ymd', get_field('start_date', $event));
	$formatted_date = $date_object ? $date_object->format('F j, Y') : 'Invalid date format';
	$raw_start_date = $date_object ? $date_object->format('Ymd') : '';
	$start_date = $formatted_date;
endif;

// Set End Date
if( !empty(get_field('end_date', $event)) ):
	$date_object = DateTime::createFromFormat('Ymd', get_field('end_date', $event));
	$formatted_date = $date_object ? $date_object->format('F j, Y') : 'Invalid date format';
	$end_date = $formatted_date;
endif;
?>

<?php if($hide_event == false): ?>
<div class="event" data-date="<?php echo $raw_start_date; ?>" data-location="<?php echo $location; ?>" data-series="<?php if(get_field('series', $event)): endif; ?>" >
	<h3><?php echo get_the_title($event); ?></h3>
	<?php if($start_date): ?><div><strong>Start Date:</strong><br /><?php echo $start_date; ?></div><?php endif; ?>
	<?php if($end_date): ?><div><strong>End Date:</strong><br /><?php echo $end_date; ?></div><?php endif; ?>
	<a href="<?php echo get_permalink($event); ?>" class="event-link"></a>
</div>
<?php endif; ?>
