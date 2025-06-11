import { __ } from '@wordpress/i18n';
import { InnerBlocks, InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, TextControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

export default function Edit({ attributes, setAttributes, clientId }) {
    const { hoverDelay, mobileBreakpoint, blockId } = attributes;

    // Debug: Check inner blocks
    const innerBlocks = useSelect(
        (select) => {
            const { getBlocks } = select('core/block-editor');
            return getBlocks(clientId);
        },
        [clientId]
    );

    // Debug: Log inner blocks
    useEffect(() => {
        console.log('Navigation Inner blocks:', innerBlocks);
        console.log('Client ID:', clientId);
    }, [innerBlocks, clientId]);

    // Generate unique block ID if not set
    useEffect(() => {
        if (!blockId) {
            setAttributes({ blockId: `field-report-nav-${clientId}` });
        }
    }, [blockId, clientId, setAttributes]);

    const blockProps = useBlockProps({
        className: 'field-report-navigation',
        'data-hover-delay': hoverDelay,
        'data-mobile-breakpoint': mobileBreakpoint,
        'data-block-id': blockId
    });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Navigation Settings', 'caes-hub')}>
                    <RangeControl
                        label={__('Hover Delay (ms)', 'caes-hub')}
                        value={hoverDelay}
                        onChange={(value) => setAttributes({ hoverDelay: value })}
                        min={0}
                        max={1000}
                        step={50}
                    />
                    <TextControl
                        label={__('Mobile Breakpoint', 'caes-hub')}
                        value={mobileBreakpoint}
                        onChange={(value) => setAttributes({ mobileBreakpoint: value })}
                        help={__('CSS breakpoint for mobile layout (e.g., 768px)', 'caes-hub')}
                    />
                </PanelBody>
            </InspectorControls>

            <nav {...blockProps} aria-label="Main navigation">
                <ul className="nav-menu">
                    <InnerBlocks
                        allowedBlocks={[
                            'core/paragraph', 
                            'core/heading',
                            'caes-hub/field-report-nav-item'
                        ]}
                        renderAppender={InnerBlocks.ButtonBlockAppender}
                    />
                </ul>
            </nav>
        </>
    );
}