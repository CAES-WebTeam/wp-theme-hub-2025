import { __ } from '@wordpress/i18n';
import { useBlockProps, InnerBlocks, useInnerBlocksProps } from '@wordpress/block-editor';

const Edit = ( { attributes, clientId } ) => {
	const { frameLabel } = attributes;
	
	const blockProps = useBlockProps( {
		className: 'reveal-frames-editor',
		'data-frame-label': frameLabel,
	} );

	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		templateLock: false,
		renderAppender: InnerBlocks.ButtonBlockAppender,
	} );

	return (
		<div { ...innerBlocksProps }>
			<div className="reveal-frames-label">
				{ frameLabel || __( 'Frame Content', 'caes-reveal' ) }
			</div>
			{ innerBlocksProps.children }
		</div>
	);
};

export default Edit;