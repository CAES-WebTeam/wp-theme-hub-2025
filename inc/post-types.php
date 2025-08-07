<?php
// Register 'Events' Custom Post Type
add_action('init', function () {
    register_post_type('events', array(
        'labels' => array(
            'name' => 'Events',
            'singular_name' => 'Events',
            'menu_name' => 'Events',
            'all_items' => 'All Events',
            'edit_item' => 'Edit Event',
            'view_item' => 'View Event',
            'view_items' => 'View Events',
            'add_new_item' => 'Add New Event',
            'add_new' => 'Add New Event',
            'new_item' => 'New Event',
            'parent_item_colon' => 'Parent Event:',
            'search_items' => 'Search Events',
            'not_found' => 'No events found',
            'not_found_in_trash' => 'No events found in Trash',
            'archives' => 'Event Archives',
            'attributes' => 'Event Attributes',
            'insert_into_item' => 'Insert into event',
            'uploaded_to_this_item' => 'Uploaded to this event',
            'filter_items_list' => 'Filter events list',
            'filter_by_date' => 'Filter events by date',
            'items_list_navigation' => 'Events list navigation',
            'items_list' => 'Events list',
            'item_published' => 'Event published.',
            'item_published_privately' => 'Event published privately.',
            'item_reverted_to_draft' => 'Event reverted to draft.',
            'item_scheduled' => 'Event scheduled.',
            'item_updated' => 'Event updated.',
            'item_link' => 'Event Link',
            'item_link_description' => 'A link to an event.',
        ),
        'public' => true,
        'show_in_rest' => true,
        'menu_icon' => 'dashicons-admin-post',
        'rewrite' => array('slug' => 'events', 'with_front' => false),
        'supports' => array(
            0 => 'title',
            1 => 'author',
            2 => 'thumbnail',
            3 => 'revisions'
        ),
        'delete_with_user' => false,
        'capability_type'    => 'event',
        'map_meta_cap'       => true,
        'capabilities' => array(
            'edit_post'          => 'edit_event',
            'read_post'          => 'read_event',
            'delete_post'        => 'delete_event',
            'edit_posts'         => 'edit_events',
            'edit_others_posts'  => 'edit_others_events',
            'publish_posts'      => 'publish_events',
            'read_private_posts' => 'read_private_events',
            'delete_posts'       => 'delete_events',
            'delete_private_posts' => 'delete_private_events',
            'delete_published_posts' => 'delete_published_events',
            'delete_others_posts' => 'delete_others_events',
            'edit_private_posts' => 'edit_private_events',
            'edit_published_posts' => 'edit_published_events',
        ),
    ));
});

// Add this temporarily to see all post types
add_action('init', function() {
    if (isset($_GET['debug_pt'])) {
        $post_types = get_post_types(['public' => true], 'objects');
        echo '<pre>';
        foreach ($post_types as $pt) {
            if ($pt->name == 'events') {
                echo "Post Type: " . $pt->name . "\n";
                echo "Rewrite slug: " . print_r($pt->rewrite, true) . "\n\n";
            }
        }
        echo '</pre>';
        exit;
    }
}, 99);

// Register 'Publications' Custom Post Type
add_action('init', function () {
	register_post_type('publications', array(
		'labels' => array(
			'name' => 'Publications',
			'singular_name' => 'Publication',
			'menu_name' => 'Publications',
			'all_items' => 'All Publications',
			'edit_item' => 'Edit Publication',
			'view_item' => 'View Publication',
			'view_items' => 'View Publications',
			'add_new_item' => 'Add New Publication',
			'add_new' => 'Add New Publication',
			'new_item' => 'New Publication',
			'parent_item_colon' => 'Parent Publications:',
			'search_items' => 'Search Publications',
			'not_found' => 'No publications found',
			'not_found_in_trash' => 'No publications found in Trash',
			'archives' => 'Publications Archives',
			'attributes' => 'Publications Attributes',
			'insert_into_item' => 'Insert into publications',
			'uploaded_to_this_item' => 'Uploaded to this publications',
			'filter_items_list' => 'Filter publications list',
			'filter_by_date' => 'Filter publications by date',
			'items_list_navigation' => 'Publications list navigation',
			'items_list' => 'Publications list',
			'item_published' => 'Publications published.',
			'item_published_privately' => 'Publications published privately.',
			'item_reverted_to_draft' => 'Publications reverted to draft.',
			'item_scheduled' => 'Publications scheduled.',
			'item_updated' => 'Publications updated.',
			'item_link' => 'Publications Link',
			'item_link_description' => 'A link to a publications.',
		),
		'public' => true,
		'show_in_rest' => true,
		'menu_icon' => 'dashicons-admin-post',
        'rewrite'     => array('slug' => 'publications', 'with_front' => false),
		'supports' => array(
			0 => 'title',
			1 => 'author',
			2 => 'editor',
			3 => 'tags',
			4 => 'thumbnail',
            5 => 'revisions'
		),
		'delete_with_user' => false
	));
});


// Register 'Series' Taxonomies for Events and Publications
// This code registers two custom taxonomies: 'event_series' and 'publication_series'.
add_action('init', function () {
	// Register Series taxonomy for Events
	register_taxonomy('event_series', array('events'), array(
		'labels' => array(
			'name' => 'Event Series',
			'singular_name' => 'Event Series',
			'menu_name' => 'Event Series',
			'all_items' => 'All Event Series',
			'edit_item' => 'Edit Event Series',
			'view_item' => 'View Event Series',
			'update_item' => 'Update Event Series',
			'add_new_item' => 'Add New Event Series',
			'new_item_name' => 'New Event Series Name',
			'search_items' => 'Search Event Series',
			'popular_items' => 'Popular Event Series',
			'separate_items_with_commas' => 'Separate event series with commas',
			'add_or_remove_items' => 'Add or remove event series',
			'choose_from_most_used' => 'Choose from the most used event series',
			'not_found' => 'No event series found',
			'no_terms' => 'No event series',
			'items_list_navigation' => 'Event series list navigation',
			'items_list' => 'Event series list',
			'back_to_items' => '← Go to event series',
			'item_link' => 'Event Series Link',
			'item_link_description' => 'A link to an event series',
		),
		'public' 		=> true,
		'show_in_menu'	=> true,
		'show_in_rest' 	=> true,
		'query_var'		=> true,
		'rewrite' => array(
            'slug' => 'events/series',
            'with_front' => false,
            'hierarchical' => true
        )
	));

	// Register Series taxonomy for Publications
	register_taxonomy('publication_series', array('publications'), array(
		'labels' => array(
			'name' => 'Publication Series',
			'singular_name' => 'Publication Series',
			'menu_name' => 'Publication Series',
			'all_items' => 'All Publication Series',
			'edit_item' => 'Edit Publication Series',
			'view_item' => 'View Publication Series',
			'update_item' => 'Update Publication Series',
			'add_new_item' => 'Add New Publication Series',
			'new_item_name' => 'New Publication Series Name',
			'search_items' => 'Search Publication Series',
			'popular_items' => 'Popular Publication Series',
			'separate_items_with_commas' => 'Separate publication series with commas',
			'add_or_remove_items' => 'Add or remove publication series',
			'choose_from_most_used' => 'Choose from the most used publication series',
			'not_found' => 'No publication series found',
			'no_terms' => 'No publication series',
			'items_list_navigation' => 'Publication series list navigation',
			'items_list' => 'Publication series list',
			'back_to_items' => '← Go to publication series',
			'item_link' => 'Publication Series Link',
			'item_link_description' => 'A link to a publication series',
		),
		'public' 		=> true,
		'show_in_menu'	=> true,
		'show_in_rest' 	=> true,
		'query_var'		=> true,
		'rewrite' => array(
            'slug' => 'publications/series',
            'with_front' => false,
            'hierarchical' => true
        )
	));
});

// Register 'CAES Departments' taxonomy for Events
add_action('init', function () {
    register_taxonomy('event_caes_departments', array('events'), array(
        'labels' => array(
            'name' => 'CAES Departments',
            'singular_name' => 'CAES Department',
            'menu_name' => 'CAES Departments',
            'all_items' => 'All CAES Departments',
            'edit_item' => 'Edit CAES Department',
            'view_item' => 'View CAES Department',
            'update_item' => 'Update CAES Department',
            'add_new_item' => 'Add New CAES Department',
            'new_item_name' => 'New CAES Department Name',
            'search_items' => 'Search CAES Departments',
            'not_found' => 'No CAES departments found',
            'no_terms' => 'No CAES departments',
            'back_to_items' => '← Go to CAES departments',
            'item_link' => 'CAES Department Link',
            'item_link_description' => 'A link to a CAES department',
        ),
        'public' => true,
        'show_in_rest' => true,
        'query_var' => true,
        'hierarchical' => false,
        'show_admin_column' => false, // Hidden since we're using ACF
        'rewrite' => array(
            'slug' => 'events/caes-departments',
            'with_front' => false
        )
    ));
});

// Hide default taxonomy metaboxes for events since we're using ACF
function hide_event_taxonomy_metaboxes() {
    remove_meta_box('tagsdiv-event_series', 'events', 'side');
    remove_meta_box('tagsdiv-event_caes_departments', 'events', 'side');
}
add_action('admin_menu', 'hide_event_taxonomy_metaboxes');

// Register the shared 'Topics' taxonomy for posts, publications, and shorthand stories
function register_topics_taxonomy()
{
    $labels = array(
        'name'              => _x('Topics', 'taxonomy general name'),
        'singular_name'     => _x('Topic', 'taxonomy singular name'),
        'search_items'      => __('Search Topics'),
        'all_items'         => __('All Topics'),
        'parent_item'       => __('Parent Topic'),
        'parent_item_colon' => __('Parent Topic:'),
        'edit_item'         => __('Edit Topic'),
        'update_item'       => __('Update Topic'),
        'add_new_item'      => __('Add New Topic'),
        'new_item_name'     => __('New Topic Name'),
        'menu_name'         => __('Topics'),
    );

    $args = array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'show_in_rest'      => true,
        'rewrite'           => array('slug' => 'topic','with_front' => false), // Changed slug to 'topic'
    );

    // Register the taxonomy and associate it with the same post types
    register_taxonomy('topics', array('post', 'publications','shorthand_story'), $args); // Changed taxonomy name to 'topics'
}
add_action('init', 'register_topics_taxonomy'); // Changed function name in action hook

// Primary Topics ACF Field - specifies the primary topic for posts,
// publications, and shorthand stories, pulling from the 'topics' taxonomy.
function create_primary_topics_field() {
    if( function_exists('acf_add_local_field_group') ):
        acf_add_local_field_group(array(
            'key' => 'group_primary_topic', // Changed group key for consistency
            'title' => 'Primary Topics', // Changed title
            'fields' => array(
                array(
                    'key' => 'field_primary_topics', // Changed field key for consistency
                    'label' => 'Primary Topics', // Changed label
                    'name' => 'primary_topics', // Changed name
                    'type' => 'taxonomy',
                    'taxonomy' => 'topics', // Changed taxonomy to 'topics'
                    'field_type' => 'multi_select',
                    'allow_null' => 1,
                    'return_format' => 'object',
                    'multiple' => 1,
                )
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'post',
                    ),
                ),
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'publications',
                    ),
                ),
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'shorthand_story',
                    ),
                ),
            ),
            'position' => 'side',
            'menu_order' => 0,
            'style' => 'default',
        ));
    endif;
}
add_action('acf/init', 'create_primary_topics_field'); // Changed function name in action hook

// Disable Yoast SEO Primary Term metabox
add_filter( 'wpseo_primary_term_taxonomies', '__return_empty_array' );



// External Publisher Taxonomy for posts
function create_external_publisher_taxonomy_and_field() {
    // Register the taxonomy first
    $labels = array(
        'name'              => 'External Publishers',
        'singular_name'     => 'External Publisher',
        'search_items'      => 'Search External Publishers',
        'all_items'         => 'All External Publishers',
        'edit_item'         => 'Edit External Publisher',
        'update_item'       => 'Update External Publisher',
        'add_new_item'      => 'Add New External Publisher',
        'new_item_name'     => 'New External Publisher Name',
        'menu_name'         => 'External Publishers',
    );

    $args = array(
        'hierarchical'      => false,
        'labels'            => $labels,
        'show_ui'           => true, // Since you're using ACF
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'external-publisher'),
        'public'            => true,
        'publicly_queryable' => true,
        'show_in_rest'      => true,
        'rest_base'         => 'external_publisher', // Explicit REST base
    );

    register_taxonomy('external_publisher', array('post'), $args);
}
add_action('init', 'create_external_publisher_taxonomy_and_field');

// Hide the default WordPress taxonomy metabox since we're using ACF
function hide_external_publisher_metabox() {
    remove_meta_box('external_publisherdiv', 'post', 'side');
}
add_action('admin_menu', 'hide_external_publisher_metabox');

// Fix Query Loop filtering for external_publisher taxonomy
function fix_external_publisher_rest_query($args, $request) {
    if (isset($request['external_publisher'])) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'external_publisher',
                'field'    => 'term_id',
                'terms'    => $request['external_publisher'],
            ),
        );
    }
    return $args;
}
add_filter('rest_post_query', 'fix_external_publisher_rest_query', 10, 2);

// Add a random placeholder image if no featured image is set
add_filter( 'post_thumbnail_html', 'caes_ordered_placeholder_if_no_thumbnail', 10, 5 );
function caes_ordered_placeholder_if_no_thumbnail( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
    static $counter = 0;

    // 1) Skip main post on single pages:
    if (
        is_singular()
        && is_main_query()
        && in_the_loop()
        && $post_id === get_queried_object_id()
    ) {
        return $html;
    }

    // 2) If there’s already a featured image, don’t touch it:
    if ( has_post_thumbnail( $post_id ) ) {
        return $html;
    }

    // 3) Pick the next placeholder in order:
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
    $index = $counter % count( $placeholders );
    $file  = $placeholders[ $index ];
    $counter++;

    $url = get_template_directory_uri() . '/assets/images/' . $file;
    $alt = get_the_title( $post_id );

    return sprintf(
        '<img src="%s" alt="%s" class="wp-post-image" />',
        esc_url( $url ),
        esc_attr( $alt )
    );
}

// Replace placeholder for main background featured image
add_filter( 'render_block', 'replace_placeholder_for_main_bg_featured_image', 10, 2 );
function replace_placeholder_for_main_bg_featured_image( $block_content, $block ) {
    // Only target core/post-featured-image block
    if ( ! isset( $block['blockName'] ) || $block['blockName'] !== 'core/post-featured-image' ) {
        return $block_content;
    }

    // Check if block has the special class
    $class_attr = isset( $block['attrs']['className'] ) ? $block['attrs']['className'] : '';
    if ( strpos( $class_attr, 'caes-hub-main-bg-f-img' ) === false ) {
        // Class not present — leave as is
        return $block_content;
    }

    // Get the post ID from block context if present, fallback to queried object
    $post_id = isset( $block['context']['postId'] ) && intval( $block['context']['postId'] )
        ? intval( $block['context']['postId'] )
        : get_queried_object_id();

    // If post has featured image, leave as is
    if ( has_post_thumbnail( $post_id ) ) {
        return $block_content;
    }

    // Post does NOT have featured image and block has the class — insert placeholder
    // Define placeholder image URL (adjust path as needed)
    $placeholder_url = get_template_directory_uri() . '/assets/images/placeholder-bg-2-lake-herrick.jpg';

    // Use the post title as alt text
    $alt = get_the_title( $post_id );

    // Return a simple figure with placeholder image
    return sprintf(
        '<figure class="wp-block-post-featured-image caes-hub-main-bg-f-img"><img src="%s" alt="%s" class="wp-post-image" /></figure>',
        esc_url( $placeholder_url ),
        esc_attr( $alt )
    );
}
