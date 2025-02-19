/******/ (() => { // webpackBootstrap
/*!********************************!*\
  !*** ./src/blocks/toc/view.js ***!
  \********************************/
window.addEventListener('load', function () {
  /** ADDING IDs TO HEADINGS */
  // Function to generate a slug from a string
  function slugify(text) {
    return text.toString().trim().toLowerCase().replace(/\s+/g, '-') // Replace spaces with dashes
    .replace(/[^\w\-]+/g, '') // Remove all non-word characters
    .replace(/\-\-+/g, '-') // Replace multiple dashes with a single dash
    .replace(/^-+/, '') // Trim dashes from the start
    .replace(/-+$/, ''); // Trim dashes from the end
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

  // Clone the original TOC to create a sticky version
  const stickyToc = originalToc.cloneNode(true);
  stickyToc.classList.add('sticky-toc');
  // Add aria-hidden attribute to the sticky TOC
  stickyToc.setAttribute('aria-hidden', 'true');
  // Set tabindex to -1 to prevent focus
  stickyToc.setAttribute('tabindex', '-1');
  // Add sticky TOC to the main element
  const mainElement = document.querySelector('main');
  mainElement.appendChild(stickyToc);

  // Only apply on desktop
  function isDesktop() {
    return window.innerWidth > 768; // Adjust breakpoint as needed
  }

  // Flags to track intersection states
  let isPostVisible = false;
  let isFeaturedImageOutOfView = false;
  let isOriginalTocOutOfView = false;

  // Observer for detecting when .caes-hub-content-post enters or exits the viewport
  const postElement = document.querySelector('.caes-hub-content-post');
  if (postElement) {
    const postObserver = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        isPostVisible = entry.isIntersecting;
        updateStickyTocVisibility();
      });
    }, {
      threshold: 0
    } // Trigger when any part of the post enters/exits the viewport
    );
    postObserver.observe(postElement);
  }

  // Observer for detecting when the featured image leaves the viewport
  const featuredImage = document.querySelector('.caes-hub-content-f-img');
  if (featuredImage) {
    const featuredImageObserver = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        isFeaturedImageOutOfView = !entry.isIntersecting;
        updateStickyTocVisibility();
      });
    }, {
      threshold: 0
    } // Trigger when any part of the featured image enters/exits the viewport
    );
    featuredImageObserver.observe(featuredImage);
  }

  // Observer for detecting when the original TOC leaves the viewport
  const tocObserver = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      isOriginalTocOutOfView = !entry.isIntersecting;
      updateStickyTocVisibility();
    });
  }, {
    threshold: 0
  } // Trigger when any part of the TOC enters/exits the viewport
  );
  tocObserver.observe(originalToc);

  // Function to update sticky TOC visibility
  function updateStickyTocVisibility() {
    if (!isDesktop()) {
      stickyToc.style.display = 'none';
      return;
    }
    if (isPostVisible && isOriginalTocOutOfView && isFeaturedImageOutOfView) {
      // Show sticky TOC
      const mainRect = mainElement.getBoundingClientRect();
      stickyToc.style.display = 'block';
      stickyToc.style.position = 'fixed';
      stickyToc.style.left = `${mainRect.left}px`;
      stickyToc.style.top = 'var(--wp--style--block-gap)'; // Adjust top offset
      stickyToc.style.transform = 'translateX(0)';
      stickyToc.style.transition = 'transform 0.3s ease-in-out';
    } else {
      // Hide sticky TOC
      stickyToc.style.transform = 'translateX(-200%)';
    }
  }

  // Handle resize to toggle sticky TOC visibility and update position dynamically
  function handleResize() {
    const mainElement = document.querySelector('main');
    if (!mainElement) return;
    if (!isDesktop()) {
      stickyToc.style.display = 'none';
    } else {
      const mainRect = mainElement.getBoundingClientRect();
      stickyToc.style.left = `${mainRect.left}px`;
      updateStickyTocVisibility();
    }
  }
  window.addEventListener('resize', handleResize);

  // Initialize visibility on load
  handleResize();
  /** END ADDING A STICKY TOC WHEN USER SCROLLS PASSED THE ORIGINAL TOC  */

  /** ADDING SMOOTH SCROLL */
  const tocLinks = document.querySelectorAll('a[data-smooth-scroll="true"]');
  tocLinks.forEach(link => {
    link.addEventListener('click', function (event) {
      event.preventDefault();
      const targetId = this.getAttribute('href').substring(1);
      const targetElement = document.getElementById(targetId);
      if (targetElement) {
        targetElement.scrollIntoView({
          behavior: 'smooth'
        });
        const observer = new IntersectionObserver((entries, observer) => {
          entries.forEach(entry => {
            if (entry.isIntersecting) {
              targetElement.setAttribute('tabindex', '-1');
              targetElement.focus();
              targetElement.addEventListener('blur', () => {
                targetElement.removeAttribute('tabindex');
              }, {
                once: true
              });
              observer.disconnect();
            }
          });
        }, {
          threshold: 1.0
        });
        observer.observe(targetElement);
      }
    });
  });
  /** END ADDING SMOOTH SCROLL */

  /** HIGHLIGHT ACTIVE TOC ITEM BASED ON SCROLL */
  const tocLinksMap = new Map(); // Map heading IDs to TOC list items

  // Map TOC links to their corresponding headings
  document.querySelectorAll('.wp-block-caes-hub-toc a[href^="#"]').forEach(link => {
    const targetId = link.getAttribute('href').substring(1);
    const targetHeading = document.getElementById(targetId);
    if (targetHeading) {
      tocLinksMap.set(targetId, link.parentElement); // Store the <li> element
    }
  });

  // Observer to track heading visibility
  const headingObserver = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      const id = entry.target.id;
      const tocItem = tocLinksMap.get(id);
      if (tocItem) {
        if (entry.isIntersecting) {
          // Add active class when heading is in view
          document.querySelectorAll('.wp-block-caes-hub-toc li').forEach(item => item.classList.remove('active'));
          tocItem.classList.add('active');
        }
      }
    });
  }, {
    threshold: 0.5
  } // Trigger when at least 50% of the heading is in view
  );

  // Observe all headings
  headings.forEach(heading => headingObserver.observe(heading));
  /** END HIGHLIGHT ACTIVE TOC ITEM BASED ON SCROLL */
});
/******/ })()
;
//# sourceMappingURL=view.js.map