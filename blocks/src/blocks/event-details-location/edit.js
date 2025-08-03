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
import { PanelBody, ToggleControl, TextControl, SelectControl, RadioControl } from '@wordpress/components';

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
	const { displayMode, asSnippet, headingFontSize, headingFontUnit } = attributes;
	
	// Generate inline style for preview
	const titleStyle = {
		fontSize: headingFontSize
			? `${headingFontSize}${headingFontUnit}`
			: undefined,
	};

	// Helper function to get preview content based on display mode
	const getPreviewContent = () => {
		if (asSnippet) {
			switch (displayMode) {
				case 'physical':
					return (
						<h3 className="event-details-title" style={titleStyle}>
							Room 101
						</h3>
					);
				case 'online':
					return (
						<h3 className="event-details-title" style={titleStyle}>
							Virtual Event
						</h3>
					);
				case 'both':
					return (
						<h3 className="event-details-title" style={titleStyle}>
							Room 101 & Online
						</h3>
					);
				default: // auto
					return (
						<h3 className="event-details-title" style={titleStyle}>
							Room 101 & Online
						</h3>
					);
			}
		} else {
			// Full display mode
			switch (displayMode) {
				case 'physical':
					return (
						<>
							<h3 className="event-details-title" style={titleStyle}>
								Location
							</h3>
							<div className="event-details-content">
								Room 101 <br />
								Located on the second floor, near the elevator.
							</div>
						</>
					);
				case 'online':
					return (
						<>
							<h3 className="event-details-title" style={titleStyle}>
								Online Location
							</h3>
							<div className="event-details-content">
								<p><a href="https://youtube.com">https://youtube.com</a></p>
							</div>
						</>
					);
				case 'both':
					return (
						<>
							<h3 className="event-details-title" style={titleStyle}>
								Location
							</h3>
							<div className="event-details-content">
								<p><em>This event is in-person and online.</em></p>
								<strong>Physical Location:</strong><br />
								Room 101 <br />
								Located on the second floor, near the elevator.<br /><br />
								<strong>Online Location:</strong><br />
								<a href="https://youtube.com">https://youtube.com</a>
							</div>
						</>
					);
				default: // auto
					return (
						<>
							<h3 className="event-details-title" style={titleStyle}>
								Location
							</h3>
							<div className="event-details-content">
								<p><em>This event is in-person and online.</em></p>
								<strong>Physical Location:</strong><br />
								Room 101 <br />
								Located on the second floor, near the elevator.<br /><br />
								<strong>Online Location:</strong><br />
								<a href="https://youtube.com">https://youtube.com</a>
							</div>
						</>
					);
			}
		}
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={__("Display Settings", "caes-hub")}>
					<RadioControl
						label={__("Location Display Mode", "caes-hub")}
						help={__("Choose what location information to display", "caes-hub")}
						selected={displayMode}
						options={[
							{ label: __("Auto (show available data)", "caes-hub"), value: "auto" },
							{ label: __("Physical location only", "caes-hub"), value: "physical" },
							{ label: __("Online location only", "caes-hub"), value: "online" },
							{ label: __("Both physical and online", "caes-hub"), value: "both" },
						]}
						onChange={(val) => setAttributes({ displayMode: val })}
					/>
					<ToggleControl
						label={__("Display as snippet", "caes-hub")}
						help={__("Show condensed version without details", "caes-hub")}
						checked={asSnippet}
						onChange={(val) => setAttributes({ asSnippet: val })}
					/>
				</PanelBody>
				<PanelBody title={__("Heading Font Size", "caes-hub")}>
					<TextControl
						label={__("Font Size", "caes-hub")}
						type="number"
						value={headingFontSize || ""}
						onChange={(val) => setAttributes({ headingFontSize: val })}
					/>
					<SelectControl
						label={__("Unit", "caes-hub")}
						value={headingFontUnit}
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
				{getPreviewContent()}
			</div>
		</>
	);
}

export default Edit;