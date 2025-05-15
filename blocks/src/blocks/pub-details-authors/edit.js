import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, SelectControl, TextControl } from '@wordpress/components';
import './editor.scss';

export default function Edit({ attributes, setAttributes }) {
	const {
		showHeading,
		customHeading,
		snippetPrefix,
		snippetPrefixPosition,
		snippetPrefixShown,
		type,
		grid,
		displayVersion
	} = attributes;

	// Checking if the block is compact style
	const blockProps = useBlockProps();
	const className = blockProps.className || '';
	const isCompact = className.includes('is-style-caes-hub-compact');

	// Default heading
	const defaultHeading = (() => {
		if (isCompact) {
			return {
				authors: "Meet the Authors",
				translators: "Meet the Translators",
				sources: "Meet the Experts"
			}[type] || "Meet the Authors";
		} else {
			return {
				authors: "Authors",
				translators: "Translators",
				sources: "Expert Sources"
			}[type] || "Authors";
		}
	})();

	const headingText = customHeading || defaultHeading;
	const gridClass = grid ? 'pub-authors-grid' : '';

	return (
		<>
			<InspectorControls>
				<PanelBody>
					<SelectControl
						label={__("Display version", "caes-hub")}
						value={displayVersion}
						options={[
							{ label: "Names only (all authors on one line)", value: "names-only" },
							{ label: "Name and title on one line", value: "names-and-titles" },
							{ label: "Name on one line, title below", value: "name-and-title-below" }
						]}
						onChange={(val) => {
							setAttributes({
								displayVersion: val,
								grid: (val === "name-and-title-below") ? grid : false // turn off grid unless version supports it
							});
						}}
					/>
					<SelectControl
						label={__("Select type", "caes-hub")}
						value={type}
						options={[
							{ label: "Authors", value: "authors" },
							{ label: "Translators", value: "translators" },
							{ label: "Sources", value: "sources" },
						]}
						onChange={(val) => setAttributes({ type: val })}
					/>
					<ToggleControl
						label={__("Display authors in grid", "caes-hub")}
						checked={grid}
						onChange={(val) => setAttributes({ grid: val })}
						disabled={displayVersion !== "name-and-title-below"}
					/>
					<ToggleControl
						label={__("Display prefix text", "caes-hub")}
						checked={snippetPrefixShown}
						onChange={(val) => setAttributes({ snippetPrefixShown: val })}
					/>
					{snippetPrefixShown && (
						<>
							<SelectControl
								label={__("Prefix text position", "caes-hub")}
								value={snippetPrefixPosition}
								options={[
									{ label: "Above author names", value: "above" },
									{ label: "Same line as author names", value: "same-line" }
								]}
								onChange={(val) => setAttributes({ snippetPrefixPosition: val })}
							/>
							<TextControl
								label={__("Prefix text", "caes-hub")}
								value={snippetPrefix}
								onChange={(val) => setAttributes({ snippetPrefix: val })}
							/>
						</>
					)}
					<ToggleControl
						label={__("Show heading", "caes-hub")}
						checked={showHeading}
						onChange={(val) => setAttributes({ showHeading: val })}
					/>
					{showHeading && (
						<TextControl
							label={__("Custom Heading", "caes-hub")}
							value={customHeading}
							onChange={(val) => setAttributes({ customHeading: val })}
						/>
					)}
				</PanelBody>
			</InspectorControls>

			<div {...useBlockProps()}>
				{showHeading && (
					<h2 className={isCompact ? 'pub-authors-heading' : 'pub-authors-heading is-style-caes-hub-section-heading has-x-large-font-size'}>
						{headingText}
					</h2>
				)}

				{snippetPrefixShown && snippetPrefixPosition === "above" && (
					<p className="pub-authors-snippet-prefix">{snippetPrefix}</p>
				)}

				<div className={`pub-authors-wrap ${gridClass}`}>
					{displayVersion === "names-only" && (
						<p className="pub-authors-snippet">
							{snippetPrefixShown && snippetPrefixPosition === "same-line" && `${snippetPrefix} `}Jane Doe, John Arbuckle, and Garfield
						</p>
					)}

					{displayVersion === "names-and-titles" && (
						<>
							<p className="pub-author">
								<a className="pub-author-name" href="#">Jane Doe</a>, <span className="pub-author-title">Associate Professor and Extension Plant Pathologist – landscape, garden, and organic fruit and vegetables, Plant Pathology</span>
							</p>
							<p className="pub-author">
								<a className="pub-author-name" href="#">John Arbuckle</a>, <span className="pub-author-title">Professor and Extension Vegetable Disease Specialist, Plant Pathology</span>
							</p>
						</>
					)}

					{displayVersion === "name-and-title-below" && (
						<>
							<div className="pub-author">
								<a className="pub-author-name" href="#">Jane Doe</a>
								<p className="pub-author-title">
									Associate Professor and Extension Plant Pathologist – landscape, garden, and organic fruit and vegetables, Plant Pathology
								</p>
							</div>
							<div className="pub-author">
								<a className="pub-author-name" href="#">John Arbuckle</a>
								<p className="pub-author-title">
									Professor and Extension Vegetable Disease Specialist, Plant Pathology
								</p>
							</div>
						</>
					)}
				</div>
			</div>
		</>
	);
}
