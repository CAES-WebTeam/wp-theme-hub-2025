/******/ (() => { // webpackBootstrap
/*!***********************************!*\
  !*** ./src/blocks/reveal/view.js ***!
  \***********************************/
/**
 * Reveal Block Frontend JavaScript
 *
 * Handles scroll-triggered frame transitions and frame-specific content visibility.
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

    // Build frame weights based on transition speed
    // slow = 1.5x scroll distance, normal = 1x, fast = 0.5x
    const speedMultipliers = {
      slow: 1.5,
      normal: 1,
      fast: 0.5
    };
    const frameWeights = [];
    let totalWeight = 0;
    frames.forEach((frame, index) => {
      if (index === 0) {
        // First frame has no incoming transition
        frameWeights.push(0);
      } else {
        const speed = frame.dataset.transitionSpeed || 'normal';
        const weight = speedMultipliers[speed] || 1;
        frameWeights.push(weight);
        totalWeight += weight;
      }
    });

    // Calculate cumulative positions (0 to 1) for each frame transition
    const framePositions = [0]; // First frame starts at 0
    let cumulative = 0;
    for (let i = 1; i < frameCount; i++) {
      cumulative += frameWeights[i] / totalWeight;
      framePositions.push(cumulative);
    }

    /**
     * Get transition styles for the incoming frame.
     * 
     * For wipe transitions, we use clip-path: inset() to reveal the image.
     * The image stays stationary; only the clipping boundary moves.
     * 
     * clip-path: inset(top right bottom left)
     * - 'up' wipe: reveals from bottom to top, so we clip from top
     * - 'down' wipe: reveals from top to bottom, so we clip from bottom
     * - 'left' wipe: reveals from right to left, so we clip from left
     * - 'right' wipe: reveals from left to right, so we clip from right
     */
    function getTransitionStyles(type, progress) {
      if (prefersReducedMotion) {
        type = 'fade';
      }

      // Default: fully visible, no clipping
      let styles = {
        opacity: '1',
        clipPath: 'inset(0 0 0 0)'
      };

      // Calculate the remaining amount to clip (inverse of progress)
      const clipAmount = ((1 - progress) * 100).toFixed(2);
      switch (type) {
        case 'fade':
          styles.opacity = progress.toFixed(3);
          styles.clipPath = 'none';
          break;
        case 'up':
          // Wipe upward: reveal from bottom, clip from top
          styles.clipPath = `inset(${clipAmount}% 0 0 0)`;
          break;
        case 'down':
          // Wipe downward: reveal from top, clip from bottom
          styles.clipPath = `inset(0 0 ${clipAmount}% 0)`;
          break;
        case 'left':
          // Wipe leftward: reveal from right, clip from left
          styles.clipPath = `inset(0 0 0 ${clipAmount}%)`;
          break;
        case 'right':
          // Wipe rightward: reveal from left, clip from right
          styles.clipPath = `inset(0 ${clipAmount}% 0 0)`;
          break;
        default:
          // 'none' or unknown: hard cut at 50%
          styles.opacity = progress >= 0.5 ? '1' : '0';
          styles.clipPath = 'none';
          break;
      }
      return styles;
    }

    /**
     * Update frame content visibility with smooth transitions
     */
    function updateFrameContentVisibility(targetIndex) {
      if (targetIndex === currentActiveIndex) {
        return; // No change needed
      }
      frameContents.forEach(content => {
        const contentIndex = parseInt(content.dataset.frameIndex, 10);
        if (contentIndex === targetIndex) {
          // Show this content
          content.style.opacity = '1';
          content.style.pointerEvents = 'auto';
          content.setAttribute('aria-hidden', 'false');
        } else {
          // Hide this content
          content.style.opacity = '0';
          content.style.pointerEvents = 'none';
          content.setAttribute('aria-hidden', 'true');
        }
      });
      currentActiveIndex = targetIndex;
    }
    function updateActiveFrame() {
      const viewportHeight = window.innerHeight;

      // Strategy: 
      // - Find which content block is currently in view
      // - That determines which background is shown
      // - Transition to next background happens AFTER content scrolls off top

      let activeFrameIndex = 0;
      let transitionProgress = 0;
      let transitioningToIndex = -1;

      // Check each content block's position
      for (let i = 0; i < frameContents.length; i++) {
        const content = frameContents[i];
        const rect = content.getBoundingClientRect();

        // If this content is visible or hasn't scrolled off yet, this is our active frame
        if (rect.bottom > 0) {
          activeFrameIndex = i;

          // Check if content is scrolling off the top and we should transition
          // Transition zone: from when content bottom hits viewport center to when it exits
          if (rect.bottom < viewportHeight * 0.5 && i < frameContents.length - 1) {
            // Calculate progress: 0 when bottom is at 50vh, 1 when bottom reaches 0
            const transitionZone = viewportHeight * 0.5;
            transitionProgress = 1 - rect.bottom / transitionZone;
            transitionProgress = Math.max(0, Math.min(1, transitionProgress));

            // Apply speed multiplier
            const nextFrame = frames[i + 1];
            if (nextFrame) {
              const speed = nextFrame.dataset.transitionSpeed || 'normal';
              const speedMultiplier = {
                slow: 0.5,
                normal: 1,
                fast: 2
              }[speed] || 1;
              transitionProgress = Math.pow(transitionProgress, 1 / speedMultiplier);
            }
            transitioningToIndex = i + 1;
          }
          break;
        }
      }

      // If all content has scrolled off, show last frame
      if (activeFrameIndex === 0 && frameContents.length > 0) {
        const firstRect = frameContents[0].getBoundingClientRect();
        if (firstRect.bottom <= 0) {
          activeFrameIndex = frameContents.length - 1;
        }
      }

      // Clamp to valid frame range
      activeFrameIndex = Math.max(0, Math.min(frameCount - 1, activeFrameIndex));

      // Update background frames
      frames.forEach((frame, index) => {
        // Kill CSS transitions to allow manual scrubbing
        frame.style.transitionDuration = '0ms';
        if (index === activeFrameIndex) {
          // Current base frame - fully visible
          frame.classList.add('is-active');
          frame.style.opacity = '1';
          frame.style.clipPath = 'none';
          frame.style.zIndex = '1';
        } else if (index === transitioningToIndex && transitionProgress > 0) {
          // Incoming frame - apply wipe/fade transition
          const type = frame.dataset.transitionType || 'fade';
          const styles = getTransitionStyles(type, transitionProgress);
          frame.classList.add('is-active');
          frame.style.opacity = styles.opacity;
          frame.style.clipPath = styles.clipPath;
          frame.style.zIndex = '2';
        } else {
          // Inactive frames - hidden
          frame.classList.remove('is-active');
          frame.style.opacity = '0';
          frame.style.clipPath = 'inset(0 0 0 0)';
          frame.style.zIndex = '0';
        }
      });

      // Update content visibility - show content for active frame
      const targetContentIndex = transitionProgress > 0.8 ? transitioningToIndex : activeFrameIndex;
      updateFrameContentVisibility(Math.max(0, targetContentIndex));
      ticking = false;
    }
    function onScroll() {
      if (!ticking) {
        window.requestAnimationFrame(updateActiveFrame);
        ticking = true;
      }
    }

    // Initialize frame content visibility
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

    // Run immediately to set initial state
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