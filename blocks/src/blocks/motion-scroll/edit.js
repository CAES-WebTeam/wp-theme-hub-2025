/**
 * Motion Scroll Block Editor
 */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InnerBlocks,
	InspectorControls,
	BlockControls,
	MediaUpload,
	MediaUploadCheck,
} from '@wordpress/block-editor';
import {
	Button,
	PanelBody,
	SelectControl,
	FocalPointPicker,
	Popover,
	TextControl,
	ToolbarGroup,
	ToolbarButton,
	Modal,
	DuotonePicker,
	DuotoneSwatch,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';

const DUOTONE_PALETTE = [
	{ colors: ['#000000', '#ffffff'], name: 'Grayscale', slug: 'grayscale' },
	{ colors: ['#000000', '#7f7f7f'], name: 'Dark grayscale', slug: 'dark-grayscale' },
	{ colors: ['#12128c', '#ffcc00'], name: 'Blue and yellow', slug: 'blue-yellow' },
	{ colors: ['#8c00b7', '#fcff41'], name: 'Purple and yellow', slug: 'purple-yellow' },
	{ colors: ['#000097', '#ff4747'], name: 'Blue and red', slug: 'blue-red' },
	{ colors: ['#004b23', '#99e2b4'], name: 'Green tones', slug: 'green-tones' },
	{ colors: ['#99154e', '#f7b2d9'], name: 'Magenta tones', slug: 'magenta-tones' },
	{ colors: ['#0d3b66', '#faf0ca'], name: 'Navy and cream', slug: 'navy-cream' },
];

const COLOR_PALETTE = [
	{ color: '#000000', name: 'Black', slug: 'black' },
	{ color: '#ffffff', name: 'White', slug: 'white' },
	{ color: '#7f7f7f', name: 'Gray', slug: 'gray' },
	{ color: '#ff4747', name: 'Red', slug: 'red' },
	{ color: '#fcff41', name: 'Yellow', slug: 'yellow' },
	{ color: '#ffcc00', name: 'Gold', slug: 'gold' },
	{ color: '#000097', name: 'Blue', slug: 'blue' },
	{ color: '#12128c', name: 'Navy', slug: 'navy' },
	{ color: '#8c00b7', name: 'Purple', slug: 'purple' },
	{ color: '#004b23', name: 'Dark Green', slug: 'dark-green' },
	{ color: '#99e2b4', name: 'Light Green', slug: 'light-green' },
	{ color: '#99154e', name: 'Magenta', slug: 'magenta' },
	{ color: '#f7b2d9', name: 'Pink', slug: 'pink' },
	{ color: '#0d3b66', name: 'Dark Blue', slug: 'dark-blue' },
	{ color: '#faf0ca', name: 'Cream', slug: 'cream' },
];

const DEFAULT_SLIDE = {
	id: '',
	image: null,
	focalPoint: { x: 0.5, y: 0.5 },
	duotone: null,
};

const generateSlideId = () => {
	return 'slide-' + Math.random().toString(36).substr(2, 9);
};

/**
 * Generate duotone SVG filter markup
 */
const getDuotoneFilter = (duotone, filterId) => {
	if (!duotone || duotone.length < 2) {
		return null;
	}

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

	const shadow = parseColor(duotone[0]);
	const highlight = parseColor(duotone[1]);

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
			</defs>
		</svg>
	);
};

const Edit = ({ attributes, setAttributes, clientId }) => {
	const { slides, contentPosition, imageDisplayMode } = attributes;
	const [showSlideManager, setShowSlideManager] = useState(false);

	// Auto-add first slide when block is inserted
	useEffect(() => {
		if (slides.length === 0) {
			setAttributes({
				slides: [{ ...DEFAULT_SLIDE, id: generateSlideId() }],
			});
		}
	}, []);

	const addSlide = () => {
		const newSlide = {
			...DEFAULT_SLIDE,
			id: generateSlideId(),
		};
		setAttributes({ slides: [...slides, newSlide] });
	};

	const removeSlide = (slideIndex) => {
		if (slides.length === 1) {
			return;
		}
		const newSlides = [...slides];
		newSlides.splice(slideIndex, 1);
		setAttributes({ slides: newSlides });
	};

	const updateSlide = (slideIndex, updates) => {
		const newSlides = [...slides];
		newSlides[slideIndex] = { ...newSlides[slideIndex], ...updates };
		setAttributes({ slides: newSlides });
	};

	const moveSlideUp = (slideIndex) => {
		if (slideIndex === 0) return;
		const newSlides = [...slides];
		[newSlides[slideIndex - 1], newSlides[slideIndex]] = [
			newSlides[slideIndex],
			newSlides[slideIndex - 1],
		];
		setAttributes({ slides: newSlides });
	};

	const moveSlideDown = (slideIndex) => {
		if (slideIndex === slides.length - 1) return;
		const newSlides = [...slides];
		[newSlides[slideIndex], newSlides[slideIndex + 1]] = [
			newSlides[slideIndex + 1],
			newSlides[slideIndex],
		];
		setAttributes({ slides: newSlides });
	};

	const duplicateSlide = (slideIndex) => {
		const slideToDuplicate = slides[slideIndex];
		const duplicatedSlide = {
			...JSON.parse(JSON.stringify(slideToDuplicate)),
			id: generateSlideId(),
		};
		const newSlides = [...slides];
		newSlides.splice(slideIndex + 1, 0, duplicatedSlide);
		setAttributes({ slides: newSlides });
	};

	const onSelectImage = (slideIndex, media) => {
		const imageData = {
			id: media.id,
			url: media.url,
			alt: media.alt || '',
			caption: media.caption || '',
			sizes: media.sizes || {},
		};
		updateSlide(slideIndex, { image: imageData });
	};

	const onRemoveImage = (slideIndex) => {
		updateSlide(slideIndex, { image: null });
	};

	const blockProps = useBlockProps({
		className: `caes-motion-scroll-editor content-${contentPosition} image-mode-${imageDisplayMode}`,
	});

	return (
		<>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						icon="align-pull-left"
						label={__('Show content on left', 'caes-motion-scroll')}
						isActive={contentPosition === 'left'}
						onClick={() => setAttributes({ contentPosition: 'left' })}
					/>
					<ToolbarButton
						icon="align-pull-right"
						label={__('Show content on right', 'caes-motion-scroll')}
						isActive={contentPosition === 'right'}
						onClick={() => setAttributes({ contentPosition: 'right' })}
					/>
				</ToolbarGroup>
				<ToolbarGroup>
					<ToolbarButton
						icon="cover-image"
						label={__('Fill background (cover)', 'caes-motion-scroll')}
						isActive={imageDisplayMode === 'cover'}
						onClick={() => setAttributes({ imageDisplayMode: 'cover' })}
					/>
					<ToolbarButton
						icon="image-flip-horizontal"
						label={__('Fit to width (contain)', 'caes-motion-scroll')}
						isActive={imageDisplayMode === 'contain'}
						onClick={() => setAttributes({ imageDisplayMode: 'contain' })}
					/>
				</ToolbarGroup>
				<ToolbarGroup>
					<ToolbarButton
						icon="admin-generic"
						label={__('Manage Images', 'caes-motion-scroll')}
						onClick={() => setShowSlideManager(true)}
					/>
				</ToolbarGroup>
			</BlockControls>

			<InspectorControls>
				<PanelBody title={__('Images', 'caes-motion-scroll')} initialOpen={true}>
					<p style={{ marginBottom: '12px', color: '#757575', fontSize: '13px' }}>
						{__('This block has', 'caes-motion-scroll')} {slides.length} {slides.length === 1 ? __('image', 'caes-motion-scroll') : __('images', 'caes-motion-scroll')}.
					</p>
					<Button
						variant="secondary"
						onClick={() => setShowSlideManager(true)}
						style={{ width: '100%' }}
					>
						{__('Manage Images', 'caes-motion-scroll')}
					</Button>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				<div className="motion-scroll-editor-layout">
					<div className="motion-scroll-editor-images">
						<div className="motion-scroll-images-preview">
							{slides.length > 0 && slides[0]?.image ? (
								<img src={slides[0].image.url} alt={slides[0].image.alt || ''} />
							) : (
								<div className="motion-scroll-placeholder">
									<p>{__('Add images using the toolbar button', 'caes-motion-scroll')}</p>
								</div>
							)}
							{slides.length > 1 && (
								<div className="motion-scroll-image-count">
									+{slides.length - 1} {__('more', 'caes-motion-scroll')}
								</div>
							)}
						</div>
					</div>
					<div className="motion-scroll-editor-content">
						<InnerBlocks
							allowedBlocks={true}
							placeholder={__('Add content blocks here...', 'caes-motion-scroll')}
						/>
					</div>
				</div>
			</div>

			{/* Slide Manager Modal */}
			{showSlideManager && (
				<Modal
					title={__('Manage Images', 'caes-motion-scroll')}
					onRequestClose={() => setShowSlideManager(false)}
					className="motion-scroll-slide-manager-modal"
					style={{ maxWidth: '900px', width: '90vw' }}
				>
					<div style={{ padding: '20px 0' }}>
						<p style={{ marginBottom: '20px', color: '#757575' }}>
							{__('Add and configure images that will transition as the user scrolls through the content.', 'caes-motion-scroll')}
						</p>

						{slides.map((slide, index) => (
							<SlideManagerPanel
								key={slide.id || index}
								slide={slide}
								index={index}
								totalSlides={slides.length}
								onUpdate={(updates) => updateSlide(index, updates)}
								onRemove={() => removeSlide(index)}
								onMoveUp={() => moveSlideUp(index)}
								onMoveDown={() => moveSlideDown(index)}
								onDuplicate={() => duplicateSlide(index)}
								onSelectImage={(media) => onSelectImage(index, media)}
								onRemoveImage={() => onRemoveImage(index)}
								clientId={clientId}
							/>
						))}

						<Button variant="primary" onClick={addSlide} style={{ width: '100%', marginTop: '20px' }}>
							{__('Add Image', 'caes-motion-scroll')}
						</Button>

						<div style={{ marginTop: '20px', textAlign: 'right' }}>
							<Button variant="secondary" onClick={() => setShowSlideManager(false)}>
								{__('Done', 'caes-motion-scroll')}
							</Button>
						</div>
					</div>
				</Modal>
			)}
		</>
	);
};

// Slide Manager Panel Component
const SlideManagerPanel = ({
	slide,
	index,
	totalSlides,
	onUpdate,
	onRemove,
	onMoveUp,
	onMoveDown,
	onDuplicate,
	onSelectImage,
	onRemoveImage,
	clientId,
}) => {
	const [isOpen, setIsOpen] = useState(false);
	const [showFocalPointModal, setShowFocalPointModal] = useState(false);
	const [showDuotoneModal, setShowDuotoneModal] = useState(false);

	return (
		<div
			style={{
				border: '1px solid #ddd',
				borderRadius: '4px',
				marginBottom: '20px',
				background: '#fff',
			}}
		>
			{/* Header */}
			<div
				style={{
					display: 'flex',
					justifyContent: 'space-between',
					alignItems: 'center',
					padding: '16px',
					borderBottom: isOpen ? '1px solid #ddd' : 'none',
					background: '#f9f9f9',
					cursor: 'pointer',
				}}
				onClick={() => setIsOpen(!isOpen)}
			>
				<div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
					{/* Thumbnail */}
					{slide.image ? (
						<div
							style={{
								width: '60px',
								height: '40px',
								borderRadius: '4px',
								overflow: 'hidden',
								border: '1px solid #ddd',
								flexShrink: 0,
							}}
						>
							{(() => {
								const filterId = `thumbnail-${clientId}-${index}`;
								const duotone = slide.duotone;
								return (
									<>
										{duotone && getDuotoneFilter(duotone, filterId)}
										<img
											src={slide.image.url}
											alt=""
											style={{
												width: '100%',
												height: '100%',
												objectFit: 'cover',
												filter: duotone ? `url(#${filterId})` : undefined,
											}}
										/>
									</>
								);
							})()}
						</div>
					) : (
						<div
							style={{
								width: '60px',
								height: '40px',
								borderRadius: '4px',
								border: '1px solid #ddd',
								background: '#e0e0e0',
								display: 'flex',
								alignItems: 'center',
								justifyContent: 'center',
								fontSize: '10px',
								color: '#666',
								flexShrink: 0,
							}}
						>
							No image
						</div>
					)}
					<strong style={{ fontSize: '16px' }}>
						{__('Image', 'caes-motion-scroll')} {index + 1}
					</strong>
				</div>
				<div style={{ display: 'flex', gap: '8px', alignItems: 'center' }} onClick={(e) => e.stopPropagation()}>
					{index > 0 && (
						<Button size="small" icon="arrow-up-alt2" onClick={onMoveUp} label={__('Move up', 'caes-motion-scroll')} />
					)}
					{index < totalSlides - 1 && (
						<Button size="small" icon="arrow-down-alt2" onClick={onMoveDown} label={__('Move down', 'caes-motion-scroll')} />
					)}
					<Button size="small" icon="admin-page" onClick={onDuplicate} label={__('Duplicate', 'caes-motion-scroll')} />
					{totalSlides > 1 && (
						<Button
							size="small"
							icon="trash"
							onClick={onRemove}
							label={__('Remove', 'caes-motion-scroll')}
							isDestructive
						/>
					)}
					<Button
						size="small"
						icon={isOpen ? 'arrow-up-alt2' : 'arrow-down-alt2'}
						onClick={(e) => {
							e.stopPropagation();
							setIsOpen(!isOpen);
						}}
						label={isOpen ? __('Collapse', 'caes-motion-scroll') : __('Expand', 'caes-motion-scroll')}
					/>
				</div>
			</div>

			{/* Content */}
			{isOpen && (
				<div style={{ padding: '20px' }}>
					<ImagePanel
						slide={slide}
						onSelectImage={onSelectImage}
						onRemoveImage={onRemoveImage}
						onUpdate={onUpdate}
						setShowFocalPointModal={setShowFocalPointModal}
						setShowDuotoneModal={setShowDuotoneModal}
						clientId={clientId}
						slideIndex={index}
					/>
				</div>
			)}

			{/* Modals */}
			{showFocalPointModal && (
				<FocalPointModal
					slide={slide}
					onUpdate={onUpdate}
					onClose={() => setShowFocalPointModal(false)}
				/>
			)}

			{showDuotoneModal && (
				<DuotoneModal
					slide={slide}
					onUpdate={onUpdate}
					onClose={() => setShowDuotoneModal(false)}
				/>
			)}
		</div>
	);
};

// Image Panel Component
const ImagePanel = ({
	slide,
	onSelectImage,
	onRemoveImage,
	onUpdate,
	setShowFocalPointModal,
	setShowDuotoneModal,
	clientId,
	slideIndex,
}) => {
	const image = slide.image;
	const duotone = slide.duotone;

	return (
		<div>
			<label style={{ display: 'block', marginBottom: '8px', fontWeight: 500, fontSize: '13px', color: '#1e1e1e' }}>
				{__('Image', 'caes-motion-scroll')}
			</label>
			<p style={{ margin: '0 0 12px', fontSize: '12px', color: '#757575' }}>
				{__('Recommended: JPEG @ 1920 x 1080px', 'caes-motion-scroll')}
			</p>

			<MediaUploadCheck>
				{!image ? (
					<div>
						<MediaUpload
							onSelect={onSelectImage}
							allowedTypes={['image']}
							render={({ open }) => (
								<Button variant="secondary" onClick={open} style={{ width: '100%', height: '200px' }}>
									{__('Select Image', 'caes-motion-scroll')}
								</Button>
							)}
						/>
					</div>
				) : (
					<div>
						{/* Image Preview */}
						<div style={{ marginBottom: '16px' }}>
							{(() => {
								const filterId = `manager-${clientId}-${slideIndex}`;
								return (
									<>
										{duotone && getDuotoneFilter(duotone, filterId)}
										<img
											src={image.url}
											alt={image.alt}
											style={{
												width: '100%',
												height: 'auto',
												maxHeight: '250px',
												aspectRatio: '16 / 9',
												objectFit: 'cover',
												borderRadius: '4px',
												filter: duotone ? `url(#${filterId})` : undefined,
											}}
										/>
									</>
								);
							})()}
						</div>

						{/* Image Actions */}
						<div style={{ display: 'flex', gap: '8px', marginBottom: '16px' }}>
							<MediaUpload
								onSelect={onSelectImage}
								allowedTypes={['image']}
								value={image?.id}
								render={({ open }) => (
									<Button variant="secondary" onClick={open}>
										{__('Replace', 'caes-motion-scroll')}
									</Button>
								)}
							/>
							<Button variant="secondary" isDestructive onClick={onRemoveImage}>
								{__('Remove', 'caes-motion-scroll')}
							</Button>
						</div>

						{/* Alt Text */}
						<TextControl
							label={__('Alt Text', 'caes-motion-scroll') + ' (' + __('required', 'caes-motion-scroll') + ')'}
							value={image?.alt || ''}
							onChange={(value) => {
								const updatedImage = { ...image, alt: value };
								onUpdate({ image: updatedImage });
							}}
							placeholder={__('Describe image for screenreaders', 'caes-motion-scroll')}
						/>

						{/* Focus & Filter Buttons */}
						<div style={{ display: 'flex', gap: '8px', marginTop: '16px', flexWrap: 'wrap', alignItems: 'center' }}>
							<Button variant="secondary" onClick={() => setShowFocalPointModal(true)} icon="image-crop">
								{__('Set Focus Point', 'caes-motion-scroll')}
							</Button>
							<Button variant="secondary" onClick={() => setShowDuotoneModal(true)} icon="admin-appearance">
								{duotone ? __('Edit Filter', 'caes-motion-scroll') : __('Add Filter', 'caes-motion-scroll')}
							</Button>
							{duotone && <DuotoneSwatch values={duotone} />}
						</div>
					</div>
				)}
			</MediaUploadCheck>
		</div>
	);
};

// Focal Point Modal
const FocalPointModal = ({ slide, onUpdate, onClose }) => {
	const image = slide.image;

	if (!image) {
		return null;
	}

	return (
		<Modal
			title={__('Set Focus Point', 'caes-motion-scroll')}
			onRequestClose={onClose}
			style={{ maxWidth: '600px', width: '100%' }}
		>
			<div style={{ padding: '8px 0' }}>
				<p style={{ margin: '0 0 16px 0', color: '#757575', fontSize: '13px' }}>
					{__('Click on the image to set the focal point. This determines which part of the image stays visible when cropped.', 'caes-motion-scroll')}
				</p>

				<FocalPointPicker
					url={image.url}
					value={slide.focalPoint || { x: 0.5, y: 0.5 }}
					onChange={(value) => onUpdate({ focalPoint: value })}
				/>

				<div style={{ marginTop: '20px', display: 'flex', justifyContent: 'flex-end' }}>
					<Button variant="primary" onClick={onClose}>
						{__('Done', 'caes-motion-scroll')}
					</Button>
				</div>
			</div>
		</Modal>
	);
};

// Duotone Modal
const DuotoneModal = ({ slide, onUpdate, onClose }) => {
	const duotone = slide.duotone;

	return (
		<Modal
			title={__('Duotone Filter', 'caes-motion-scroll')}
			onRequestClose={onClose}
			style={{ maxWidth: '400px', width: '100%' }}
		>
			<div style={{ padding: '8px 0' }}>
				<p style={{ margin: '0 0 16px 0', color: '#757575', fontSize: '13px' }}>
					{__('Apply a duotone color filter to this image. The first color replaces shadows, the second replaces highlights.', 'caes-motion-scroll')}
				</p>

				<DuotonePicker
					duotonePalette={DUOTONE_PALETTE}
					colorPalette={COLOR_PALETTE}
					value={duotone || undefined}
					onChange={(value) => onUpdate({ duotone: value })}
				/>

				<div style={{ marginTop: '20px', display: 'flex', justifyContent: 'space-between' }}>
					{duotone && (
						<Button
							variant="tertiary"
							isDestructive
							onClick={() => {
								onUpdate({ duotone: null });
								onClose();
							}}
						>
							{__('Remove Filter', 'caes-motion-scroll')}
						</Button>
					)}
					<div style={{ marginLeft: 'auto' }}>
						<Button variant="primary" onClick={onClose}>
							{__('Done', 'caes-motion-scroll')}
						</Button>
					</div>
				</div>
			</div>
		</Modal>
	);
};

export default Edit;
