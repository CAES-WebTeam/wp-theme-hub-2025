/**
 * Reveal Block Frontend JavaScript
 *
 * Handles scroll-triggered frame transitions with three-phase behavior:
 * 1. Enter: Background scrolls into view naturally
 * 2. Fixed: Background freezes while content scrolls, frame transitions occur
 * 3. Exit: Background scrolls away naturally
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
		const background = block.querySelector( '.reveal-background' );
		const content = block.querySelector( '.reveal-content' );
		const frames = block.querySelectorAll( '.reveal-frame' );

		if ( ! background || ! content || frames.length === 0 ) {
			return;
		}

		const frameCount = frames.length;
		let currentFrameIndex = 0;
		let ticking = false;

		/**
		 * Apply transition to a frame
		 *
		 * @param {HTMLElement} frame     The frame element
		 * @param {boolean}     isActive  Whether frame should be active
		 */
		function applyTransition( frame, isActive ) {
			const transitionType = frame.dataset.transitionType || 'fade';
			const transitionSpeed = parseInt( frame.dataset.transitionSpeed, 10 ) || 500;

			// Set transition duration (or 0 for reduced motion)
			const duration = prefersReducedMotion ? 0 : transitionSpeed;
			frame.style.transitionDuration = `${ duration }ms`;

			if ( isActive ) {
				frame.classList.add( 'is-active' );
				frame.style.opacity = '1';
				frame.style.transform = 'translate(0, 0)';
			} else {
				frame.classList.remove( 'is-active' );
				frame.style.opacity = '0';
			}
		}

		/**
		 * Update scroll state and active frame
		 */
		function updateScrollState() {
			const blockRect = block.getBoundingClientRect();
			const viewportHeight = window.innerHeight;
			const blockTop = blockRect.top;
			const blockBottom = blockRect.bottom;
			const blockHeight = block.offsetHeight;

			// Determine which phase we're in
			// Phase 1 (Enter): Block top is below viewport top (blockTop > 0)
			// Phase 2 (Fixed): Block top is at or above viewport top, AND block bottom is below viewport bottom
			// Phase 3 (Exit): Block bottom is at or above viewport bottom

			const shouldBeFixed = blockTop <= 0 && blockBottom > viewportHeight;
			const isPastEnd = blockTop <= 0 && blockBottom <= viewportHeight;

			// Update classes based on phase
			if ( shouldBeFixed ) {
				// Phase 2: Fixed
				block.classList.add( 'is-in-view' );
				block.classList.remove( 'is-past-end' );
				background.classList.add( 'is-fixed' );
			} else if ( isPastEnd ) {
				// Phase 3: Exit - pin background to bottom
				block.classList.add( 'is-in-view' );
				block.classList.add( 'is-past-end' );
				background.classList.remove( 'is-fixed' );
			} else {
				// Phase 1: Enter - or completely out of view
				block.classList.remove( 'is-in-view' );
				block.classList.remove( 'is-past-end' );
				background.classList.remove( 'is-fixed' );
			}

			// Calculate frame transitions only during fixed phase
			if ( shouldBeFixed ) {
				// Calculate scroll progress through the fixed phase
				// Total scrollable distance = block height - viewport height
				const scrollableDistance = blockHeight - viewportHeight;
				
				if ( scrollableDistance > 0 ) {
					// How far we've scrolled into the block (blockTop is negative when scrolled past)
					const scrolledDistance = Math.abs( blockTop );
					const scrollProgress = Math.max( 0, Math.min( 1, scrolledDistance / scrollableDistance ) );

					// Determine which frame should be active
					const newFrameIndex = Math.min(
						frameCount - 1,
						Math.floor( scrollProgress * frameCount )
					);

					// Update frames if index changed
					if ( newFrameIndex !== currentFrameIndex ) {
						frames.forEach( ( frame, index ) => {
							applyTransition( frame, index === newFrameIndex );
						} );
						currentFrameIndex = newFrameIndex;
					}
				}
			}

			ticking = false;
		}

		/**
		 * Handle scroll events with requestAnimationFrame throttling
		 */
		function onScroll() {
			if ( ! ticking ) {
				window.requestAnimationFrame( updateScrollState );
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
		updateScrollState();

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