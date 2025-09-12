import { registerBlockVariation } from '@wordpress/blocks';
import { addFilter } from '@wordpress/hooks';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';

/** Event Query Block Variation - START */

// Register event query block variation
const eventsVariation = 'upcoming-events';

registerBlockVariation('core/query', {
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
  isActive: ['namespace'],
  scope: ['inserter'],
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

/** Event Query Block Variation - END */

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
      offset: 0,
      // Add attributes to store term selections
      taxQueryInclude: [],
      taxQueryExclude: [],
    },
  },
  isActive: ['namespace'],
  scope: ['inserter'],
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

// Add Inspector Controls for filtering
const PubVariationControls = ({ props: { attributes, setAttributes } }) => {
  const { query } = attributes;
  const { taxQueryInclude = [], taxQueryExclude = [] } = query;

  // Fetch publication categories using the core data store
  const pubCategories = useSelect((select) => {
    return select('core').getEntityRecords('taxonomy', 'publication_category', { per_page: -1 });
  }, []);

  // Handlers to update attributes when checkboxes are changed
  const toggleTerm = (termId, queryKey, currentTerms) => {
    const newTerms = currentTerms.includes(termId)
      ? currentTerms.filter(id => id !== termId)
      : [...currentTerms, termId];
    setAttributes({ query: { ...query, [queryKey]: newTerms } });
  };

  return (
    <PanelBody title="Publication Feed Settings">
      {/* Language Selector (existing) */}
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

      {/* Include by Category */}
      <div style={{ marginBottom: '16px' }}>
        <strong>Include by Category</strong>
        {!pubCategories && <p>Loading categories...</p>}
        {pubCategories && pubCategories.length === 0 && <p>No categories found.</p>}
        {pubCategories && pubCategories.map(term => (
          <CheckboxControl
            key={term.id}
            label={term.name}
            checked={taxQueryInclude.includes(term.id)}
            onChange={() => toggleTerm(term.id, 'taxQueryInclude', taxQueryInclude)}
          />
        ))}
      </div>

      {/* Exclude by Category */}
      <div>
        <strong>Exclude by Category</strong>
        {!pubCategories && <p>Loading categories...</p>}
        {pubCategories && pubCategories.length === 0 && <p>No categories found.</p>}
        {pubCategories && pubCategories.map(term => (
          <CheckboxControl
            key={term.id}
            label={term.name}
            checked={taxQueryExclude.includes(term.id)}
            onChange={() => toggleTerm(term.id, 'taxQueryExclude', taxQueryExclude)}
          />
        ))}
      </div>
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