/******/ (() => { // webpackBootstrap
/*!*********************************************!*\
  !*** ./src/blocks/lightbox-gallery/view.js ***!
  \*********************************************/
/**
 * Lightbox Gallery Frontend JavaScript
 * Handles accessible lightbox functionality with keyboard navigation and focus management
 */

document.addEventListener('DOMContentLoaded', function () {
  class LightboxGallery {
    constructor(galleryElement) {
      this.gallery = galleryElement;
      this.galleryId = galleryElement.id;

      // Get images data from data attribute
      try {
        const imagesData = this.gallery.dataset.images;
        this.images = imagesData ? JSON.parse(imagesData) : [];
      } catch (e) {
        console.error('Error parsing gallery images data:', e);
        this.images = [];
      }
      this.currentImageIndex = 0;
      this.lastFocusedElement = null;

      // Get DOM elements
      this.modal = this.gallery.querySelector('.lightbox-modal');
      this.lightboxImage = this.modal.querySelector('.lightbox-image');
      this.lightboxCaption = this.modal.querySelector('.lightbox-caption');
      this.closeBtn = this.modal.querySelector('.lightbox-close');
      this.prevBtn = this.modal.querySelector('.prev-btn');
      this.nextBtn = this.modal.querySelector('.next-btn');
      this.backdrop = this.modal.querySelector('.lightbox-backdrop');

      // Gallery elements (updated selectors for simplified structure)
      this.viewGalleryBtn = this.gallery.querySelector('.view-gallery-btn');
      this.galleryTrigger = this.gallery.querySelector('.gallery-trigger');
      this.triggerImage = this.gallery.querySelector('.gallery-trigger-image');
      this.init();
    }
    init() {
      this.bindEvents();
    }
    bindEvents() {
      // View Gallery button
      if (this.viewGalleryBtn) {
        this.viewGalleryBtn.addEventListener('click', e => {
          e.preventDefault();
          e.stopPropagation();
          this.openLightbox(0);
        });
        this.viewGalleryBtn.addEventListener('keydown', e => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            e.stopPropagation();
            this.openLightbox(0);
          }
        });
      }

      // Also allow clicking the gallery trigger area to open lightbox
      if (this.galleryTrigger) {
        this.galleryTrigger.addEventListener('click', e => {
          // Only trigger if clicking on the image itself, not the button
          if (e.target === this.triggerImage || e.target === this.galleryTrigger) {
            e.preventDefault();
            this.openLightbox(0);
          }
        });
      }

      // Lightbox controls
      if (this.closeBtn) {
        this.closeBtn.addEventListener('click', () => this.closeLightbox());
      }
      if (this.prevBtn) {
        this.prevBtn.addEventListener('click', () => this.goToPrevImage());
      }
      if (this.nextBtn) {
        this.nextBtn.addEventListener('click', () => this.goToNextImage());
      }

      // Backdrop click to close
      if (this.backdrop) {
        this.backdrop.addEventListener('click', () => this.closeLightbox());
      }

      // Global keyboard events for lightbox
      document.addEventListener('keydown', e => this.handleGlobalKeydown(e));
    }
    openLightbox(index = 0) {
      if (this.images.length === 0) {
        return;
      }
      this.currentImageIndex = index;
      this.lastFocusedElement = document.activeElement;

      // Update lightbox content
      this.updateLightboxImage();

      // Show modal
      this.modal.setAttribute('aria-hidden', 'false');
      this.modal.style.display = 'flex';

      // Prevent body scroll
      document.body.style.overflow = 'hidden';
      document.body.classList.add('lightbox-open');

      // Focus management
      setTimeout(() => {
        if (this.closeBtn) {
          this.closeBtn.focus();
        }
      }, 100);
    }
    closeLightbox() {
      // Hide modal
      this.modal.setAttribute('aria-hidden', 'true');
      this.modal.style.display = 'none';

      // Restore body scroll
      document.body.style.overflow = '';
      document.body.classList.remove('lightbox-open');

      // Return focus to last focused element
      if (this.lastFocusedElement) {
        this.lastFocusedElement.focus();
      }
    }
    updateLightboxImage() {
      const image = this.images[this.currentImageIndex];
      if (!image) {
        return;
      }

      // Update the lightbox image
      if (this.lightboxImage) {
        this.lightboxImage.src = image.url;
        this.lightboxImage.alt = image.alt || '';
      }

      // Update caption
      if (this.lightboxCaption) {
        this.lightboxCaption.innerHTML = image.caption || '';
      }

      // Update navigation button states
      if (this.prevBtn) {
        this.prevBtn.disabled = this.images.length <= 1;
      }
      if (this.nextBtn) {
        this.nextBtn.disabled = this.images.length <= 1;
      }
    }
    goToNextImage() {
      if (this.images.length <= 1) return;
      this.currentImageIndex = (this.currentImageIndex + 1) % this.images.length;
      this.updateLightboxImage();
    }
    goToPrevImage() {
      if (this.images.length <= 1) return;
      this.currentImageIndex = (this.currentImageIndex - 1 + this.images.length) % this.images.length;
      this.updateLightboxImage();
    }
    handleGlobalKeydown(e) {
      // Only handle when lightbox is open
      if (this.modal.getAttribute('aria-hidden') === 'true') {
        return;
      }
      switch (e.key) {
        case 'Escape':
          e.preventDefault();
          this.closeLightbox();
          break;
        case 'ArrowLeft':
          e.preventDefault();
          this.goToPrevImage();
          break;
        case 'ArrowRight':
          e.preventDefault();
          this.goToNextImage();
          break;
        case 'Tab':
          this.handleFocusTrap(e);
          break;
      }
    }
    handleFocusTrap(e) {
      const focusableElements = [this.closeBtn, this.prevBtn, this.nextBtn].filter(el => el && !el.disabled);
      if (focusableElements.length === 0) return;
      const firstElement = focusableElements[0];
      const lastElement = focusableElements[focusableElements.length - 1];
      if (e.shiftKey) {
        // Shift + Tab
        if (document.activeElement === firstElement) {
          e.preventDefault();
          lastElement.focus();
        }
      } else {
        // Tab
        if (document.activeElement === lastElement) {
          e.preventDefault();
          firstElement.focus();
        }
      }
    }
  }

  // Initialize all lightbox galleries on the page
  const galleries = document.querySelectorAll('.lightbox-gallery');
  galleries.forEach(gallery => {
    new LightboxGallery(gallery);
  });
});
/******/ })()
;
//# sourceMappingURL=view.js.map