/******/ (() => { // webpackBootstrap
/*!***********************************!*\
  !*** ./src/blocks/reveal/view.js ***!
  \***********************************/
/**
 * Reveal Block Frontend JavaScript - Sticky Approach v6
 * 
 * Handles:
 * 1. Setting section heights based on content height
 * 2. Content opacity fade as it enters/exits
 */

(function () {
  'use strict';

  function initRevealBlock(block) {
    const sections = block.querySelectorAll('.reveal-frame-section');
    const contents = block.querySelectorAll('.reveal-frame-content');
    if (sections.length === 0) {
      return;
    }
    let ticking = false;

    /**
     * Calculate and set section heights based on content
     * Section needs: 100vh (for sticky background) + content height + 100vh (for content to scroll through)
     */
    function setSectionHeights() {
      const viewportHeight = window.innerHeight;
      sections.forEach((section, index) => {
        const content = section.querySelector('.reveal-frame-content');
        if (!content) return;

        // Get actual content height (excluding padding)
        const contentChildren = content.children;
        let contentHeight = 0;
        for (let child of contentChildren) {
          contentHeight += child.offsetHeight;
          // Add margin
          const style = window.getComputedStyle(child);
          contentHeight += parseInt(style.marginTop) + parseInt(style.marginBottom);
        }

        // Minimum content height
        contentHeight = Math.max(contentHeight, 100);

        // Section height calculation:
        // - 100vh for the sticky background
        // - contentHeight for the actual content
        // - 100vh for the content to scroll completely through the viewport
        // But we subtract 100vh because content has margin-top: -100vh

        // Uniform height calculation for ALL sections (including the last one)
        // This ensures the background stays sticky while content scrolls through
        const sectionHeight = viewportHeight + contentHeight + viewportHeight;
        section.style.height = sectionHeight + 'px';
      });
    }

    /**
     * Calculate content opacity based on actual content position
     */
    function getContentOpacity(element) {
      const viewportHeight = window.innerHeight;

      // Find actual content children bounds
      const contentChildren = element.children;
      if (contentChildren.length === 0) {
        return 1;
      }
      let contentTop = Infinity;
      let contentBottom = -Infinity;
      for (let child of contentChildren) {
        const childRect = child.getBoundingClientRect();
        if (childRect.height === 0) continue; // Skip empty elements
        if (childRect.top < contentTop) contentTop = childRect.top;
        if (childRect.bottom > contentBottom) contentBottom = childRect.bottom;
      }

      // Fallback if no visible children
      if (contentTop === Infinity) {
        return 1;
      }
      const fadeZone = viewportHeight * 0.15; // 15% of viewport for fade

      // Content fully below viewport - hidden
      if (contentTop >= viewportHeight) {
        return 0;
      }

      // Content fully above viewport - hidden
      if (contentBottom <= 0) {
        return 0;
      }

      // Fading in from bottom
      if (contentTop > viewportHeight - fadeZone) {
        return (viewportHeight - contentTop) / fadeZone;
      }

      // Fading out at top
      if (contentBottom < fadeZone) {
        return contentBottom / fadeZone;
      }

      // Fully visible
      return 1;
    }
    function updateOnScroll() {
      const viewportHeight = window.innerHeight;

      // Loop through every section to determine visibility and transitions
      sections.forEach((section, index) => {
        const bg = section.querySelector('.reveal-frame-background');
        const content = section.querySelector('.reveal-frame-content');
        const transitionType = bg.getAttribute('data-transition') || 'none';
        const rect = section.getBoundingClientRect();

        // Determine if content is visible (using your existing logic)
        const contentOpacity = getContentOpacity(content);
        const contentVisible = contentOpacity > 0;

        // Calculate section progress: where section top is relative to viewport
        // progress = 0 when section.top = viewportHeight (just entering)
        // progress = 1 when section.top = 0 (fully entered)
        let sectionProgress = (viewportHeight - rect.top) / viewportHeight;
        sectionProgress = Math.max(0, Math.min(1, sectionProgress));

        // First frame: always visible, highest z-index initially
        if (index === 0) {
          bg.style.opacity = 1;
          bg.style.transform = 'none';
          // Z-index decreases as we scroll past it
          bg.style.zIndex = contentVisible ? 10 : 5;
          return;
        }

        // For subsequent frames:
        // - Keep them hidden until their content has scrolled away (transition zone)
        // - Then transition them in
        // - Keep them visible until next frame takes over

        // Determine if we're in the "transition zone" (between content sections)
        // This is when current content is gone but section is still entering
        const inTransitionZone = !contentVisible && sectionProgress < 1;
        const fullyEntered = sectionProgress >= 1;

        // Calculate transition progress within the transition zone
        // Start transitioning once content is fully scrolled away
        let transitionProgress = 0;
        if (inTransitionZone || fullyEntered) {
          // Transition happens in the gap after content scrolls away
          // Map sectionProgress to transitionProgress
          transitionProgress = fullyEntered ? 1 : sectionProgress;
        }

        // Z-index management: active frame on top
        if (contentVisible || fullyEntered) {
          bg.style.zIndex = 10;
        } else if (inTransitionZone) {
          bg.style.zIndex = 9; // Transitioning in, but below active content
        } else {
          bg.style.zIndex = 5; // Not visible yet
        }

        // Apply transitions
        if (transitionType === 'fade') {
          bg.style.opacity = transitionProgress;
          bg.style.transform = 'none';
        } else if (transitionType === 'left') {
          const x = (1 - transitionProgress) * 100;
          bg.style.transform = `translate3d(${x}%, 0, 0)`;
          bg.style.opacity = transitionProgress > 0 ? 1 : 0;
        } else if (transitionType === 'right') {
          const x = -(1 - transitionProgress) * 100;
          bg.style.transform = `translate3d(${x}%, 0, 0)`;
          bg.style.opacity = transitionProgress > 0 ? 1 : 0;
        } else if (transitionType === 'up') {
          bg.style.opacity = transitionProgress > 0 ? 1 : 0;
          bg.style.transform = 'none';
        } else {
          // 'none' - just appear
          bg.style.opacity = transitionProgress > 0 ? 1 : 0;
          bg.style.transform = 'none';
        }
      });

      // Handle Content Opacity
      contents.forEach(content => {
        const opacity = getContentOpacity(content);
        content.style.opacity = Math.max(0, Math.min(1, opacity));
      });
      ticking = false;
    }
    function onScroll() {
      if (!ticking) {
        window.requestAnimationFrame(updateOnScroll);
        ticking = true;
      }
    }
    function onResize() {
      setSectionHeights();
      updateOnScroll();
    }

    // Initialize
    contents.forEach(content => {
      content.style.transition = 'none';
    });

    // Set initial heights
    setSectionHeights();

    // Set up listeners
    window.addEventListener('scroll', onScroll, {
      passive: true
    });
    window.addEventListener('resize', onResize, {
      passive: true
    });

    // Recalculate after images load
    block.querySelectorAll('img').forEach(img => {
      if (!img.complete) {
        img.addEventListener('load', setSectionHeights);
      }
    });
    updateOnScroll();
    block._revealCleanup = function () {
      window.removeEventListener('scroll', onScroll);
      window.removeEventListener('resize', onResize);
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