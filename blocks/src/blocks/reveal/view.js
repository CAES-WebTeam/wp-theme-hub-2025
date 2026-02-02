/**
 * Reveal Block Frontend JavaScript
 *
 * Simple approach: content scrolls naturally, JS only handles
 * background frame transitions based on which content is in view.
 */

( function () {
	'use strict';

	const prefersReducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	function initRevealBlock( block ) {
		const frames = block.querySelectorAll( '.reveal-frame' );
		const frameContents = block.querySelectorAll( '.reveal-frame-content' );

		if ( frames.length === 0 ) {
			return;
		}

		let currentFrameIndex = 0;

		/**
		 * Apply transition styles to incoming frame.
		 */
		function applyTransition( frame, type, progress ) {
			if ( prefersReducedMotion ) {
				type = 'fade';
			}

			const clipAmount = ( ( 1 - progress ) * 100 ).toFixed( 2 );

			switch ( type ) {
				case 'fade':
					frame.style.opacity = progress.toFixed( 3 );
					frame.style.clipPath = 'none';
					break;
				case 'up':
					frame.style.opacity = '1';
					frame.style.clipPath = `inset(${ clipAmount }% 0 0 0)`;
					break;
				case 'down':
					frame.style.opacity = '1';
					frame.style.clipPath = `inset(0 0 ${ clipAmount }% 0)`;
					break;
				case 'left':
					frame.style.opacity = '1';
					frame.style.clipPath = `inset(0 0 0 ${ clipAmount }%)`;
					break;
				case 'right':
					frame.style.opacity = '1';
					frame.style.clipPath = `inset(0 ${ clipAmount }% 0 0)`;
					break;
				default:
					frame.style.opacity = progress >= 0.5 ? '1' : '0';
					frame.style.clipPath = 'none';
					break;
			}
		}

		/**
		 * Update which background frame is visible based on content position.
		 */
		function updateFrames() {
			const viewportHeight = window.innerHeight;
			const viewportCenter = viewportHeight / 2;

			// Find which content block is most in view (closest to viewport center)
			let activeIndex = 0;
			let closestDistance = Infinity;

			frameContents.forEach( ( content, index ) => {
				const rect = content.getBoundingClientRect();
				const contentCenter = rect.top + rect.height / 2;
				const distance = Math.abs( contentCenter - viewportCenter );

				// Only consider if content is at least partially visible
				if ( rect.bottom > 0 && rect.top < viewportHeight ) {
					if ( distance < closestDistance ) {
						closestDistance = distance;
						activeIndex = index;
					}
				}
			} );

			// Also check: if we're past all content, stay on last frame
			const lastContent = frameContents[ frameContents.length - 1 ];
			if ( lastContent ) {
				const lastRect = lastContent.getBoundingClientRect();
				if ( lastRect.bottom < viewportCenter ) {
					activeIndex = frameContents.length - 1;
				}
			}

			// Check if first content hasn't entered yet
			const firstContent = frameContents[ 0 ];
			if ( firstContent ) {
				const firstRect = firstContent.getBoundingClientRect();
				if ( firstRect.top > viewportCenter ) {
					activeIndex = 0;
				}
			}

			// Calculate transition progress between frames
			let transitionProgress = 1;
			let transitioningToIndex = activeIndex;

			if ( activeIndex < frameContents.length - 1 ) {
				const currentContent = frameContents[ activeIndex ];
				const nextContent = frameContents[ activeIndex + 1 ];

				if ( currentContent && nextContent ) {
					const currentRect = currentContent.getBoundingClientRect();
					const nextRect = nextContent.getBoundingClientRect();

					// Transition starts when current content center passes viewport center
					// and ends when next content center reaches viewport center
					const currentCenter = currentRect.top + currentRect.height / 2;
					const nextCenter = nextRect.top + nextRect.height / 2;

					if ( currentCenter < viewportCenter && nextCenter > viewportCenter ) {
						// We're in a transition zone
						const transitionZone = nextCenter - currentCenter;
						const progressInZone = viewportCenter - currentCenter;
						transitionProgress = Math.max( 0, Math.min( 1, progressInZone / transitionZone ) );
						transitioningToIndex = activeIndex + 1;
					}
				}
			}

			// Update frame visibility
			frames.forEach( ( frame, index ) => {
				// Disable CSS transitions for manual scrubbing
				frame.style.transitionDuration = '0ms';

				if ( index < activeIndex ) {
					// Past frames: hidden
					frame.classList.remove( 'is-active' );
					frame.style.opacity = '0';
					frame.style.clipPath = 'inset(0 0 0 0)';
					frame.style.zIndex = '0';
				} else if ( index === activeIndex ) {
					// Current frame: fully visible
					frame.classList.add( 'is-active' );
					frame.style.opacity = '1';
					frame.style.clipPath = 'none';
					frame.style.zIndex = '1';
				} else if ( index === transitioningToIndex && transitionProgress > 0 && transitionProgress < 1 ) {
					// Next frame: transitioning in
					const type = frame.dataset.transitionType || 'fade';
					frame.classList.add( 'is-active' );
					frame.style.zIndex = '2';
					applyTransition( frame, type, transitionProgress );
				} else {
					// Future frames: hidden
					frame.classList.remove( 'is-active' );
					frame.style.opacity = '0';
					frame.style.clipPath = 'inset(0 0 100% 0)';
					frame.style.zIndex = '0';
				}
			} );

			currentFrameIndex = activeIndex;
		}

		// Throttle scroll updates
		let ticking = false;
		function onScroll() {
			if ( ! ticking ) {
				window.requestAnimationFrame( () => {
					updateFrames();
					ticking = false;
				} );
				ticking = true;
			}
		}

		window.addEventListener( 'scroll', onScroll, { passive: true } );
		window.addEventListener( 'resize', updateFrames );

		// Initial update
		updateFrames();

		// Cleanup function
		block._revealCleanup = function () {
			window.removeEventListener( 'scroll', onScroll );
			window.removeEventListener( 'resize', updateFrames );
		};
	}

	function initAllBlocks() {
		const blocks = document.querySelectorAll( '.caes-reveal' );
		blocks.forEach( initRevealBlock );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initAllBlocks );
	} else {
		initAllBlocks();
	}
} )();