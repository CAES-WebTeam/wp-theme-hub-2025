/**
 * Motion Scroll Block Frontend Script
 * Handles scroll-triggered image transitions
 */

document.addEventListener('DOMContentLoaded', function () {
	const motionScrollBlocks = document.querySelectorAll('.caes-motion-scroll');

	motionScrollBlocks.forEach((block) => {
		const content = block.querySelector('.motion-scroll-content');
		const slides = block.querySelectorAll('.motion-scroll-slide');
		const slideCount = parseInt(block.getAttribute('data-slide-count') || slides.length, 10);

		if (slideCount === 0 || !content) {
			return;
		}

		let currentSlideIndex = 0;

		// Function to update active slide based on scroll position
		function updateActiveSlide() {
			const rect = content.getBoundingClientRect();
			const contentTop = rect.top;
			const contentHeight = rect.height;
			const viewportHeight = window.innerHeight;

			// Calculate how far through the content we've scrolled
			// When content top is at viewport top (0), we're at the start
			// When content bottom is at viewport top, we're at the end
			const scrollProgress = Math.max(0, Math.min(1, -contentTop / (contentHeight - viewportHeight)));

			// Determine which slide should be active based on scroll progress
			const targetSlideIndex = Math.min(
				slideCount - 1,
				Math.floor(scrollProgress * slideCount)
			);

			// Only update if the slide has changed
			if (targetSlideIndex !== currentSlideIndex) {
				slides[currentSlideIndex]?.classList.remove('is-active');
				slides[targetSlideIndex]?.classList.add('is-active');
				currentSlideIndex = targetSlideIndex;
			}
		}

		// Throttle scroll events for performance
		let ticking = false;
		function onScroll() {
			if (!ticking) {
				window.requestAnimationFrame(() => {
					updateActiveSlide();
					ticking = false;
				});
				ticking = true;
			}
		}

		// Initial check
		updateActiveSlide();

		// Listen for scroll events
		window.addEventListener('scroll', onScroll, { passive: true });

		// Optional: Intersection Observer for performance optimization
		// Only update when the block is in view
		if ('IntersectionObserver' in window) {
			const observer = new IntersectionObserver(
				(entries) => {
					entries.forEach((entry) => {
						if (entry.isIntersecting) {
							updateActiveSlide();
						}
					});
				},
				{
					threshold: 0,
					rootMargin: '100px',
				}
			);

			observer.observe(block);
		}
	});
});
