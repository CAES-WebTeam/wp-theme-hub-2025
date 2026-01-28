/**
 * Save function for the Reveal block.
 * Outputs InnerBlocks content; PHP render wraps with background container.
 */
import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

const Save = () => {
	return (
		<div { ...useBlockProps.save() }>
			<InnerBlocks.Content />
		</div>
	);
};

export default Save;
