import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';
import './editor.scss';

export default function Edit({ attributes, setAttributes }) {
    const { wordLimit } = attributes;

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
                </PanelBody>
            </InspectorControls>

            <div {...useBlockProps()}>
                <p>{previewText}</p>
            </div>
        </>
    );
}