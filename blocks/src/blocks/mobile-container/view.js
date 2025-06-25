/**
 * Mobile Container JavaScript
 * Handles hamburger menu toggle functionality and overlay behavior
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all mobile containers on the page
    const mobileContainers = document.querySelectorAll('.mobile-container');
    
    mobileContainers.forEach(initMobileContainer);
});

function initMobileContainer(container) {
    const trigger = container.querySelector('[data-mobile-trigger]');
    const overlay = container.querySelector('[data-mobile-overlay]');
    const closeTriggers = container.querySelectorAll('[data-overlay-close]');
    
    if (!trigger || !overlay) return;
    
    let isOpen = false;
    let previousFocus = null;
    
    // Open overlay
    function openOverlay() {
        if (isOpen) return;
        
        // Store current focus for restoration later
        previousFocus = document.activeElement;
        
        isOpen = true;
        trigger.setAttribute('aria-expanded', 'true');
        overlay.setAttribute('aria-hidden', 'false');
        overlay.classList.add('is-open');
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
        
        // Focus management - focus first focusable element in overlay
        setTimeout(() => {
            const firstFocusable = overlay.querySelector(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            if (firstFocusable) {
                firstFocusable.focus();
            }
        }, 100); // Small delay to allow animation to start
        
        // Add class to trigger for styling
        trigger.classList.add('is-active');
    }
    
    // Close overlay
    function closeOverlay() {
        if (!isOpen) return;
        
        isOpen = false;
        trigger.setAttribute('aria-expanded', 'false');
        overlay.setAttribute('aria-hidden', 'true');
        overlay.classList.remove('is-open');
        
        // Restore body scroll
        document.body.style.overflow = '';
        
        // Restore focus
        if (previousFocus) {
            previousFocus.focus();
            previousFocus = null;
        }
        
        // Remove active class from trigger
        trigger.classList.remove('is-active');
    }
    
    // Toggle overlay
    function toggleOverlay() {
        if (isOpen) {
            closeOverlay();
        } else {
            openOverlay();
        }
    }
    
    // Event listeners
    trigger.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        toggleOverlay();
    });
    
    // Close triggers (close button, backdrop)
    closeTriggers.forEach(closeTrigger => {
        closeTrigger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeOverlay();
        });
    });
    
    // Keyboard handling
    document.addEventListener('keydown', function(e) {
        if (!isOpen) return;
        
        // Escape key closes overlay
        if (e.key === 'Escape') {
            e.preventDefault();
            closeOverlay();
            return;
        }
        
        // Tab key trap focus within overlay
        if (e.key === 'Tab') {
            trapFocus(e, overlay);
        }
    });
    
    // Close on window resize if we move to desktop size
    window.addEventListener('resize', function() {
        const breakpoint = container.dataset.mobileBreakpoint || '768px';
        const breakpointValue = parseInt(breakpoint);
        
        if (window.innerWidth >= breakpointValue && isOpen) {
            closeOverlay();
        }
    });
}

/**
 * Trap focus within the overlay for accessibility
 */
function trapFocus(e, container) {
    const focusableElements = container.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    
    if (focusableElements.length === 0) return;
    
    const firstFocusable = focusableElements[0];
    const lastFocusable = focusableElements[focusableElements.length - 1];
    
    // If shift+tab on first element, focus last element
    if (e.shiftKey && document.activeElement === firstFocusable) {
        e.preventDefault();
        lastFocusable.focus();
    }
    // If tab on last element, focus first element
    else if (!e.shiftKey && document.activeElement === lastFocusable) {
        e.preventDefault();
        firstFocusable.focus();
    }
}