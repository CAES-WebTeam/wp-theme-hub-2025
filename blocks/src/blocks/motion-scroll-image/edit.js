/**
 * Motion Scroll Image Block Editor
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';

const Edit = ({ attributes, setAttributes, context }) => {
	const { slideIndex } = attributes;
	const slides = context['caes-hub/motion-scroll-slides'] || [];

	const blockProps = useBlockProps({
		className: 'caes-motion-scroll-image-editor',
	});

	// Create options for the select control
	const imageOptions = slides.map((slide, index) => {
		const label = slide.image?.alt || `Image ${index + 1}`;
		return {
			label: `${index + 1}. ${label}`,
			value: index,
		};
	});

	// Add a default "no selection" option if no slides
	if (imageOptions.length === 0) {
		imageOptions.push({
			label: __('No images available', 'caes-motion-scroll'),
			value: -1,
		});
	}

	// Get the selected slide
	const selectedSlide = slides[slideIndex] || null;
	const selectedImage = selectedSlide?.image || null;

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Image Selection', 'caes-motion-scroll')} initialOpen={true}>
					<SelectControl
						label={__('Select Image', 'caes-motion-scroll')}
						value={slideIndex}
						options={imageOptions}
						onChange={(value) => setAttributes({ slideIndex: parseInt(value, 10) })}
						help={__('Choose which image from the parent block to display on mobile.', 'caes-motion-scroll')}
					/>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				<div className="motion-scroll-image-preview">
					{selectedImage ? (
						<>
							<img src={selectedImage.url} alt={selectedImage.alt || ''} />
							<div className="motion-scroll-image-label">
								📱 {__('Mobile Only', 'caes-motion-scroll')}
							</div>
						</>
					) : (
						<div className="motion-scroll-image-placeholder">
							<p>
								{slides.length === 0
									? __('Add images to the parent Motion Scroll block first', 'caes-motion-scroll')
									: __('Select an image in the sidebar', 'caes-motion-scroll')
								}
							</p>
						</div>
					)}
				</div>
			</div>
		</>
	);
};

export default Edit;
