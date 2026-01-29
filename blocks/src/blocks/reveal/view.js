/**
 * Reveal Block Frontend JavaScript
 *
 * Handles scroll-triggered frame transitions.
 */

( function () {
	'use strict';

	const prefersReducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	function initRevealBlock( block ) {
		const frames = block.querySelectorAll( '.reveal-frame' );

		if ( frames.length === 0 ) {
			return;
		}

		const frameCount = frames.length;
		let ticking = false;

		// Build frame weights based on transition speed
		// slow = 1.5x scroll distance, normal = 1x, fast = 0.5x
		const speedMultipliers = { slow: 1.5, normal: 1, fast: 0.5 };
		const frameWeights = [];
		let totalWeight = 0;

		frames.forEach( ( frame, index ) => {
			if ( index === 0 ) {
				// First frame has no incoming transition
				frameWeights.push( 0 );
			} else {
				const speed = frame.dataset.transitionSpeed || 'normal';
				const weight = speedMultipliers[ speed ] || 1;
				frameWeights.push( weight );
				totalWeight += weight;
			}
		} );

		// Calculate cumulative positions (0 to 1) for each frame transition
		const framePositions = [ 0 ]; // First frame starts at 0
		let cumulative = 0;
		for ( let i = 1; i < frameCount; i++ ) {
			cumulative += frameWeights[ i ] / totalWeight;
			framePositions.push( cumulative );
		}

		/**
		 * Get transition styles for the incoming frame.
		 * 
		 * For wipe transitions, we use clip-path: inset() to reveal the image.
		 * The image stays stationary; only the clipping boundary moves.
		 * 
		 * clip-path: inset(top right bottom left)
		 * - 'up' wipe: reveals from bottom to top, so we clip from top
		 * - 'down' wipe: reveals from top to bottom, so we clip from bottom
		 * - 'left' wipe: reveals from right to left, so we clip from left
		 * - 'right' wipe: reveals from left to right, so we clip from right
		 */
		function getTransitionStyles( type, progress ) {
			if ( prefersReducedMotion ) {
				type = 'fade';
			}

			// Default: fully visible, no clipping
			let styles = {
				opacity: '1',
				clipPath: 'inset(0 0 0 0)'
			};

			// Calculate the remaining amount to clip (inverse of progress)
			const clipAmount = ( ( 1 - progress ) * 100 ).toFixed( 2 );

			switch ( type ) {
				case 'fade':
					styles.opacity = progress.toFixed( 3 );
					styles.clipPath = 'none';
					break;
				case 'up':
					// Wipe upward: reveal from bottom, clip from top
					styles.clipPath = `inset(${ clipAmount }% 0 0 0)`;
					break;
				case 'down':
					// Wipe downward: reveal from top, clip from bottom
					styles.clipPath = `inset(0 0 ${ clipAmount }% 0)`;
					break;
				case 'left':
					// Wipe leftward: reveal from right, clip from left
					styles.clipPath = `inset(0 0 0 ${ clipAmount }%)`;
					break;
				case 'right':
					// Wipe rightward: reveal from left, clip from right
					styles.clipPath = `inset(0 ${ clipAmount }% 0 0)`;
					break;
				default:
					// 'none' or unknown: hard cut at 50%
					styles.opacity = progress >= 0.5 ? '1' : '0';
					styles.clipPath = 'none';
					break;
			}

			return styles;
		}

		function updateActiveFrame() {
			const blockRect = block.getBoundingClientRect();
			const viewportHeight = window.innerHeight;
			const blockTop = blockRect.top;
			const blockHeight = block.offsetHeight;

			// Calculate scroll progress
			const scrollableDistance = blockHeight - viewportHeight;
			const scrolledDistance = Math.max( 0, -blockTop );

			let scrollProgress = 0;

			// Avoid division by zero if block fits perfectly in viewport
			if ( scrollableDistance > 0 ) {
				scrollProgress = Math.min( 1, scrolledDistance / scrollableDistance );
			}

			// Find which transition we're in based on weighted positions
			let currentIndex = 0;
			let localProgress = 0;

			for ( let i = 1; i < frameCount; i++ ) {
				if ( scrollProgress >= framePositions[ i ] ) {
					currentIndex = i;
				} else {
					// We're in the transition from (i-1) to i
					currentIndex = i - 1;
					const transitionStart = framePositions[ i - 1 ] || 0;
					const transitionEnd = framePositions[ i ];
					const transitionLength = transitionEnd - transitionStart;
					if ( transitionLength > 0 ) {
						localProgress = ( scrollProgress - transitionStart ) / transitionLength;
					}
					break;
				}
			}

			// If we've scrolled past all transitions, we're on the last frame
			if ( scrollProgress >= 1 ) {
				currentIndex = frameCount - 1;
				localProgress = 0;
			}

			const nextIndex = Math.min( frameCount - 1, currentIndex + 1 );

			frames.forEach( ( frame, index ) => {
				// Kill CSS transitions to allow manual scrubbing
				frame.style.transitionDuration = '0ms';

				if ( index === currentIndex ) {
					// Current base frame - fully visible, no clipping
					frame.classList.add( 'is-active' );
					frame.style.opacity = '1';
					frame.style.clipPath = 'none';
					frame.style.zIndex = '1';
				} else if ( index === nextIndex && nextIndex !== currentIndex && localProgress > 0 ) {
					// Incoming frame - apply wipe/fade transition
					const type = frame.dataset.transitionType || 'fade';
					const styles = getTransitionStyles( type, localProgress );
					
					frame.classList.add( 'is-active' );
					frame.style.opacity = styles.opacity;
					frame.style.clipPath = styles.clipPath;
					frame.style.zIndex = '2';
				} else {
					// Inactive frames - fully clipped/hidden
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

		// Run immediately to set initial state
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