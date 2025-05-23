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
        const originalHeadingMap = new Map();
        const stickyHeadingMap = new Map();

        // Add top of content anchor if enabled
        if (enableTopAnchor && headings.length > 0) {
            const topAnchorId = 'top-of-page';
            
            // Create top anchor list items
            const topListItem = document.createElement('li');
            const topLink = document.createElement('a');
            topLink.textContent = anchorLinkText;
            topLink.href = `#${topAnchorId}`;
            topListItem.appendChild(topLink);
            
            const stickyTopItem = topListItem.cloneNode(true);
            
            // Add to both TOCs
            tocList.appendChild(topListItem);
            stickyTocList.appendChild(stickyTopItem);
            
            // Map the top items for active state tracking
            originalHeadingMap.set(topAnchorId, topListItem);
            stickyHeadingMap.set(topAnchorId, stickyTopItem);
        }

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

            // Map both original and sticky items to their heading IDs
            originalHeadingMap.set(uniqueID, listItem);
            stickyHeadingMap.set(uniqueID, stickyItem);

            lastLevel = level;
        });

        tocWrapper.appendChild(tocList);

        // Add sticky TOC before </main> only if popout is enabled
        const mainElement = document.querySelector('main');
        if (mainElement && enablePopout) {
            const stickyTOC = document.createElement('div');
            stickyTOC.classList.add('sticky-toc');
            const tocTitle = document.createElement('h2');
            tocTitle.textContent = title;
            stickyTOC.appendChild(tocTitle);
            stickyTOC.appendChild(stickyTocList);
            mainElement.appendChild(stickyTOC);

            return { stickyTOC, originalHeadingMap, stickyHeadingMap };
        }

        return { stickyTOC: null, originalHeadingMap, stickyHeadingMap };
    }

    const { stickyTOC, originalHeadingMap, stickyHeadingMap } = buildTOCs(headings) || {};

    function enableSmoothScroll() {
        document.addEventListener('click', function (event) {
            if (event.target.tagName === 'A' && event.target.hash) {
                event.preventDefault();
                const targetID = event.target.hash.substring(1);
                
                // Handle top of page link
                if (targetID === 'top-of-page') {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return;
                }
                
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

    function observeHeadings() {
        if (!originalHeadingMap || !stickyHeadingMap) return;

        const observer = new IntersectionObserver(entries => {
            let activeSet = false;
            
            // Check if we're at the top of the page
            const isAtTop = window.scrollY < 100; // Within 100px of top
            
            if (isAtTop && enableTopAnchor) {
                // Clear all active states
                document.querySelectorAll('.wp-block-caes-hub-toc-new li').forEach(item => {
                    item.classList.remove('active');
                });
                if (enablePopout) {
                    document.querySelectorAll('.sticky-toc li').forEach(item => {
                        item.classList.remove('active');
                    });
                }
                
                // Set top link as active
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
                        // Clear active states from both TOCs
                        document.querySelectorAll('.wp-block-caes-hub-toc-new li').forEach(item => {
                            item.classList.remove('active');
                        });
                        if (enablePopout) {
                            document.querySelectorAll('.sticky-toc li').forEach(item => {
                                item.classList.remove('active');
                            });
                        }
                        
                        // Set active states for both TOCs
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

        headings.forEach(heading => observer.observe(heading));
        
        // Also listen for scroll events to handle top-of-page detection
        if (enableTopAnchor) {
            window.addEventListener('scroll', () => {
                const isAtTop = window.scrollY < 100;
                
                if (isAtTop) {
                    // Clear all active states
                    document.querySelectorAll('.wp-block-caes-hub-toc-new li').forEach(item => {
                        item.classList.remove('active');
                    });
                    if (enablePopout) {
                        document.querySelectorAll('.sticky-toc li').forEach(item => {
                            item.classList.remove('active');
                        });
                    }
                    
                    // Set top link as active
                    if (originalHeadingMap.has('top-of-page')) {
                        originalHeadingMap.get('top-of-page').classList.add('active');
                    }
                    if (enablePopout && stickyHeadingMap.has('top-of-page')) {
                        stickyHeadingMap.get('top-of-page').classList.add('active');
                    }
                }
            });
        }
    }

    observeHeadings();
});