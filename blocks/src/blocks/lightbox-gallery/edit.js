import { __ } from '@wordpress/i18n';
import { useBlockProps, MediaUpload, MediaUploadCheck, BlockControls, InspectorControls } from '@wordpress/block-editor';
import { Button, Flex, FlexItem, PanelBody, ToggleControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { ToolbarGroup, ToolbarButton } from '@wordpress/components';

const Edit = ({ attributes, setAttributes }) => {
	const { images, showFilmStrip } = attributes;
	const [isPreviewMode, setIsPreviewMode] = useState(false);

	const onUpdateImageField = (index, field, value) => {
		const newImages = [...images];
		newImages[index][field] = value;
		setAttributes({ images: newImages });
	};

	const onSelectImages = (media) => {
		const newImages = media.map((item) => ({
			id: item.id,
			url: item.url,
			alt: item.alt || '',
			caption: item.caption || '',
			sizes: item.sizes || {}
		}));
		setAttributes({ images: newImages });
	};

	const onRemoveImage = (index) => {
		const newImages = [...images];
		newImages.splice(index, 1);
		setAttributes({ images: newImages });
	};

	const blockProps = useBlockProps({
		className: 'lightbox-gallery-block'
	});

	if (!images || images.length === 0) {
		return (
			<div {...blockProps}>
				<MediaUploadCheck>
					<MediaUpload
						onSelect={onSelectImages}
						allowedTypes={['image']}
						multiple={true}
						gallery={true}
						value={images.map(img => img.id)}
						render={({ open }) => (
							<Button
								onClick={open}
								variant="secondary"
								className="lightbox-gallery-placeholder"
								style={{
									width: '100%',
									height: '200px',
									border: '2px dashed #ccc',
									borderRadius: '4px',
									display: 'flex',
									alignItems: 'center',
									justifyContent: 'center',
									flexDirection: 'column'
								}}
							>
								<span className="dashicon dashicons-camera" style={{ fontSize: '48px', marginBottom: '8px' }}></span>
								{__('Create Lightbox Gallery', 'lightbox-gallery')}
							</Button>
						)}
					/>
				</MediaUploadCheck>
			</div>
		);
	}

	// Preview Mode - Shows frontend appearance
	if (isPreviewMode) {
		return (
			<div {...blockProps}>
				<BlockControls>
					<ToolbarGroup>
						<ToolbarButton
							onClick={() => setIsPreviewMode(false)}
							icon="edit"
						>
							{__('Edit', 'lightbox-gallery')}
						</ToolbarButton>
					</ToolbarGroup>
				</BlockControls>

				<div className="lightbox-gallery-preview">
					<div className="lightbox-gallery-container">
						<div className="gallery-single-trigger">
							<div className="single-image-container">
								<img
									src={images[0]?.url}
									alt=""
									className="trigger-image"
								/>
								<div className="view-gallery-btn-preview">
									<span className="view-gallery-text">{__('View Gallery', 'lightbox-gallery')}</span>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		);
	}

	return (
		<>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						onClick={() => setIsPreviewMode(true)}
						icon="visibility"
					>
						{__('Preview', 'lightbox-gallery')}
					</ToolbarButton>
				</ToolbarGroup>
			</BlockControls>

			<InspectorControls>
				<PanelBody title={__('Gallery Settings', 'lightbox-gallery')}>
					<ToggleControl
						label={__('Show Film Strip', 'lightbox-gallery')}
						checked={showFilmStrip}
						onChange={(value) => setAttributes({ showFilmStrip: value })}
						help={__('Display thumbnail navigation below the main image', 'lightbox-gallery')}
					/>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				<div className="lightbox-gallery-editor">
					{/* Simplified Gallery Preview */}
					<div className="gallery-preview-simple">
						<div className="preview-image-container">
							<img
								src={images[0]?.url}
								alt=""
								className="preview-image"
							/>
							<div className="view-gallery-overlay">
								<span className="view-gallery-text">
									{__('View Gallery', 'lightbox-gallery')}
								</span>
							</div>
						</div>
					</div>

					{/* Edit Controls */}
					<div className="gallery-controls">
						<Flex>
							<FlexItem>
								<MediaUploadCheck>
									<MediaUpload
										onSelect={onSelectImages}
										allowedTypes={['image']}
										multiple={true}
										gallery={true}
										value={images.map(img => img.id)}
										render={({ open }) => (
											<Button onClick={open} variant="secondary">
												{__('Edit Gallery', 'lightbox-gallery')}
											</Button>
										)}
									/>
								</MediaUploadCheck>
							</FlexItem>
						</Flex>

						{/* Image Management */}
						<div className="images-editor">
							<h4>{__('Images in Gallery', 'lightbox-gallery')}</h4>
							{images.map((image, index) => (
								<div key={image.id} className="image-editor-item">
									<div className="image-preview">
										<img
											src={image.sizes?.thumbnail?.url || image.url}
											alt=""
											style={{ width: '80px', height: '80px', objectFit: 'cover' }}
										/>
									</div>
									<div className="image-fields">
										<label>
											{__('Alt Text:', 'lightbox-gallery')}
											<input
												type="text"
												value={image.alt || ''}
												onChange={(e) => onUpdateImageField(index, 'alt', e.target.value)}
												placeholder={__('Describe this image...', 'lightbox-gallery')}
											/>
										</label>
										<label>
											{__('Caption:', 'lightbox-gallery')}
											<input
												type="text"
												value={image.caption || ''}
												onChange={(e) => onUpdateImageField(index, 'caption', e.target.value)}
												placeholder={__('Image caption...', 'lightbox-gallery')}
											/>
										</label>
										<Button
											onClick={() => onRemoveImage(index)}
											variant="secondary"
											isDestructive
											style={{ marginTop: '8px' }}
										>
											{__('Remove', 'lightbox-gallery')}
										</Button>
									</div>
								</div>
							))}
						</div>
					</div>
				</div>
			</div>
		</>
	);
};

export default Edit;