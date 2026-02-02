/**
 * Reveal Block Frontend JavaScript
 * 
 * Correctly mimics Shorthand's behavior:
 * - Content scrolls NATURALLY through the viewport (not faded in place)
 * - JavaScript only controls opacity based on content's ACTUAL position
 * - Content fades in as it enters viewport from below
 * - Content fades out as it exits viewport at top
 * - Background transitions happen at the right time based on content position
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

		const frameCount = frames.length;
		let ticking = false;

		// Parse transition data from frames
		const transitions = [];
		frames.forEach( ( frame, index ) => {
			transitions.push( {
				type: frame.dataset.transitionType || 'fade',
				speed: frame.dataset.transitionSpeed || 'normal'
			} );
		} );

		// Speed affects how much of the transition zone is used
		const speedMultipliers = { slow: 0.5, normal: 0.35, fast: 0.2 };

		/**
		 * Get clip-path style for wipe transitions
		 */
		function getTransitionStyles( type, progress ) {
			if ( prefersReducedMotion ) {
				type = 'fade';
			}

			const clipAmount = ( ( 1 - progress ) * 100 ).toFixed( 2 );

			switch ( type ) {
				case 'fade':
					return { opacity: progress, clipPath: 'none' };
				case 'up':
					return { opacity: 1, clipPath: `inset(${ clipAmount }% 0 0 0)` };
				case 'down':
					return { opacity: 1, clipPath: `inset(0 0 ${ clipAmount }% 0)` };
				case 'left':
					return { opacity: 1, clipPath: `inset(0 0 0 ${ clipAmount }%)` };
				case 'right':
					return { opacity: 1, clipPath: `inset(0 ${ clipAmount }% 0 0)` };
				default:
					return { opacity: progress >= 0.5 ? 1 : 0, clipPath: 'none' };
			}
		}

		/**
		 * Calculate opacity based on element's actual position in viewport
		 * This lets content scroll naturally while fading in/out at edges
		 */
		function getContentOpacityFromPosition( element ) {
			const rect = element.getBoundingClientRect();
			const viewportHeight = window.innerHeight;
			
			// Define fade zones (percentage of viewport height)
			const fadeInZone = viewportHeight * 0.25;  // Bottom 25% of viewport
			const fadeOutZone = viewportHeight * 0.15; // Top 15% of viewport
			
			// Element's vertical center
			const elementCenter = rect.top + ( rect.height / 2 );
			
			// Fully off-screen below
			if ( rect.top >= viewportHeight ) {
				return 0;
			}
			
			// Fully off-screen above
			if ( rect.bottom <= 0 ) {
				return 0;
			}
			
			// Fading in from bottom
			if ( rect.top > viewportHeight - fadeInZone ) {
				const distanceIntoFadeZone = viewportHeight - rect.top;
				return Math.min( 1, distanceIntoFadeZone / fadeInZone );
			}
			
			// Fading out at top
			if ( rect.top < fadeOutZone && rect.bottom > 0 ) {
				// Use element top position for fade out
				const distanceFromTop = rect.top;
				if ( distanceFromTop < 0 ) {
					// Element is partially off-screen at top
					const visibleHeight = rect.bottom;
					return Math.min( 1, visibleHeight / ( rect.height * 0.5 ) );
				}
				return Math.min( 1, distanceFromTop / fadeOutZone );
			}
			
			// Fully visible
			return 1;
		}

		/**
		 * Determine which frame should be active based on content positions
		 */
		function getActiveFrameIndex() {
			const viewportCenter = window.innerHeight / 2;
			let closestIndex = 0;
			let closestDistance = Infinity;

			frameContents.forEach( ( content, index ) => {
				const rect = content.getBoundingClientRect();
				const contentCenter = rect.top + ( rect.height / 2 );
				const distance = Math.abs( contentCenter - viewportCenter );
				
				// Only consider if content is at least partially in viewport
				if ( rect.bottom > 0 && rect.top < window.innerHeight ) {
					if ( distance < closestDistance ) {
						closestDistance = distance;
						closestIndex = index;
					}
				}
			} );

			// Also check if we're at the very start or end
			const firstContent = frameContents[ 0 ];
			const lastContent = frameContents[ frameContents.length - 1 ];
			
			if ( firstContent ) {
				const firstRect = firstContent.getBoundingClientRect();
				if ( firstRect.top > viewportCenter ) {
					closestIndex = 0;
				}
			}
			
			if ( lastContent ) {
				const lastRect = lastContent.getBoundingClientRect();
				if ( lastRect.bottom < viewportCenter ) {
					closestIndex = frameContents.length - 1;
				}
			}

			return closestIndex;
		}

		/**
		 * Calculate background transition progress based on content positions
		 */
		function getBackgroundTransitionProgress( fromIndex, toIndex ) {
			if ( fromIndex >= frameContents.length || toIndex >= frameContents.length ) {
				return 0;
			}

			const fromContent = frameContents[ fromIndex ];
			const toContent = frameContents[ toIndex ];
			
			if ( ! fromContent || ! toContent ) {
				return 0;
			}

			const fromRect = fromContent.getBoundingClientRect();
			const toRect = toContent.getBoundingClientRect();
			const viewportHeight = window.innerHeight;
			
			// Transition starts when "from" content is in upper portion of screen
			// and "to" content is entering from below
			const transitionZone = viewportHeight * 0.4; // 40% of viewport
			const transitionStart = viewportHeight * 0.3; // Starts at 30% from top
			
			// If the "from" content top is above the transition start point
			if ( fromRect.top < transitionStart && toRect.top < viewportHeight ) {
				// Calculate how far through the transition we are
				const progress = ( transitionStart - fromRect.top ) / transitionZone;
				return Math.max( 0, Math.min( 1, progress ) );
			}
			
			return 0;
		}

		/**
		 * Main scroll handler
		 */
		function updateOnScroll() {
			const activeIndex = getActiveFrameIndex();

			// Update content opacity based on actual position
			frameContents.forEach( ( content, index ) => {
				const opacity = getContentOpacityFromPosition( content );
				content.style.opacity = opacity;
				content.style.pointerEvents = opacity > 0.3 ? 'auto' : 'none';
			} );

			// Update background frames
			frames.forEach( ( frame, index ) => {
				frame.style.transitionDuration = '0ms';

				if ( index === activeIndex ) {
					// This is the active frame
					frame.style.opacity = '1';
					frame.style.clipPath = 'none';
					frame.style.zIndex = '1';
					frame.style.display = 'block';
					frame.classList.add( 'is-active' );
				} else if ( index === activeIndex + 1 ) {
					// This is the NEXT frame - check if it should be transitioning in
					const transitionProgress = getBackgroundTransitionProgress( activeIndex, index );
					
					if ( transitionProgress > 0 ) {
						const styles = getTransitionStyles( transitions[ index ].type, transitionProgress );
						frame.style.opacity = styles.opacity;
						frame.style.clipPath = styles.clipPath;
						frame.style.zIndex = '2'; // Above current active
						frame.style.display = 'block';
						frame.classList.add( 'is-active' );
					} else {
						frame.style.opacity = '0';
						frame.style.display = 'none';
						frame.style.zIndex = '0';
						frame.classList.remove( 'is-active' );
					}
				} else {
					// Not active, not transitioning
					frame.style.opacity = '0';
					frame.style.display = 'none';
					frame.style.zIndex = '0';
					frame.classList.remove( 'is-active' );
				}
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
		frameContents.forEach( ( content ) => {
			content.style.opacity = '0';
			// Remove any transition on opacity so it responds instantly to scroll
			content.style.transition = 'none';
		} );

		// First frame visible
		if ( frames[ 0 ] ) {
			frames[ 0 ].style.opacity = '1';
			frames[ 0 ].style.clipPath = 'none';
			frames[ 0 ].style.display = 'block';
			frames[ 0 ].classList.add( 'is-active' );
		}

		// Set up event listeners
		window.addEventListener( 'scroll', onScroll, { passive: true } );
		window.addEventListener( 'resize', updateOnScroll );
		
		// Initial update
		updateOnScroll();

		// Cleanup function
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