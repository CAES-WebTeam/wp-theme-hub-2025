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
// import { useBlockProps } from '@wordpress/block-editor';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { Panel, PanelBody, SelectControl } from '@wordpress/components';

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
                    <SelectControl
                        label={__('Element', 'caes-hub')}
                        value={attributes.element}
                        options={[
                            { label: 'Heading 1', value: 'h1' },
                            { label: 'Heading 2', value: 'h2' },
                            { label: 'Heading 3', value: 'h3' },
                            { label: 'Heading 4', value: 'h4' },
                            { label: 'Heading 5', value: 'h5' },
                            { label: 'Heading 6', value: 'h6' },
                            { label: 'Paragraph', value: 'p' }
                        ]}
                        onChange={(val) => setAttributes({ element: val })}
                    />
                </PanelBody>
            </InspectorControls>
                {attributes.element ? (
                    <attributes.element {...useBlockProps()}>
                        Jane Doe
                    </attributes.element>
                ) : (
                    <p {...useBlockProps()}>
                        Jane Doe
                    </p>
                )}
        </>
    );
}
