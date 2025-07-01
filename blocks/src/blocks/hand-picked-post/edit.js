// Components
import { __ } from '@wordpress/i18n';
import {
    useBlockProps,
    InspectorControls,
    InnerBlocks,
    BlockControls
} from '@wordpress/block-editor';
import {
    PanelBody,
    FormTokenField,
    CheckboxControl,
    __experimentalNumberControl as NumberControl,
    Spinner,
    RadioControl,
    ToolbarGroup,
    ToolbarButton,
    RangeControl,
    __experimentalToggleGroupControl as ToggleGroupControl,
    __experimentalToggleGroupControlOption as ToggleGroupControlOption,
    SelectControl
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

// Import editor CSS
import './editor.scss';

// Preset spacing classes and labels
const SPACING_CLASSES = [
    '', // None (no gap)
    '--wp--preset--spacing--20',
    '--wp--preset--spacing--30',
    '--wp--preset--spacing--40',
    '--wp--preset--spacing--50',
    '--wp--preset--spacing--60',
    '--wp--preset--spacing--70',
    '--wp--preset--spacing--80',
];

const SPACING_LABELS = [
    'None (no gap)',
    '2X-Small',
    'X-Small',
    'Small',
    'Medium',
    'Large',
    'X-Large',
    '2X-Large',
];

export default function Edit({ attributes, setAttributes }) {
    const {
        postIds = [],
        postType = ['post'],
        feedType = 'related-topics',
        numberOfItems = 5,
        customGapStep = 0,
        displayLayout = 'list',
        columns = 3,
        gridItemPosition = 'manual',
        gridAutoColumnWidth = 12,
        gridAutoColumnUnit = 'rem'
    } = attributes;

    // Normalize postType to array
    const selectedPostTypes = Array.isArray(postType) ? postType : [postType];

    const handlePostTypeToggle = (type) => {
        const updated = selectedPostTypes.includes(type)
            ? selectedPostTypes.filter((t) => t !== type)
            : [...selectedPostTypes, type];
        setAttributes({ postType: updated, postIds: [] });
    };

    const postTypeOptions = [
        { value: 'post', label: 'Posts', restBase: 'posts' },
        { value: 'page', label: 'Pages', restBase: 'pages' },
        { value: 'shorthand_story', label: 'Shorthand Stories', restBase: 'shorthand_story' },
        { value: 'publications', label: 'Publications', restBase: 'publications' },
    ];

    const [availablePosts, setAvailablePosts] = useState([]);
    const [isLoading, setIsLoading] = useState(false);

    useEffect(() => {
        if (!selectedPostTypes.length) return;

        setIsLoading(true);

        Promise.all(
            selectedPostTypes.map((type) => {
                const restBase = postTypeOptions.find((opt) => opt.value === type)?.restBase || type;
                return apiFetch({ path: `/wp/v2/${restBase}?per_page=20&_fields=id,title,slug` })
                    .then((posts) =>
                        posts.map((post) => ({
                            id: post.id,
                            label: post.title.rendered || `(${type} #${post.id})`,
                            postType: type,
                        }))
                    )
                    .catch(() => []); // Return empty array on error
            })
        )
            .then((results) => {
                setAvailablePosts(results.flat());
                setIsLoading(false);
            })
            .catch(() => {
                setAvailablePosts([]);
                setIsLoading(false);
            });
    }, [selectedPostTypes.sort().join(',')]); // Watch for exact changes

    const selectedPostLabels = postIds.reduce((labels, id) => {
        const match = availablePosts.find((p) => p.id === id);
        if (match) {
            labels.push(match.label);
        }
        return labels;
    }, []);

    const postSuggestions = availablePosts.map((p) => p.label);

    const DEFAULT_TEMPLATE = [
        ['core/post-title', {}],
        ['core/post-excerpt', {}],
    ];

    // Generate class names based on attributes
    const baseClass = displayLayout === 'grid'
        ? `hand-picked-post-grid columns-${columns}`
        : 'hand-picked-post-list';

    const spacingClass = customGapStep > 0
        ? `gap-${SPACING_CLASSES[customGapStep].replace(/^--/, '').replace(/--/g, '-')}`
        : '';

    const combinedClassName = `${baseClass} ${spacingClass}`.trim();

    const blockProps = useBlockProps();


    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Featured Content Settings', 'hand-picked-post')}>
                    <RadioControl
                        label={__('Feed Type', 'hand-picked-post')}
                        selected={feedType}
                        options={[
                            { label: __('Related Topics', 'hand-picked-post'), value: 'related-topics' },
                            { label: __('Hand Pick Posts', 'hand-picked-post'), value: 'hand-picked' },
                        ]}
                        onChange={(value) => setAttributes({ feedType: value, postIds: [] })}
                    />

                    <PanelBody title={__('Select Post Types', 'hand-picked-post')} initialOpen={true}>
                        {postTypeOptions.map(({ value, label }) => (
                            <CheckboxControl
                                key={value}
                                label={label}
                                checked={selectedPostTypes.includes(value)}
                                onChange={() => handlePostTypeToggle(value)}
                            />
                        ))}
                    </PanelBody>

                    {feedType === 'hand-picked' && selectedPostTypes.length > 0 && (
                        <>
                            {isLoading && <Spinner />}
                            {!isLoading && (
                                <FormTokenField
                                    label={__('Select Posts', 'hand-picked-post')}
                                    value={selectedPostLabels}
                                    suggestions={postSuggestions}
                                    onChange={(selectedLabels) => {
                                        const selectedIds = selectedLabels
                                            .map((label) => {
                                                const match = availablePosts.find((p) => p.label === label);
                                                return match ? match.id : null;
                                            })
                                            .filter((id) => id !== null);
                                        setAttributes({ postIds: selectedIds });
                                    }}
                                />
                            )}
                        </>
                    )}

                    {feedType === 'related-topics' && (
                        <NumberControl
                            label={__('Number of Items', 'hand-picked-post')}
                            value={numberOfItems}
                            min={1}
                            onChange={(value) => setAttributes({ numberOfItems: parseInt(value, 10) })}
                        />
                    )}

                    {displayLayout === 'grid' && (
                        <>
                            <ToggleGroupControl
                                label={__('Grid Item Position', 'hand-picked-post')}
                                value={gridItemPosition}
                                onChange={(value) => setAttributes({ gridItemPosition: value })}
                                isBlock
                            >
                                <ToggleGroupControlOption value="auto" label="Automatic" />
                                <ToggleGroupControlOption value="manual" label="Manual" />
                            </ToggleGroupControl>
                        </>
                    )}

                    {displayLayout === 'grid' && gridItemPosition === 'auto' && (
                        <>
                            <NumberControl
                                label={__('Auto Column Width', 'hand-picked-post')}
                                value={gridAutoColumnWidth}
                                onChange={(value) => setAttributes({ gridAutoColumnWidth: parseFloat(value) })}
                                min={1}
                                step={1}
                            />
                            <SelectControl
                                label={__('Auto Column Unit', 'hand-picked-post')}
                                value={gridAutoColumnUnit}
                                onChange={(value) => setAttributes({ gridAutoColumnUnit: value })}
                                options={[
                                    { value: 'rem', label: 'rem' },
                                    { value: 'px', label: 'px' },
                                    { value: '%', label: '%' },
                                ]} 
                            />
                        </>
                    )}

                    {displayLayout === 'grid' && gridItemPosition === 'manual' && (
                        <>
                            <RangeControl
                                label={__('Number of Columns', 'hand-picked-post')}
                                value={columns}
                                onChange={(value) => setAttributes({ columns: value })}
                                min={1}
                                max={16}
                            />
                            <RangeControl
                                label={__('Gap between items', 'hand-picked-post')}
                                value={customGapStep}
                                onChange={(value) => setAttributes({ customGapStep: value })}
                                min={0}
                                max={SPACING_CLASSES.length - 1}
                                step={1}
                                help={SPACING_LABELS[customGapStep] ? SPACING_LABELS[customGapStep] : 'No gap'}
                            />
                        </>
                    )}
                </PanelBody>
            </InspectorControls>

            <BlockControls>
                <ToolbarGroup>
                    <ToolbarButton
                        icon="list-view"
                        label={__('List View', 'hand-picked-post')}
                        isPressed={displayLayout === 'list'}
                        onClick={() => setAttributes({ displayLayout: 'list' })}
                    />
                    <ToolbarButton
                        icon="grid-view"
                        label={__('Grid View', 'hand-picked-post')}
                        isPressed={displayLayout === 'grid'}
                        onClick={() => setAttributes({ displayLayout: 'grid' })}
                    />
                </ToolbarGroup>
            </BlockControls>

            <div {...blockProps}>
                <div className={combinedClassName}>
                    {feedType === 'hand-picked' && (!postIds || postIds.length === 0) && (
                        <p className="hand-picked-post-empty">
                            {__('Please select one or more posts from the sidebar.', 'hand-picked-post')}
                        </p>
                    )}

                    {((feedType === 'hand-picked' && postIds && postIds.length > 0) || feedType === 'related-topics') && (
                        <InnerBlocks
                            template={DEFAULT_TEMPLATE}
                            templateLock={false}
                            renderAppender={InnerBlocks.ButtonBlockAppender}
                        />
                    )}
                </div>
            </div>
        </>
    );
}