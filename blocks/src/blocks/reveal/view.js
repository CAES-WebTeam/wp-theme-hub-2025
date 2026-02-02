/**
 * Reveal Block Frontend JavaScript - Fixed Scroll Approach
 * 
 * Behavior:
 * 1. Block starts as normal flow
 * 2. When block reaches top of viewport, it becomes fixed
 * 3. While fixed, scroll controls: content position, frame transitions
 * 4. When complete, block unfixes and normal scroll resumes
 */

(function () {
	'use strict';

	function initRevealBlock(block) {
		const sections = block.querySelectorAll('.reveal-frame-section');
		const backgrounds = block.querySelectorAll('.reveal-frame-background');
		const contents = block.querySelectorAll('.reveal-frame-content');

		if (sections.length === 0) {
			return;
		}

		let ticking = false;
		let blockFixed = false;
		let virtualHeight = 0;

		/**
		 * Calculate total virtual height needed for all frames
		 */
		function calculateVirtualHeight() {
			const viewportHeight = window.innerHeight;
			let totalHeight = 0;

			sections.forEach((section, index) => {
				const content = section.querySelector('.reveal-frame-content');
				const bg = section.querySelector('.reveal-frame-background');
				
				// Get actual content height
				const contentChildren = content.children;
				let contentHeight = 0;
				for (let child of contentChildren) {
					contentHeight += child.offsetHeight;
					const style = window.getComputedStyle(child);
					contentHeight += parseInt(style.marginTop) + parseInt(style.marginBottom);
				}
				contentHeight = Math.max(contentHeight, 100);

				// For each frame we need:
				// 1. One viewport height + content height to scroll content through
				totalHeight += viewportHeight + contentHeight;

				// 2. Transition distance to next frame (except for last frame)
				if (index < sections.length - 1) {
					const speed = bg.getAttribute('data-speed') || 'normal';
					let transitionDistance;
					if (speed === 'slow') {
						transitionDistance = 2.0 * viewportHeight;
					} else if (speed === 'fast') {
						transitionDistance = 1.0 * viewportHeight;
					} else {
						transitionDistance = 1.5 * viewportHeight;
					}
					totalHeight += transitionDistance;
				}
			});

			virtualHeight = totalHeight;
			
			// Set block's height to create scroll space
			block.style.minHeight = virtualHeight + 'px';
		}

		/**
		 * Update based on scroll position
		 */
		function updateOnScroll() {
			const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
			const viewportHeight = window.innerHeight;
			const blockRect = block.getBoundingClientRect();
			const blockTop = scrollTop + blockRect.top;

			// Determine if block should be fixed
			const shouldFix = blockRect.top <= 0 && blockRect.bottom > viewportHeight;

			if (shouldFix && !blockFixed) {
				// Fix the block
				blockFixed = true;
				block.classList.add('is-fixed');
			} else if (!shouldFix && blockFixed) {
				// Unfix the block
				blockFixed = false;
				block.classList.remove('is-fixed');
			}

			if (!blockFixed) {
				// Not fixed - reset all frames to default state
				backgrounds.forEach((bg, index) => {
					if (index === 0) {
						bg.style.opacity = 1;
						bg.style.transform = 'none';
						bg.style.zIndex = 10;
					} else {
						bg.style.opacity = 0;
						bg.style.transform = 'none';
						bg.style.zIndex = 5;
					}
				});
				contents.forEach((content) => {
					content.style.transform = 'translateY(100vh)';
					content.style.opacity = 0;
				});
				ticking = false;
				return;
			}

			// Calculate virtual scroll (how far through the fixed experience)
			const virtualScroll = scrollTop - blockTop;
			
			// Track state
			let currentFrameIndex = 0;
			let accumulatedHeight = 0;
			let inTransition = false;
			let transitionProgress = 0;
			let nextFrameIndex = -1;

			// Determine which frame we're in and calculate states
			sections.forEach((section, index) => {
				const content = section.querySelector('.reveal-frame-content');
				const bg = section.querySelector('.reveal-frame-background');
				
				// Calculate content height
				const contentChildren = content.children;
				let contentHeight = 0;
				for (let child of contentChildren) {
					contentHeight += child.offsetHeight;
					const style = window.getComputedStyle(child);
					contentHeight += parseInt(style.marginTop) + parseInt(style.marginBottom);
				}
				contentHeight = Math.max(contentHeight, 100);

				const frameContentHeight = viewportHeight + contentHeight;
				
				// Get transition distance for this frame
				const speed = bg.getAttribute('data-speed') || 'normal';
				let transitionDistance = 0;
				if (index < sections.length - 1) {
					if (speed === 'slow') {
						transitionDistance = 2.0 * viewportHeight;
					} else if (speed === 'fast') {
						transitionDistance = 1.0 * viewportHeight;
					} else {
						transitionDistance = 1.5 * viewportHeight;
					}
				}

				const frameStartHeight = accumulatedHeight;
				const frameEndHeight = frameStartHeight + frameContentHeight;
				const transitionEndHeight = frameEndHeight + transitionDistance;

				// Check where we are in this frame
				if (virtualScroll >= frameStartHeight && virtualScroll < frameEndHeight) {
					// We're in this frame's content
					currentFrameIndex = index;
					
					// Calculate content position (scroll from bottom to top)
					const frameProgress = (virtualScroll - frameStartHeight) / frameContentHeight;
					const contentOffset = frameProgress * (viewportHeight + contentHeight) - viewportHeight;
					content.style.transform = `translateY(${-contentOffset}px)`;
					
					// Fade content in/out at edges
					if (frameProgress < 0.15) {
						content.style.opacity = frameProgress / 0.15;
					} else if (frameProgress > 0.85) {
						content.style.opacity = (1 - frameProgress) / 0.15;
					} else {
						content.style.opacity = 1;
					}
				} else if (virtualScroll >= frameEndHeight && virtualScroll < transitionEndHeight && index < sections.length - 1) {
					// We're in transition to next frame
					currentFrameIndex = index;
					nextFrameIndex = index + 1;
					inTransition = true;
					
					const transitionVirtualScroll = virtualScroll - frameEndHeight;
					transitionProgress = transitionVirtualScroll / transitionDistance;
					transitionProgress = Math.max(0, Math.min(1, transitionProgress));
					
					// Hide current frame's content
					content.style.opacity = 0;
					content.style.transform = 'translateY(-100vh)';
				} else {
					// Content is not visible
					content.style.opacity = 0;
					if (virtualScroll < frameStartHeight) {
						content.style.transform = 'translateY(100vh)';
					} else {
						content.style.transform = 'translateY(-100vh)';
					}
				}

				accumulatedHeight = transitionEndHeight;
			});

			// Apply frame visibility and transitions
			backgrounds.forEach((bg, index) => {
				const transitionType = bg.getAttribute('data-transition') || 'none';

				if (index === currentFrameIndex && !inTransition) {
					// Active frame
					bg.style.opacity = 1;
					bg.style.transform = 'none';
					bg.style.zIndex = 10;
				} else if (index === currentFrameIndex && inTransition) {
					// Current frame during transition - backdrop
					bg.style.opacity = 1;
					bg.style.transform = 'none';
					bg.style.zIndex = 9;
				} else if (index === nextFrameIndex && inTransition) {
					// Next frame transitioning in
					bg.style.zIndex = 10;
					
					if (transitionType === 'fade') {
						bg.style.opacity = transitionProgress;
						bg.style.transform = 'none';
					} else if (transitionType === 'left') {
						const x = (1 - transitionProgress) * 100;
						bg.style.transform = `translate3d(${x}%, 0, 0)`;
						bg.style.opacity = 1;
					} else if (transitionType === 'right') {
						const x = -(1 - transitionProgress) * 100;
						bg.style.transform = `translate3d(${x}%, 0, 0)`;
						bg.style.opacity = 1;
					} else if (transitionType === 'up') {
						const y = (1 - transitionProgress) * 100;
						bg.style.transform = `translate3d(0, ${y}%, 0)`;
						bg.style.opacity = 1;
					} else {
						bg.style.opacity = transitionProgress;
						bg.style.transform = 'none';
					}
				} else {
					// Hidden frames
					bg.style.opacity = 0;
					bg.style.transform = 'none';
					bg.style.zIndex = 5;
				}
			});

			ticking = false;
		}

		function onScroll() {
			if (!ticking) {
				window.requestAnimationFrame(updateOnScroll);
				ticking = true;
			}
		}

		function onResize() {
			calculateVirtualHeight();
			updateOnScroll();
		}

		// Initialize
		calculateVirtualHeight();
		
		// Set initial state
		backgrounds.forEach((bg, index) => {
			if (index === 0) {
				bg.style.opacity = 1;
				bg.style.zIndex = 10;
			} else {
				bg.style.opacity = 0;
				bg.style.zIndex = 5;
			}
		});
		
		contents.forEach((content) => {
			content.style.transform = 'translateY(100vh)';
			content.style.opacity = 0;
			content.style.transition = 'none';
		});

		// Set up listeners
		window.addEventListener('scroll', onScroll, { passive: true });
		window.addEventListener('resize', onResize, { passive: true });

		// Recalculate after images load
		block.querySelectorAll('img').forEach((img) => {
			if (!img.complete) {
				img.addEventListener('load', () => {
					calculateVirtualHeight();
					updateOnScroll();
				});
			}
		});

		updateOnScroll();

		block._revealCleanup = function () {
			window.removeEventListener('scroll', onScroll);
			window.removeEventListener('resize', onResize);
		};
	}

	function initAllBlocks() {
		document.querySelectorAll('.caes-reveal').forEach(initRevealBlock);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAllBlocks);
	} else {
		initAllBlocks();
	}
})();