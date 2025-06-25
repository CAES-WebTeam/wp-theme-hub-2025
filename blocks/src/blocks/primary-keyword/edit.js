import { __ } from '@wordpress/i18n';
import { 
    useBlockProps, 
    InspectorControls, 
    __experimentalUseBorderProps as useBorderProps
} from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';

export default function Edit({ attributes, setAttributes }) {
    const { showCategoryIcon, enableLinks, style } = attributes;

    // Get border props from the style attribute
    const borderProps = useBorderProps(attributes);
    
    // Combine block props with border props
    const blockProps = useBlockProps({
        ...borderProps,
        className: borderProps?.className ? `${borderProps.className}` : undefined,
        style: { ...borderProps?.style }
    });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Primary Keyword Settings', 'caes-hub')}>
                    <ToggleControl
                        label={__('Show Category Icon', 'caes-hub')}
                        checked={showCategoryIcon}
                        onChange={(value) => setAttributes({ showCategoryIcon: value })}
                        help={__('Display content type icon (written, audio, video, gallery)', 'caes-hub')}
                    />
                    <ToggleControl
                        label={__('Enable Links', 'caes-hub')}
                        checked={enableLinks}
                        onChange={(value) => setAttributes({ enableLinks: value })}
                        help={__('Make keywords clickable links to their archive pages', 'caes-hub')}
                    />
                </PanelBody>
                

            </InspectorControls>

            <div {...blockProps}>
                <div className="primary-keywords-wrapper">
                    <div className="primary-keywords-placeholder">
                        {showCategoryIcon && (
                            <span className="primary-keyword-category-icon category-written" aria-hidden="true">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z" />
                                </svg>
                            </span>
                        )}
                        <span className="primary-keyword-link">
                            {__('Primary Keyword', 'caes-hub')}
                        </span>
                        <span className="primary-keyword-separator">, </span>
                        <span className="primary-keyword-link">
                            {__('Another Keyword', 'caes-hub')}
                        </span>
                    </div>
                </div>
            </div>
        </>
    );
}