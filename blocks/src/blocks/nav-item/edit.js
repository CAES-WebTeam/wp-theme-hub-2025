import { __ } from '@wordpress/i18n';
import { InnerBlocks, InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { useEffect } from '@wordpress/element';

export default function Edit({ attributes, setAttributes, context, clientId }) {
    const { linkText, linkUrl, opensInNewTab, hasFlyout, flyoutId } = attributes;
    const navigationId = context['fieldReport/navigationId'];

    // Generate flyout ID when flyout is enabled
    useEffect(() => {
        if (hasFlyout && !flyoutId) {
            setAttributes({ flyoutId: `${navigationId}-flyout-${clientId}` });
        }
    }, [hasFlyout, flyoutId, navigationId, clientId, setAttributes]);

    const blockProps = useBlockProps({
        className: `nav-item${hasFlyout ? ' nav-item-with-submenu' : ''}`
    });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Link Settings', 'your-textdomain')}>
                    <TextControl
                        label={__('Link Text', 'your-textdomain')}
                        value={linkText}
                        onChange={(value) => setAttributes({ linkText: value })}
                    />
                    <TextControl
                        label={__('Link URL', 'your-textdomain')}
                        value={linkUrl}
                        onChange={(value) => setAttributes({ linkUrl: value })}
                        placeholder="https://example.com"
                    />
                    <ToggleControl
                        label={__('Open in new tab', 'your-textdomain')}
                        checked={opensInNewTab}
                        onChange={(value) => setAttributes({ opensInNewTab: value })}
                    />
                    <ToggleControl
                        label={__('Has flyout submenu', 'your-textdomain')}
                        checked={hasFlyout}
                        onChange={(value) => setAttributes({ hasFlyout: value })}
                    />
                </PanelBody>
            </InspectorControls>

            <li {...blockProps}>
                {hasFlyout ? (
                    <>
                        <div className="nav-link-wrapper">
                            <a href={linkUrl} className="nav-link nav-primary-link">
                                {linkText || __('Navigation Item', 'your-textdomain')}
                            </a>
                            <button className="submenu-toggle">
                                <span className="submenu-arrow">▶</span>
                            </button>
                        </div>
                        <InnerBlocks
                            allowedBlocks={['caes-hub/field-report-nav-flyout']}
                            template={[['caes-hub/field-report-nav-flyout']]}
                        />
                    </>
                ) : (
                    <a href={linkUrl} className="nav-link">
                        {linkText || __('Navigation Item', 'your-textdomain')}
                    </a>
                )}
            </li>
        </>
    );
}