import { registerBlockVariation } from '@wordpress/blocks';
import { addFilter } from '@wordpress/hooks';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';

/** Publications Query Block Variation - START */

const publicationsVariation = 'pubs-feed';

registerBlockVariation('core/query', {
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
  isActive: ['namespace'],
  scope: ['inserter'],
  // allowedControls: ['inherit', 'postType', 'sticky', 'taxQuery', 'author', 'search', 'format', 'parents'],
  innerBlocks: [
    [
      'core/post-template',
      {},
      [
        ['core/post-title']
      ],
    ]
  ]
});

// Check if block is using our Publications variation
const isPubsVariation = (props) => {
  const { attributes: { namespace } } = props;
  return namespace && namespace === publicationsVariation;
};

// Add Inspector Controls for selecting language
const PubVariationControls = ({ props: { attributes, setAttributes } }) => {
  const { query } = attributes;

  return (
    <PanelBody title="Publication Feed Settings">
      {/* Language Selector */}
      <SelectControl
        label="Language"
        value={query.language}
        options={[
          { value: '', label: '' },
          { value: '1', label: 'English' },
          { value: '2', label: 'Spanish' }
        ]}
        onChange={(value) =>
          setAttributes({ query: { ...query, language: value } })
        }
      />
    </PanelBody>
  );
};

export const withPubVariationControls = (BlockEdit) => (props) => {
  return isPubsVariation(props) ? (
    <>
      <BlockEdit {...props} />
      <InspectorControls>
        <PubVariationControls props={props} />
      </InspectorControls>
    </>
  ) : (
    <BlockEdit {...props} />
  );
};

addFilter('editor.BlockEdit', 'core/query', withPubVariationControls);

/** Publications Query Block Variation - END */

/** Stories Query Block Variation - START */

const storiesVariation = 'stories-feed'; // Define a unique namespace for your stories

registerBlockVariation('core/query', {
  name: storiesVariation,
  title: 'Stories Feed',
  description: 'Displays a feed of stories',
  icon: 'list-view',
  attributes: {
    namespace: storiesVariation,
    query: {
      postType: 'post', 
      perPage: 4,
      offset: 0
    },
  },
  isActive: ['namespace'],
  scope: ['inserter'],
  innerBlocks: [
    [
      'core/post-template',
      {},
      [
        ['core/post-title'],
        ['core/post-date'],
        ['core/post-excerpt']
      ],
    ]
  ]
});

/** Stories Query Block Variation - END */