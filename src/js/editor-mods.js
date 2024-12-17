// ADD BLOCK STYLES

wp.blocks.registerBlockStyle( 'core/list', {
    name: 'caes-hub-list-none',
    label: 'List Style: None'
} );

wp.blocks.registerBlockStyle( 'core/button', {
    name: 'caes-hub-red-border',
    label: 'Red Border'
} );

wp.blocks.registerBlockStyle( 'core/button', {
    name: 'caes-hub-black-border',
    label: 'Black Border'
} );

wp.blocks.registerBlockStyle( 'core/heading', {
    name: 'caes-hub-section-heading',
    label: 'Section Heading'
} );

wp.blocks.registerBlockStyle( 'core/heading', {
    name: 'caes-hub-full-underline',
    label: 'Full Width Underline'
} );

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