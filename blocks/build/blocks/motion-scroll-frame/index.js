/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/blocks/motion-scroll-frame/edit.js":
/*!************************************************!*\
  !*** ./src/blocks/motion-scroll-frame/edit.js ***!
  \************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__);



/**
 * Generate duotone SVG filter markup
 */

const getDuotoneFilter = (duotone, filterId) => {
  if (!duotone || duotone.length < 2) {
    return null;
  }
  const parseColor = hex => {
    let color = hex.replace('#', '');
    if (color.length === 3) {
      color = color[0] + color[0] + color[1] + color[1] + color[2] + color[2];
    }
    return {
      r: parseInt(color.slice(0, 2), 16) / 255,
      g: parseInt(color.slice(2, 4), 16) / 255,
      b: parseInt(color.slice(4, 6), 16) / 255
    };
  };
  const shadow = parseColor(duotone[0]);
  const highlight = parseColor(duotone[1]);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("svg", {
    xmlns: "http://www.w3.org/2000/svg",
    viewBox: "0 0 0 0",
    width: "0",
    height: "0",
    focusable: "false",
    role: "none",
    style: {
      visibility: 'hidden',
      position: 'absolute',
      left: '-9999px',
      overflow: 'hidden'
    },
    "aria-hidden": "true",
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("defs", {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("filter", {
        id: filterId,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("feColorMatrix", {
          colorInterpolationFilters: "sRGB",
          type: "matrix",
          values: ".299 .587 .114 0 0 .299 .587 .114 0 0 .299 .587 .114 0 0 0 0 0 1 0"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("feComponentTransfer", {
          colorInterpolationFilters: "sRGB",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("feFuncR", {
            type: "table",
            tableValues: `${shadow.r} ${highlight.r}`
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("feFuncG", {
            type: "table",
            tableValues: `${shadow.g} ${highlight.g}`
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("feFuncB", {
            type: "table",
            tableValues: `${shadow.b} ${highlight.b}`
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("feFuncA", {
            type: "table",
            tableValues: "0 1"
          })]
        })]
      })
    })
  });
};

/**
 * Convert overlay color and opacity to rgba string
 */
const getOverlayRgba = (overlayColor, overlayOpacity) => {
  if (!overlayColor) {
    return 'transparent';
  }
  const opacity = (overlayOpacity !== null && overlayOpacity !== void 0 ? overlayOpacity : 0) / 100;
  const hex = overlayColor.replace('#', '');
  const r = parseInt(hex.substring(0, 2), 16);
  const g = parseInt(hex.substring(2, 4), 16);
  const b = parseInt(hex.substring(4, 6), 16);
  return `rgba(${r}, ${g}, ${b}, ${opacity})`;
};
const Edit = ({
  attributes,
  context,
  clientId
}) => {
  var _context$caesHubMot;
  const {
    frameIndex,
    frameLabel
  } = attributes;

  // Get frame data from parent context
  const frames = context['caes-hub/motion-scroll-frames'] || [];
  const overlayColor = context['caes-hub/motion-scroll-overlayColor'] || '#000000';
  const overlayOpacity = (_context$caesHubMot = context['caes-hub/motion-scroll-overlayOpacity']) !== null && _context$caesHubMot !== void 0 ? _context$caesHubMot : 0;

  // Get this frame's data
  const frame = frames[frameIndex] || null;
  const desktopImage = frame?.desktopImage || null;
  const desktopFocalPoint = frame?.desktopFocalPoint || {
    x: 0.5,
    y: 0.5
  };
  const desktopDuotone = frame?.desktopDuotone || frame?.duotone || null;
  const filterId = `motion-scroll-frame-editor-${clientId}`;
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useBlockProps)({
    className: 'motion-scroll-frame-editor',
    'data-frame-label': frameLabel
  });
  const innerBlocksProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useInnerBlocksProps)({
    className: 'motion-scroll-frame-content'
  }, {
    templateLock: false,
    renderAppender: _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.InnerBlocks.ButtonBlockAppender
  });
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("div", {
    ...blockProps,
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("div", {
      className: "motion-scroll-frame-preview",
      children: desktopImage ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("div", {
        className: "motion-scroll-frame-thumbnail",
        children: [desktopDuotone && getDuotoneFilter(desktopDuotone, filterId), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("img", {
          src: desktopImage.url,
          alt: "",
          style: {
            objectPosition: `${desktopFocalPoint.x * 100}% ${desktopFocalPoint.y * 100}%`,
            filter: desktopDuotone ? `url(#${filterId})` : undefined
          }
        }), overlayOpacity > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("div", {
          className: "motion-scroll-frame-thumbnail-overlay",
          style: {
            background: getOverlayRgba(overlayColor, overlayOpacity)
          }
        })]
      }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("div", {
        className: "motion-scroll-frame-no-image",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("span", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('No image', 'caes-motion-scroll')
        })
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("div", {
      className: "motion-scroll-frame-label",
      children: frameLabel || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Frame', 'caes-motion-scroll')
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("div", {
      ...innerBlocksProps
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (Edit);

/***/ }),

/***/ "./src/blocks/motion-scroll-frame/editor.scss":
/*!****************************************************!*\
  !*** ./src/blocks/motion-scroll-frame/editor.scss ***!
  \****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "react/jsx-runtime":
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["ReactJSXRuntime"];

/***/ }),

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

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["i18n"];

/***/ }),

/***/ "./src/blocks/motion-scroll-frame/block.json":
/*!***************************************************!*\
  !*** ./src/blocks/motion-scroll-frame/block.json ***!
  \***************************************************/
/***/ ((module) => {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"caes-hub/motion-scroll-frame","version":"0.1.0","title":"Motion Scroll Frame","category":"media","icon":"format-image","description":"A content frame within a Motion Scroll block.","keywords":["motion","scroll","frame","content"],"textdomain":"caes-motion-scroll","parent":["caes-hub/motion-scroll"],"attributes":{"frameIndex":{"type":"number","default":0},"frameId":{"type":"string","default":""},"frameLabel":{"type":"string","default":"Frame"}},"usesContext":["caes-hub/motion-scroll-frames","caes-hub/motion-scroll-overlayColor","caes-hub/motion-scroll-overlayOpacity","caes-hub/motion-scroll-contentPosition"],"supports":{"html":false,"reusable":false},"editorScript":"file:./index.js"}');

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
/*!*************************************************!*\
  !*** ./src/blocks/motion-scroll-frame/index.js ***!
  \*************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _editor_scss__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./editor.scss */ "./src/blocks/motion-scroll-frame/editor.scss");
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./edit */ "./src/blocks/motion-scroll-frame/edit.js");
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./block.json */ "./src/blocks/motion-scroll-frame/block.json");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);






(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_4__.name, {
  edit: _edit__WEBPACK_IMPORTED_MODULE_3__["default"],
  save: ({
    attributes
  }) => {
    // Output wrapper for PHP to parse and extract content
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
      className: "motion-scroll-frame-content",
      "data-frame-index": attributes.frameIndex,
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.InnerBlocks.Content, {})
    });
  }
});
})();

/******/ })()
;
//# sourceMappingURL=index.js.map