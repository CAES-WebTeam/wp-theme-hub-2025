/******/ (() => { // webpackBootstrap
/*!***********************************!*\
  !*** ./src/blocks/reveal/view.js ***!
  \***********************************/
(function () {
  'use strict';

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  function initRevealBlock(block) {
    const stage = block.querySelector('.reveal-stage');
    const triggers = block.querySelectorAll('.reveal-trigger');
    const frames = block.querySelectorAll('.reveal-frame');
    const contents = block.querySelectorAll('.reveal-frame-content');
    if (!triggers.length || !frames.length) return;
    let ticking = false;
    function updateOnScroll() {
      const viewportHeight = window.innerHeight;

      // Iterate over every trigger to determine state
      triggers.forEach((trigger, index) => {
        const rect = trigger.getBoundingClientRect();
        const frame = frames[index];
        const content = contents[index];

        // Calculate LOCAL progress for this specific slide
        // 0 = Trigger top is at bottom of viewport (entering)
        // 1 = Trigger bottom is at bottom of viewport (leaving)
        // We actually want: 0 = Trigger top at TOP of viewport.

        // Logic: How far has the trigger top moved up past the viewport top?
        // When rect.top == 0, progress = 0.
        // When rect.top == -(rect.height - viewportHeight), progress = 1.
        // This assumes the trigger is taller than the viewport (which it is, min 100vh).

        let progress = -rect.top / (rect.height - viewportHeight);

        // Clamp progress
        const clampedProgress = Math.max(0, Math.min(1, progress));

        // --- 1. Background Transitions ---

        // Frame 0 is the base, it handles differently (always visible unless covered)
        if (index === 0) {
          frame.style.opacity = '1';
        } else {
          // Transition Logic for frames > 0
          // Transition happens in the first 30% of the trigger
          const transitionEnd = 0.3;
          const transitionProgress = Math.min(1, clampedProgress / transitionEnd);
          const type = frame.dataset.transitionType || 'fade';
          if (prefersReducedMotion) {
            frame.style.opacity = transitionProgress >= 1 ? '1' : '0';
          } else if (type === 'wipe') {
            // Wipe Up Effect
            frame.style.opacity = '1';
            // 100% -> 0% (inset from top)
            const clipVal = (1 - transitionProgress) * 100;
            frame.style.clipPath = `inset(${clipVal}% 0 0 0)`;
          } else {
            // Fade Effect
            frame.style.opacity = transitionProgress;
            frame.style.clipPath = 'none';
          }
        }

        // --- 2. Content Transitions ---

        if (content) {
          // Content Logic:
          // Fade In: 10% - 30%
          // Stay: 30% - 70%
          // Fade Out: 70% - 90%

          let opacity = 0;
          if (clampedProgress > 0.1 && clampedProgress < 0.9) {
            if (clampedProgress < 0.3) {
              // Fading in
              opacity = (clampedProgress - 0.1) / 0.2;
            } else if (clampedProgress > 0.7) {
              // Fading out
              opacity = 1 - (clampedProgress - 0.7) / 0.2;
            } else {
              // Fully visible
              opacity = 1;
            }
          }
          content.style.opacity = opacity;
          // Prevent hidden content from blocking clicks
          content.style.pointerEvents = opacity > 0.5 ? 'auto' : 'none';

          // Optional: subtle vertical shift for content
          if (!prefersReducedMotion) {
            const translateY = (1 - opacity) * 20;
            content.children[0].style.transform = `translateY(${translateY}px)`;
            content.children[0].style.transition = 'transform 0.1s linear';
          }
        }
      });
      ticking = false;
    }
    function onScroll() {
      if (!ticking) {
        window.requestAnimationFrame(updateOnScroll);
        ticking = true;
      }
    }
    window.addEventListener('scroll', onScroll, {
      passive: true
    });
    window.addEventListener('resize', updateOnScroll);

    // Initial call
    updateOnScroll();
  }

  // Initialize on load
  document.addEventListener('DOMContentLoaded', function () {
    const blocks = document.querySelectorAll('.caes-reveal');
    blocks.forEach(initRevealBlock);
  });
})();
/******/ })()
;
//# sourceMappingURL=view.js.map