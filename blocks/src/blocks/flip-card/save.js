import { InnerBlocks } from '@wordpress/block-editor';

/**
 * Save function for the Flip Card block
 * 
 * Since this block uses server-side rendering (render.php),
 * we return <InnerBlocks.Content /> to save the inner blocks
 * content to the database, which will then be processed by render.php
 */
export default function save() {
	return <InnerBlocks.Content />;
}