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

  /*** START TABLE FIX */
  document.querySelectorAll('.wp-block-post-content table:not(.wp-block-table table)').forEach(table => {
    if (!table.closest('figure.wp-block-table')) {
      const wrapper = document.createElement('div');
      wrapper.classList.add('responsitable-wrapper');
      table.parentNode.insertBefore(wrapper, table);
      wrapper.appendChild(table);
    }
  });
  /*** END TABLE FIX */

  /*** RESPONSITABLE */
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
  /*** END RESPONSITABLE */

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

  /*** LEGACY CONTENT CSS CLASSES */
  const classicWrapper = document.querySelector(".classic-content-wrapper");
  if (classicWrapper) {
    document.querySelectorAll(".classic-content-wrapper figure").forEach((figure) => {
      const classList = Array.from(figure.classList);
      const isBlock = classList.some(cls => cls.startsWith("wp-"));
      if (!isBlock) {
        figure.classList.add("legacy-figure");

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

    document.querySelectorAll(".classic-content-wrapper img").forEach((img) => {
      const classList = Array.from(img.classList);
      const isBlock = classList.some(cls => cls.startsWith("wp-"));
      if (!isBlock) {
        img.classList.add("legacy-image");

        const parentFigure = img.closest("figure");
        if (!parentFigure) {
          const wrapper = document.createElement("figure");
          wrapper.classList.add("legacy-figure");
          img.parentNode.insertBefore(wrapper, img);
          wrapper.appendChild(img);

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

    document.querySelectorAll(".classic-content-wrapper div.left, .classic-content-wrapper div.right").forEach((div) => {
      const classList = Array.from(div.classList);
      const isBlock = classList.some(cls => cls.startsWith("wp-"));
      if (!isBlock) {
        div.classList.add("legacy-div");
      }

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

    document.querySelectorAll(".classic-content-wrapper table").forEach((table) => {
      const classList = Array.from(table.classList);
      const isBlock = classList.some(cls => cls.startsWith("wp-"));
      if (!isBlock) {
        table.classList.add("legacy-table");
      }
    });

    // If a figcaption is a sibling of a responsitable-wrapper or table, add legacy-table-caption class
    document.querySelectorAll(".classsic-content-wrapper figcaption").forEach((figcaption) => {
      const sibling = figcaption.nextElementSibling;
      if (sibling && sibling.classList.contains("responsitable-wrapper") || sibling.tagName === "TABLE") {
        figcaption.classList.add("legacy-table-caption");
      }
    });

  }

});

/*** END LEGACY CONTENT CSS CLASSES */