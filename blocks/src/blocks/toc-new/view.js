window.addEventListener('load', function () {
    const postContent = document.querySelector('.caes-hub-content-post');
    if (!postContent) return;

    const tocWrapper = document.querySelector('.wp-block-caes-hub-toc-new');
    if (!tocWrapper) return;

    const showSubheadings = tocWrapper.dataset.showSubheadings === "1";
    const listStyle = tocWrapper.dataset.listStyle || "ul";
    const title = tocWrapper.dataset.title || "Table of Contents";
    const enablePopout = tocWrapper.dataset.popout === "true" || tocWrapper.dataset.popout === "1";
    const enableTopAnchor = tocWrapper.dataset.topOfContentAnchor === "true" || tocWrapper.dataset.topOfContentAnchor === "1";
    const anchorLinkText = tocWrapper.dataset.anchorLinkText || "Top of Content";
    const currentPage = parseInt(tocWrapper.dataset.currentPage) || 1;
    const totalPages = parseInt(tocWrapper.dataset.totalPages) || 1;
    const headingsData = JSON.parse(tocWrapper.dataset.headings || '[]');

    // Get base URL for building page links
    const baseUrl = window.location.pathname.replace(/\/\d+\/?$/, '');

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

    function buildTOCs(headingsData) {
        if (headingsData.length === 0) return;

        const tocList = createList();
        const stickyTocList = createList();
        const originalHeadingMap = new Map();
        const stickyHeadingMap = new Map();

        // Add "Top of Content" anchor if enabled
        if (enableTopAnchor && headingsData.length > 0) {
            const topAnchorId = 'top-of-page';

            const topListItem = document.createElement('li');
            const topLink = document.createElement('a');
            topLink.textContent = anchorLinkText;
            topLink.href = currentPage === 1 ? `#${topAnchorId}` : baseUrl;
            topListItem.appendChild(topLink);

            if (currentPage !== 1) {
                const pageIndicator = document.createElement('span');
                pageIndicator.className = 'toc-page-indicator';
                pageIndicator.textContent = 'Page 1';
                topListItem.appendChild(pageIndicator);
            }

            const stickyTopItem = topListItem.cloneNode(true);

            tocList.appendChild(topListItem);
            stickyTocList.appendChild(stickyTopItem);

            originalHeadingMap.set(topAnchorId, topListItem);
            stickyHeadingMap.set(topAnchorId, stickyTopItem);
        }

        let currentList = tocList;
        let stickyCurrentList = stickyTocList;
        let lastLevel = 2;

        headingsData.forEach(heading => {
            const { text, level, id, page } = heading;
            const isOnCurrentPage = page === currentPage;

            const listItem = document.createElement('li');
            const link = document.createElement('a');
            link.textContent = text;
            
            // Build the correct URL
            if (isOnCurrentPage) {
                link.href = `#${id}`;
            } else {
                const pageUrl = page === 1 ? baseUrl : `${baseUrl}/${page}`;
                link.href = `${pageUrl}#${id}`;
            }

            listItem.appendChild(link);

            // Add page indicator for headings on other pages
            if (!isOnCurrentPage && totalPages > 1) {
                const pageIndicator = document.createElement('span');
                pageIndicator.className = 'toc-page-indicator';
                pageIndicator.textContent = `Page ${page}`;
                listItem.appendChild(pageIndicator);
                listItem.classList.add('toc-other-page');
            } else if (isOnCurrentPage) {
                listItem.classList.add('toc-current-page');
            }

            const stickyItem = listItem.cloneNode(true);

            // Handle nesting for subheadings
            if (level === 2) {
                currentList = tocList;
                stickyCurrentList = stickyTocList;
            } else if (level > lastLevel) {
                const newList = createList(true);
                currentList.lastElementChild?.appendChild(newList);
                currentList = newList;

                const newStickyList = createList(true);
                stickyCurrentList.lastElementChild?.appendChild(newStickyList);
                stickyCurrentList = newStickyList;
            }

            currentList.appendChild(listItem);
            stickyCurrentList.appendChild(stickyItem);

            // Only track headings on current page for active highlighting
            if (isOnCurrentPage) {
                originalHeadingMap.set(id, listItem);
                stickyHeadingMap.set(id, stickyItem);
            }

            lastLevel = level;
        });

        // Wrap list in a scrollable container
        const scrollWrapper = document.createElement('div');
        scrollWrapper.classList.add('toc-scroll-wrapper');
        scrollWrapper.appendChild(tocList);
        tocWrapper.appendChild(scrollWrapper);

        // Add sticky TOC before </main> only if popout is enabled
        const mainElement = document.querySelector('main');
        if (mainElement && enablePopout) {
            const stickyTOC = document.createElement('div');
            stickyTOC.classList.add('sticky-toc');

            const tocTitle = document.createElement('h2');
            tocTitle.textContent = title;
            stickyTOC.appendChild(tocTitle);

            const stickyScrollWrapper = document.createElement('div');
            stickyScrollWrapper.classList.add('toc-scroll-wrapper');
            stickyScrollWrapper.appendChild(stickyTocList);
            stickyTOC.appendChild(stickyScrollWrapper);

            mainElement.appendChild(stickyTOC);

            return { stickyTOC, originalHeadingMap, stickyHeadingMap };
        }

        return { stickyTOC: null, originalHeadingMap, stickyHeadingMap };
    }

    const { stickyTOC, originalHeadingMap, stickyHeadingMap } = buildTOCs(headingsData) || {};

    function enableSmoothScroll() {
        document.addEventListener('click', function (event) {
            if (event.target.tagName === 'A' && event.target.hash) {
                const targetID = event.target.hash.substring(1);

                // Only prevent default for same-page anchors
                const linkUrl = new URL(event.target.href);
                const currentUrl = new URL(window.location.href);
                
                if (linkUrl.pathname === currentUrl.pathname) {
                    event.preventDefault();
                    
                    if (targetID === 'top-of-page') {
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return;
                    }

                    const targetElement = document.getElementById(targetID);
                    if (targetElement) {
                        targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
            }
        });
    }
    enableSmoothScroll();

    function handleScroll() {
        if (!stickyTOC) return;

        const tocRect = tocWrapper.getBoundingClientRect();
        const stickyTocRect = stickyTOC.getBoundingClientRect();
        const postContentRect = postContent.getBoundingClientRect();

        const tocShouldBeVisible = tocRect.top < 0;
        const tocBottomHitsPostBottom = stickyTocRect.bottom >= postContentRect.bottom;

        if (tocShouldBeVisible && !tocBottomHitsPostBottom) {
            stickyTOC.classList.add('visible');
        } else {
            stickyTOC.classList.remove('visible');
        }
    }
    window.addEventListener('scroll', handleScroll);

    function assignHeadingIDs() {
        // Get all headings currently in the DOM (current page only)
        const headingsInDom = Array.from(postContent.querySelectorAll(showSubheadings ? 'h2, h3, h4, h5, h6' : 'h2'))
            .filter(heading => heading.textContent !== title);

        // Match DOM headings with our headings data and assign IDs
        let dataIndex = 0;
        headingsInDom.forEach(domHeading => {
            // Find matching heading in data for current page
            while (dataIndex < headingsData.length) {
                const data = headingsData[dataIndex];
                if (data.page === currentPage && data.text === domHeading.textContent.trim()) {
                    domHeading.id = data.id;
                    dataIndex++;
                    break;
                }
                dataIndex++;
            }
        });

        return headingsInDom;
    }

    const headingsInDom = assignHeadingIDs();

    function observeHeadings() {
        if (!originalHeadingMap || !stickyHeadingMap || !headingsInDom) return;

        const observer = new IntersectionObserver(entries => {
            let activeSet = false;
            const isAtTop = window.scrollY < 100;

            if (isAtTop && enableTopAnchor && currentPage === 1) {
                document.querySelectorAll('.wp-block-caes-hub-toc-new li.toc-current-page').forEach(item => {
                    item.classList.remove('active');
                });
                if (enablePopout) {
                    document.querySelectorAll('.sticky-toc li.toc-current-page').forEach(item => {
                        item.classList.remove('active');
                    });
                }
                if (originalHeadingMap.has('top-of-page')) {
                    originalHeadingMap.get('top-of-page').classList.add('active');
                }
                if (enablePopout && stickyHeadingMap.has('top-of-page')) {
                    stickyHeadingMap.get('top-of-page').classList.add('active');
                }
                return;
            }

            entries.forEach(entry => {
                const id = entry.target.id;
                if (originalHeadingMap.has(id) && stickyHeadingMap.has(id)) {
                    const originalTocItem = originalHeadingMap.get(id);
                    const stickyTocItem = stickyHeadingMap.get(id);

                    if (entry.isIntersecting && !activeSet) {
                        document.querySelectorAll('.wp-block-caes-hub-toc-new li.toc-current-page').forEach(item => {
                            item.classList.remove('active');
                        });
                        if (enablePopout) {
                            document.querySelectorAll('.sticky-toc li.toc-current-page').forEach(item => {
                                item.classList.remove('active');
                            });
                        }

                        originalTocItem.classList.add('active');
                        if (enablePopout) {
                            stickyTocItem.classList.add('active');
                        }
                        activeSet = true;
                    }
                }
            });
        }, {
            rootMargin: '0px 0px -80% 0px',
            threshold: 0.1
        });

        headingsInDom.forEach(heading => observer.observe(heading));
    }

    observeHeadings();
});