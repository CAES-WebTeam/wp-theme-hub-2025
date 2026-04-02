/******/ (() => { // webpackBootstrap
/*!***********************************************!*\
  !*** ./src/blocks/pub-details-number/view.js ***!
  \***********************************************/
document.addEventListener('DOMContentLoaded', function () {
  // Skip tooltip logic if screen width is 782px or less
  if (window.matchMedia('(max-width: 782px)').matches) return;
  const triggers = document.querySelectorAll('.tooltip-trigger');
  triggers.forEach(trigger => {
    const tooltipId = trigger.getAttribute('aria-describedby');
    const tooltip = document.getElementById(tooltipId);
    if (!tooltip) return;

    // Show tooltip
    const showTooltip = () => {
      tooltip.classList.add('visible');
    };

    // Hide tooltip, with hover check
    const hideTooltip = () => {
      setTimeout(() => {
        if (!tooltip.matches(':hover')) {
          tooltip.classList.remove('visible');
        }
      }, 100);
    };

    // Hide on Escape key
    const handleKeydown = e => {
      if (e.key === 'Escape' || e.key === 'Esc') {
        tooltip.classList.remove('visible');
      }
    };

    // Event listeners
    trigger.addEventListener('focus', showTooltip);
    trigger.addEventListener('mouseenter', showTooltip);
    trigger.addEventListener('blur', hideTooltip);
    trigger.addEventListener('mouseleave', hideTooltip);
    trigger.addEventListener('keydown', handleKeydown);
    tooltip.addEventListener('mouseleave', () => {
      tooltip.classList.remove('visible');
    });
  });
});
/******/ })()
;
//# sourceMappingURL=view.js.map