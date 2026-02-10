/**
 * Motion Scroll Block Frontend JavaScript
 * 
 * Handles scroll-driven image transitions in a split-layout format.
 * Media column is sticky, images transition as user scrolls through content sections.
 */

(function () {
	'use strict';

	/**
	 * Initialize a single motion scroll block
	 */
	function initMotionScrollBlock(block) {
		const mediaContainer = block.querySelector('.motion-scroll-media');
		const frames = block.querySelectorAll('.motion-scroll-frame');
		const contentContainer = block.querySelector('.motion-scroll-content');
		const sections = block.querySelectorAll('.motion-scroll-section');

		if (!mediaContainer || frames.length === 0 || sections.length === 0) {
			return;
		}

		let ticking = false;
		let sectionData = [];

		/**
		 * Calculate layout metrics for each section
		 */
		function calculateLayout() {
			const viewportHeight = window.innerHeight;
			sectionData = [];

			sections.forEach((section, index) => {
				const rect = section.getBoundingClientRect();
				const sectionTop = window.pageYOffset + rect.top;
				const sectionHeight = section.offsetHeight;
				
				// Get transition config from corresponding frame
				const frame = frames[index];
				const speed = frame ? (frame.getAttribute('data-speed') || 'normal') : 'normal';
				
				let transitionDistance;
				switch (speed) {
					case 'slow':
						transitionDistance = viewportHeight * 0.8;
						break;
					case 'fast':
						transitionDistance = viewportHeight * 0.3;
						break;
					default:
						transitionDistance = viewportHeight * 0.5;
				}

				sectionData.push({
					index,
					section,
					frame: frame,
					sectionTop,
					sectionHeight,
					sectionBottom: sectionTop + sectionHeight,
					// Transition starts when section enters viewport
					transitionStart: sectionTop - viewportHeight,
					transitionEnd: sectionTop - viewportHeight + transitionDistance,
				});
			});
		}

		/**
		 * Determine which frame should be active based on scroll position
		 */
		function getCurrentFrameState(scrollTop) {
			const viewportHeight = window.innerHeight;
			const viewportCenter = scrollTop + (viewportHeight / 2);
			
			let activeIndex = 0;
			let transitioningToIndex = -1;
			let transitionProgress = 0;

			// Find which section is most in view
			for (let i = 0; i < sectionData.length; i++) {
				const data = sectionData[i];
				const sectionCenter = data.sectionTop + (data.sectionHeight / 2);
				
				// If viewport center is past this section's start
				if (viewportCenter >= data.sectionTop) {
					activeIndex = i;
				}
				
				// Check if we're in transition zone for the next section
				if (i > 0 && scrollTop >= sectionData[i].transitionStart && scrollTop <= sectionData[i].transitionEnd) {
					transitioningToIndex = i;
					const progressIntoTransition = scrollTop - sectionData[i].transitionStart;
					const transitionDistance = sectionData[i].transitionEnd - sectionData[i].transitionStart;
					transitionProgress = Math.min(1, Math.max(0, progressIntoTransition / transitionDistance));
					activeIndex = i - 1;
				}
			}

			return { activeIndex, transitioningToIndex, transitionProgress };
		}

		/**
		 * Apply transition effects to frames
		 */
		function applyFrameTransitions(activeIndex, transitioningToIndex, transitionProgress) {
			frames.forEach((frame, index) => {
				const transitionType = frame.getAttribute('data-transition') || 'fade';
				
				// Reset styles
				frame.style.transform = '';
				frame.style.opacity = '';
				frame.style.clipPath = '';
				
				if (index === activeIndex && transitioningToIndex === -1) {
					// Active frame, no transition happening
					frame.classList.add('is-active');
					frame.classList.remove('is-transitioning-in', 'is-transitioning-out');
					frame.style.opacity = '1';
					frame.style.zIndex = '10';
				} else if (index === activeIndex && transitioningToIndex !== -1) {
					// Active frame, but transitioning out
					frame.classList.remove('is-active', 'is-transitioning-in');
					frame.classList.add('is-transitioning-out');
					frame.style.opacity = '1';
					frame.style.zIndex = '9';
				} else if (index === transitioningToIndex) {
					// Frame transitioning in
					frame.classList.remove('is-active', 'is-transitioning-out');
					frame.classList.add('is-transitioning-in');
					frame.style.zIndex = '10';
					
					// Apply transition based on type
					switch (transitionType) {
						case 'fade':
							frame.style.opacity = transitionProgress;
							break;
						case 'left':
							// Wipe from left edge toward right
							frame.style.opacity = '1';
							frame.style.clipPath = `inset(0 ${(1 - transitionProgress) * 100}% 0 0)`;
							break;
						case 'right':
							// Wipe from right edge toward left
							frame.style.opacity = '1';
							frame.style.clipPath = `inset(0 0 0 ${(1 - transitionProgress) * 100}%)`;
							break;
						case 'up':
							// Wipe from top edge downward
							frame.style.opacity = '1';
							frame.style.clipPath = `inset(0 0 ${(1 - transitionProgress) * 100}% 0)`;
							break;
						case 'down':
							// Wipe from bottom edge upward
							frame.style.opacity = '1';
							frame.style.clipPath = `inset(${(1 - transitionProgress) * 100}% 0 0 0)`;
							break;
						default:
							frame.style.opacity = transitionProgress;
					}
				} else {
					// Hidden frame
					frame.classList.remove('is-active', 'is-transitioning-in', 'is-transitioning-out');
					frame.style.opacity = '0';
					frame.style.zIndex = '5';
				}
			});
		}

		/**
		 * Main scroll handler
		 */
		function updateOnScroll() {
			const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
			const viewportHeight = window.innerHeight;
			const blockRect = block.getBoundingClientRect();
			const blockTop = scrollTop + blockRect.top;
			const blockBottom = blockTop + block.offsetHeight;

			// Check if block is in view
			const isInView = blockRect.top < viewportHeight && blockRect.bottom > 0;
			
			// Check if media should be sticky (block top is at or above viewport top)
			const shouldBeSticky = blockRect.top <= 0 && blockRect.bottom > viewportHeight;

			// Update block state classes
			block.classList.toggle('is-in-view', isInView);
			block.classList.toggle('is-sticky', shouldBeSticky);

			if (!isInView) {
				// Not in view, show first frame
				frames.forEach((frame, index) => {
					if (index === 0) {
						frame.style.opacity = '1';
						frame.style.zIndex = '10';
						frame.classList.add('is-active');
					} else {
						frame.style.opacity = '0';
						frame.style.zIndex = '5';
						frame.classList.remove('is-active');
					}
				});
				ticking = false;
				return;
			}

			// Get current frame state
			const { activeIndex, transitioningToIndex, transitionProgress } = getCurrentFrameState(scrollTop);

			// Apply frame transitions
			applyFrameTransitions(activeIndex, transitioningToIndex, transitionProgress);

			// Store current frame for potential use
			block.dataset.currentFrame = activeIndex;

			ticking = false;
		}

		/**
		 * Throttled scroll handler
		 */
		function onScroll() {
			if (!ticking) {
				window.requestAnimationFrame(updateOnScroll);
				ticking = true;
			}
		}

		/**
		 * Handle resize
		 */
		function onResize() {
			calculateLayout();
			updateOnScroll();
		}

		/**
		 * Initialize
		 */
		function init() {
			calculateLayout();
			
			// Set up event listeners
			window.addEventListener('scroll', onScroll, { passive: true });
			window.addEventListener('resize', onResize, { passive: true });

			// Recalculate after images load
			const images = block.querySelectorAll('img');
			let imagesLoaded = 0;
			const totalImages = images.length;

			images.forEach((img) => {
				if (img.complete) {
					imagesLoaded++;
				} else {
					img.addEventListener('load', () => {
						imagesLoaded++;
						if (imagesLoaded === totalImages) {
							calculateLayout();
							updateOnScroll();
						}
					});
					img.addEventListener('error', () => {
						imagesLoaded++;
					});
				}
			});

			// Initial update
			updateOnScroll();

			// Mark as initialized
			block.dataset.initialized = 'true';
		}

		// Store cleanup function
		block._motionScrollCleanup = function () {
			window.removeEventListener('scroll', onScroll);
			window.removeEventListener('resize', onResize);
		};

		// Initialize
		init();
	}

	/**
	 * Initialize all motion scroll blocks on the page
	 */
	function initAllBlocks() {
		document.querySelectorAll('.caes-motion-scroll').forEach((block) => {
			if (!block.dataset.initialized) {
				initMotionScrollBlock(block);
			}
		});
	}

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAllBlocks);
	} else {
		initAllBlocks();
	}

	// Re-initialize if new blocks are added
	if (typeof MutationObserver !== 'undefined') {
		const observer = new MutationObserver((mutations) => {
			mutations.forEach((mutation) => {
				mutation.addedNodes.forEach((node) => {
					if (node.nodeType === 1) {
						if (node.classList?.contains('caes-motion-scroll')) {
							initMotionScrollBlock(node);
						}
						node.querySelectorAll?.('.caes-motion-scroll')?.forEach(initMotionScrollBlock);
					}
				});
			});
		});

		observer.observe(document.body, { childList: true, subtree: true });
	}
})();
