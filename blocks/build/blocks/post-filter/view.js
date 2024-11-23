var __webpack_exports__ = {};
/*!*********************************!*\
  !*** ./src/post-filter/view.js ***!
  \*********************************/
document.addEventListener('DOMContentLoaded', function () {
  const categorySelect = document.getElementById('category-select');
  const queryLoopContainer = document.querySelector('.wp-block-query');
  const postTemplateContainer = queryLoopContainer.querySelector('.wp-block-post-template');

  // Store the original post template structure and the initial number of items
  let postTemplateClone = postTemplateContainer.children[0].cloneNode(true);
  const initialPostCount = postTemplateContainer.children.length;
  if (categorySelect && queryLoopContainer) {
    categorySelect.addEventListener('change', function () {
      const selectedCategory = categorySelect.value;
      let apiUrl = '/wp-json/wp/v2/posts?per_page=10&_embed=wp:featuredmedia,wp:term';

      // Add category filter if a category is selected
      if (selectedCategory) {
        apiUrl += `&categories=${selectedCategory}`;
        console.log(apiUrl);
      }

      // Fetch posts from the REST API
      fetch(apiUrl, {
        method: 'GET'
      }).then(response => {
        if (!response.ok) {
          throw new Error(`Error: ${response.statusText}`);
        }
        return response.json();
      }).then(posts => {
        // Clear all post items
        postTemplateContainer.innerHTML = '';

        // Determine the number of posts to display based on fetched posts and the initial post count
        const postCount = Math.max(posts.length, initialPostCount);

        // Loop through the number of posts we need to display
        for (let i = 0; i < postCount; i++) {
          let postItem;

          // If a post exists for this index, use its data
          if (posts[i]) {
            postItem = postTemplateClone.cloneNode(true); // Clone for each post

            const post = posts[i];

            // Find the title, featured image, excerpt, and categories within the current post item
            const titleElement = postItem.querySelector('.wp-block-post-title a');
            const featuredImageElement = postItem.querySelector('.wp-block-post-featured-image img');
            const excerptElement = postItem.querySelector('.wp-block-post-excerpt p');
            const categoriesElement = postItem.querySelector('.wp-block-post-terms');

            // Update the title
            if (titleElement) {
              titleElement.textContent = post.title.rendered;
              titleElement.href = post.link;
            }

            // Update the featured image if it exists
            if (featuredImageElement && post._embedded && post._embedded['wp:featuredmedia'] && post._embedded['wp:featuredmedia'][0].source_url) {
              const featuredImage = post._embedded['wp:featuredmedia'][0].source_url;
              featuredImageElement.src = featuredImage;
              console.log(featuredImage);
            } else {
              // If no featured image exists, optionally hide the image element
              if (featuredImageElement) {
                featuredImageElement.style.display = 'none';
              }
            }

            // Update the excerpt
            if (excerptElement) {
              excerptElement.innerHTML = post.excerpt.rendered;
            }

            // Update categories
            if (categoriesElement && post._embedded && post._embedded['wp:term']) {
              const categories = post._embedded['wp:term'][0]; // Categories are at index 0
              categoriesElement.innerHTML = '';
              categories.forEach(category => {
                const categoryLink = document.createElement('a');
                categoryLink.href = `/category/${category.slug}`;
                categoryLink.textContent = category.name;
                categoriesElement.appendChild(categoryLink);
                categoriesElement.innerHTML += ', ';
              });
              categoriesElement.innerHTML = categoriesElement.innerHTML.slice(0, -2);
            }
          } else {
            // If no post exists for this index, append an empty template item
            postItem = postTemplateClone.cloneNode(true);
            postItem.style.display = 'none'; // Hide the extra template item
          }
          postTemplateContainer.appendChild(postItem);
        }
      }).catch(error => {
        console.error('Failed to fetch posts:', error);
      });
    });
  }
});

//# sourceMappingURL=view.js.map