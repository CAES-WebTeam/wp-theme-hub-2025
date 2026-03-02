/**
 * Motion Scroll Image Block Editor
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { useEffect, useRef } from '@wordpress/element';

const parseColor = (hex) => {
	let color = hex.replace('#', '');
	if (color.length === 3) {
		color = color[0] + color[0] + color[1] + color[1] + color[2] + color[2];
	}
	return {
		r: parseInt(color.slice(0, 2), 16) / 255,
		g: parseInt(color.slice(2, 4), 16) / 255,
		b: parseInt(color.slice(4, 6), 16) / 255,
	};
};

const getDuotoneFilterPrimitives = (duotone, filterId) => {
	const shadow = parseColor(duotone[0]);
	const highlight = parseColor(duotone[1]);

	return (
		<filter id={filterId}>
			<feColorMatrix
				colorInterpolationFilters="sRGB"
				type="matrix"
				values=".299 .587 .114 0 0 .299 .587 .114 0 0 .299 .587 .114 0 0 0 0 0 1 0"
			/>
			<feComponentTransfer colorInterpolationFilters="sRGB">
				<feFuncR type="table" tableValues={`${shadow.r} ${highlight.r}`} />
				<feFuncG type="table" tableValues={`${shadow.g} ${highlight.g}`} />
				<feFuncB type="table" tableValues={`${shadow.b} ${highlight.b}`} />
				<feFuncA type="table" tableValues="0 1" />
			</feComponentTransfer>
		</filter>
	);
};

const getDuotoneFilter = (duotone, filterId) => {
	if (!duotone || duotone.length < 2) {
		return null;
	}

	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 0 0"
			width="0"
			height="0"
			focusable="false"
			role="none"
			style={{ visibility: 'hidden', position: 'absolute', left: '-9999px', overflow: 'hidden' }}
			aria-hidden="true"
		>
			<defs>
				{getDuotoneFilterPrimitives(duotone, filterId)}
			</defs>
		</svg>
	);
};

const Edit = ({ attributes, setAttributes, context, clientId }) => {
	const { slideIndex } = attributes;
	const slides = context['caes-hub/motion-scroll-slides'] || [];

	const blockProps = useBlockProps({
		className: 'caes-motion-scroll-image-editor',
	});

	// Get the selected slide
	const selectedSlide = slides[slideIndex] || null;
	const selectedImage = selectedSlide?.image || null;
	const duotone = selectedSlide?.duotone || null;
	const filterId = `motion-scroll-image-duotone-${clientId}`;

	// Apply duotone filter using the iframe document's actual URL to avoid
	// the <base> tag breaking fragment-only url(#id) CSS filter references.
	const imgRef = useRef();
	useEffect(() => {
		const el = imgRef.current;
		if (!el) return;

		if (duotone && duotone.length >= 2) {
			const docUrl = el.ownerDocument.URL.split('#')[0];
			el.style.filter = `url('${docUrl}#${filterId}')`;
		} else {
			el.style.filter = '';
		}
	}, [duotone, filterId, selectedImage]);

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
							{duotone && getDuotoneFilter(duotone, filterId)}
							<img ref={imgRef} src={selectedImage.url} alt={selectedImage.alt || ''} />
							{selectedSlide?.caption && (
								<div className="motion-scroll-image-caption-preview">
									{selectedSlide.caption}
								</div>
							)}
							<div className="motion-scroll-image-label">
								<span aria-hidden="true">📱</span> {__('Mobile Only', 'caes-motion-scroll')}
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
