var __webpack_exports__ = {};
/*!***************************************!*\
  !*** ./src/blocks/carousel-2/view.js ***!
  \***************************************/
document.addEventListener('DOMContentLoaded', function () {
  const carousel = document.querySelector('.wp-block-caes-hub-carousel-2');
  if (!carousel) return;
  const slides = carousel.querySelectorAll('.caes-hub-carousel-slide');
  const prevBtn = carousel.querySelector('.btn-prev');
  const nextBtn = carousel.querySelector('.btn-next');
  const pauseBtn = carousel.querySelector('.btn-pause');
  const counter = carousel.querySelector('.carousel-counter');
  let currentIndex = 0;
  let isPlaying = true;
  let autoplayInterval;
  const totalSlides = slides.length;
  function updateCounter() {
    counter.textContent = `${currentIndex + 1} / ${totalSlides}`;
  }
  function showSlide(index) {
    slides.forEach((slide, i) => {
      slide.style.opacity = i === index ? '1' : '0';
      slide.style.zIndex = i === index ? '1' : '0';
      slide.setAttribute('aria-hidden', i !== index);
    });
    updateCounter();
    adjustCarouselHeight();
  }
  function nextSlide() {
    currentIndex = (currentIndex + 1) % totalSlides;
    showSlide(currentIndex);
  }
  function prevSlide() {
    currentIndex = (currentIndex - 1 + totalSlides) % totalSlides;
    showSlide(currentIndex);
  }
  function pauseAutoplay() {
    isPlaying = false;
    clearInterval(autoplayInterval);
    pauseBtn.setAttribute('aria-pressed', 'false');
    pauseBtn.querySelector('.sr-only').textContent = 'Play';
  }
  function playAutoplay() {
    isPlaying = true;
    autoplayInterval = setInterval(nextSlide, 6000);
    pauseBtn.setAttribute('aria-pressed', 'true');
    pauseBtn.querySelector('.sr-only').textContent = 'Pause';
  }
  function adjustCarouselHeight() {
    const carouselSlides = document.querySelector('.caes-hub-carousel-slides');
    const slides = carouselSlides?.querySelectorAll('.caes-hub-carousel-slide');
    const activeSlide = slides?.[currentIndex];
    if (carouselSlides && activeSlide) {
      const slideHeight = activeSlide.offsetHeight;
      carouselSlides.style.height = `${slideHeight}px`;
    }
  }
  function controlsDistance() {
    const wrapper = document.querySelector('.caes-hub-carousel-slide__content-wrapper');
    const content = document.querySelector('.caes-hub-carousel-slide__content');
    const controls = document.querySelector('.caes-hub-carousel-controls-wrapper');
    if (wrapper && content && controls) {
      const screenWidth = window.innerWidth;
      const wrapperBottom = wrapper.getBoundingClientRect().bottom;
      const contentBottom = content.getBoundingClientRect().bottom;
      const bottomDistance = wrapperBottom - contentBottom;
      const controlsHeight = controls.getBoundingClientRect().height;
      const controlsBottom = bottomDistance - controlsHeight - 10;
      if (screenWidth >= 1100 && screenWidth <= 1555) {
        const wrapperWidth = wrapper.getBoundingClientRect().width;
        controls.style.right = 'unset';
        controls.style.bottom = '10px';
        controls.style.left = wrapperWidth + 10 + 'px';
      } else {
        const wrapperLeft = wrapper.getBoundingClientRect().left;
        const contentLeft = content.getBoundingClientRect().left;
        const leftDistance = (wrapperLeft - contentLeft) * -1;
        controls.style.bottom = `${controlsBottom}px`;
        controls.style.left = `${leftDistance}px`;
        controls.style.right = 'auto';
      }
      controls.style.opacity = '1';
      controls.style.visibility = 'visible';
    }
  }
  window.addEventListener('load', () => {
    adjustCarouselHeight();
    controlsDistance();
    carousel.classList.add('loaded');
    setTimeout(() => {
      adjustCarouselHeight();
      controlsDistance();
    }, 100);
    const images = carousel.querySelectorAll('img');
    images.forEach(img => {
      if (!img.complete) {
        img.addEventListener('load', () => {
          adjustCarouselHeight();
          controlsDistance();
        });
      }
    });
  });
  window.addEventListener('resize', () => {
    adjustCarouselHeight();
    controlsDistance();
  });
  pauseBtn.addEventListener('click', () => {
    isPlaying ? pauseAutoplay() : playAutoplay();
  });
  nextBtn.addEventListener('click', () => {
    nextSlide();
    if (isPlaying) pauseAutoplay();
  });
  prevBtn.addEventListener('click', () => {
    prevSlide();
    if (isPlaying) pauseAutoplay();
  });

  // Initialize
  slides.forEach((slide, i) => {
    slide.setAttribute('aria-hidden', i !== 0);
    slide.style.transition = 'opacity 0.5s ease';
    slide.style.opacity = i === 0 ? '1' : '0';
    slide.style.position = 'absolute';
    slide.style.top = '0';
    slide.style.left = '0';
    slide.style.width = '100%';
  });
  carousel.querySelector('.caes-hub-carousel-slides').style.position = 'relative';
  showSlide(currentIndex);
  playAutoplay();

  // Stop autoplay on mobile if carousel is mostly off screen
  if (window.innerWidth < 784) {
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (!entry.isIntersecting && isPlaying) {
          pauseAutoplay();
        }
      });
    }, {
      root: null,
      threshold: 0.5 // At least 50% must be visible to keep playing
    });
    observer.observe(carousel);
  }
});

//# sourceMappingURL=view.js.map