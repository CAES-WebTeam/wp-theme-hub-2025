import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls, RichText } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import './editor.scss';

const { useSelect } = wp.data;

function slugify(text) {
	return text
		.toString()
		.trim()
		.toLowerCase()
		.replace(/\s+/g, '-')        // Replace spaces with dashes
		.replace(/[^\w\-]+/g, '')   // Remove all non-word characters
		.replace(/\-\-+/g, '-')     // Replace multiple dashes with a single dash
		.replace(/^-+/, '')         // Trim dashes from the start
		.replace(/-+$/, '');        // Trim dashes from the end
}

export default function Edit({ attributes, setAttributes }) {
	const { listStyle, tocHeading, showSubheadings, tocData, smoothScroll } = attributes;

	const content = useSelect(select => select('core/editor').getEditedPostContent());

	const headings = Array.from(new DOMParser().parseFromString(content, 'text/html').querySelectorAll('h2, h3, h4, h5, h6'))
	.filter(heading => heading.textContent.trim() !== tocHeading?.trim()) // Exclude TOC heading
	.map((heading, index, array) => {
		const text = heading.textContent || '';
		let id = heading.id || slugify(text); // Use existing ID or generate slugified ID

		// Ensure unique ID if duplicates exist
		const existingIds = array.slice(0, index).map(h => h.id || slugify(h.textContent || '')); // Track previous IDs
		let uniqueId = id;
		let counter = 1;

		// Append counter if the ID is not unique
		while (existingIds.includes(uniqueId)) {
			uniqueId = `${id}-${counter++}`;
		}

		// Update the heading ID to the unique ID
		heading.id = uniqueId;

		return {
			id: uniqueId, // Use the unique ID
			text,
			level: parseInt(heading.tagName.slice(1)),
		};
	});

	const updateTOCData = (headings) => {
		const toc = [];
		const stack = [];

		headings.forEach((heading) => {
			while (stack.length > 0 && stack[stack.length - 1].level >= heading.level) {
				stack.pop();
			}

			const parent = stack.length > 0 ? stack[stack.length - 1].children : toc;
			const newItem = { ...heading, children: [] };
			parent.push(newItem);
			stack.push(newItem);
		});

		const filteredToc = toc.filter(item => item.level === 2);
		setAttributes({ tocData: JSON.stringify(filteredToc) });
	};

	const renderTOC = (headings) => {
		const toc = [];
		const stack = [];

		headings.forEach((heading) => {
			while (stack.length > 0 && stack[stack.length - 1].level >= heading.level) {
				stack.pop();
			}

			const parent = stack.length > 0 ? stack[stack.length - 1].children : toc;
			const newItem = { ...heading, children: [] };
			parent.push(newItem);
			stack.push(newItem);
		});

		const renderList = (items) => {
			const ListTag = listStyle === 'ol' ? 'ol' : 'ul';
			return (
				<ListTag className={listStyle === 'none' ? 'list-none' : ''}>
					{items.map((item) => (
						<li key={item.id}>
							<a href={`#${item.id}`} data-smooth-scroll={smoothScroll ? 'true' : 'false'}>{item.text}</a>
							{showSubheadings && item.children.length > 0 && renderList(item.children)}
						</li>
					))}
				</ListTag>
			);
		};

		return renderList(toc.filter(item => item.level === 2));
	};

	updateTOCData(headings);

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Table of Contents Settings')}>
					<SelectControl
						label={__('List Style')}
						value={listStyle}
						options={[
							{ label: 'Unordered List', value: 'ul' },
							{ label: 'Ordered List', value: 'ol' },
							{ label: 'No Bullets', value: 'none' },
						]}
						onChange={(newListStyle) => setAttributes({ listStyle: newListStyle })}
					/>
					<ToggleControl
						label={__('Show Subheadings')}
						checked={showSubheadings}
						onChange={(newShowSubheadings) => setAttributes({ showSubheadings: newShowSubheadings })}
						help={showSubheadings
							? __('Subheadings (H3 and below) are currently visible in the table of contents.')
							: __('Only H2 headings are currently visible in the table of contents.')}
					/>
					<ToggleControl
						label={__('Smooth Scrolling')}
						checked={smoothScroll}
						onChange={(newSmoothScroll) => setAttributes({ smoothScroll: newSmoothScroll })}
						help={smoothScroll
							? __('Table of contents links will scroll smoothly to headings when clicked.')
							: __('Smooth scrolling is turned off.')}
					/>
				</PanelBody>
			</InspectorControls>
			<div className="toc-block" {...useBlockProps()}>
				<RichText
					tagName="h2"
					value={tocHeading}
					onChange={(newHeading) => setAttributes({ tocHeading: newHeading })}
					placeholder={__('Table of Contents')}
				/>
				{renderTOC(headings)}
			</div>
		</>
	);
}
