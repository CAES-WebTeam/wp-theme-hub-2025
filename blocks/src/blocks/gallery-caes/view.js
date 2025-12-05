/**
 * CAES Gallery Frontend JavaScript - Parvus Integration
 * Handles thumbnail trigger mode click events
 */

document.addEventListener('DOMContentLoaded', function() {
    const galleries = document.querySelectorAll('.caes-gallery-parvus.has-thumbnail-trigger');
    
    galleries.forEach(gallery => {
        const triggerBtn = gallery.querySelector('.view-gallery-btn');
        const triggerArea = gallery.querySelector('.gallery-trigger-visible');
        const firstLink = gallery.querySelector('.parvus-gallery a.lightbox');
        
        if (triggerBtn && firstLink) {
            // Click the button
            triggerBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                firstLink.click();
            });
            
            // Click the image area (but not the button)
            if (triggerArea) {
                triggerArea.addEventListener('click', (e) => {
                    if (e.target !== triggerBtn && !triggerBtn.contains(e.target)) {
                        e.preventDefault();
                        firstLink.click();
                    }
                });
            }
        }
    });
});