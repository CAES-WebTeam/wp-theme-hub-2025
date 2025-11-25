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
  
  /*** START PARVUS SAFARI FLASH DEBUG */
  console.log('🔍 Parvus Safari Flash Debugger Loaded');
  
  // Detect Safari
  const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
  console.log('Browser:', isSafari ? '🟢 Safari' : '⚪ Other');
  
  // Store timing data
  const timingData = {
    events: [],
    paints: [],
    styles: []
  };
  
  // Monitor performance entries (paints, compositing)
  if ('PerformanceObserver' in window) {
    try {
      const paintObserver = new PerformanceObserver((list) => {
        list.getEntries().forEach((entry) => {
          timingData.paints.push({
            time: entry.startTime,
            name: entry.name,
            duration: entry.duration
          });
          console.log('🎨 Paint Event:', entry.name, 'at', entry.startTime.toFixed(2) + 'ms');
        });
      });
      paintObserver.observe({ entryTypes: ['paint', 'measure'] });
    } catch (e) {
      console.warn('PerformanceObserver not supported:', e);
    }
  }
  
  // Monitor computed styles helper
  function captureComputedStyles(element, label) {
    if (!element) return;
    const styles = window.getComputedStyle(element);
    const data = {
      time: performance.now(),
      label: label,
      element: element.className,
      transform: styles.transform,
      opacity: styles.opacity,
      visibility: styles.visibility,
      display: styles.display,
      backgroundColor: styles.backgroundColor,
      willChange: styles.willChange,
      backfaceVisibility: styles.backfaceVisibility,
      isolation: styles.isolation
    };
    timingData.styles.push(data);
    console.log('🎨 Computed Styles:', label, data);
    return data;
  }
  
  // Wait for Parvus to be ready
  setTimeout(() => {
    const parvusContainer = document.querySelector('.parvus');
    const parvusOverlay = document.querySelector('.parvus__overlay');
    const parvusSlider = document.querySelector('.parvus__slider');
    
    if (parvusContainer) {
      console.log('✅ Found Parvus container');
      
      // Track transitions
      parvusContainer.addEventListener('transitionstart', (e) => {
        console.log('🚀 TRANSITION START:', {
          property: e.propertyName,
          target: e.target.className,
          elapsedTime: e.elapsedTime
        });
        
        captureComputedStyles(parvusContainer, 'Container at transition start');
        captureComputedStyles(parvusOverlay, 'Overlay at transition start');
        captureComputedStyles(parvusSlider, 'Slider at transition start');
      }, true);
      
      parvusContainer.addEventListener('transitionend', (e) => {
        console.log('🏁 TRANSITION END:', {
          property: e.propertyName,
          target: e.target.className,
          elapsedTime: e.elapsedTime
        });
        
        captureComputedStyles(parvusContainer, 'Container at transition end');
        captureComputedStyles(parvusOverlay, 'Overlay at transition end');
        captureComputedStyles(parvusSlider, 'Slider at transition end');
      }, true);
      
      // Track class changes
      const classObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
          if (mutation.attributeName === 'class' || mutation.attributeName === 'style') {
            console.log('🏷️ ATTRIBUTE CHANGE:', {
              element: mutation.target.className,
              attribute: mutation.attributeName,
              old: mutation.oldValue,
              new: mutation.target.getAttribute(mutation.attributeName)
            });
            
            setTimeout(() => {
              captureComputedStyles(parvusContainer, 'Container after change');
              captureComputedStyles(parvusOverlay, 'Overlay after change');
              captureComputedStyles(parvusSlider, 'Slider after change');
            }, 0);
          }
        });
      });
      
      classObserver.observe(parvusContainer, {
        attributes: true,
        attributeOldValue: true,
        attributeFilter: ['class', 'style']
      });
      
      if (parvusOverlay) {
        classObserver.observe(parvusOverlay, {
          attributes: true,
          attributeOldValue: true,
          attributeFilter: ['class', 'style']
        });
      }
      
      if (parvusSlider) {
        classObserver.observe(parvusSlider, {
          attributes: true,
          attributeOldValue: true,
          attributeFilter: ['class', 'style']
        });
      }
    } else {
      console.warn('❌ Parvus container not found');
    }
    
    // Monitor all lightbox triggers
    document.querySelectorAll('.lightbox, .parvus-trigger').forEach((trigger) => {
      trigger.addEventListener('click', (e) => {
        console.log('👆 LIGHTBOX CLICKED:', {
          src: e.currentTarget.href,
          time: performance.now()
        });
        
        // Capture pre-open state
        setTimeout(() => {
          const container = document.querySelector('.parvus');
          const overlay = document.querySelector('.parvus__overlay');
          const slider = document.querySelector('.parvus__slider');
          
          captureComputedStyles(container, 'Container immediately after click');
          captureComputedStyles(overlay, 'Overlay immediately after click');
          captureComputedStyles(slider, 'Slider immediately after click');
        }, 10);
      });
    });
  }, 1000);
  
  // Export debug data to console
  window.getParvusDebugData = () => {
    console.log('📊 === PARVUS DEBUG REPORT ===');
    console.log('Total Events:', timingData.events.length);
    console.log('Total Paints:', timingData.paints.length);
    console.log('Total Style Captures:', timingData.styles.length);
    console.log('\n📋 Full Data:', timingData);
    
    return timingData;
  };
  
  console.log('💡 TIP: After seeing the flash, run window.getParvusDebugData() in console');
  /*** END PARVUS SAFARI FLASH DEBUG */
  
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