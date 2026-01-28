/******/ (() => { // webpackBootstrap
/*!***********************************!*\
  !*** ./src/blocks/reveal/view.js ***!
  \***********************************/
/**
 * Reveal Block Frontend JavaScript
 *
 * Handles scroll-triggered frame transitions with support for
 * multiple transition types and reduced motion preferences.
 */

(function () {
  'use strict';

  // Check reduced motion preference
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /**
   * Initialize a single Reveal block
   *
   * @param {HTMLElement} block The reveal block element
   */
  function initRevealBlock(block) {
    const background = block.querySelector('.reveal-background');
    const content = block.querySelector('.reveal-content');
    const frames = block.querySelectorAll('.reveal-frame');
    if (!background || !content || frames.length === 0) {
      return;
    }
    const frameCount = frames.length;
    let currentFrameIndex = 0;
    let isInView = false;
    let ticking = false;

    /**
     * Apply transition to a frame
     *
     * @param {HTMLElement} frame     The frame element
     * @param {boolean}     isActive  Whether frame should be active
     * @param {boolean}     isEntering Whether frame is entering (vs leaving)
     */
    function applyTransition(frame, isActive, isEntering) {
      const transitionType = frame.dataset.transitionType || 'fade';
      const transitionSpeed = parseInt(frame.dataset.transitionSpeed, 10) || 500;

      // Set transition duration (or 0 for reduced motion)
      const duration = prefersReducedMotion ? 0 : transitionSpeed;
      frame.style.transitionDuration = `${duration}ms`;

      // Reset transform
      frame.style.transform = '';
      if (isActive) {
        frame.classList.add('is-active');
        frame.style.opacity = '1';

        // Apply entrance animation
        if (!prefersReducedMotion && transitionType !== 'none' && transitionType !== 'fade') {
          // Set initial position for entrance
          const startPositions = {
            up: 'translateY(100%)',
            down: 'translateY(-100%)',
            left: 'translateX(100%)',
            right: 'translateX(-100%)'
          };
          if (startPositions[transitionType]) {
            // Start from offset position
            frame.style.transform = startPositions[transitionType];
            // Force reflow
            frame.offsetHeight; // eslint-disable-line no-unused-expressions
            // Animate to center
            frame.style.transform = 'translate(0, 0)';
          }
        }
      } else {
        frame.classList.remove('is-active');
        frame.style.opacity = '0';
      }
    }

    /**
     * Update active frame based on scroll position
     */
    function updateActiveFrame() {
      const blockRect = block.getBoundingClientRect();
      const viewportHeight = window.innerHeight;

      // Calculate scroll progress through the block
      // 0 = block just entered viewport at bottom
      // 1 = block has left viewport at top
      const blockHeight = block.offsetHeight;
      const scrollStart = viewportHeight; // Block top at viewport bottom
      const scrollEnd = -blockHeight; // Block bottom at viewport top
      const scrollRange = scrollStart - scrollEnd;
      const currentScroll = blockRect.top;
      const scrollProgress = Math.max(0, Math.min(1, (scrollStart - currentScroll) / scrollRange));

      // Determine which frame should be active
      // Divide scroll progress into equal segments for each frame
      const newFrameIndex = Math.min(frameCount - 1, Math.floor(scrollProgress * frameCount));

      // Update frames if index changed
      if (newFrameIndex !== currentFrameIndex) {
        const isScrollingDown = newFrameIndex > currentFrameIndex;
        frames.forEach((frame, index) => {
          const isActive = index === newFrameIndex;
          const wasActive = index === currentFrameIndex;
          if (isActive || wasActive) {
            applyTransition(frame, isActive, isActive && isScrollingDown);
          }
        });
        currentFrameIndex = newFrameIndex;
      }
      ticking = false;
    }

    /**
     * Handle scroll events with requestAnimationFrame throttling
     */
    function onScroll() {
      if (!isInView) {
        return;
      }
      if (!ticking) {
        window.requestAnimationFrame(updateActiveFrame);
        ticking = true;
      }
    }

    /**
     * Handle block entering/leaving viewport
     *
     * @param {IntersectionObserverEntry[]} entries Observer entries
     */
    function onIntersection(entries) {
      entries.forEach(entry => {
        isInView = entry.isIntersecting;
        if (isInView) {
          // Block is in viewport - fix background
          background.classList.add('is-fixed');
          // Trigger initial frame update
          updateActiveFrame();
        } else {
          // Block left viewport - unfix background
          background.classList.remove('is-fixed');

          // Reset to first or last frame based on scroll direction
          const blockRect = block.getBoundingClientRect();
          const resetToFirst = blockRect.top > window.innerHeight;
          frames.forEach((frame, index) => {
            const shouldBeActive = resetToFirst ? index === 0 : index === frameCount - 1;
            frame.classList.toggle('is-active', shouldBeActive);
            frame.style.opacity = shouldBeActive ? '1' : '0';
            frame.style.transform = '';
          });
          currentFrameIndex = resetToFirst ? 0 : frameCount - 1;
        }
      });
    }

    // Set up Intersection Observer
    const observer = new IntersectionObserver(onIntersection, {
      threshold: 0,
      rootMargin: '0px'
    });
    observer.observe(block);

    // Add scroll listener
    window.addEventListener('scroll', onScroll, {
      passive: true
    });

    // Initial state - ensure first frame is visible
    frames.forEach((frame, index) => {
      if (index === 0) {
        frame.classList.add('is-active');
        frame.style.opacity = '1';
      } else {
        frame.classList.remove('is-active');
        frame.style.opacity = '0';
      }
    });

    // Store cleanup function on element for potential future use
    block._revealCleanup = function () {
      observer.disconnect();
      window.removeEventListener('scroll', onScroll);
    };
  }

  /**
   * Initialize all Reveal blocks on the page
   */
  function initAllBlocks() {
    const blocks = document.querySelectorAll('.caes-reveal');
    blocks.forEach(initRevealBlock);
  }

  // Initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAllBlocks);
  } else {
    initAllBlocks();
  }
})();
/******/ })()
;
//# sourceMappingURL=view.js.map