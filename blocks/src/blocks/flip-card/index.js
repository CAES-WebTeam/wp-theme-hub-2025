/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * All files containing `style` keyword are bundled together. The code used
 * gets applied both to the front of your site and to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './style.scss';

/**
 * Internal dependencies
 */
import Edit from './edit';
import Save from './save';
import metadata from './block.json';

const flipCardIcon = (
	<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2 16c0 2.21 0 3.316.702 4.054q.169.178.37.327C3.908 21 5.16 21 7.667 21h.666c2.506 0 3.759 0 4.595-.62q.201-.147.37-.326C14 19.316 14 18.211 14 16c0-2.21 0-3.316-.702-4.054a3 3 0 0 0-.37-.327C12.092 11 10.84 11 8.333 11h-.666c-2.506 0-3.759 0-4.595.62a3 3 0 0 0-.37.326C2 12.684 2 13.789 2 16m8-8c0-2.21 0-3.316.702-4.054q.168-.178.37-.327C11.908 3 13.16 3 15.667 3h.666c2.506 0 3.759 0 4.595.62q.201.148.37.326C22 4.684 22 5.789 22 8c0 2.21 0 3.316-.702 4.054a3 3 0 0 1-.37.327c-.758.562-1.86.614-3.928.618M2 15h12m-4-8h12M2 9c0-3.317 2.683-6 6-6l-.857 1.714M22 15c0 3.317-2.683 6-6 6l.857-1.714" color="currentColor" /></svg>
);

/**
 * Every block starts by registering a new block type definition.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
registerBlockType(metadata.name, {
	/**
	 * @see ./edit.js
	 */
	icon: flipCardIcon,
	edit: Edit,
	save: Save
});
