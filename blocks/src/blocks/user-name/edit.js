
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';


import './editor.scss';

export default function Edit({ attributes, setAttributes }) {
    const { element, linkToProfile } = attributes;
    
    const nameContent = linkToProfile ? (
        <a href="#" style={{ textDecoration: 'underline', color: 'inherit' }}>
            Jane Doe
        </a>
    ) : (
        'Jane Doe'
    );

    return (
        <>
            <InspectorControls>
                <PanelBody>
                    <SelectControl
                        label={__('Element', 'caes-hub')}
                        value={element}
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
                    <ToggleControl
                        label={__('Link to Profile', 'caes-hub')}
                        help={linkToProfile ? __('Name will link to user profile.', 'caes-hub') : __('Name will display without link.', 'caes-hub')}
                        checked={linkToProfile}
                        onChange={(val) => setAttributes({ linkToProfile: val })}
                    />
                </PanelBody>
            </InspectorControls>
            {element ? (
                <attributes.element {...useBlockProps()}>
                    {nameContent}
                </attributes.element>
            ) : (
                <p {...useBlockProps()}>
                    {nameContent}
                </p>
            )}
        </>
    );
}