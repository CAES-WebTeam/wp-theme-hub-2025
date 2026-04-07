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

const PREVIEW_DEGREES = [
    { name: 'Doctor of Philosophy', fieldOfStudy: 'Horticultural Science', institution: 'Michigan State University', year: '1999' },
    { name: 'Master of Science', fieldOfStudy: 'Forestry', institution: 'University of Florence', year: '1993' },
];

export default function Edit({ attributes, setAttributes }) {
    const { showHeading, headingLevel, headingFontSize, itemFontSize, style } = attributes;
    const blockGap = style?.spacing?.blockGap;
    const [ fontSizes ] = useSettings( 'typography.fontSizes' );

    const TagName = `h${headingLevel}`;

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
                <PanelBody title={__('Education Settings', 'caes-hub')}>
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
                <PanelBody title={__('Item Size', 'caes-hub')}>
                    <FontSizePicker
                        fontSizes={fontSizes}
                        value={itemFontSize}
                        onChange={(value) => setAttributes({ itemFontSize: value || '' })}
                        withReset
                        size="__unstable-large"
                    />
                </PanelBody>
            </InspectorControls>
            <div {...useBlockProps({ className: 'person-education', style: blockGap ? { gap: getSpacingPresetCssVar( blockGap ) } : {} })}>
                {showHeading && (
                    <TagName
                        className="wp-block-heading person-education__heading"
                        style={headingFontSize ? { fontSize: headingFontSize } : {}}
                    >
                        {__('Education', 'caes-hub')}
                    </TagName>
                )}
                <div
                    className="person-education__list"
                    style={itemFontSize ? { fontSize: itemFontSize } : {}}
                >
                    {PREVIEW_DEGREES.map((d) => (
                        <div key={d.name + d.institution} className="person-education__item">
                            <p className="person-education__item-title">
                                <strong>{[d.name, d.fieldOfStudy].filter(Boolean).join(', ')}</strong>
                            </p>
                            <p className="person-education__item-institution">
                                {d.institution}{d.year ? ` (${d.year})` : ''}
                            </p>
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}
