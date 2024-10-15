import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, FormTokenField, Spinner, ToggleControl } from '@wordpress/components'; // Import FormTokenField
import apiFetch from '@wordpress/api-fetch';
import ServerSideRender from '@wordpress/server-side-render';
import './editor.scss';

export default function Edit({ attributes, setAttributes }) {
    // Destructure attributes
    const { handSelectPosts, selectedPosts = [] } = attributes;

    // State for available posts and fetching status
    const [availablePosts, setAvailablePosts] = useState([]);
    const [isFetching, setIsFetching] = useState(false);

    // Fetch posts when the component mounts
    useEffect(() => {
        setIsFetching(true);
        apiFetch({ path: `/wp/v2/posts?per_page=100` }) // Fetching a large batch of posts
            .then((posts) => {
                setAvailablePosts(posts);
                setIsFetching(false);
            });
    }, []);

    // Handle selecting posts
    const handlePostSelection = (tokens) => {
        // Get the IDs of the selected posts
        const selectedIDs = tokens.map(token => {
            const post = availablePosts.find(post => post.title.rendered === token);
            return post ? post.id : null;
        }).filter(id => id !== null); // Filter out any null values
        
        setAttributes({ selectedPosts: selectedIDs });
    };

    return (
        <>
            <InspectorControls>
                <PanelBody>
                    <ToggleControl
                        label="Hand Select Posts"
                        help={
                            handSelectPosts
                                ? 'Hand select posts.'
                                : 'Filter posts by taxonomy.'
                        }
                        checked={handSelectPosts}
                        onChange={(val) => {
                            setAttributes({ handSelectPosts: val });
                        }}
                    />
                    {handSelectPosts && (
                        <>
                            {isFetching ? (
                                <Spinner />
                            ) : (
                                <FormTokenField
                                    value={availablePosts
                                        .filter(post => selectedPosts.includes(post.id))
                                        .map(post => post.title.rendered)}
                                    suggestions={availablePosts.map(post => post.title.rendered)}
                                    onChange={handlePostSelection} // Handle selection
                                    __nextHasNoMarginBottom
                                />
                            )}
                        </>
                    )}
                </PanelBody>
            </InspectorControls>
            <div {...useBlockProps()}>
                <ServerSideRender
                    block="caes-hub/carousel"
                    attributes={attributes}
                />
            </div>
        </>
    );
}
