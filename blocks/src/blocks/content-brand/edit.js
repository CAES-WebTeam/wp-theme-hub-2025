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
import { PanelBody, SelectControl, __experimentalUnitControl as UnitControl } from '@wordpress/components';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';

// import ServerSideRender from '@wordpress/server-side-render';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {Element} Element to render.
 */
const Edit = ({ attributes, setAttributes }) => {
	const isLight = attributes.version === 'light';

	const units = [
		{ value: 'px', label: 'px', default: 0 },
		{ value: '%', label: '%', default: 10 },
		{ value: 'em', label: 'em', default: 0 },
		{ value: 'rem', label: 'rem', default: 0 },
	];

	return (
		<>
			<InspectorControls>
				<PanelBody>
					<SelectControl
						label={__("Version", "caes-hub")}
						options={[
							{ label: 'Light', value: 'light' },
							{ label: 'Dark', value: 'dark' }
						]}
						help={__("Logo should be easily visible over the background color.", "caes-hub")}
						value={attributes.version}
						onChange={(val) => setAttributes({ version: val })}
					/>
					<UnitControl
						label={__("Custom width", "caes-hub")}
						value={attributes.customWidth}
						onChange={(val) => setAttributes({ customWidth: val })}
						units={units}
					/>
				</PanelBody>
			</InspectorControls>
			<div 
				{...useBlockProps()} 
				style={{ 
					width: attributes.customWidth || 'auto', 
					height: '50px', 
					backgroundColor: isLight ? '#eaeaea' : '#474747', 
					display: 'flex', 
					alignItems: 'center', 
					justifyContent: 'center', 
					color: isLight ? '#000' : '#fff',
					fontSize: '14px',
					fontWeight: 'bold',
					padding: '0.5rem'
				}}
			>
				{__("Logo Placeholder", "caes-hub")}
			</div>
		</>
	);
}
export default Edit;