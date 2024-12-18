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
import { PanelBody, ToggleControl } from '@wordpress/components';

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
const Edit = ({ attributes, setAttributes }) => {
	return (
		<>
			<InspectorControls>
				<PanelBody>
					<ToggleControl
						label={__("Display heading", "caes-hub")}
						checked={attributes.heading}
						disabled={attributes.dateAsSnippet} // Heading toggle is locked if snippet is on
						onChange={(val) => {
							setAttributes({
								heading: val,
								dateAsSnippet: val ? attributes.dateAsSnippet : false,
							});
						}}
					/>
					<ToggleControl
						label={__("Display date", "caes-hub")}
						checked={attributes.showDate}
						disabled={attributes.dateAsSnippet} // Date toggle is locked if snippet is on
						onChange={(val) => setAttributes({ showDate: val })}
					/>
					<ToggleControl
						label={__("Display time", "caes-hub")}
						checked={attributes.showTime}
						disabled={attributes.dateAsSnippet} // Disable time when snippet is ON
						onChange={(val) => setAttributes({ showTime: val })}
					/>
					<ToggleControl
						label={__("Display date as snippet", "caes-hub")}
						checked={attributes.dateAsSnippet}
						onChange={(val) => {
							setAttributes({
								dateAsSnippet: val,
								heading: val ? true : attributes.heading, // Enforce heading ON when snippet is ON
								showDate: val ? true : attributes.showDate, // Enforce showDate ON when snippet is ON
								showTime: val ? false : attributes.showTime, // Reset showTime when snippet is ON
							});
						}}
					/>
				</PanelBody>
			</InspectorControls>
			<div {...useBlockProps()}>
				{/* Message when no information is displayed */}
				{(attributes.heading && !attributes.dateAsSnippet && !attributes.showDate && !attributes.showTime) ||
					(!attributes.heading && !attributes.dateAsSnippet && !attributes.showDate && !attributes.showTime) ? (
					<p className="event-details-message">
						<em>{__("Please turn on date, time, or date as snippet.", "caes-hub")}</em>
					</p>
				) : null}


				{/* Render heading if enabled and date or time is selected */}
				{attributes.heading && (attributes.showDate || attributes.showTime) && (
					<h3 className="event-details-title">
						{attributes.dateAsSnippet
							? "January 15, 2024"
							: `${attributes.showDate ? "Date" : ""}${attributes.showTime ? (attributes.showDate ? " & Time" : "Time") : ""}`}
					</h3>
				)}

				{/* Render content if snippet is OFF and showDate or showTime is ON */}
				{!attributes.dateAsSnippet && (attributes.showDate || attributes.showTime) && (
					<div className="event-details-content">
						{attributes.showDate && "January 15, 2024"}
						{attributes.showDate && attributes.showTime && <br />}
						{attributes.showTime && "10:00 AM - 2:00 PM"}
					</div>
				)}
			</div>

		</>
	);
};

export default Edit;



