/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, TextControl, CheckboxControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import './editor.scss';

/**
 * The edit function describes the structure of your block in the editor.
 * This can be thought of as the render method for the client-side.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/block-edit-save/#edit
 *
 * @param {Object}   props               Properties passed to the function.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to set block attributes.
 *
 * @return {WPElement} Element to render.
 */
export default function Edit({ attributes, setAttributes }) {
	const { showDateSort, showPostTypeFilter, showTopicFilter, showAuthorFilter, showLanguageFilter, postTypes, taxonomySlug } = attributes;
	const blockProps = useBlockProps();

	// State to hold available post types for the checkbox list
	const [availablePostTypes, setAvailablePostTypes] = useState([]);

	// Check if publications post type is selected
	const isPublicationsSelected = postTypes.includes('publications');

	// Fetch all public post types using @wordpress/data
	const fetchedPostTypes = useSelect((select) => {
		const { getPostTypes } = select('core');
		const postTypes = getPostTypes({ per_page: -1 }); // Fetch all post types

		if (!postTypes) {
			return [];
		}

		// Filter out built-in post types that are not typically searchable or useful for filtering
		return postTypes.filter(
			(postType) =>
				postType.viewable &&
				postType.slug !== 'attachment' &&
				postType.slug !== 'wp_block' &&
				postType.slug !== 'wp_template' &&
				postType.slug !== 'wp_template_part' &&
				postType.slug !== 'wp_navigation' &&
				postType.slug !== 'wp_global_styles'
		);
	}, []);

	useEffect(() => {
		if (fetchedPostTypes.length > 0) {
			setAvailablePostTypes(fetchedPostTypes);
		}
	}, [fetchedPostTypes]);

	return (
		<div {...blockProps}>
			<InspectorControls>
				<PanelBody title={__('Search Filter Settings', 'caes-hub')}>
					<ToggleControl
						label={__('Show Date Sorting', 'caes-hub')}
						checked={showDateSort}
						onChange={(value) => setAttributes({ showDateSort: value })}
						help={
							showDateSort
								? __('Date sorting dropdown will be visible.', 'caes-hub')
								: __('Date sorting dropdown will be hidden.', 'caes-hub')
						}
					/>
					<ToggleControl
						label={__('Show Post Type Filter', 'caes-hub')}
						checked={showPostTypeFilter}
						onChange={(value) => setAttributes({ showPostTypeFilter: value })}
						help={
							showPostTypeFilter
								? __('Post type filter dropdown will be visible.', 'caes-hub')
								: __('Post type filter dropdown will be hidden.', 'caes-hub')
						}
					/>
					{showPostTypeFilter && availablePostTypes.length > 0 && (
						<div style={{ marginTop: '15px', borderTop: '1px solid #eee', paddingTop: '15px' }}>
							<p style={{ fontWeight: 'bold' }}>{__('Select Post Types to Filter:', 'caes-hub')}</p>
							{availablePostTypes.map((postType) => (
								<CheckboxControl
									key={postType.slug}
									label={postType.labels.singular_name || postType.slug}
									checked={postTypes.includes(postType.slug)}
									onChange={(isChecked) => {
										const newPostTypes = isChecked
											? [...postTypes, postType.slug]
											: postTypes.filter((slug) => slug !== postType.slug);
										setAttributes({ postTypes: newPostTypes });
									}}
								/>
							))}
							<p className="description">
								{__('Selected post types will appear in the filter dropdown.', 'caes-hub')}
							</p>
						</div>
					)}
					<ToggleControl
						label={__('Show Topics Taxonomy Filter', 'caes-hub')}
						checked={showTopicFilter}
						onChange={(value) => setAttributes({ showTopicFilter: value })}
						help={
							showTopicFilter
								? __('Topics taxonomy filter (checkboxes) will be visible.', 'caes-hub')
								: __('Topics taxonomy filter (checkboxes) will be hidden.', 'caes-hub')
						}
					/>
					{showTopicFilter && (
						<TextControl
							label={__('Custom Taxonomy Slug for Topics', 'caes-hub')}
							value={taxonomySlug}
							onChange={(value) => setAttributes({ taxonomySlug: value })}
							help={__(
								'Enter the slug of your custom taxonomy (e.g., "topics").',
								'caes-hub'
							)}
						/>
					)}
					<ToggleControl
						label={__('Show Author Filter', 'caes-hub')}
						checked={showAuthorFilter}
						onChange={(value) => setAttributes({ showAuthorFilter: value })}
						help={
							showAuthorFilter
								? __('Author filter dropdown will be visible.', 'caes-hub')
								: __('Author filter dropdown will be hidden.', 'caes-hub')
						}
					/>
					{isPublicationsSelected && (
						<ToggleControl
							label={__('Show Language Filter', 'caes-hub')}
							checked={showLanguageFilter}
							onChange={(value) => setAttributes({ showLanguageFilter: value })}
							help={
								showLanguageFilter
									? __('Language filter (checkboxes) will be visible for publications. Uses ACF custom field "language".', 'caes-hub')
									: __('Language filter (checkboxes) will be hidden.', 'caes-hub')
							}
						/>
					)}
				</PanelBody>
			</InspectorControls>
			<div className="caes-hub-relevanssi-search-filters-editor">
				<p>
					{__('Relevanssi Search Filters Block', 'caes-hub')}
				</p>
				<p>
					{__('Configure sorting and filtering options in the block settings sidebar.', 'caes-hub')}
				</p>
				{showDateSort && <p> - {__('Date Sorting Enabled', 'caes-hub')}</p>}
				{showPostTypeFilter && <p> - {__('Post Type Filter Enabled', 'caes-hub')}</p>}
				{showTopicFilter && <p> - {__('Topics Filter Enabled (Checkboxes, Taxonomy: ', 'caes-hub')}{taxonomySlug})</p>}
				{showAuthorFilter && <p> - {__('Author Filter Enabled', 'caes-hub')}</p>}
				{isPublicationsSelected && showLanguageFilter && (
					<p> - {__('Language Filter Enabled (Checkboxes, ACF Field: language)', 'caes-hub')}</p>
				)}
			</div>
		</div>
	);
}