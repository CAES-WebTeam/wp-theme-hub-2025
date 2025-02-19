import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls, RichText } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';

export default function Edit({ attributes, setAttributes }) {
    const { listStyle, tocHeading, showSubheadings } = attributes;
    const ListTag = listStyle === 'ol' ? 'ol' : 'ul';
    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Table of Contents Settings')}>
                    <SelectControl
                        label={__('List Style')}
                        value={listStyle}
                        options={[
                            { label: 'Unordered List', value: 'ul' },
                            { label: 'Ordered List', value: 'ol' },
                            { label: 'No Bullets', value: 'none' },
                        ]}
                        onChange={(newListStyle) => setAttributes({ listStyle: newListStyle })}
                    />
                    <ToggleControl
                        label={__('Show Subheadings')}
                        checked={showSubheadings}
                        onChange={(newShowSubheadings) => setAttributes({ showSubheadings: newShowSubheadings })}
                        help={showSubheadings
                            ? __('Subheadings (H3 and below) are currently visible in the table of contents.')
                            : __('Only H2 headings are currently visible in the table of contents.')}
                    />
                </PanelBody>
            </InspectorControls>
            <div className="toc-block" {...useBlockProps()}>
                <RichText
                    tagName="h2"
                    value={tocHeading}
                    onChange={(newHeading) => setAttributes({ tocHeading: newHeading })}
                    placeholder={__('Table of Contents')}
                />
                <ListTag className={listStyle === 'none' ? 'is-style-caes-hub-list-none' : ''}>
                    <li><a href="#">List item 1</a></li>
                    <li><a href="#">List item 2</a></li>
                    <li><a href="#">List item 3</a></li>
                </ListTag>
            </div>
        </>
    );
}
