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
import {
	useBlockProps,
	InspectorControls
} from '@wordpress/block-editor';
import {
	Panel,
	PanelBody,
	SelectControl
} from '@wordpress/components';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';

/**
 * Gutenberg 2.8 added the <ServerSideRender> block which enables rendering to 
 * take place on the server using PHP rather than in JavaScript.
 */
 import ServerSideRender from '@wordpress/server-side-render';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {WPElement} Element to render.
 */

const Edit = ({ attributes, setAttributes }) => {

	return (
		<>
			<InspectorControls>
				<Panel>
					<PanelBody>
						<SelectControl
							label={__("Login", "gutenberg-multi")}
							options={[
								{ label: 'Enabled', value: 'true' },
								{ label: 'Disabled', value: 'false' }
							]}
							value={attributes.login}
							onChange={(val) => setAttributes({ login: val })}
						/>
						<SelectControl
							label={__("Submit a Student Complaint Link", "gutenberg-multi")}
							options={[
								{ label: 'Include student complaint link', value: 'true' },
								{ label: 'Exclude student complaint link', value: 'false' }
							]}
							value={attributes.submitComplaintLink}
							onChange={(val) => setAttributes({ submitComplaintLink: val })}
						/>
					</PanelBody>
				</Panel>
			</InspectorControls>
			<div {...useBlockProps()}>
				<ServerSideRender
					block="caes-hub/uga-footer"
					attributes={attributes}
				/>
			</div>
		</>
	)
}

export default Edit