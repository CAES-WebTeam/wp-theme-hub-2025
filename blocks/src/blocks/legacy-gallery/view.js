/**
 * Legacy Gallery Block JavaScript
 * Handles filmstrip navigation and accessibility
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all gallery blocks on the page
    const galleries = document.querySelectorAll('.legacy-gallery-block');
    galleries.forEach(initGallery);
});

function initGallery(gallery) {
    const mainImage = gallery.querySelector('[data-gallery-main]');
    const caption = gallery.querySelector('[data-gallery-caption]');
    const thumbButtons = gallery.querySelectorAll('[data-gallery-thumb]');
    const srCurrent = gallery.querySelector('[data-gallery-sr-current]');
    
    if (!mainImage || !thumbButtons.length) return;
    
    let currentIndex = 0;
    const totalImages = thumbButtons.length;
    
    // Add click handlers to thumbnail buttons
    thumbButtons.forEach((button, index) => {
        button.addEventListener('click', () => {
            showImage(index);
        });
        
        // Add keyboard navigation
        button.addEventListener('keydown', (e) => {
            handleKeyboardNavigation(e, index);
        });
    });
    
    /**
     * Display the image at the specified index
     */
    function showImage(index) {
        if (index < 0 || index >= totalImages || index === currentIndex) return;
        
        const button = thumbButtons[index];
        const imageUrl = button.dataset.imageUrl;
        const imageAlt = button.dataset.imageAlt;
        const imageWidth = button.dataset.imageWidth;
        const imageHeight = button.dataset.imageHeight;
        const imageCaption = button.dataset.imageCaption;
        
        // Update main image
        mainImage.src = imageUrl;
        mainImage.alt = imageAlt;
        mainImage.width = imageWidth;
        mainImage.height = imageHeight;
        
        // Update caption
        if (caption) {
            if (imageCaption) {
                caption.innerHTML = imageCaption;
                caption.classList.remove('sr-only');
            } else {
                caption.innerHTML = `Image ${index + 1} of ${totalImages}`;
                caption.classList.add('sr-only');
            }
        }
        
        // Update active states
        updateActiveStates(index);
        
        // Update screen reader info
        if (srCurrent) {
            srCurrent.textContent = `Showing image ${index + 1} of ${totalImages}`;
        }
        
        currentIndex = index;
    }
    
    /**
     * Update active states and ARIA attributes
     */
    function updateActiveStates(activeIndex) {
        thumbButtons.forEach((button, index) => {
            const isActive = index === activeIndex;
            
            // Update visual active state
            button.classList.toggle('active', isActive);
            
            // Update ARIA attributes
            button.setAttribute('aria-selected', isActive.toString());
            button.tabIndex = isActive ? 0 : -1;
        });
    }
    
    /**
     * Handle keyboard navigation within the filmstrip
     */
    function handleKeyboardNavigation(e, currentButtonIndex) {
        let newIndex = currentButtonIndex;
        let handled = false;
        
        switch (e.key) {
            case 'ArrowLeft':
                newIndex = currentButtonIndex > 0 ? currentButtonIndex - 1 : totalImages - 1;
                handled = true;
                break;
                
            case 'ArrowRight':
                newIndex = currentButtonIndex < totalImages - 1 ? currentButtonIndex + 1 : 0;
                handled = true;
                break;
                
            case 'Home':
                newIndex = 0;
                handled = true;
                break;
                
            case 'End':
                newIndex = totalImages - 1;
                handled = true;
                break;
                
            case 'Enter':
            case ' ':
                // Space or Enter - show this image
                showImage(currentButtonIndex);
                handled = true;
                break;
        }
        
        if (handled) {
            e.preventDefault();
            
            // For arrow keys and Home/End, move focus and show image
            if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(e.key)) {
                thumbButtons[newIndex].focus();
                showImage(newIndex);
            }
        }
    }
    
    /**
     * Optional: Add swipe support for touch devices
     */
    function addSwipeSupport() {
        let startX = null;
        let startY = null;
        const minSwipeDistance = 50;
        
        mainImage.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
        }, { passive: true });
        
        mainImage.addEventListener('touchend', (e) => {
            if (!startX || !startY) return;
            
            const endX = e.changedTouches[0].clientX;
            const endY = e.changedTouches[0].clientY;
            
            const deltaX = endX - startX;
            const deltaY = endY - startY;
            
            // Only trigger if horizontal swipe is longer than vertical
            if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > minSwipeDistance) {
                if (deltaX > 0) {
                    // Swipe right - previous image
                    const prevIndex = currentIndex > 0 ? currentIndex - 1 : totalImages - 1;
                    showImage(prevIndex);
                } else {
                    // Swipe left - next image
                    const nextIndex = currentIndex < totalImages - 1 ? currentIndex + 1 : 0;
                    showImage(nextIndex);
                }
            }
            
            startX = null;
            startY = null;
        }, { passive: true });
    }
    
    // Initialize swipe support
    addSwipeSupport();
    
    // Optional: Auto-focus first thumbnail when gallery comes into view
    // (Useful for keyboard users)
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                // Gallery is visible, but don't auto-focus unless user is keyboard navigating
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });
    
    observer.observe(gallery);
}