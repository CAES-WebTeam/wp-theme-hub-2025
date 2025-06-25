import { __ } from '@wordpress/i18n';
import { 
    useBlockProps,
    __experimentalUseBorderProps as useBorderProps
} from '@wordpress/block-editor';

export default function Edit({ attributes, setAttributes }) {

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

            <div {...blockProps}>
                <div className="external-publisher-wrapper">
                    <div className="external-publisher-placeholder">
                        <span className="external-publisher-link">
                            {__('External Publisher', 'caes-hub')}
                        </span>

                    </div>
                </div>
            </div>
        </>
    );
}