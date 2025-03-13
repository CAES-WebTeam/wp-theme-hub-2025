<?php
// Register 'Events' Custom Post Type
add_action( 'init', function() {
	register_post_type( 'events', array(
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
			0 => 'title', 1 => 'author', 2 => 'thumbnail'
		),
		'delete_with_user' => false,
	) );
} );

// Register 'Publications' Custom Post Type
add_action( 'init', function() {
	register_post_type( 'publications', array(
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
			0 => 'title', 1 => 'author', 2 => 'editor', 3 => 'tags', 4 => 'thumbnail'
		),
		'delete_with_user' => false,
	) );
} );


// Register 'Series' Taxonomy
add_action( 'init', function() {
	register_taxonomy( 'series', array( 'events', 'publications' ),
		array(
		'labels' => array(
			'name' => 'Series',
			'singular_name' => 'Series',
			'menu_name' => 'Series',
			'all_items' => 'All Series',
			'edit_item' => 'Edit Series',
			'view_item' => 'View Series',
			'update_item' => 'Update Series',
			'add_new_item' => 'Add New Series',
			'new_item_name' => 'New Series Name',
			'search_items' => 'Search Series',
			'popular_items' => 'Popular Series',
			'separate_items_with_commas' => 'Separate series with commas',
			'add_or_remove_items' => 'Add or remove series',
			'choose_from_most_used' => 'Choose from the most used series',
			'not_found' => 'No series found',
			'no_terms' => 'No series',
			'items_list_navigation' => 'Series list navigation',
			'items_list' => 'Series list',
			'back_to_items' => '← Go to series',
			'item_link' => 'Series Link',
			'item_link_description' => 'A link to a series',
		),
		'public' 		=> true,
		'show_in_menu'	=> true,
		'show_in_rest' 	=> true,
		'query_var'		=> true,
		'rewrite' 		=> array( 'slug' => 'event-series' ),
	) );
} );


// Register the 'Keywords' taxonomy for the Publications
function register_keywords_taxonomy() {
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
	register_taxonomy('keywords', array('publications'), $args);
}
add_action('init', 'register_keywords_taxonomy');
