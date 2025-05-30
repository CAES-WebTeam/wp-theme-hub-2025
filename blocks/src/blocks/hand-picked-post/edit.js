// Components
import { __ } from '@wordpress/i18n';
import {
    useBlockProps,
    InspectorControls,
    InnerBlocks,
} from '@wordpress/block-editor';
import {
    PanelBody,
    FormTokenField,
    SelectControl,
    __experimentalNumberControl as NumberControl
} from '@wordpress/components';
import {
    useState,
} from '@wordpress/element';

// Import editor CSS
import './editor.scss';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @param {Object} props All props passed to this function.
 * @return {WPElement}   Element to render.
 */
export default function Edit({ attributes, setAttributes }) {

    const {
        postIds = [],
        postType,
        feedType,
    } = attributes;

    // Function to get posts based on selected post type
    function getPosts() {
        let options = [];
        const posts = wp.data.select('core').getEntityRecords('postType', postType, { per_page: -1 });
        if (null === posts) {
            return options;
        }
        posts.forEach((post) => {
            options.push({ value: post.id, label: post.title.rendered });
        });
        return options;
    }

    // Handle post type change
    const handlePostTypeChange = (value) => {
        setAttributes({ postType: value });
        // Reset the postId when post type changes
        setAttributes({ postId: 0 });
    };

    const [filteredOptions, setFilteredOptions] = useState(getPosts());

    const DEFAULT_TEMPLATE = [
        ['core/post-title', {}],
        ['core/post-excerpt', {}]
    ];

    const inspectorControls = (
        <>
            <InspectorControls>
                <PanelBody title={__('Featured Content Settings', 'hand-picked-post')}>
                    <SelectControl
                        label={__('Select Feed Type', 'hand-picked-post')}
                        value={feedType}
                        options={[
                            { label: __('Related Keywords', 'hand-picked-post'), value: 'related-keywords' },
                            { label: __('Hand Pick Posts', 'hand-picked-post'), value: 'hand-picked' }
                        ]}
                        onChange={(value) => {
                            const updates = { feedType: value };
                            if (value === 'related-keywords') {
                                updates.postId = 0; // Clear selected post
                            }
                            setAttributes(updates);
                        }}
                    />
                    {/* Post Type Selection Control */}
                    <SelectControl
                        label={__('Select Post Type', 'hand-picked-post')}
                        value={postType}
                        options={[
                            { label: __('Posts', 'hand-picked-post'), value: 'post' },
                            { label: __('Pages', 'hand-picked-post'), value: 'page' },
                            { label: __('Shorthand Stories', 'hand-picked-post'), value: 'shorthand_story' },
                            { label: __('Publications', 'hand-picked-post'), value: 'publication' },
                        ]}
                        onChange={handlePostTypeChange}
                    />
                    {/* Post Selection Control */}
                    {attributes.feedType === 'hand-picked' && (
                        <FormTokenField
                            label={__('Select Posts', 'hand-picked-post')}
                            value={postIds.map((id) => {
                                const post = getPosts().find((p) => p.value === id);
                                return post ? post.label : id;
                            })}
                            suggestions={getPosts().map((p) => p.label)}
                            onChange={(selectedLabels) => {
                                const allPosts = getPosts();
                                const selectedIds = selectedLabels
                                    .map((label) => {
                                        const match = allPosts.find((p) => p.label === label);
                                        return match ? match.value : null;
                                    })
                                    .filter((id) => id !== null);
                                setAttributes({ postIds: selectedIds });
                            }}
                        />
                    )}
                    {attributes.feedType === 'related-keywords' && (
                        <NumberControl
                            label={__('Number of Items', 'hand-picked-post')}
                            value={attributes.numberOfItems}
                            min={1}
                            onChange={(value) => setAttributes({ numberOfItems: parseInt(value) })}
                        />
                    )}
                </PanelBody>
            </InspectorControls>
        </>
    );

    return (
        <>
            {inspectorControls}
            <div {...useBlockProps()}>
                
                {feedType === 'hand-picked' && (!postIds || postIds.length === 0) && (
                    <p className="hand-picked-post-empty">
                        {__('Please select one or more posts from the sidebar.', 'hand-picked-post')}
                    </p>
                )}

                {feedType === 'hand-picked' && postIds && postIds.length > 0 && (
                    <InnerBlocks template={DEFAULT_TEMPLATE} />
                )}


                {feedType === 'related-keywords' && (
                    <>
                        <InnerBlocks template={DEFAULT_TEMPLATE} />
                    </>
                )}

            </div>
        </>
    );

}
