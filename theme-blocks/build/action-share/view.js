import * as __WEBPACK_EXTERNAL_MODULE__wordpress_interactivity_8e89b257__ from "@wordpress/interactivity";
/******/ var __webpack_modules__ = ({

/***/ "@wordpress/interactivity":
/*!*******************************************!*\
  !*** external "@wordpress/interactivity" ***!
  \*******************************************/
/***/ ((module) => {

module.exports = __WEBPACK_EXTERNAL_MODULE__wordpress_interactivity_8e89b257__;

/***/ })

/******/ });
/************************************************************************/
/******/ // The module cache
/******/ var __webpack_module_cache__ = {};
/******/ 
/******/ // The require function
/******/ function __webpack_require__(moduleId) {
/******/ 	// Check if module is in cache
/******/ 	var cachedModule = __webpack_module_cache__[moduleId];
/******/ 	if (cachedModule !== undefined) {
/******/ 		return cachedModule.exports;
/******/ 	}
/******/ 	// Create a new module (and put it into the cache)
/******/ 	var module = __webpack_module_cache__[moduleId] = {
/******/ 		// no module.id needed
/******/ 		// no module.loaded needed
/******/ 		exports: {}
/******/ 	};
/******/ 
/******/ 	// Execute the module function
/******/ 	__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 
/******/ 	// Return the exports of the module
/******/ 	return module.exports;
/******/ }
/******/ 
/************************************************************************/
/******/ /* webpack/runtime/make namespace object */
/******/ (() => {
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = (exports) => {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/ })();
/******/ 
/************************************************************************/
var __webpack_exports__ = {};
/*!**********************************!*\
  !*** ./src/action-share/view.js ***!
  \**********************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/interactivity */ "@wordpress/interactivity");

const {
  state
} = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)("action-share", {
  state: {
    get isOpen() {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      return context.isOpen;
    }
  },
  actions: {
    toggle: () => {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      context.isOpen = !context.isOpen;
    },
    shareOnFacebook: () => {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const postUrl = context.postUrl;
      const facebookShareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(postUrl)}`;
      window.open(facebookShareUrl, 'facebookShareWindow', 'width=500,height=500,top=100,left=100,toolbar=0,menubar=0,scrollbars=1,resizable=1');
    },
    shareOnX: () => {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const postUrl = context.postUrl;
      const postTitle = encodeURIComponent(context.postTitle);
      const twitterShareUrl = `https://twitter.com/intent/tweet?url=${encodeURIComponent(postUrl)}&text=${postTitle}`;
      window.open(twitterShareUrl, 'twitterShareWindow', 'width=500,height=500,top=100,left=100,toolbar=0,menubar=0,scrollbars=1,resizable=1');
    },
    shareOnPinterest: () => {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const postUrl = context.postUrl;
      const postTitle = encodeURIComponent(context.postTitle);
      const postImage = encodeURIComponent(context.postImage);
      const pinterestShareUrl = `https://www.pinterest.com/pin/create/button/?url=${encodeURIComponent(postUrl)}&media=${postImage}&description=${postTitle}`;
      window.open(pinterestShareUrl, 'pinterestShareWindow', 'width=500,height=500,top=100,left=100,toolbar=0,menubar=0,scrollbars=1,resizable=1');
    },
    shareOnLinkedIn: () => {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const postUrl = context.postUrl;
      const postTitle = encodeURIComponent(context.postTitle);
      const linkedInShareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(postUrl)}&title=${postTitle}`;
      window.open(linkedInShareUrl, 'linkedInShareWindow', 'width=500,height=500,top=100,left=100,toolbar=0,menubar=0,scrollbars=1,resizable=1');
    },
    shareOnReddit: () => {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const postUrl = context.postUrl;
      const postTitle = encodeURIComponent(context.postTitle);
      const redditShareUrl = `https://www.reddit.com/submit?url=${encodeURIComponent(postUrl)}&title=${postTitle}`;
      window.open(redditShareUrl, 'redditShareWindow', 'width=500,height=500,top=100,left=100,toolbar=0,menubar=0,scrollbars=1,resizable=1');
    },
    shareOnPocket: () => {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const postUrl = context.postUrl;
      const pocketShareUrl = `https://getpocket.com/save?url=${encodeURIComponent(postUrl)}`;
      window.open(pocketShareUrl, 'pocketShareWindow', 'width=500,height=500,top=100,left=100,toolbar=0,menubar=0,scrollbars=1,resizable=1');
    },
    shareByEmail: () => {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const postUrl = context.postUrl;
      const postTitle = encodeURIComponent(context.postTitle);
      const emailShareUrl = `mailto:?subject=${postTitle}&body=${postUrl}`;
      window.location.href = emailShareUrl;
    },
    copyUrl: () => {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const postUrl = context.postUrl; // Get the post URL from the context
      const copyMessage = document.querySelector('.caes-hub-copy-url__tooltip');

      // Use modern Clipboard API to copy the URL
      navigator.clipboard.writeText(postUrl).then(() => {
        // Display success message
        copyMessage.innerText = "Copied!";
        copyMessage.style.display = 'block';

        // Hide the message after 2 seconds
        setTimeout(() => {
          copyMessage.style.display = 'none';
        }, 2000);
      }).catch(() => {
        // Display error message if copying fails
        copyMessage.innerText = "Failed to copy!";
        copyMessage.style.display = 'block';
        setTimeout(() => {
          copyMessage.style.display = 'none';
        }, 2000);
      });
    }
  },
  callbacks: {
    openModal: () => {
      const {
        ref
      } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)();
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      if (state.isOpen) {
        ref.style.opacity = '0';
        ref.style.visibility = 'visible';
        setTimeout(() => {
          ref.showModal();
          ref.style.opacity = '1';
        }, 10);
      } else {
        ref.style.opacity = '0';
        setTimeout(() => {
          ref.close();
          ref.style.visibility = 'hidden';
        }, 300);
      }
      // Make sure the modal is closed when the user hits escape
      ref.addEventListener('close', () => {
        ref.style.visibility = 'hidden';
        ref.style.opacity = '0';
        setTimeout(() => {
          context.isOpen = false;
        }, 300);
      });
    },
    showCopyTooltip: (copyButton, copyMessage, message) => {
      copyMessage.innerText = message;
      copyMessage.style.display = 'block';

      // Position the tooltip above the button
      const rect = copyButton.getBoundingClientRect();
      copyMessage.style.left = `${rect.left + window.pageXOffset}px`;
      copyMessage.style.top = `${rect.top + window.pageYOffset - 30}px`; // Adjust the position above the button

      // Hide the tooltip after 2 seconds
      setTimeout(() => {
        copyMessage.style.display = 'none';
      }, 2000);
    }
  }
});

//# sourceMappingURL=view.js.map