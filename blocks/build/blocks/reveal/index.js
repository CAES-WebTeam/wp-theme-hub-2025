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
const DEFAULT_FRAME = {
  id: '',
  desktopImage: null,
  mobileImage: null,
  focalPoint: {
    x: 0.5,
    y: 0.5
  },
  duotone: null,
  transition: {
    type: 'fade',
    speed: 500
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
    minHeight
  } = attributes;
  const [expandedFrame, setExpandedFrame] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(null);
  const [showOverlayColorPicker, setShowOverlayColorPicker] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(false);

  // Add a new frame
  const addFrame = () => {
    const newFrame = {
      ...DEFAULT_FRAME,
      id: generateFrameId()
    };
    setAttributes({
      frames: [...frames, newFrame]
    });
    setExpandedFrame(frames.length);
  };

  // Remove a frame
  const removeFrame = frameIndex => {
    const newFrames = [...frames];
    newFrames.splice(frameIndex, 1);
    setAttributes({
      frames: newFrames
    });
    if (expandedFrame === frameIndex) {
      setExpandedFrame(null);
    }
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
    setExpandedFrame(frameIndex - 1);
  };

  // Move frame down
  const moveFrameDown = frameIndex => {
    if (frameIndex === frames.length - 1) return;
    const newFrames = [...frames];
    [newFrames[frameIndex], newFrames[frameIndex + 1]] = [newFrames[frameIndex + 1], newFrames[frameIndex]];
    setAttributes({
      frames: newFrames
    });
    setExpandedFrame(frameIndex + 1);
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
    // Parse hex color
    const hex = overlayColor.replace('#', '');
    const r = parseInt(hex.substring(0, 2), 16);
    const g = parseInt(hex.substring(2, 4), 16);
    const b = parseInt(hex.substring(4, 6), 16);
    return `rgba(${r}, ${g}, ${b}, ${opacity})`;
  };

  // Get first frame's image for preview
  const previewImage = frames.length > 0 && frames[0].desktopImage ? frames[0].desktopImage.url : null;
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useBlockProps)({
    className: 'caes-reveal-block',
    style: {
      '--reveal-min-height': minHeight
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
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.InspectorControls, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
        title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Overlay Settings', 'caes-reveal'),
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
          style: {
            display: 'flex',
            flexDirection: 'column',
            gap: '16px'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
            style: {
              display: 'flex',
              alignItems: 'center',
              gap: '12px'
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
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.RangeControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Overlay Opacity', 'caes-reveal'),
            value: overlayOpacity,
            onChange: value => setAttributes({
              overlayOpacity: value
            }),
            min: 0,
            max: 100,
            step: 5
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
          })
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
        title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Frames', 'caes-reveal'),
        initialOpen: true,
        children: [frames.length === 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Notice, {
          status: "warning",
          isDismissible: false,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Add at least one frame to display a background.', 'caes-reveal')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
          style: {
            display: 'flex',
            flexDirection: 'column',
            gap: '12px'
          },
          children: [frames.map((frame, index) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
            className: "reveal-frame-item",
            style: {
              border: '1px solid #ddd',
              borderRadius: '4px',
              overflow: 'hidden',
              backgroundColor: '#f9f9f9'
            },
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("button", {
              type: "button",
              onClick: () => setExpandedFrame(expandedFrame === index ? null : index),
              style: {
                display: 'flex',
                alignItems: 'center',
                gap: '12px',
                width: '100%',
                padding: '12px',
                border: 'none',
                background: 'none',
                cursor: 'pointer',
                textAlign: 'left'
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
                style: {
                  flexGrow: 1
                },
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("strong", {
                  children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Frame', 'caes-reveal'), " ", index + 1]
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
                  style: {
                    fontSize: '12px',
                    color: '#666'
                  },
                  children: frame.transition.type !== 'none' ? `${frame.transition.type} (${frame.transition.speed}ms)` : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('No transition', 'caes-reveal')
                })]
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
                style: {
                  fontSize: '20px'
                },
                children: expandedFrame === index ? '−' : '+'
              })]
            }), expandedFrame === index && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
              style: {
                padding: '12px',
                borderTop: '1px solid #ddd'
              },
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
                style: {
                  display: 'flex',
                  flexDirection: 'column',
                  gap: '16px'
                },
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
                  children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("label", {
                    style: {
                      display: 'block',
                      marginBottom: '8px',
                      fontWeight: 500
                    },
                    children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Desktop Image', 'caes-reveal'), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
                      style: {
                        color: '#cc0000'
                      },
                      children: " *"
                    })]
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.MediaUploadCheck, {
                    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.MediaUpload, {
                      onSelect: media => onSelectImage(index, 'desktopImage', media),
                      allowedTypes: ['image'],
                      value: frame.desktopImage?.id,
                      render: ({
                        open
                      }) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
                        children: frame.desktopImage ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
                          style: {
                            position: 'relative'
                          },
                          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("img", {
                            src: frame.desktopImage.sizes?.medium?.url || frame.desktopImage.url,
                            alt: frame.desktopImage.alt,
                            style: {
                              width: '100%',
                              height: 'auto',
                              borderRadius: '4px'
                            }
                          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.__experimentalHStack, {
                            spacing: 2,
                            style: {
                              marginTop: '8px'
                            },
                            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                              variant: "secondary",
                              onClick: open,
                              size: "small",
                              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Replace', 'caes-reveal')
                            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                              variant: "secondary",
                              isDestructive: true,
                              onClick: () => onRemoveImage(index, 'desktopImage'),
                              size: "small",
                              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Remove', 'caes-reveal')
                            })]
                          })]
                        }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                          variant: "secondary",
                          onClick: open,
                          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Select Image', 'caes-reveal')
                        })
                      })
                    })
                  }), frame.desktopImage && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
                    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Alt Text', 'caes-reveal'),
                    value: frame.desktopImage.alt || '',
                    onChange: value => {
                      const updatedImage = {
                        ...frame.desktopImage,
                        alt: value
                      };
                      updateFrame(index, {
                        desktopImage: updatedImage
                      });
                    },
                    style: {
                      marginTop: '8px'
                    }
                  })]
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
                  children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("label", {
                    style: {
                      display: 'block',
                      marginBottom: '8px',
                      fontWeight: 500
                    },
                    children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Mobile Image (optional)', 'caes-reveal')
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.MediaUploadCheck, {
                    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.MediaUpload, {
                      onSelect: media => onSelectImage(index, 'mobileImage', media),
                      allowedTypes: ['image'],
                      value: frame.mobileImage?.id,
                      render: ({
                        open
                      }) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
                        children: frame.mobileImage ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
                          style: {
                            position: 'relative'
                          },
                          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("img", {
                            src: frame.mobileImage.sizes?.thumbnail?.url || frame.mobileImage.url,
                            alt: frame.mobileImage.alt,
                            style: {
                              width: '80px',
                              height: '80px',
                              objectFit: 'cover',
                              borderRadius: '4px'
                            }
                          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.__experimentalHStack, {
                            spacing: 2,
                            style: {
                              marginTop: '8px'
                            },
                            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                              variant: "secondary",
                              onClick: open,
                              size: "small",
                              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Replace', 'caes-reveal')
                            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                              variant: "secondary",
                              isDestructive: true,
                              onClick: () => onRemoveImage(index, 'mobileImage'),
                              size: "small",
                              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Remove', 'caes-reveal')
                            })]
                          })]
                        }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                          variant: "secondary",
                          onClick: open,
                          size: "small",
                          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Select Mobile Image', 'caes-reveal')
                        })
                      })
                    })
                  }), frame.mobileImage && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
                    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Alt Text', 'caes-reveal'),
                    value: frame.mobileImage.alt || '',
                    onChange: value => {
                      const updatedImage = {
                        ...frame.mobileImage,
                        alt: value
                      };
                      updateFrame(index, {
                        mobileImage: updatedImage
                      });
                    },
                    style: {
                      marginTop: '8px'
                    }
                  })]
                }), frame.desktopImage && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
                  children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("label", {
                    style: {
                      display: 'block',
                      marginBottom: '8px',
                      fontWeight: 500
                    },
                    children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Focal Point', 'caes-reveal')
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.FocalPointPicker, {
                    url: frame.desktopImage.url,
                    value: frame.focalPoint,
                    onChange: value => updateFrame(index, {
                      focalPoint: value
                    })
                  })]
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
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
                    onChange: value => updateFrame(index, {
                      transition: {
                        ...frame.transition,
                        type: value
                      }
                    })
                  }), frame.transition.type !== 'none' && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.RangeControl, {
                    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Speed (ms)', 'caes-reveal'),
                    value: frame.transition.speed,
                    onChange: value => updateFrame(index, {
                      transition: {
                        ...frame.transition,
                        speed: value
                      }
                    }),
                    min: 100,
                    max: 2000,
                    step: 50
                  })]
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.__experimentalHStack, {
                  spacing: 2,
                  style: {
                    borderTop: '1px solid #ddd',
                    paddingTop: '12px'
                  },
                  children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                    variant: "secondary",
                    onClick: () => moveFrameUp(index),
                    disabled: index === 0,
                    icon: "arrow-up-alt2",
                    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Move Up', 'caes-reveal'),
                    size: "small"
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                    variant: "secondary",
                    onClick: () => moveFrameDown(index),
                    disabled: index === frames.length - 1,
                    icon: "arrow-down-alt2",
                    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Move Down', 'caes-reveal'),
                    size: "small"
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
                    variant: "secondary",
                    isDestructive: true,
                    onClick: () => removeFrame(index),
                    icon: "trash",
                    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Remove Frame', 'caes-reveal'),
                    size: "small",
                    style: {
                      marginLeft: 'auto'
                    }
                  })]
                })]
              })
            })]
          }, frame.id)), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            variant: "primary",
            onClick: addFrame,
            style: {
              width: '100%'
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Add Frame', 'caes-reveal')
          })]
        })]
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
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
            objectPosition: frames[0]?.focalPoint ? `${frames[0].focalPoint.x * 100}% ${frames[0].focalPoint.y * 100}%` : 'center'
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
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Add frames in the sidebar to set background images', 'caes-reveal')
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
        className: "reveal-frame-indicator",
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
          minHeight: '200px'
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

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"caes-hub/reveal","version":"0.1.0","title":"Reveal","category":"media","icon":"cover-image","description":"Full-window fixed background that transitions between frames as the user scrolls through content.","keywords":["reveal","scroll","parallax","immersive","background","storytelling"],"textdomain":"caes-reveal","attributes":{"frames":{"type":"array","default":[],"items":{"type":"object"}},"overlayColor":{"type":"string","default":"#000000"},"overlayOpacity":{"type":"number","default":30},"minHeight":{"type":"string","default":"100vh"}},"supports":{"align":["full","wide"],"html":false,"color":{"text":true,"background":false},"spacing":{"padding":true}},"editorScript":"file:./index.js","editorStyle":"file:./index.css","style":"file:./style-index.css","render":"file:./render.php","viewScript":"file:./view.js"}');

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