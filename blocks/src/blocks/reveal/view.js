/**
 * Reveal Block Frontend JavaScript
 * 
 * Mimics Shorthand's reveal behavior:
 * 1. Frame 1 background visible, content fades in
 * 2. Content fades out as you scroll
 * 3. Frame 2 background transitions in (wipe/fade)
 * 4. Frame 2 content fades in
 * 5. Repeat...
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
		// Each frame (except first) has a transition type and speed
		const transitions = [];
		frames.forEach( ( frame, index ) => {
			transitions.push( {
				type: frame.dataset.transitionType || 'fade',
				speed: frame.dataset.transitionSpeed || 'normal'
			} );
		} );

		// Speed affects what portion of scroll the transition takes
		// Shorthand uses ~0.35 (35%) for transitions
		const speedMultipliers = { slow: 0.4, normal: 0.25, fast: 0.15 };

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
		 * Main scroll handler
		 */
		function updateOnScroll() {
			const blockRect = block.getBoundingClientRect();
			const viewportHeight = window.innerHeight;
			const blockHeight = block.offsetHeight;

			// Calculate scroll progress through the block (0 to 1)
			const scrollableDistance = blockHeight - viewportHeight;
			const scrolledDistance = Math.max( 0, -blockRect.top );
			const scrollProgress = scrollableDistance > 0 
				? Math.min( 1, scrolledDistance / scrollableDistance ) 
				: 0;

			// Divide scroll into segments for each frame
			// Each segment: [content fade in] [content visible] [content fade out] [bg transition]
			const segmentSize = 1 / frameCount;

			// For each frame, calculate its state
			frames.forEach( ( frame, index ) => {
				const segmentStart = index * segmentSize;
				const segmentEnd = ( index + 1 ) * segmentSize;
				const transitionSize = speedMultipliers[ transitions[ index ].speed ] || 0.25;
				
				// Within this frame's segment:
				// - First 15%: content fades in
				// - Middle: content fully visible
				// - Last transitionSize%: content fades out + next bg transitions in
				const contentFadeInEnd = segmentStart + ( segmentSize * 0.15 );
				const contentFadeOutStart = segmentEnd - ( segmentSize * transitionSize );
				const bgTransitionStart = contentFadeOutStart;

				frame.style.transitionDuration = '0ms';

				// Determine background visibility
				if ( scrollProgress < segmentStart ) {
					// Before this frame's segment - hidden
					frame.style.opacity = '0';
					frame.style.clipPath = 'inset(0 0 0 0)';
					frame.style.zIndex = '0';
					frame.style.display = 'none';
				} else if ( scrollProgress >= segmentStart && scrollProgress < bgTransitionStart ) {
					// In this frame's content phase - fully visible
					frame.style.opacity = '1';
					frame.style.clipPath = 'none';
					frame.style.zIndex = '1';
					frame.style.display = 'block';
					frame.classList.add( 'is-active' );
				} else if ( scrollProgress >= bgTransitionStart && scrollProgress < segmentEnd && index < frameCount - 1 ) {
					// Transitioning out (next frame transitioning in)
					// This frame stays visible as base
					frame.style.opacity = '1';
					frame.style.clipPath = 'none';
					frame.style.zIndex = '1';
					frame.style.display = 'block';
					frame.classList.add( 'is-active' );
				} else if ( scrollProgress >= segmentEnd ) {
					// After this frame's segment
					if ( index === frameCount - 1 ) {
						// Last frame stays visible
						frame.style.opacity = '1';
						frame.style.clipPath = 'none';
						frame.style.zIndex = '1';
						frame.style.display = 'block';
						frame.classList.add( 'is-active' );
					} else {
						// Previous frames get hidden
						frame.style.opacity = '0';
						frame.style.display = 'none';
						frame.style.zIndex = '0';
						frame.classList.remove( 'is-active' );
					}
				}

				// Handle incoming transition for frames after first
				if ( index > 0 ) {
					const prevSegmentEnd = index * segmentSize;
					const prevTransitionSize = speedMultipliers[ transitions[ index ].speed ] || 0.25;
					const prevBgTransitionStart = prevSegmentEnd - ( segmentSize * prevTransitionSize );

					if ( scrollProgress >= prevBgTransitionStart && scrollProgress < prevSegmentEnd ) {
						// This frame is transitioning in
						const transitionProgress = ( scrollProgress - prevBgTransitionStart ) / ( prevSegmentEnd - prevBgTransitionStart );
						const styles = getTransitionStyles( transitions[ index ].type, transitionProgress );
						
						frame.style.opacity = styles.opacity;
						frame.style.clipPath = styles.clipPath;
						frame.style.zIndex = '2';
						frame.style.display = 'block';
						frame.classList.add( 'is-active' );
					}
				}
			} );

			// Handle content opacity
			frameContents.forEach( ( content, index ) => {
				const segmentStart = index * segmentSize;
				const segmentEnd = ( index + 1 ) * segmentSize;
				const transitionSize = speedMultipliers[ transitions[ index ]?.speed ] || 0.25;
				
				const contentFadeInStart = segmentStart;
				const contentFadeInEnd = segmentStart + ( segmentSize * 0.15 );
				const contentFadeOutStart = segmentEnd - ( segmentSize * transitionSize );
				const contentFadeOutEnd = segmentEnd;

				let opacity = 0;

				if ( scrollProgress < contentFadeInStart ) {
					opacity = 0;
				} else if ( scrollProgress >= contentFadeInStart && scrollProgress < contentFadeInEnd ) {
					// Fading in
					opacity = ( scrollProgress - contentFadeInStart ) / ( contentFadeInEnd - contentFadeInStart );
				} else if ( scrollProgress >= contentFadeInEnd && scrollProgress < contentFadeOutStart ) {
					// Fully visible
					opacity = 1;
				} else if ( scrollProgress >= contentFadeOutStart && scrollProgress < contentFadeOutEnd ) {
					// Fading out
					opacity = 1 - ( ( scrollProgress - contentFadeOutStart ) / ( contentFadeOutEnd - contentFadeOutStart ) );
				} else {
					opacity = 0;
				}

				// Last frame content stays visible at end
				if ( index === frameCount - 1 && scrollProgress >= contentFadeInEnd ) {
					opacity = 1;
				}

				content.style.opacity = opacity;
				content.style.pointerEvents = opacity > 0.5 ? 'auto' : 'none';
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
			content.style.transition = 'none';
			content.style.opacity = '0';
		} );

		// First frame starts visible
		if ( frames[ 0 ] ) {
			frames[ 0 ].style.opacity = '1';
			frames[ 0 ].style.clipPath = 'none';
			frames[ 0 ].style.display = 'block';
			frames[ 0 ].classList.add( 'is-active' );
		}

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