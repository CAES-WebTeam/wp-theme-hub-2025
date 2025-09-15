import { registerBlockVariation } from '@wordpress/blocks';
import { addFilter } from '@wordpress/hooks';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, CheckboxControl, Spinner, ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

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
      taxQueryExcludePubs: [],
      orderByLatestUpdate: false,
      orderByLatestPublishDate: false, // --- ADDED: New attribute
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

const isPubsVariation = ({ attributes: { namespace } }) => namespace === publicationsVariation;

const PubVariationControls = ({ props: { attributes: { query }, setAttributes } }) => {
  const { taxQueryExcludePubs = [], orderByLatestUpdate, orderByLatestPublishDate } = query;

  const { terms, isLoading } = useSelect(select => {
    const { getEntityRecords } = select('core');
    const taxonomy = 'publication_category';
    const query = { per_page: -1 };
    return {
      terms: getEntityRecords('taxonomy', taxonomy, query),
      isLoading: !select('core').hasFinishedResolution('getEntityRecords', ['taxonomy', taxonomy, query]),
    };
  }, []);

  const toggleTerm = termId => {
    const newTaxQuery = taxQueryExcludePubs.includes(termId)
      ? taxQueryExcludePubs.filter(id => id !== termId)
      : [...taxQueryExcludePubs, termId];
    setAttributes({ query: { ...query, taxQueryExcludePubs: newTaxQuery } });
  };

  // --- MODIFIED: Mutual exclusivity logic for toggles ---
  const handleUpdateToggle = (isChecked) => {
    setAttributes({
      query: {
        ...query,
        orderByLatestUpdate: isChecked,
        orderByLatestPublishDate: isChecked ? false : orderByLatestPublishDate, // If checked, turn the other off
      }
    });
  };

  const handlePublishToggle = (isChecked) => {
    setAttributes({
      query: {
        ...query,
        orderByLatestPublishDate: isChecked,
        orderByLatestUpdate: isChecked ? false : orderByLatestUpdate, // If checked, turn the other off
      }
    });
  };

  return (
    <>
      <PanelBody title="Publication Filters" initialOpen={true}>
        <ToggleControl
          label="Sort by latest update"
          checked={orderByLatestUpdate}
          onChange={handleUpdateToggle}
        />
        <ToggleControl
          label="Sort by latest publish date"
          checked={orderByLatestPublishDate}
          onChange={handlePublishToggle}
        />
        <hr />
        <strong>Exclude Publication Types</strong>
        {isLoading && <Spinner />}
        {!isLoading && terms && terms.length === 0 && <p>No categories found.</p>}
        {!isLoading && terms && terms.map(term => (
          <CheckboxControl
            key={term.id}
            label={term.name}
            checked={taxQueryExcludePubs.includes(term.id)}
            onChange={() => toggleTerm(term.id)}
          />
        ))}
      </PanelBody>
    </>
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

addFilter('editor.BlockEdit', 'core/query/with-pub-controls', withPubVariationControls);

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
        ['core/post-title']
      ],
    ]
  ]
});

/** Stories Query Block Variation - END */