/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/blocks/motion-scroll/edit.js":
/*!******************************************!*\
  !*** ./src/blocks/motion-scroll/edit.js ***!
  \******************************************/
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
/**
 * Motion Scroll Block Editor
 */





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
const DEFAULT_SLIDE = {
  id: '',
  image: null,
  focalPoint: {
    x: 0.5,
    y: 0.5
  },
  duotone: null
};
const generateSlideId = () => {
  return 'slide-' + Math.random().toString(36).substr(2, 9);
};

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
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("svg", {
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
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("defs", {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("filter", {
        id: filterId,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("feColorMatrix", {
          colorInterpolationFilters: "sRGB",
          type: "matrix",
          values: ".299 .587 .114 0 0 .299 .587 .114 0 0 .299 .587 .114 0 0 0 0 0 1 0"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("feComponentTransfer", {
          colorInterpolationFilters: "sRGB",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("feFuncR", {
            type: "table",
            tableValues: `${shadow.r} ${highlight.r}`
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("feFuncG", {
            type: "table",
            tableValues: `${shadow.g} ${highlight.g}`
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("feFuncB", {
            type: "table",
            tableValues: `${shadow.b} ${highlight.b}`
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("feFuncA", {
            type: "table",
            tableValues: "0 1"
          })]
        })]
      })
    })
  });
};
const Edit = ({
  attributes,
  setAttributes,
  clientId
}) => {
  const {
    slides,
    contentPosition
  } = attributes;
  const [showSlideManager, setShowSlideManager] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(false);

  // Auto-add first slide when block is inserted
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    if (slides.length === 0) {
      setAttributes({
        slides: [{
          ...DEFAULT_SLIDE,
          id: generateSlideId()
        }]
      });
    }
  }, []);
  const addSlide = () => {
    const newSlide = {
      ...DEFAULT_SLIDE,
      id: generateSlideId()
    };
    setAttributes({
      slides: [...slides, newSlide]
    });
  };
  const removeSlide = slideIndex => {
    if (slides.length === 1) {
      return;
    }
    const newSlides = [...slides];
    newSlides.splice(slideIndex, 1);
    setAttributes({
      slides: newSlides
    });
  };
  const updateSlide = (slideIndex, updates) => {
    const newSlides = [...slides];
    newSlides[slideIndex] = {
      ...newSlides[slideIndex],
      ...updates
    };
    setAttributes({
      slides: newSlides
    });
  };
  const moveSlideUp = slideIndex => {
    if (slideIndex === 0) return;
    const newSlides = [...slides];
    [newSlides[slideIndex - 1], newSlides[slideIndex]] = [newSlides[slideIndex], newSlides[slideIndex - 1]];
    setAttributes({
      slides: newSlides
    });
  };
  const moveSlideDown = slideIndex => {
    if (slideIndex === slides.length - 1) return;
    const newSlides = [...slides];
    [newSlides[slideIndex], newSlides[slideIndex + 1]] = [newSlides[slideIndex + 1], newSlides[slideIndex]];
    setAttributes({
      slides: newSlides
    });
  };
  const duplicateSlide = slideIndex => {
    const slideToDuplicate = slides[slideIndex];
    const duplicatedSlide = {
      ...JSON.parse(JSON.stringify(slideToDuplicate)),
      id: generateSlideId()
    };
    const newSlides = [...slides];
    newSlides.splice(slideIndex + 1, 0, duplicatedSlide);
    setAttributes({
      slides: newSlides
    });
  };
  const onSelectImage = (slideIndex, media) => {
    const imageData = {
      id: media.id,
      url: media.url,
      alt: media.alt || '',
      caption: media.caption || '',
      sizes: media.sizes || {}
    };
    updateSlide(slideIndex, {
      image: imageData
    });
  };
  const onRemoveImage = slideIndex => {
    updateSlide(slideIndex, {
      image: null
    });
  };
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useBlockProps)({
    className: `caes-motion-scroll-editor content-${contentPosition}`
  });
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.BlockControls, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToolbarGroup, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToolbarButton, {
          icon: "align-pull-left",
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Show content on left', 'caes-motion-scroll'),
          isActive: contentPosition === 'left',
          onClick: () => setAttributes({
            contentPosition: 'left'
          })
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToolbarButton, {
          icon: "align-pull-right",
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Show content on right', 'caes-motion-scroll'),
          isActive: contentPosition === 'right',
          onClick: () => setAttributes({
            contentPosition: 'right'
          })
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToolbarGroup, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ToolbarButton, {
          icon: "admin-generic",
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Manage Images', 'caes-motion-scroll'),
          onClick: () => setShowSlideManager(true)
        })
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.InspectorControls, {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
        title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Images', 'caes-motion-scroll'),
        initialOpen: true,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("p", {
          style: {
            marginBottom: '12px',
            color: '#757575',
            fontSize: '13px'
          },
          children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('This block has', 'caes-motion-scroll'), " ", slides.length, " ", slides.length === 1 ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('image', 'caes-motion-scroll') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('images', 'caes-motion-scroll'), "."]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          variant: "secondary",
          onClick: () => setShowSlideManager(true),
          style: {
            width: '100%'
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Manage Images', 'caes-motion-scroll')
        })]
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
      ...blockProps,
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        className: "motion-scroll-editor-layout",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
          className: "motion-scroll-editor-images",
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
            className: "motion-scroll-images-preview",
            children: [slides.length > 0 && slides[0]?.image ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("img", {
              src: slides[0].image.url,
              alt: slides[0].image.alt || ''
            }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
              className: "motion-scroll-placeholder",
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Add images using the toolbar button', 'caes-motion-scroll')
              })
            }), slides.length > 1 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
              className: "motion-scroll-image-count",
              children: ["+", slides.length - 1, " ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('more', 'caes-motion-scroll')]
            })]
          })
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
          className: "motion-scroll-editor-content",
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.InnerBlocks, {
            allowedBlocks: true,
            placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Add content blocks here...', 'caes-motion-scroll')
          })
        })]
      })
    }), showSlideManager && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Modal, {
      title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Manage Images', 'caes-motion-scroll'),
      onRequestClose: () => setShowSlideManager(false),
      className: "motion-scroll-slide-manager-modal",
      style: {
        maxWidth: '900px',
        width: '90vw'
      },
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        style: {
          padding: '20px 0'
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
          style: {
            marginBottom: '20px',
            color: '#757575'
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Add and configure images that will transition as the user scrolls through the content.', 'caes-motion-scroll')
        }), slides.map((slide, index) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(SlideManagerPanel, {
          slide: slide,
          index: index,
          totalSlides: slides.length,
          onUpdate: updates => updateSlide(index, updates),
          onRemove: () => removeSlide(index),
          onMoveUp: () => moveSlideUp(index),
          onMoveDown: () => moveSlideDown(index),
          onDuplicate: () => duplicateSlide(index),
          onSelectImage: media => onSelectImage(index, media),
          onRemoveImage: () => onRemoveImage(index),
          clientId: clientId
        }, slide.id || index)), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          variant: "primary",
          onClick: addSlide,
          style: {
            width: '100%',
            marginTop: '20px'
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Add Image', 'caes-motion-scroll')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
          style: {
            marginTop: '20px',
            textAlign: 'right'
          },
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            variant: "secondary",
            onClick: () => setShowSlideManager(false),
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Done', 'caes-motion-scroll')
          })
        })]
      })
    })]
  });
};

// Slide Manager Panel Component
const SlideManagerPanel = ({
  slide,
  index,
  totalSlides,
  onUpdate,
  onRemove,
  onMoveUp,
  onMoveDown,
  onDuplicate,
  onSelectImage,
  onRemoveImage,
  clientId
}) => {
  const [isOpen, setIsOpen] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(false);
  const [showFocalPointModal, setShowFocalPointModal] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(false);
  const [showDuotoneModal, setShowDuotoneModal] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(false);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
    style: {
      border: '1px solid #ddd',
      borderRadius: '4px',
      marginBottom: '20px',
      background: '#fff'
    },
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
      style: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        padding: '16px',
        borderBottom: isOpen ? '1px solid #ddd' : 'none',
        background: '#f9f9f9',
        cursor: 'pointer'
      },
      onClick: () => setIsOpen(!isOpen),
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        style: {
          display: 'flex',
          alignItems: 'center',
          gap: '12px'
        },
        children: [slide.image ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
          style: {
            width: '60px',
            height: '40px',
            borderRadius: '4px',
            overflow: 'hidden',
            border: '1px solid #ddd',
            flexShrink: 0
          },
          children: (() => {
            const filterId = `thumbnail-${clientId}-${index}`;
            const duotone = slide.duotone;
            return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
              children: [duotone && getDuotoneFilter(duotone, filterId), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("img", {
                src: slide.image.url,
                alt: "",
                style: {
                  width: '100%',
                  height: '100%',
                  objectFit: 'cover',
                  filter: duotone ? `url(#${filterId})` : undefined
                }
              })]
            });
          })()
        }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
          style: {
            width: '60px',
            height: '40px',
            borderRadius: '4px',
            border: '1px solid #ddd',
            background: '#e0e0e0',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            fontSize: '10px',
            color: '#666',
            flexShrink: 0
          },
          children: "No image"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("strong", {
          style: {
            fontSize: '16px'
          },
          children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Image', 'caes-motion-scroll'), " ", index + 1]
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        style: {
          display: 'flex',
          gap: '8px',
          alignItems: 'center'
        },
        onClick: e => e.stopPropagation(),
        children: [index > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          size: "small",
          icon: "arrow-up-alt2",
          onClick: onMoveUp,
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Move up', 'caes-motion-scroll')
        }), index < totalSlides - 1 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          size: "small",
          icon: "arrow-down-alt2",
          onClick: onMoveDown,
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Move down', 'caes-motion-scroll')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          size: "small",
          icon: "admin-page",
          onClick: onDuplicate,
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Duplicate', 'caes-motion-scroll')
        }), totalSlides > 1 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          size: "small",
          icon: "trash",
          onClick: onRemove,
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Remove', 'caes-motion-scroll'),
          isDestructive: true
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          size: "small",
          icon: isOpen ? 'arrow-up-alt2' : 'arrow-down-alt2',
          onClick: e => {
            e.stopPropagation();
            setIsOpen(!isOpen);
          },
          label: isOpen ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Collapse', 'caes-motion-scroll') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Expand', 'caes-motion-scroll')
        })]
      })]
    }), isOpen && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
      style: {
        padding: '20px'
      },
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(ImagePanel, {
        slide: slide,
        onSelectImage: onSelectImage,
        onRemoveImage: onRemoveImage,
        onUpdate: onUpdate,
        setShowFocalPointModal: setShowFocalPointModal,
        setShowDuotoneModal: setShowDuotoneModal,
        clientId: clientId,
        slideIndex: index
      })
    }), showFocalPointModal && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(FocalPointModal, {
      slide: slide,
      onUpdate: onUpdate,
      onClose: () => setShowFocalPointModal(false)
    }), showDuotoneModal && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(DuotoneModal, {
      slide: slide,
      onUpdate: onUpdate,
      onClose: () => setShowDuotoneModal(false)
    })]
  });
};

// Image Panel Component
const ImagePanel = ({
  slide,
  onSelectImage,
  onRemoveImage,
  onUpdate,
  setShowFocalPointModal,
  setShowDuotoneModal,
  clientId,
  slideIndex
}) => {
  const image = slide.image;
  const duotone = slide.duotone;
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("label", {
      style: {
        display: 'block',
        marginBottom: '8px',
        fontWeight: 500,
        fontSize: '13px',
        color: '#1e1e1e'
      },
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Image', 'caes-motion-scroll')
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
      style: {
        margin: '0 0 12px',
        fontSize: '12px',
        color: '#757575'
      },
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Recommended: JPEG @ 1920 x 1080px', 'caes-motion-scroll')
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.MediaUploadCheck, {
      children: !image ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.MediaUpload, {
          onSelect: onSelectImage,
          allowedTypes: ['image'],
          render: ({
            open
          }) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            variant: "secondary",
            onClick: open,
            style: {
              width: '100%',
              height: '200px'
            },
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Select Image', 'caes-motion-scroll')
          })
        })
      }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
          style: {
            marginBottom: '16px'
          },
          children: (() => {
            const filterId = `manager-${clientId}-${slideIndex}`;
            return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
              children: [duotone && getDuotoneFilter(duotone, filterId), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("img", {
                src: image.url,
                alt: image.alt,
                style: {
                  width: '100%',
                  height: 'auto',
                  maxHeight: '250px',
                  aspectRatio: '16 / 9',
                  objectFit: 'cover',
                  borderRadius: '4px',
                  filter: duotone ? `url(#${filterId})` : undefined
                }
              })]
            });
          })()
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
          style: {
            display: 'flex',
            gap: '8px',
            marginBottom: '16px'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.MediaUpload, {
            onSelect: onSelectImage,
            allowedTypes: ['image'],
            value: image?.id,
            render: ({
              open
            }) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
              variant: "secondary",
              onClick: open,
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Replace', 'caes-motion-scroll')
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            variant: "secondary",
            isDestructive: true,
            onClick: onRemoveImage,
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Remove', 'caes-motion-scroll')
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Alt Text', 'caes-motion-scroll') + ' (' + (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('required', 'caes-motion-scroll') + ')',
          value: image?.alt || '',
          onChange: value => {
            const updatedImage = {
              ...image,
              alt: value
            };
            onUpdate({
              image: updatedImage
            });
          },
          placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Describe image for screenreaders', 'caes-motion-scroll')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
          style: {
            display: 'flex',
            gap: '8px',
            marginTop: '16px',
            flexWrap: 'wrap',
            alignItems: 'center'
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            variant: "secondary",
            onClick: () => setShowFocalPointModal(true),
            icon: "image-crop",
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Set Focus Point', 'caes-motion-scroll')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            variant: "secondary",
            onClick: () => setShowDuotoneModal(true),
            icon: "admin-appearance",
            children: duotone ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Edit Filter', 'caes-motion-scroll') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Add Filter', 'caes-motion-scroll')
          }), duotone && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.DuotoneSwatch, {
            values: duotone
          })]
        })]
      })
    })]
  });
};

// Focal Point Modal
const FocalPointModal = ({
  slide,
  onUpdate,
  onClose
}) => {
  const image = slide.image;
  if (!image) {
    return null;
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Modal, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Set Focus Point', 'caes-motion-scroll'),
    onRequestClose: onClose,
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
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Click on the image to set the focal point. This determines which part of the image stays visible when cropped.', 'caes-motion-scroll')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.FocalPointPicker, {
        url: image.url,
        value: slide.focalPoint || {
          x: 0.5,
          y: 0.5
        },
        onChange: value => onUpdate({
          focalPoint: value
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
        style: {
          marginTop: '20px',
          display: 'flex',
          justifyContent: 'flex-end'
        },
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          variant: "primary",
          onClick: onClose,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Done', 'caes-motion-scroll')
        })
      })]
    })
  });
};

// Duotone Modal
const DuotoneModal = ({
  slide,
  onUpdate,
  onClose
}) => {
  const duotone = slide.duotone;
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Modal, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Duotone Filter', 'caes-motion-scroll'),
    onRequestClose: onClose,
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
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Apply a duotone color filter to this image. The first color replaces shadows, the second replaces highlights.', 'caes-motion-scroll')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.DuotonePicker, {
        duotonePalette: DUOTONE_PALETTE,
        colorPalette: COLOR_PALETTE,
        value: duotone || undefined,
        onChange: value => onUpdate({
          duotone: value
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        style: {
          marginTop: '20px',
          display: 'flex',
          justifyContent: 'space-between'
        },
        children: [duotone && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
          variant: "tertiary",
          isDestructive: true,
          onClick: () => {
            onUpdate({
              duotone: null
            });
            onClose();
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Remove Filter', 'caes-motion-scroll')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
          style: {
            marginLeft: 'auto'
          },
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
            variant: "primary",
            onClick: onClose,
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Done', 'caes-motion-scroll')
          })
        })]
      })]
    })
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (Edit);

/***/ }),

/***/ "./src/blocks/motion-scroll/index.js":
/*!*******************************************!*\
  !*** ./src/blocks/motion-scroll/index.js ***!
  \*******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./edit */ "./src/blocks/motion-scroll/edit.js");
/* harmony import */ var _save__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./save */ "./src/blocks/motion-scroll/save.js");
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./block.json */ "./src/blocks/motion-scroll/block.json");
/* harmony import */ var _editor_scss__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./editor.scss */ "./src/blocks/motion-scroll/editor.scss");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./style.scss */ "./src/blocks/motion-scroll/style.scss");
/**
 * Motion Scroll Block Registration
 */






(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_3__.name, {
  edit: _edit__WEBPACK_IMPORTED_MODULE_1__["default"],
  save: _save__WEBPACK_IMPORTED_MODULE_2__["default"]
});

/***/ }),

/***/ "./src/blocks/motion-scroll/save.js":
/*!******************************************!*\
  !*** ./src/blocks/motion-scroll/save.js ***!
  \******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ save)
/* harmony export */ });
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);
/**
 * Motion Scroll Block Save (Dynamic render via PHP)
 */


function save() {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.InnerBlocks.Content, {});
}

/***/ }),

/***/ "./src/blocks/motion-scroll/editor.scss":
/*!**********************************************!*\
  !*** ./src/blocks/motion-scroll/editor.scss ***!
  \**********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./src/blocks/motion-scroll/style.scss":
/*!*********************************************!*\
  !*** ./src/blocks/motion-scroll/style.scss ***!
  \*********************************************/
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

/***/ "./src/blocks/motion-scroll/block.json":
/*!*********************************************!*\
  !*** ./src/blocks/motion-scroll/block.json ***!
  \*********************************************/
/***/ ((module) => {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"caes-hub/motion-scroll","version":"0.1.0","title":"Motion Scroll","category":"media","icon":"images-alt2","description":"Split layout with scrollable content on one side and sticky transitioning images on the other.","keywords":["motion","scroll","scrollmation","sticky","images","storytelling"],"textdomain":"caes-motion-scroll","attributes":{"align":{"type":"string","default":"full"},"slides":{"type":"array","default":[],"items":{"type":"object"}},"contentPosition":{"type":"string","default":"left","enum":["left","right"]}},"supports":{"align":["full","wide"],"html":false,"color":{"text":true,"background":true},"spacing":{"padding":true,"margin":true}},"providesContext":{"caes-hub/motion-scroll-slides":"slides"},"render":"file:./render.php","editorScript":"file:./index.js","editorStyle":"file:./index.css","style":"file:./style-index.css","viewScript":"file:./view.js"}');

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
/******/ 			"blocks/motion-scroll/index": 0,
/******/ 			"blocks/motion-scroll/style-index": 0
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
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["blocks/motion-scroll/style-index"], () => (__webpack_require__("./src/blocks/motion-scroll/index.js")))
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;
//# sourceMappingURL=index.js.map