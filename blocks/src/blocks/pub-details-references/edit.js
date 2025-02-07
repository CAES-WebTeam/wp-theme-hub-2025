/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {Element} Element to render.
 */
export default function Edit(attributes) {
	return (
		<div {...useBlockProps()}>
			<h2 className="wp-block-heading is-style-caes-hub-full-underline">References</h2>
			<p className="reference"><span className="reference-title">Example title of a reference.</span> <span className="reference-text">Example text of a reference.</span> <a href="https://www.caes.uga.edu" target="outside">https://www.caes.uga.edu</a></p>
			<p className="reference"><span className="reference-title">Example title of a reference.</span> <span className="reference-text">Example text of a reference.</span> <a href="https://www.caes.uga.edu" target="outside">https://www.caes.uga.edu</a></p>			
		</div>
	);
}
