import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl, ToggleControl } from '@wordpress/components';
import './editor.scss';

export default function Edit({ attributes, setAttributes }) {
    const { wordLimit, showFeaturedImage, conditionalDisplay } = attributes;

    const loremIpsum =
        `
        Summary preview. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed non risus. 
        Suspendisse lectus tortor, dignissim sit amet, adipiscing nec, ultricies sed, dolor. 
        Cras elementum ultrices diam. Maecenas ligula massa, varius a, semper congue, euismod non, mi. 
        Proin porttitor, orci nec nonummy molestie, enim est eleifend mi, non fermentum diam nisl sit amet erat. 
        Duis semper. Duis arcu massa, scelerisque vitae, consequat in, pretium a, enim. Pellentesque congue. 
        Ut in risus volutpat libero pharetra tempor. Cras vestibulum bibendum augue. 
        Praesent egestas leo in pede. Praesent blandit odio eu enim.
        `;

    // Generate preview text based on wordLimit
    const words = loremIpsum.split(' ');
    const isTruncated = wordLimit > 0 && wordLimit < words.length;
    const previewText =
        wordLimit > 0 ? words.slice(0, wordLimit).join(' ') + (isTruncated ? '…' : '') : loremIpsum;

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Display Settings', 'text-domain')} initialOpen={true}>
                    <RangeControl
                        label={__('Word Limit', 'text-domain')}
                        value={wordLimit}
                        onChange={(value) => setAttributes({ wordLimit: value })}
                        min={0}
                        max={100}
                        allowReset
                        initialPosition={0}
                        help={__('Set to 0 for no limit', 'text-domain')}
                    />
                    
                    <ToggleControl
                        label={__('Show Featured Image', 'text-domain')}
                        checked={showFeaturedImage}
                        onChange={(value) => setAttributes({ showFeaturedImage: value })}
                        help={__('Display the featured image above the summary at full width', 'text-domain')}
                    />
                    
                    <ToggleControl
                        label={__('Conditional Display', 'text-domain')}
                        checked={conditionalDisplay}
                        onChange={(value) => setAttributes({ conditionalDisplay: value })}
                        help={__('Only show this block if the main content is empty', 'text-domain')}
                    />
                </PanelBody>
            </InspectorControls>

            <div {...useBlockProps()}>
                {conditionalDisplay && (
                    <div style={{
                        padding: '8px',
                        backgroundColor: '#f0f0f0',
                        border: '1px dashed #ccc',
                        marginBottom: '12px',
                        fontSize: '12px',
                        color: '#666'
                    }}>
                        {__('Conditional Display: Will only show if main content is empty', 'text-domain')}
                    </div>
                )}
                
                {showFeaturedImage && (
                    <div style={{
                        width: '100%',
                        height: '200px',
                        backgroundColor: '#f9f9f9',
                        border: '2px dashed #ddd',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        marginBottom: '16px',
                        color: '#666',
                        fontSize: '14px'
                    }}>
                        {__('Featured Image Preview', 'text-domain')}
                    </div>
                )}
                
                <p>{previewText}</p>
            </div>
        </>
    );
}