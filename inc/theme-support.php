<?php

// Editor Styles
function caes_hub_editor_styles()
{
	add_editor_style('./assets/css/editor.css');
}
add_action('after_setup_theme', 'caes_hub_editor_styles');


// Enqueue style sheet and JavaScript
function caes_hub_styles()
{
	wp_enqueue_style(
		'caes-hub-styles',
		get_theme_file_uri('assets/css/main.css'),
		[],
		wp_get_theme()->get('Version')
	);
	wp_enqueue_script('caes-hub-script', get_template_directory_uri() . '/assets/js/main.js', array(), '1.0.0', true);
}
add_action('wp_enqueue_scripts', 'caes_hub_styles');

// Adds custom style choices to core blocks with add-block-styles.js
function theme_editor_assets()
{
	wp_enqueue_script(
		'block-styles',
		get_theme_file_uri() . '/assets/js/block-styles.js',
		array('wp-blocks', 'wp-dom-ready', 'wp-edit-post')
	);
}
add_action('enqueue_block_editor_assets', 'theme_editor_assets');


// Remove Default Block Patterns
function remove_default_block_patterns()
{
	remove_theme_support('core-block-patterns');
}
add_action('after_setup_theme', 'remove_default_block_patterns');


// Unregister API Patterns
add_filter('should_load_remote_block_patterns', '__return_false');

// Improve thumbnail quality

add_filter('jpeg_quality', function($arg){ return 90; });
add_filter('wp_editor_set_quality', function($arg){ return 90; });


// Function to retrieve the user's IP address
if (!function_exists('getUserIP')) {
	function getUserIP()
	{
		// Check for shared internet/proxy servers
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			return $_SERVER['HTTP_CLIENT_IP'];
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			// To handle multiple IPs passed by proxies
			$ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
			return trim($ipList[0]);
		} else {
			return $_SERVER['REMOTE_ADDR'];
		}
	}
}

// Define a constant for the user's IP address
if (!defined('USER_IP')) {
	//define('USER_IP', getUserIP());
	define('USER_IP', '174.109.38.141');
}

// Set IPStack API key
if (!defined('IPSTACK_API_KEY')) {
	define('IPSTACK_API_KEY', '338d99bff58c62f955abeb40826ee660');
}

// Function to get user location
if (!function_exists('getUserLocation')) {
	function getUserLocation()
	{
		$url = "http://api.ipstack.com/" . USER_IP . "?access_key=" . IPSTACK_API_KEY;

		// Make the API request
		$response = wp_remote_get($url); // Use WordPress HTTP API
		if (is_wp_error($response)) {
			return null;
		}

		$data = json_decode(wp_remote_retrieve_body($response), true);

		if (isset($data['latitude']) && isset($data['longitude'])) {
			return [
				'latitude' => $data['latitude'],
				'longitude' => $data['longitude']
			];
		}

		return null;
	}
}

// Function to calculate the distance
if (!function_exists('calculateDistance')) {
	function calculateDistance($lat1, $lon1, $lat2, $lon2)
	{
		// Convert degrees to radians
		$lat1 = deg2rad($lat1);
		$lon1 = deg2rad($lon1);
		$lat2 = deg2rad($lat2);
		$lon2 = deg2rad($lon2);

		// Haversine formula
		$earthRadius = 3958.8; // Radius of Earth in miles
		$deltaLat = $lat2 - $lat1;
		$deltaLon = $lon2 - $lon1;

		$a = sin($deltaLat / 2) * sin($deltaLat / 2) +
			cos($lat1) * cos($lat2) *
			sin($deltaLon / 2) * sin($deltaLon / 2);
		$c = 2 * atan2(sqrt($a), sqrt(1 - $a));

		return $earthRadius * $c; // Distance in miles
	}
}

// Function to check if within radius
if (!function_exists('isWithinRadius')) {
	function isWithinRadius($userLat, $userLon, $targetLat, $targetLon, $radius)
	{
		$distance = calculateDistance($userLat, $userLon, $targetLat, $targetLon);
		return $distance <= $radius;
	}
}

// add_filter( 'pre_render_block', 'wpfieldwork_upcoming_events_pre_render_block', 10, 2 );
function wpfieldwork_upcoming_events_pre_render_block($pre_render, $parsed_block)
{

	// Verify it's the block that should be modified using the namespace
	if (!empty($parsed_block['attrs']['namespace']) && 'upcoming-events' === $parsed_block['attrs']['namespace']) {

		add_filter(
			'query_loop_block_query_vars',
			function ($query, $block) {
				// get today's date in Ymd format
				$today = date('Ymd');

				// the meta key was start_date, compare to today to get event's from today or later
				$query['meta_key'] = 'start_date';
				$query['meta_value'] = $today;
				$query['meta_compare'] = '>=';

				// also likely want to set order by this key in ASC so next event listed first
				$query['orderby'] = 'meta_value';
				$query['order'] = 'ASC';

				return $query;
			},
			10,
			2
		);
	}
	return $pre_render;
}

add_filter('rest_events_query', 'wpfieldwork_rest_upcoming_events', 10, 2);

function wpfieldwork_rest_upcoming_events($args, $request)
{

	// grab value from the request
	$dateFilter = $request['filterByDate'];

	// proceed if it exists
	// add same meta query arguments
	if ($dateFilter) {
		$today = date('Ymd');
		$args['meta_key'] = 'start_date';
		$args['meta_value'] = $today;
		$args['meta_compare'] = '>=';
		$args['orderby'] = 'meta_value';
		$args['order'] = 'ASC';
	}

	return $args;
}

// Register pattern categories
add_action('init', function() {
	if (function_exists('register_block_pattern_category')) {
		register_block_pattern_category(
			'pub_feeds',
			array(
				'label' => __('Publication Feeds', 'field-report'),
				'description' => __('Publication feeds', 'field-report'),
			)
		);
		register_block_pattern_category(
			'story_feeds',
			array(
				'label' => __('Story Feeds', 'field-report'),
				'description' => __('Story feeds', 'field-report'),
			)
		);
		register_block_pattern_category(
			'event_feeds',
			array(
				'label' => __('Event Feeds', 'field-report'),
				'description' => __('Event feeds', 'field-report'),
			)
		);
		register_block_pattern_category(
			'content_patterns',
			array(
				'label' => __('Content', 'field-report'),
				'description' => __('Design patterns that can be used in article and publication content', 'field-report'),
			)
		);
	}
});

/* Filter for search form */
add_filter('get_search_form', function($form) {
    // Customize the default search form markup
    $form = '
    <form role="search" method="get" class="caes-hub-form__input-button-container" action="' . esc_url(home_url('/')) . '">
        <label>
            <span class="screen-reader-text">' . _x('Search for:', 'label') . '</span>
            <input type="search" class="caes-hub-form__input" placeholder="' . esc_attr_x('Search …', 'placeholder') . '" value="' . get_search_query() . '" name="s">
        </label>
    </form>';
    return $form;
});


// Enable the REST API for Shorthand post type, makes it available in block editor query loop
function shorthand_rest_api($args, $post_type) {
    if ($post_type === 'shorthand_story') {
        $args['show_in_rest'] = true;

        // Ensure 'supports' is an array and add 'excerpt'
        if (isset($args['supports']) && is_array($args['supports'])) {
            $args['supports'][] = 'excerpt';
        } else {
            $args['supports'] = ['excerpt'];
        }
    }
    return $args;
}
add_filter('register_post_type_args', 'shorthand_rest_api', 10, 2);

// Add excerpt field for pages
function add_excerpts_to_pages() {
    add_post_type_support('page', 'excerpt');
}
add_action('init', 'add_excerpts_to_pages');

// Add custom login stylesheet
function hub_login_stylesheet()
{
	wp_enqueue_style('custom-login', get_stylesheet_directory_uri() . '/assets/css/login.css');
}
add_action('login_enqueue_scripts', 'hub_login_stylesheet');

// Format dates in APA style
function format_date_apa_style( $timestamp ) {
	$month = date( 'n', $timestamp );
	$day   = date( 'j', $timestamp );
	$year  = date( 'Y', $timestamp );

	$month_names = [
		1  => 'Jan.',
		2  => 'Feb.',
		3  => 'March',
		4  => 'April',
		5  => 'May',
		6  => 'June',
		7  => 'July',
		8  => 'Aug.',
		9  => 'Sept.',
		10 => 'Oct.',
		11 => 'Nov.',
		12 => 'Dec.',
	];

	$month_str = $month_names[ $month ];

	return "$month_str $day, $year";
}

add_filter( 'render_block', function( $block_content, $block ) {
	if ( $block['blockName'] !== 'core/post-date' ) {
		return $block_content;
	}

	$post_id  = get_the_ID();
	$datetime = get_post_datetime( $post_id );

	if ( ! $datetime ) {
		return $block_content;
	}

	$timestamp = $datetime->getTimestamp();
	$apa_date  = format_date_apa_style( $timestamp );

	// Replace only the contents of the <time> tag
	$block_content = preg_replace_callback(
		'|<time([^>]*)>(.*?)</time>|i',
		function ( $matches ) use ( $apa_date ) {
			return '<time' . $matches[1] . '>' . esc_html( $apa_date ) . '</time>';
		},
		$block_content
	);

	return $block_content;
}, 10, 2 );

// Wrap Classic Editor blocks in a div with a specific class
function wrap_classic_content( $content ) {
    // Get the current post type
    $post_type = get_post_type();
    
    // Check if we're on a singular view AND post type is either post or publications
    if ( is_singular() && ($post_type === 'post' || $post_type === 'publications') ) {
        // More thorough check if content uses blocks
        // If content contains any block markers or has_blocks returns true, consider it block content
        $has_block_content = has_blocks($content) || 
                             strpos($content, '<!-- wp:') !== false || 
                             strpos($content, 'wp-block-') !== false;
        
        // Only wrap if it's NOT block content
        if ( !$has_block_content ) {
            // Add the wrapping div
            $wrapper_start = '<div class="classic-content-wrapper">';
            $wrapper_end = '</div>';
            // Wrap the content
            $content = $wrapper_start . $content . $wrapper_end;
        }
    }
    return $content;
}
add_filter( 'the_content', 'wrap_classic_content' );

// Saved Post AJAX
add_action('wp_enqueue_scripts', function() {
  wp_register_script('saved-posts-helper', false);
  wp_enqueue_script('saved-posts-helper');

  wp_localize_script('saved-posts-helper', 'SavedPostsVars', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
  ]);
});

add_action('wp_ajax_nopriv_get_saved_posts', 'get_saved_posts_callback');
add_action('wp_ajax_get_saved_posts', 'get_saved_posts_callback');

// Callback function to handle AJAX request for saved posts
function get_saved_posts_callback() {
    if (empty($_GET['ids'])) {
        echo '<p>No saved posts.</p>';
        wp_die();
    }

    $output = '';

    foreach ($_GET['ids'] as $post_type => $ids) {
        $ids = array_map('intval', (array)$ids);
        if (empty($ids)) continue;

        $posts = get_posts([
            'post_type' => $post_type,
            'post__in' => $ids,
            'orderby' => 'post__in',
            'posts_per_page' => -1,
        ]);

        if ($posts) {
            $output .= '<h2>' . ucfirst($post_type) . '</h2><ul>';
            foreach ($posts as $post) {
                $output .= '<li><a href="' . get_permalink($post) . '">' . esc_html(get_the_title($post)) . '</a></li>';
            }
            $output .= '</ul>';
        }
    }

    echo $output ?: '<p>No matching saved posts found.</p>';
    wp_die();
}

// Add random placeholder image if no featured image is set
add_filter( 'post_thumbnail_html', 'caes_random_placeholder_if_no_thumbnail', 10, 5 );
function caes_random_placeholder_if_no_thumbnail( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
    // Skip only if we're rendering the main post on a single view
    if ( get_the_ID() === $post_id && is_singular() ) {
        return $html;
    }

    // If the post has a featured image, use it
    if ( has_post_thumbnail( $post_id ) ) {
        return $html;
    }

    // Array of placeholder filenames
    $placeholders = [
		'placeholder-bg-1-athens.jpg',
		'placeholder-bg-2-hedges.jpg',
		'placeholder-bg-1-lake-herrick.jpg',
		'placeholder-bg-2-olympic.jpg',
		'placeholder-bg-1-hedges.jpg',
		'placeholder-bg-2-lake-herrick.jpg',
		'placeholder-bg-1-olympic.jpg',
		'placeholder-bg-2-athens.jpg',
	];
	
    $file = $placeholders[ array_rand( $placeholders ) ];
    $url  = get_template_directory_uri() . '/assets/images/' . $file;
    $alt  = get_the_title( $post_id );

    return sprintf(
        '<img src="%s" alt="%s" class="wp-post-image" />',
        esc_url( $url ),
        esc_attr( $alt )
    );
}

// Add Google Tag Manager code to the head (only for non-logged-in users and non-local domains)
function add_gtm_head_block_theme() {
    // Get current domain
    $current_domain = $_SERVER['HTTP_HOST'];
    
    // Only load GTM if user is NOT logged in AND domain doesn't contain ".local"
    if (!is_user_logged_in() && strpos($current_domain, '.local') === false) {
        ?>
        <!-- Google Tag Manager -->
        <script>
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','GTM-MTZTHHB7');
        </script>
        <!-- End Google Tag Manager -->
        <?php
    }
}
add_action('wp_head', 'add_gtm_head_block_theme', 0); // Priority 0 = very top of <head>

// Add Google Tag Manager code to the body (only for non-logged-in users and non-local domains)
function add_gtm_noscript_block_theme() {
    // Get current domain
    $current_domain = $_SERVER['HTTP_HOST'];
    
    // Only load GTM if user is NOT logged in AND domain doesn't contain ".local"
    if (!is_user_logged_in() && strpos($current_domain, '.local') === false) {
        ?>
        <!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-MTZTHHB7"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->
        <?php
    }
}
add_action('wp_body_open', 'add_gtm_noscript_block_theme');

// Global counter for placeholder images
global $caes_placeholder_counter;
$caes_placeholder_counter = 0;

// Helper function for placeholder images in custom blocks
function caes_get_placeholder_image($post_id) {
    global $caes_placeholder_counter;
    
    $placeholders = [
        'placeholder-bg-1-athens.jpg',
        'placeholder-bg-1-hedges.jpg',
        'placeholder-bg-1-lake-herrick.jpg',
        'placeholder-bg-1-olympic.jpg',
        'placeholder-bg-2-athens.jpg',
        'placeholder-bg-2-hedges.jpg',
        'placeholder-bg-2-lake-herrick.jpg',
        'placeholder-bg-2-olympic.jpg',
    ];
    
    $index = $caes_placeholder_counter % count($placeholders);
    $file = $placeholders[$index];
    $caes_placeholder_counter++;
    
    return [
        'url' => get_template_directory_uri() . '/assets/images/' . $file,
        'alt' => get_the_title($post_id)
    ];
}