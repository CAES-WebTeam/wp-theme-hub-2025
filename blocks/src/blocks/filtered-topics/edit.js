import { 
    InspectorControls, 
    useBlockProps 
} from '@wordpress/block-editor';
import { 
    PanelBody, 
    ToggleControl, 
    TextControl,
    Spinner
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

export default function Edit({ attributes, setAttributes }) {
    const {
        showHierarchy,
        showPostCounts,
        displayAsDropdown,
        showHeading,
        customHeading,
        filterByContext,
        emptyMessage
    } = attributes;

    const blockProps = useBlockProps({
        className: 'caes-hub-filtered-topics'
    });

    // Get topics for preview
    const { topics, isLoading } = useSelect((select) => {
        const { getEntityRecords, isResolving } = select(coreStore);
        
        return {
            topics: getEntityRecords('taxonomy', 'topics', { 
                hide_empty: true,
                per_page: 10 
            }),
            isLoading: isResolving('getEntityRecords', ['taxonomy', 'topics', { 
                hide_empty: true,
                per_page: 10 
            }])
        };
    }, []);

    // Render preview of topics
    const renderTopicsPreview = () => {
        if (isLoading) {
            return (
                <div className="topics-loading">
                    <Spinner />
                    <p>{__('Loading topics...', 'caes-hub')}</p>
                </div>
            );
        }

        if (!topics || topics.length === 0) {
            return (
                <p className="topics-empty-message">
                    {emptyMessage}
                </p>
            );
        }

        const previewTopics = topics.slice(0, 5); // Show only first 5 for preview

        if (displayAsDropdown) {
            return (
                <div className="topics-dropdown-wrapper">
                    <select className="topics-dropdown" disabled>
                        <option>Select {customHeading}</option>
                        {previewTopics.map(topic => (
                            <option key={topic.id}>
                                {topic.name}
                                {showPostCounts && ` (${topic.count})`}
                            </option>
                        ))}
                    </select>
                </div>
            );
        }

        return (
            <ul className={`topics-list${showHierarchy ? ' topics-hierarchical' : ''}`}>
                {previewTopics.map(topic => (
                    <li key={topic.id} className={`topic-item topic-item-${topic.id}`}>
                        <a href="#" onClick={(e) => e.preventDefault()}>
                            {topic.name}
                        </a>
                        {showPostCounts && (
                            <span className="post-count">({topic.count})</span>
                        )}
                    </li>
                ))}
                {topics.length > 5 && (
                    <li className="topic-item-preview-notice">
                        <em>{__('... and %d more topics', 'caes-hub').replace('%d', topics.length - 5)}</em>
                    </li>
                )}
            </ul>
        );
    };

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Display Settings', 'caes-hub')} initialOpen={true}>
                    <ToggleControl
                        label={__('Filter by current section', 'caes-hub')}
                        help={__('Only show topics that have content in the current section (publications, events, etc.)', 'caes-hub')}
                        checked={filterByContext}
                        onChange={(value) => setAttributes({ filterByContext: value })}
                    />
                    
                    <ToggleControl
                        label={__('Display as dropdown', 'caes-hub')}
                        checked={displayAsDropdown}
                        onChange={(value) => setAttributes({ displayAsDropdown: value })}
                    />
                    
                    {!displayAsDropdown && (
                        <ToggleControl
                            label={__('Show hierarchy', 'caes-hub')}
                            help={__('Display topics in hierarchical structure with parent-child relationships', 'caes-hub')}
                            checked={showHierarchy}
                            onChange={(value) => setAttributes({ showHierarchy: value })}
                        />
                    )}
                    
                    <ToggleControl
                        label={__('Show post counts', 'caes-hub')}
                        checked={showPostCounts}
                        onChange={(value) => setAttributes({ showPostCounts: value })}
                    />
                </PanelBody>
                
                <PanelBody title={__('Heading Settings', 'caes-hub')} initialOpen={false}>
                    <ToggleControl
                        label={__('Show heading', 'caes-hub')}
                        checked={showHeading}
                        onChange={(value) => setAttributes({ showHeading: value })}
                    />
                    
                    {showHeading && (
                        <TextControl
                            label={__('Custom heading text', 'caes-hub')}
                            value={customHeading}
                            onChange={(value) => setAttributes({ customHeading: value })}
                            placeholder={__('Topics', 'caes-hub')}
                        />
                    )}
                </PanelBody>
                
                <PanelBody title={__('Messages', 'caes-hub')} initialOpen={false}>
                    <TextControl
                        label={__('Empty state message', 'caes-hub')}
                        help={__('Message to show when no topics are found', 'caes-hub')}
                        value={emptyMessage}
                        onChange={(value) => setAttributes({ emptyMessage: value })}
                        placeholder={__('No topics found for this section.', 'caes-hub')}
                    />
                </PanelBody>
            </InspectorControls>

            <div {...blockProps}>
                {showHeading && customHeading && (
                    <h3 className="topics-heading">{customHeading}</h3>
                )}
                
                <div className="topics-preview-wrapper">
                    <div className="topics-preview-label">
                        <small>
                            <em>
                                {__('Block Preview:', 'caes-hub')} {filterByContext ? __('Will filter by current section on frontend', 'caes-hub') : __('Showing all topics', 'caes-hub')}
                            </em>
                        </small>
                    </div>
                    {renderTopicsPreview()}
                </div>
            </div>
        </>
    );
}