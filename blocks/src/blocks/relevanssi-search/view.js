/**
 * This script handles the frontend interactivity for the Relevanssi Search Filters block.
 * It uses AJAX to fetch search results dynamically without page reloads.
 */
document.addEventListener('DOMContentLoaded', () => {
    const searchFilterBlocks = document.querySelectorAll('.wp-block-caes-hub-relevanssi-search');

    searchFilterBlocks.forEach(block => {
        const form = block.querySelector('.relevanssi-search-form');
        if (!form) return;

        const searchInput = form.querySelector('#relevanssi-search-input');
        const sortByDateSelect = form.querySelector('#relevanssi-sort-by-date');
        const postTypeSelect = form.querySelector('#relevanssi-post-type-filter');
        const resultsContainer = block.querySelector('.relevanssi-ajax-search-results-container');
        const blockTaxonomySlug = block.dataset.taxonomySlug || 'category';
        const blockAllowedPostTypes = block.dataset.allowedPostTypes ? JSON.parse(block.dataset.allowedPostTypes) : [];

        // Modal elements
        const openTopicsModalButton = block.querySelector('.open-topics-modal');
        const topicsModal = block.querySelector('#topics-modal');
        const topicsModalCloseButton = topicsModal ? topicsModal.querySelector('.topics-modal-close') : null;
        const applyTopicsFilterButton = topicsModal ? topicsModal.querySelector('.apply-topics-filter') : null;

        // Ensure all checkbox labels have a consistent class for searching
        if (topicsModal) {
            topicsModal.querySelectorAll('.topics-modal-checkboxes label').forEach(label => {
                label.classList.add('topic-checkbox-item');
            });
        }

        // Function to update or create the search title
        const updateSearchTitle = (searchTerm) => {
            let titleElement = block.querySelector('.search-results-title');
            
            if (!titleElement) {
                // Create title if it doesn't exist
                titleElement = document.createElement('h1');
                titleElement.className = 'search-results-title';
                // Insert before the form
                form.parentNode.insertBefore(titleElement, form);
            }
            
            if (searchTerm && searchTerm.trim()) {
                titleElement.textContent = `Search results for: "${searchTerm.trim()}"`;
            } else {
                titleElement.textContent = 'Search';
            }
            titleElement.style.display = 'block';
        };

        // Function to update or create the results count
        const updateResultsCount = (count) => {
            let countElement = block.querySelector('.search-results-count');
            
            if (!countElement) {
                countElement = document.createElement('div');
                countElement.className = 'search-results-count';
                
                // Insert after the selected filters container
                const selectedContainer = block.querySelector('.selected-topic-filters');
                if (selectedContainer) {
                    selectedContainer.parentNode.insertBefore(countElement, selectedContainer.nextSibling);
                } else {
                    // Fallback: insert after the form if no filters container
                    form.parentNode.insertBefore(countElement, form.nextSibling);
                }
            }
            
            if (count !== undefined && count !== null && count > 0) {
                if (count === 1) {
                    countElement.textContent = '1 result found';
                } else {
                    countElement.textContent = `${count} results found`;
                }
                countElement.style.display = 'block';
            } else {
                // Hide the count element when there are 0 results or count is null
                // Let the PHP handle the "No results found" message
                countElement.style.display = 'none';
            }
        };

        // Function to fetch and display search results
        const fetchAndDisplaySearchResults = (page = 1) => {
            if (!resultsContainer) return;
        
            // Use the EXACT same beautiful plant animation structure we made together!
            resultsContainer.innerHTML = `
                <div class="plant-loading-container">
                    <div class="plant-garden">
                        <div class="plant">
                            <div class="leaf leaf-left dark"></div>
                            <div class="leaf leaf-right"></div>
                            <div class="leaf leaf-left-2"></div>
                            <div class="leaf leaf-right-2 darker"></div>
                            <div class="leaf leaf-left-3 dark"></div>
                            <div class="leaf leaf-right-3"></div>
                        </div>
        
                        <div class="plant-2">
                            <div class="leaf p2-leaf-left dark"></div>
                            <div class="leaf p2-leaf-right"></div>
                            <div class="leaf p2-leaf-left-2 darker"></div>
                            <div class="leaf p2-leaf-right-2"></div>
                        </div>
        
                        <div class="plant-3">
                            <div class="leaf p3-leaf-left"></div>
                            <div class="leaf p3-leaf-right dark"></div>
                            <div class="leaf p3-leaf-left-2 darker"></div>
                            <div class="leaf p3-leaf-right-2"></div>
                        </div>
                    </div>
                    <p class="loading-text">Loading results...</p>
                </div>
            `;
        
            const formData = new FormData();
        
            // Security: Add nonce and action for the WordPress AJAX handler.
            formData.append('action', 'caes_hub_search_results');
            if (window.caesHubAjax && window.caesHubAjax.nonce) {
                formData.append('security', caesHubAjax.nonce);
            }
        
            // Collect data from form elements
            const searchTerm = searchInput ? searchInput.value : '';
            formData.append('s', searchTerm);
            formData.append('paged', page);
            formData.append('taxonomySlug', blockTaxonomySlug);
            formData.append('allowedPostTypes', JSON.stringify(blockAllowedPostTypes));
        
            if (sortByDateSelect) {
                const selectedOrder = sortByDateSelect.value;
                if (selectedOrder === 'post_date_desc') {
                    formData.append('orderby', 'post_date');
                    formData.append('order', 'desc');
                } else if (selectedOrder === 'post_date_asc') {
                    formData.append('orderby', 'post_date');
                    formData.append('order', 'asc');
                } else {
                    formData.append('orderby', 'relevance');
                }
            }
        
            if (postTypeSelect && postTypeSelect.value) {
                formData.append('post_type', postTypeSelect.value);
            }
        
            // Initialize checkedTopicSlugs outside the if block
            let checkedTopicSlugs = [];
        
            if (topicsModal) {
                checkedTopicSlugs = Array.from(topicsModal.querySelectorAll('input[type="checkbox"]:checked'))
                    .map(cb => cb.value)
                    .filter(slug => slug !== '');
        
                if (checkedTopicSlugs.length > 0) {
                    checkedTopicSlugs.forEach(slug => {
                        formData.append(`${blockTaxonomySlug}[]`, slug);
                    });
                }
            }
        
            // Update the search title
            updateSearchTitle(searchTerm);
        
            fetch(caesHubAjax.ajaxurl, {
                method: 'POST',
                body: formData,
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(html => {
                    resultsContainer.innerHTML = html;
                    
                    // Extract results count from the response
                    let resultsCount = null;
                    
                    // Method 1: Look for a data attribute on the results container
                    const resultsWrapper = resultsContainer.querySelector('[data-results-count]');
                    if (resultsWrapper) {
                        resultsCount = parseInt(resultsWrapper.getAttribute('data-results-count'), 10);
                    }
                    
                    // Method 2: Look for a hidden input with the count
                    const countInput = resultsContainer.querySelector('input[name="results_count"]');
                    if (countInput && resultsCount === null) {
                        resultsCount = parseInt(countInput.value, 10);
                    }
                    
                    // Method 3: Look for a specific element with the count
                    const countElement = resultsContainer.querySelector('.wp-query-results-count');
                    if (countElement && resultsCount === null) {
                        resultsCount = parseInt(countElement.textContent, 10);
                    }
                    
                    // Method 4: Try to parse from existing pagination or results info
                    if (resultsCount === null) {
                        // Look for WordPress pagination info
                        const paginationInfo = resultsContainer.querySelector('.pagination-info, .wp-pagenavi');
                        if (paginationInfo) {
                            const match = paginationInfo.textContent.match(/(\d+)\s+(?:results?|posts?|items?)/i);
                            if (match) {
                                resultsCount = parseInt(match[1], 10);
                            }
                        }
                    }
                    
                    // Method 5: Count actual result items as fallback
                    if (resultsCount === null) {
                        const resultItems = resultsContainer.querySelectorAll('.search-result-item, article, .post, .result-item');
                        if (resultItems.length > 0) {
                            // This is just the current page count, not total - but better than nothing
                            resultsCount = resultItems.length;
                        }
                    }
                    
                    updateResultsCount(resultsCount);
                    attachPaginationListeners();
                })
                .catch(error => {
                    console.error('Error fetching search results:', error);
                    resultsContainer.innerHTML = '<p class="error-message">Error loading results. Please try again.</p>';
                    updateResultsCount(null); // Hide count on error
                });
        
            updateURL(formData);
            renderSelectedTopicFilters(checkedTopicSlugs);
        };
        
        

        // Function to update the browser URL
        const updateURL = (formData) => {
            const params = new URLSearchParams();
            // Create a clean set of params for the URL
            for (const [key, value] of formData.entries()) {
                // IMPORTANT: Removed 'paged' from the exclusion list so it's included in the URL
                if (!['action', 'security', 'allowedPostTypes'].includes(key)) {
                    // Handle array parameters like taxonomy slugs
                    if (key.endsWith('[]')) {
                        const paramName = key.slice(0, -2);
                        // Append multiple values for the same parameter name
                        params.append(paramName, value);
                    } else {
                        params.set(key, value);
                    }
                }
            }
            const newUrl = `${window.location.pathname}?${params.toString()}`;
            window.history.pushState({ path: newUrl }, '', newUrl);
        };

        const renderSelectedTopicFilters = (slugs) => {
            const selectedContainer = block.querySelector('.selected-topic-filters');
            if (!selectedContainer) return;
        
            selectedContainer.innerHTML = ''; // Clear old pills
        
            if (!slugs.length) return;
        
            // Add "Filters applied:" text
            const filtersLabel = document.createElement('span');
            filtersLabel.textContent = 'Filters applied:';
            filtersLabel.className = 'filters-applied-label';
            selectedContainer.appendChild(filtersLabel);
        
            // Create container for the filter pills and clear button
            const filtersWrapper = document.createElement('div');
            filtersWrapper.className = 'filters-wrapper';
        
            slugs.forEach(slug => {
                const checkbox = topicsModal.querySelector(`input[value="${slug}"]`);
                const label = checkbox?.parentElement?.textContent.trim() || slug;
        
                const pill = document.createElement('div');
                pill.className = 'topic-pill';
        
                const textSpan = document.createElement('span');
                textSpan.textContent = label;
        
                const removeBtn = document.createElement('button');
                removeBtn.setAttribute('type', 'button');
                removeBtn.setAttribute('aria-label', `Remove filter ${label}`);
                removeBtn.innerHTML = '&times;';
                removeBtn.addEventListener('click', () => {
                    if (checkbox) checkbox.checked = false;
                    fetchAndDisplaySearchResults();
                });
        
                pill.appendChild(textSpan);
                pill.appendChild(removeBtn);
                filtersWrapper.appendChild(pill);
            });
        
            const clearAllBtn = document.createElement('button');
            clearAllBtn.textContent = 'Clear all';
            clearAllBtn.className = 'clear-all';
            clearAllBtn.setAttribute('type', 'button');
            clearAllBtn.setAttribute('aria-label', 'Clear all selected filters');
            clearAllBtn.addEventListener('click', () => {
                topicsModal.querySelectorAll('input[type="checkbox"]:checked').forEach(cb => cb.checked = false);
                fetchAndDisplaySearchResults();
            });
        
            filtersWrapper.appendChild(clearAllBtn);
            selectedContainer.appendChild(filtersWrapper);
        };

        // Function to handle pagination clicks
        const attachPaginationListeners = () => {
            resultsContainer.querySelectorAll('.page-numbers').forEach(link => {
                link.addEventListener('click', e => {
                    e.preventDefault();
                    const url = new URL(link.href);
                    let page = 1; // Default to page 1

                    // Attempt to get 'paged' from query parameters first (if any)
                    if (url.searchParams.has('paged')) {
                        page = parseInt(url.searchParams.get('paged'), 10) || 1;
                    } else {
                        // If 'paged' not in query params, try to extract from path (e.g., /page/2/)
                        const pathSegments = url.pathname.split('/');
                        const pageIndex = pathSegments.indexOf('page');
                        if (pageIndex > -1 && pageIndex + 1 < pathSegments.length) {
                            const pageNum = parseInt(pathSegments[pageIndex + 1], 10);
                            if (!isNaN(pageNum) && pageNum > 0) {
                                page = pageNum;
                            }
                        }
                    }

                    fetchAndDisplaySearchResults(page); // Pass the extracted page number
                });
            });
        };

        // Check for initial results count on page load
        const extractInitialResultsCount = () => {
            let resultsCount = null;
            
            // Try the same methods as in fetchAndDisplaySearchResults
            const resultsWrapper = resultsContainer.querySelector('[data-results-count]');
            if (resultsWrapper) {
                resultsCount = parseInt(resultsWrapper.getAttribute('data-results-count'), 10);
            }
            
            if (resultsCount === null) {
                const countInput = resultsContainer.querySelector('input[name="results_count"]');
                if (countInput) {
                    resultsCount = parseInt(countInput.value, 10);
                }
            }
            
            if (resultsCount === null) {
                const countElement = resultsContainer.querySelector('.wp-query-results-count');
                if (countElement) {
                    resultsCount = parseInt(countElement.textContent, 10);
                }
            }
            
            if (resultsCount !== null) {
                updateResultsCount(resultsCount);
            }
        };

        // Initialize on page load
        const urlParams = new URLSearchParams(window.location.search);
        if (
            urlParams.has('s') ||
            urlParams.has('orderby') ||
            urlParams.has('post_type') ||
            urlParams.getAll(blockTaxonomySlug).length
        ) {
            fetchAndDisplaySearchResults();
        }

        // Update search title on initial page load (always show an H1)
        const initialSearchTerm = searchInput ? searchInput.value : '';
        updateSearchTitle(initialSearchTerm);

        // Call this on initial page load if there are existing results
        if (resultsContainer && resultsContainer.children.length > 0) {
            extractInitialResultsCount();
        }

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            fetchAndDisplaySearchResults();
        });

        if (sortByDateSelect) sortByDateSelect.addEventListener('change', () => fetchAndDisplaySearchResults());
        if (postTypeSelect) postTypeSelect.addEventListener('change', () => fetchAndDisplaySearchResults());

        // --- Modal Logic ---
        if (openTopicsModalButton && topicsModal) {
            let previouslyFocusedElement;

            // Helper to get all tabbable elements within the modal
            const getTabbableElements = () => {
                return Array.from(topicsModal.querySelectorAll(
                    'a[href]:not([disabled]), button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"]):not([disabled])'
                )).filter(el => el.offsetWidth > 0 || el.offsetHeight > 0); // Filter out hidden elements
            };

            const trapFocus = (e) => {
                const tabbableElements = getTabbableElements();
                if (tabbableElements.length === 0) return; // No tabbable elements, nothing to trap

                const firstTabbableElement = tabbableElements[0];
                const lastTabbableElement = tabbableElements[tabbableElements.length - 1];

                if (e.key === 'Tab') {
                    if (e.shiftKey) { // Shift + Tab
                        if (document.activeElement === firstTabbableElement) {
                            lastTabbableElement.focus();
                            e.preventDefault();
                        }
                    } else { // Tab
                        if (document.activeElement === lastTabbableElement) {
                            firstTabbableElement.focus();
                            e.preventDefault();
                        }
                    }
                } else if (e.key === 'Escape') {
                    closeModal();
                }
            };

            const openModal = () => {
                previouslyFocusedElement = document.activeElement;
                topicsModal.style.display = 'flex';
                openTopicsModalButton.setAttribute('aria-expanded', 'true');
                topicsModal.removeAttribute('aria-hidden');

                // Set focus to the first tabbable element inside the modal
                const tabbableElements = getTabbableElements();
                if (tabbableElements.length > 0) {
                    tabbableElements[0].focus();
                }

                document.addEventListener('keydown', trapFocus);
            };

            const closeModal = () => {
                topicsModal.style.display = 'none';
                openTopicsModalButton.setAttribute('aria-expanded', 'false');
                topicsModal.setAttribute('aria-hidden', 'true');

                document.removeEventListener('keydown', trapFocus);
                if (previouslyFocusedElement) {
                    previouslyFocusedElement.focus();
                }
            };

            openTopicsModalButton.addEventListener('click', openModal);

            if (topicsModalCloseButton) {
                topicsModalCloseButton.addEventListener('click', closeModal);
            }

            if (applyTopicsFilterButton) {
                applyTopicsFilterButton.addEventListener('click', () => {
                    fetchAndDisplaySearchResults();
                    closeModal();
                });
            } else {
                // Fallback if no specific 'Apply Filter' button (though recommended to have one)
                topicsModal.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                    checkbox.addEventListener('change', () => {
                        fetchAndDisplaySearchResults();
                        // Consider if you want to close the modal here or only with an explicit apply button
                        // If you uncomment closeModal(), users might find it jarring if modal closes on every click.
                        // closeModal();
                    });
                });
            }

            // Close modal if clicking outside of it
            window.addEventListener('click', (event) => {
                if (event.target === topicsModal) {
                    closeModal();
                }
            });

            // Topic search inside the modal
            const topicSearchInput = topicsModal.querySelector('.topics-modal-search-input');
            if (topicSearchInput) {
                topicSearchInput.addEventListener('input', (e) => {
                    const searchTerm = e.target.value.toLowerCase();
                    topicsModal.querySelectorAll('.topic-checkbox-item').forEach(item => {
                        const label = item.textContent.toLowerCase();
                        item.style.display = label.includes(searchTerm) ? '' : 'none';
                    });
                });
            }
        }
        
        attachPaginationListeners();
    });
});