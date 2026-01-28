/**
 * Reveal Block Frontend JavaScript
 *
 * Handles scroll-triggered frame transitions.
 * The CSS uses clip-path to handle the visual "window" effect,
 * so JS only needs to manage frame transitions based on scroll position.
 */

( function () {
	'use strict';

	// Check reduced motion preference
	const prefersReducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	/**
	 * Initialize a single Reveal block
	 *
	 * @param {HTMLElement} block The reveal block element
	 */
	function initRevealBlock( block ) {
		const frames = block.querySelectorAll( '.reveal-frame' );

		if ( frames.length === 0 ) {
			return;
		}

		const frameCount = frames.length;
		let currentFrameIndex = 0;
		let ticking = false;

		/**
		 * Get the exit transform for a direction (rewind - go back to where it entered from)
		 */
		function getExitTransform( direction ) {
			switch ( direction ) {
				case 'up':
					return 'translateY(100%)'; // Came from bottom, go back to bottom
				case 'down':
					return 'translateY(-100%)'; // Came from top, go back to top
				case 'left':
					return 'translateX(100%)'; // Came from right, go back to right
				case 'right':
					return 'translateX(-100%)'; // Came from left, go back to left
				default:
					return '';
			}
		}

		/**
		 * Get the enter transform for a direction
		 */
		function getEnterTransform( direction ) {
			switch ( direction ) {
				case 'up':
					return 'translateY(100%)'; // Enter from bottom
				case 'down':
					return 'translateY(-100%)'; // Enter from top
				case 'left':
					return 'translateX(100%)'; // Enter from right
				case 'right':
					return 'translateX(-100%)'; // Enter from left
				default:
					return '';
			}
		}

		/**
		 * Apply forward transition (scrolling down) - new frame slides in over old frame
		 */
		function applyForwardTransition( newFrame, oldFrame ) {
			const transitionType = newFrame.dataset.transitionType || 'fade';
			const transitionSpeed = parseInt( newFrame.dataset.transitionSpeed, 10 ) || 500;
			const duration = prefersReducedMotion ? 0 : transitionSpeed;
			const isDirectional = [ 'up', 'down', 'left', 'right' ].includes( transitionType );
			const initialTransform = getEnterTransform( transitionType );

			if ( isDirectional && initialTransform && ! prefersReducedMotion ) {
				// Keep old frame visible underneath
				if ( oldFrame ) {
					oldFrame.style.zIndex = '1';
					oldFrame.style.opacity = '1';
				}

				// Position new frame off-screen
				newFrame.style.transitionDuration = '0ms';
				newFrame.style.transform = initialTransform;
				newFrame.style.opacity = '1';
				newFrame.style.zIndex = '2';
				newFrame.offsetHeight; // Force reflow

				// Animate new frame in
				newFrame.style.transitionDuration = `${ duration }ms`;
				newFrame.classList.add( 'is-active' );
				newFrame.style.transform = 'translate(0, 0)';

				// Clean up old frame after animation
				if ( oldFrame ) {
					setTimeout( () => {
						oldFrame.classList.remove( 'is-active' );
						oldFrame.style.opacity = '0';
						oldFrame.style.zIndex = '';
						newFrame.style.zIndex = '';
					}, duration );
				}
			} else {
				// Fade transition
				newFrame.style.transitionDuration = `${ duration }ms`;
				newFrame.classList.add( 'is-active' );
				newFrame.style.opacity = '1';
				newFrame.style.transform = 'translate(0, 0)';

				if ( oldFrame ) {
					oldFrame.style.transitionDuration = `${ duration }ms`;
					oldFrame.classList.remove( 'is-active' );
					oldFrame.style.opacity = '0';
				}
			}
		}

		/**
		 * Apply reverse transition (scrolling up) - old frame slides out, revealing new frame underneath
		 */
		function applyReverseTransition( newFrame, oldFrame ) {
			const transitionType = oldFrame?.dataset.transitionType || 'fade';
			const transitionSpeed = parseInt( oldFrame?.dataset.transitionSpeed, 10 ) || 500;
			const duration = prefersReducedMotion ? 0 : transitionSpeed;
			const isDirectional = [ 'up', 'down', 'left', 'right' ].includes( transitionType );
			const exitTransform = getExitTransform( transitionType );

			if ( isDirectional && exitTransform && oldFrame && ! prefersReducedMotion ) {
				// Make new frame visible underneath immediately
				newFrame.style.transitionDuration = '0ms';
				newFrame.style.transform = 'translate(0, 0)';
				newFrame.style.opacity = '1';
				newFrame.style.zIndex = '1';
				newFrame.classList.add( 'is-active' );
				newFrame.offsetHeight; // Force reflow

				// Old frame slides out
				oldFrame.style.zIndex = '2';
				oldFrame.style.transitionDuration = `${ duration }ms`;
				oldFrame.style.transform = exitTransform;

				// Clean up after animation
				setTimeout( () => {
					oldFrame.classList.remove( 'is-active' );
					oldFrame.style.opacity = '0';
					oldFrame.style.transform = '';
					oldFrame.style.zIndex = '';
					newFrame.style.zIndex = '';
				}, duration );
			} else {
				// Fade transition
				newFrame.style.transitionDuration = `${ duration }ms`;
				newFrame.classList.add( 'is-active' );
				newFrame.style.opacity = '1';
				newFrame.style.transform = 'translate(0, 0)';

				if ( oldFrame ) {
					oldFrame.style.transitionDuration = `${ duration }ms`;
					oldFrame.classList.remove( 'is-active' );
					oldFrame.style.opacity = '0';
				}
			}
		}

		/**
		 * Update active frame based on scroll position
		 */
		function updateActiveFrame() {
			const blockRect = block.getBoundingClientRect();
			const viewportHeight = window.innerHeight;
			const blockTop = blockRect.top;
			const blockHeight = block.offsetHeight;

			// Calculate scroll progress through the block
			// Progress is 0 when block top is at viewport top
			// Progress is 1 when block bottom is at viewport bottom
			const scrollableDistance = blockHeight - viewportHeight;
			
			if ( scrollableDistance <= 0 ) {
				ticking = false;
				return;
			}

			// How far we've scrolled into the block (blockTop is negative when scrolled past top)
			const scrolledDistance = Math.max( 0, -blockTop );
			const scrollProgress = Math.min( 1, scrolledDistance / scrollableDistance );

			// Determine which frame should be active
			const newFrameIndex = Math.min(
				frameCount - 1,
				Math.floor( scrollProgress * frameCount )
			);

			// Update frames if index changed
			if ( newFrameIndex !== currentFrameIndex ) {
				const oldFrame = frames[ currentFrameIndex ];
				const newFrame = frames[ newFrameIndex ];
				const isScrollingDown = newFrameIndex > currentFrameIndex;
				
				// Hide any frames that aren't involved in the transition
				frames.forEach( ( frame, index ) => {
					if ( index !== newFrameIndex && index !== currentFrameIndex ) {
						frame.classList.remove( 'is-active' );
						frame.style.opacity = '0';
						frame.style.zIndex = '';
					}
				} );

				// Apply appropriate transition based on scroll direction
				if ( isScrollingDown ) {
					applyForwardTransition( newFrame, oldFrame );
				} else {
					applyReverseTransition( newFrame, oldFrame );
				}
				
				currentFrameIndex = newFrameIndex;
			}

			ticking = false;
		}

		/**
		 * Handle scroll events with requestAnimationFrame throttling
		 */
		function onScroll() {
			if ( ! ticking ) {
				window.requestAnimationFrame( updateActiveFrame );
				ticking = true;
			}
		}

		// Add scroll listener
		window.addEventListener( 'scroll', onScroll, { passive: true } );

		// Initial state - ensure first frame is visible
		frames.forEach( ( frame, index ) => {
			if ( index === 0 ) {
				frame.classList.add( 'is-active' );
				frame.style.opacity = '1';
			} else {
				frame.classList.remove( 'is-active' );
				frame.style.opacity = '0';
			}
		} );

		// Initial scroll state check
		updateActiveFrame();

		// Store cleanup function on element for potential future use
		block._revealCleanup = function () {
			window.removeEventListener( 'scroll', onScroll );
		};
	}

	/**
	 * Initialize all Reveal blocks on the page
	 */
	function initAllBlocks() {
		const blocks = document.querySelectorAll( '.caes-reveal' );
		blocks.forEach( initRevealBlock );
	}

	// Initialize on DOM ready
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initAllBlocks );
	} else {
		initAllBlocks();
	}
} )();