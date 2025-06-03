<?php
// Register 'Events' Custom Post Type
add_action('init', function () {
	register_post_type('events', array(
		'labels' => array(
			'name' => 'Events',
			'singular_name' => 'Events',
			'menu_name' => 'Events',
			'all_items' => 'All Events',
			'edit_item' => 'Edit Events',
			'view_item' => 'View Events',
			'view_items' => 'View Events',
			'add_new_item' => 'Add New Events',
			'add_new' => 'Add New Events',
			'new_item' => 'New Events',
			'parent_item_colon' => 'Parent Events:',
			'search_items' => 'Search Events',
			'not_found' => 'No events found',
			'not_found_in_trash' => 'No events found in Trash',
			'archives' => 'Events Archives',
			'attributes' => 'Events Attributes',
			'insert_into_item' => 'Insert into events',
			'uploaded_to_this_item' => 'Uploaded to this events',
			'filter_items_list' => 'Filter events list',
			'filter_by_date' => 'Filter events by date',
			'items_list_navigation' => 'Events list navigation',
			'items_list' => 'Events list',
			'item_published' => 'Events published.',
			'item_published_privately' => 'Events published privately.',
			'item_reverted_to_draft' => 'Events reverted to draft.',
			'item_scheduled' => 'Events scheduled.',
			'item_updated' => 'Events updated.',
			'item_link' => 'Events Link',
			'item_link_description' => 'A link to a events.',
		),
		'public' => true,
		'show_in_rest' => true,
		'menu_icon' => 'dashicons-admin-post',
		'supports' => array(
			0 => 'title',
			1 => 'author',
			2 => 'thumbnail'
		),
		'delete_with_user' => false,
	));
});

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
		'supports' => array(
			0 => 'title',
			1 => 'author',
			2 => 'editor',
			3 => 'tags',
			4 => 'thumbnail'
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



// Register the 'Keywords' taxonomy for the Publications
function register_keywords_taxonomy()
{
	$labels = array(
		'name'              => _x('Keywords', 'taxonomy general name'),
		'singular_name'     => _x('Keyword', 'taxonomy singular name'),
		'search_items'      => __('Search Keywords'),
		'all_items'         => __('All Keywords'),
		'parent_item'       => __('Parent Keyword'),
		'parent_item_colon' => __('Parent Keyword:'),
		'edit_item'         => __('Edit Keyword'),
		'update_item'       => __('Update Keyword'),
		'add_new_item'      => __('Add New Keyword'),
		'new_item_name'     => __('New Keyword Name'),
		'menu_name'         => __('Keywords'),
	);

	$args = array(
		'hierarchical'      => false,
		'labels'            => $labels,
		'show_ui'           => true,
		'show_admin_column' => true,
		'query_var'         => true,
		'show_in_rest'      => true,
		'rewrite'           => array('slug' => 'keyword'),
	);

	// Register the taxonomy and associate it with the 'publications' post type
	register_taxonomy('keywords', array('post', 'publications','shorthand_story'), $args);
}
add_action('init', 'register_keywords_taxonomy');


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