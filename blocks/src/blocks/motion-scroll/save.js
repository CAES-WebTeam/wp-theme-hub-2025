/**
 * Motion Scroll Block Save (Dynamic render via PHP)
 */
import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

export default function save() {
	return (
		<InnerBlocks.Content />
	);
}
