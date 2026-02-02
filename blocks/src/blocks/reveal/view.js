/**
 * Reveal Block Frontend JavaScript - Sticky Approach v5
 * 
 * CSS sticky does the heavy lifting. This JS just:
 * 1. Adds smooth opacity fade to content as it enters/exits
 * 2. (Future: transition effects between frames)
 */

( function () {
	'use strict';

	function initRevealBlock( block ) {
		const contents = block.querySelectorAll( '.reveal-frame-content' );

		if ( contents.length === 0 ) {
			return;
		}

		let ticking = false;

		/**
		 * Calculate content opacity based on its position in viewport
		 */
		function getContentOpacity( element ) {
			const rect = element.getBoundingClientRect();
			const viewportHeight = window.innerHeight;
			
			// Get the actual content inside (not the padding)
			const contentChildren = element.children;
			if ( contentChildren.length === 0 ) {
				return 1;
			}
			
			// Find the bounding box of actual content
			let contentTop = Infinity;
			let contentBottom = -Infinity;
			
			for ( let child of contentChildren ) {
				const childRect = child.getBoundingClientRect();
				if ( childRect.top < contentTop ) contentTop = childRect.top;
				if ( childRect.bottom > contentBottom ) contentBottom = childRect.bottom;
			}
			
			// If we couldn't find content, use element bounds
			if ( contentTop === Infinity ) {
				contentTop = rect.top;
				contentBottom = rect.bottom;
			}
			
			const fadeZone = viewportHeight * 0.2; // 20% of viewport for fade
			
			// Content fully below viewport
			if ( contentTop >= viewportHeight ) {
				return 0;
			}
			
			// Content fully above viewport  
			if ( contentBottom <= 0 ) {
				return 0;
			}
			
			// Fading in from bottom
			if ( contentTop > viewportHeight - fadeZone ) {
				return ( viewportHeight - contentTop ) / fadeZone;
			}
			
			// Fading out at top
			if ( contentBottom < fadeZone ) {
				return contentBottom / fadeZone;
			}
			
			// Fully visible
			return 1;
		}

		function updateOnScroll() {
			contents.forEach( ( content ) => {
				const opacity = getContentOpacity( content );
				content.style.opacity = Math.max( 0, Math.min( 1, opacity ) );
			} );

			ticking = false;
		}

		function onScroll() {
			if ( ! ticking ) {
				window.requestAnimationFrame( updateOnScroll );
				ticking = true;
			}
		}

		// Initialize
		contents.forEach( ( content ) => {
			content.style.transition = 'none';
		} );

		window.addEventListener( 'scroll', onScroll, { passive: true } );
		window.addEventListener( 'resize', updateOnScroll );
		
		updateOnScroll();

		block._revealCleanup = function () {
			window.removeEventListener( 'scroll', onScroll );
			window.removeEventListener( 'resize', updateOnScroll );
		};
	}

	function initAllBlocks() {
		document.querySelectorAll( '.caes-reveal' ).forEach( initRevealBlock );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initAllBlocks );
	} else {
		initAllBlocks();
	}
} )();