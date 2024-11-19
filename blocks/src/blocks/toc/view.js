window.addEventListener('load', function () {
	
	/** ADDING IDs TO HEADINGS */
	// Function to generate a slug from a string
	function slugify(text) {
		return text
			.toString()
			.trim()
			.toLowerCase()
			.replace(/\s+/g, '-')       // Replace spaces with dashes
			.replace(/[^\w\-]+/g, '')   // Remove all non-word characters
			.replace(/\-\-+/g, '-')     // Replace multiple dashes with a single dash
			.replace(/^-+/, '')         // Trim dashes from the start
			.replace(/-+$/, '');        // Trim dashes from the end
	}

	// Function to ensure unique IDs
	function ensureUniqueId(id, existingIds) {
		let uniqueId = id;
		let counter = 0;

		while (existingIds.has(uniqueId)) {
			counter++;
			uniqueId = `${id}-${counter}`;
		}

		existingIds.add(uniqueId);
		return uniqueId;
	}

	// Grab the headings and add IDs
	const allHeadings = document.querySelectorAll('.caes-hub-content-post h2, .caes-hub-content-post h3, .caes-hub-content-post h4, .caes-hub-content-post h5, .caes-hub-content-post h6');
	const headings = Array.from(allHeadings).filter(heading => {
		return !(heading.tagName === 'H2' && heading.closest('.wp-block-caes-hub-toc'));
	});

	const existingIds = new Set();

	headings.forEach(heading => {
		let id = heading.id;

		if (!id) {
			const text = heading.textContent || 'heading';
			id = slugify(text);
			heading.id = id;
		}

		heading.id = ensureUniqueId(id, existingIds);
	});
	/** END ADDING IDs TO HEADINGS */

	/** ADDING A STICKY TOC WHEN USER SCROLLS PASSED THE ORIGINAL TOC  */
	const originalToc = document.querySelector('.wp-block-caes-hub-toc');
	if (!originalToc) return;

	const stickyToc = originalToc.cloneNode(true);
	stickyToc.classList.add('sticky-toc');

	// Insert sticky TOC right after the original TOC
	originalToc.insertAdjacentElement('afterend', stickyToc);

	// Only apply on desktop
	function isDesktop() {
		return window.innerWidth > 768; // Adjust breakpoint as needed
	}

	let isStickyVisible = false;

	let isStickyHidden = false; // New flag to track if TOC is hidden at the bottom of the content

	function handleScroll() {
		const tocRect = originalToc.getBoundingClientRect();
		const mainElement = document.querySelector('main');
		const mainRect = mainElement.getBoundingClientRect();
		const contentElement = document.querySelector('.caes-hub-content');
		const contentRect = contentElement.getBoundingClientRect();
		const stickyTocRect = stickyToc.getBoundingClientRect();

		// Show sticky TOC when original TOC scrolls out of view and not hidden by content bottom
		if (isDesktop() && tocRect.bottom < 0 && !isStickyVisible && !isStickyHidden) {
			stickyToc.style.position = 'fixed';
			stickyToc.style.left = `${mainRect.left}px`;
			stickyToc.style.top = 'var(--wp--style--block-gap)'; // Adjust top offset as needed
			stickyToc.style.transform = 'translateX(0)';
			stickyToc.style.transition = 'transform 0.3s ease-in-out';
			isStickyVisible = true;
		}
		// Hide sticky TOC when the original TOC is visible
		else if (isStickyVisible && tocRect.bottom >= 0) {
			stickyToc.style.transform = 'translateX(-200%)'; // Move fully out of view
			isStickyVisible = false;
			isStickyHidden = false; // Reset hidden state when original TOC is back
		}
		// Hide sticky TOC when it reaches the bottom of the content
		else if (isStickyVisible && stickyTocRect.bottom >= contentRect.bottom) {
			stickyToc.style.transform = 'translateX(-200%)'; // Slide out
			isStickyVisible = false;
			isStickyHidden = true; // Mark as hidden due to reaching content bottom
		}
		// Handle scrolling back up: Show sticky TOC if original TOC is out of view and not hidden
		else if (isStickyHidden && tocRect.bottom < 0 && stickyTocRect.bottom < contentRect.bottom) {
			stickyToc.style.transform = 'translateX(0)'; // Bring back into view
			isStickyVisible = true;
			isStickyHidden = false; // Reset hidden state
		}
	}


	function handleResize() {
		if (!isDesktop()) {
			stickyToc.style.display = 'none';
		} else {
			stickyToc.style.display = 'block';
		}
	}

	window.addEventListener('scroll', handleScroll);
	window.addEventListener('resize', handleResize);

	// Initialize visibility on load
	handleResize();
	handleScroll();
	/** END ADDING A STICKY TOC WHEN USER SCROLLS PASSED THE ORIGINAL TOC  */

	/** ADDING SMOOTH SCROLL */
	const tocLinks = document.querySelectorAll('a[data-smooth-scroll="true"]');
	tocLinks.forEach(link => {
		link.addEventListener('click', function (event) {
			event.preventDefault();
			const targetId = this.getAttribute('href').substring(1);
			const targetElement = document.getElementById(targetId);

			if (targetElement) {
				targetElement.scrollIntoView({ behavior: 'smooth' });

				const observer = new IntersectionObserver((entries, observer) => {
					entries.forEach(entry => {
						if (entry.isIntersecting) {
							targetElement.setAttribute('tabindex', '-1');
							targetElement.focus();
							targetElement.addEventListener('blur', () => {
								targetElement.removeAttribute('tabindex');
							}, { once: true });
							observer.disconnect();
						}
					});
				}, { threshold: 1.0 });

				observer.observe(targetElement);
			}
		});
	});
	/** END ADDING SMOOTH SCROLL */

});