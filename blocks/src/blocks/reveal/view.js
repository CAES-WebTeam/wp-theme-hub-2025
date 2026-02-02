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

            // Track which frames should be visible and at what progress
            let activeIndex = 0;
            let transitionProgress = 0;
            let inTransition = false;
            let nextIndex = -1;

            // First, update all content opacity
            contents.forEach((content) => {
                const opacity = getContentOpacity(content);
                content.style.opacity = Math.max(0, Math.min(1, opacity));
            });

            // Determine current state by finding which section we're in
            sections.forEach((section, index) => {
                const rect = section.getBoundingClientRect();
                const sectionTop = scrollTop + rect.top;
                const sectionBottom = sectionTop + section.offsetHeight;
                const content = section.querySelector('.reveal-frame-content');
                
                // Check if content is visible
                const contentOpacity = getContentOpacity(content);
                const contentVisible = contentOpacity > 0.1; // Small threshold

                // Determine if we're in this section
                if (scrollTop >= sectionTop && scrollTop < sectionBottom) {
                    if (contentVisible) {
                        // We're viewing this section's content
                        activeIndex = index;
                        inTransition = false;
                    } else if (index < sections.length - 1) {
                        // Content has scrolled away, we're in transition zone
                        inTransition = true;
                        activeIndex = index;
                        nextIndex = index + 1;
                        
                        // Get speed attribute from the NEXT frame (the one transitioning in)
                        const nextBg = sections[nextIndex].querySelector('.reveal-frame-background');
                        const speed = nextBg.getAttribute('data-speed') || 'normal';
                        
                        // Base transition distance (how much scroll needed for transition)
                        // Adjust based on speed:
                        // - slow: 2.0 viewport heights (more scrolling needed)
                        // - normal: 1.5 viewport heights
                        // - fast: 1.0 viewport height (less scrolling needed)
                        let transitionDistance;
                        if (speed === 'slow') {
                            transitionDistance = 2.0 * viewportHeight;
                        } else if (speed === 'fast') {
                            transitionDistance = 1.0 * viewportHeight;
                        } else {
                            transitionDistance = 1.5 * viewportHeight;
                        }
                        
                        // Calculate how far into the transition zone we are
                        // Find where content ended
                        const contentChildren = content.children;
                        let contentBottom = sectionTop;
                        
                        for (let child of contentChildren) {
                            const childRect = child.getBoundingClientRect();
                            const childBottom = scrollTop + childRect.bottom;
                            if (childBottom > contentBottom) {
                                contentBottom = childBottom;
                            }
                        }
                        
                        // Transition starts after content scrolls past top of viewport
                        const transitionStart = contentBottom - viewportHeight;
                        const transitionEnd = transitionStart + transitionDistance;
                        
                        // Calculate progress through transition zone (0 to 1)
                        transitionProgress = (scrollTop - transitionStart) / transitionDistance;
                        transitionProgress = Math.max(0, Math.min(1, transitionProgress));
                    }
                }
            });

            // Apply visibility and transitions to all backgrounds
            sections.forEach((section, index) => {
                const bg = section.querySelector('.reveal-frame-background');
                const transitionType = bg.getAttribute('data-transition') || 'none';

                if (index === activeIndex && !inTransition) {
                    // This is the active frame - fully visible
                    bg.style.opacity = 1;
                    bg.style.transform = 'none';
                    bg.style.zIndex = 10;
                } else if (index === activeIndex && inTransition) {
                    // Current frame during transition - stay visible as backdrop
                    bg.style.opacity = 1;
                    bg.style.transform = 'none';
                    bg.style.zIndex = 9;
                } else if (index === nextIndex && inTransition) {
                    // Next frame transitioning in - this is the key animation
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