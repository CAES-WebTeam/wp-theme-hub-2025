window.addEventListener('load', function () {
    const postContent = document.querySelector('.caes-hub-content-post');
    if (!postContent) return;

    const tocWrapper = document.querySelector('.wp-block-caes-hub-toc-new');
    if (!tocWrapper) return;

    const showSubheadings = tocWrapper.dataset.showSubheadings === "1";
    const listStyle = tocWrapper.dataset.listStyle || "ul";
    const title = tocWrapper.dataset.title || "Table of Contents";

    // Filter out the h2 element with the same text as the title
    const headings = Array.from(postContent.querySelectorAll(showSubheadings ? 'h2, h3, h4, h5, h6' : 'h2')).filter(heading => heading.textContent !== title);

    function slugify(text) {
        return text.toString().trim().toLowerCase()
            .replace(/\s+/g, '-')
            .replace(/[^\w\-]+/g, '')
            .replace(/\-\-+/g, '-')
            .replace(/^-+/, '')
            .replace(/-+$/, '');
    }

    function generateUniqueID(baseID, usedIDs) {
        let uniqueID = baseID;
        let count = 2;
        while (usedIDs.has(uniqueID)) {
            uniqueID = `${baseID}-${count}`;
            count++;
        }
        usedIDs.add(uniqueID);
        return uniqueID;
    }

    function createList(isSublist = false) {
        if (isSublist) {
            return document.createElement("ul");
        }
        if (listStyle === "ol") {
            return document.createElement("ol");
        }
        const ul = document.createElement("ul");
        if (listStyle === "none") {
            ul.classList.add("is-style-caes-hub-list-none");
        }
        return ul;
    }

    function buildTOCs(headings) {
        if (headings.length === 0) return;

        const usedIDs = new Set();
        const tocList = createList();
        const stickyTocList = createList();
        const headingMap = new Map();

        let currentList = tocList;
        let stickyCurrentList = stickyTocList;
        let lastLevel = 2;

        headings.forEach(heading => {
            const level = parseInt(heading.tagName.substring(1), 10);
            const baseID = slugify(heading.textContent);
            const uniqueID = generateUniqueID(baseID, usedIDs);
            heading.id = uniqueID;

            const listItem = document.createElement('li');
            const link = document.createElement('a');
            link.textContent = heading.textContent;
            link.href = `#${uniqueID}`;
            listItem.appendChild(link);

            const stickyItem = listItem.cloneNode(true);

            // Reset nesting if it's an H2
            if (level === 2) {
                currentList = tocList;
                stickyCurrentList = stickyTocList;
            } else if (level > lastLevel) {
                // Create new sublist only if level increases
                const newList = createList(true);
                currentList.lastElementChild?.appendChild(newList);
                currentList = newList;

                const newStickyList = createList(true);
                stickyCurrentList.lastElementChild?.appendChild(newStickyList);
                stickyCurrentList = newStickyList;
            }

            // Append to the lists
            currentList.appendChild(listItem);
            stickyCurrentList.appendChild(stickyItem);

            // Ensure correct mapping between heading ID and sticky item
            headingMap.set(uniqueID, stickyItem);

            lastLevel = level;
        });

        tocWrapper.appendChild(tocList);

        // Add sticky TOC before </main>
        const mainElement = document.querySelector('main');
        if (mainElement) {
            const stickyTOC = document.createElement('div');
            stickyTOC.classList.add('sticky-toc');
            const tocTitle = document.createElement('h2');
            tocTitle.textContent = title;
            stickyTOC.appendChild(tocTitle);
            stickyTOC.appendChild(stickyTocList);
            mainElement.appendChild(stickyTOC);

            return { stickyTOC, headingMap };
        }

        return { stickyTOC: null, headingMap };
    }

    const { stickyTOC, headingMap } = buildTOCs(headings) || {};

    function enableSmoothScroll() {
        document.addEventListener('click', function (event) {
            if (event.target.tagName === 'A' && event.target.hash) {
                event.preventDefault();
                const targetID = event.target.hash.substring(1);
                const targetElement = document.getElementById(targetID);
                if (targetElement) {
                    targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        });
    }
    enableSmoothScroll();

    function handleScroll() {
        if (!stickyTOC) return;

        const tocRect = tocWrapper.getBoundingClientRect();
        if (tocRect.top < 0) {
            stickyTOC.classList.add('visible');
        } else {
            stickyTOC.classList.remove('visible');
        }
    }
    window.addEventListener('scroll', handleScroll);

    function observeHeadings() {
        if (!headingMap) return;

        const observer = new IntersectionObserver(entries => {
            let activeSet = false;
            entries.forEach(entry => {
                const id = entry.target.id;
                if (headingMap.has(id)) {
                    const tocItem = headingMap.get(id);
                    if (entry.isIntersecting && !activeSet) {
                        document.querySelectorAll('.sticky-toc li').forEach(item => {
                            item.classList.remove('active');
                        });
                        tocItem.classList.add('active');
                        activeSet = true;
                    }
                }
            });
        }, {
            rootMargin: '0px 0px -80% 0px',
            threshold: 0.1
        });

        headings.forEach(heading => observer.observe(heading));
    }

    observeHeadings();
});
