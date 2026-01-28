/******/ (() => { // webpackBootstrap
/*!***********************************!*\
  !*** ./src/blocks/reveal/view.js ***!
  \***********************************/
/**
 * Reveal Block Frontend JavaScript
 *
 * Handles scroll-triggered frame transitions.
 * The CSS uses clip-path to handle the visual "window" effect,
 * so JS only needs to manage frame transitions based on scroll position.
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
    const frames = block.querySelectorAll('.reveal-frame');
    if (frames.length === 0) {
      return;
    }
    const frameCount = frames.length;
    let currentFrameIndex = 0;
    let ticking = false;

    /**
     * Apply transition to a frame
     *
     * @param {HTMLElement} frame     The frame element
     * @param {boolean}     isActive  Whether frame should be active
     */
    function applyTransition(frame, isActive) {
      const transitionType = frame.dataset.transitionType || 'fade';
      const transitionSpeed = parseInt(frame.dataset.transitionSpeed, 10) || 500;

      // Set transition duration (or 0 for reduced motion)
      const duration = prefersReducedMotion ? 0 : transitionSpeed;
      frame.style.transitionDuration = `${duration}ms`;
      if (isActive) {
        // Set initial position based on transition type (before animating in)
        let initialTransform = '';
        switch (transitionType) {
          case 'up':
            initialTransform = 'translateY(100%)';
            break;
          case 'down':
            initialTransform = 'translateY(-100%)';
            break;
          case 'left':
            initialTransform = 'translateX(100%)';
            break;
          case 'right':
            initialTransform = 'translateX(-100%)';
            break;
          default:
            initialTransform = '';
        }
        if (initialTransform && !prefersReducedMotion) {
          // Disable transition temporarily to set initial position
          frame.style.transitionDuration = '0ms';
          frame.style.transform = initialTransform;
          frame.style.opacity = '0';

          // Force reflow to apply initial state
          frame.offsetHeight;

          // Re-enable transition and animate to final position
          frame.style.transitionDuration = `${duration}ms`;
        }

        // Animate to visible, centered position
        frame.classList.add('is-active');
        frame.style.opacity = '1';
        frame.style.transform = 'translate(0, 0)';
      } else {
        // Fade out (no directional exit, just opacity)
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
      const blockTop = blockRect.top;
      const blockHeight = block.offsetHeight;

      // Calculate scroll progress through the block
      // Progress is 0 when block top is at viewport top
      // Progress is 1 when block bottom is at viewport bottom
      const scrollableDistance = blockHeight - viewportHeight;
      if (scrollableDistance <= 0) {
        ticking = false;
        return;
      }

      // How far we've scrolled into the block (blockTop is negative when scrolled past top)
      const scrolledDistance = Math.max(0, -blockTop);
      const scrollProgress = Math.min(1, scrolledDistance / scrollableDistance);

      // Determine which frame should be active
      const newFrameIndex = Math.min(frameCount - 1, Math.floor(scrollProgress * frameCount));

      // Update frames if index changed
      if (newFrameIndex !== currentFrameIndex) {
        frames.forEach((frame, index) => {
          applyTransition(frame, index === newFrameIndex);
        });
        currentFrameIndex = newFrameIndex;
      }
      ticking = false;
    }

    /**
     * Handle scroll events with requestAnimationFrame throttling
     */
    function onScroll() {
      if (!ticking) {
        window.requestAnimationFrame(updateActiveFrame);
        ticking = true;
      }
    }

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

    // Initial scroll state check
    updateActiveFrame();

    // Store cleanup function on element for potential future use
    block._revealCleanup = function () {
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