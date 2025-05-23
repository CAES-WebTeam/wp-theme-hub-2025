import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls, RichText } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl, TextControl } from '@wordpress/components';

export default function Edit({ attributes, setAttributes }) {
    const { listStyle, tocHeading, showSubheadings, popout, topOfContentAnchor, anchorLinkText } = attributes;
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
                    <ToggleControl
                        label={__('Popout Table of Contents')}
                        checked={popout}
                        onChange={(newPopout) => setAttributes({ popout: newPopout })}
                        help={popout
                            ? __('TOC will be shown in a popout sidebar when the TOC is off screen.')
                            : __('TOC popout is disabled.')}
                    />
                    <ToggleControl
                        label={__('Top of Content Anchor')}
                        checked={topOfContentAnchor}
                        onChange={(newTopOfContentAnchor) => setAttributes({ topOfContentAnchor: newTopOfContentAnchor })}
                        help={topOfContentAnchor
                            ? __('Anchor link to the top of the content is enabled.')
                            : __('Anchor link to the top of the content is disabled.')}
                    />
                    {topOfContentAnchor && (
                        <TextControl
                            label={__('Anchor Link Text')}
                            value={anchorLinkText}
                            onChange={(newAnchorLinkText) => setAttributes({ anchorLinkText: newAnchorLinkText })}
                            placeholder={__('Top of Content')}
                            help={__('This text will be used for the anchor link to the top of the content.')}
                        />
                    )}
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
                    {/* If topOfContentAnchor is true, add an anchor link to the top of the content */}
                    {topOfContentAnchor && (
                        <li>
                            <a href="#top-of-content">{anchorLinkText}</a>
                        </li>
                    )}
                    {/* Example list items */}
                    <li><a href="#">List item 1</a></li>
                    <li><a href="#">List item 2</a></li>
                    <li><a href="#">List item 3</a></li>
                </ListTag>
            </div>
        </>
    );
}
