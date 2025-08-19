// ADD BLOCK STYLES

// List Editor Styles

wp.blocks.registerBlockStyle( 'core/list', {
    name: 'caes-hub-list-none',
    label: 'List Style: None'
} );

wp.blocks.registerBlockStyle( 'core/list', {
  name: 'caes-hub-list-checkbox',
  label: 'List Style: Checkbox'
} );

// Button Editor Styles

wp.blocks.registerBlockStyle( 'core/button', {
    name: 'caes-hub-red-border',
    label: 'Red Border'
} );

wp.blocks.registerBlockStyle( 'core/button', {
    name: 'caes-hub-black-border',
    label: 'Black Border'
} );

wp.blocks.registerBlockStyle( 'core/button', {
    name: 'caes-hub-arrow',
    label: 'Arrow'
} );

// Heading Editor Styles

wp.blocks.registerBlockStyle( 'core/heading', {
    name: 'caes-hub-section-heading',
    label: 'Section Heading'
} );

wp.blocks.registerBlockStyle( 'core/heading', {
    name: 'caes-hub-full-underline',
    label: 'Full Width Underline'
} );

// Post Title Editor Styles

wp.blocks.registerBlockStyle( 'core/post-title', {
  name: 'caes-hub-section-heading',
  label: 'Section Heading'
} );

wp.blocks.registerBlockStyle( 'core/post-title', {
  name: 'caes-hub-full-underline',
  label: 'Full Width Underline'
} );

// Separator Editor Styles

wp.blocks.registerBlockStyle( 'core/separator', {
  name: 'caes-hub-partial-underline',
  label: 'Partial Width Underline'
})

wp.blocks.registerBlockStyle( 'core/separator', {
  name: 'caes-hub-contrast-underline-thick',
  label: 'Thick Contrast Underline'
})

// Quote Editor Styles

wp.blocks.registerBlockStyle( 'core/quote', {
  name: 'caes-hub-red-smart-quote',
  label: 'Red Smart Quote'
})

// Table Editor Styles

wp.blocks.registerBlockStyle( 'core/table', {
  name: 'caes-hub-hstripe',
  label: 'Stripes with Borders'
})

wp.blocks.registerBlockStyle( 'core/table', {
  name: 'caes-hub-vstripe',
  label: 'Vertical Stripes'
})

wp.blocks.registerBlockStyle( 'core/table', {
  name: 'caes-hub-vstripe-border',
  label: 'Vertical Stripes with Borders'
})

// Group Editor Styles
wp.blocks.registerBlockStyle( 'core/group', {
  name: 'caes-hub-align-left-40',
  label: 'Align Left 40%'
})

wp.blocks.registerBlockStyle( 'core/group', {
  name: 'caes-hub-align-right-40',
  label: 'Align Right 40%'
})

wp.blocks.registerBlockStyle( 'core/group', {
  name: 'caes-hub-step',
  label: 'Step Arrow Right'
})

// PARAGRAPH STYLES
wp.blocks.registerBlockStyle( 'core/paragraph', {
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
