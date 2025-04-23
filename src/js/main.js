document.addEventListener('DOMContentLoaded', function () {

  /*** START TO TOP BUTTON */
  const toTopButton = document.createElement('a');
  toTopButton.classList.add('caes-hub-to-top');
  toTopButton.href = '#';
  toTopButton.innerHTML = `<span>Back to top</span>`;

  const main = document.querySelector('main');
  main.appendChild(toTopButton);

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
        }, 250);
      }
    }
  });

  toTopButton.addEventListener('click', function (event) {
    event.preventDefault();
    window.scrollTo({
      top: 0,
      behavior: 'smooth'
    });
  });
  /*** END TO TOP BUTTON */

  /*** ADD RESPONSITABLE WRAPPER */
  document.querySelectorAll('.single-post .wp-block-post-content table:not(.wp-block-table table),.single-publications .wp-block-post-content table:not(.wp-block-table table)').forEach(table => {
    if (!table.closest('figure.wp-block-table')) {
      const wrapper = document.createElement('div');
      wrapper.classList.add('responsitable-wrapper');
      table.parentNode.insertBefore(wrapper, table);
      wrapper.appendChild(table);
    }
  });
  /*** END ADD RESPONSITABLE WRAPPER */

  /*** RESPONSITABLE BEHAVIOR */
  requestAnimationFrame(() => {
    document.querySelectorAll('.wp-block-post-content table').forEach(table => {
      let wrapper = table.closest('.responsitable-wrapper');

      if (!wrapper) {
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
          wrapper = document.createElement('div');
          wrapper.classList.add('responsitable-wrapper');
          table.parentNode.insertBefore(wrapper, table);
          wrapper.appendChild(table);
        }
      }

      if (wrapper && !wrapper.querySelector('.responsitable-scroll-note')) {
        const note = document.createElement('p');
        note.innerHTML = '<em class="responsitable-scroll-note">(Scroll right for more)</em>';
        wrapper.insertBefore(note, table);
      }
    });
  });
  /*** END RESPONSITABLE BEHAVIOR */

  /*** REMOVE EMPTY PARAGRAPHS */
  const contentContainers = document.querySelectorAll('.post, .entry-content');
  contentContainers.forEach(container => {
    const paragraphs = container.querySelectorAll('p');
    paragraphs.forEach(p => {
      const html = p.innerHTML.replace(/<br\s*\/?>/gi, '').replace(/&#13;/g, '').replace(/&nbsp;/gi, '').trim();
      if (html === '') {
        p.remove();
      }
    });
  });

  /*** HANDLE LEGACY CONTENT CSS */

  const classicWrapper = document.querySelector(".classic-content-wrapper");

  if (!classicWrapper) return;

  // Helpers
  const isBlockClass = (el) =>
    Array.from(el.classList).some((cls) => cls.startsWith("wp-"));

  const applyLegacyAlignment = (el, target) => {
    const alignments = [
      { cls: "align-left", legacy: "legacy-figure-left" },
      { cls: "align-right", legacy: "legacy-figure-right" },
      { cls: "center", legacy: "legacy-figure-center" },
      { cls: "image-left", legacy: "legacy-figure-left" },
      { cls: "image-right", legacy: "legacy-figure-right" },
      { cls: "image-center", legacy: "legacy-figure-center" },
    ];

    alignments.forEach(({ cls, legacy }) => {
      if (el.classList.contains(cls)) {
        target.classList.add(legacy);
        el.classList.remove(cls);
      }
    });
  };

  // Step 1: Handle existing <figure> elements
  classicWrapper.querySelectorAll("figure").forEach((figure) => {
    if (!isBlockClass(figure)) {
      figure.classList.add("legacy-figure");
      applyLegacyAlignment(figure, figure);

      // Fallback: apply alignment based on inner images
      figure.querySelectorAll("img.legacy-image").forEach((img) => {
        applyLegacyAlignment(img, figure);
      });
    }
  });

  // Step 2: Wrap orphaned <img> tags in a <figure> and apply alignment
  classicWrapper.querySelectorAll("img").forEach((img) => {
    if (!isBlockClass(img)) {
      img.classList.add("legacy-image");

      if (!img.closest("figure")) {
        const wrapper = document.createElement("figure");
        wrapper.classList.add("legacy-figure");
        img.parentNode.insertBefore(wrapper, img);
        wrapper.appendChild(img);
        applyLegacyAlignment(img, wrapper);
      }
    }
  });

  // Step 3: Add legacy class to floated divs and align their inner images
  classicWrapper.querySelectorAll("div.left, div.right").forEach((div) => {
    if (!isBlockClass(div)) {
      div.classList.add("legacy-div");
    }

    const img = div.querySelector("img.image-left, img.image-right");
    const figure = img?.closest("figure.legacy-figure");
    if (img && figure) {
      applyLegacyAlignment(img, figure);
    }
  });

  // Step 4: Mark legacy tables
  classicWrapper.querySelectorAll("table").forEach((table) => {
    if (!isBlockClass(table)) {
      table.classList.add("legacy-table");
    }
  });

  // Step 5: Add legacy-table-caption if figcaption is next to a table or responsive wrapper
  classicWrapper.querySelectorAll("figcaption").forEach((figcaption) => {
    const sibling = figcaption.nextElementSibling;
    if (
      sibling &&
      (sibling.classList.contains("responsitable-wrapper") ||
        sibling.tagName === "TABLE")
    ) {
      figcaption.classList.add("legacy-table-caption");
    }
  });

  /*** END HANDLE LEGACY CONTENT CSS */

});