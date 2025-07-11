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
                <PanelBody title={__('Primary Topic Settings', 'caes-hub')}>
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
                        help={__('Make topics clickable links to their archive pages', 'caes-hub')}
                    />
                </PanelBody>


            </InspectorControls>

            <div {...blockProps}>
                <div className="primary-topics-wrapper">
                    <div className="primary-topics-placeholder">
                        {showCategoryIcon && (
                            <span className="primary-topic-category-icon" aria-hidden="true" style={{ marginRight: "8px" }}>
                                <svg width="16" height="16" viewBox="0 0 192 199.92" fill="currentColor">
                                    <defs>
                                        <clipPath id="9be5ba86e0">
                                            <path d="M 0.0390625 32.625 L 191.960938 32.625 L 191.960938 159.328125 L 0.0390625 159.328125 Z M 0.0390625 32.625" />
                                        </clipPath>
                                    </defs>
                                    <g>
                                        <path d="M 23.335938 119.28125 L 74.273438 119.28125 C 76.117188 119.28125 77.613281 117.785156 77.613281 115.941406 L 77.613281 56.007812 C 77.613281 54.164062 76.117188 52.667969 74.273438 52.667969 L 23.335938 52.667969 C 21.496094 52.667969 20 54.164062 20 56.007812 L 20 115.941406 C 20 117.785156 21.496094 119.28125 23.335938 119.28125 Z M 26.675781 59.347656 L 70.933594 59.347656 L 70.933594 112.640625 L 26.675781 112.640625 Z M 26.675781 59.347656" />
                                        <path d="M 143.175781 52.667969 L 94.234375 52.667969 C 92.390625 52.667969 90.894531 54.164062 90.894531 56.007812 C 90.894531 57.851562 92.390625 59.347656 94.234375 59.347656 L 143.175781 59.347656 C 145.015625 59.347656 146.515625 57.851562 146.515625 56.007812 C 146.515625 54.164062 145.015625 52.667969 143.175781 52.667969 Z M 143.175781 52.667969" />
                                        <path d="M 143.175781 72.671875 L 94.234375 72.671875 C 92.390625 72.671875 90.894531 74.167969 90.894531 76.011719 C 90.894531 77.855469 92.390625 79.351562 94.234375 79.351562 L 143.175781 79.351562 C 145.015625 79.351562 146.515625 77.855469 146.515625 76.011719 C 146.515625 74.167969 145.015625 72.671875 143.175781 72.671875 Z M 143.175781 72.671875" />
                                        <path d="M 143.175781 92.636719 L 94.234375 92.636719 C 92.390625 92.636719 90.894531 94.132812 90.894531 95.976562 C 90.894531 97.820312 92.390625 99.316406 94.234375 99.316406 L 143.175781 99.316406 C 145.015625 99.316406 146.515625 97.820312 146.515625 95.976562 C 146.515625 94.132812 145.015625 92.636719 143.175781 92.636719 Z M 143.175781 92.636719" />
                                        <path d="M 143.175781 112.640625 L 94.234375 112.640625 C 92.390625 112.640625 90.894531 114.136719 90.894531 115.980469 C 90.894531 117.824219 92.390625 119.320312 94.234375 119.320312 L 143.175781 119.320312 C 145.015625 119.320312 146.515625 117.824219 146.515625 115.980469 C 146.515625 114.097656 145.015625 112.640625 143.175781 112.640625 Z M 143.175781 112.640625" />
                                        <path d="M 143.175781 132.605469 L 23.335938 132.605469 C 21.496094 132.605469 20 134.101562 20 135.945312 C 20 137.789062 21.496094 139.285156 23.335938 139.285156 L 143.175781 139.285156 C 145.015625 139.285156 146.515625 137.789062 146.515625 135.945312 C 146.515625 134.101562 145.015625 132.605469 143.175781 132.605469 Z M 143.175781 132.605469" />
                                        <g clipPath="url(#9be5ba86e0)">
                                            <path d="M 188.621094 52.667969 L 166.472656 52.667969 L 166.472656 36.042969 C 166.472656 34.199219 164.976562 32.699219 163.132812 32.699219 L 3.378906 32.699219 C 1.535156 32.699219 0.0390625 34.199219 0.0390625 36.042969 L 0.0390625 143.203125 C 0.0390625 152.070312 7.253906 159.253906 16.082031 159.253906 L 175.878906 159.253906 C 184.746094 159.253906 191.921875 152.035156 191.921875 143.203125 L 191.921875 56.046875 C 191.960938 54.164062 190.464844 52.667969 188.621094 52.667969 Z M 156.378906 152.570312 L 16.082031 152.570312 C 10.902344 152.570312 6.679688 148.347656 6.679688 143.164062 L 6.679688 39.34375 L 159.832031 39.34375 L 159.832031 143.203125 C 159.832031 146.695312 160.984375 149.960938 162.90625 152.609375 L 156.378906 152.609375 Z M 185.320312 143.164062 C 185.320312 148.347656 181.097656 152.570312 175.917969 152.570312 C 170.734375 152.570312 166.511719 148.347656 166.511719 143.164062 L 166.511719 59.347656 L 185.320312 59.347656 Z M 185.320312 143.164062" />
                                        </g>
                                    </g>
                                </svg>
                            </span>
                        )}
                        <span className="primary-topic-link">
                            {__('Primary Topic', 'caes-hub')}
                        </span>
                        <span className="primary-topic-separator">, </span>
                        <span className="primary-topic-link">
                            {__('Another Topic', 'caes-hub')}
                        </span>
                    </div>
                </div>
            </div>
        </>
    );
}