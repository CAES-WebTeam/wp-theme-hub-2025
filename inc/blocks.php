<?php
/** START BLOCKS FOR THEME **/

// Register Blocks
function caes_hub_block_init()
{

	// Register breadcrumbs block
	register_block_type(get_template_directory() . '/blocks/build/blocks/breadcrumbs' );
	
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
	register_block_type(get_template_directory() . '/blocks/build/blocks/action-pdf' );

	// Register Post Filter
	register_block_type(get_template_directory() . '/blocks/build/blocks/post-filter' );

	// Register Time To Read
	register_block_type(get_template_directory() . '/blocks/build/blocks/time-to-read' );

	// Register Hand Picked Post
	register_block_type(get_template_directory() . '/blocks/build/blocks/hand-picked-post' );

	// Register Primary Topic
	register_block_type(get_template_directory() . '/blocks/build/blocks/primary-topic' );

	// Register External Publisher
	register_block_type(get_template_directory() . '/blocks/build/blocks/external-publisher' );

	// Register Flip Card
	register_block_type(get_template_directory() . '/blocks/build/blocks/flip-card' );

	// Lightbox Gallery
	register_block_type(get_template_directory() . '/blocks/build/blocks/lightbox-gallery' );

	// Legacy Gallery
	register_block_type(get_template_directory() . '/blocks/build/blocks/legacy-gallery' );

	// Register Carousel
	register_block_type(get_template_directory() . '/blocks/build/blocks/carousel' );
	register_block_type(get_template_directory() . '/blocks/build/blocks/carousel-2' );

	// Register Table of Contents
	register_block_type(get_template_directory() . '/blocks/build/blocks/toc-new' );

	// Register Event Blocks
	register_block_type(get_template_directory() . '/blocks/build/blocks/event-form');
	register_block_type(get_template_directory() . '/blocks/build/blocks/event-block');
	register_block_type(get_template_directory() . '/blocks/build/blocks/event-details-description');
	register_block_type(get_template_directory() . '/blocks/build/blocks/event-details-register');
	register_block_type(get_template_directory() . '/blocks/build/blocks/event-details-date-time');
	register_block_type(get_template_directory() . '/blocks/build/blocks/event-details-location');
	register_block_type(get_template_directory() . '/blocks/build/blocks/event-details-online-location');
	register_block_type(get_template_directory() . '/blocks/build/blocks/event-details-cost');
	register_block_type(get_template_directory() . '/blocks/build/blocks/event-details-parking');
	register_block_type(get_template_directory() . '/blocks/build/blocks/event-details-documents');
	register_block_type(get_template_directory() . '/blocks/build/blocks/event-details-contact');
	register_block_type(get_template_directory() . '/blocks/build/blocks/event-details-featured-image');

	// Register Publication Blocks
	register_block_type(get_template_directory() . '/blocks/build/blocks/expert-mark');
	register_block_type(get_template_directory() . '/blocks/build/blocks/pub-details-authors');
	register_block_type(get_template_directory() . '/blocks/build/blocks/pub-details-history');
	register_block_type(get_template_directory() . '/blocks/build/blocks/pub-details-number');
	register_block_type(get_template_directory() . '/blocks/build/blocks/pub-details-resources');
	register_block_type(get_template_directory() . '/blocks/build/blocks/pub-details-status');
	register_block_type(get_template_directory() . '/blocks/build/blocks/pub-details-summary');
	register_block_type(get_template_directory() . '/blocks/build/blocks/pub-details-translation-link');
	register_block_type(get_template_directory() . '/blocks/build/blocks/pub-details-type');

	// Register User Blocks
	register_block_type(get_template_directory() . '/blocks/build/blocks/user-bio');
	register_block_type(get_template_directory() . '/blocks/build/blocks/user-feed');
	register_block_type(get_template_directory() . '/blocks/build/blocks/user-department');
	register_block_type(get_template_directory() . '/blocks/build/blocks/user-name');
	register_block_type(get_template_directory() . '/blocks/build/blocks/user-position');
	register_block_type(get_template_directory() . '/blocks/build/blocks/user-email');
	register_block_type(get_template_directory() . '/blocks/build/blocks/user-phone');
	register_block_type(get_template_directory() . '/blocks/build/blocks/user-image');

	// Navigation Blocks
	register_block_type(get_template_directory() . '/blocks/build/blocks/nav-flyout');
	register_block_type(get_template_directory() . '/blocks/build/blocks/nav-item');
	register_block_type(get_template_directory() . '/blocks/build/blocks/nav-container');
	register_block_type(get_template_directory() . '/blocks/build/blocks/mobile-container');

	// Relevanssi Search Block
	register_block_type(get_template_directory() . '/blocks/build/blocks/relevanssi-search');

}
add_action('init', 'caes_hub_block_init');