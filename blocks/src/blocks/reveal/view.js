/**
 * Reveal Block Frontend JavaScript
 *
 * Each frame has two scroll phases:
 * 1. HOLD - Background stays while content is visible (content scrolls naturally)
 * 2. TRANSITION - Background transitions to next frame
 *
 * The transition only happens AFTER the content has scrolled off.
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

		// Speed multipliers affect transition duration
		const speedMultipliers = { slow: 1.5, normal: 1, fast: 0.5 };

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

		/**
		 * Update frame content visibility
		 */
		function updateFrameContentVisibility( targetIndex ) {
			if ( targetIndex === currentActiveIndex ) {
				return;
			}

			frameContents.forEach( ( content ) => {
				const contentIndex = parseInt( content.dataset.frameIndex, 10 );

				if ( contentIndex === targetIndex ) {
					content.style.opacity = '1';
					content.style.pointerEvents = 'auto';
					content.setAttribute( 'aria-hidden', 'false' );
				} else {
					content.style.opacity = '0';
					content.style.pointerEvents = 'none';
					content.setAttribute( 'aria-hidden', 'true' );
				}
			} );

			currentActiveIndex = targetIndex;
		}

		/**
		 * Main update function - determines active frame and transition progress
		 * 
		 * Sequence for each frame:
		 * 1. Previous content scrolls off top
		 * 2. Background transitions to new frame (in the gap)
		 * 3. New content scrolls in from bottom
		 * 4. Repeat
		 */
		function updateActiveFrame() {
			const viewportHeight = window.innerHeight;
			
			let activeFrameIndex = 0;
			let transitionProgress = 0;
			let nextFrameIndex = -1;

			// Loop through content blocks to find which one is "active"
			for ( let i = 0; i < frameContents.length; i++ ) {
				const content = frameContents[ i ];
				const rect = content.getBoundingClientRect();
				
				// Get the transition zone height for transitioning TO this frame
				const thisFrame = frames[ i ];
				const transitionSpeed = thisFrame ? ( thisFrame.dataset.transitionSpeed || 'normal' ) : 'normal';
				const transitionMultiplier = speedMultipliers[ transitionSpeed ] || 1;
				const transitionZoneHeight = viewportHeight * 0.6 * transitionMultiplier;

				// Check if this content's top has entered the viewport
				const contentTopEntered = rect.top < viewportHeight;
				
				if ( i === 0 ) {
					// First frame - always active until its content leaves
					if ( rect.bottom > 0 ) {
						activeFrameIndex = 0;
						break;
					}
					// First content has left, check for transition to frame 2
					continue;
				}

				// For frames 2+:
				// The transition should happen BEFORE content enters
				// Transition zone is above this content (between prev content and this one)
				
				const prevContent = frameContents[ i - 1 ];
				const prevRect = prevContent.getBoundingClientRect();
				
				// Previous content has scrolled off top
				if ( prevRect.bottom <= 0 ) {
					// We're either transitioning or fully on this frame
					
					// Calculate how far into the transition zone we are
					// Transition starts when prev content bottom hits 0
					// Transition ends when this content top reaches a threshold (e.g., 80% down viewport)
					const transitionEndPoint = viewportHeight * 0.8;
					
					if ( rect.top > transitionEndPoint ) {
						// Still transitioning - prev content gone, this content not yet visible
						// Progress based on how close this content's top is to the end point
						const distanceToEnd = rect.top - transitionEndPoint;
						transitionProgress = Math.max( 0, Math.min( 1, 1 - ( distanceToEnd / transitionZoneHeight ) ) );
						activeFrameIndex = i - 1;
						nextFrameIndex = i;
						break;
					} else {
						// Transition complete, this frame is now active
						activeFrameIndex = i;
						
						// Check if THIS content is leaving and we need to transition to next
						if ( rect.bottom <= 0 && i < frameContents.length - 1 ) {
							// This content has left, start next transition
							continue;
						}
						break;
					}
				} else {
					// Previous content still visible, stay on previous frame
					activeFrameIndex = i - 1;
					break;
				}
			}

			// Handle scrolling past all content
			const lastContent = frameContents[ frameContents.length - 1 ];
			if ( lastContent ) {
				const lastRect = lastContent.getBoundingClientRect();
				if ( lastRect.bottom <= 0 ) {
					activeFrameIndex = frameCount - 1;
					transitionProgress = 0;
					nextFrameIndex = -1;
				}
			}

			// Clamp to valid range
			activeFrameIndex = Math.max( 0, Math.min( frameCount - 1, activeFrameIndex ) );

			// Update background frames
			frames.forEach( ( frame, index ) => {
				frame.style.transitionDuration = '0ms';

				if ( index === activeFrameIndex ) {
					// Active frame - fully visible
					frame.classList.add( 'is-active' );
					frame.style.opacity = '1';
					frame.style.clipPath = 'none';
					frame.style.zIndex = '1';
				} else if ( index === nextFrameIndex && transitionProgress > 0 ) {
					// Next frame - transitioning in
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

			// Content visibility: show content only when its frame is fully active (not transitioning)
			let visibleContentIndex = activeFrameIndex;
			if ( nextFrameIndex >= 0 && transitionProgress >= 1 ) {
				visibleContentIndex = nextFrameIndex;
			}
			updateFrameContentVisibility( visibleContentIndex );

			ticking = false;
		}

		function onScroll() {
			if ( ! ticking ) {
				window.requestAnimationFrame( updateActiveFrame );
				ticking = true;
			}
		}

		// Initialize content visibility
		frameContents.forEach( ( content, index ) => {
			content.style.transition = 'opacity 0.3s ease';
			if ( index === 0 ) {
				content.style.opacity = '1';
				content.style.pointerEvents = 'auto';
				content.setAttribute( 'aria-hidden', 'false' );
			} else {
				content.style.opacity = '0';
				content.style.pointerEvents = 'none';
				content.setAttribute( 'aria-hidden', 'true' );
			}
		} );

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