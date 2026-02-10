/******/ (() => { // webpackBootstrap
/*!***********************************!*\
  !*** ./src/blocks/reveal/view.js ***!
  \***********************************/
/**
 * Reveal Block Frontend JavaScript - Single Sticky Container Approach
 * 
 * This approach uses ONE sticky container for all backgrounds.
 * The container sticks when the block reaches the top of the viewport,
 * and all transitions are handled via opacity/transform - never position changes.
 * 
 * Key benefits:
 * - No position switching = no visual jumps
 * - All backgrounds share the same coordinate space = perfect alignment
 * - GPU-accelerated opacity/transform transitions = smooth animations
 */

(function () {
  'use strict';

  /**
   * Initialize a single reveal block
   */
  function initRevealBlock(block) {
    const backgroundsContainer = block.querySelector('.reveal-backgrounds');
    const backgrounds = block.querySelectorAll('.reveal-frame-background');
    const sectionsContainer = block.querySelector('.reveal-sections');
    const sections = block.querySelectorAll('.reveal-frame-section');
    if (!backgroundsContainer || sections.length === 0) {
      return;
    }

    // Configuration
    const config = {
      // How much of the viewport the content area takes up
      contentViewportRatio: 0.7,
      // Extra scroll distance after last content before unsticking
      exitScrollDistance: 0.5 // viewport heights
    };
    let ticking = false;
    let frameData = [];

    /**
     * Calculate layout metrics for each frame
     */
    function calculateLayout() {
      const viewportHeight = window.innerHeight;
      frameData = [];
      let cumulativeHeight = 0;
      sections.forEach((section, index) => {
        const content = section.querySelector('.reveal-frame-content');
        const bg = backgrounds[index];

        // Get content height
        let contentHeight = 0;
        if (content) {
          const contentChildren = content.children;
          for (let child of contentChildren) {
            contentHeight += child.offsetHeight;
            const style = window.getComputedStyle(child);
            contentHeight += parseInt(style.marginTop || 0) + parseInt(style.marginBottom || 0);
          }
        }
        contentHeight = Math.max(contentHeight, 100);

        // Get transition config
        const speed = bg ? bg.getAttribute('data-speed') || 'normal' : 'normal';
        let transitionDistance;
        switch (speed) {
          case 'slow':
            transitionDistance = viewportHeight * 2.5;
            break;
          case 'fast':
            transitionDistance = viewportHeight * 1.0;
            break;
          default:
            transitionDistance = viewportHeight * 1.8;
        }

        // Calculate section height:
        // For frame 0: content starts centered, padding-top = (vh - contentHeight) / 2
        // For other frames: content enters from bottom, padding-top = 100vh (CSS default)
        // transitionDistance for the background transition (except last frame)
        const isFirstFrame = index === 0;
        const isLastFrame = index === sections.length - 1;

        // How far content is pushed down within its section before scrolling begins
        const initialPaddingTop = isFirstFrame ? Math.max(0, (viewportHeight - contentHeight) / 2) : viewportHeight;

        // Override CSS padding-top for the first frame so content starts centered
        if (isFirstFrame) {
          section.style.paddingTop = initialPaddingTop + 'px';
        }

        // How far user must scroll before content exits the top of the viewport
        const scrollToExit = initialPaddingTop + contentHeight;
        const sectionHeight = isLastFrame ? scrollToExit + viewportHeight * config.exitScrollDistance : scrollToExit + transitionDistance;
        frameData.push({
          index,
          section,
          content,
          background: bg,
          contentHeight,
          transitionDistance,
          sectionHeight,
          sectionStart: cumulativeHeight,
          sectionEnd: cumulativeHeight + sectionHeight,
          contentStart: cumulativeHeight,
          contentEnd: cumulativeHeight + scrollToExit,
          // Transition zone starts after content exits the top
          transitionStart: cumulativeHeight + scrollToExit,
          transitionEnd: cumulativeHeight + scrollToExit + transitionDistance,
          isLastFrame
        });
        cumulativeHeight += sectionHeight;

        // Set section height
        section.style.height = sectionHeight + 'px';
      });

      // Set total height for sections container
      sectionsContainer.style.height = cumulativeHeight + 'px';
      return cumulativeHeight;
    }

    /**
     * Get scroll progress within the reveal block (0 = top of block at top of viewport, 1 = end)
     */
    function getBlockScrollProgress() {
      const blockRect = block.getBoundingClientRect();
      const blockTop = window.pageYOffset + blockRect.top;
      const scrollTop = window.pageYOffset;

      // How far we've scrolled into the block
      const scrollIntoBlock = scrollTop - blockTop;

      // Total scrollable distance within the block
      const totalHeight = sectionsContainer.offsetHeight;
      return {
        scrollIntoBlock,
        totalHeight,
        blockTop,
        blockRect,
        // Is the block currently "active" (sticky container should be stuck)
        isActive: blockRect.top <= 0 && blockRect.bottom > window.innerHeight,
        // Has the block started (top is at or above viewport top)
        hasStarted: blockRect.top <= 0,
        // Is the block finished (bottom is at or above viewport bottom)
        isFinished: blockRect.bottom <= window.innerHeight
      };
    }

    /**
     * Determine which frame is active and calculate transition progress
     */
    function getCurrentFrameState(scrollIntoBlock) {
      const viewportHeight = window.innerHeight;
      let activeIndex = 0;
      let transitioningToIndex = -1;
      let transitionProgress = 0;
      for (let i = 0; i < frameData.length; i++) {
        const frame = frameData[i];
        if (scrollIntoBlock >= frame.sectionStart && scrollIntoBlock < frame.sectionEnd) {
          activeIndex = i;

          // Check if we're in the transition zone
          if (!frame.isLastFrame && scrollIntoBlock >= frame.transitionStart) {
            transitioningToIndex = i + 1;
            const progressIntoTransition = scrollIntoBlock - frame.transitionStart;
            transitionProgress = Math.min(1, Math.max(0, progressIntoTransition / frame.transitionDistance));
          }
          break;
        }
      }

      // If we've scrolled past all frames, show the last one
      if (scrollIntoBlock >= frameData[frameData.length - 1]?.sectionEnd) {
        activeIndex = frameData.length - 1;
      }
      return {
        activeIndex,
        transitioningToIndex,
        transitionProgress
      };
    }

    /**
    * Apply transition effects to backgrounds
    */
    function applyBackgroundTransitions(activeIndex, transitioningToIndex, transitionProgress) {
      backgrounds.forEach((bg, index) => {
        const transitionType = bg.getAttribute('data-transition') || 'fade';

        // Reset transforms
        bg.style.transform = '';
        bg.style.opacity = '';
        bg.style.clipPath = '';
        if (index === activeIndex && transitioningToIndex === -1) {
          // Active frame, no transition happening
          bg.classList.add('is-active');
          bg.classList.remove('is-transitioning-in', 'is-transitioning-out');
          bg.style.opacity = '1';
          bg.style.zIndex = '10';
        } else if (index === activeIndex && transitioningToIndex !== -1) {
          // Active frame, but transitioning out
          bg.classList.remove('is-active', 'is-transitioning-in');
          bg.classList.add('is-transitioning-out');
          bg.style.opacity = '1';
          bg.style.zIndex = '9';
        } else if (index === transitioningToIndex) {
          // Frame transitioning in
          bg.classList.remove('is-active', 'is-transitioning-out');
          bg.classList.add('is-transitioning-in');
          bg.style.zIndex = '10';

          // Apply transition based on type
          switch (transitionType) {
            case 'fade':
              bg.style.opacity = transitionProgress;
              break;
            case 'left':
              // Wipe from left edge toward right
              bg.style.opacity = '1';
              bg.style.clipPath = `inset(0 ${(1 - transitionProgress) * 100}% 0 0)`;
              break;
            case 'right':
              // Wipe from right edge toward left
              bg.style.opacity = '1';
              bg.style.clipPath = `inset(0 0 0 ${(1 - transitionProgress) * 100}%)`;
              break;
            case 'up':
              // Wipe from top edge downward
              bg.style.opacity = '1';
              bg.style.clipPath = `inset(0 0 ${(1 - transitionProgress) * 100}% 0)`;
              break;
            case 'down':
              // Wipe from bottom edge upward
              bg.style.opacity = '1';
              bg.style.clipPath = `inset(${(1 - transitionProgress) * 100}% 0 0 0)`;
              break;
            case 'zoom':
              bg.style.opacity = transitionProgress;
              bg.style.transform = `scale(${0.8 + 0.2 * transitionProgress})`;
              break;
            default:
              bg.style.opacity = transitionProgress;
          }
        } else {
          // Hidden frame
          bg.classList.remove('is-active', 'is-transitioning-in', 'is-transitioning-out');
          bg.style.opacity = '0';
          bg.style.zIndex = '5';
        }
      });
    }

    /**
     * Position content within viewport during scroll
     */
    function updateContentPositions(scrollIntoBlock) {
      const viewportHeight = window.innerHeight;
      frameData.forEach(frame => {
        if (!frame.content) return;

        // Calculate where content should be positioned
        // Content starts at bottom of viewport, scrolls to top, then off top
        const localScroll = scrollIntoBlock - frame.sectionStart;

        // Content enters when localScroll = 0 (at bottom)
        // Content is centered when localScroll = viewportHeight * 0.15
        // Content exits when localScroll = viewportHeight + contentHeight

        // The content div uses CSS to position itself, we just need to ensure
        // the section is tall enough (which we do in calculateLayout)
      });
    }

    /**
     * Main scroll handler
     */
    function updateOnScroll() {
      const {
        scrollIntoBlock,
        isActive,
        hasStarted,
        isFinished
      } = getBlockScrollProgress();

      // Update block state classes
      block.classList.toggle('has-started', hasStarted);
      block.classList.toggle('is-active', isActive);
      block.classList.toggle('is-finished', isFinished);

      // Get current frame state
      const {
        activeIndex,
        transitioningToIndex,
        transitionProgress
      } = getCurrentFrameState(scrollIntoBlock);

      // Apply background transitions
      applyBackgroundTransitions(activeIndex, transitioningToIndex, transitionProgress);

      // Update content positions if needed
      updateContentPositions(scrollIntoBlock);

      // Store current frame for potential use by other scripts
      block.dataset.currentFrame = activeIndex;
      ticking = false;
    }

    /**
     * Throttled scroll handler
     */
    function onScroll() {
      if (!ticking) {
        window.requestAnimationFrame(updateOnScroll);
        ticking = true;
      }
    }

    /**
     * Handle resize
     */
    function onResize() {
      calculateLayout();
      updateOnScroll();
    }

    /**
     * Initialize
     */
    function init() {
      calculateLayout();

      // Set up event listeners
      window.addEventListener('scroll', onScroll, {
        passive: true
      });
      window.addEventListener('resize', onResize, {
        passive: true
      });

      // Recalculate after images load
      const images = block.querySelectorAll('img');
      let imagesLoaded = 0;
      const totalImages = images.length;
      images.forEach(img => {
        if (img.complete) {
          imagesLoaded++;
        } else {
          img.addEventListener('load', () => {
            imagesLoaded++;
            if (imagesLoaded === totalImages) {
              calculateLayout();
              updateOnScroll();
            }
          });
          img.addEventListener('error', () => {
            imagesLoaded++;
          });
        }
      });

      // Initial update
      updateOnScroll();

      // Mark as initialized
      block.dataset.initialized = 'true';
    }

    // Store cleanup function
    block._revealCleanup = function () {
      window.removeEventListener('scroll', onScroll);
      window.removeEventListener('resize', onResize);
    };

    // Initialize
    init();
  }

  /**
   * Initialize all reveal blocks on the page
   */
  function initAllBlocks() {
    document.querySelectorAll('.caes-reveal').forEach(block => {
      if (!block.dataset.initialized) {
        initRevealBlock(block);
      }
    });
  }

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAllBlocks);
  } else {
    initAllBlocks();
  }

  // Re-initialize if new blocks are added (for block editor compatibility)
  if (typeof MutationObserver !== 'undefined') {
    const observer = new MutationObserver(mutations => {
      mutations.forEach(mutation => {
        mutation.addedNodes.forEach(node => {
          if (node.nodeType === 1) {
            if (node.classList?.contains('caes-reveal')) {
              initRevealBlock(node);
            }
            node.querySelectorAll?.('.caes-reveal')?.forEach(initRevealBlock);
          }
        });
      });
    });
    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  }
})();
/******/ })()
;
//# sourceMappingURL=view.js.map