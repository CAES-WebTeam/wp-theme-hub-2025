// Import Parvus
import Parvus from 'parvus';

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

  window.addEventListener('scroll', function () {
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
  
  /*** START SAFARI PARVUS FLASH FIX */
  // Only run in Safari
  const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
  console.log('🔍 Safari Detection:', isSafari ? 'YES - Safari' : 'NO - Not Safari');
  
  if (isSafari) {
    console.log('✅ Safari fix activated');
    
    // Add CSS for Safari body GPU fix when Parvus is open
    const style = document.createElement('style');
    style.id = 'safari-parvus-fix';
    style.textContent = `
      body.parvus-is-open {
        -webkit-transform: translate3d(0, 0, 0) !important;
        transform: translate3d(0, 0, 0) !important;
        -webkit-backface-visibility: hidden !important;
        backface-visibility: hidden !important;
      }
      
      body.parvus-disable-transitions ::view-transition,
      body.parvus-disable-transitions ::view-transition-group(*),
      body.parvus-disable-transitions ::view-transition-image-pair(*),
      body.parvus-disable-transitions ::view-transition-old(*),
      body.parvus-disable-transitions ::view-transition-new(*) {
        display: none !important;
      }
    `;
    document.head.appendChild(style);
    console.log('✅ Safari fix CSS injected');
    
    // Function to attach observer to Parvus element
    function attachParvusObserver(parvusElement) {
      console.log('🎯 Attaching observer to Parvus element');
      
      const parvusObserver = new MutationObserver(() => {
        const isOpen = parvusElement.hasAttribute('open');
        console.log('🔔 Parvus open attribute changed:', isOpen ? 'OPEN' : 'CLOSED');
        
        if (isOpen) {
          console.log('➕ Adding body classes: parvus-is-open, parvus-disable-transitions');
          document.body.classList.add('parvus-is-open', 'parvus-disable-transitions');
          console.log('Body classes now:', document.body.className);
        } else {
          console.log('⏱️ Waiting 350ms before removing body classes...');
          setTimeout(() => {
            console.log('➖ Removing body classes');
            document.body.classList.remove('parvus-is-open', 'parvus-disable-transitions');
            console.log('Body classes now:', document.body.className);
          }, 350);
        }
      });
      
      parvusObserver.observe(parvusElement, {
        attributes: true,
        attributeFilter: ['open']
      });
      console.log('👀 Now observing Parvus open attribute');
      
      // Check immediately if already open
      if (parvusElement.hasAttribute('open')) {
        console.log('⚡ Parvus is already open!');
        document.body.classList.add('parvus-is-open', 'parvus-disable-transitions');
      }
    }
    
    // Check if Parvus already exists on page load
    console.log('🔎 Checking if Parvus already exists in DOM...');
    const existingParvus = document.querySelector('.parvus');
    if (existingParvus) {
      console.log('✅ Found existing Parvus element!');
      attachParvusObserver(existingParvus);
    } else {
      console.log('❌ No existing Parvus element found');
    }
    
    // Also watch for Parvus being added dynamically
    const bodyObserver = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
          console.log('🔍 Node added to DOM:', node.nodeName, node.className);
          
          if (node.classList && node.classList.contains('parvus')) {
            console.log('🎯 Parvus element detected in DOM!');
            attachParvusObserver(node);
          }
        });
      });
    });
    
    bodyObserver.observe(document.body, {
      childList: true,
      subtree: true
    });
    console.log('👀 Now watching for Parvus to be added to DOM');
  } else {
    console.log('⏭️ Skipping Safari fix (not Safari)');
  }
  /*** END SAFARI PARVUS FLASH FIX */
  
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