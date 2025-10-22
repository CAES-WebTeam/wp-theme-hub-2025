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

add_filter('jpeg_quality', function ($arg) {
    return 90;
});
add_filter('wp_editor_set_quality', function ($arg) {
    return 90;
});


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
add_action('init', function () {
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
add_filter('get_search_form', function ($form) {
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
function shorthand_rest_api($args, $post_type)
{
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
function add_excerpts_to_pages()
{
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
function format_date_apa_style($timestamp)
{
    $month = date('n', $timestamp);
    $day   = date('j', $timestamp);
    $year  = date('Y', $timestamp);

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

    $month_str = $month_names[$month];

    return "$month_str $day, $year";
}

add_filter('render_block', function ($block_content, $block) {
    if ($block['blockName'] !== 'core/post-date') {
        return $block_content;
    }

    $post_id  = get_the_ID();
    $datetime = get_post_datetime($post_id);

    if (! $datetime) {
        return $block_content;
    }

    $timestamp = $datetime->getTimestamp();
    $apa_date  = format_date_apa_style($timestamp);

    // Replace only the contents of the <time> tag
    $block_content = preg_replace_callback(
        '|<time([^>]*)>(.*?)</time>|i',
        function ($matches) use ($apa_date) {
            return '<time' . $matches[1] . '>' . esc_html($apa_date) . '</time>';
        },
        $block_content
    );

    return $block_content;
}, 10, 2);

// Wrap Classic Editor blocks in a div with a specific class
function wrap_classic_content($content)
{
    // Get the current post type
    $post_type = get_post_type();

    // Check if we're on a singular view AND post type is either post or publications
    if (is_singular() && ($post_type === 'post' || $post_type === 'publications')) {
        // More thorough check if content uses blocks
        // If content contains any block markers or has_blocks returns true, consider it block content
        $has_block_content = has_blocks($content) ||
            strpos($content, '<!-- wp:') !== false ||
            strpos($content, 'wp-block-') !== false;

        // Only wrap if it's NOT block content
        if (!$has_block_content) {
            // Add the wrapping div
            $wrapper_start = '<div class="classic-content-wrapper">';
            $wrapper_end = '</div>';
            // Wrap the content
            $content = $wrapper_start . $content . $wrapper_end;
        }
    }
    return $content;
}
add_filter('the_content', 'wrap_classic_content');

// Saved Post AJAX
add_action('wp_enqueue_scripts', function () {
    wp_register_script('saved-posts-helper', false);
    wp_enqueue_script('saved-posts-helper');

    wp_localize_script('saved-posts-helper', 'SavedPostsVars', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
    ]);
});

add_action('wp_ajax_nopriv_get_saved_posts', 'get_saved_posts_callback');
add_action('wp_ajax_get_saved_posts', 'get_saved_posts_callback');

// Callback function to handle AJAX request for saved posts
function get_saved_posts_callback()
{
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
add_filter('post_thumbnail_html', 'caes_random_placeholder_if_no_thumbnail', 10, 5);
function caes_random_placeholder_if_no_thumbnail($html, $post_id, $post_thumbnail_id, $size, $attr)
{
    // Skip only if we're rendering the main post on a single view
    if (get_the_ID() === $post_id && is_singular()) {
        return $html;
    }

    // If the post has a featured image, use it
    if (has_post_thumbnail($post_id)) {
        return $html;
    }

    // Check if we're on an author archive page using the main query
    if (is_author()) {
        $url = get_template_directory_uri() . '/assets/images/placeholder-bg-1-lake-herrick-big.jpg';
        $alt = get_the_title($post_id);
        return sprintf(
            '<img src="%s" alt="%s" class="wp-post-image" />',
            esc_url($url),
            esc_attr($alt)
        );
    }

    // Array of placeholder filenames for other contexts
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

    $file = $placeholders[array_rand($placeholders)];
    $url  = get_template_directory_uri() . '/assets/images/' . $file;
    $alt  = get_the_title($post_id);

    return sprintf(
        '<img src="%s" alt="%s" class="wp-post-image" />',
        esc_url($url),
        esc_attr($alt)
    );
}

// Add Google Tag Manager code to the head (only for non-logged-in users and non-local domains)
function add_gtm_head_block_theme()
{
    // Get current domain
    $current_domain = $_SERVER['HTTP_HOST'];

    // Only load GTM if user is NOT logged in AND domain doesn't contain ".local"
    if (!is_user_logged_in() && strpos($current_domain, '.local') === false) {
?>
        <!-- Google Tag Manager -->
        <script>
            (function(w, d, s, l, i) {
                w[l] = w[l] || [];
                w[l].push({
                    'gtm.start': new Date().getTime(),
                    event: 'gtm.js'
                });
                var f = d.getElementsByTagName(s)[0],
                    j = d.createElement(s),
                    dl = l != 'dataLayer' ? '&l=' + l : '';
                j.async = true;
                j.src =
                    'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
                f.parentNode.insertBefore(j, f);
            })(window, document, 'script', 'dataLayer', 'GTM-MTZTHHB7');
        </script>
        <!-- End Google Tag Manager -->
    <?php
    }
}
add_action('wp_head', 'add_gtm_head_block_theme', 0); // Priority 0 = very top of <head>

// Add Google Tag Manager code to the body (only for non-logged-in users and non-local domains)
function add_gtm_noscript_block_theme()
{
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
function caes_get_placeholder_image($post_id)
{
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

/* SOFT PUBLISH POST STATUS */

// Note to self: we are only using this on Shorthand posts for now, because it uses the classic type editor,
// and that's easier to change. It would be more complex to add this to the block editor, and isn't being 
// asked for right now.

/* SOFT PUBLISH POST STATUS */

// 1. REGISTER CUSTOM POST STATUS
add_action('init', 'rudr_custom_status_creation');
function rudr_custom_status_creation()
{
    register_post_status('soft_publish', array(
        'label' => 'Soft Published',
        'label_count' => _n_noop('Soft Published <span class="count">(%s)</span>', 'Soft Published <span class="count">(%s)</span>'),
        'public' => true,
        'show_in_admin_status_list' => true,
        'show_in_admin_all_list' => true,
    ));
}

// 2. ADD TO CLASSIC EDITOR DROPDOWN (shorthand_story only)
add_action('admin_footer-post.php', function () {
    global $post;
    if (!$post || $post->post_type !== 'shorthand_story') return;
    ?>
    <script>
        jQuery(function($) {
            // First, add the option to dropdown
            $('#post_status').append('<option value="soft_publish">Soft Published</option>');

            // Function to update UI elements
            function updateSoftPublishUI() {
                var status = $('#post_status').val();
                var $publishButton = $('#publish');

                if (status === 'soft_publish') {
                    $('.misc-pub-curtime').hide();
                    $publishButton.val('Update');
                    $publishButton.removeClass('button-primary').addClass('button-large');
                } else {
                    $('.misc-pub-curtime').show();
                    $publishButton.val('Publish');
                    $publishButton.removeClass('button-large').addClass('button-primary');
                }
            }

            // Set initial state for existing soft_publish posts
            <?php if ('soft_publish' === get_post_status()) : ?>
                $('#post-status-display').text('Soft Published');
                $('#post_status').val('soft_publish');
            <?php endif; ?>

            // Update UI based on current status
            updateSoftPublishUI();

            // Update UI when status changes
            $('#post_status').on('change', function() {
                if ($(this).val() === 'soft_publish') {
                    $('#post-status-display').text('Soft Published');
                }
                updateSoftPublishUI();
            });
        });
    </script>
<?php
});

add_action('admin_footer-post-new.php', function () {
    global $post;
    if (!$post || $post->post_type !== 'shorthand_story') return;
?>
    <script>
        jQuery(function($) {
            $('#post_status').append('<option value="soft_publish">Soft Published</option>');
        });
    </script>
<?php
});

// 3. ADD TO QUICK EDIT
add_action('admin_footer-edit.php', 'rudr_status_into_inline_edit');
function rudr_status_into_inline_edit()
{
?>
    <script>
        jQuery(function($) {
            $('select[name="_status"]').append('<option value="soft_publish">Soft Published</option>');
        });
    </script>
    <?php
}

// 4. DISPLAY STATUS LABEL IN POST LIST
add_filter('display_post_states', 'rudr_display_status_label');
function rudr_display_status_label($states)
{
    if ('soft_publish' === get_query_var('post_status')) {
        return $states;
    }

    if ('soft_publish' === get_post_status()) {
        $states[] = 'Soft Published';
    }

    return $states;
}

// ===================================================================
// 5. SCHEDULED PUBLISHING FUNCTIONALITY (REVISED AND CORRECTED)
// ===================================================================

// Add scheduled publish date meta box (No changes needed here)
function add_scheduled_publish_meta_box()
{
    add_meta_box(
        'scheduled_publish_box',
        'Scheduled Publish',
        'scheduled_publish_meta_box_callback',
        ['shorthand_story'],
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'add_scheduled_publish_meta_box');

// Meta box content (No changes needed here)
function scheduled_publish_meta_box_callback($post)
{
    wp_nonce_field('scheduled_publish_nonce', 'scheduled_publish_nonce');
    $scheduled_date = get_post_meta($post->ID, '_scheduled_publish_date', true);

    if ($post->post_status === 'soft_publish') {
    ?>
        <input type="hidden" name="keep_soft_publish" value="1">

        <div style="background: #f0f6fc; padding: 8px; margin-bottom: 10px; border-radius: 3px;">
            <strong>Soft Publish Scheduling</strong><br>
            <small>This schedules when your soft published post becomes fully published and appears in feeds.</small>
        </div>
        <label for="scheduled_publish_date">Auto-publish on:</label>
        <input type="datetime-local"
            id="scheduled_publish_date"
            name="scheduled_publish_date"
            value="<?php echo $scheduled_date ? date('Y-m-d\TH:i', strtotime($scheduled_date)) : ''; ?>">
        <p><small>Leave blank to publish manually later</small></p>
        <?php if ($scheduled_date): ?>
            <p><small>Currently scheduled for: <?php echo date('M j, Y \a\t g:i A', strtotime($scheduled_date)); ?></small></p>
        <?php endif; ?>
    <?php
    } else {
    ?>
        <div style="background: #fff2cc; padding: 8px; border-radius: 3px;">
            <strong>Note:</strong> Soft publish scheduling is only available when post status is set to "Soft Published".<br>
            <small>Use WordPress's native "Publish immediately" date picker for regular scheduled posts.</small>
        </div>
<?php
    }
}

// **REVISED** Save scheduled date with timezone and post-specific cron fixes
function save_scheduled_publish_meta($post_id)
{
    // Basic checks
    if (
        !isset($_POST['scheduled_publish_nonce']) ||
        !wp_verify_nonce($_POST['scheduled_publish_nonce'], 'scheduled_publish_nonce')
    ) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if ('shorthand_story' !== get_post_type($post_id)) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // **FIX**: Define a post-specific hook and arguments
    $hook = 'publish_specific_soft_post';
    $args = array('post_id' => $post_id);

    // Always clear the existing schedule for THIS post first
    wp_clear_scheduled_hook($hook, $args);

    if (isset($_POST['scheduled_publish_date']) && !empty($_POST['scheduled_publish_date'])) {
        $local_date_string = sanitize_text_field($_POST['scheduled_publish_date']);

        // --- NEW DEBUGGING LOGS ---
        error_log("Post {$post_id} - RAW date received from form: " . $local_date_string);
        // --- END DEBUGGING LOGS ---

        // Convert local datetime string to a UTC/GMT datetime string
        $gmt_date_string = get_gmt_from_date($local_date_string);

        // Create the correct UTC timestamp for scheduling
        $utc_timestamp = strtotime($gmt_date_string);

        // --- NEW DEBUGGING LOGS ---
        $current_utc_timestamp = time();
        error_log("Post {$post_id} - Converted to GMT: {$gmt_date_string} | Schedule Timestamp: {$utc_timestamp} | Current Timestamp: {$current_utc_timestamp}");
        // --- END DEBUGGING LOGS ---

        // Only schedule if the time is in the future
        if ($utc_timestamp > $current_utc_timestamp) {
            // Update post meta with the local date string for display purposes in the meta box
            update_post_meta($post_id, '_scheduled_publish_date', $local_date_string);

            // Schedule a UNIQUE event for this specific post
            wp_schedule_single_event($utc_timestamp, $hook, $args);

            error_log("Post {$post_id}: SUCCESS - Event scheduled for {$gmt_date_string} UTC.");
        } else {
            error_log("Post {$post_id}: FAILED - Did not schedule, date is in the past.");
        }
    } else {
        // If the date field is cleared, remove the meta and the schedule
        delete_post_meta($post_id, '_scheduled_publish_date');
        error_log("Post {$post_id}: Date cleared, unscheduled event.");
    }
}
add_action('save_post', 'save_scheduled_publish_meta');


// **REVISED** Cron function to publish a specific post
function publish_specific_post($post_id)
{
    error_log("Cron running for post {$post_id} at: " . current_time('Y-m-d H:i:s'));

    // Get the specific post
    $post = get_post($post_id);

    // Double-check that the post is still 'soft_publish' before changing it
    if ($post && $post->post_status === 'soft_publish') {

        // **FIX**: Get the scheduled date from post meta to ensure correct feed order
        $scheduled_local_date = get_post_meta($post_id, '_scheduled_publish_date', true);

        if ($scheduled_local_date) {
            // Format the date for WordPress's database fields
            $post_date_mysql     = date('Y-m-d H:i:s', strtotime($scheduled_local_date));
            $post_date_gmt_mysql = get_gmt_from_date($post_date_mysql);

            wp_update_post([
                'ID'            => $post_id,
                'post_status'   => 'publish',
                'post_date'     => $post_date_mysql,     // Set the publish date to the scheduled time
                'post_date_gmt' => $post_date_gmt_mysql, // Set the GMT publish date to the scheduled time
            ]);

            delete_post_meta($post_id, '_scheduled_publish_date');
            error_log("Successfully published post {$post_id} with scheduled date: {$post_date_mysql}.");
        } else {
            error_log("Could not publish post {$post_id}: _scheduled_publish_date meta was missing.");
        }
    } else {
        error_log("Could not publish post {$post_id}. It was either not found or its status was not 'soft_publish'.");
    }
}
// **FIX**: Add the action for the NEW, post-specific hook
add_action('publish_specific_soft_post', 'publish_specific_post', 10, 1);

// ===================================================================
// END OF REVISED SECTION
// ===================================================================


// 6. OPTIONAL: BULK ACTION TO PROMOTE POSTS TO PUBLISHED

// Add bulk action to promote soft_publish to publish
function add_publish_now_bulk_action($bulk_actions)
{
    $bulk_actions['publish_now'] = __('Publish Now');
    return $bulk_actions;
}
add_filter('bulk_actions-edit-shorthand_story', 'add_publish_now_bulk_action');

// Handle the bulk action
function handle_publish_now_bulk_action($redirect_to, $doaction, $post_ids)
{
    if ($doaction !== 'publish_now') {
        return $redirect_to;
    }

    foreach ($post_ids as $post_id) {
        wp_update_post(array(
            'ID' => $post_id,
            'post_status' => 'publish'
        ));
        // Remove scheduled date if it exists
        delete_post_meta($post_id, '_scheduled_publish_date');
    }

    $redirect_to = add_query_arg('published_now', count($post_ids), $redirect_to);
    return $redirect_to;
}
add_filter('handle_bulk_actions-edit-shorthand_story', 'handle_publish_now_bulk_action', 10, 3);

// Show admin notice after bulk publish
function publish_now_admin_notice()
{
    if (!empty($_REQUEST['published_now'])) {
        $count = intval($_REQUEST['published_now']);
        printf('<div id="message" class="updated fade"><p>' .
            _n('Published %s post.', 'Published %s posts.', $count, 'your-text-domain') .
            '</p></div>', $count);
    }
}
add_action('admin_notices', 'publish_now_admin_notice');


// Protect soft_publish status from being changed to publish (but allow cron)
function protect_soft_publish_status($data, $postarr)
{
    // Don't interfere with cron jobs or programmatic updates
    if (defined('DOING_CRON') && DOING_CRON) {
        return $data;
    }

    // Don't interfere if this is a programmatic wp_update_post call
    if (!isset($_POST['post_status'])) {
        return $data;
    }

    // If the original post was soft_publish and no explicit status change requested
    if (isset($postarr['ID']) && $postarr['ID']) {
        $original_post = get_post($postarr['ID']);

        if ($original_post && $original_post->post_status === 'soft_publish') {
            // If post_status is explicitly set to soft_publish, keep it
            if (isset($postarr['post_status']) && $postarr['post_status'] === 'soft_publish') {
                $data['post_status'] = 'soft_publish';
            }
            // If it's trying to change to publish via admin form, keep soft_publish
            elseif (isset($_POST['post_status']) && $_POST['post_status'] === 'publish') {
                $data['post_status'] = 'soft_publish';
            }
        }
    }

    return $data;
}
add_filter('wp_insert_post_data', 'protect_soft_publish_status', 10, 2);

/**
 * Add a comprehensive set of favicon links to the theme head.
 * This function is tailored to a specific list of icon files and includes
 * the browserconfig.xml for Microsoft tiles.
 */
function my_theme_add_favicon_final()
{
    $site_name = get_bloginfo('name');

    // Define the path to your favicons folder
    $favicon_path = get_template_directory_uri() . '/assets/images/favicons/';

    // -- Standard & PNG Favicons --
    echo '';
    echo '<link rel="icon" type="image/x-icon" href="' . esc_url($favicon_path . 'favicon.ico') . '">';
    // echo '<link rel="icon" type="image/png" sizes="16x16" href="' . esc_url($favicon_path . 'favicon-16x16.png') . '">';
    // echo '<link rel="icon" type="image/png" sizes="32x32" href="' . esc_url($favicon_path . 'favicon-32x32.png') . '">';
    echo '<link rel="icon" type="image/png" sizes="96x96" href="' . esc_url($favicon_path . 'favicon-96x96.png') . '">';
    echo '<link rel="icon" type="image/png" sizes="128x128" href="' . esc_url($favicon_path . 'favicon-128.png') . '">';
    echo '<link rel="icon" type="image/png" sizes="196x196" href="' . esc_url($favicon_path . 'favicon-196x196.png') . '">';

    // -- Apple Touch Icons --
    echo '';
    echo '<link rel="apple-touch-icon" sizes="57x57" href="' . esc_url($favicon_path . 'apple-touch-icon-57x57.png') . '">';
    echo '<link rel="apple-touch-icon" sizes="60x60" href="' . esc_url($favicon_path . 'apple-touch-icon-60x60.png') . '">';
    echo '<link rel="apple-touch-icon" sizes="72x72" href="' . esc_url($favicon_path . 'apple-touch-icon-72x72.png') . '">';
    echo '<link rel="apple-touch-icon" sizes="76x76" href="' . esc_url($favicon_path . 'apple-touch-icon-76x76.png') . '">';
    echo '<link rel="apple-touch-icon" sizes="114x114" href="' . esc_url($favicon_path . 'apple-touch-icon-114x114.png') . '">';
    echo '<link rel="apple-touch-icon" sizes="120x120" href="' . esc_url($favicon_path . 'apple-touch-icon-120x120.png') . '">';
    echo '<link rel="apple-touch-icon" sizes="144x144" href="' . esc_url($favicon_path . 'apple-touch-icon-144x144.png') . '">';
    echo '<link rel="apple-touch-icon" sizes="152x152" href="' . esc_url($favicon_path . 'apple-touch-icon-152x152.png') . '">';

    // -- Microsoft Tile Icons & Theme Color --
    echo '';
    echo '<meta name="msapplication-TileColor" content="#004E60">';
    echo '<meta name="msapplication-TileImage" content="' . esc_url($favicon_path . 'mstile-144x144.png') . '">';
    echo '<meta name="theme-color" content="#004E60">';

    // Link to browserconfig.xml
    echo '<meta name="msapplication-config" content="' . esc_url($favicon_path . 'ieconfig.xml') . '">';

    // App name for when saved to home screen on mobile devices
    echo '<meta name="application-name" content="' . esc_attr($site_name) . '">';
    echo '<meta name="apple-mobile-web-app-title" content="' . esc_attr($site_name) . '">';
}

// Hook the function into the wp_head action
add_action('wp_head', 'my_theme_add_favicon_final');
