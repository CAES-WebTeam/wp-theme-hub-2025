import { registerBlockVariation } from '@wordpress/blocks';
import { addFilter } from '@wordpress/hooks';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';

/** Event Query Block Variation - START */

// Register event query block variation
const eventsVariation = 'upcoming-events';

registerBlockVariation( 'core/query', {
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

// Check if block is using our Events variation
const isEventsVariation = ( props ) => {
  const { attributes: { namespace } } = props;
  return namespace && namespace === eventsVariation;
};

// Add Inspector Controls for selecting event type
const EventsVariationControls = ( { props: { attributes, setAttributes } } ) => {
  const { query } = attributes;

  return (
    <PanelBody title="Events Feed Settings">
      <SelectControl
        label="CAES or Extension"
        value={ query.event_type }
        options={[
          { value: 'All', label: 'All' },
          { value: 'CAES', label: 'CAES' },
          { value: 'Extension', label: 'Extension' }
        ]}
        onChange={( value ) =>
          setAttributes({ query: { ...query, event_type: value } })
        }
      />
    </PanelBody>
  );
};


export const withEventsVariationControls = ( BlockEdit ) => ( props ) => {
  return isEventsVariation( props ) ? (
    <>
      <BlockEdit { ...props } />
      <InspectorControls>
        <EventsVariationControls props={ props } />
      </InspectorControls>
    </>
  ) : (
    <BlockEdit { ...props } />
  );
};

addFilter( 'editor.BlockEdit', 'core/query', withEventsVariationControls );

/** Event Query Block Variation - END */

/** Publications Query Block Variation - START */

const publicationsVariation = 'pubs-feed';

registerBlockVariation( 'core/query', {
  name: publicationsVariation,
  title: 'Publications Feed',
  description: 'Displays a feed of publications',
  icon: 'list-view',
  attributes: {
    namespace: publicationsVariation,
    query: {
      postType: 'publications',
      perPage: 4,
      offset: 0
    },
  },
  isActive: [ 'namespace' ],
  scope: [ 'inserter' ],
  // allowedControls: ['inherit', 'postType', 'sticky', 'taxQuery', 'author', 'search', 'format', 'parents'],
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

// Check if block is using our Publications variation
const isPubsVariation = ( props ) => {
  const { attributes: { namespace } } = props;
  return namespace && namespace === publicationsVariation;
};

// Add Inspector Controls for selecting language
const PubVariationControls = ( { props: { attributes, setAttributes } } ) => {
  const { query } = attributes;

  return (
    <PanelBody title="Publication Feed Settings">
      {/* Language Selector */}
      <SelectControl
        label="Language"
        value={ query.language }
        options={[
          { value: '', label: '' },
          { value: '1', label: 'English' },
          { value: '2', label: 'Spanish' }
        ]}
        onChange={( value ) =>
          setAttributes({ query: { ...query, language: value } })
        }
      />

      {/* Order By Selector */}
      {/* <SelectControl
        label="Order By"
        value={ query.pubOrderBy }
        options={[
          { value: 'date_desc', label: 'Newest to oldest' },
          { value: 'date_asc', label: 'Oldest to newest' },
          { value: 'title_asc', label: 'A → Z' },
          { value: 'title_desc', label: 'Z → A' },
          { value: 'recently_revised', label: 'Recently Revised' },
          { value: 'recently_published', label: 'Recently Published' }
        ]}
        onChange={( value ) =>
          setAttributes({ query: { ...query, pubOrderBy: value } })
        }
      /> */}
    </PanelBody>
  );
};

export const withPubVariationControls = ( BlockEdit ) => ( props ) => {
  return isPubsVariation( props ) ? (
    <>
      <BlockEdit { ...props } />
      <InspectorControls>
        <PubVariationControls props={ props } />
      </InspectorControls>
    </>
  ) : (
    <BlockEdit { ...props } />
  );
};

addFilter( 'editor.BlockEdit', 'core/query', withPubVariationControls );

/** Publications Query Block Variation - END */