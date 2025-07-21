import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import './editor.scss';

export default function Edit({ attributes, setAttributes }) {
    const { displayOption, enableFallback } = attributes;

    const onChangeDisplayOption = (newOption) => {
        setAttributes({ displayOption: newOption });
    };

    const onChangeFallback = (newValue) => {
        setAttributes({ enableFallback: newValue });
    };

    const getFallbackText = () => {
        if (!enableFallback) return '';
        return displayOption === 'bio' 
            ? __(' (fallback to tagline)', 'caes-hub')
            : __(' (fallback to biography)', 'caes-hub');
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
                    
                    <ToggleControl
                        label={__('Enable Fallback', 'caes-hub')}
                        checked={enableFallback}
                        onChange={onChangeFallback}
                        help={__('If enabled, will fall back to the other option if the primary choice is empty.', 'caes-hub')}
                    />
                </PanelBody>
            </InspectorControls>
            
            <p {...useBlockProps()}>
                {displayOption === 'bio' 
                    ? __('User Biography', 'caes-hub')
                    : __('User Tagline', 'caes-hub')
                }
                {getFallbackText()}
            </p>
        </>
    );
}