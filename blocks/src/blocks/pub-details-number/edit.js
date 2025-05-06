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

import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';

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
export default function Edit({ attributes, setAttributes }) {
	return (
		<>
			<InspectorControls>
				<PanelBody>
					<ToggleControl
						label={__("Display as information block", "caes-hub")}
						checked={attributes.displayAsInfo}
						onChange={(val) => {
							setAttributes({
								displayAsInfo: val,
								showTooltip: val ? false : attributes.showTooltip,
								link: val ? "" : attributes.link,
							});
						}}
						help={__("Displays info about what the pub number means", "caes-hub")}
					/>
					<ToggleControl
						label={__("Show tooltip", "caes-hub")}
						checked={attributes.showTooltip}
						onChange={(val) => setAttributes({ showTooltip: val })}
						disabled={attributes.displayAsInfo}
						help={__("Show the tooltip on hover", "caes-hub")}
					/>
					<TextControl
						label={__("Link", "caes-hub")}
						value={attributes.link}
						onChange={(val) => setAttributes({ link: val })}
						disabled={attributes.displayAsInfo}
						help={__("URL to the explanation of the publication number", "caes-hub")}
					/>
				</PanelBody>
			</InspectorControls>
			<p {...useBlockProps()}>AB 1234</p>
		</>
	);
}
