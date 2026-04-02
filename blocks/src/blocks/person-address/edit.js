import { __ } from '@wordpress/i18n';
import {
    useBlockProps,
    InspectorControls,
    BlockControls,
    HeadingLevelDropdown,
    FontSizePicker,
    useSettings,
    getSpacingPresetCssVar,
} from '@wordpress/block-editor';
import { PanelBody, ToggleControl, SelectControl } from '@wordpress/components';
import './editor.scss';

export default function Edit({ attributes, setAttributes }) {
    const { addressType, showHeading, headingLevel, headingFontSize, contentFontSize, style } = attributes;
    const blockGap = style?.spacing?.blockGap;
    const [ fontSizes ] = useSettings( 'typography.fontSizes' );

    const TagName = `h${headingLevel}`;
    const headingLabel = addressType === 'shipping'
        ? __('Shipping Address', 'caes-hub')
        : __('Mailing Address', 'caes-hub');

    return (
        <>
            {showHeading && (
                <BlockControls group="block">
                    <HeadingLevelDropdown
                        value={headingLevel}
                        onChange={(value) => setAttributes({ headingLevel: value })}
                    />
                </BlockControls>
            )}
            <InspectorControls>
                <PanelBody title={__('Address Settings', 'caes-hub')}>
                    <SelectControl
                        label={__('Address type', 'caes-hub')}
                        value={addressType}
                        options={[
                            { label: __('Mailing', 'caes-hub'), value: 'mailing' },
                            { label: __('Shipping', 'caes-hub'), value: 'shipping' },
                        ]}
                        onChange={(value) => setAttributes({ addressType: value })}
                    />
                    <ToggleControl
                        label={__('Show heading', 'caes-hub')}
                        checked={showHeading}
                        onChange={(value) => setAttributes({ showHeading: value })}
                    />
                </PanelBody>
            </InspectorControls>
            {showHeading && (
                <InspectorControls>
                    <PanelBody title={__('Heading Size', 'caes-hub')}>
                        <SelectControl
                            label={__('Heading level', 'caes-hub')}
                            value={headingLevel}
                            options={[1,2,3,4,5,6].map((n) => ({ label: `H${n}`, value: n }))}
                            onChange={(value) => setAttributes({ headingLevel: parseInt(value) })}
                        />
                        <FontSizePicker
                            fontSizes={fontSizes}
                            value={headingFontSize}
                            onChange={(value) => setAttributes({ headingFontSize: value || '' })}
                            withReset
                            size="__unstable-large"
                        />
                    </PanelBody>
                </InspectorControls>
            )}
            <InspectorControls>
                <PanelBody title={__('Content Size', 'caes-hub')}>
                    <FontSizePicker
                        fontSizes={fontSizes}
                        value={contentFontSize}
                        onChange={(value) => setAttributes({ contentFontSize: value || '' })}
                        withReset
                        size="__unstable-large"
                    />
                </PanelBody>
            </InspectorControls>
            <div {...useBlockProps({ className: 'person-address', style: blockGap ? { gap: getSpacingPresetCssVar( blockGap ) } : {} })}>
                {showHeading && (
                    <TagName
                        className="wp-block-heading person-address__heading"
                        style={headingFontSize ? { fontSize: headingFontSize } : {}}
                    >
                        {headingLabel}
                    </TagName>
                )}
                <p
                    className="person-address__content"
                    style={contentFontSize ? { fontSize: contentFontSize } : {}}
                >
                    111 Riverbend Road<br />
                    Center for Applied Genetic Technologies, Room 249<br />
                    Athens, GA 30602
                </p>
            </div>
        </>
    );
}
