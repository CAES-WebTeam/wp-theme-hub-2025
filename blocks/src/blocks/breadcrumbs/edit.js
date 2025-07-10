import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	ToggleControl,
	RangeControl,
	Notice,
} from '@wordpress/components';

export default function Edit({ attributes, setAttributes }) {
	const { showHome, homeText, maxDepth } = attributes;

	const blockProps = useBlockProps();

	// Mock breadcrumb data for preview
	const mockBreadcrumbs = [
		...(showHome ? [{ title: homeText, url: '#', isHome: true }] : []),
		{ title: 'Blog', url: '#' },
		{ title: 'Category Name', url: '#' },
		{ title: 'Current Post', url: null, isCurrent: true },
	];

	// Apply maxDepth if set
	const displayBreadcrumbs = maxDepth > 0 
		? mockBreadcrumbs.slice(-maxDepth) 
		: mockBreadcrumbs;

	const renderBreadcrumbItem = (item, index) => {
		return (
			<span key={index} className="breadcrumb-item">
				{item.url ? (
					<a 
						href={item.url}
						className={item.isHome ? 'breadcrumb-home' : 'breadcrumb-link'}
						onClick={(e) => e.preventDefault()}
					>
						{item.title}
					</a>
				) : (
					<span 
						className="breadcrumb-current"
						aria-current="page"
					>
						{item.title}
					</span>
				)}
			</span>
		);
	};

	return (
		<>
		  <InspectorControls>
			<PanelBody 
			  title={__('Breadcrumb Settings', 'your-textdomain')}
			  initialOpen={true}
			>
			  <ToggleControl
				label={__('Show Home Link', 'your-textdomain')}
				checked={showHome}
				onChange={(value) => setAttributes({ showHome: value })}
				help={__('Include a link to the homepage in breadcrumbs', 'your-textdomain')}
			  />
			  
			  {showHome && (
				<TextControl
				  label={__('Home Text', 'your-textdomain')}
				  value={homeText}
				  onChange={(value) => setAttributes({ homeText: value })}
				  help={__('Text to display for the home link', 'your-textdomain')}
				/>
			  )}
			  
			  <RangeControl
				label={__('Max Depth', 'your-textdomain')}
				value={maxDepth}
				onChange={(value) => setAttributes({ maxDepth: value })}
				min={0}
				max={10}
				help={__('Maximum number of breadcrumb items to show (0 = unlimited)', 'your-textdomain')}
			  />
			</PanelBody>
		  </InspectorControls>
		  
		  <nav 
			{...blockProps}
			className={`${blockProps.className} breadcrumb-navigation`}
			aria-label={__('Breadcrumb Navigation', 'your-textdomain')}
		  >
			<ol className="breadcrumb-list">
			  {displayBreadcrumbs.map((item, index) => (
				<li key={index} className="breadcrumb-list-item">
				  {renderBreadcrumbItem(item, index)}
				</li>
			  ))}
			</ol>
		  </nav>
		</>
	  );
}