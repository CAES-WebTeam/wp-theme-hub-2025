import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import './editor.scss';

export default function Edit({ attributes, setAttributes }) {
    const { linkTerms } = attributes;

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Areas of Expertise Settings', 'caes-hub')}>
                    <ToggleControl
                        label={__('Link terms to taxonomy archive', 'caes-hub')}
                        checked={linkTerms}
                        onChange={(value) => setAttributes({ linkTerms: value })}
                    />
                </PanelBody>
            </InspectorControls>
            <div {...useBlockProps({ className: 'person-areas-of-expertise' })}>
                <span className="person-expertise-term">{__('Biochemistry', 'caes-hub')}</span>
                <span className="person-expertise-term">{__('Cell Biology', 'caes-hub')}</span>
                <span className="person-expertise-term">{__('Plant Biology', 'caes-hub')}</span>
            </div>
        </>
    );
}
