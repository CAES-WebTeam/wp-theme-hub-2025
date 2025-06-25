import { __ } from '@wordpress/i18n';
import { InnerBlocks, InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, ToggleControl, ColorPicker } from '@wordpress/components';
import { useEffect } from '@wordpress/element';

export default function Edit({ attributes, setAttributes, clientId }) {
    const { 
        blockId, 
        mobileBreakpoint, 
        overlayPosition, 
        overlayBackgroundColor, 
        showCloseButton,
        hamburgerLabel 
    } = attributes;

    // Generate unique block ID if not set
    useEffect(() => {
        if (!blockId) {
            setAttributes({ blockId: `mobile-container-${clientId}` });
        }
    }, [blockId, clientId, setAttributes]);

    const blockProps = useBlockProps({
        className: 'mobile-container-block',
        'data-block-id': blockId,
        'data-mobile-breakpoint': mobileBreakpoint,
        'data-overlay-position': overlayPosition
    });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Mobile Container Settings', 'caes-hub')}>
                    <TextControl
                        label={__('Mobile Breakpoint', 'caes-hub')}
                        value={mobileBreakpoint}
                        onChange={(value) => setAttributes({ mobileBreakpoint: value })}
                        help={__('CSS breakpoint for mobile layout (e.g., 768px)', 'caes-hub')}
                    />
                    
                    <SelectControl
                        label={__('Overlay Position', 'caes-hub')}
                        value={overlayPosition}
                        onChange={(value) => setAttributes({ overlayPosition: value })}
                        options={[
                            { label: __('Full Screen', 'caes-hub'), value: 'full' },
                            { label: __('Slide from Right', 'caes-hub'), value: 'right' },
                            { label: __('Slide from Left', 'caes-hub'), value: 'left' }
                        ]}
                    />
                    
                    <div style={{ marginBottom: '16px' }}>
                        <label style={{ display: 'block', marginBottom: '8px', fontWeight: '600' }}>
                            {__('Overlay Background Color', 'caes-hub')}
                        </label>
                        <ColorPicker
                            color={overlayBackgroundColor}
                            onChange={(color) => setAttributes({ overlayBackgroundColor: color })}
                        />
                    </div>
                    
                    <ToggleControl
                        label={__('Show Close Button', 'caes-hub')}
                        checked={showCloseButton}
                        onChange={(value) => setAttributes({ showCloseButton: value })}
                        help={__('Show X button to close overlay', 'caes-hub')}
                    />
                    
                    <TextControl
                        label={__('Hamburger Button Label', 'caes-hub')}
                        value={hamburgerLabel}
                        onChange={(value) => setAttributes({ hamburgerLabel: value })}
                        help={__('Accessible label for screen readers', 'caes-hub')}
                        placeholder={__('Menu', 'caes-hub')}
                    />
                </PanelBody>
            </InspectorControls>

            <div {...blockProps}>
                <div className="editor-preview-notice" style={{
                    padding: '12px',
                    backgroundColor: '#f0f0f0',
                    border: '1px dashed #ccc',
                    marginBottom: '16px',
                    borderRadius: '4px'
                }}>
                    <strong>{__('Mobile Container', 'caes-hub')}</strong>
                    <br />
                    <small>{__('Content shows normally on desktop, with hamburger overlay on mobile.', 'caes-hub')}</small>
                </div>

                {/* Mobile hamburger button preview */}
                <div className="mobile-trigger-preview" style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: '8px',
                    padding: '8px 12px',
                    backgroundColor: '#e0e0e0',
                    borderRadius: '4px',
                    marginBottom: '16px',
                    opacity: '0.7'
                }}>
                    <div className="hamburger-icon" style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: '3px'
                    }}>
                        <div style={{
                            width: '20px',
                            height: '2px',
                            backgroundColor: '#333',
                            borderRadius: '1px'
                        }}></div>
                        <div style={{
                            width: '20px',
                            height: '2px',
                            backgroundColor: '#333',
                            borderRadius: '1px'
                        }}></div>
                        <div style={{
                            width: '20px',
                            height: '2px',
                            backgroundColor: '#333',
                            borderRadius: '1px'
                        }}></div>
                    </div>
                    <span style={{ fontSize: '14px' }}>
                        {hamburgerLabel || __('Menu', 'caes-hub')} {__('(Mobile Preview)', 'caes-hub')}
                    </span>
                </div>

                {/* Content area */}
                <div className="mobile-container-content">
                    <InnerBlocks
                        allowedBlocks={[
                            'caes-hub/field-report-navigation',
                            'core/search',
                            'core/paragraph',
                            'core/heading',
                            'core/list',
                            'core/group',
                            'core/columns',
                            'core/buttons',
                            'core/separator',
                            'core/spacer'
                        ]}
                        template={[
                            ['core/paragraph', { 
                                content: __('Add your mobile menu content here (navigation, search, etc.)', 'caes-hub')
                            }]
                        ]}
                        renderAppender={InnerBlocks.ButtonBlockAppender}
                    />
                </div>
            </div>
        </>
    );
}