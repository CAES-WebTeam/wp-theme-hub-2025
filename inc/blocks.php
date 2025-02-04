<?php
/** START BLOCKS FOR THEME **/

// Register Blocks
function caes_hub_block_init()
{
	// Register Header Brand block
	register_block_type(get_template_directory() . '/blocks/build/blocks/header-brand' );

	// Register Content Brand block
	register_block_type(get_template_directory() . '/blocks/build/blocks/content-brand' );

	// Register UGA Footer
	register_block_type(get_template_directory(). '/blocks/build/blocks/uga-footer' );

	// Register Actions
	register_block_type(get_template_directory() . '/blocks/build/blocks/action-print' );
	register_block_type(get_template_directory() . '/blocks/build/blocks/action-share' );
	register_block_type(get_template_directory() . '/blocks/build/blocks/action-save' );
	register_block_type(get_template_directory() . '/blocks/build/blocks/action-ics' );

	// Register Post Filter
	register_block_type(get_template_directory() . '/blocks/build/blocks/post-filter' );

	// Register Time To Read
	register_block_type(get_template_directory() . '/blocks/build/blocks/time-to-read' );

	// Register Hand Picked Post
	register_block_type(get_template_directory() . '/blocks/build/blocks/hand-picked-post' );

	// Register Carousel
	register_block_type(get_template_directory() . '/blocks/build/blocks/carousel' );

	// Register Table of Contents
	register_block_type(get_template_directory() . '/blocks/build/blocks/toc' );

	// Register Event Blocks
	register_block_type(get_template_directory() . '/blocks/build/blocks/event-form');
	register_block_type(get_template_directory() . '/blocks/build/blocks/event-block');
	register_block_type(get_template_directory() . '/blocks/build/blocks/event-featured');
	register_block_type(get_template_directory() . '/blocks/build/blocks/event-details-description');
	register_block_type(get_template_directory() . '/blocks/build/blocks/event-details-date');
	register_block_type(get_template_directory() . '/blocks/build/blocks/event-details-register');
	register_block_type(get_template_directory() . '/blocks/build/blocks/event-details-date-time');
	register_block_type(get_template_directory() . '/blocks/build/blocks/event-details-location');
	register_block_type(get_template_directory() . '/blocks/build/blocks/event-details-location-snippet');
	register_block_type(get_template_directory() . '/blocks/build/blocks/event-details-online-location');
	register_block_type(get_template_directory() . '/blocks/build/blocks/event-details-cost');
	register_block_type(get_template_directory() . '/blocks/build/blocks/event-details-parking');
	register_block_type(get_template_directory() . '/blocks/build/blocks/event-details-documents');
	register_block_type(get_template_directory() . '/blocks/build/blocks/event-details-contact');
	register_block_type(get_template_directory() . '/blocks/build/blocks/event-details-featured-image');
	register_block_type(get_template_directory() . '/blocks/build/blocks/event-details-gallery');
}
add_action('init', 'caes_hub_block_init');
