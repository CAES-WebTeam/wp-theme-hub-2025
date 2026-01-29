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
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__);





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
const SPEED_OPTIONS = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Slow', 'caes-reveal'),
  value: 'slow'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Normal', 'caes-reveal'),
  value: 'normal'
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Fast', 'caes-reveal'),
  value: 'fast'
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
    type: 'fade'
    // Speed is now handled globally
  }
};
const generateFrameId = () => {
  return 'frame-' + Math.random().toString(36).substr(2, 9);
};
const Edit = ({
  attributes,
  setAttributes
}) => {
  const {
    frames,
    overlayColor,
    overlayOpacity,
    minHeight,
    scrollSpeed
  } = attributes;
  const [isPreviewMode, setIsPreviewMode] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(false);
  const [showOverlayColorPicker, setShowOverlayColorPicker] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(false);

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

  // Calculate min-height based on speed for the editor view
  // This mimics the logic in render.php to give an accurate preview of scroll distance
  const getCalculatedMinHeight = () => {
    // If user manually set a minHeight override (not default '100vh' or 'auto'), you might want to respect that.
    // However, the previous code had a specific 'Layout' control for this.
    // If you want the "Scroll Speed" to drive the height, we should use that logic here.

    const count = Math.max(1, frames.length);
    let multiplier = 100; // Normal

    if (scrollSpeed === 'slow') multiplier = 150;
    if (scrollSpeed === 'fast') multiplier = 75;
    return `${count * multiplier}vh`;
  };

  // Get first frame's image for preview
  const firstFrame = frames.length > 0 ? frames[0] : null;
  const previewImage = firstFrame?.desktopImage?.url || null;
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useBlockProps)({
    className: 'caes-reveal-block',
    style: {
      '--reveal-min-height': getCalculatedMinHeight() // Use calculated height based on speed
    }
  });

  // Color swatch button component
  const ColorSwatchButton = ({
    color,
    onClick,
    label
  }) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
    onClick: onClick,
    style: {
      width: '36px',
      height: '36px',
      padding: '0',
      border: '1px solid #949494',
      borderRadius: '4px',
      background: color,
      minWidth: '36px'
    },
    "aria-label": label
  });

  // Shared Inspector Controls (shown in both modes)
  const sharedInspectorControls = /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.InspectorControls, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
      title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Block Settings', 'caes-reveal'),
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Scroll Speed', 'caes-reveal'),
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Determines how much scrolling is required to transition between all frames.', 'caes-reveal'),
        value: scrollSpeed || 'normal',
        options: SPEED_OPTIONS,
        onChange: value => setAttributes({
          scrollSpeed: value
        })
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
      title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Overlay Settings', 'caes-reveal'),
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        style: {
          display: 'flex',
          alignItems: 'center',
          gap: '12px',
          marginBottom: '16px'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
          style: {
            minWidth: '100px'
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Overlay Color', 'caes-reveal')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
          style: {
            position: 'relative'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(ColorSwatchButton, {
            color: overlayColor,
            onClick: () => setShowOverlayColorPicker(!showOverlayColorPicker),
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Select overlay color', 'caes-reveal')
          }), showOverlayColorPicker && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Popover, {
            position: "bottom left",
            onClose: () => setShowOverlayColorPicker(false),
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
              style: {
                padding: '16px'
              },
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ColorPicker, {
                color: overlayColor,
                onChange: color => setAttributes({
                  overlayColor: color
                }),
                enableAlpha: false
              })
            })
          })]
        })]
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
      title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Layout', 'caes-reveal'),
      initialOpen: false,
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Minimum Height', 'caes-reveal'),
        value: minHeight,
        options: [{
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Full viewport (100vh)', 'caes-reveal'),
          value: '100vh'
        }, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('75% viewport', 'caes-reveal'),
          value: '75vh'
        }, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('50% viewport', 'caes-reveal'),
          value: '50vh'
        }, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Auto (content height)', 'caes-reveal'),
          value: 'auto'
        }],
        onChange: value => setAttributes({
          minHeight: value
        }),
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('This controls the CSS min-height property directly. The scrollable distance is now automatically calculated based on the Scroll Speed setting.', 'caes-reveal')
      })
    })]
  });

  // PREVIEW MODE
  if (isPreviewMode) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.BlockControls, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToolbarGroup, {
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToolbarButton, {
            onClick: () => setIsPreviewMode(false),
            icon: "edit",
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Edit', 'caes-reveal')
          })
        })
      }), sharedInspectorControls, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        ...blockProps,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
          className: "reveal-background-preview",
          style: {
            position: 'absolute',
            inset: 0,
            zIndex: 0,
            overflow: 'hidden'
          },
          children: [previewImage ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("img", {
            src: previewImage,
            alt: "",
            style: {
              width: '100%',
              height: '100%',
              objectFit: 'cover',
              objectPosition: firstFrame?.desktopFocalPoint ? `${firstFrame.desktopFocalPoint.x * 100}% ${firstFrame.desktopFocalPoint.y * 100}%` : 'center'
            }
          }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
            style: {
              width: '100%',
              height: '100%',
              backgroundColor: '#e0e0e0',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              color: '#666'
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('No frames added yet', 'caes-reveal')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
            className: "reveal-overlay-preview",
            style: {
              position: 'absolute',
              inset: 0,
              backgroundColor: getOverlayRgba(),
              pointerEvents: 'none'
            }
          })]
        }), frames.length > 1 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
          style: {
            position: 'absolute',
            top: '12px',
            right: '12px',
            backgroundColor: 'rgba(0, 0, 0, 0.7)',
            color: '#fff',
            padding: '4px 10px',
            borderRadius: '4px',
            fontSize: '12px',
            zIndex: 10
          },
          children: [frames.length, " ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('frames', 'caes-reveal')]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
          className: "reveal-content-editor",
          style: {
            position: 'relative',
            zIndex: 1,
            minHeight: '200px',
            padding: 'var(--wp--preset--spacing--50, 2rem)'
          },
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.InnerBlocks, {
            template: [['core/paragraph', {
              placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Add content that will scroll over the background...', 'caes-reveal')
            }]],
            templateLock: false
          })
        })]
      })]
    });
  }

  // EDIT MODE
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.BlockControls, {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToolbarGroup, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToolbarButton, {
          onClick: () => setIsPreviewMode(true),
          icon: "visibility",
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Preview', 'caes-reveal')
        })
      })
    }), sharedInspectorControls, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
      ...blockProps,
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        className: "caes-reveal-editor",
        style: {
          backgroundColor: '#fff',
          padding: '20px',
          minHeight: '300px'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
          className: "reveal-header",
          style: {
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
            marginBottom: '20px',
            paddingBottom: '12px',
            borderBottom: '1px solid #ddd'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("h3", {
            style: {
              margin: 0,
              fontSize: '14px',
              fontWeight: 600,
              fontFamily: 'monospace'
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Reveal Block', 'caes-reveal')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
            style: {
              display: 'flex',
              gap: '8px'
            },
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
              onClick: () => setIsPreviewMode(true),
              variant: "secondary",
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Preview', 'caes-reveal')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
              onClick: addFrame,
              variant: "primary",
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Add Frame', 'caes-reveal')
            })]
          })]
        }), frames.length === 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
          status: "warning",
          isDismissible: false,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Add a frame to set a background image.', 'caes-reveal')
        }), frames.map((frame, frameIndex) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(FrameEditor, {
          frame: frame,
          frameIndex: frameIndex,
          totalFrames: frames.length,
          onUpdate: updates => updateFrame(frameIndex, updates),
          onRemove: () => removeFrame(frameIndex),
          onMoveUp: () => moveFrameUp(frameIndex),
          onMoveDown: () => moveFrameDown(frameIndex),
          onSelectImage: (imageType, media) => onSelectImage(frameIndex, imageType, media),
          onRemoveImage: imageType => onRemoveImage(frameIndex, imageType)
        }, frame.id))]
      })
    })]
  });
};

// Frame Editor Component
const FrameEditor = ({
  frame,
  frameIndex,
  totalFrames,
  onUpdate,
  onRemove,
  onMoveUp,
  onMoveDown,
  onSelectImage,
  onRemoveImage
}) => {
  const [isExpanded, setIsExpanded] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(false);
  const [focalPointModal, setFocalPointModal] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(null); // 'desktop' | 'mobile' | null
  const [duotoneModal, setDuotoneModal] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(null); // 'desktop' | 'mobile' | null

  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
    className: "reveal-frame-editor",
    style: {
      border: '1px solid #ddd',
      borderRadius: '4px',
      padding: '16px',
      marginBottom: '16px',
      backgroundColor: '#f9f9f9'
    },
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
      className: "frame-header",
      style: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: isExpanded ? '16px' : 0,
        paddingBottom: isExpanded ? '12px' : 0,
        borderBottom: isExpanded ? '1px solid #ddd' : 'none'
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        style: {
          display: 'flex',
          alignItems: 'center',
          gap: '12px'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
          style: {
            width: '48px',
            height: '48px',
            backgroundColor: '#ddd',
            borderRadius: '4px',
            overflow: 'hidden',
            flexShrink: 0
          },
          children: frame.desktopImage && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("img", {
            src: frame.desktopImage.sizes?.thumbnail?.url || frame.desktopImage.url,
            alt: "",
            style: {
              width: '100%',
              height: '100%',
              objectFit: 'cover'
            }
          })
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("strong", {
            children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Frame', 'caes-reveal'), " ", frameIndex + 1]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
            style: {
              fontSize: '12px',
              color: '#666'
            },
            children: frame.transition.type !== 'none' ? `${frame.transition.type}` : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('No transition', 'caes-reveal')
          })]
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        style: {
          display: 'flex',
          gap: '8px'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          onClick: () => setIsExpanded(!isExpanded),
          variant: "secondary",
          icon: isExpanded ? 'arrow-up-alt2' : 'arrow-down-alt2',
          label: isExpanded ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Collapse', 'caes-reveal') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Expand', 'caes-reveal')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          onClick: onMoveUp,
          variant: "secondary",
          disabled: frameIndex === 0,
          icon: "arrow-up-alt",
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Move Up', 'caes-reveal')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          onClick: onMoveDown,
          variant: "secondary",
          disabled: frameIndex === totalFrames - 1,
          icon: "arrow-down-alt",
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Move Down', 'caes-reveal')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          onClick: onRemove,
          variant: "secondary",
          isDestructive: true,
          disabled: totalFrames === 1,
          icon: "trash",
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Remove Frame', 'caes-reveal')
        })]
      })]
    }), isExpanded && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
      className: "frame-content",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        style: {
          display: 'grid',
          gridTemplateColumns: '1fr 1fr',
          gap: '24px',
          marginBottom: '20px'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
          style: {
            padding: '16px',
            backgroundColor: '#fff',
            border: '1px solid #e0e0e0',
            borderRadius: '4px'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
            style: {
              display: 'flex',
              alignItems: 'flex-start',
              gap: '8px',
              marginBottom: '12px',
              lineHeight: '1'
            },
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
              className: "dashicons dashicons-desktop",
              style: {
                fontSize: '20px'
              }
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("strong", {
                style: {
                  display: 'block'
                },
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Wide Screens', 'caes-reveal')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
                style: {
                  fontSize: '12px',
                  color: '#666'
                },
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Computers, Large Tablets Etc.', 'caes-reveal')
              })]
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("p", {
            style: {
              margin: '0 0 4px 0',
              fontSize: '13px',
              color: '#1e1e1e'
            },
            children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Background image (will be cropped to screen)', 'caes-reveal'), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
              style: {
                color: '#cc0000'
              },
              children: " *"
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
            style: {
              margin: '0 0 12px 0',
              fontSize: '12px',
              color: '#757575'
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Recommended: JPEG @ 2560 x 1440px', 'caes-reveal')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.MediaUploadCheck, {
            children: !frame.desktopImage ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.MediaUpload, {
              onSelect: media => onSelectImage('desktopImage', media),
              allowedTypes: ['image'],
              value: frame.desktopImage?.id,
              render: ({
                open
              }) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
                onClick: open,
                style: {
                  border: '2px dashed #c4c4c4',
                  borderRadius: '4px',
                  padding: '20px',
                  textAlign: 'center',
                  cursor: 'pointer',
                  backgroundColor: '#fafafa',
                  marginBottom: '12px',
                  minHeight: '150px',
                  display: 'flex',
                  flexDirection: 'column',
                  alignItems: 'center',
                  justifyContent: 'center'
                },
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
                  className: "dashicons dashicons-upload",
                  style: {
                    fontSize: '24px',
                    color: '#757575',
                    marginBottom: '8px'
                  }
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
                  style: {
                    color: '#757575',
                    fontSize: '13px'
                  },
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('DRAG & DROP', 'caes-reveal')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
                  style: {
                    display: 'flex',
                    gap: '8px',
                    marginTop: '12px'
                  },
                  children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                    variant: "secondary",
                    onClick: e => {
                      e.stopPropagation();
                      open();
                    },
                    children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Upload', 'caes-reveal')
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                    variant: "secondary",
                    onClick: e => {
                      e.stopPropagation();
                      open();
                    },
                    children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Media Library', 'caes-reveal')
                  })]
                })]
              })
            }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
              style: {
                marginBottom: '12px'
              },
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
                style: {
                  border: '1px solid #c4c4c4',
                  borderRadius: '4px',
                  padding: '12px',
                  backgroundColor: '#fafafa',
                  marginBottom: '8px',
                  textAlign: 'center'
                },
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("img", {
                  src: frame.desktopImage.sizes?.medium?.url || frame.desktopImage.url,
                  alt: frame.desktopImage.alt,
                  style: {
                    maxWidth: '100%',
                    maxHeight: '150px',
                    borderRadius: '4px'
                  }
                })
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
                style: {
                  display: 'flex',
                  gap: '8px'
                },
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.MediaUpload, {
                  onSelect: media => onSelectImage('desktopImage', media),
                  allowedTypes: ['image'],
                  value: frame.desktopImage?.id,
                  render: ({
                    open
                  }) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                    variant: "secondary",
                    onClick: open,
                    size: "small",
                    children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Replace', 'caes-reveal')
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                  variant: "secondary",
                  isDestructive: true,
                  onClick: () => onRemoveImage('desktopImage'),
                  size: "small",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Remove', 'caes-reveal')
                })]
              })]
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
            label: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
              children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Caption', 'caes-reveal'), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("span", {
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
            placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Add a caption', 'caes-reveal'),
            disabled: !frame.desktopImage
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
            label: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
              children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Alt Text', 'caes-reveal'), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("span", {
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
            placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Describe media for screenreaders', 'caes-reveal'),
            disabled: !frame.desktopImage
          }), frame.desktopImage && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
            style: {
              display: 'flex',
              gap: '8px',
              marginTop: '12px',
              flexWrap: 'wrap',
              alignItems: 'center'
            },
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
              variant: "secondary",
              onClick: () => setFocalPointModal('desktop'),
              icon: "image-crop",
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Set Focus Point', 'caes-reveal')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
              variant: "secondary",
              onClick: () => setDuotoneModal('desktop'),
              icon: "admin-appearance",
              children: frame.desktopDuotone ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Edit Filter', 'caes-reveal') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Add Filter', 'caes-reveal')
            }), frame.desktopDuotone && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.DuotoneSwatch, {
              values: frame.desktopDuotone
            })]
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
          style: {
            padding: '16px',
            backgroundColor: '#fff',
            border: '1px solid #e0e0e0',
            borderRadius: '4px'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
            style: {
              display: 'flex',
              alignItems: 'flex-start',
              gap: '8px',
              marginBottom: '12px',
              lineHeight: '1'
            },
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
              className: "dashicons dashicons-smartphone",
              style: {
                fontSize: '20px'
              }
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("strong", {
                style: {
                  display: 'block'
                },
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Tall Screens', 'caes-reveal')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
                style: {
                  fontSize: '12px',
                  color: '#666'
                },
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Devices In Portrait Orientation', 'caes-reveal')
              })]
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
            style: {
              margin: '0 0 4px 0',
              fontSize: '13px',
              color: '#1e1e1e'
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Background image (will be cropped to screen)', 'caes-reveal')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
            style: {
              margin: '0 0 12px 0',
              fontSize: '12px',
              color: '#757575'
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Recommended: JPEG @ 1080 x 1920px', 'caes-reveal')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.MediaUploadCheck, {
            children: !frame.mobileImage ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.MediaUpload, {
              onSelect: media => onSelectImage('mobileImage', media),
              allowedTypes: ['image'],
              value: frame.mobileImage?.id,
              render: ({
                open
              }) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
                onClick: open,
                style: {
                  border: '2px dashed #c4c4c4',
                  borderRadius: '4px',
                  padding: '20px',
                  textAlign: 'center',
                  cursor: 'pointer',
                  backgroundColor: '#fafafa',
                  marginBottom: '12px',
                  minHeight: '150px',
                  display: 'flex',
                  flexDirection: 'column',
                  alignItems: 'center',
                  justifyContent: 'center'
                },
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
                  className: "dashicons dashicons-upload",
                  style: {
                    fontSize: '24px',
                    color: '#757575',
                    marginBottom: '8px'
                  }
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
                  style: {
                    color: '#757575',
                    fontSize: '13px'
                  },
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('DRAG & DROP', 'caes-reveal')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
                  style: {
                    display: 'flex',
                    gap: '8px',
                    marginTop: '12px'
                  },
                  children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                    variant: "secondary",
                    onClick: e => {
                      e.stopPropagation();
                      open();
                    },
                    children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Upload', 'caes-reveal')
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                    variant: "secondary",
                    onClick: e => {
                      e.stopPropagation();
                      open();
                    },
                    children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Media Library', 'caes-reveal')
                  })]
                })]
              })
            }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
              style: {
                marginBottom: '12px'
              },
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
                style: {
                  border: '1px solid #c4c4c4',
                  borderRadius: '4px',
                  padding: '12px',
                  backgroundColor: '#fafafa',
                  marginBottom: '8px',
                  textAlign: 'center'
                },
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("img", {
                  src: frame.mobileImage.sizes?.medium?.url || frame.mobileImage.url,
                  alt: frame.mobileImage.alt,
                  style: {
                    maxWidth: '100%',
                    maxHeight: '150px',
                    borderRadius: '4px'
                  }
                })
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
                style: {
                  display: 'flex',
                  gap: '8px'
                },
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.MediaUpload, {
                  onSelect: media => onSelectImage('mobileImage', media),
                  allowedTypes: ['image'],
                  value: frame.mobileImage?.id,
                  render: ({
                    open
                  }) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                    variant: "secondary",
                    onClick: open,
                    size: "small",
                    children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Replace', 'caes-reveal')
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                  variant: "secondary",
                  isDestructive: true,
                  onClick: () => onRemoveImage('mobileImage'),
                  size: "small",
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Remove', 'caes-reveal')
                })]
              })]
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
            label: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
              children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Caption', 'caes-reveal'), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("span", {
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
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
            label: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
              children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Alt Text', 'caes-reveal'), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("span", {
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
          }), frame.mobileImage && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
            style: {
              display: 'flex',
              gap: '8px',
              marginTop: '12px',
              flexWrap: 'wrap',
              alignItems: 'center'
            },
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
              variant: "secondary",
              onClick: () => setFocalPointModal('mobile'),
              icon: "image-crop",
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Set Focus Point', 'caes-reveal')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
              variant: "secondary",
              onClick: () => setDuotoneModal('mobile'),
              icon: "admin-appearance",
              children: frame.mobileDuotone ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Edit Filter', 'caes-reveal') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Add Filter', 'caes-reveal')
            }), frame.mobileDuotone && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.DuotoneSwatch, {
              values: frame.mobileDuotone
            })]
          })]
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        style: {
          paddingTop: '16px',
          borderTop: '1px solid #ddd'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("label", {
          style: {
            display: 'block',
            marginBottom: '8px',
            fontWeight: 500
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Transition', 'caes-reveal')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
          value: frame.transition.type,
          options: TRANSITION_OPTIONS,
          onChange: value => onUpdate({
            transition: {
              ...frame.transition,
              type: value
            }
          })
        })]
      })]
    }), focalPointModal && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Modal, {
      title: focalPointModal === 'desktop' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Set Focus Point — Wide Screens', 'caes-reveal') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Set Focus Point — Tall Screens', 'caes-reveal'),
      onRequestClose: () => setFocalPointModal(null),
      style: {
        maxWidth: '600px',
        width: '100%'
      },
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        style: {
          padding: '8px 0'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
          style: {
            margin: '0 0 16px 0',
            color: '#757575',
            fontSize: '13px'
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Click on the image to set the focal point. This determines which part of the image stays visible when cropped to fit the screen.', 'caes-reveal')
        }), focalPointModal === 'desktop' && frame.desktopImage && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.FocalPointPicker, {
          url: frame.desktopImage.url,
          value: frame.desktopFocalPoint || {
            x: 0.5,
            y: 0.5
          },
          onChange: value => onUpdate({
            desktopFocalPoint: value
          })
        }), focalPointModal === 'mobile' && frame.mobileImage && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.FocalPointPicker, {
          url: frame.mobileImage.url,
          value: frame.mobileFocalPoint || {
            x: 0.5,
            y: 0.5
          },
          onChange: value => onUpdate({
            mobileFocalPoint: value
          })
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
          style: {
            marginTop: '20px',
            display: 'flex',
            justifyContent: 'flex-end'
          },
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            variant: "primary",
            onClick: () => setFocalPointModal(null),
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Done', 'caes-reveal')
          })
        })]
      })
    }), duotoneModal && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Modal, {
      title: duotoneModal === 'desktop' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Duotone Filter — Wide Screens', 'caes-reveal') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Duotone Filter — Tall Screens', 'caes-reveal'),
      onRequestClose: () => setDuotoneModal(null),
      style: {
        maxWidth: '400px',
        width: '100%'
      },
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        style: {
          padding: '8px 0'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
          style: {
            margin: '0 0 16px 0',
            color: '#757575',
            fontSize: '13px'
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Apply a duotone color filter to this image. The first color replaces shadows, the second replaces highlights.', 'caes-reveal')
        }), duotoneModal === 'desktop' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.DuotonePicker, {
          duotonePalette: DUOTONE_PALETTE,
          colorPalette: COLOR_PALETTE,
          value: frame.desktopDuotone || undefined,
          onChange: value => onUpdate({
            desktopDuotone: value
          })
        }), duotoneModal === 'mobile' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.DuotonePicker, {
          duotonePalette: DUOTONE_PALETTE,
          colorPalette: COLOR_PALETTE,
          value: frame.mobileDuotone || undefined,
          onChange: value => onUpdate({
            mobileDuotone: value
          })
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
          style: {
            marginTop: '20px',
            display: 'flex',
            justifyContent: 'space-between'
          },
          children: [(duotoneModal === 'desktop' && frame.desktopDuotone || duotoneModal === 'mobile' && frame.mobileDuotone) && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            variant: "tertiary",
            isDestructive: true,
            onClick: () => {
              if (duotoneModal === 'desktop') {
                onUpdate({
                  desktopDuotone: null
                });
              } else {
                onUpdate({
                  mobileDuotone: null
                });
              }
              setDuotoneModal(null);
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Remove Filter', 'caes-reveal')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
            style: {
              marginLeft: 'auto'
            },
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
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

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"caes-hub/reveal","version":"0.1.0","title":"Reveal","category":"media","icon":"cover-image","description":"Full-window fixed background that transitions between frames as the user scrolls through content.","keywords":["reveal","scroll","parallax","immersive","background","storytelling"],"textdomain":"caes-reveal","attributes":{"frames":{"type":"array","default":[],"items":{"type":"object"}},"overlayColor":{"type":"string","default":"#000000"},"overlayOpacity":{"type":"number","default":30},"minHeight":{"type":"string","default":"100vh"},"scrollSpeed":{"type":"string","default":"normal","enum":["slow","normal","fast"]}},"supports":{"align":["full","wide"],"html":false,"color":{"text":true,"background":false},"spacing":{"padding":true}},"render":"file:./render.php","editorScript":"file:./index.js","editorStyle":"file:./index.css","style":"file:./style-index.css","viewScript":"file:./view.js"}');

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