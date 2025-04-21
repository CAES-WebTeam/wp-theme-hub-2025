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