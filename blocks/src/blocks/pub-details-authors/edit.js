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
import { PanelBody, ToggleControl, SelectControl, TextControl } from '@wordpress/components';

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
						label={__("Show heading", "caes-hub")}
						checked={attributes.showHeading}
						onChange={(val) => {
							setAttributes({
								showHeading: val
							});
						}}
					/>
					<TextControl
						label={__("Custom Heading", "caes-hub")}
						value={attributes.customHeading}
						onChange={(val) => {
							setAttributes({
								customHeading: val
							});
						}}
					/>
					<SelectControl
						label={__("Select type", "caes-hub")}
						value={attributes.type}
						options={[
							{ label: "Authors", value: "authors" },
							{ label: "Translators", value: "translators" },
							{ label: "Sources", value: "sources" },
						]}
						onChange={(val) => {
							setAttributes({
								type: val
							});
						}}
					/>
					<ToggleControl
						label={__("Display authors as snippet", "caes-hub")}
						checked={attributes.authorsAsSnippet}
						onChange={(val) => {
							setAttributes({
								authorsAsSnippet: val
							});
						}}
					/>

					{attributes.authorsAsSnippet && (
						<TextControl
							label={__("Prefix text before snippet", "caes-hub")}
							value={attributes.snippetPrefix}
							onChange={(val) => {
								setAttributes({
									snippetPrefix: val
								});
							}}
						/>
					)}
				</PanelBody>
			</InspectorControls>

			{attributes.authorsAsSnippet ? (
				<div {...useBlockProps()}>
					<p>
						{attributes.snippetPrefix && (
							<><span className="pub-authors-snippet-prefix">{attributes.snippetPrefix} </span><br/></>
						)}
						Jane Doe and John Arbuckle
					</p>
				</div>
			) : (

				// More expanded details
				<div {...useBlockProps()}>
					{attributes.showHeading && (
						<h2 className="pub-authors-heading is-style-caes-hub-section-heading has-x-large-font-size">
							{/* If custom heading is set, use that, otherwise use default */}
							{attributes.customHeading || (attributes.type === "translators" ? "Translators" : (attributes.type === "sources" ? "Sources" : "Authors"))}
						</h2>
					)}
					<div className="pub-author">
						<a className="pub-author-name" href="#">Jane Doe</a>
						<p className="pub-author-title">
							Associate Professor and Extension Plant Pathologist - landscape, garden, and organic fruit and vegetables, Plant Pathology
						</p>
					</div>
					<div className="pub-author">
						<a className="pub-author-name" href="#">John Arbuckle</a>
						<p className="pub-author-title">
							Professor and Extension Vegetable Disease Specialist, Plant Pathology
						</p>
					</div>
				</div>
			)}

		</>
	);
}
