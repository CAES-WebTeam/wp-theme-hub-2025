/**
 * Reveal Block Frontend JavaScript - Sticky Approach
 * 
 * CSS sticky positioning handles the main behavior:
 * - Background stays pinned while content scrolls through
 * - Background scrolls away naturally when content is done
 * 
 * This JS handles:
 * - Content opacity fade (optional smoothing)
 * - Any future transition effects between frames
 */

( function () {
	'use strict';

	function initRevealBlock( block ) {
		const sections = block.querySelectorAll( '.reveal-frame-section' );
		const contents = block.querySelectorAll( '.reveal-frame-content' );

		if ( sections.length === 0 ) {
			return;
		}

		let ticking = false;

		/**
		 * Calculate content opacity based on position in viewport
		 * Fades in from bottom, fades out at top
		 */
		function getContentOpacity( element ) {
			const rect = element.getBoundingClientRect();
			const viewportHeight = window.innerHeight;
			
			// Fade zones
			const fadeInStart = viewportHeight * 0.85;  // Start fading in when top is at 85% down
			const fadeInEnd = viewportHeight * 0.6;    // Fully visible when top is at 60% down
			const fadeOutStart = viewportHeight * 0.2; // Start fading out when top is at 20% down
			const fadeOutEnd = 0;                       // Fully faded when top hits 0
			
			const elementTop = rect.top;
			
			// Below viewport
			if ( elementTop > viewportHeight ) {
				return 0;
			}
			
			// Above viewport
			if ( rect.bottom < 0 ) {
				return 0;
			}
			
			// Fading in (entering from bottom)
			if ( elementTop > fadeInEnd ) {
				if ( elementTop > fadeInStart ) {
					return 0;
				}
				return 1 - ( ( elementTop - fadeInEnd ) / ( fadeInStart - fadeInEnd ) );
			}
			
			// Fading out (exiting at top)
			if ( elementTop < fadeOutStart ) {
				if ( elementTop < fadeOutEnd ) {
					return Math.max( 0, rect.bottom / ( viewportHeight * 0.3 ) );
				}
				return elementTop / fadeOutStart;
			}
			
			// Fully visible
			return 1;
		}

		/**
		 * Update on scroll
		 */
		function updateOnScroll() {
			contents.forEach( ( content ) => {
				const opacity = getContentOpacity( content );
				content.style.opacity = opacity;
			} );

			ticking = false;
		}

		function onScroll() {
			if ( ! ticking ) {
				window.requestAnimationFrame( updateOnScroll );
				ticking = true;
			}
		}

		// Initialize - start with content visible (CSS handles initial state)
		contents.forEach( ( content ) => {
			content.style.transition = 'none';
		} );

		// Set up scroll listener
		window.addEventListener( 'scroll', onScroll, { passive: true } );
		window.addEventListener( 'resize', updateOnScroll );
		
		// Initial update
		updateOnScroll();

		// Cleanup
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