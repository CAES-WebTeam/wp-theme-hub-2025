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
    let ticking = false;

    /**
     * Calculate styles for the entering frame based on type and progress
     * @param {string} type Transition type (up, down, left, right, fade)
     * @param {number} progress 0 to 1
     * @return {object} Object with opacity and transform properties
     */
    function getTransitionStyles(type, progress) {
      // Force fade for reduced motion
      if (prefersReducedMotion) {
        type = 'fade';
      }
      let styles = {
        opacity: '1',
        transform: 'translate(0, 0)'
      };
      switch (type) {
        case 'fade':
          styles.opacity = progress.toFixed(3);
          styles.transform = 'translate(0, 0)';
          break;
        case 'up':
          // Enter from bottom
          styles.transform = `translateY(${(1 - progress) * 100}%)`;
          break;
        case 'down':
          // Enter from top
          styles.transform = `translateY(${(1 - progress) * -100}%)`;
          break;
        case 'left':
          // Enter from right
          styles.transform = `translateX(${(1 - progress) * 100}%)`;
          break;
        case 'right':
          // Enter from left
          styles.transform = `translateX(${(1 - progress) * -100}%)`;
          break;
        default:
          // None or unknown
          styles.opacity = progress >= 0.5 ? '1' : '0';
          break;
      }
      return styles;
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
      const scrollableDistance = blockHeight - viewportHeight;
      if (scrollableDistance <= 0) {
        ticking = false;
        return;
      }

      // How far we've scrolled into the block
      const scrolledDistance = Math.max(0, -blockTop);
      // 0.0 to 1.0
      const scrollProgress = Math.min(1, scrolledDistance / scrollableDistance);

      // Map 0..1 to 0..(Frames-1)
      const totalTransitions = frameCount - 1;
      // Ensure we don't go out of bounds
      const virtualScroll = Math.min(totalTransitions, scrollProgress * totalTransitions);

      // Determine which frames are involved
      const currentIndex = Math.floor(virtualScroll);
      const nextIndex = Math.min(frameCount - 1, currentIndex + 1);
      const localProgress = virtualScroll - currentIndex;

      // Update all frames
      frames.forEach((frame, index) => {
        // Kill CSS transitions so we can scrub manually
        frame.style.transitionDuration = '0ms';
        if (index === currentIndex) {
          // This is the "bottom" frame of the current stack
          // It stays static and fully visible behind the entering frame
          frame.classList.add('is-active');
          frame.style.opacity = '1';
          frame.style.transform = 'translate(0, 0)';
          frame.style.zIndex = '1';
        } else if (index === nextIndex && nextIndex !== currentIndex) {
          // This is the "top" frame entering the stack
          const type = frame.dataset.transitionType || 'fade';
          const styles = getTransitionStyles(type, localProgress);
          frame.classList.add('is-active');
          frame.style.opacity = styles.opacity;
          frame.style.transform = styles.transform;
          frame.style.zIndex = '2';
        } else {
          // Frames not involved in the current transition pair
          frame.classList.remove('is-active');
          frame.style.opacity = '0';
          frame.style.zIndex = '0';
        }
      });
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

    // Initial state
    updateActiveFrame();

    // Store cleanup function on element
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