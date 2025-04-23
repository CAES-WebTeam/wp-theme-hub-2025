
// Handle responsive tables function

// Wrap all tables in a responsitable-wrapper
function wrapResponsiveTables() {
  const tables = document.querySelectorAll('figure > table, .wp-block-table');

  tables.forEach((table) => {
    const figure = table.closest('figure') || table;

    // Skip if already wrapped
    if (figure.parentElement.classList.contains('responsitable-wrapper')) return;

    const wrapper = document.createElement('div');
    wrapper.classList.add('responsitable-wrapper');

    figure.parentNode.insertBefore(wrapper, figure);
    wrapper.appendChild(figure);
  });
}

function handleOverflowScroll() {
  const wrappers = document.querySelectorAll('.responsitable-wrapper');

  wrappers.forEach((wrapper) => {
    const table = wrapper.querySelector('table');
    if (!table) return;

    // Reset scroll class
    wrapper.classList.remove('responsitable-scroll');

    // Check for overflow
    if (table.scrollWidth > wrapper.clientWidth) {
      wrapper.classList.add('responsitable-scroll');
    }
  });
}
// End responsive tables function

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

  // Step 4.5: Wrap legacy tables in <figure> and move <caption> to <figcaption>
  classicWrapper.querySelectorAll("table.legacy-table").forEach((table) => {
    const hasFigure = table.closest("figure");
    if (hasFigure) return;

    const figure = document.createElement("figure");
    figure.classList.add("legacy-figure");

    table.parentNode.insertBefore(figure, table);
    figure.appendChild(table);

    // Handle caption -> figcaption
    const caption = table.querySelector("caption");
    if (caption) {
      const figcaption = document.createElement("figcaption");
      figcaption.classList.add("legacy-table-caption");
      figcaption.textContent = caption.textContent;
      caption.remove();
      figure.appendChild(figcaption);
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

  /** Responsive tables on page load */
  wrapResponsiveTables();
  handleOverflowScroll();
  /** End responsive tables on page load */

});

// Recheck for responsive tables on window resize
window.addEventListener('resize', handleOverflowScroll);
