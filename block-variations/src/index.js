import { registerBlockVariation } from '@wordpress/blocks';
import { addFilter } from '@wordpress/hooks';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, CheckboxControl, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

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


// This section adds the Language filter specifically to the Publications variation
const isPubsVariation = (props) => {
  const { attributes: { namespace } } = props;
  return namespace && namespace === publicationsVariation;
};

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
        ['core/post-title'],
        ['core/post-date'],
        ['core/post-excerpt']
      ],
    ]
  ]
});

/** Stories Query Block Variation - END */


/** GENERIC TAXONOMY EXCLUSION CONTROLS - START */

/**
 * A component that renders exclusion filters for all taxonomies of a given post type.
 */
const TaxonomyExclusionFilters = ({ postType, attributes, setAttributes }) => {
    // Get all taxonomies registered to the current post type
    const taxonomies = useSelect(
        (select) => select('core').getTaxonomies({ type: postType, per_page: -1 }),
        [postType]
    );

    if (!taxonomies || taxonomies.length === 0) {
        return null; // Don't render anything if no taxonomies are found
    }

    // Render a panel for each taxonomy
    return (
        <>
            {taxonomies.map((taxonomy) => (
                <TaxonomyTermSelector
                    key={taxonomy.slug}
                    taxonomy={taxonomy}
                    attributes={attributes}
                    setAttributes={setAttributes}
                />
            ))}
        </>
    );
};

/**
 * A component that fetches and displays a checklist of terms for a single taxonomy.
 */
const TaxonomyTermSelector = ({ taxonomy, attributes, setAttributes }) => {
    const { query } = attributes;
    const { taxQueryExclude = {} } = query;
    const excludedTerms = taxQueryExclude[taxonomy.slug] || [];

    // Fetch all terms for the current taxonomy
    const { terms, isLoading } = useSelect(
        (select) => ({
            terms: select('core').getEntityRecords('taxonomy', taxonomy.slug, { per_page: -1 }),
            isLoading: !select('core').hasFinishedResolution('getEntityRecords', ['taxonomy', taxonomy.slug, { per_page: -1 }]),
        }),
        [taxonomy.slug]
    );

    // Handler to update the block's attributes when a checkbox is toggled
    const toggleTerm = (termId) => {
        const newExcludedTerms = excludedTerms.includes(termId)
            ? excludedTerms.filter((id) => id !== termId)
            : [...excludedTerms, termId];

        const newTaxQueryExclude = {
            ...taxQueryExclude,
            [taxonomy.slug]: newExcludedTerms,
        };

        // Clean up empty arrays from the object
        if (newExcludedTerms.length === 0) {
            delete newTaxQueryExclude[taxonomy.slug];
        }

        setAttributes({
            query: { ...query, taxQueryExclude: newTaxQueryExclude },
        });
    };

    if (!terms && !isLoading) {
        return null; // Don't show panel if there are no terms
    }

    return (
        <PanelBody title={`${taxonomy.name} - Exclusion Filter`}>
            {isLoading && <Spinner />}
            {!isLoading && terms && terms.length > 0 && (
                terms.map((term) => (
                    <CheckboxControl
                        key={term.id}
                        label={term.name}
                        checked={excludedTerms.includes(term.id)}
                        onChange={() => toggleTerm(term.id)}
                    />
                ))
            )}
        </PanelBody>
    );
};


/**
 * Higher-Order Component to add the taxonomy exclusion controls to the Query block's inspector.
 */
const withTaxonomyExclusionControls = (BlockEdit) => (props) => {
    const { name, attributes } = props;

    // Only apply to the core Query block
    if (name !== 'core/query') {
        return <BlockEdit {...props} />;
    }

    const postType = attributes.query.postType;

    return (
        <>
            <BlockEdit {...props} />
            <InspectorControls>
                <TaxonomyExclusionFilters
                    postType={postType}
                    attributes={attributes}
                    setAttributes={props.setAttributes}
                />
            </InspectorControls>
        </>
    );
};

// Apply the filter to the block editor
addFilter(
    'editor.BlockEdit',
    'my-plugin/with-taxonomy-exclusion-controls',
    withTaxonomyExclusionControls
);

/** GENERIC TAXONOMY EXCLUSION CONTROLS - END */