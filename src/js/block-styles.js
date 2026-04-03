// ADD BLOCK STYLES

/** COVER BLOCK PARALLAX CONTROLS **/

const { addFilter } = wp.hooks;
const { createHigherOrderComponent } = wp.compose;
const { Fragment, createElement } = wp.element;
const { InspectorControls } = wp.blockEditor;
const { PanelBody, SelectControl } = wp.components;

addFilter(
    'blocks.registerBlockType',
    'caes-hub/cover-parallax-attributes',
    function (settings, name) {
        if (name !== 'core/cover') return settings;
        return Object.assign({}, settings, {
            attributes: Object.assign({}, settings.attributes, {
                caesParallaxType: {
                    type: 'string',
                    default: 'none',
                },
                caesParallaxSpeed: {
                    type: 'string',
                    default: 'medium',
                },
            }),
        });
    }
);

const withParallaxControls = createHigherOrderComponent(function (BlockEdit) {
    return function (props) {
        if (props.name !== 'core/cover') {
            return createElement(BlockEdit, props);
        }

        const { attributes, setAttributes } = props;
        const { caesParallaxType, caesParallaxSpeed } = attributes;

        const controls = [
            createElement(SelectControl, {
                key: 'parallax-type',
                label: 'Parallax Effect',
                value: caesParallaxType,
                options: [
                    { label: 'None', value: 'none' },
                    { label: 'Shift', value: 'shift' },
                    { label: 'Zoom', value: 'zoom' },
                ],
                onChange: function (value) {
                    setAttributes({ caesParallaxType: value });
                },
            }),
        ];

        if (caesParallaxType !== 'none') {
            controls.push(
                createElement(SelectControl, {
                    key: 'parallax-speed',
                    label: 'Speed',
                    value: caesParallaxSpeed,
                    options: [
                        { label: 'Slow', value: 'slow' },
                        { label: 'Medium', value: 'medium' },
                        { label: 'Fast', value: 'fast' },
                    ],
                    onChange: function (value) {
                        setAttributes({ caesParallaxSpeed: value });
                    },
                })
            );
        }

        const { Notice } = wp.components;

        const note = createElement(
            Notice,
            { key: 'parallax-note', status: 'info', isDismissible: false },
            'Custom feature added by the CAES Field Report theme.'
        );

        return createElement(
            Fragment,
            null,
            createElement(BlockEdit, props),
            createElement(
                InspectorControls,
                null,
                createElement(PanelBody, { title: 'Parallax', initialOpen: false }, note, ...controls)
            )
        );
    };
}, 'withParallaxControls');

addFilter('editor.BlockEdit', 'caes-hub/cover-parallax-controls', withParallaxControls);

/** END COVER BLOCK PARALLAX CONTROLS **/

// List Editor Styles

wp.blocks.registerBlockStyle('core/list', {
  name: 'caes-hub-list-none',
  label: 'List Style: None'
});

wp.blocks.registerBlockStyle('core/list', {
  name: 'caes-hub-list-checkbox',
  label: 'List Style: Checkbox'
});

// Button Editor Styles

wp.blocks.registerBlockStyle('core/button', {
  name: 'caes-hub-red-border',
  label: 'Red Border'
});

wp.blocks.registerBlockStyle('core/button', {
  name: 'caes-hub-black-border',
  label: 'Black Border'
});

wp.blocks.registerBlockStyle('core/button', {
  name: 'caes-hub-arrow',
  label: 'Arrow'
});

// Heading Editor Styles

wp.blocks.registerBlockStyle('core/heading', {
  name: 'caes-hub-section-heading',
  label: 'Section Heading'
});

wp.blocks.registerBlockStyle('core/heading', {
  name: 'caes-hub-full-underline',
  label: 'Full Width Underline'
});

// Post Terms Editor Styles

wp.blocks.registerBlockStyle('core/post-terms', {
  name: 'caes-hub-section-heading',
  label: 'Section Heading'
});

wp.blocks.registerBlockStyle('core/post-terms', {
  name: 'caes-hub-full-underline',
  label: 'Full Width Underline'
});

// Post Title Editor Styles

wp.blocks.registerBlockStyle('core/post-title', {
  name: 'caes-hub-section-heading',
  label: 'Section Heading'
});

wp.blocks.registerBlockStyle('core/post-title', {
  name: 'caes-hub-full-underline',
  label: 'Full Width Underline'
});

// Separator Editor Styles

wp.blocks.registerBlockStyle('core/separator', {
  name: 'caes-hub-partial-underline',
  label: 'Partial Width Underline'
})

wp.blocks.registerBlockStyle('core/separator', {
  name: 'caes-hub-partial-thick-underline',
  label: 'Partial Width Thick Underline'
})

wp.blocks.registerBlockStyle('core/separator', {
  name: 'caes-hub-contrast-underline-thick',
  label: 'Thick Contrast Underline'
})

// Quote Editor Styles

wp.blocks.registerBlockStyle('core/quote', {
  name: 'caes-hub-red-smart-quote',
  label: 'Red Smart Quote'
})

// Table Editor Styles

wp.blocks.registerBlockStyle('core/table', {
  name: 'caes-hub-hstripe',
  label: 'Stripes with Borders'
})

wp.blocks.registerBlockStyle('core/table', {
  name: 'caes-hub-vstripe',
  label: 'Vertical Stripes'
})

wp.blocks.registerBlockStyle('core/table', {
  name: 'caes-hub-vstripe-border',
  label: 'Vertical Stripes with Borders'
})

// Group Editor Styles
wp.blocks.registerBlockStyle('core/group', {
  name: 'caes-hub-align-left-40',
  label: 'Align Left 40%'
})

wp.blocks.registerBlockStyle('core/group', {
  name: 'caes-hub-align-right-40',
  label: 'Align Right 40%'
})

wp.blocks.registerBlockStyle('core/group', {
  name: 'caes-hub-step',
  label: 'Step Arrow Right'
})

// PARAGRAPH STYLES
wp.blocks.registerBlockStyle('core/paragraph', {
  name: 'caes-hub-hanging-indent',
  label: 'Hanging Indent'
});

// CUSTOM BLOCKS
wp.blocks.registerBlockStyle('caes-hub/pub-details-authors', {
  name: 'caes-hub-compact',
  label: 'Compact'
});
wp.blocks.registerBlockStyle('caes-hub/pub-details-authors', {
  name: 'caes-hub-olympic-accent-border',
  label: 'Olympic Accent Border'
});
wp.blocks.registerBlockStyle('caes-hub/toc-new', {
  name: 'caes-hub-style-2',
  label: 'Style 2'
});
wp.blocks.registerBlockStyle('caes-hub/primary-topic', {
  name: 'caes-hub-oswald-uppercase',
  label: 'Oswald Uppercase'
});
wp.blocks.registerBlockStyle('caes-hub/primary-topic', {
  name: 'caes-hub-merriweather-sans-uppercase',
  label: 'Merriweather Sans Uppercase'
});
wp.blocks.registerBlockStyle('caes-hub/relevanssi-search', {
  name: 'caes-hub-relevanssi-menu-search',
  label: 'Menu Search'
});
