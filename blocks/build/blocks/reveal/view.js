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
      sections.forEach((section, index) => {
        const bg = section.querySelector('.reveal-frame-background');
        if (!bg) return;
        const transitionType = bg.getAttribute('data-transition') || 'none';
        const rect = section.getBoundingClientRect();

        // --- LOGIC FOR FRAME 1 (Always Visible) ---
        if (index === 0) {
          bg.style.opacity = 1;
          bg.style.transform = 'none';
          return;
        }

        // --- LOGIC FOR FRAMES 2+ ---

        // 1. Entering the screen (Coming from bottom)
        if (rect.top > 0 && rect.top < viewportHeight) {
          // Calculate 0 to 1 progress (0 = just started entering, 1 = fully active)
          let progress = 1 - rect.top / viewportHeight;
          if (transitionType === 'fade') {
            // THE FIX: "Pin" the image to the top of the viewport
            // We translate it UP by the exact amount the section is DOWN
            bg.style.transform = `translate3d(0, -${rect.top}px, 0)`;

            // Fade it in
            bg.style.opacity = progress;
          } else {
            // Default sliding behavior (Normal)
            bg.style.transform = 'none';
            bg.style.opacity = 1;
          }
        }
        // 2. Fully Active (Stuck at top)
        else if (rect.top <= 0) {
          bg.style.transform = 'none';
          bg.style.opacity = 1;
        }
        // 3. Below Screen (Not yet visible)
        else {
          // Hide completely to prevent glitches
          bg.style.opacity = 0;
          bg.style.transform = 'none';
        }
      });

      // Handle text opacity (Keep your existing logic)
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