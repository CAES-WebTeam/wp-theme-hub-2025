/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, TextControl, CheckboxControl, SelectControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import { PanelColorSettings } from '@wordpress/block-editor';

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
	const { 
		showDateSort, 
		showPostTypeFilter, 
		showTopicFilter, 
		showAuthorFilter, 
		showLanguageFilter, 
		showHeading,
		hideSubmitButton,
		placeholderText,
		showButton,
		buttonText,
		buttonUrl,
		postTypes, 
		taxonomySlug, 
		headingColor, 
		headingAlignment, 
		customHeading, 
		resultsPageUrl 
	} = attributes;
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
				<PanelBody title={__('Results Page Settings', 'caes-hub')}>
					<TextControl
						label={__('Results Page URL', 'caes-hub')}
						value={resultsPageUrl}
						onChange={(value) => setAttributes({ resultsPageUrl: value })}
						help={__('Optional: Enter a URL to redirect search submissions to another page with the full search results. Leave blank to show results on the same page.', 'caes-hub')}
						placeholder={__('e.g., /search-results/', 'caes-hub')}
					/>
					{resultsPageUrl && (
						<p className="description" style={{ marginTop: '10px', padding: '10px', backgroundColor: '#f0f6fc', border: '1px solid #c3c4c7', borderRadius: '4px' }}>
							<strong>{__('How it works:', 'caes-hub')}</strong><br />
							{__('When specified, this block becomes a "search-only" form that redirects to the results page. The target page should also have this block configured with all desired filters enabled.', 'caes-hub')}
						</p>
					)}
				</PanelBody>
				<PanelBody title={__('Search Input Settings', 'caes-hub')}>
					<ToggleControl
						label={__('Hide Submit Button', 'caes-hub')}
						checked={hideSubmitButton}
						onChange={(value) => setAttributes({ hideSubmitButton: value })}
						help={
							hideSubmitButton
								? __('Submit button is hidden. Users press Return to search.', 'caes-hub')
								: __('Submit button is visible next to the search input.', 'caes-hub')
						}
					/>
					<TextControl
						label={__('Placeholder Text', 'caes-hub')}
						value={placeholderText}
						onChange={(value) => setAttributes({ placeholderText: value })}
						help={__('Leave blank to use default ("Search...").', 'caes-hub')}
						placeholder={__('Search...', 'caes-hub')}
					/>
				</PanelBody>
				<PanelBody title={__('Heading Settings', 'caes-hub')}>
					<ToggleControl
						label={__('Show Heading', 'caes-hub')}
						checked={showHeading}
						onChange={(value) => setAttributes({ showHeading: value })}
						help={
							showHeading
								? __('Heading will be displayed above the search form.', 'caes-hub')
								: __('Heading will be hidden.', 'caes-hub')
						}
					/>
					{showHeading && (
						<>
							<SelectControl
								label={__('Text Alignment', 'caes-hub')}
								value={headingAlignment}
								onChange={(value) => setAttributes({ headingAlignment: value })}
								options={[
									{ label: __('Left', 'caes-hub'), value: 'left' },
									{ label: __('Center', 'caes-hub'), value: 'center' },
									{ label: __('Right', 'caes-hub'), value: 'right' }
								]}
							/>
							<TextControl
								label={__('Custom Heading Text', 'caes-hub')}
								value={customHeading}
								onChange={(value) => setAttributes({ customHeading: value })}
								help={__('Leave blank to use default text ("Search" or "Search results for: [query]")', 'caes-hub')}
								placeholder={__('e.g., Search Expert Resources', 'caes-hub')}
							/>
						</>
					)}
				</PanelBody>
				{showHeading && (
					<PanelColorSettings
						title={__('Heading Color', 'caes-hub')}
						colorSettings={[
							{
								value: headingColor,
								onChange: (value) => setAttributes({ headingColor: value }),
								label: __('Text Color', 'caes-hub')
							}
						]}
					/>
				)}
				<PanelBody title={__('Button Settings', 'caes-hub')}>
					<ToggleControl
						label={__('Show Button', 'caes-hub')}
						checked={showButton}
						onChange={(value) => setAttributes({ showButton: value })}
						help={
							showButton
								? __('A custom button will be displayed next to the filters.', 'caes-hub')
								: __('No additional button will be shown.', 'caes-hub')
						}
					/>
					{showButton && (
						<>
							<TextControl
								label={__('Button Text', 'caes-hub')}
								value={buttonText}
								onChange={(value) => setAttributes({ buttonText: value })}
								help={__('Enter the text to display on the button.', 'caes-hub')}
								placeholder={__('e.g., Advanced Search', 'caes-hub')}
							/>
							<TextControl
								label={__('Button URL', 'caes-hub')}
								value={buttonUrl}
								onChange={(value) => setAttributes({ buttonUrl: value })}
								help={__('Enter the URL the button should link to.', 'caes-hub')}
								placeholder={__('e.g., /advanced-search/', 'caes-hub')}
							/>
						</>
					)}
				</PanelBody>
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
				{resultsPageUrl && (
					<p style={{ backgroundColor: '#f0f6fc', padding: '10px', border: '1px solid #c3c4c7', borderRadius: '4px', marginTop: '10px' }}>
						<strong>{__('Search-only mode:', 'caes-hub')}</strong> {__('Redirects to', 'caes-hub')} {resultsPageUrl}
					</p>
				)}
				{!showHeading && (
					<p style={{ backgroundColor: '#fff3cd', padding: '10px', border: '1px solid #ffd60a', borderRadius: '4px', marginTop: '10px' }}>
						<strong>{__('Note:', 'caes-hub')}</strong> {__('Heading is hidden', 'caes-hub')}
					</p>
				)}
				{showButton && (
					<p style={{ backgroundColor: '#d1ecf1', padding: '10px', border: '1px solid #bee5eb', borderRadius: '4px', marginTop: '10px' }}>
						<strong>{__('Button enabled:', 'caes-hub')}</strong> {buttonText || __('(No text set)', 'caes-hub')} → {buttonUrl || __('(No URL set)', 'caes-hub')}
					</p>
				)}
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