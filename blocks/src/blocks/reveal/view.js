/**
 * Reveal Block Frontend JavaScript - Sticky Approach v6
 * 
 * Handles:
 * 1. Setting section heights based on content height
 * 2. Content opacity fade as it enters/exits
 */

(function () {
	'use strict';

	function initRevealBlock(block) {
		const sections = block.querySelectorAll('.reveal-frame-section');
		const contents = block.querySelectorAll('.reveal-frame-content');

		if (sections.length === 0) {
			return;
		}

		let ticking = false;

		/**
		 * Calculate and set section heights based on content
		 * Section needs: 100vh (for sticky background) + content height + 100vh (for content to scroll through)
		 */
		function setSectionHeights() {
			const viewportHeight = window.innerHeight;

			sections.forEach((section, index) => {
				const content = section.querySelector('.reveal-frame-content');
				if (!content) return;

				// Get actual content height (excluding padding)
				const contentChildren = content.children;
				let contentHeight = 0;

				for (let child of contentChildren) {
					contentHeight += child.offsetHeight;
					// Add margin
					const style = window.getComputedStyle(child);
					contentHeight += parseInt(style.marginTop) + parseInt(style.marginBottom);
				}

				// Minimum content height
				contentHeight = Math.max(contentHeight, 100);

				// Section height calculation:
				// - 100vh for the sticky background
				// - contentHeight for the actual content
				// - 100vh for the content to scroll completely through the viewport
				// But we subtract 100vh because content has margin-top: -100vh

				// Uniform height calculation for ALL sections (including the last one)
				// This ensures the background stays sticky while content scrolls through
				const sectionHeight = viewportHeight + contentHeight + viewportHeight;

				section.style.height = sectionHeight + 'px';
			});
		}

		/**
		 * Calculate content opacity based on actual content position
		 */
		function getContentOpacity(element) {
			const viewportHeight = window.innerHeight;

			// Find actual content children bounds
			const contentChildren = element.children;
			if (contentChildren.length === 0) {
				return 1;
			}

			let contentTop = Infinity;
			let contentBottom = -Infinity;

			for (let child of contentChildren) {
				const childRect = child.getBoundingClientRect();
				if (childRect.height === 0) continue; // Skip empty elements
				if (childRect.top < contentTop) contentTop = childRect.top;
				if (childRect.bottom > contentBottom) contentBottom = childRect.bottom;
			}

			// Fallback if no visible children
			if (contentTop === Infinity) {
				return 1;
			}

			const fadeZone = viewportHeight * 0.15; // 15% of viewport for fade

			// Content fully below viewport - hidden
			if (contentTop >= viewportHeight) {
				return 0;
			}

			// Content fully above viewport - hidden
			if (contentBottom <= 0) {
				return 0;
			}

			// Fading in from bottom
			if (contentTop > viewportHeight - fadeZone) {
				return (viewportHeight - contentTop) / fadeZone;
			}

			// Fading out at top
			if (contentBottom < fadeZone) {
				return contentBottom / fadeZone;
			}

			// Fully visible
			return 1;
		}

		function updateOnScroll() {
            const viewportHeight = window.innerHeight;
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

            // Determine which section we're currently "in" based on scroll position
            let activeIndex = 0;
            let transitionProgress = 0;
            let inTransition = false;
            let nextIndex = -1;

            sections.forEach((section, index) => {
                const rect = section.getBoundingClientRect();
                const sectionTop = scrollTop + rect.top;
                const content = section.querySelector('.reveal-frame-content');
                
                // Check if content is visible
                const contentOpacity = getContentOpacity(content);
                const contentVisible = contentOpacity > 0;

                // Update content opacity
                content.style.opacity = Math.max(0, Math.min(1, contentOpacity));

                // Determine active section
                // A section is active if we're within its bounds and its content is visible
                if (scrollTop >= sectionTop - viewportHeight && scrollTop < sectionTop + section.offsetHeight) {
                    if (contentVisible) {
                        activeIndex = index;
                    } else if (index < sections.length - 1) {
                        // We're in the gap between sections - transition zone
                        inTransition = true;
                        activeIndex = index;
                        nextIndex = index + 1;
                        
                        // Calculate transition progress based on how far through the gap we are
                        // The gap is the space between when content fades out and next content fades in
                        const nextSection = sections[index + 1];
                        const nextContent = nextSection.querySelector('.reveal-frame-content');
                        const nextContentOpacity = getContentOpacity(nextContent);
                        
                        // Transition progresses as we move through the gap
                        const sectionProgress = (scrollTop - sectionTop) / section.offsetHeight;
                        transitionProgress = Math.max(0, Math.min(1, sectionProgress));
                    }
                }
            });

            // Now apply visibility and transitions to all backgrounds
            sections.forEach((section, index) => {
                const bg = section.querySelector('.reveal-frame-background');
                const transitionType = bg.getAttribute('data-transition') || 'none';

                if (index === activeIndex && !inTransition) {
                    // This is the active frame - fully visible
                    bg.style.opacity = 1;
                    bg.style.transform = 'none';
                    bg.style.zIndex = 10;
                } else if (index === activeIndex && inTransition && index === 0) {
                    // First frame during transition - fade out
                    bg.style.opacity = 1 - transitionProgress;
                    bg.style.transform = 'none';
                    bg.style.zIndex = 9;
                } else if (index === activeIndex && inTransition) {
                    // Current frame during transition - keep visible as backdrop
                    bg.style.opacity = 1;
                    bg.style.transform = 'none';
                    bg.style.zIndex = 9;
                } else if (index === nextIndex && inTransition) {
                    // Next frame transitioning in
                    bg.style.zIndex = 10;
                    
                    if (transitionType === 'fade') {
                        bg.style.opacity = transitionProgress;
                        bg.style.transform = 'none';
                    } else if (transitionType === 'left') {
                        // Slide in from right
                        const x = (1 - transitionProgress) * 100;
                        bg.style.transform = `translate3d(${x}%, 0, 0)`;
                        bg.style.opacity = 1;
                    } else if (transitionType === 'right') {
                        // Slide in from left
                        const x = -(1 - transitionProgress) * 100;
                        bg.style.transform = `translate3d(${x}%, 0, 0)`;
                        bg.style.opacity = 1;
                    } else if (transitionType === 'up') {
                        // Slide up from bottom
                        const y = (1 - transitionProgress) * 100;
                        bg.style.transform = `translate3d(0, ${y}%, 0)`;
                        bg.style.opacity = 1;
                    } else {
                        // 'none' - just fade in
                        bg.style.opacity = transitionProgress;
                        bg.style.transform = 'none';
                    }
                } else {
                    // All other frames - hidden
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
			setSectionHeights();
			updateOnScroll();
		}

		// Initialize
		contents.forEach((content) => {
			content.style.transition = 'none';
		});

		// Set initial state - first frame visible
		if (sections.length > 0) {
			const firstBg = sections[0].querySelector('.reveal-frame-background');
			if (firstBg) {
				firstBg.style.opacity = 1;
				firstBg.style.zIndex = 10;
			}
		}

		// Set initial heights
		setSectionHeights();

		// Set up listeners
		window.addEventListener('scroll', onScroll, { passive: true });
		window.addEventListener('resize', onResize, { passive: true });

		// Recalculate after images load
		block.querySelectorAll('img').forEach((img) => {
			if (!img.complete) {
				img.addEventListener('load', setSectionHeights);
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