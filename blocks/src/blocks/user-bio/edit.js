import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import './editor.scss';

export default function Edit({ attributes, setAttributes }) {
    const { displayOption } = attributes;

    const onChangeDisplayOption = (newOption) => {
        setAttributes({ displayOption: newOption });
    };

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Display Settings', 'caes-hub')}>
                    <SelectControl
                        label={__('Display Option', 'caes-hub')}
                        value={displayOption}
                        options={[
                            { label: __('Biography', 'caes-hub'), value: 'bio' },
                            { label: __('Tagline', 'caes-hub'), value: 'tagline' }
                        ]}
                        onChange={onChangeDisplayOption}
                        help={__('Choose whether to display the user\'s biography or tagline.', 'caes-hub')}
                    />
                </PanelBody>
            </InspectorControls>
            
            <p {...useBlockProps()}>
                {displayOption === 'bio' 
                    ? __('User Biography', 'caes-hub')
                    : __('User Tagline', 'caes-hub')
                }
            </p>
        </>
    );
}