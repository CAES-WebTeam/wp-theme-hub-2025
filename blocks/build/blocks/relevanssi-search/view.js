/******/ (() => { // webpackBootstrap
/*!**********************************************!*\
  !*** ./src/blocks/relevanssi-search/view.js ***!
  \**********************************************/
/**
 * This script handles the frontend interactivity for the Relevanssi Search Filters block.
 * It uses AJAX to fetch search results dynamically without page reloads for full results blocks,
 * and allows normal form submission for search-only blocks that redirect to a results page.
 */
document.addEventListener('DOMContentLoaded', () => {
  const searchFilterBlocks = document.querySelectorAll('.wp-block-caes-hub-relevanssi-search');
  searchFilterBlocks.forEach(block => {
    const form = block.querySelector('.relevanssi-search-form');
    if (!form) return;

    // Get block configuration from data attributes
    const resultsPageUrl = block.dataset.resultsPageUrl || '';
    const isSearchOnlyBlock = resultsPageUrl !== '';
    const showHeading = block.dataset.showHeading === 'true';
    const searchInput = form.querySelector('#relevanssi-search-input');
    const sortByDateSelect = form.querySelector('#relevanssi-sort-by-date');
    const postTypeSelect = form.querySelector('#relevanssi-post-type-filter');
    const languageSelect = form.querySelector('#relevanssi-language-filter');
    const resultsContainer = block.querySelector('.relevanssi-ajax-search-results-container');
    const blockTaxonomySlug = block.dataset.taxonomySlug || 'category';
    const blockAllowedPostTypes = block.dataset.allowedPostTypes ? JSON.parse(block.dataset.allowedPostTypes) : [];
    const showLanguageFilter = block.dataset.showLanguageFilter === 'true';

    // Modal elements - Topics (only exist for full results blocks)
    const openTopicsModalButton = block.querySelector('.open-topics-modal');
    const topicsModal = block.querySelector('#topics-modal');
    const topicsModalCloseButton = topicsModal ? topicsModal.querySelector('.topics-modal-close') : null;
    const applyTopicsFilterButton = topicsModal ? topicsModal.querySelector('.apply-topics-filter') : null;

    // Modal elements - Authors (only exist for full results blocks)
    const openAuthorsModalButton = block.querySelector('.open-authors-modal');
    const authorsModal = block.querySelector('#authors-modal');
    const authorsModalCloseButton = authorsModal ? authorsModal.querySelector('.authors-modal-close') : null;
    const applyAuthorsFilterButton = authorsModal ? authorsModal.querySelector('.apply-authors-filter') : null;

    // If this is a search-only block, set up simple form submission and exit
    if (isSearchOnlyBlock) {
      // For search-only blocks, allow normal form submission (no preventDefault)
      // The form action is already set to the results page URL in render.php
      form.addEventListener('submit', e => {
        // Let the form submit normally - no AJAX needed
        // The browser will navigate to the results page with the search query
      });

      // Update search title on initial page load for consistency (only if heading is enabled)
      if (showHeading) {
        const initialSearchTerm = searchInput ? searchInput.value : '';
        updateSearchTitle(initialSearchTerm);
      }
      return; // Exit early - no need to set up AJAX or advanced features
    }

    // The rest of this code only runs for full results blocks (no resultsPageUrl)

    // Ensure all checkbox labels have a consistent class for searching
    if (topicsModal) {
      topicsModal.querySelectorAll('.topics-modal-checkboxes label').forEach(label => {
        label.classList.add('topic-checkbox-item');
      });
    }
    if (authorsModal) {
      authorsModal.querySelectorAll('.authors-modal-checkboxes label').forEach(label => {
        label.classList.add('author-checkbox-item');
      });
    }

    // Function to update the search title (H1 only) - only if heading is enabled
    const updateSearchTitle = searchTerm => {
      if (!showHeading) return; // Don't create or update heading if it's disabled

      let titleElement = block.querySelector('.search-results-title');
      if (!titleElement) {
        // Create title if it doesn't exist and heading is enabled
        titleElement = document.createElement('h1');
        titleElement.className = 'search-results-title';
        // Insert before the form
        form.parentNode.insertBefore(titleElement, form);
      }

      // H1 is always just the base heading
      const customHeading = block.getAttribute('data-custom-heading');
      const baseHeading = customHeading || 'Search';
      titleElement.textContent = baseHeading;
      titleElement.style.display = 'block';
    };

    // Function to update the H2 results heading (called after AJAX response)
    const updateResultsHeading = searchTerm => {
      let existingHeading = block.querySelector('.search-results-heading');
      if (existingHeading) {
        existingHeading.remove();
      }
      if (searchTerm && searchTerm.trim()) {
        const resultsHeading = document.createElement('h2');
        resultsHeading.className = 'search-results-heading';
        resultsHeading.textContent = `Results for: "${searchTerm.trim()}"`;
        resultsHeading.style.display = 'block';

        // Find the selected filters container
        const selectedContainer = block.querySelector('.selected-topic-filters');

        // Insert AFTER the selected filters container if it exists and has content,
        // otherwise, insert after the form.
        if (selectedContainer && selectedContainer.children.length > 0) {
          selectedContainer.parentNode.insertBefore(resultsHeading, selectedContainer.nextSibling);
        } else if (form) {
          form.parentNode.insertBefore(resultsHeading, form.nextSibling);
        } else {
          // Fallback if neither exists
          block.prepend(resultsHeading);
        }
      }
    };

    // Function to update or create the results count
    const updateResultsCount = count => {
      let countElement = block.querySelector('.search-results-count');
      const resultsHeading = block.querySelector('.search-results-heading'); // Get the H2 element

      if (!countElement) {
        countElement = document.createElement('div');
        countElement.className = 'search-results-count';

        // Insert AFTER the results heading (H2) if it exists
        if (resultsHeading) {
          resultsHeading.parentNode.insertBefore(countElement, resultsHeading.nextSibling);
        } else {
          // Fallback: if no H2 (e.g., no search term), insert after selected filters or form
          const selectedContainer = block.querySelector('.selected-topic-filters');
          if (selectedContainer) {
            selectedContainer.parentNode.insertBefore(countElement, selectedContainer.nextSibling);
          } else {
            form.parentNode.insertBefore(countElement, form.nextSibling);
          }
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
        countElement.style.display = 'none';
      }
    };

    // Function to fetch and display search results
    const fetchAndDisplaySearchResults = (page = 1) => {
      if (!resultsContainer) return;

      // Use the EXACT same beautiful plant animation structure from the original!
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
                            <div class="leaf leaf-left-4"></div>
                            <div class="leaf leaf-right-4 dark"></div>
                            <div class="leaf leaf-left-5 darker"></div>
                            <div class="leaf leaf-right-5"></div>
                        </div>
        
                        <div class="plant-2">
                            <div class="leaf p2-leaf-left dark"></div>
                            <div class="leaf p2-leaf-right"></div>
                            <div class="leaf p2-leaf-left-2 darker"></div>
                            <div class="leaf p2-leaf-right-2"></div>
                            <div class="leaf p2-leaf-left-3 dark"></div>
                            <div class="leaf p2-leaf-right-3"></div>
                        </div>
        
                        <div class="plant-3">
                            <div class="leaf p3-leaf-left"></div>
                            <div class="leaf p3-leaf-right dark"></div>
                            <div class="leaf p3-leaf-left-2 darker"></div>
                            <div class="leaf p3-leaf-right-2"></div>
                            <div class="leaf p3-leaf-left-3 dark"></div>
                            <div class="leaf p3-leaf-right-3"></div>
                            <div class="leaf p3-leaf-left-4"></div>
                            <div class="leaf p3-leaf-right-4 darker"></div>
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

      // Pass filter toggle states
      formData.append('showDateSort', sortByDateSelect ? 'true' : 'false');
      formData.append('showPostTypeFilter', postTypeSelect ? 'true' : 'false');
      formData.append('showTopicFilter', topicsModal ? 'true' : 'false');
      formData.append('showAuthorFilter', authorsModal ? 'true' : 'false');
      formData.append('showLanguageFilter', showLanguageFilter ? 'true' : 'false');
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

      // Handle language selection
      if (languageSelect && languageSelect.value) {
        formData.append('language', languageSelect.value);
      }

      // Initialize checkedTopicSlugs outside the if block
      let checkedTopicSlugs = [];
      if (topicsModal) {
        checkedTopicSlugs = Array.from(topicsModal.querySelectorAll('input[type="checkbox"]:checked')).map(cb => cb.value).filter(slug => slug !== '');
        if (checkedTopicSlugs.length > 0) {
          checkedTopicSlugs.forEach(slug => {
            formData.append(`${blockTaxonomySlug}[]`, slug);
          });
        }
      }

      // Initialize checkedAuthorSlugs for authors filter - using SLUGS instead of IDs
      let checkedAuthorSlugs = [];
      if (authorsModal) {
        checkedAuthorSlugs = Array.from(authorsModal.querySelectorAll('input[type="checkbox"]:checked')).map(cb => cb.value).filter(slug => slug !== '');
        if (checkedAuthorSlugs.length > 0) {
          checkedAuthorSlugs.forEach(slug => {
            formData.append('author_slug[]', slug); // Use author_slug[] instead of author[]
          });
        }
      }

      // Update the search title (H1 only) - only if heading is enabled
      if (showHeading) {
        updateSearchTitle(searchTerm);
      }

      // Track when the fetch starts and ensure minimum display time for animation
      const fetchStartTime = Date.now();
      const minimumDisplayTime = 0;
      fetch(caesHubAjax.ajaxurl, {
        method: 'POST',
        body: formData
      }).then(response => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text();
      }).then(html => {
        const fetchEndTime = Date.now();
        const elapsedTime = fetchEndTime - fetchStartTime;
        const remainingTime = Math.max(0, minimumDisplayTime - elapsedTime);

        // Wait for the remaining time before showing results
        setTimeout(() => {
          // Scroll the user back to the top of the search block.
          block.scrollIntoView();
          resultsContainer.innerHTML = html;

          // Update the H2 results heading AFTER DOM is updated
          updateResultsHeading(searchTerm);

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
        }, remainingTime);
      }).catch(error => {
        console.error('Error fetching search results:', error);
        // Still respect minimum time even on error
        const fetchEndTime = Date.now();
        const elapsedTime = fetchEndTime - fetchStartTime;
        const remainingTime = Math.max(0, minimumDisplayTime - elapsedTime);
        setTimeout(() => {
          resultsContainer.innerHTML = '<p class="error-message">Error loading results. Please try again.</p>';
          updateResultsCount(null); // Hide count on error
        }, remainingTime);
      });
      updateURL(formData);
      renderSelectedFilters(checkedTopicSlugs, checkedAuthorSlugs);
    };

    // Function to update the browser URL
    const updateURL = formData => {
      const params = new URLSearchParams();
      // Create a clean set of params for the URL
      for (const [key, value] of formData.entries()) {
        // IMPORTANT: Removed 'paged' from the exclusion list so it's included in the URL
        if (!['action', 'security', 'allowedPostTypes', 'showDateSort', 'showPostTypeFilter', 'showTopicFilter', 'showAuthorFilter', 'showLanguageFilter'].includes(key)) {
          // Handle array parameters like taxonomy slugs and author slugs
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
      window.history.pushState({
        path: newUrl
      }, '', newUrl);
    };
    const renderSelectedFilters = (topicSlugs, authorSlugs) => {
      const selectedContainer = block.querySelector('.selected-topic-filters');
      if (!selectedContainer) return;
      selectedContainer.innerHTML = ''; // Clear old pills

      const hasTopicFilters = topicSlugs && topicSlugs.length > 0;
      const hasAuthorFilters = authorSlugs && authorSlugs.length > 0;
      if (!hasTopicFilters && !hasAuthorFilters) return;

      // Add "Filters applied:" text
      const filtersLabel = document.createElement('span');
      filtersLabel.textContent = 'Filters applied:';
      filtersLabel.className = 'filters-applied-label';
      selectedContainer.appendChild(filtersLabel);

      // Create container for the filter pills and clear button
      const filtersWrapper = document.createElement('div');
      filtersWrapper.className = 'filters-wrapper';

      // Render topic filter pills
      if (hasTopicFilters) {
        topicSlugs.forEach(slug => {
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
      }

      // Render author filter pills - now using author slugs
      if (hasAuthorFilters) {
        authorSlugs.forEach(slug => {
          const checkbox = authorsModal.querySelector(`input[value="${slug}"]`);
          const label = checkbox?.parentElement?.textContent.trim() || slug;
          const pill = document.createElement('div');
          pill.className = 'author-pill';
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
      }
      const clearAllBtn = document.createElement('button');
      clearAllBtn.textContent = 'Clear all';
      clearAllBtn.className = 'clear-all';
      clearAllBtn.setAttribute('type', 'button');
      clearAllBtn.setAttribute('aria-label', 'Clear all selected filters');
      clearAllBtn.addEventListener('click', () => {
        if (topicsModal) {
          topicsModal.querySelectorAll('input[type="checkbox"]:checked').forEach(cb => cb.checked = false);
        }
        if (authorsModal) {
          authorsModal.querySelectorAll('input[type="checkbox"]:checked').forEach(cb => cb.checked = false);
        }
        fetchAndDisplaySearchResults();
      });
      filtersWrapper.appendChild(clearAllBtn);
      selectedContainer.appendChild(filtersWrapper);
    };

    // Function to handle pagination clicks
    const attachPaginationListeners = () => {
      if (!resultsContainer) return;
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
      if (!resultsContainer) return;
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
    const hasActiveFilters = urlParams.has('s') || urlParams.has('orderby') || urlParams.has('post_type') || urlParams.has('language') || urlParams.getAll(blockTaxonomySlug).length || authorsModal && urlParams.getAll('author_slug').length;
    if (hasActiveFilters) {
      fetchAndDisplaySearchResults();
    }

    // Update search title on initial page load (only if heading is enabled)
    if (showHeading) {
      const initialSearchTerm = searchInput ? searchInput.value : '';
      updateSearchTitle(initialSearchTerm);
    }

    // Call this on initial page load if there are existing results
    if (resultsContainer && resultsContainer.children.length > 0) {
      extractInitialResultsCount();
    }

    // Form submission handler - only prevent default for full results blocks
    form.addEventListener('submit', e => {
      e.preventDefault();
      fetchAndDisplaySearchResults();
    });

    // Event listeners for filter changes
    if (sortByDateSelect) sortByDateSelect.addEventListener('change', () => fetchAndDisplaySearchResults());
    if (postTypeSelect) postTypeSelect.addEventListener('change', () => fetchAndDisplaySearchResults());
    if (languageSelect) languageSelect.addEventListener('change', () => fetchAndDisplaySearchResults());

    // --- Topics Modal Logic ---
    if (openTopicsModalButton && topicsModal) {
      let previouslyFocusedElement;

      // Helper to get all tabbable elements within the modal
      const getTopicsTabbableElements = () => {
        return Array.from(topicsModal.querySelectorAll('a[href]:not([disabled]), button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"]):not([disabled])')).filter(el => el.offsetWidth > 0 || el.offsetHeight > 0);
      };
      const trapTopicsFocus = e => {
        const tabbableElements = getTopicsTabbableElements();
        if (tabbableElements.length === 0) return;
        const firstTabbableElement = tabbableElements[0];
        const lastTabbableElement = tabbableElements[tabbableElements.length - 1];
        if (e.key === 'Tab') {
          if (e.shiftKey) {
            if (document.activeElement === firstTabbableElement) {
              lastTabbableElement.focus();
              e.preventDefault();
            }
          } else {
            if (document.activeElement === lastTabbableElement) {
              firstTabbableElement.focus();
              e.preventDefault();
            }
          }
        } else if (e.key === 'Escape') {
          closeTopicsModal();
        }
      };
      const openTopicsModal = () => {
        previouslyFocusedElement = document.activeElement;
        topicsModal.style.display = 'flex';
        openTopicsModalButton.setAttribute('aria-expanded', 'true');
        topicsModal.removeAttribute('aria-hidden');
        const tabbableElements = getTopicsTabbableElements();
        if (tabbableElements.length > 0) {
          tabbableElements[0].focus();
        }
        document.addEventListener('keydown', trapTopicsFocus);
      };
      const closeTopicsModal = () => {
        topicsModal.style.display = 'none';
        openTopicsModalButton.setAttribute('aria-expanded', 'false');
        topicsModal.setAttribute('aria-hidden', 'true');
        document.removeEventListener('keydown', trapTopicsFocus);
        if (previouslyFocusedElement) {
          previouslyFocusedElement.focus();
        }
      };
      openTopicsModalButton.addEventListener('click', openTopicsModal);
      if (topicsModalCloseButton) {
        topicsModalCloseButton.addEventListener('click', closeTopicsModal);
      }
      if (applyTopicsFilterButton) {
        applyTopicsFilterButton.addEventListener('click', () => {
          fetchAndDisplaySearchResults();
          closeTopicsModal();
        });
      } else {
        topicsModal.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
          checkbox.addEventListener('change', () => {
            fetchAndDisplaySearchResults();
          });
        });
      }
      window.addEventListener('click', event => {
        if (event.target === topicsModal) {
          closeTopicsModal();
        }
      });
      const topicSearchInput = topicsModal.querySelector('.topics-modal-search-input');
      if (topicSearchInput) {
        topicSearchInput.addEventListener('input', e => {
          const searchTerm = e.target.value.toLowerCase();
          topicsModal.querySelectorAll('.topic-checkbox-item').forEach(item => {
            const label = item.textContent.toLowerCase();
            item.style.display = label.includes(searchTerm) ? '' : 'none';
          });
        });
      }
    }

    // --- Authors Modal Logic ---
    if (openAuthorsModalButton && authorsModal) {
      let previouslyFocusedElementAuthors;
      const getAuthorsTabbableElements = () => {
        return Array.from(authorsModal.querySelectorAll('a[href]:not([disabled]), button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"]):not([disabled])')).filter(el => el.offsetWidth > 0 || el.offsetHeight > 0);
      };
      const trapAuthorsFocus = e => {
        const tabbableElements = getAuthorsTabbableElements();
        if (tabbableElements.length === 0) return;
        const firstTabbableElement = tabbableElements[0];
        const lastTabbableElement = tabbableElements[tabbableElements.length - 1];
        if (e.key === 'Tab') {
          if (e.shiftKey) {
            if (document.activeElement === firstTabbableElement) {
              lastTabbableElement.focus();
              e.preventDefault();
            }
          } else {
            if (document.activeElement === lastTabbableElement) {
              firstTabbableElement.focus();
              e.preventDefault();
            }
          }
        } else if (e.key === 'Escape') {
          closeAuthorsModal();
        }
      };
      const openAuthorsModal = () => {
        previouslyFocusedElementAuthors = document.activeElement;
        authorsModal.style.display = 'flex';
        openAuthorsModalButton.setAttribute('aria-expanded', 'true');
        authorsModal.removeAttribute('aria-hidden');
        const tabbableElements = getAuthorsTabbableElements();
        if (tabbableElements.length > 0) {
          tabbableElements[0].focus();
        }
        document.addEventListener('keydown', trapAuthorsFocus);
      };
      const closeAuthorsModal = () => {
        authorsModal.style.display = 'none';
        openAuthorsModalButton.setAttribute('aria-expanded', 'false');
        authorsModal.setAttribute('aria-hidden', 'true');
        document.removeEventListener('keydown', trapAuthorsFocus);
        if (previouslyFocusedElementAuthors) {
          previouslyFocusedElementAuthors.focus();
        }
      };
      openAuthorsModalButton.addEventListener('click', openAuthorsModal);
      if (authorsModalCloseButton) {
        authorsModalCloseButton.addEventListener('click', closeAuthorsModal);
      }
      if (applyAuthorsFilterButton) {
        applyAuthorsFilterButton.addEventListener('click', () => {
          fetchAndDisplaySearchResults();
          closeAuthorsModal();
        });
      } else {
        authorsModal.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
          checkbox.addEventListener('change', () => {
            fetchAndDisplaySearchResults();
          });
        });
      }
      window.addEventListener('click', event => {
        if (event.target === authorsModal) {
          closeAuthorsModal();
        }
      });
      const authorSearchInput = authorsModal.querySelector('.authors-modal-search-input');
      if (authorSearchInput) {
        authorSearchInput.addEventListener('input', e => {
          const searchTerm = e.target.value.toLowerCase();
          authorsModal.querySelectorAll('.author-checkbox-item').forEach(item => {
            const label = item.textContent.toLowerCase();
            item.style.display = label.includes(searchTerm) ? '' : 'none';
          });
        });
      }
    }
    attachPaginationListeners();
  });
});
/******/ })()
;
//# sourceMappingURL=view.js.map