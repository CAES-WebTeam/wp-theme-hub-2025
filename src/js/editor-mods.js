// ADD BLOCK STYLES

// List Editor Styles

wp.blocks.registerBlockStyle( 'core/list', {
    name: 'caes-hub-list-none',
    label: 'List Style: None'
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

// Quote Editor Styles

wp.blocks.registerBlockStyle( 'core/quote', {
  name: 'caes-hub-red-smart-quote',
  label: 'Red Smart Quote'
})

// ADD BLOCK VARIATIONS

const eventsVariation = 'upcoming-events';

wp.blocks.registerBlockVariation( 'core/query', {
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
    },
  },
  isActive: [ 'namespace' ],
  scope: [ 'inserter' ],
  innerBlocks: [
    [
      'core/post-template',
      {},
      [
        [ 'core/post-title' ]
      ],
    ]
  ]
});