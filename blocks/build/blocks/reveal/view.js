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
      const blockRect = block.getBoundingClientRect();
      const viewportHeight = window.innerHeight;
      const blockTop = blockRect.top;
      const blockHeight = block.offsetHeight;

      // Calculate scroll progress through entire block (0 to 1)
      const scrollableDistance = blockHeight - viewportHeight;
      const scrolledDistance = Math.max(0, -blockTop);
      let scrollProgress = 0;
      if (scrollableDistance > 0) {
        scrollProgress = Math.min(1, scrolledDistance / scrollableDistance);
      }

      // Each frame gets an equal segment of scroll
      // Within each segment: 80% content hold, 20% transition to next
      const CONTENT_PHASE = 0.8;
      const TRANSITION_PHASE = 0.2;
      const segmentSize = 1 / frameCount;

      // Find which frame segment we're in
      const rawIndex = scrollProgress / segmentSize;
      const currentIndex = Math.min(frameCount - 1, Math.floor(rawIndex));
      const progressInSegment = rawIndex - currentIndex; // 0 to 1 within this segment

      let localProgress = 0; // Transition progress (0 = not started, 1 = complete)
      let isInTransition = false;
      if (progressInSegment > CONTENT_PHASE && currentIndex < frameCount - 1) {
        // We're in the transition phase of this segment
        isInTransition = true;
        // Map the transition portion (0.8-1.0) to (0-1)
        localProgress = (progressInSegment - CONTENT_PHASE) / TRANSITION_PHASE;
      }
      const nextIndex = Math.min(frameCount - 1, currentIndex + 1);

      // Update background frames
      frames.forEach((frame, index) => {
        frame.style.transitionDuration = '0ms';
        if (index === currentIndex) {
          // Current base frame - fully visible
          frame.classList.add('is-active');
          frame.style.opacity = '1';
          frame.style.clipPath = 'none';
          frame.style.zIndex = '1';
        } else if (index === nextIndex && isInTransition && localProgress > 0) {
          // Next frame - transitioning in
          const type = frame.dataset.transitionType || 'fade';
          const styles = getTransitionStyles(type, localProgress);
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

      // Content: show current frame's content during content phase,
      // switch to next frame's content when transition is > 50% complete
      const targetContentIndex = isInTransition && localProgress > 0.5 ? nextIndex : currentIndex;
      updateFrameContentVisibility(targetContentIndex);
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