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

const PREVIEW_AWARDS = [
    { title: 'Award Title Goes Here, Institution Name Here', date: '2023' },
    { title: 'Award Title Goes Here, Institution Name Here 2', date: '2021' },
    { title: 'Award Title Goes Here, Institution Name Here 3', date: '2017' },
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
                <PanelBody title={__('Awards Settings', 'caes-hub')}>
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
            <div {...useBlockProps({ className: 'person-awards', style: blockGap ? { gap: getSpacingPresetCssVar( blockGap ) } : {} })}>
                {showHeading && (
                    <TagName
                        className="wp-block-heading person-awards__heading"
                        style={headingFontSize ? { fontSize: headingFontSize } : {}}
                    >
                        {__('Awards and Honors', 'caes-hub')}
                    </TagName>
                )}
                <ul
                    className="wp-block-list person-awards__list"
                    style={itemFontSize ? { fontSize: itemFontSize } : {}}
                >
                    {PREVIEW_AWARDS.map((a) => (
                        <li key={a.title}>
                            <span className="person-awards__item-title">{a.title}</span>
                            {a.date && <span className="person-awards__item-date">{a.date}</span>}
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}
