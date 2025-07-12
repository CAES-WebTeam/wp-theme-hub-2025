import { __ } from '@wordpress/i18n';

import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { Panel, PanelBody, ToggleControl, SelectControl, __experimentalUnitControl as UnitControl } from '@wordpress/components';

import './editor.scss';

import catImage from '../../../../assets/images/cat-stock.jpg';

export default function Edit({ attributes, setAttributes }) {
    const { mobileVersion, aspectRatio, width, widthUnit, fullHeight } = attributes;

    // Aspect ratio options matching WordPress image block
    const aspectRatioOptions = [
        { label: __('Original'), value: 'auto' },
        { label: __('Square'), value: '1' },
        { label: __('16:9'), value: '16/9' },
        { label: __('4:3'), value: '4/3' },
        { label: __('3:2'), value: '3/2' },
        { label: __('9:16'), value: '9/16' },
        { label: __('3:4'), value: '3/4' },
        { label: __('2:3'), value: '2/3' },
    ];

    // Generate inline styles for preview
    const imageStyles = {
        aspectRatio: aspectRatio !== 'auto' ? aspectRatio : undefined,
        width: fullHeight ? '100%' : `${width}${widthUnit}`,
        height: fullHeight ? '100%' : undefined,
        objectFit: (aspectRatio !== 'auto' || fullHeight) ? 'cover' : undefined,
    };

    const figureStyles = {
        width: fullHeight ? '100%' : `${width}${widthUnit}`,
        height: fullHeight ? '100%' : undefined,
    };

    return (
        <>
            <InspectorControls>
                <Panel>
                    <PanelBody title={__('Display Settings')}>
                        <ToggleControl
                            label={__('Mobile version')}
                            checked={mobileVersion}
                            onChange={(value) => setAttributes({ mobileVersion: value })}
                        />
                        
                        <ToggleControl
                            label={__('Full height')}
                            checked={fullHeight}
                            onChange={(value) => setAttributes({ fullHeight: value })}
                            help={__('Make the image container fill 100% of the parent height.')}
                        />
                        
                        <SelectControl
                            label={__('Aspect Ratio')}
                            value={aspectRatio}
                            options={aspectRatioOptions}
                            onChange={(value) => setAttributes({ aspectRatio: value })}
                            help={__('Set the aspect ratio for the image. "Original" maintains the natural proportions.')}
                        />
                        
                        {!fullHeight && (
                            <UnitControl
                                label={__('Width')}
                                value={`${width}${widthUnit}`}
                                onChange={(value) => {
                                    const parsed = parseFloat(value);
                                    const unit = value.replace(parsed.toString(), '') || '%';
                                    setAttributes({ 
                                        width: parsed || 100, 
                                        widthUnit: unit 
                                    });
                                }}
                                units={[
                                    { value: '%', label: '%', default: 100 },
                                    { value: 'px', label: 'px', default: 300 },
                                    { value: 'rem', label: 'rem', default: 20 },
                                ]}
                                help={__('Set the width of the image container.')}
                            />
                        )}
                    </PanelBody>
                </Panel>            
            </InspectorControls>
            <figure 
                {...useBlockProps({ 
                    className: mobileVersion ? 'mobile-version' : '',
                    style: figureStyles
                })}
            >
                <img 
                    src={catImage} 
                    alt="Cat" 
                    style={imageStyles}
                />
            </figure>
        </>
    );
}