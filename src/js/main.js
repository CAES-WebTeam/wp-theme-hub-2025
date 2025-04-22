// Back to top button

// Wait for page to load
document.addEventListener('DOMContentLoaded', function () {

  /*** START TO TOP BUTTON */
  // Make back to top button
  const toTopButton = document.createElement('a');
  toTopButton.classList.add('caes-hub-to-top');
  toTopButton.href = '#';
  toTopButton.innerHTML = `<span>Back to top</span>`;

  // Append toTopButton to main
  const main = document.querySelector('main');
  main.appendChild(toTopButton);

  // Show or hide button if scroll position is lower than viewport height
  window.addEventListener('scroll', function () {
    if (window.scrollY > window.innerHeight / 2) {
      if (!toTopButton.classList.contains('caes-hub-to-top-visible')) {
        toTopButton.classList.add('caes-hub-to-top-visible');
      }
    } else {
      if (toTopButton.classList.contains('caes-hub-to-top-visible')) {
        toTopButton.classList.add('caes-hub-to-top-hide');
        setTimeout(() => {
          toTopButton.classList.remove('caes-hub-to-top-visible', 'caes-hub-to-top-hide');
        }, 250); // Match the timeout to the CSS transition duration for opacity
      }
    }
  });

  // Add event listener to toTopButton
  toTopButton.addEventListener('click', function (event) {
    event.preventDefault();
    window.scrollTo({
      top: 0,
      behavior: 'smooth'
    });
  });
  /*** END TO TOP BUTTON */

  /*** START TABLE FIX */
  // Find tables without wp-block-table class
  const tables = document.querySelectorAll('.wp-block-post-content table:not(.wp-block-table table)');
  tables.forEach(table => {
    // Check if the table is already inside a <figure.wp-block-table>
    if (!table.closest('figure.wp-block-table')) {
      // Add wrapper div
      const wrapper = document.createElement('div');
      wrapper.classList.add('responsitable-wrapper');
      table.parentNode.insertBefore(wrapper, table);
      wrapper.appendChild(table);
    }
  });

});

// Remove empty paragraphs
document.addEventListener('DOMContentLoaded', function () {
  const contentContainers = document.querySelectorAll('.post, .entry-content');

  contentContainers.forEach(container => {
    const paragraphs = container.querySelectorAll('p');

    paragraphs.forEach(p => {
      const html = p.innerHTML.replace(/<br\s*\/?>/gi, '').replace(/&#13;/g, '').replace(/&nbsp;/gi, '').trim();

      // If it's empty or only whitespace after cleaning
      if (html === '') {
        p.remove();
      }
    });
  });
});

// Responsitables

document.addEventListener('DOMContentLoaded', () => {
  requestAnimationFrame(() => {
    document.querySelectorAll('.entry-content table').forEach(table => {
      const wrapper = table.closest('.responsitable-wrapper');

      // Function to add the scroll note if missing
      const ensureScrollNote = (wrapperElement, referenceTable) => {
        const existingNote = wrapperElement.querySelector('.responsitable-scroll-note');
        if (!existingNote) {
          const note = document.createElement('p');
          note.innerHTML = '<em class="responsitable-scroll-note">(Scroll right for more)</em>';
          wrapperElement.insertBefore(note, referenceTable);
        }
      };

      // Already wrapped — just ensure the note is there
      if (wrapper) {
        ensureScrollNote(wrapper, table);
        return;
      }

      // Create a temporary clone to test overflow
      const clone = table.cloneNode(true);
      clone.style.position = 'absolute';
      clone.style.visibility = 'hidden';
      clone.style.height = 'auto';
      clone.style.width = 'auto';
      clone.style.maxWidth = 'none';
      document.body.appendChild(clone);

      const isOverflowing = clone.scrollWidth > clone.clientWidth;
      document.body.removeChild(clone);

      if (isOverflowing) {
        const newWrapper = document.createElement('div');
        newWrapper.className = 'responsitable-wrapper';

        table.parentNode.insertBefore(newWrapper, table);
        newWrapper.appendChild(table);

        ensureScrollNote(newWrapper, table);
      }
    });
  });
});

// Legacy support for imported in figures and images.
document.addEventListener("DOMContentLoaded", function () {

  // Check if the classic content wrapper exists
  const classicWrapper = document.querySelector(".classic-content-wrapper");
  
  // Proceed if the wrapper exists
  if (classicWrapper) {
    // Handle <figure>
    document.querySelectorAll(".classic-content-wrapper figure").forEach((figure) => {
      const classList = Array.from(figure.classList);
      const isBlock = classList.some(cls => cls.startsWith("wp-"));
      if (!isBlock) {
        figure.classList.add("legacy-figure");
  
        // Alignment handling
        if (figure.classList.contains("align-left")) {
          figure.classList.add("legacy-figure-left");
          figure.classList.remove("align-left");
        } else if (figure.classList.contains("align-right")) {
          figure.classList.add("legacy-figure-right");
          figure.classList.remove("align-right");
        } else if (figure.classList.contains("center")) {
          figure.classList.add("legacy-figure-center");
          figure.classList.remove("center");
        } else {
          // No alignment class on figure, check images inside
          const imgs = figure.querySelectorAll("img.legacy-image");
          imgs.forEach((img) => {
            if (img.classList.contains("image-left")) {
              figure.classList.add("legacy-figure-left");
              img.classList.remove("image-left");
            } else if (img.classList.contains("image-right")) {
              figure.classList.add("legacy-figure-right");
              img.classList.remove("image-right");
            } else if (img.classList.contains("image-center")) {
              figure.classList.add("legacy-figure-center");
              img.classList.remove("image-center");
            }
          });
        }
      }
    });
  
    // Handle <img>
    document.querySelectorAll(".classic-content-wrapper img").forEach((img) => {
      const classList = Array.from(img.classList);
      const isBlock = classList.some(cls => cls.startsWith("wp-"));
      if (!isBlock) {
        img.classList.add("legacy-image");
  
        // If img has no wrapping <figure>, wrap it and assign alignment
        const parentFigure = img.closest("figure");
        if (!parentFigure) {
          const wrapper = document.createElement("figure");
          wrapper.classList.add("legacy-figure");
  
          // Insert wrapper before img, then move img inside
          img.parentNode.insertBefore(wrapper, img);
          wrapper.appendChild(img);
  
          // Apply alignment based on img classes
          if (img.classList.contains("image-left")) {
            wrapper.classList.add("legacy-figure-left");
            img.classList.remove("image-left");
          } else if (img.classList.contains("image-right")) {
            wrapper.classList.add("legacy-figure-right");
            img.classList.remove("image-right");
          } else if (img.classList.contains("image-center")) {
            wrapper.classList.add("legacy-figure-center");
            img.classList.remove("image-center");
          }
        }
      }
    });
  
    // Handle <div class="left"> or <div class="right">
    document.querySelectorAll(".classic-content-wrapper div.left, .classic-content-wrapper div.right").forEach((div) => {
      const classList = Array.from(div.classList);
      const isBlock = classList.some(cls => cls.startsWith("wp-"));
      if (!isBlock) {
        div.classList.add("legacy-div");
      }
  
      // Check for image alignment in child images
      const img = div.querySelector("img.image-left, img.image-right");
      if (img) {
        const figure = img.closest("figure.legacy-figure");
        if (figure) {
          if (img.classList.contains("image-left")) {
            figure.classList.add("legacy-figure-left");
          } else if (img.classList.contains("image-right")) {
            figure.classList.add("legacy-figure-right");
          }
        }
      }
    });
  
    // Handle <table>
    document.querySelectorAll(".classic-content-wrapper table").forEach((table) => {
      const classList = Array.from(table.classList);
      const isBlock = classList.some(cls => cls.startsWith("wp-"));
      if (!isBlock) {
        table.classList.add("legacy-table");
      }
    });
  }
});