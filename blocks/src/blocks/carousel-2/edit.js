import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
    PanelBody, FormTokenField, Spinner, ToggleControl,
    SelectControl, TextControl
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import ServerSideRender from '@wordpress/server-side-render';
import './editor.scss';

export default function Edit({ attributes, setAttributes }) {

    // State for available posts and fetching status
    const [availablePosts, setAvailablePosts] = useState([]);
    const [availableCategories, setAvailableCategories] = useState([]);
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

    // Fetch categories for filtering
    useEffect(() => {
        apiFetch({ path: `/wp/v2/categories?per_page=100` })
            .then((categories) => {
                setAvailableCategories(categories);
            });
    }, []);

    // Handle selecting posts
    const handlePostSelection = (tokens) => {
        const selectedIDs = tokens.map(token => {
            const post = availablePosts.find(post => post.title.rendered === token);
            return post ? post.id : null;
        }).filter(id => id !== null);

        setAttributes({ selectedPosts: selectedIDs });
    };

    return (
        <>
            <InspectorControls>
                <PanelBody>
                    <ToggleControl
                        label="Hand Select Posts"
                        help={
                            attributes.handSelectPosts
                                ? 'Hand select posts.'
                                : 'Filter posts by category.'
                        }
                        checked={attributes.handSelectPosts}
                        onChange={(val) => {
                            setAttributes({ handSelectPosts: val });
                        }}
                    />

                    {!attributes.handSelectPosts && (
                        <>
                            {/* Order By */}
                            <SelectControl
                                label="Order By"
                                value={attributes.orderBy}
                                options={[
                                    { label: 'Newest to Oldest', value: 'date_desc' },
                                    { label: 'Oldest to Newest', value: 'date_asc' },
                                    { label: 'A to Z', value: 'title_asc' },
                                    { label: 'Z to A', value: 'title_desc' }
                                ]}
                                onChange={(value) => setAttributes({ orderBy: value })}
                            />

                            {/* Filter by Categories */}
                            <FormTokenField
                                label="Filter by Categories"
                                value={availableCategories
                                    .filter(cat => attributes.categories.includes(cat.id))
                                    .map(cat => cat.name)}
                                suggestions={availableCategories.map(cat => cat.name)}
                                onChange={(tokens) => {
                                    const selectedIDs = tokens.map(token => {
                                        const category = availableCategories.find(cat => cat.name === token);
                                        return category ? category.id : null;
                                    }).filter(id => id !== null);
                                    setAttributes({ categories: selectedIDs });
                                }}
                            />

                            {/* Number of Posts */}
                            <TextControl
                                label="Number of Posts to Display"
                                type="number"
                                value={attributes.numberOfPosts}
                                min={1}
                                onChange={(value) => setAttributes({ numberOfPosts: parseInt(value, 10) })}
                            />
                        </>
                    )}

                    {attributes.handSelectPosts && (
                        <>
                            {isFetching ? (
                                <Spinner />
                            ) : (
                                <FormTokenField
                                    value={availablePosts
                                        .filter(post => attributes.selectedPosts.includes(post.id))
                                        .map(post => post.title.rendered)}
                                    suggestions={availablePosts.map(post => post.title.rendered)}
                                    onChange={handlePostSelection}
                                />
                            )}
                        </>
                    )}
                </PanelBody>
            </InspectorControls>

            <div {...useBlockProps()}>
                <div className="caes-hub-carousel-preview">
                    <h2>This is where the carousel will go.</h2>
                    <p>Future plans include making this preview look like what it does on the frontend.</p>
                    <h3>Your Settings:</h3>
                    <ul>
                        <li>{attributes.handSelectPosts ? 'Hand select posts.' : 'Filter posts by category.'}</li>
                        <li>Order By: {attributes.orderBy}</li>
                        <li>Number of Posts: {attributes.numberOfPosts}</li>
                        <li>Categories: {attributes.categories.length > 0 ? attributes.categories.join(', ') : 'None'}</li>
                        <li>Selected Posts: {attributes.selectedPosts.length > 0 ? attributes.selectedPosts.join(', ') : 'None'}</li>
                    </ul>
                </div>
            </div>
        </>
    );
}
