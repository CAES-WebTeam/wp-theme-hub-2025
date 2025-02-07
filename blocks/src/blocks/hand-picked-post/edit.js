// Components
import { __ } from '@wordpress/i18n';
import {
    useBlockProps,
    InspectorControls,
    InnerBlocks,
} from '@wordpress/block-editor';
import {
    PanelBody,
    ComboboxControl,
    SelectControl,
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
export default function Edit(props) {
    const {
        attributes,
    } = props;

    const {
        postId,
        postType,
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
        props.setAttributes({ postType: value });
        // Reset the postId when post type changes
        props.setAttributes({ postId: 0 });
    };

    const [filteredOptions, setFilteredOptions] = useState(getPosts());

    const DEFAULT_TEMPLATE = [
        [ 'core/post-title', {} ],
        [ 'core/post-excerpt', {} ]
    ];

    const inspectorControls = (
        <>
            <InspectorControls>
                <PanelBody title={__('Featured Content Settings', 'hand-picked-post')}>
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
                    <ComboboxControl
                        label={__('Select Post', 'hand-picked-post')}
                        value={postId}
                        onChange={(id) => props.setAttributes({ postId: parseInt(id) })}
                        options={getPosts()}
                        onFilterValueChange={(inputValue) =>
                            setFilteredOptions(
                                getPosts().filter((option) =>
                                    option.label
                                        .toLowerCase()
                                        .startsWith(inputValue.toLowerCase())
                                )
                            )
                        }
                    />
                </PanelBody>
            </InspectorControls>
        </>
    );

    if (0 === postId) {
        return (
            <>
                {inspectorControls}
                <div {...useBlockProps()}>
                    <p className="hand-picked-post-empty">{__(
                        'Please click this block and select a post from the right side.',
                        'hand-picked-post'
                    )
                    }</p>
                </div>
            </>
        );
    }

    return (
        <>
            {inspectorControls}
            <div {...useBlockProps()}>
                <InnerBlocks 
                    template={DEFAULT_TEMPLATE}
                />
            </div>
        </>
    );
}
