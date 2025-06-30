/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "@wordpress/block-editor":
/*!*************************************!*\
  !*** external ["wp","blockEditor"] ***!
  \*************************************/
/***/ ((module) => {

module.exports = window["wp"]["blockEditor"];

/***/ }),

/***/ "@wordpress/blocks":
/*!********************************!*\
  !*** external ["wp","blocks"] ***!
  \********************************/
/***/ ((module) => {

module.exports = window["wp"]["blocks"];

/***/ }),

/***/ "@wordpress/components":
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
/***/ ((module) => {

module.exports = window["wp"]["components"];

/***/ }),

/***/ "@wordpress/hooks":
/*!*******************************!*\
  !*** external ["wp","hooks"] ***!
  \*******************************/
/***/ ((module) => {

module.exports = window["wp"]["hooks"];

/***/ }),

/***/ "react/jsx-runtime":
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["ReactJSXRuntime"];

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!**********************!*\
  !*** ./src/index.js ***!
  \**********************/
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   withEventsVariationControls: () => (/* binding */ withEventsVariationControls),
/* harmony export */   withPubVariationControls: () => (/* binding */ withPubVariationControls)
/* harmony export */ });
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_hooks__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/hooks */ "@wordpress/hooks");
/* harmony import */ var _wordpress_hooks__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_hooks__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__);





/** Event Query Block Variation - START */

// Register event query block variation

const eventsVariation = 'upcoming-events';
(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockVariation)('core/query', {
  name: eventsVariation,
  title: 'Upcoming Events',
  description: 'Displays a list of upcoming events',
  icon: 'calendar-alt',
  attributes: {
    namespace: eventsVariation,
    query: {
      postType: 'events',
      perPage: 4,
      offset: 0,
      filterByDate: true
    }
  },
  isActive: ['namespace'],
  scope: ['inserter'],
  innerBlocks: [['core/post-template', {}, [['core/post-title']]]]
});

// Check if block is using our Events variation
const isEventsVariation = props => {
  const {
    attributes: {
      namespace
    }
  } = props;
  return namespace && namespace === eventsVariation;
};

// Add Inspector Controls for selecting event type
const EventsVariationControls = ({
  props: {
    attributes,
    setAttributes
  }
}) => {
  const {
    query
  } = attributes;
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: "Events Feed Settings",
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
      label: "CAES or Extension",
      value: query.event_type,
      options: [{
        value: 'All',
        label: 'All'
      }, {
        value: 'CAES',
        label: 'CAES'
      }, {
        value: 'Extension',
        label: 'Extension'
      }],
      onChange: value => setAttributes({
        query: {
          ...query,
          event_type: value
        }
      })
    })
  });
};
const withEventsVariationControls = BlockEdit => props => {
  return isEventsVariation(props) ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(BlockEdit, {
      ...props
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.InspectorControls, {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(EventsVariationControls, {
        props: props
      })
    })]
  }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(BlockEdit, {
    ...props
  });
};
(0,_wordpress_hooks__WEBPACK_IMPORTED_MODULE_1__.addFilter)('editor.BlockEdit', 'core/query', withEventsVariationControls);

/** Event Query Block Variation - END */

/** Publications Query Block Variation - START */

const publicationsVariation = 'pubs-feed';
(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockVariation)('core/query', {
  name: publicationsVariation,
  title: 'Publications Feed',
  description: 'Displays a feed of publications',
  icon: 'list-view',
  attributes: {
    namespace: publicationsVariation,
    query: {
      postType: 'publications',
      perPage: 4,
      offset: 0
    }
  },
  isActive: ['namespace'],
  scope: ['inserter'],
  // allowedControls: ['inherit', 'postType', 'sticky', 'taxQuery', 'author', 'search', 'format', 'parents'],
  innerBlocks: [['core/post-template', {}, [['core/post-title']]]]
});

// Check if block is using our Publications variation
const isPubsVariation = props => {
  const {
    attributes: {
      namespace
    }
  } = props;
  return namespace && namespace === publicationsVariation;
};

// Add Inspector Controls for selecting language
const PubVariationControls = ({
  props: {
    attributes,
    setAttributes
  }
}) => {
  const {
    query
  } = attributes;
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
    title: "Publication Feed Settings",
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
      label: "Language",
      value: query.language,
      options: [{
        value: '',
        label: ''
      }, {
        value: '1',
        label: 'English'
      }, {
        value: '2',
        label: 'Spanish'
      }],
      onChange: value => setAttributes({
        query: {
          ...query,
          language: value
        }
      })
    })
  });
};
const withPubVariationControls = BlockEdit => props => {
  return isPubsVariation(props) ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(BlockEdit, {
      ...props
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.InspectorControls, {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(PubVariationControls, {
        props: props
      })
    })]
  }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(BlockEdit, {
    ...props
  });
};
(0,_wordpress_hooks__WEBPACK_IMPORTED_MODULE_1__.addFilter)('editor.BlockEdit', 'core/query', withPubVariationControls);

/** Publications Query Block Variation - END */

/** Posts Query Block Controls - START */

// Check if block is querying posts
// const isPostsQuery = (props) => {
//   // Safety checks first
//   if (!props || !props.attributes || !props.attributes.query) {
//     return false;
//   }

//   const { attributes: { query } } = props;
//   console.log('Checking if posts query:', query);
//   const isPost = query && query.postType === 'post';
//   console.log('Is post query:', isPost);
//   return isPost;
// };

// // Add Inspector Controls for posts queries
// const PostsQueryControls = ({ props: { attributes, setAttributes } }) => {
//   const { query } = attributes;

//   return (
//     <PanelBody title="Additional Feed Settings">
//       {/* External Publishers Toggle */}
//       <ToggleControl
//         label="Only show posts with external publishers"
//         checked={query.hasExternalPublishers || false}
//         onChange={(value) => {
//           console.log('Setting hasExternalPublishers to:', value);
//           console.log('Full query before update:', query);

//           const newQuery = {
//             ...query,
//             hasExternalPublishers: value
//           };

//           console.log('Full query after update:', newQuery);

//           setAttributes({ query: newQuery });
//         }}
//       />
//     </PanelBody>
//   );
// };

// export const withPostsQueryControls = (BlockEdit) => (props) => {
//   return isPostsQuery(props) ? (
//     <>
//       <BlockEdit {...props} />
//       <InspectorControls>
//         <PostsQueryControls props={props} />
//       </InspectorControls>
//     </>
//   ) : (
//     <BlockEdit {...props} />
//   );
// };

// addFilter('editor.BlockEdit', 'core/query', withPostsQueryControls);

/** Posts Query Block Controls - END */
})();

/******/ })()
;
//# sourceMappingURL=index.js.map