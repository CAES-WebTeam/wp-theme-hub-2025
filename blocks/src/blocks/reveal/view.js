/**
 * Reveal Block Frontend JavaScript
 *
 * Handles scroll-triggered frame transitions with Shorthand-style phased scrolling:
 * 1. Background frame transitions in
 * 2. Content scrolls into view and stays
 * 3. Content scrolls out
 * 4. Next background frame transitions in
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
		let currentActiveIndex = 0;

		// Each frame has 3 phases:
		// 1. Background transition in (from previous frame)
		// 2. Content visible (scrolls in, holds, scrolls out)
		// 3. Background transition out (to next frame)
		//
		// Phase weights (as proportion of each frame's scroll segment):
		const PHASE_BG_IN = 0.2;      // 20% for background transition in
		const PHASE_CONTENT = 0.6;    // 60% for content (in + hold + out)
		const PHASE_BG_OUT = 0.2;     // 20% for background transition out
		
		// Within the content phase, subdivide:
		const CONTENT_IN = 0.25;      // Content fades/slides in
		const CONTENT_HOLD = 0.5;     // Content fully visible
		const CONTENT_OUT = 0.25;     // Content fades/slides out

		// Build frame segments based on transition speed
		const speedMultipliers = { slow: 1.5, normal: 1, fast: 0.5 };
		const frameWeights = [];
		let totalWeight = 0;

		frames.forEach( ( frame, index ) => {
			const speed = frame.dataset.transitionSpeed || 'normal';
			const weight = speedMultipliers[ speed ] || 1;
			frameWeights.push( weight );
			totalWeight += weight;
		} );

		// Calculate cumulative positions (0 to 1) for each frame's start
		const framePositions = [];
		let cumulative = 0;
		for ( let i = 0; i < frameCount; i++ ) {
			framePositions.push( cumulative );
			cumulative += frameWeights[ i ] / totalWeight;
		}
		framePositions.push( 1 ); // End position

		/**
		 * Get transition styles for background frame wipe/fade.
		 */
		function getBackgroundTransitionStyles( type, progress ) {
			if ( prefersReducedMotion ) {
				type = 'fade';
			}

			let styles = {
				opacity: '1',
				clipPath: 'inset(0 0 0 0)',
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

		/**
		 * Get content visibility styles based on content phase progress.
		 * Progress 0-1 covers: fade in -> hold -> fade out
		 */
		function getContentStyles( progress ) {
			let opacity = 1;
			let transform = 'translateY(0)';

			if ( progress < CONTENT_IN ) {
				// Fading/sliding in
				const inProgress = progress / CONTENT_IN;
				opacity = inProgress;
				transform = prefersReducedMotion ? 'translateY(0)' : `translateY(${ ( 1 - inProgress ) * 30 }px)`;
			} else if ( progress < CONTENT_IN + CONTENT_HOLD ) {
				// Fully visible
				opacity = 1;
				transform = 'translateY(0)';
			} else {
				// Fading/sliding out
				const outProgress = ( progress - CONTENT_IN - CONTENT_HOLD ) / CONTENT_OUT;
				opacity = 1 - outProgress;
				transform = prefersReducedMotion ? 'translateY(0)' : `translateY(${ -outProgress * 30 }px)`;
			}

			return { opacity: opacity.toFixed( 3 ), transform };
		}

		/**
		 * Update all frames and content based on scroll position.
		 */
		function updateActiveFrame() {
			const blockRect = block.getBoundingClientRect();
			const viewportHeight = window.innerHeight;
			const blockTop = blockRect.top;
			const blockHeight = block.offsetHeight;

			// Calculate overall scroll progress through the block (0 to 1)
			const scrollableDistance = blockHeight - viewportHeight;
			let scrollProgress = 0;

			if ( scrollableDistance > 0 ) {
				const scrolledDistance = Math.max( 0, -blockTop );
				scrollProgress = Math.min( 1, scrolledDistance / scrollableDistance );
			}

			// Determine which frame segment we're in
			let currentFrameIndex = 0;
			for ( let i = 0; i < frameCount; i++ ) {
				if ( scrollProgress >= framePositions[ i ] ) {
					currentFrameIndex = i;
				}
			}

			// Calculate progress within the current frame's segment (0 to 1)
			const frameStart = framePositions[ currentFrameIndex ];
			const frameEnd = framePositions[ currentFrameIndex + 1 ] || 1;
			const frameLength = frameEnd - frameStart;
			let frameProgress = 0;
			if ( frameLength > 0 ) {
				frameProgress = Math.min( 1, ( scrollProgress - frameStart ) / frameLength );
			}

			// Determine which phase we're in within this frame
			let phase = 'content'; // 'bg-in', 'content', 'bg-out'
			let phaseProgress = 0;

			if ( currentFrameIndex === 0 && frameProgress < PHASE_BG_IN ) {
				// First frame: no bg-in transition, jump straight to content
				phase = 'content';
				phaseProgress = frameProgress / ( PHASE_BG_IN + PHASE_CONTENT );
			} else if ( frameProgress < PHASE_BG_IN ) {
				// Background transitioning in
				phase = 'bg-in';
				phaseProgress = frameProgress / PHASE_BG_IN;
			} else if ( frameProgress < PHASE_BG_IN + PHASE_CONTENT ) {
				// Content phase
				phase = 'content';
				phaseProgress = ( frameProgress - PHASE_BG_IN ) / PHASE_CONTENT;
			} else {
				// Background transitioning out (next frame transitioning in)
				phase = 'bg-out';
				phaseProgress = ( frameProgress - PHASE_BG_IN - PHASE_CONTENT ) / PHASE_BG_OUT;
			}

			// Update background frames
			frames.forEach( ( frame, index ) => {
				frame.style.transitionDuration = '0ms';

				if ( index < currentFrameIndex ) {
					// Past frames - hidden
					frame.classList.remove( 'is-active' );
					frame.style.opacity = '0';
					frame.style.clipPath = 'inset(0 0 0 0)';
					frame.style.zIndex = '0';
				} else if ( index === currentFrameIndex ) {
					// Current frame - fully visible (base layer)
					frame.classList.add( 'is-active' );
					frame.style.opacity = '1';
					frame.style.clipPath = 'none';
					frame.style.zIndex = '1';
				} else if ( index === currentFrameIndex + 1 ) {
					// Next frame - transitioning in during bg-out phase
					if ( phase === 'bg-out' ) {
						const type = frame.dataset.transitionType || 'fade';
						const styles = getBackgroundTransitionStyles( type, phaseProgress );
						frame.classList.add( 'is-active' );
						frame.style.opacity = styles.opacity;
						frame.style.clipPath = styles.clipPath;
						frame.style.zIndex = '2';
					} else {
						// Not yet visible
						frame.classList.remove( 'is-active' );
						frame.style.opacity = '0';
						frame.style.clipPath = 'inset(100% 0 0 0)';
						frame.style.zIndex = '0';
					}
				} else {
					// Future frames - hidden
					frame.classList.remove( 'is-active' );
					frame.style.opacity = '0';
					frame.style.clipPath = 'inset(100% 0 0 0)';
					frame.style.zIndex = '0';
				}
			} );

			// Update content visibility
			frameContents.forEach( ( content ) => {
				const contentIndex = parseInt( content.dataset.frameIndex, 10 );

				if ( contentIndex === currentFrameIndex ) {
					// Current frame's content
					if ( phase === 'bg-in' && currentFrameIndex > 0 ) {
						// Background still transitioning in, content not visible yet
						content.style.opacity = '0';
						content.style.transform = 'translateY(30px)';
						content.style.pointerEvents = 'none';
					} else if ( phase === 'content' || phase === 'bg-in' ) {
						// Content phase - animate in/hold/out
						const styles = getContentStyles( phaseProgress );
						content.style.opacity = styles.opacity;
						content.style.transform = styles.transform;
						content.style.pointerEvents = parseFloat( styles.opacity ) > 0.5 ? 'auto' : 'none';
					} else {
						// bg-out phase - content fading out as we prepare for next frame
						const fadeOut = 1 - phaseProgress;
						content.style.opacity = fadeOut.toFixed( 3 );
						content.style.transform = prefersReducedMotion ? 'translateY(0)' : `translateY(${ -phaseProgress * 30 }px)`;
						content.style.pointerEvents = 'none';
					}
					content.setAttribute( 'aria-hidden', 'false' );
				} else {
					// Not the current frame's content - hide it
					content.style.opacity = '0';
					content.style.transform = 'translateY(30px)';
					content.style.pointerEvents = 'none';
					content.setAttribute( 'aria-hidden', 'true' );
				}
			} );

			currentActiveIndex = currentFrameIndex;
			ticking = false;
		}

		function onScroll() {
			if ( ! ticking ) {
				window.requestAnimationFrame( updateActiveFrame );
				ticking = true;
			}
		}

		// Initialize styles
		frameContents.forEach( ( content, index ) => {
			content.style.transition = 'none'; // We control animations manually
			if ( index === 0 ) {
				content.style.opacity = '1';
				content.style.transform = 'translateY(0)';
				content.style.pointerEvents = 'auto';
				content.setAttribute( 'aria-hidden', 'false' );
			} else {
				content.style.opacity = '0';
				content.style.transform = 'translateY(30px)';
				content.style.pointerEvents = 'none';
				content.setAttribute( 'aria-hidden', 'true' );
			}
		} );

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