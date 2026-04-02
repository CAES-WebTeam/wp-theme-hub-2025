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

const PREVIEW_WORKS = [
    { title: 'Single Cell Multi-Omics Reveals Rare Biosynthetic Cell Types in the Medicinal Tree Camptotheca acuminata.', year: '2025', journal: 'Plant Biotechnology Journal' },
    { title: 'Discovery of iridoid cyclase completes the iridoid pathway in asterids.', year: '2025', journal: 'Nature Plants' },
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
                <PanelBody title={__('Scholarly Works Settings', 'caes-hub')}>
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
            <div {...useBlockProps({ className: 'person-scholarly-works', style: blockGap ? { gap: getSpacingPresetCssVar( blockGap ) } : {} })}>
                {showHeading && (
                    <TagName
                        className="wp-block-heading person-scholarly-works__heading"
                        style={headingFontSize ? { fontSize: headingFontSize } : {}}
                    >
                        {__('Scholarly Works', 'caes-hub')}
                    </TagName>
                )}
                <ul
                    className="wp-block-list person-scholarly-works__list"
                    style={itemFontSize ? { fontSize: itemFontSize } : {}}
                >
                    {PREVIEW_WORKS.map((w) => (
                        <li key={w.title}>
                            <strong>{w.title}</strong> ({w.year}). {w.journal}.
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}
