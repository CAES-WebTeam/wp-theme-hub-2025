// Import Parvus
import Parvus from 'parvus';

/*** SAFARI PARVUS FLASH FIX - RUN IMMEDIATELY */
(function () {
  const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);

  if (isSafari) {
    // Inject CSS immediately
    const style = document.createElement('style');
    style.id = 'safari-parvus-fix';
    style.textContent = `
      body {
        -webkit-transform: translate3d(0, 0, 0) !important;
        transform: translate3d(0, 0, 0) !important;
        -webkit-backface-visibility: hidden !important;
        backface-visibility: hidden !important;
      }
      
      ::view-transition,
      ::view-transition-group(*),
      ::view-transition-image-pair(*),
      ::view-transition-old(*),
      ::view-transition-new(*) {
        display: none !important;
      }
    `;
    (document.head || document.documentElement).appendChild(style);
  }
})();
/*** END SAFARI FIX */

// Handle responsive tables function

// Wrap all tables in a responsitable-wrapper
function wrapResponsiveTables() {
  const tables = document.querySelectorAll('table');

  tables.forEach((table) => {
    const figure = table.closest('figure');
    const target = figure || table;

    // Skip if already wrapped
    if (target.parentElement.classList.contains('responsitable-wrapper')) return;

    const wrapper = document.createElement('div');
    wrapper.classList.add('responsitable-wrapper');

    target.parentNode.insertBefore(wrapper, target);
    wrapper.appendChild(target);
  });
}

function handleOverflowScroll() {
  const wrappers = document.querySelectorAll('.responsitable-wrapper');

  wrappers.forEach((wrapper) => {
    const table = wrapper.querySelector('table');
    if (!table) return;

    // Reset scroll class
    wrapper.classList.remove('responsitable-scroll');

    // Check for overflow
    if (table.scrollWidth > wrapper.clientWidth) {
      wrapper.classList.add('responsitable-scroll');
    }
  });
}
// End responsive tables function

document.addEventListener('DOMContentLoaded', function () {

  /*** START TO TOP BUTTON */
  const toTopButton = document.createElement('a');
  toTopButton.classList.add('caes-hub-to-top');
  toTopButton.href = '#';
  toTopButton.innerHTML = `<span>Back to top</span>`;

  const main = document.querySelector('main');
  main.appendChild(toTopButton);

  // Check if Shorthand, Motion Scroll, or Reveal caption is currently visible and in viewport
  function isCaptionVisible() {
    // Check for Shorthand captions (these are fixed/floating)
    const shorthandCaptions = document.querySelectorAll('.MediaRenderer__fixedCaption');
    for (const caption of shorthandCaptions) {
      const style = window.getComputedStyle(caption);
      if (style.display !== 'none' && parseFloat(style.opacity) > 0) {
        return true;
      }
    }

    // Check for Motion Scroll and Reveal captions (these scroll with content)
    // Only hide button if caption is in the lower portion of viewport where button appears
    const scrollCaptionSelectors = [
      '.motion-scroll-caption',
      '.motion-scroll-image-caption',
      '.reveal-frame-caption'
    ];

    const viewportHeight = window.innerHeight;
    const buttonZone = viewportHeight * 0.3; // Bottom 30% of viewport where button appears

    for (const selector of scrollCaptionSelectors) {
      const captions = document.querySelectorAll(selector);
      for (const caption of captions) {
        const style = window.getComputedStyle(caption);
        if (style.display !== 'none' && parseFloat(style.opacity) > 0) {
          const rect = caption.getBoundingClientRect();
          // Check if caption is in viewport and in the bottom zone where button appears
          if (rect.top < viewportHeight && rect.bottom > (viewportHeight - buttonZone)) {
            return true;
          }
        }
      }
    }
    return false;
  }

  // Update button visibility based on scroll AND caption state
  function updateToTopVisibility() {
    const captionVisible = isCaptionVisible();

    if (captionVisible) {
      toTopButton.classList.add('caes-hub-to-top-caption-hidden');
    } else {
      toTopButton.classList.remove('caes-hub-to-top-caption-hidden');
    }
  }

  // Watch for caption changes (Shorthand, Motion Scroll, Reveal)
  const captionObserver = new MutationObserver(updateToTopVisibility);
  const captionSelectors = [
    '.MediaRenderer__fixedCaption',
    '.motion-scroll-caption',
    '.motion-scroll-image-caption',
    '.reveal-frame-caption'
  ];

  // Observe existing captions
  captionSelectors.forEach(selector => {
    document.querySelectorAll(selector).forEach(caption => {
      captionObserver.observe(caption, {
        attributes: true,
        attributeFilter: ['style', 'class']
      });
    });
  });

  // Also watch for dynamically added captions
  const bodyObserver = new MutationObserver((mutations) => {
    mutations.forEach(mutation => {
      mutation.addedNodes.forEach(node => {
        if (node.nodeType === 1) {
          captionSelectors.forEach(selector => {
            const captions = node.querySelectorAll?.(selector) || [];
            captions.forEach(caption => {
              captionObserver.observe(caption, {
                attributes: true,
                attributeFilter: ['style', 'class']
              });
            });
            if (node.classList?.contains(selector.replace('.', ''))) {
              captionObserver.observe(node, {
                attributes: true,
                attributeFilter: ['style', 'class']
              });
            }
          });
        }
      });
    });
    updateToTopVisibility();
  });

  bodyObserver.observe(document.body, { childList: true, subtree: true });

  window.addEventListener('scroll', function () {
    updateToTopVisibility(); // Also check on scroll

    if (window.scrollY > window.innerHeight / 2) {
      if (!toTopButton.classList.contains('caes-hub-to-top-visible')) {
        toTopButton.classList.add('caes-hub-to-top-visible');
      }
    } else {
      if (toTopButton.classList.contains('caes-hub-to-top-visible')) {
        toTopButton.classList.add('caes-hub-to-top-hide');
        setTimeout(() => {
          toTopButton.classList.remove('caes-hub-to-top-visible', 'caes-hub-to-top-hide');
        }, 250);
      }
    }
  });

  toTopButton.addEventListener('click', function (event) {
    event.preventDefault();
    window.scrollTo({
      top: 0,
      behavior: 'smooth'
    });
  });
  /*** END TO TOP BUTTON */

  /*** REMOVE EMPTY PARAGRAPHS */
  const contentContainers = document.querySelectorAll('.post, .entry-content');
  contentContainers.forEach(container => {
    const paragraphs = container.querySelectorAll('p');
    paragraphs.forEach(p => {
      const html = p.innerHTML.replace(/<br\s*\/?>/gi, '').replace(/&#13;/g, '').replace(/&nbsp;/gi, '').trim();
      if (html === '') {
        p.remove();
      }
    });
  });
  /*** END REMOVE EMPTY PARAGRAPHS */

  /*** START PARVUS LIGHTBOX INITIALIZATION */
  // Find all image blocks that link to images and add lightbox class
  const linkedImgs = document.querySelectorAll('.wp-block-image a[href*=".webp"],.wp-block-image a[href*=".jpg"],.wp-block-image a[href*=".jpeg"],.wp-block-image a[href*=".png"],.wp-block-image a[href*=".gif"]');

  for (const link of linkedImgs) {
    link.classList.add('lightbox');

    // Get sibling figcaption if it exists
    const sibling = link.nextElementSibling;
    if (sibling && sibling.classList.contains('wp-element-caption')) {
      const caption = sibling.innerHTML;
      link.setAttribute('data-caption', caption);
    }
  }

  // Initialize Parvus for galleries
  const prvs = new Parvus({
    gallerySelector: '.wp-block-gallery, .parvus-gallery'
  });

  /*** END PARVUS LIGHTBOX INITIALIZATION */

  /*** HANDLE LEGACY CONTENT */
  const classicWrapper = document.querySelector(".classic-content-wrapper");

  if (classicWrapper) {
    const classicH2s = classicWrapper.querySelectorAll("h2");

    classicH2s.forEach((h2) => {
      h2.classList.add("is-style-caes-hub-full-underline");

      h2.querySelectorAll("strong").forEach((strong) => {
        while (strong.firstChild) {
          strong.parentNode.insertBefore(strong.firstChild, strong);
        }
        strong.remove();
      });
    });
  }

  /** Responsive tables on page load */
  wrapResponsiveTables();
  handleOverflowScroll();

  // Saved Posts
  const saveButtons = document.querySelectorAll('.btn-save');

  saveButtons.forEach(button => {
    button.addEventListener('click', function () {
      const postId = this.getAttribute('data-id');
      const postType = this.getAttribute('data-type');

      let saved = JSON.parse(localStorage.getItem('savedPosts')) || {};

      if (!saved[postType]) saved[postType] = [];

      // Toggle save/remove
      if (saved[postType].includes(postId)) {
        saved[postType] = saved[postType].filter(id => id !== postId);
        this.classList.remove('saved');
      } else {
        saved[postType].push(postId);
        this.classList.add('saved');
      }

      // Save back to localStorage
      localStorage.setItem('savedPosts', JSON.stringify(saved));
    });
  });

  // Add no-smooth-scroll class to html if the hash is #expert-advice
  if (window.location.hash === '#expert-advice') {
    document.documentElement.classList.add('no-smooth-scroll');

    const el = document.getElementById('expert-advice');
    if (el) {
      el.scrollIntoView(); // Instant scroll
    }

    // Remove the class immediately
    setTimeout(() => {
      document.documentElement.classList.remove('no-smooth-scroll');
    }, 0);
  }

});

// Recheck for responsive tables on window resize
window.addEventListener('resize', handleOverflowScroll);