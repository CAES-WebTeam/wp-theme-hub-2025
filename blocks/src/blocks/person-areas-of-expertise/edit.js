import { __ } from '@wordpress/i18n';
import {
    useBlockProps,
    InspectorControls,
    BlockControls,
    HeadingLevelDropdown,
    FontSizePicker,
    useSettings,
} from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import './editor.scss';

export default function Edit({ attributes, setAttributes }) {
    const { linkTerms, showHeading, headingLevel, termFontSize } = attributes;
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
            <InspectorControls>
                <PanelBody title={__('Term Size', 'caes-hub')}>
                    <FontSizePicker
                        fontSizes={fontSizes}
                        value={termFontSize}
                        onChange={(value) => setAttributes({ termFontSize: value || '1.3rem' })}
                        withReset
                        size="__unstable-large"
                    />
                </PanelBody>
            </InspectorControls>
            <div {...useBlockProps({ className: 'person-areas-of-expertise' })}>
                {showHeading && (
                    <TagName className="wp-block-heading person-areas-of-expertise__heading">
                        {__('Areas of expertise', 'caes-hub')}
                    </TagName>
                )}
                <div className="person-areas-of-expertise__terms">
                    {['Biochemistry', 'Cell Biology', 'Plant Biology'].map((term) => (
                        <span
                            key={term}
                            className="person-expertise-term has-hedges-background-color has-background"
                            style={termFontSize ? { fontSize: termFontSize } : {}}
                        >
                            {term}
                        </span>
                    ))}
                </div>
            </div>
        </>
    );
}
