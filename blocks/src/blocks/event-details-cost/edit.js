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
import { PanelBody, TextControl, SelectControl } from '@wordpress/components';

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
				<PanelBody title={__("Heading Font Size", "caes-hub")}>
					<TextControl
						label={__("Font Size", "caes-hub")}
						type="number"
						value={attributes.headingFontSize || ""}
						onChange={(val) => setAttributes({ headingFontSize: val })}
					/>
					<SelectControl
						label={__("Unit", "caes-hub")}
						value={attributes.headingFontUnit}
						options={[
							{ label: "px", value: "px" },
							{ label: "em", value: "em" },
							{ label: "rem", value: "rem" },
							{ label: "%", value: "%" },
						]}
						onChange={(val) => setAttributes({ headingFontUnit: val })}
					/>
				</PanelBody>
			</InspectorControls>
			<div {...useBlockProps()}>
				<h3 className="event-details-title" style={{
					fontSize: attributes.headingFontSize
						? `${attributes.headingFontSize}${attributes.headingFontUnit}`
						: undefined
				}}>Cost</h3>
				<div className="event-details-content">$20.50</div>
			</div>
		</>
	);
}
