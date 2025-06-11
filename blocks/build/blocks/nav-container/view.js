/******/ (() => { // webpackBootstrap
/*!******************************************!*\
  !*** ./src/blocks/nav-container/view.js ***!
  \******************************************/
/**
 * Field Report Navigation JavaScript
 * Handles hover/focus interactions and keyboard accessibility
 */

document.addEventListener('DOMContentLoaded', function () {
  // Initialize all navigation blocks on the page
  const navBlocks = document.querySelectorAll('.field-report-navigation');
  navBlocks.forEach(initNavigation);
});
function initNavigation(nav) {
  const hoverDelay = parseInt(nav.dataset.hoverDelay) || 300;
  const submenuItems = nav.querySelectorAll('.nav-item-with-submenu');
  submenuItems.forEach(item => {
    const toggle = item.querySelector('.submenu-toggle');
    const flyout = item.querySelector('.nav-flyout');
    const primaryLink = item.querySelector('.nav-primary-link');
    if (!toggle || !flyout) return;
    let hoverTimer = null;
    let isOpen = false;

    // Toggle button click/keyboard activation
    toggle.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      if (isOpen) {
        closeFlyout();
      } else {
        openFlyout();
      }
    });

    // Handle keyboard activation (Enter/Space)
    toggle.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        e.stopPropagation();
        if (isOpen) {
          closeFlyout();
        } else {
          openFlyout();
        }
      }

      // Escape key to close
      if (e.key === 'Escape' && isOpen) {
        closeFlyout();
        toggle.focus(); // Return focus to toggle
      }
    });

    // Hover to open (with delay) - covers the entire nav item
    item.addEventListener('mouseenter', function () {
      clearTimeout(hoverTimer);
      hoverTimer = setTimeout(() => {
        if (!isOpen) {
          openFlyout();
        }
      }, hoverDelay);
    });

    // Mouse leave to close - but only when leaving the entire nav item (including flyout)
    item.addEventListener('mouseleave', function () {
      clearTimeout(hoverTimer);
      if (isOpen) {
        closeFlyout();
      }
    });

    // Close when focus leaves the entire submenu (for keyboard users)
    item.addEventListener('focusout', function (e) {
      // Use setTimeout to allow focus to move to new element
      setTimeout(() => {
        // Only close if focus has completely left this nav item
        if (!item.contains(document.activeElement)) {
          closeFlyout();
        }
      }, 10);
    });
    function openFlyout() {
      if (isOpen) return;

      // Close other open flyouts in this navigation
      nav.querySelectorAll('.nav-item-with-submenu').forEach(otherItem => {
        if (otherItem !== item) {
          const otherToggle = otherItem.querySelector('.submenu-toggle');
          const otherFlyout = otherItem.querySelector('.nav-flyout');
          if (otherToggle && otherFlyout) {
            otherToggle.setAttribute('aria-expanded', 'false');
            otherFlyout.style.display = 'none';
            otherItem.classList.remove('submenu-open');
          }
        }
      });
      isOpen = true;
      toggle.setAttribute('aria-expanded', 'true');
      flyout.style.display = 'block';

      // Add class for CSS animations if desired
      item.classList.add('submenu-open');
    }
    function closeFlyout() {
      if (!isOpen) return;
      isOpen = false;
      toggle.setAttribute('aria-expanded', 'false');
      flyout.style.display = 'none';
      item.classList.remove('submenu-open');
    }
  });

  // Close flyouts when clicking outside
  document.addEventListener('click', function (e) {
    if (!nav.contains(e.target)) {
      nav.querySelectorAll('.nav-item-with-submenu').forEach(item => {
        const toggle = item.querySelector('.submenu-toggle');
        const flyout = item.querySelector('.nav-flyout');
        if (toggle && flyout) {
          toggle.setAttribute('aria-expanded', 'false');
          flyout.style.display = 'none';
          item.classList.remove('submenu-open');
        }
      });
    }
  });
}
/******/ })()
;
//# sourceMappingURL=view.js.map