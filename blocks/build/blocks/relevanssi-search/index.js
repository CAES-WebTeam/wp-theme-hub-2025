/******/ (() => { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ "./src/blocks/relevanssi-search/edit.js":
/*!**********************************************!*\
  !*** ./src/blocks/relevanssi-search/edit.js ***!
  \**********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Edit)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _editor_scss__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./editor.scss */ "./src/blocks/relevanssi-search/editor.scss");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__);
/**
 * WordPress dependencies
 */







/**
 * Internal dependencies
 */


/**
 * The edit function describes the structure of your block in the editor.
 * This can be thought of as the render method for the client-side.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/block-edit-save/#edit
 *
 * @param {Object}   props               Properties passed to the function.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to set block attributes.
 *
 * @return {WPElement} Element to render.
 */

function Edit({
  attributes,
  setAttributes
}) {
  const {
    showDateSort,
    showPostTypeFilter,
    showTopicFilter,
    showAuthorFilter,
    showLanguageFilter,
    showHeading,
    showButton,
    buttonText,
    buttonUrl,
    postTypes,
    taxonomySlug,
    headingColor,
    headingAlignment,
    customHeading,
    resultsPageUrl
  } = attributes;
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useBlockProps)();

  // State to hold available post types for the checkbox list
  const [availablePostTypes, setAvailablePostTypes] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useState)([]);

  // Check if publications post type is selected
  const isPublicationsSelected = postTypes.includes('publications');

  // Fetch all public post types using @wordpress/data
  const fetchedPostTypes = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useSelect)(select => {
    const {
      getPostTypes
    } = select('core');
    const postTypes = getPostTypes({
      per_page: -1
    }); // Fetch all post types

    if (!postTypes) {
      return [];
    }

    // Filter out built-in post types that are not typically searchable or useful for filtering
    return postTypes.filter(postType => postType.viewable && postType.slug !== 'attachment' && postType.slug !== 'wp_block' && postType.slug !== 'wp_template' && postType.slug !== 'wp_template_part' && postType.slug !== 'wp_navigation' && postType.slug !== 'wp_global_styles');
  }, []);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useEffect)(() => {
    if (fetchedPostTypes.length > 0) {
      setAvailablePostTypes(fetchedPostTypes);
    }
  }, [fetchedPostTypes]);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
    ...blockProps,
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.InspectorControls, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
        title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Results Page Settings', 'caes-hub'),
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Results Page URL', 'caes-hub'),
          value: resultsPageUrl,
          onChange: value => setAttributes({
            resultsPageUrl: value
          }),
          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Optional: Enter a URL to redirect search submissions to another page with the full search results. Leave blank to show results on the same page.', 'caes-hub'),
          placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('e.g., /search-results/', 'caes-hub')
        }), resultsPageUrl && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("p", {
          className: "description",
          style: {
            marginTop: '10px',
            padding: '10px',
            backgroundColor: '#f0f6fc',
            border: '1px solid #c3c4c7',
            borderRadius: '4px'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('How it works:', 'caes-hub')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("br", {}), (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('When specified, this block becomes a "search-only" form that redirects to the results page. The target page should also have this block configured with all desired filters enabled.', 'caes-hub')]
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
        title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Heading Settings', 'caes-hub'),
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Show Heading', 'caes-hub'),
          checked: showHeading,
          onChange: value => setAttributes({
            showHeading: value
          }),
          help: showHeading ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Heading will be displayed above the search form.', 'caes-hub') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Heading will be hidden.', 'caes-hub')
        }), showHeading && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Text Alignment', 'caes-hub'),
            value: headingAlignment,
            onChange: value => setAttributes({
              headingAlignment: value
            }),
            options: [{
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Left', 'caes-hub'),
              value: 'left'
            }, {
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Center', 'caes-hub'),
              value: 'center'
            }, {
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Right', 'caes-hub'),
              value: 'right'
            }]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Custom Heading Text', 'caes-hub'),
            value: customHeading,
            onChange: value => setAttributes({
              customHeading: value
            }),
            help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Leave blank to use default text ("Search" or "Search results for: [query]")', 'caes-hub'),
            placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('e.g., Search Expert Resources', 'caes-hub')
          })]
        })]
      }), showHeading && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.PanelColorSettings, {
        title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Heading Color', 'caes-hub'),
        colorSettings: [{
          value: headingColor,
          onChange: value => setAttributes({
            headingColor: value
          }),
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Text Color', 'caes-hub')
        }]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
        title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Button Settings', 'caes-hub'),
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Show Button', 'caes-hub'),
          checked: showButton,
          onChange: value => setAttributes({
            showButton: value
          }),
          help: showButton ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('A custom button will be displayed next to the filters.', 'caes-hub') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('No additional button will be shown.', 'caes-hub')
        }), showButton && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Button Text', 'caes-hub'),
            value: buttonText,
            onChange: value => setAttributes({
              buttonText: value
            }),
            help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Enter the text to display on the button.', 'caes-hub'),
            placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('e.g., Advanced Search', 'caes-hub')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Button URL', 'caes-hub'),
            value: buttonUrl,
            onChange: value => setAttributes({
              buttonUrl: value
            }),
            help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Enter the URL the button should link to.', 'caes-hub'),
            placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('e.g., /advanced-search/', 'caes-hub')
          })]
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
        title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Search Filter Settings', 'caes-hub'),
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Show Date Sorting', 'caes-hub'),
          checked: showDateSort,
          onChange: value => setAttributes({
            showDateSort: value
          }),
          help: showDateSort ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Date sorting dropdown will be visible.', 'caes-hub') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Date sorting dropdown will be hidden.', 'caes-hub')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Show Post Type Filter', 'caes-hub'),
          checked: showPostTypeFilter,
          onChange: value => setAttributes({
            showPostTypeFilter: value
          }),
          help: showPostTypeFilter ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Post type filter dropdown will be visible.', 'caes-hub') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Post type filter dropdown will be hidden.', 'caes-hub')
        }), showPostTypeFilter && availablePostTypes.length > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          style: {
            marginTop: '15px',
            borderTop: '1px solid #eee',
            paddingTop: '15px'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
            style: {
              fontWeight: 'bold'
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Select Post Types to Filter:', 'caes-hub')
          }), availablePostTypes.map(postType => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CheckboxControl, {
            label: postType.labels.singular_name || postType.slug,
            checked: postTypes.includes(postType.slug),
            onChange: isChecked => {
              const newPostTypes = isChecked ? [...postTypes, postType.slug] : postTypes.filter(slug => slug !== postType.slug);
              setAttributes({
                postTypes: newPostTypes
              });
            }
          }, postType.slug)), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
            className: "description",
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Selected post types will appear in the filter dropdown.', 'caes-hub')
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Show Topics Taxonomy Filter', 'caes-hub'),
          checked: showTopicFilter,
          onChange: value => setAttributes({
            showTopicFilter: value
          }),
          help: showTopicFilter ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Topics taxonomy filter (checkboxes) will be visible.', 'caes-hub') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Topics taxonomy filter (checkboxes) will be hidden.', 'caes-hub')
        }), showTopicFilter && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Custom Taxonomy Slug for Topics', 'caes-hub'),
          value: taxonomySlug,
          onChange: value => setAttributes({
            taxonomySlug: value
          }),
          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Enter the slug of your custom taxonomy (e.g., "topics").', 'caes-hub')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Show Author Filter', 'caes-hub'),
          checked: showAuthorFilter,
          onChange: value => setAttributes({
            showAuthorFilter: value
          }),
          help: showAuthorFilter ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Author filter dropdown will be visible.', 'caes-hub') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Author filter dropdown will be hidden.', 'caes-hub')
        }), isPublicationsSelected && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToggleControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Show Language Filter', 'caes-hub'),
          checked: showLanguageFilter,
          onChange: value => setAttributes({
            showLanguageFilter: value
          }),
          help: showLanguageFilter ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Language filter (checkboxes) will be visible for publications. Uses ACF custom field "language".', 'caes-hub') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Language filter (checkboxes) will be hidden.', 'caes-hub')
        })]
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
      className: "caes-hub-relevanssi-search-filters-editor",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Relevanssi Search Filters Block', 'caes-hub')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Configure sorting and filtering options in the block settings sidebar.', 'caes-hub')
      }), resultsPageUrl && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("p", {
        style: {
          backgroundColor: '#f0f6fc',
          padding: '10px',
          border: '1px solid #c3c4c7',
          borderRadius: '4px',
          marginTop: '10px'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Search-only mode:', 'caes-hub')
        }), " ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Redirects to', 'caes-hub'), " ", resultsPageUrl]
      }), !showHeading && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("p", {
        style: {
          backgroundColor: '#fff3cd',
          padding: '10px',
          border: '1px solid #ffd60a',
          borderRadius: '4px',
          marginTop: '10px'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Note:', 'caes-hub')
        }), " ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Heading is hidden', 'caes-hub')]
      }), showButton && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("p", {
        style: {
          backgroundColor: '#d1ecf1',
          padding: '10px',
          border: '1px solid #bee5eb',
          borderRadius: '4px',
          marginTop: '10px'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Button enabled:', 'caes-hub')
        }), " ", buttonText || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('(No text set)', 'caes-hub'), " \u2192 ", buttonUrl || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('(No URL set)', 'caes-hub')]
      }), showDateSort && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("p", {
        children: [" - ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Date Sorting Enabled', 'caes-hub')]
      }), showPostTypeFilter && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("p", {
        children: [" - ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Post Type Filter Enabled', 'caes-hub')]
      }), showTopicFilter && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("p", {
        children: [" - ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Topics Filter Enabled (Checkboxes, Taxonomy: ', 'caes-hub'), taxonomySlug, ")"]
      }), showAuthorFilter && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("p", {
        children: [" - ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Author Filter Enabled', 'caes-hub')]
      }), isPublicationsSelected && showLanguageFilter && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("p", {
        children: [" - ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Language Filter Enabled (Checkboxes, ACF Field: language)', 'caes-hub')]
      })]
    })]
  });
}

/***/ }),

/***/ "./src/blocks/relevanssi-search/editor.scss":
/*!**************************************************!*\
  !*** ./src/blocks/relevanssi-search/editor.scss ***!
  \**************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./src/blocks/relevanssi-search/style.scss":
/*!*************************************************!*\
  !*** ./src/blocks/relevanssi-search/style.scss ***!
  \*************************************************/
/***/ (() => {

throw new Error("Module build failed (from ./node_modules/mini-css-extract-plugin/dist/loader.js):\nHookWebpackError: Module build failed (from ./node_modules/sass-loader/dist/cjs.js):\nexpected selector.\n\u001b[34m    ╷\u001b[0m\n\u001b[34m337 │\u001b[0m             .page-numbers:not(\u001b[31m\u001b[0m'.dots') {\n\u001b[34m    │\u001b[0m \u001b[31m                              ^\u001b[0m\n\u001b[34m    ╵\u001b[0m\n  src/blocks/relevanssi-search/style.scss 337:31  root stylesheet\n    at tryRunOrWebpackError (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/HookWebpackError.js:86:9)\n    at __webpack_require_module__ (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Compilation.js:5299:12)\n    at __webpack_require__ (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Compilation.js:5256:18)\n    at /Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Compilation.js:5328:20\n    at symbolIterator (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/neo-async/async.js:3485:9)\n    at done (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/neo-async/async.js:3527:9)\n    at Hook.eval [as callAsync] (eval at create (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/tapable/lib/HookCodeFactory.js:33:10), <anonymous>:15:1)\n    at Hook.CALL_ASYNC_DELEGATE [as _callAsync] (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/tapable/lib/Hook.js:18:14)\n    at /Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Compilation.js:5234:43\n    at symbolIterator (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/neo-async/async.js:3482:9)\n    at timesSync (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/neo-async/async.js:2297:7)\n    at Object.eachLimit (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/neo-async/async.js:3463:5)\n    at /Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Compilation.js:5196:16\n    at symbolIterator (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/neo-async/async.js:3485:9)\n    at timesSync (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/neo-async/async.js:2297:7)\n    at Object.eachLimit (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/neo-async/async.js:3463:5)\n    at /Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Compilation.js:5164:15\n    at symbolIterator (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/neo-async/async.js:3485:9)\n    at done (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/neo-async/async.js:3527:9)\n    at /Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Compilation.js:5110:8\n    at /Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Compilation.js:3531:6\n    at /Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/HookWebpackError.js:67:2\n    at Hook.eval [as callAsync] (eval at create (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/tapable/lib/HookCodeFactory.js:33:10), <anonymous>:15:1)\n    at Cache.store (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Cache.js:111:20)\n    at ItemCacheFacade.store (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/CacheFacade.js:141:15)\n    at /Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Compilation.js:3530:11\n    at /Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Cache.js:95:34\n    at Array.<anonymous> (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/cache/MemoryCachePlugin.js:45:13)\n    at /Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Cache.js:95:19\n    at Hook.eval [as callAsync] (eval at create (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/tapable/lib/HookCodeFactory.js:33:10), <anonymous>:19:1)\n    at Cache.get (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Cache.js:79:18)\n    at ItemCacheFacade.get (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/CacheFacade.js:115:15)\n    at Compilation._codeGenerationModule (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Compilation.js:3498:9)\n    at codeGen (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Compilation.js:5098:11)\n    at symbolIterator (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/neo-async/async.js:3482:9)\n    at timesSync (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/neo-async/async.js:2297:7)\n    at Object.eachLimit (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/neo-async/async.js:3463:5)\n    at /Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Compilation.js:5128:14\n    at processQueue (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/util/processAsyncTree.js:61:4)\n    at processTicksAndRejections (node:internal/process/task_queues:77:11)\n    at runNextTicks (node:internal/process/task_queues:64:3)\n    at process.processImmediate (node:internal/timers:449:9)\n-- inner error --\nError: Module build failed (from ./node_modules/sass-loader/dist/cjs.js):\nexpected selector.\n\u001b[34m    ╷\u001b[0m\n\u001b[34m337 │\u001b[0m             .page-numbers:not(\u001b[31m\u001b[0m'.dots') {\n\u001b[34m    │\u001b[0m \u001b[31m                              ^\u001b[0m\n\u001b[34m    ╵\u001b[0m\n  src/blocks/relevanssi-search/style.scss 337:31  root stylesheet\n    at Object.<anonymous> (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/css-loader/dist/cjs.js??ruleSet[1].rules[4].use[1]!/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/postcss-loader/dist/cjs.js??ruleSet[1].rules[4].use[2]!/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/sass-loader/dist/cjs.js??ruleSet[1].rules[4].use[3]!/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/src/blocks/relevanssi-search/style.scss:1:7)\n    at /Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/javascript/JavascriptModulesPlugin.js:494:10\n    at Hook.eval [as call] (eval at create (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/tapable/lib/HookCodeFactory.js:19:10), <anonymous>:7:1)\n    at Hook.CALL_DELEGATE [as _call] (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/tapable/lib/Hook.js:14:14)\n    at /Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Compilation.js:5301:39\n    at tryRunOrWebpackError (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/HookWebpackError.js:81:7)\n    at __webpack_require_module__ (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Compilation.js:5299:12)\n    at __webpack_require__ (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Compilation.js:5256:18)\n    at /Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Compilation.js:5328:20\n    at symbolIterator (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/neo-async/async.js:3485:9)\n    at done (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/neo-async/async.js:3527:9)\n    at Hook.eval [as callAsync] (eval at create (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/tapable/lib/HookCodeFactory.js:33:10), <anonymous>:15:1)\n    at Hook.CALL_ASYNC_DELEGATE [as _callAsync] (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/tapable/lib/Hook.js:18:14)\n    at /Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Compilation.js:5234:43\n    at symbolIterator (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/neo-async/async.js:3482:9)\n    at timesSync (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/neo-async/async.js:2297:7)\n    at Object.eachLimit (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/neo-async/async.js:3463:5)\n    at /Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Compilation.js:5196:16\n    at symbolIterator (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/neo-async/async.js:3485:9)\n    at timesSync (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/neo-async/async.js:2297:7)\n    at Object.eachLimit (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/neo-async/async.js:3463:5)\n    at /Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Compilation.js:5164:15\n    at symbolIterator (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/neo-async/async.js:3485:9)\n    at done (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/neo-async/async.js:3527:9)\n    at /Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Compilation.js:5110:8\n    at /Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Compilation.js:3531:6\n    at /Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/HookWebpackError.js:67:2\n    at Hook.eval [as callAsync] (eval at create (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/tapable/lib/HookCodeFactory.js:33:10), <anonymous>:15:1)\n    at Cache.store (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Cache.js:111:20)\n    at ItemCacheFacade.store (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/CacheFacade.js:141:15)\n    at /Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Compilation.js:3530:11\n    at /Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Cache.js:95:34\n    at Array.<anonymous> (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/cache/MemoryCachePlugin.js:45:13)\n    at /Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Cache.js:95:19\n    at Hook.eval [as callAsync] (eval at create (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/tapable/lib/HookCodeFactory.js:33:10), <anonymous>:19:1)\n    at Cache.get (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Cache.js:79:18)\n    at ItemCacheFacade.get (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/CacheFacade.js:115:15)\n    at Compilation._codeGenerationModule (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Compilation.js:3498:9)\n    at codeGen (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Compilation.js:5098:11)\n    at symbolIterator (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/neo-async/async.js:3482:9)\n    at timesSync (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/neo-async/async.js:2297:7)\n    at Object.eachLimit (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/neo-async/async.js:3463:5)\n    at /Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/Compilation.js:5128:14\n    at processQueue (/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/webpack/lib/util/processAsyncTree.js:61:4)\n    at processTicksAndRejections (node:internal/process/task_queues:77:11)\n    at runNextTicks (node:internal/process/task_queues:64:3)\n    at process.processImmediate (node:internal/timers:449:9)\n\nGenerated code for /Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/css-loader/dist/cjs.js??ruleSet[1].rules[4].use[1]!/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/postcss-loader/dist/cjs.js??ruleSet[1].rules[4].use[2]!/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/node_modules/sass-loader/dist/cjs.js??ruleSet[1].rules[4].use[3]!/Users/ashleywilliams/Documents/_CODE/github/wp-theme-hub-2025/blocks/src/blocks/relevanssi-search/style.scss\n1 | throw new Error(\"Module build failed (from ./node_modules/sass-loader/dist/cjs.js):\\nexpected selector.\\n\\u001b[34m    ╷\\u001b[0m\\n\\u001b[34m337 │\\u001b[0m             .page-numbers:not(\\u001b[31m\\u001b[0m'.dots') {\\n\\u001b[34m    │\\u001b[0m \\u001b[31m                              ^\\u001b[0m\\n\\u001b[34m    ╵\\u001b[0m\\n  src/blocks/relevanssi-search/style.scss 337:31  root stylesheet\");");

/***/ }),

/***/ "react/jsx-runtime":
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
/***/ ((module) => {

"use strict";
module.exports = window["ReactJSXRuntime"];

/***/ }),

/***/ "@wordpress/block-editor":
/*!*************************************!*\
  !*** external ["wp","blockEditor"] ***!
  \*************************************/
/***/ ((module) => {

"use strict";
module.exports = window["wp"]["blockEditor"];

/***/ }),

/***/ "@wordpress/blocks":
/*!********************************!*\
  !*** external ["wp","blocks"] ***!
  \********************************/
/***/ ((module) => {

"use strict";
module.exports = window["wp"]["blocks"];

/***/ }),

/***/ "@wordpress/components":
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
/***/ ((module) => {

"use strict";
module.exports = window["wp"]["components"];

/***/ }),

/***/ "@wordpress/data":
/*!******************************!*\
  !*** external ["wp","data"] ***!
  \******************************/
/***/ ((module) => {

"use strict";
module.exports = window["wp"]["data"];

/***/ }),

/***/ "@wordpress/element":
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
/***/ ((module) => {

"use strict";
module.exports = window["wp"]["element"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ ((module) => {

"use strict";
module.exports = window["wp"]["i18n"];

/***/ }),

/***/ "./src/blocks/relevanssi-search/block.json":
/*!*************************************************!*\
  !*** ./src/blocks/relevanssi-search/block.json ***!
  \*************************************************/
/***/ ((module) => {

"use strict";
module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"caes-hub/relevanssi-search","version":"1.0.0","title":"CAES Hub Relevanssi Search","category":"widgets","icon":"search","description":"A custom block for Relevanssi search with advanced sorting and filtering options by date, post type, and custom taxonomy.","supports":{"html":false,"spacing":{"margin":true,"padding":true}},"attributes":{"showDateSort":{"type":"boolean","default":true},"showPostTypeFilter":{"type":"boolean","default":true},"showTopicFilter":{"type":"boolean","default":true},"showAuthorFilter":{"type":"boolean","default":true},"showLanguageFilter":{"type":"boolean","default":false},"showHeading":{"type":"boolean","default":true},"showButton":{"type":"boolean","default":false},"buttonText":{"type":"string","default":""},"buttonUrl":{"type":"string","default":""},"postTypes":{"type":"array","default":["post","page"],"items":{"type":"string"}},"taxonomySlug":{"type":"string","default":"category"},"headingColor":{"type":"string","default":""},"headingAlignment":{"type":"string","default":"left"},"customHeading":{"type":"string","default":""},"resultsPageUrl":{"type":"string","default":""}},"textdomain":"caes-hub","editorScript":"file:./index.js","editorStyle":"file:./index.css","style":"file:./style-index.css","viewScript":"file:./view.js","render":"file:./render.php"}');

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
// This entry needs to be wrapped in an IIFE because it needs to be in strict mode.
(() => {
"use strict";
/*!***********************************************!*\
  !*** ./src/blocks/relevanssi-search/index.js ***!
  \***********************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./style.scss */ "./src/blocks/relevanssi-search/style.scss");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_style_scss__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./edit */ "./src/blocks/relevanssi-search/edit.js");
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./block.json */ "./src/blocks/relevanssi-search/block.json");
/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */


/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * All files containing `style` keyword are bundled together. The code used
 * gets applied both to the front of your site and to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */


/**
 * Internal dependencies
 */



/**
 * Every block starts by registering a new block type definition.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_3__.name, {
  /**
   * @see ./edit.js
   */
  edit: _edit__WEBPACK_IMPORTED_MODULE_2__["default"]
});
})();

/******/ })()
;
//# sourceMappingURL=index.js.map