/**
 * Reveal Block Frontend JavaScript
 *
 * Scroll behavior:
 * 1. Frame background transitions in (wipe/fade)
 * 2. Background HOLDS while content scrolls through naturally
 * 3. Once content scrolls off, next frame background transitions in
 * 4. Repeat...
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

		// Transition speed multipliers (affects how much scroll distance the transition takes)
		const speedMultipliers = { slow: 1.5, normal: 1, fast: 0.5 };

		// Get transition scroll distance in pixels for each frame
		const transitionDistances = [];
		frames.forEach( ( frame ) => {
			const speed = frame.dataset.transitionSpeed || 'normal';
			const multiplier = speedMultipliers[ speed ] || 1;
			// Base transition distance is 50vh, modified by speed
			transitionDistances.push( window.innerHeight * 0.5 * multiplier );
		} );

		/**
		 * Get transition styles for the incoming frame.
		 */
		function getTransitionStyles( type, progress ) {
			if ( prefersReducedMotion ) {
				type = 'fade';
			}

			let styles = {
				opacity: '1',
				clipPath: 'inset(0 0 0 0)'
			};

			const clipAmount = ( ( 1 - progress ) * 100 ).toFixed( 2 );

			switch ( type ) {
				case 'fade':
					styles.opacity = progress.toFixed( 3 );
					styles.clipPath = 'none';
					break;
				case 'up':
					styles.clipPath = `inset(${ clipAmount }% 0 0 0)`;
					break;
				case 'down':
					styles.clipPath = `inset(0 0 ${ clipAmount }% 0)`;
					break;
				case 'left':
					styles.clipPath = `inset(0 0 0 ${ clipAmount }%)`;
					break;
				case 'right':
					styles.clipPath = `inset(0 ${ clipAmount }% 0 0)`;
					break;
				default:
					styles.opacity = progress >= 0.5 ? '1' : '0';
					styles.clipPath = 'none';
					break;
			}

			return styles;
		}

		function updateActiveFrame() {
			const viewportHeight = window.innerHeight;

			// Determine which frame should be active based on content positions
			// A frame is active when its content is on screen or approaching
			// Transition happens when content has scrolled off and next content is approaching

			let activeFrameIndex = 0;
			let transitionProgress = 0;
			let transitioningToIndex = -1;

			for ( let i = 0; i < frameContents.length; i++ ) {
				const content = frameContents[ i ];
				const rect = content.getBoundingClientRect();
				const nextContent = frameContents[ i + 1 ];

				// Content is considered "active" if any part is visible or it's above viewport
				// (meaning we've scrolled past it but haven't reached next content yet)
				
				if ( rect.top < viewportHeight && rect.bottom > 0 ) {
					// Content is currently visible on screen - this frame is active
					activeFrameIndex = i;
					
					// Check if we should start transitioning to next frame
					// Transition starts when this content's bottom is near top of viewport
					if ( nextContent && rect.bottom < viewportHeight * 0.3 ) {
						const nextRect = nextContent.getBoundingClientRect();
						const transitionDistance = transitionDistances[ i + 1 ] || ( viewportHeight * 0.5 );
						
						// Calculate transition progress based on gap between contents
						// Transition happens in the space between current content bottom and next content top
						const gapStart = rect.bottom;
						const gapEnd = Math.min( nextRect.top, viewportHeight * 0.5 );
						
						if ( gapStart < gapEnd ) {
							// We're in the transition zone
							transitionProgress = Math.max( 0, Math.min( 1, 
								( viewportHeight * 0.3 - rect.bottom ) / transitionDistance 
							) );
							transitioningToIndex = i + 1;
						}
					}
					break;
				} else if ( rect.bottom <= 0 ) {
					// Content has scrolled completely off the top - move to next frame
					activeFrameIndex = i + 1;
				} else if ( rect.top >= viewportHeight ) {
					// Content hasn't entered viewport yet - stay on previous frame
					activeFrameIndex = Math.max( 0, i - 1 );
					
					// But check if we should be transitioning TO this frame
					if ( i > 0 ) {
						const prevContent = frameContents[ i - 1 ];
						const prevRect = prevContent.getBoundingClientRect();
						
						if ( prevRect.bottom < viewportHeight * 0.3 ) {
							const transitionDistance = transitionDistances[ i ] || ( viewportHeight * 0.5 );
							transitionProgress = Math.max( 0, Math.min( 1,
								( viewportHeight * 0.3 - prevRect.bottom ) / transitionDistance
							) );
							transitioningToIndex = i;
							activeFrameIndex = i - 1;
						}
					}
					break;
				}
			}

			// Clamp to valid range
			activeFrameIndex = Math.max( 0, Math.min( frameCount - 1, activeFrameIndex ) );

			// Update background frames
			frames.forEach( ( frame, index ) => {
				frame.style.transitionDuration = '0ms';

				if ( index === activeFrameIndex ) {
					// Current active frame - fully visible
					frame.classList.add( 'is-active' );
					frame.style.opacity = '1';
					frame.style.clipPath = 'none';
					frame.style.zIndex = '1';
				} else if ( index === transitioningToIndex && transitionProgress > 0 ) {
					// Frame transitioning in
					const type = frame.dataset.transitionType || 'fade';
					const styles = getTransitionStyles( type, transitionProgress );
					
					frame.classList.add( 'is-active' );
					frame.style.opacity = styles.opacity;
					frame.style.clipPath = styles.clipPath;
					frame.style.zIndex = '2';
				} else {
					// Inactive frames
					frame.classList.remove( 'is-active' );
					frame.style.opacity = '0';
					frame.style.clipPath = 'inset(0 0 0 0)';
					frame.style.zIndex = '0';
				}
			} );

			ticking = false;
		}

		function onScroll() {
			if ( ! ticking ) {
				window.requestAnimationFrame( updateActiveFrame );
				ticking = true;
			}
		}

		window.addEventListener( 'scroll', onScroll, { passive: true } );
		window.addEventListener( 'resize', updateActiveFrame );

		// Initial update
		updateActiveFrame();

		block._revealCleanup = function () {
			window.removeEventListener( 'scroll', onScroll );
			window.removeEventListener( 'resize', updateActiveFrame );
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