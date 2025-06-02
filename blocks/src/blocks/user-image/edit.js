import { __ } from '@wordpress/i18n';

// import { useBlockProps } from '@wordpress/block-editor';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { Panel, PanelBody, ToggleControl } from '@wordpress/components';

import './editor.scss';

import catImage from '../../../../assets/images/cat-stock.jpg';

export default function Edit({ attributes, setAttributes }) {
    return (
        <>
            <InspectorControls>
                <Panel>
                    <PanelBody>
                        <ToggleControl
                            label="Mobile version"
                            checked={attributes.mobileVersion}
                            onChange={(value) => setAttributes({ mobileVersion: value })}
                        />
                    </PanelBody>
                </Panel>            
            </InspectorControls>
            <figure {...useBlockProps({ className: attributes.mobileVersion ? 'mobile-version' : '' })}>
                <img src={catImage} alt="Cat" />
            </figure>
        </>
    );
}
