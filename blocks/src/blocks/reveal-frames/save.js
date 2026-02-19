import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

const Save = ( { attributes } ) => {
	const { frameIndex } = attributes;
	
	const blockProps = useBlockProps.save( {
		className: 'reveal-frame-content',
		'data-frame-index': frameIndex,
	} );

	return (
		<div { ...blockProps }>
			<InnerBlocks.Content />
		</div>
	);
};

export default Save;