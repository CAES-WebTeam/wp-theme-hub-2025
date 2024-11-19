import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function save({ attributes }) {
	const { listStyle, tocHeading, showSubheadings, tocData, smoothScroll } = attributes;

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

	let toc = [];
	try {
		toc = JSON.parse(tocData);
	} catch (e) {
		console.error('Error parsing TOC data:', e);
	}

	return (
		<div className="wp-block-caes-hub-toc" {...useBlockProps.save()}>
			<RichText.Content tagName="h2" value={tocHeading} />
			{renderList(toc)}
		</div>
	);
}