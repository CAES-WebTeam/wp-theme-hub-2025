/******/ (() => { // webpackBootstrap
/*!***********************************!*\
  !*** ./src/blocks/reveal/view.js ***!
  \***********************************/
/**
 * Reveal Block Frontend JavaScript - Simple Fixed Background Approach
 */

(function () {
  'use strict';

  function initRevealBlock(block) {
    const sections = block.querySelectorAll('.reveal-frame-section');
    const backgrounds = block.querySelectorAll('.reveal-frame-background');
    const contents = block.querySelectorAll('.reveal-frame-content');
    if (sections.length === 0) {
      return;
    }
    let ticking = false;

    /**
     * Set section heights based on content
     */
    function setSectionHeights() {
      const viewportHeight = window.innerHeight;
      sections.forEach((section, index) => {
        const content = section.querySelector('.reveal-frame-content');
        const bg = section.querySelector('.reveal-frame-background');
        if (!content) return;
        const contentChildren = content.children;
        let contentHeight = 0;
        for (let child of contentChildren) {
          contentHeight += child.offsetHeight;
          const style = window.getComputedStyle(child);
          contentHeight += parseInt(style.marginTop) + parseInt(style.marginBottom);
        }
        contentHeight = Math.max(contentHeight, 200);

        // Get transition distance for this section
        const speed = bg.getAttribute('data-speed') || 'normal';
        let transitionDistance;
        if (speed === 'slow') {
          transitionDistance = 2.5 * viewportHeight;
        } else if (speed === 'fast') {
          transitionDistance = 1.2 * viewportHeight;
        } else {
          transitionDistance = 2.0 * viewportHeight;
        }

        // Section height: viewport (for content to enter) + content height + viewport (for content to exit) + transition space
        // The last section needs extra space to scroll the background away
        if (index === sections.length - 1) {
          section.style.height = viewportHeight * 3 + contentHeight + 'px';
        } else {
          section.style.height = viewportHeight * 2 + contentHeight + transitionDistance + 'px';
        }
      });
    }

    /**
     * Update on scroll
     */
    function updateOnScroll() {
      const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
      const viewportHeight = window.innerHeight;
      const blockRect = block.getBoundingClientRect();
      const blockTop = scrollTop + blockRect.top;
      const blockBottom = blockTop + block.offsetHeight;

      // Check if we're even in the reveal block
      const inBlock = scrollTop >= blockTop && scrollTop <= blockBottom;
      let activeIndex = -1;
      let transitionIndex = -1;
      let transitionProgress = 0;
      if (!inBlock) {
        // Not in block - hide all backgrounds
        backgrounds.forEach(bg => {
          bg.style.opacity = 0;
          bg.style.zIndex = 5;
        });
        ticking = false;
        return;
      }

      // Find which section we're currently in
      sections.forEach((section, index) => {
        const rect = section.getBoundingClientRect();
        const sectionTop = scrollTop + rect.top;
        const sectionBottom = sectionTop + section.offsetHeight;
        const content = section.querySelector('.reveal-frame-content');
        const contentRect = content.getBoundingClientRect();

        // Is scroll position within this section?
        if (scrollTop >= sectionTop && scrollTop < sectionBottom) {
          // Check if content is visible (has some part in viewport)
          const contentVisible = contentRect.bottom > viewportHeight * 0.15 && contentRect.top < viewportHeight * 0.85;
          if (contentVisible) {
            // Content is visible, this is the active frame
            activeIndex = index;
          } else if (index < sections.length - 1) {
            // Content not visible but we're in this section = transition zone
            activeIndex = index;
            transitionIndex = index + 1;

            // Calculate transition progress
            const bg = sections[transitionIndex].querySelector('.reveal-frame-background');
            const speed = bg.getAttribute('data-speed') || 'normal';
            let transitionDistance;
            if (speed === 'slow') {
              transitionDistance = 2.5 * viewportHeight;
            } else if (speed === 'fast') {
              transitionDistance = 1.2 * viewportHeight;
            } else {
              transitionDistance = 2.0 * viewportHeight;
            }

            // Transition starts when content is fully gone
            // That's at: sectionTop + 2*viewportHeight + contentHeight
            const contentChildren = content.children;
            let contentHeight = 0;
            for (let child of contentChildren) {
              contentHeight += child.offsetHeight;
              const style = window.getComputedStyle(child);
              contentHeight += parseInt(style.marginTop) + parseInt(style.marginBottom);
            }
            const transitionStart = sectionTop + 2 * viewportHeight + contentHeight;
            const progressIntoTransition = scrollTop - transitionStart;
            transitionProgress = progressIntoTransition / transitionDistance;
            transitionProgress = Math.max(0, Math.min(1, transitionProgress));
          } else if (index === sections.length - 1) {
            // Last section - check if content has scrolled away
            const contentGone = contentRect.bottom < viewportHeight * 0.15;
            if (contentGone) {
              // Content gone, keep background visible until we scroll past section
              activeIndex = index;
            } else {
              // Content still visible
              activeIndex = index;
            }
          }
        }
      });

      // If no active frame found and we're in the block, don't show anything
      if (activeIndex === -1) {
        backgrounds.forEach(bg => {
          bg.style.opacity = 0;
          bg.style.zIndex = 5;
        });
        ticking = false;
        return;
      }

      // Apply background visibility
      backgrounds.forEach((bg, index) => {
        const transitionType = bg.getAttribute('data-transition') || 'none';
        if (index === activeIndex && transitionIndex === -1) {
          // Active frame, no transition
          bg.style.opacity = 1;
          bg.style.transform = 'none';
          bg.style.zIndex = 10;
        } else if (index === activeIndex && transitionIndex !== -1) {
          // Active frame during transition - backdrop
          bg.style.opacity = 1;
          bg.style.transform = 'none';
          bg.style.zIndex = 9;
        } else if (index === transitionIndex) {
          // Frame transitioning in
          bg.style.zIndex = 10;
          if (transitionType === 'fade') {
            bg.style.opacity = transitionProgress;
            bg.style.transform = 'none';
          } else if (transitionType === 'left') {
            const x = (1 - transitionProgress) * 100;
            bg.style.transform = `translate3d(${x}%, 0, 0)`;
            bg.style.opacity = 1;
          } else if (transitionType === 'right') {
            const x = -(1 - transitionProgress) * 100;
            bg.style.transform = `translate3d(${x}%, 0, 0)`;
            bg.style.opacity = 1;
          } else if (transitionType === 'up') {
            const y = (1 - transitionProgress) * 100;
            bg.style.transform = `translate3d(0, ${y}%, 0)`;
            bg.style.opacity = 1;
          } else {
            bg.style.opacity = transitionProgress;
            bg.style.transform = 'none';
          }
        } else {
          // Hidden
          bg.style.opacity = 0;
          bg.style.transform = 'none';
          bg.style.zIndex = 5;
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
    function onResize() {
      setSectionHeights();
      updateOnScroll();
    }

    // Initialize
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
        img.addEventListener('load', () => {
          setSectionHeights();
          updateOnScroll();
        });
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