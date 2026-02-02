/******/ (() => { // webpackBootstrap
/*!***********************************!*\
  !*** ./src/blocks/reveal/view.js ***!
  \***********************************/
/**
 * Reveal Block Frontend JavaScript
 *
 * Each frame has two scroll phases:
 * 1. HOLD - Background stays while content is visible (content scrolls naturally)
 * 2. TRANSITION - Background transitions to next frame
 *
 * The transition only happens AFTER the content has scrolled off.
 */

(function () {
  'use strict';

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  function initRevealBlock(block) {
    const frames = block.querySelectorAll('.reveal-frame');
    const frameContents = block.querySelectorAll('.reveal-frame-content');
    if (frames.length === 0) {
      return;
    }
    const frameCount = frames.length;
    let ticking = false;
    let currentActiveIndex = 0;

    // Speed multipliers affect transition duration
    const speedMultipliers = {
      slow: 1.5,
      normal: 1,
      fast: 0.5
    };

    /**
     * Get transition styles for the incoming frame.
     */
    function getTransitionStyles(type, progress) {
      if (prefersReducedMotion) {
        type = 'fade';
      }
      let styles = {
        opacity: '1',
        clipPath: 'inset(0 0 0 0)'
      };
      const clipAmount = ((1 - progress) * 100).toFixed(2);
      switch (type) {
        case 'fade':
          styles.opacity = progress.toFixed(3);
          styles.clipPath = 'none';
          break;
        case 'up':
          styles.clipPath = `inset(${clipAmount}% 0 0 0)`;
          break;
        case 'down':
          styles.clipPath = `inset(0 0 ${clipAmount}% 0)`;
          break;
        case 'left':
          styles.clipPath = `inset(0 0 0 ${clipAmount}%)`;
          break;
        case 'right':
          styles.clipPath = `inset(0 ${clipAmount}% 0 0)`;
          break;
        default:
          styles.opacity = progress >= 0.5 ? '1' : '0';
          styles.clipPath = 'none';
          break;
      }
      return styles;
    }

    /**
     * Update frame content visibility
     */
    function updateFrameContentVisibility(targetIndex) {
      if (targetIndex === currentActiveIndex) {
        return;
      }
      frameContents.forEach(content => {
        const contentIndex = parseInt(content.dataset.frameIndex, 10);
        if (contentIndex === targetIndex) {
          content.style.opacity = '1';
          content.style.pointerEvents = 'auto';
          content.setAttribute('aria-hidden', 'false');
        } else {
          content.style.opacity = '0';
          content.style.pointerEvents = 'none';
          content.setAttribute('aria-hidden', 'true');
        }
      });
      currentActiveIndex = targetIndex;
    }

    /**
     * Main update function - determines active frame and transition progress
     */
    function updateActiveFrame() {
      const viewportHeight = window.innerHeight;
      let activeFrameIndex = 0;
      let transitionProgress = 0;
      let nextFrameIndex = -1;

      // Loop through content blocks to find which one is "active"
      for (let i = 0; i < frameContents.length; i++) {
        const content = frameContents[i];
        const rect = content.getBoundingClientRect();

        // Get the transition zone height for the NEXT frame
        const nextFrame = frames[i + 1];
        const transitionSpeed = nextFrame ? nextFrame.dataset.transitionSpeed || 'normal' : 'normal';
        const transitionMultiplier = speedMultipliers[transitionSpeed] || 1;
        const transitionZoneHeight = viewportHeight * 0.5 * transitionMultiplier;

        // Content top is above viewport bottom = content has entered
        // Content bottom is above 0 = content hasn't fully left
        const contentEntered = rect.top < viewportHeight;
        const contentNotFullyGone = rect.bottom > -transitionZoneHeight;
        if (contentEntered && contentNotFullyGone) {
          activeFrameIndex = i;

          // Check if we're in the transition zone (content bottom is above viewport top)
          if (rect.bottom < 0 && i < frameContents.length - 1) {
            // Content has scrolled off top, we're in transition zone
            // Progress: 0 when content just left, 1 when transition zone ends
            transitionProgress = Math.min(1, Math.abs(rect.bottom) / transitionZoneHeight);
            nextFrameIndex = i + 1;
          }
          break;
        } else if (!contentEntered) {
          // This content hasn't entered yet, stay on previous frame
          activeFrameIndex = Math.max(0, i - 1);
          break;
        }
      }

      // If we've scrolled past everything, show last frame
      const lastContent = frameContents[frameContents.length - 1];
      if (lastContent) {
        const lastRect = lastContent.getBoundingClientRect();
        const lastTransitionZone = viewportHeight * 0.5;
        if (lastRect.bottom < -lastTransitionZone) {
          activeFrameIndex = frameCount - 1;
          transitionProgress = 0;
          nextFrameIndex = -1;
        }
      }

      // Clamp to valid range
      activeFrameIndex = Math.max(0, Math.min(frameCount - 1, activeFrameIndex));

      // If transition is complete, move to next frame
      if (transitionProgress >= 1 && nextFrameIndex > 0) {
        activeFrameIndex = nextFrameIndex;
        transitionProgress = 0;
        nextFrameIndex = -1;
      }

      // Update background frames
      frames.forEach((frame, index) => {
        frame.style.transitionDuration = '0ms';
        if (index === activeFrameIndex) {
          // Active frame - fully visible
          frame.classList.add('is-active');
          frame.style.opacity = '1';
          frame.style.clipPath = 'none';
          frame.style.zIndex = '1';
        } else if (index === nextFrameIndex && transitionProgress > 0) {
          // Next frame - transitioning in
          const type = frame.dataset.transitionType || 'fade';
          const styles = getTransitionStyles(type, transitionProgress);
          frame.classList.add('is-active');
          frame.style.opacity = styles.opacity;
          frame.style.clipPath = styles.clipPath;
          frame.style.zIndex = '2';
        } else {
          // Inactive frames
          frame.classList.remove('is-active');
          frame.style.opacity = '0';
          frame.style.clipPath = 'inset(0 0 0 0)';
          frame.style.zIndex = '0';
        }
      });

      // Update content visibility
      const visibleContentIndex = transitionProgress > 0.5 && nextFrameIndex >= 0 ? nextFrameIndex : activeFrameIndex;
      updateFrameContentVisibility(visibleContentIndex);
      ticking = false;
    }
    function onScroll() {
      if (!ticking) {
        window.requestAnimationFrame(updateActiveFrame);
        ticking = true;
      }
    }

    // Initialize content visibility
    frameContents.forEach((content, index) => {
      content.style.transition = 'opacity 0.3s ease';
      if (index === 0) {
        content.style.opacity = '1';
        content.style.pointerEvents = 'auto';
        content.setAttribute('aria-hidden', 'false');
      } else {
        content.style.opacity = '0';
        content.style.pointerEvents = 'none';
        content.setAttribute('aria-hidden', 'true');
      }
    });
    window.addEventListener('scroll', onScroll, {
      passive: true
    });
    window.addEventListener('resize', updateActiveFrame);

    // Initial update
    updateActiveFrame();
    block._revealCleanup = function () {
      window.removeEventListener('scroll', onScroll);
      window.removeEventListener('resize', updateActiveFrame);
    };
  }
  function initAllBlocks() {
    const blocks = document.querySelectorAll('.caes-reveal');
    blocks.forEach(initRevealBlock);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAllBlocks);
  } else {
    initAllBlocks();
  }
})();
/******/ })()
;
//# sourceMappingURL=view.js.map