var __webpack_exports__ = {};
/*!*************************************!*\
  !*** ./src/blocks/carousel/view.js ***!
  \*************************************/
document.querySelectorAll('.wp-block-caes-hub-carousel').forEach(carousel => {
  const slides = carousel.querySelectorAll('.caes-hub-carousel-slide');
  const nextButton = carousel.querySelector('.btn-next');
  const prevButton = carousel.querySelector('.btn-prev');
  const playPauseButton = carousel.querySelector('.btn-pause');
  const slideNavButtons = carousel.querySelectorAll('.caes-hub-carousel-nav button');
  const navStrip = carousel.querySelector('.caes-hub-carousel-nav ul');
  const itemWidth = 200; // Each item is 200px wide
  let currentSlide = 0;
  let autoplay = true;
  let intervalId;

  // Live region for accessibility
  const liveregion = document.createElement('div');
  liveregion.setAttribute('aria-live', 'polite');
  liveregion.setAttribute('aria-atomic', 'true');
  liveregion.setAttribute('class', 'liveregion sr-only');
  carousel.appendChild(liveregion);
  function updateCarousel() {
    slides.forEach((slide, index) => {
      if (index === currentSlide) {
        slide.style.opacity = '1';
        slide.style.visibility = 'visible';
        slide.setAttribute('aria-hidden', 'false');
      } else {
        slide.style.opacity = '0';
        slide.style.visibility = 'hidden';
        slide.setAttribute('aria-hidden', 'true');
      }
    });
    slideNavButtons.forEach((button, index) => {
      button.classList.toggle('current', index === currentSlide);
    });

    // Calculate the amount to translate based on the currentSlide
    const translateValue = -((currentSlide - 1) * itemWidth); // Shift left so current item is second
    navStrip.style.transform = `translateX(${translateValue}px)`;

    // Add smooth scrolling effect to the nav strip
    navStrip.style.transition = 'transform 0.5s ease-in-out';
    playPauseButton.setAttribute('aria-pressed', autoplay);
    playPauseButton.innerHTML = autoplay ? '<span class="sr-only">Stop Slides</span>' : '<span class="sr-only">Start Slides</span>';
  }
  function nextSlide() {
    currentSlide = (currentSlide + 1) % slides.length;
    updateCarousel();
    liveregion.textContent = `Item ${currentSlide + 1} of ${slides.length}.`; // Announce slide
  }
  function previousSlide() {
    currentSlide = (currentSlide - 1 + slides.length) % slides.length;
    updateCarousel();
    liveregion.textContent = `Item ${currentSlide + 1} of ${slides.length}.`; // Announce slide
  }
  function goToSlide(index) {
    currentSlide = index;
    updateCarousel();
    liveregion.textContent = `Item ${currentSlide + 1} of ${slides.length}.`; // Announce slide
  }
  function play() {
    autoplay = true;
    intervalId = setInterval(nextSlide, 8000);
  }
  function pause() {
    autoplay = false;
    clearInterval(intervalId);
  }
  nextButton.addEventListener('click', () => {
    pause();
    nextSlide();
  });
  prevButton.addEventListener('click', () => {
    pause();
    previousSlide();
  });
  playPauseButton.addEventListener('click', () => {
    if (autoplay) {
      pause();
    } else {
      play();
    }
    updateCarousel(); // Update after toggling autoplay
  });
  slideNavButtons.forEach((button, index) => {
    button.addEventListener('click', () => {
      pause();
      goToSlide(index);
    });
    button.addEventListener('keydown', event => {
      if (event.key === 'Enter' || event.key === ' ') {
        pause();
        goToSlide(index);
        event.preventDefault();
      }
    });
  });

  // Initialize the carousel
  updateCarousel();

  // Start autoplay if enabled
  if (autoplay) {
    play();
  }
});

//# sourceMappingURL=view.js.map