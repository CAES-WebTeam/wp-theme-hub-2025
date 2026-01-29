/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/blocks/reveal/edit.js":
/*!***********************************!*\
  !*** ./src/blocks/reveal/edit.js ***!
  \***********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__);







/**
 * Generate duotone SVG filter markup - matches WordPress core implementation.
 * Uses feColorMatrix for grayscale conversion, then feComponentTransfer for color mapping.
 *
 * @param {string[]} duotone - Array of two hex colors [shadow, highlight]
 * @param {string} filterId - Unique ID for the filter
 * @returns {JSX.Element|null} SVG element with filter definition
 */

const getDuotoneFilter = (duotone, filterId) => {
  if (!duotone || duotone.length < 2) {
    return null;
  }

  // Convert hex colors to RGB values (0-1 range)
  const parseColor = hex => {
    let color = hex.replace('#', '');
    // Handle 3-character hex
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
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("svg", {
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
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("defs", {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("filter", {
        id: filterId,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("feColorMatrix", {
          colorInterpolationFilters: "sRGB",
          type: "matrix",
          values: ".299 .587 .114 0 0 .299 .587 .114 0 0 .299 .587 .114 0 0 0 0 0 1 0"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("feComponentTransfer", {
          colorInterpolationFilters: "sRGB",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("feFuncR", {
            type: "table",
            tableValues: `${shadow.r} ${highlight.r}`
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("feFuncG", {
            type: "table",
            tableValues: `${shadow.g} ${highlight.g}`
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("feFuncB", {
            type: "table",
            tableValues: `${shadow.b} ${highlight.b}`
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("feFuncA", {
            type: "table",
            tableValues: "0 1"
          })]
        })]
      })
    })
  });
};
const TRANSITION_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('None', 'caes-reveal'),
  value: 'none'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Fade', 'caes-reveal'),
  value: 'fade'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Up', 'caes-reveal'),
  value: 'up'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Down', 'caes-reveal'),
  value: 'down'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Left', 'caes-reveal'),
  value: 'left'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Right', 'caes-reveal'),
  value: 'right'
}];

// Duotone presets - common duotone combinations
const DUOTONE_PALETTE = [{
  colors: ['#000000', '#ffffff'],
  name: 'Grayscale',
  slug: 'grayscale'
}, {
  colors: ['#000000', '#7f7f7f'],
  name: 'Dark grayscale',
  slug: 'dark-grayscale'
}, {
  colors: ['#12128c', '#ffcc00'],
  name: 'Blue and yellow',
  slug: 'blue-yellow'
}, {
  colors: ['#8c00b7', '#fcff41'],
  name: 'Purple and yellow',
  slug: 'purple-yellow'
}, {
  colors: ['#000097', '#ff4747'],
  name: 'Blue and red',
  slug: 'blue-red'
}, {
  colors: ['#004b23', '#99e2b4'],
  name: 'Green tones',
  slug: 'green-tones'
}, {
  colors: ['#99154e', '#f7b2d9'],
  name: 'Magenta tones',
  slug: 'magenta-tones'
}, {
  colors: ['#0d3b66', '#faf0ca'],
  name: 'Navy and cream',
  slug: 'navy-cream'
}];

// Color palette for custom duotone creation
const COLOR_PALETTE = [{
  color: '#000000',
  name: 'Black',
  slug: 'black'
}, {
  color: '#ffffff',
  name: 'White',
  slug: 'white'
}, {
  color: '#7f7f7f',
  name: 'Gray',
  slug: 'gray'
}, {
  color: '#ff4747',
  name: 'Red',
  slug: 'red'
}, {
  color: '#fcff41',
  name: 'Yellow',
  slug: 'yellow'
}, {
  color: '#ffcc00',
  name: 'Gold',
  slug: 'gold'
}, {
  color: '#000097',
  name: 'Blue',
  slug: 'blue'
}, {
  color: '#12128c',
  name: 'Navy',
  slug: 'navy'
}, {
  color: '#8c00b7',
  name: 'Purple',
  slug: 'purple'
}, {
  color: '#004b23',
  name: 'Dark Green',
  slug: 'dark-green'
}, {
  color: '#99e2b4',
  name: 'Light Green',
  slug: 'light-green'
}, {
  color: '#99154e',
  name: 'Magenta',
  slug: 'magenta'
}, {
  color: '#f7b2d9',
  name: 'Pink',
  slug: 'pink'
}, {
  color: '#0d3b66',
  name: 'Dark Blue',
  slug: 'dark-blue'
}, {
  color: '#faf0ca',
  name: 'Cream',
  slug: 'cream'
}];
const DEFAULT_FRAME = {
  id: '',
  desktopImage: null,
  mobileImage: null,
  desktopFocalPoint: {
    x: 0.5,
    y: 0.5
  },
  mobileFocalPoint: {
    x: 0.5,
    y: 0.5
  },
  desktopDuotone: null,
  mobileDuotone: null,
  transition: {
    type: 'fade',
    speed: 'normal'
  }
};
const generateFrameId = () => {
  return 'frame-' + Math.random().toString(36).substr(2, 9);
};
const Edit = ({
  attributes,
  setAttributes,
  clientId
}) => {
  const {
    frames,
    overlayColor,
    overlayOpacity,
    minHeight
  } = attributes;
  const [isPreviewMode, setIsPreviewMode] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(false);
  const [showOverlayColorPicker, setShowOverlayColorPicker] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(false);
  const {
    replaceInnerBlocks
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_4__.useDispatch)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.store);
  const {
    innerBlocks
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_4__.useSelect)(select => ({
    innerBlocks: select(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.store).getBlocks(clientId)
  }), [clientId]);

  // Auto-add first frame when block is inserted
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    if (frames.length === 0) {
      setAttributes({
        frames: [{
          ...DEFAULT_FRAME,
          id: generateFrameId()
        }]
      });
    }
  }, []);

  // Sync frame content blocks with frames array
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    if (frames.length === 0) {
      return;
    }

    // Check if we need to update inner blocks
    const needsUpdate = innerBlocks.length !== frames.length || innerBlocks.some((block, index) => block.name !== 'caes-hub/reveal-frames' || block.attributes.frameIndex !== index || block.attributes.frameLabel !== `Frame ${index + 1} Content`);
    if (needsUpdate) {
      const newInnerBlocks = frames.map((frame, index) => {
        // Try to preserve existing content if the block exists
        const existingBlock = innerBlocks.find(b => b.name === 'caes-hub/reveal-frames' && b.attributes.frameIndex === index);
        if (existingBlock) {
          return {
            ...existingBlock,
            attributes: {
              ...existingBlock.attributes,
              frameIndex: index,
              frameLabel: `Frame ${index + 1} Content`
            }
          };
        }

        // Create new block
        return (0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_5__.createBlock)('caes-hub/reveal-frames', {
          frameIndex: index,
          frameLabel: `Frame ${index + 1} Content`
        });
      });
      replaceInnerBlocks(clientId, newInnerBlocks, false);
    }
  }, [frames.length, clientId]);

  // Add a new frame
  const addFrame = () => {
    const newFrame = {
      ...DEFAULT_FRAME,
      id: generateFrameId()
    };
    setAttributes({
      frames: [...frames, newFrame]
    });
  };

  // Remove a frame
  const removeFrame = frameIndex => {
    if (frames.length === 1) {
      return; // Don't allow removing the last frame
    }
    const newFrames = [...frames];
    newFrames.splice(frameIndex, 1);
    setAttributes({
      frames: newFrames
    });
  };

  // Update a frame's properties
  const updateFrame = (frameIndex, updates) => {
    const newFrames = [...frames];
    newFrames[frameIndex] = {
      ...newFrames[frameIndex],
      ...updates
    };
    setAttributes({
      frames: newFrames
    });
  };

  // Move frame up
  const moveFrameUp = frameIndex => {
    if (frameIndex === 0) return;
    const newFrames = [...frames];
    [newFrames[frameIndex - 1], newFrames[frameIndex]] = [newFrames[frameIndex], newFrames[frameIndex - 1]];
    setAttributes({
      frames: newFrames
    });
  };

  // Move frame down
  const moveFrameDown = frameIndex => {
    if (frameIndex === frames.length - 1) return;
    const newFrames = [...frames];
    [newFrames[frameIndex], newFrames[frameIndex + 1]] = [newFrames[frameIndex + 1], newFrames[frameIndex]];
    setAttributes({
      frames: newFrames
    });
  };

  // Duplicate a frame
  const duplicateFrame = frameIndex => {
    const frameToDuplicate = frames[frameIndex];
    const duplicatedFrame = {
      ...JSON.parse(JSON.stringify(frameToDuplicate)),
      // Deep clone
      id: generateFrameId()
    };
    const newFrames = [...frames];
    newFrames.splice(frameIndex + 1, 0, duplicatedFrame);
    setAttributes({
      frames: newFrames
    });
  };

  // Handle image selection
  const onSelectImage = (frameIndex, imageType, media) => {
    const imageData = {
      id: media.id,
      url: media.url,
      alt: media.alt || '',
      caption: media.caption || '',
      sizes: media.sizes || {}
    };
    updateFrame(frameIndex, {
      [imageType]: imageData
    });
  };

  // Handle image removal
  const onRemoveImage = (frameIndex, imageType) => {
    updateFrame(frameIndex, {
      [imageType]: null
    });
  };

  // Calculate overlay rgba
  const getOverlayRgba = () => {
    const opacity = overlayOpacity / 100;
    const hex = overlayColor.replace('#', '');
    const r = parseInt(hex.substring(0, 2), 16);
    const g = parseInt(hex.substring(2, 4), 16);
    const b = parseInt(hex.substring(4, 6), 16);
    return `rgba(${r}, ${g}, ${b}, ${opacity})`;
  };

  // Calculate min-height based on per-frame transition speeds
  const getCalculatedMinHeight = () => {
    const count = Math.max(1, frames.length);
    const speedMultipliers = {
      slow: 1.5,
      normal: 1,
      fast: 0.5
    };
    let totalVh = 100;
    for (let i = 1; i < count; i++) {
      const speed = frames[i]?.transition?.speed || 'normal';
      const multiplier = speedMultipliers[speed] || 1;
      totalVh += 100 * multiplier;
    }
    return `${totalVh}vh`;
  };
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useBlockProps)({
    className: 'caes-reveal-editor',
    style: {
      '--reveal-min-height': getCalculatedMinHeight()
    }
  });
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
    ...blockProps,
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.BlockControls, {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToolbarGroup, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToolbarButton, {
          icon: isPreviewMode ? 'edit' : 'visibility',
          label: isPreviewMode ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Edit', 'caes-reveal') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Preview', 'caes-reveal'),
          onClick: () => setIsPreviewMode(!isPreviewMode)
        })
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.InspectorControls, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
        title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Frames', 'caes-reveal'),
        initialOpen: true,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
          style: {
            marginBottom: '12px',
            fontSize: '13px',
            color: '#757575'
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Each frame creates a full-window background that transitions as users scroll.', 'caes-reveal')
        }), frames.map((frame, index) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(FramePanel, {
          frame: frame,
          index: index,
          totalFrames: frames.length,
          onUpdate: updates => updateFrame(index, updates),
          onRemove: () => removeFrame(index),
          onMoveUp: () => moveFrameUp(index),
          onMoveDown: () => moveFrameDown(index),
          onDuplicate: () => duplicateFrame(index),
          onSelectImage: (imageType, media) => onSelectImage(index, imageType, media),
          onRemoveImage: imageType => onRemoveImage(index, imageType)
        }, frame.id || index)), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          variant: "secondary",
          onClick: addFrame,
          style: {
            width: '100%',
            marginTop: '12px'
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Add Frame', 'caes-reveal')
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
        title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Overlay', 'caes-reveal'),
        initialOpen: false,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          style: {
            marginBottom: '16px'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("label", {
            style: {
              display: 'block',
              marginBottom: '8px',
              fontWeight: 500
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Overlay Color', 'caes-reveal')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            onClick: () => setShowOverlayColorPicker(!showOverlayColorPicker),
            style: {
              width: '100%',
              height: '36px',
              background: overlayColor,
              border: '1px solid #ddd',
              cursor: 'pointer'
            }
          }), showOverlayColorPicker && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Popover, {
            onClose: () => setShowOverlayColorPicker(false),
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ColorPicker, {
              color: overlayColor,
              onChange: value => setAttributes({
                overlayColor: value
              }),
              enableAlpha: false
            })
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.RangeControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Overlay Opacity', 'caes-reveal'),
          value: overlayOpacity,
          onChange: value => setAttributes({
            overlayOpacity: value
          }),
          min: 0,
          max: 100,
          step: 5
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          style: {
            marginTop: '12px',
            padding: '12px',
            background: '#f0f0f0',
            borderRadius: '4px',
            fontSize: '13px'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Preview:', 'caes-reveal')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
            style: {
              marginTop: '8px',
              height: '40px',
              background: getOverlayRgba(),
              borderRadius: '2px',
              border: '1px solid #ddd'
            }
          })]
        })]
      })]
    }), isPreviewMode ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
      className: "reveal-preview",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
        status: "info",
        isDismissible: false,
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Preview mode: Scroll to see frame transitions', 'caes-reveal')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
        className: "reveal-background",
        children: frames.map((frame, index) => {
          if (!frame.desktopImage) {
            return null;
          }
          const filterId = `preview-${clientId}-${index}`;
          const desktopDuotone = frame.desktopDuotone || frame.duotone;
          return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
            className: "reveal-frame",
            children: [desktopDuotone && getDuotoneFilter(desktopDuotone, filterId), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("img", {
              src: frame.desktopImage.url,
              alt: frame.desktopImage.alt || '',
              style: {
                objectPosition: `${(frame.desktopFocalPoint?.x || 0.5) * 100}% ${(frame.desktopFocalPoint?.y || 0.5) * 100}%`,
                filter: desktopDuotone ? `url(#${filterId})` : undefined
              }
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
              className: "reveal-overlay",
              style: {
                background: getOverlayRgba()
              }
            })]
          }, frame.id || index);
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
        className: "reveal-content",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.InnerBlocks, {})
      })]
    }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
      className: "reveal-editor-mode",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
        status: "info",
        isDismissible: false,
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Add content to each frame using the containers below. Content will appear/disappear as users scroll through frames.', 'caes-reveal')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.InnerBlocks, {
        allowedBlocks: ['caes-hub/reveal-frames']
      })]
    })]
  });
};

// Frame Panel Component
const FramePanel = ({
  frame,
  index,
  totalFrames,
  onUpdate,
  onRemove,
  onMoveUp,
  onMoveDown,
  onDuplicate,
  onSelectImage,
  onRemoveImage
}) => {
  const [isOpen, setIsOpen] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(index === 0);
  const [focalPointModal, setFocalPointModal] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(null);
  const [duotoneModal, setDuotoneModal] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(null);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
    style: {
      border: '1px solid #ddd',
      borderRadius: '4px',
      marginBottom: '12px',
      background: '#fff'
    },
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
      style: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        padding: '12px',
        cursor: 'pointer',
        borderBottom: isOpen ? '1px solid #ddd' : 'none'
      },
      onClick: () => setIsOpen(!isOpen),
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("strong", {
        children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Frame', 'caes-reveal'), " ", index + 1]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        style: {
          display: 'flex',
          gap: '8px'
        },
        onClick: e => e.stopPropagation(),
        children: [index > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          size: "small",
          icon: "arrow-up-alt2",
          onClick: onMoveUp,
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Move up', 'caes-reveal')
        }), index < totalFrames - 1 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          size: "small",
          icon: "arrow-down-alt2",
          onClick: onMoveDown,
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Move down', 'caes-reveal')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          size: "small",
          icon: "admin-page",
          onClick: onDuplicate,
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Duplicate', 'caes-reveal')
        }), totalFrames > 1 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          size: "small",
          icon: "trash",
          onClick: onRemove,
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Remove', 'caes-reveal'),
          isDestructive: true
        })]
      })]
    }), isOpen && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
      style: {
        padding: '16px'
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        style: {
          marginBottom: '20px'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("label", {
          style: {
            display: 'block',
            marginBottom: '8px',
            fontWeight: 500
          },
          children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Desktop Image', 'caes-reveal'), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
            style: {
              fontWeight: 'normal',
              color: '#e65054'
            },
            children: " *"
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.MediaUploadCheck, {
          children: !frame.desktopImage ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.MediaUpload, {
            onSelect: media => onSelectImage('desktopImage', media),
            allowedTypes: ['image'],
            render: ({
              open
            }) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
              variant: "secondary",
              onClick: open,
              style: {
                width: '100%'
              },
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Select Image', 'caes-reveal')
            })
          }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
              style: {
                marginBottom: '12px'
              },
              children: (() => {
                const filterId = `editor-${frame.id}-desktop`;
                const duotone = frame.desktopDuotone || frame.duotone;
                return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
                  children: [duotone && getDuotoneFilter(duotone, filterId), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("img", {
                    src: frame.desktopImage.url,
                    alt: frame.desktopImage.alt,
                    style: {
                      maxWidth: '100%',
                      maxHeight: '150px',
                      borderRadius: '4px',
                      filter: duotone ? `url(#${filterId})` : undefined
                    }
                  })]
                });
              })()
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
              style: {
                display: 'flex',
                gap: '8px',
                marginBottom: '12px'
              },
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.MediaUpload, {
                onSelect: media => onSelectImage('desktopImage', media),
                allowedTypes: ['image'],
                value: frame.desktopImage?.id,
                render: ({
                  open
                }) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                  variant: "secondary",
                  onClick: open,
                  size: "small",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Replace', 'caes-reveal')
                })
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                variant: "secondary",
                isDestructive: true,
                onClick: () => onRemoveImage('desktopImage'),
                size: "small",
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Remove', 'caes-reveal')
              })]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
              label: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
                children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Caption', 'caes-reveal'), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("span", {
                  style: {
                    fontWeight: 'normal',
                    color: '#757575'
                  },
                  children: [" (", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('optional', 'caes-reveal'), ")"]
                })]
              }),
              value: frame.desktopImage?.caption || '',
              onChange: value => {
                const updatedImage = {
                  ...frame.desktopImage,
                  caption: value
                };
                onUpdate({
                  desktopImage: updatedImage
                });
              },
              placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Add a caption', 'caes-reveal')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
              label: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
                children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Alt Text', 'caes-reveal'), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("span", {
                  style: {
                    fontWeight: 'normal',
                    color: '#757575'
                  },
                  children: [" (", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('recommended', 'caes-reveal'), ")"]
                })]
              }),
              value: frame.desktopImage?.alt || '',
              onChange: value => {
                const updatedImage = {
                  ...frame.desktopImage,
                  alt: value
                };
                onUpdate({
                  desktopImage: updatedImage
                });
              },
              placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Describe media for screenreaders', 'caes-reveal')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
              style: {
                display: 'flex',
                gap: '8px',
                marginTop: '12px',
                flexWrap: 'wrap',
                alignItems: 'center'
              },
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                variant: "secondary",
                onClick: () => setFocalPointModal('desktop'),
                icon: "image-crop",
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Set Focus Point', 'caes-reveal')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                variant: "secondary",
                onClick: () => setDuotoneModal('desktop'),
                icon: "admin-appearance",
                children: frame.desktopDuotone || frame.duotone ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Edit Filter', 'caes-reveal') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Add Filter', 'caes-reveal')
              }), (frame.desktopDuotone || frame.duotone) && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.DuotoneSwatch, {
                values: frame.desktopDuotone || frame.duotone
              })]
            })]
          })
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        style: {
          marginBottom: '20px'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("label", {
          style: {
            display: 'block',
            marginBottom: '8px',
            fontWeight: 500
          },
          children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Mobile Image', 'caes-reveal'), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("span", {
            style: {
              fontWeight: 'normal',
              color: '#757575'
            },
            children: [" (", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('optional', 'caes-reveal'), ")"]
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
          style: {
            fontSize: '13px',
            color: '#757575',
            marginBottom: '12px'
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Provide a different image optimized for portrait/mobile screens.', 'caes-reveal')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.MediaUploadCheck, {
          children: !frame.mobileImage ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.MediaUpload, {
            onSelect: media => onSelectImage('mobileImage', media),
            allowedTypes: ['image'],
            render: ({
              open
            }) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
              variant: "secondary",
              onClick: open,
              style: {
                width: '100%'
              },
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Select Image', 'caes-reveal')
            })
          }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
              style: {
                marginBottom: '12px'
              },
              children: (() => {
                const filterId = `editor-${frame.id}-mobile`;
                const duotone = frame.mobileDuotone;
                return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
                  children: [duotone && getDuotoneFilter(duotone, filterId), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("img", {
                    src: frame.mobileImage.url,
                    alt: frame.mobileImage.alt,
                    style: {
                      maxWidth: '100%',
                      maxHeight: '150px',
                      borderRadius: '4px',
                      filter: duotone ? `url(#${filterId})` : undefined
                    }
                  })]
                });
              })()
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
              style: {
                display: 'flex',
                gap: '8px'
              },
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.MediaUpload, {
                onSelect: media => onSelectImage('mobileImage', media),
                allowedTypes: ['image'],
                value: frame.mobileImage?.id,
                render: ({
                  open
                }) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                  variant: "secondary",
                  onClick: open,
                  size: "small",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Replace', 'caes-reveal')
                })
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                variant: "secondary",
                isDestructive: true,
                onClick: () => onRemoveImage('mobileImage'),
                size: "small",
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Remove', 'caes-reveal')
              })]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
              label: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
                children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Caption', 'caes-reveal'), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("span", {
                  style: {
                    fontWeight: 'normal',
                    color: '#757575'
                  },
                  children: [" (", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('optional', 'caes-reveal'), ")"]
                })]
              }),
              value: frame.mobileImage?.caption || '',
              onChange: value => {
                const updatedImage = {
                  ...frame.mobileImage,
                  caption: value
                };
                onUpdate({
                  mobileImage: updatedImage
                });
              },
              placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Add a caption', 'caes-reveal'),
              disabled: !frame.mobileImage
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
              label: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
                children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Alt Text', 'caes-reveal'), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("span", {
                  style: {
                    fontWeight: 'normal',
                    color: '#757575'
                  },
                  children: [" (", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('recommended', 'caes-reveal'), ")"]
                })]
              }),
              value: frame.mobileImage?.alt || '',
              onChange: value => {
                const updatedImage = {
                  ...frame.mobileImage,
                  alt: value
                };
                onUpdate({
                  mobileImage: updatedImage
                });
              },
              placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Describe media for screenreaders', 'caes-reveal'),
              disabled: !frame.mobileImage
            }), frame.mobileImage && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
              style: {
                display: 'flex',
                gap: '8px',
                marginTop: '12px',
                flexWrap: 'wrap',
                alignItems: 'center'
              },
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                variant: "secondary",
                onClick: () => setFocalPointModal('mobile'),
                icon: "image-crop",
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Set Focus Point', 'caes-reveal')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                variant: "secondary",
                onClick: () => setDuotoneModal('mobile'),
                icon: "admin-appearance",
                children: frame.mobileDuotone ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Edit Filter', 'caes-reveal') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Add Filter', 'caes-reveal')
              }), frame.mobileDuotone && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.DuotoneSwatch, {
                values: frame.mobileDuotone
              })]
            })]
          })
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        style: {
          paddingTop: '16px',
          borderTop: '1px solid #ddd'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("label", {
          style: {
            display: 'block',
            marginBottom: '8px',
            fontWeight: 500
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Transition', 'caes-reveal')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          style: {
            display: 'flex',
            gap: '16px'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
            style: {
              flex: 1
            },
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Type', 'caes-reveal'),
              value: frame.transition.type,
              options: TRANSITION_OPTIONS,
              onChange: value => onUpdate({
                transition: {
                  ...frame.transition,
                  type: value
                }
              })
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
            style: {
              flex: 1
            },
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Speed', 'caes-reveal'),
              value: frame.transition.speed || 'normal',
              options: [{
                label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Slow', 'caes-reveal'),
                value: 'slow'
              }, {
                label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Normal', 'caes-reveal'),
                value: 'normal'
              }, {
                label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Fast', 'caes-reveal'),
                value: 'fast'
              }],
              onChange: value => onUpdate({
                transition: {
                  ...frame.transition,
                  speed: value
                }
              })
            })
          })]
        })]
      })]
    }), focalPointModal && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Modal, {
      title: focalPointModal === 'desktop' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Set Focus Point — Wide Screens', 'caes-reveal') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Set Focus Point — Tall Screens', 'caes-reveal'),
      onRequestClose: () => setFocalPointModal(null),
      style: {
        maxWidth: '600px',
        width: '100%'
      },
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        style: {
          padding: '8px 0'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
          style: {
            margin: '0 0 16px 0',
            color: '#757575',
            fontSize: '13px'
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Click on the image to set the focal point. This determines which part of the image stays visible when cropped to fit the screen.', 'caes-reveal')
        }), focalPointModal === 'desktop' && frame.desktopImage && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.FocalPointPicker, {
          url: frame.desktopImage.url,
          value: frame.desktopFocalPoint || {
            x: 0.5,
            y: 0.5
          },
          onChange: value => onUpdate({
            desktopFocalPoint: value
          })
        }), focalPointModal === 'mobile' && frame.mobileImage && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.FocalPointPicker, {
          url: frame.mobileImage.url,
          value: frame.mobileFocalPoint || {
            x: 0.5,
            y: 0.5
          },
          onChange: value => onUpdate({
            mobileFocalPoint: value
          })
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
          style: {
            marginTop: '20px',
            display: 'flex',
            justifyContent: 'flex-end'
          },
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            variant: "primary",
            onClick: () => setFocalPointModal(null),
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Done', 'caes-reveal')
          })
        })]
      })
    }), duotoneModal && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Modal, {
      title: duotoneModal === 'desktop' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Duotone Filter — Wide Screens', 'caes-reveal') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Duotone Filter — Tall Screens', 'caes-reveal'),
      onRequestClose: () => setDuotoneModal(null),
      style: {
        maxWidth: '400px',
        width: '100%'
      },
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        style: {
          padding: '8px 0'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
          style: {
            margin: '0 0 16px 0',
            color: '#757575',
            fontSize: '13px'
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Apply a duotone color filter to this image. The first color replaces shadows, the second replaces highlights.', 'caes-reveal')
        }), duotoneModal === 'desktop' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.DuotonePicker, {
          duotonePalette: DUOTONE_PALETTE,
          colorPalette: COLOR_PALETTE,
          value: frame.desktopDuotone || frame.duotone || undefined,
          onChange: value => onUpdate({
            desktopDuotone: value,
            duotone: null
          })
        }), duotoneModal === 'mobile' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.DuotonePicker, {
          duotonePalette: DUOTONE_PALETTE,
          colorPalette: COLOR_PALETTE,
          value: frame.mobileDuotone || undefined,
          onChange: value => onUpdate({
            mobileDuotone: value
          })
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          style: {
            marginTop: '20px',
            display: 'flex',
            justifyContent: 'space-between'
          },
          children: [(duotoneModal === 'desktop' && (frame.desktopDuotone || frame.duotone) || duotoneModal === 'mobile' && frame.mobileDuotone) && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            variant: "tertiary",
            isDestructive: true,
            onClick: () => {
              if (duotoneModal === 'desktop') {
                onUpdate({
                  desktopDuotone: null,
                  duotone: null
                });
              } else {
                onUpdate({
                  mobileDuotone: null
                });
              }
              setDuotoneModal(null);
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Remove Filter', 'caes-reveal')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
            style: {
              marginLeft: 'auto'
            },
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
              variant: "primary",
              onClick: () => setDuotoneModal(null),
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Done', 'caes-reveal')
            })
          })]
        })]
      })
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (Edit);

/***/ }),

/***/ "./src/blocks/reveal/index.js":
/*!************************************!*\
  !*** ./src/blocks/reveal/index.js ***!
  \************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./style.scss */ "./src/blocks/reveal/style.scss");
/* harmony import */ var _editor_scss__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./editor.scss */ "./src/blocks/reveal/editor.scss");
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./edit */ "./src/blocks/reveal/edit.js");
/* harmony import */ var _save__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./save */ "./src/blocks/reveal/save.js");
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./block.json */ "./src/blocks/reveal/block.json");
/**
 * Registers a new block provided a unique name and an object defining its behavior.
 */




/**
 * Internal dependencies
 */




// Register the main Reveal block
(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_5__.name, {
  edit: _edit__WEBPACK_IMPORTED_MODULE_3__["default"],
  save: _save__WEBPACK_IMPORTED_MODULE_4__["default"]
});

/***/ }),

/***/ "./src/blocks/reveal/save.js":
/*!***********************************!*\
  !*** ./src/blocks/reveal/save.js ***!
  \***********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);
/**
 * Save function for the Reveal block.
 * Outputs InnerBlocks content; PHP render wraps with background container.
 */


const Save = () => {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("div", {
    ..._wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.useBlockProps.save(),
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.InnerBlocks.Content, {})
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (Save);

/***/ }),

/***/ "./src/blocks/reveal/editor.scss":
/*!***************************************!*\
  !*** ./src/blocks/reveal/editor.scss ***!
  \***************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./src/blocks/reveal/style.scss":
/*!**************************************!*\
  !*** ./src/blocks/reveal/style.scss ***!
  \**************************************/
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

/***/ "@wordpress/components":
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
/***/ ((module) => {

module.exports = window["wp"]["components"];

/***/ }),

/***/ "@wordpress/data":
/*!******************************!*\
  !*** external ["wp","data"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["data"];

/***/ }),

/***/ "@wordpress/element":
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
/***/ ((module) => {

module.exports = window["wp"]["element"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["i18n"];

/***/ }),

/***/ "./src/blocks/reveal/block.json":
/*!**************************************!*\
  !*** ./src/blocks/reveal/block.json ***!
  \**************************************/
/***/ ((module) => {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"caes-hub/reveal","version":"0.2.0","title":"Reveal","category":"media","icon":"cover-image","description":"Full-window fixed background that transitions between frames as the user scrolls through content.","keywords":["reveal","scroll","parallax","immersive","background","storytelling"],"textdomain":"caes-reveal","attributes":{"frames":{"type":"array","default":[],"items":{"type":"object"}},"overlayColor":{"type":"string","default":"#000000"},"overlayOpacity":{"type":"number","default":30},"minHeight":{"type":"string","default":"100vh"},"scrollSpeed":{"type":"string","default":"normal","enum":["slow","normal","fast"]}},"supports":{"align":["full","wide"],"html":false,"color":{"text":true,"background":false},"spacing":{"padding":true}},"providesContext":{"caes-hub/reveal-frames":"frames"},"render":"file:./render.php","editorScript":"file:./index.js","editorStyle":"file:./index.css","style":"file:./style-index.css","viewScript":"file:./view.js"}');

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
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = __webpack_modules__;
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/chunk loaded */
/******/ 	(() => {
/******/ 		var deferred = [];
/******/ 		__webpack_require__.O = (result, chunkIds, fn, priority) => {
/******/ 			if(chunkIds) {
/******/ 				priority = priority || 0;
/******/ 				for(var i = deferred.length; i > 0 && deferred[i - 1][2] > priority; i--) deferred[i] = deferred[i - 1];
/******/ 				deferred[i] = [chunkIds, fn, priority];
/******/ 				return;
/******/ 			}
/******/ 			var notFulfilled = Infinity;
/******/ 			for (var i = 0; i < deferred.length; i++) {
/******/ 				var [chunkIds, fn, priority] = deferred[i];
/******/ 				var fulfilled = true;
/******/ 				for (var j = 0; j < chunkIds.length; j++) {
/******/ 					if ((priority & 1 === 0 || notFulfilled >= priority) && Object.keys(__webpack_require__.O).every((key) => (__webpack_require__.O[key](chunkIds[j])))) {
/******/ 						chunkIds.splice(j--, 1);
/******/ 					} else {
/******/ 						fulfilled = false;
/******/ 						if(priority < notFulfilled) notFulfilled = priority;
/******/ 					}
/******/ 				}
/******/ 				if(fulfilled) {
/******/ 					deferred.splice(i--, 1)
/******/ 					var r = fn();
/******/ 					if (r !== undefined) result = r;
/******/ 				}
/******/ 			}
/******/ 			return result;
/******/ 		};
/******/ 	})();
/******/ 	
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
/******/ 	/* webpack/runtime/jsonp chunk loading */
/******/ 	(() => {
/******/ 		// no baseURI
/******/ 		
/******/ 		// object to store loaded and loading chunks
/******/ 		// undefined = chunk not loaded, null = chunk preloaded/prefetched
/******/ 		// [resolve, reject, Promise] = chunk loading, 0 = chunk loaded
/******/ 		var installedChunks = {
/******/ 			"blocks/reveal/index": 0,
/******/ 			"blocks/reveal/style-index": 0
/******/ 		};
/******/ 		
/******/ 		// no chunk on demand loading
/******/ 		
/******/ 		// no prefetching
/******/ 		
/******/ 		// no preloaded
/******/ 		
/******/ 		// no HMR
/******/ 		
/******/ 		// no HMR manifest
/******/ 		
/******/ 		__webpack_require__.O.j = (chunkId) => (installedChunks[chunkId] === 0);
/******/ 		
/******/ 		// install a JSONP callback for chunk loading
/******/ 		var webpackJsonpCallback = (parentChunkLoadingFunction, data) => {
/******/ 			var [chunkIds, moreModules, runtime] = data;
/******/ 			// add "moreModules" to the modules object,
/******/ 			// then flag all "chunkIds" as loaded and fire callback
/******/ 			var moduleId, chunkId, i = 0;
/******/ 			if(chunkIds.some((id) => (installedChunks[id] !== 0))) {
/******/ 				for(moduleId in moreModules) {
/******/ 					if(__webpack_require__.o(moreModules, moduleId)) {
/******/ 						__webpack_require__.m[moduleId] = moreModules[moduleId];
/******/ 					}
/******/ 				}
/******/ 				if(runtime) var result = runtime(__webpack_require__);
/******/ 			}
/******/ 			if(parentChunkLoadingFunction) parentChunkLoadingFunction(data);
/******/ 			for(;i < chunkIds.length; i++) {
/******/ 				chunkId = chunkIds[i];
/******/ 				if(__webpack_require__.o(installedChunks, chunkId) && installedChunks[chunkId]) {
/******/ 					installedChunks[chunkId][0]();
/******/ 				}
/******/ 				installedChunks[chunkId] = 0;
/******/ 			}
/******/ 			return __webpack_require__.O(result);
/******/ 		}
/******/ 		
/******/ 		var chunkLoadingGlobal = globalThis["webpackChunktheme_blocks"] = globalThis["webpackChunktheme_blocks"] || [];
/******/ 		chunkLoadingGlobal.forEach(webpackJsonpCallback.bind(null, 0));
/******/ 		chunkLoadingGlobal.push = webpackJsonpCallback.bind(null, chunkLoadingGlobal.push.bind(chunkLoadingGlobal));
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module depends on other loaded chunks and execution need to be delayed
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["blocks/reveal/style-index"], () => (__webpack_require__("./src/blocks/reveal/index.js")))
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;
//# sourceMappingURL=index.js.map