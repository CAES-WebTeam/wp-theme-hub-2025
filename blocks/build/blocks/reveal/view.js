/******/ (() => { // webpackBootstrap
/*!***********************************!*\
  !*** ./src/blocks/reveal/view.js ***!
  \***********************************/
/**
 * Reveal Block Frontend JavaScript
 * 
 * Improved to more closely match Shorthand's behavior:
 * - Content starts completely hidden (opacity: 0)
 * - Content fades in AFTER the background is fully visible
 * - Content fades out BEFORE the next background transitions in
 * - Background transitions use clip-path for wipes
 * - Transition percentages match Shorthand's 0.35 (35%) pattern
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

    // Parse transition data from frames
    // Shorthand uses format: "0.35 fade;0.35 up" where 0.35 is the transition percentage
    const transitions = [];
    frames.forEach((frame, index) => {
      const speed = frame.dataset.transitionSpeed || 'normal';
      transitions.push({
        type: frame.dataset.transitionType || 'fade',
        speed: speed,
        // Convert speed to percentage (like Shorthand's 0.35)
        percentage: getTransitionPercentage(speed)
      });
    });

    /**
     * Get transition percentage based on speed setting
     * Shorthand uses 0.35 (35%) as default
     */
    function getTransitionPercentage(speed) {
      switch (speed) {
        case 'slow':
          return 0.45;
        case 'fast':
          return 0.20;
        case 'normal':
        default:
          return 0.35;
      }
    }

    /**
     * Get clip-path style for wipe transitions
     */
    function getTransitionStyles(type, progress) {
      if (prefersReducedMotion) {
        type = 'fade';
      }
      const clipAmount = ((1 - progress) * 100).toFixed(2);
      switch (type) {
        case 'fade':
          return {
            opacity: progress,
            clipPath: 'none'
          };
        case 'up':
          return {
            opacity: 1,
            clipPath: `inset(${clipAmount}% 0 0 0)`
          };
        case 'down':
          return {
            opacity: 1,
            clipPath: `inset(0 0 ${clipAmount}% 0)`
          };
        case 'left':
          return {
            opacity: 1,
            clipPath: `inset(0 0 0 ${clipAmount}%)`
          };
        case 'right':
          return {
            opacity: 1,
            clipPath: `inset(0 ${clipAmount}% 0 0)`
          };
        default:
          return {
            opacity: progress >= 0.5 ? 1 : 0,
            clipPath: 'none'
          };
      }
    }

    /**
     * Calculate content opacity based on scroll position within frame segment
     * Content fades in after background, fades out before next transition
     */
    function getContentOpacity(scrollProgress, segmentStart, segmentEnd, transitionPercentage) {
      const segmentLength = segmentEnd - segmentStart;

      // Content fade-in starts slightly after segment starts (10% into segment)
      const fadeInStart = segmentStart + segmentLength * 0.05;
      const fadeInEnd = segmentStart + segmentLength * 0.20;

      // Content fade-out ends when background transition starts
      const fadeOutStart = segmentEnd - segmentLength * transitionPercentage - segmentLength * 0.05;
      const fadeOutEnd = segmentEnd - segmentLength * transitionPercentage;
      if (scrollProgress < fadeInStart) {
        return 0;
      } else if (scrollProgress < fadeInEnd) {
        // Fading in
        return (scrollProgress - fadeInStart) / (fadeInEnd - fadeInStart);
      } else if (scrollProgress < fadeOutStart) {
        // Fully visible
        return 1;
      } else if (scrollProgress < fadeOutEnd) {
        // Fading out
        return 1 - (scrollProgress - fadeOutStart) / (fadeOutEnd - fadeOutStart);
      } else {
        return 0;
      }
    }

    /**
     * Main scroll handler
     */
    function updateOnScroll() {
      const blockRect = block.getBoundingClientRect();
      const viewportHeight = window.innerHeight;
      const blockHeight = block.offsetHeight;

      // Calculate scroll progress through the block (0 to 1)
      // Progress = 0 when block top is at viewport bottom
      // Progress = 1 when block bottom is at viewport top
      const scrollableDistance = blockHeight - viewportHeight;
      const scrolledDistance = Math.max(0, -blockRect.top);
      const scrollProgress = scrollableDistance > 0 ? Math.min(1, scrolledDistance / scrollableDistance) : 0;

      // Calculate segment boundaries for each frame
      const segmentSize = 1 / frameCount;

      // Process each frame
      frames.forEach((frame, index) => {
        const segmentStart = index * segmentSize;
        const segmentEnd = (index + 1) * segmentSize;
        const transitionPct = transitions[index].percentage;
        const transitionStart = segmentEnd - segmentSize * transitionPct;
        const isFirstFrame = index === 0;
        const isLastFrame = index === frameCount - 1;

        // Disable CSS transitions - we're controlling everything via JS
        frame.style.transitionDuration = '0ms';

        // Determine background state
        let bgOpacity = 0;
        let bgClipPath = 'inset(100% 0 0 0)'; // Start fully clipped
        let bgZIndex = 0;
        let bgDisplay = 'none';
        if (isFirstFrame) {
          // First frame: visible from start, no transition in
          if (scrollProgress < transitionStart) {
            bgOpacity = 1;
            bgClipPath = 'none';
            bgZIndex = 1;
            bgDisplay = 'block';
          } else if (scrollProgress < segmentEnd) {
            // Transitioning out (next frame coming in)
            bgOpacity = 1;
            bgClipPath = 'none';
            bgZIndex = 1;
            bgDisplay = 'block';
          } else {
            // Completely past this frame
            bgOpacity = 0;
            bgDisplay = 'none';
            bgZIndex = 0;
          }
        } else {
          // Subsequent frames: transition in during previous frame's transition period
          const prevSegmentEnd = segmentStart;
          const prevTransitionPct = transitions[index].percentage;
          const incomingTransitionStart = prevSegmentEnd - segmentSize * prevTransitionPct;
          if (scrollProgress < incomingTransitionStart) {
            // Before this frame's transition starts
            bgOpacity = 0;
            bgDisplay = 'none';
            bgZIndex = 0;
          } else if (scrollProgress < prevSegmentEnd) {
            // This frame is transitioning IN
            const transitionProgress = (scrollProgress - incomingTransitionStart) / (prevSegmentEnd - incomingTransitionStart);
            const styles = getTransitionStyles(transitions[index].type, transitionProgress);
            bgOpacity = styles.opacity;
            bgClipPath = styles.clipPath;
            bgZIndex = 2; // On top during transition
            bgDisplay = 'block';
          } else if (scrollProgress < transitionStart || isLastFrame) {
            // Fully visible
            bgOpacity = 1;
            bgClipPath = 'none';
            bgZIndex = 1;
            bgDisplay = 'block';
          } else if (scrollProgress < segmentEnd && !isLastFrame) {
            // Transitioning out (stays as base layer)
            bgOpacity = 1;
            bgClipPath = 'none';
            bgZIndex = 1;
            bgDisplay = 'block';
          } else if (!isLastFrame) {
            // Completely past this frame
            bgOpacity = 0;
            bgDisplay = 'none';
            bgZIndex = 0;
          }
        }

        // Apply background styles
        frame.style.opacity = bgOpacity;
        frame.style.clipPath = bgClipPath;
        frame.style.zIndex = bgZIndex;
        frame.style.display = bgDisplay;
        if (bgDisplay === 'block' && bgOpacity > 0.5) {
          frame.classList.add('is-active');
        } else {
          frame.classList.remove('is-active');
        }
      });

      // Handle content opacity separately
      frameContents.forEach((content, index) => {
        const segmentStart = index * segmentSize;
        const segmentEnd = (index + 1) * segmentSize;
        const isLastFrame = index === frameCount - 1;

        // Get next frame's transition percentage for fade-out timing
        const nextTransitionPct = transitions[index + 1]?.percentage || transitions[index].percentage;
        let opacity;
        if (isLastFrame) {
          // Last frame: fade in but don't fade out
          const fadeInStart = segmentStart + segmentSize * 0.05;
          const fadeInEnd = segmentStart + segmentSize * 0.20;
          if (scrollProgress < fadeInStart) {
            opacity = 0;
          } else if (scrollProgress < fadeInEnd) {
            opacity = (scrollProgress - fadeInStart) / (fadeInEnd - fadeInStart);
          } else {
            opacity = 1;
          }
        } else {
          opacity = getContentOpacity(scrollProgress, segmentStart, segmentEnd, nextTransitionPct);
        }

        // Apply content styles
        content.style.opacity = opacity;
        content.style.pointerEvents = opacity > 0.5 ? 'auto' : 'none';
      });
      ticking = false;
    }
    function onScroll() {
      if (!ticking) {
        window.requestAnimationFrame(updateOnScroll);
        ticking = true;
      }
    }

    // Initialize: all content starts hidden
    frameContents.forEach(content => {
      content.style.transition = 'none';
      content.style.opacity = '0';
    });

    // All frames start hidden except the first
    frames.forEach((frame, index) => {
      frame.style.transition = 'none';
      if (index === 0) {
        frame.style.opacity = '1';
        frame.style.clipPath = 'none';
        frame.style.display = 'block';
        frame.style.zIndex = '1';
        frame.classList.add('is-active');
      } else {
        frame.style.opacity = '0';
        frame.style.display = 'none';
        frame.style.zIndex = '0';
      }
    });

    // Set up scroll listener
    window.addEventListener('scroll', onScroll, {
      passive: true
    });
    window.addEventListener('resize', updateOnScroll);

    // Initial update
    updateOnScroll();

    // Cleanup function for WordPress block editor
    block._revealCleanup = function () {
      window.removeEventListener('scroll', onScroll);
      window.removeEventListener('resize', updateOnScroll);
    };
  }
  function initAllBlocks() {
    document.querySelectorAll('.caes-reveal').forEach(initRevealBlock);
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