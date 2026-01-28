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

		function getTransitionStyles( type, progress ) {
			if ( prefersReducedMotion ) {
				type = 'fade';
			}

			let styles = {
				opacity: '1',
				transform: 'translate(0, 0)'
			};

			switch ( type ) {
				case 'fade':
					styles.opacity = progress.toFixed( 3 );
					break;
				case 'up':
					styles.transform = `translateY(${ ( 1 - progress ) * 100 }%)`;
					break;
				case 'down':
					styles.transform = `translateY(${ ( 1 - progress ) * -100 }%)`;
					break;
				case 'left':
					styles.transform = `translateX(${ ( 1 - progress ) * 100 }%)`;
					break;
				case 'right':
					styles.transform = `translateX(${ ( 1 - progress ) * -100 }%)`;
					break;
				default:
					styles.opacity = progress >= 0.5 ? '1' : '0';
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

			// Map 0..1 to 0..(Frames-1)
			const totalTransitions = Math.max( 0, frameCount - 1 );
			const virtualScroll = Math.min( totalTransitions, scrollProgress * totalTransitions );
			
			const currentIndex = Math.floor( virtualScroll );
			const nextIndex = Math.min( frameCount - 1, currentIndex + 1 );
			const localProgress = virtualScroll - currentIndex;

			frames.forEach( ( frame, index ) => {
				// Kill CSS transitions to allow manual scrubbing
				frame.style.transitionDuration = '0ms';

				if ( index === currentIndex ) {
					// Current base frame
					frame.classList.add( 'is-active' );
					frame.style.opacity = '1';
					frame.style.transform = 'translate(0, 0)';
					frame.style.zIndex = '1';
				} else if ( index === nextIndex && nextIndex !== currentIndex ) {
					// Incoming frame
					const type = frame.dataset.transitionType || 'fade';
					const styles = getTransitionStyles( type, localProgress );
					
					frame.classList.add( 'is-active' );
					frame.style.opacity = styles.opacity;
					frame.style.transform = styles.transform;
					frame.style.zIndex = '2';
				} else {
					// Inactive frames
					frame.classList.remove( 'is-active' );
					frame.style.opacity = '0';
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