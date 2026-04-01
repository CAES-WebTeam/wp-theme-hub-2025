import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import './editor.scss';

export default function Edit({ attributes, setAttributes }) {
    const { linkTerms, showHeading } = attributes;

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Areas of Expertise Settings', 'caes-hub')}>
                    <ToggleControl
                        label={__('Show heading', 'caes-hub')}
                        checked={showHeading}
                        onChange={(value) => setAttributes({ showHeading: value })}
                    />
                    <ToggleControl
                        label={__('Link terms to taxonomy archive', 'caes-hub')}
                        checked={linkTerms}
                        onChange={(value) => setAttributes({ linkTerms: value })}
                    />
                </PanelBody>
            </InspectorControls>
            <div {...useBlockProps({ className: 'person-areas-of-expertise' })}>
                {showHeading && <h2 className="person-areas-of-expertise__heading">{__('Areas of expertise', 'caes-hub')}</h2>}
                <span className="person-expertise-term">{__('Biochemistry', 'caes-hub')}</span>
                <span className="person-expertise-term">{__('Cell Biology', 'caes-hub')}</span>
                <span className="person-expertise-term">{__('Plant Biology', 'caes-hub')}</span>
            </div>
        </>
    );
}
