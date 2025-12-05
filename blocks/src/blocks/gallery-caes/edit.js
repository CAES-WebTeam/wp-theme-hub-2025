import { __ } from '@wordpress/i18n';
import { useBlockProps, MediaUpload, MediaUploadCheck, BlockControls, InspectorControls } from '@wordpress/block-editor';
import { Button, PanelBody, SelectControl, Notice, ToolbarGroup, ToolbarButton, ToggleControl, ColorPicker, __experimentalHStack as HStack, __experimentalVStack as VStack, Popover } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';

const Edit = ({ attributes, setAttributes }) => {
	const { rows, cropImages, showCaptions, captionTextColor, captionBackgroundColor, useThumbnailTrigger } = attributes;
	const [isPreviewMode, setIsPreviewMode] = useState(false);
	const [showTextColorPicker, setShowTextColorPicker] = useState(false);
	const [showBgColorPicker, setShowBgColorPicker] = useState(false);

	// Auto-add first row when block is inserted
	useEffect(() => {
		if (rows.length === 0) {
			setAttributes({ rows: [{ columns: 3, images: [] }] });
		}
	}, []);

	// Get all images from all rows (flattened)
	const getAllImages = () => {
		return rows.reduce((acc, row) => {
			return acc.concat(row.images || []);
		}, []);
	};

	// Add a new row
	const addRow = () => {
		const newRows = [...rows, { columns: 3, images: [] }];
		setAttributes({ rows: newRows });
	};

	// Remove a row
	const removeRow = (rowIndex) => {
		const newRows = [...rows];
		newRows.splice(rowIndex, 1);
		setAttributes({ rows: newRows });
	};

	// Move row up
	const moveRowUp = (rowIndex) => {
		if (rowIndex === 0) return;
		const newRows = [...rows];
		[newRows[rowIndex - 1], newRows[rowIndex]] = [newRows[rowIndex], newRows[rowIndex - 1]];
		setAttributes({ rows: newRows });
	};

	// Move row down
	const moveRowDown = (rowIndex) => {
		if (rowIndex === rows.length - 1) return;
		const newRows = [...rows];
		[newRows[rowIndex], newRows[rowIndex + 1]] = [newRows[rowIndex + 1], newRows[rowIndex]];
		setAttributes({ rows: newRows });
	};

	// Update column count for a row
	const updateRowColumns = (rowIndex, columns) => {
		const newRows = [...rows];
		newRows[rowIndex].columns = parseInt(columns);
		setAttributes({ rows: newRows });
	};

	// Select images for a row
	const onSelectImages = (rowIndex, media) => {
		const newRows = [...rows];
		newRows[rowIndex].images = media.map((item) => ({
			id: item.id,
			url: item.url,
			alt: item.alt || '',
			caption: item.caption || '',
			sizes: item.sizes || {}
		}));
		setAttributes({ rows: newRows });
	};

	// Update image field
	const onUpdateImageField = (rowIndex, imageIndex, field, value) => {
		const newRows = [...rows];
		newRows[rowIndex].images[imageIndex][field] = value;
		setAttributes({ rows: newRows });
	};

	// Remove image from row
	const onRemoveImage = (rowIndex, imageIndex) => {
		const newRows = [...rows];
		newRows[rowIndex].images.splice(imageIndex, 1);
		setAttributes({ rows: newRows });
	};

	// Color swatch button component
	const ColorSwatchButton = ({ color, onClick, label }) => (
		<Button
			onClick={onClick}
			style={{
				width: '36px',
				height: '36px',
				padding: '0',
				border: '1px solid #949494',
				borderRadius: '4px',
				background: color,
				minWidth: '36px'
			}}
			aria-label={label}
		/>
	);

	const blockProps = useBlockProps({
		className: 'caes-gallery-block'
	});

	// Caption overlay styles for editor
	const captionOverlayStyle = {
		position: 'absolute',
		bottom: 0,
		left: 0,
		right: 0,
		padding: '8px 12px',
		color: captionTextColor,
		backgroundColor: captionBackgroundColor,
		fontSize: '14px',
		lineHeight: '1.4',
		margin: 0
	};

	// View Gallery bar style
	const viewGalleryBarStyle = {
		position: 'absolute',
		bottom: 0,
		left: '50%',
		transform: 'translateX(-50%)',
		width: '100%',
		backgroundColor: '#000',
		color: '#fff',
		padding: '1rem',
		textAlign: 'left',
		fontSize: '1rem',
		fontFamily: 'inherit',
		border: 0
	};

	// Get first image for thumbnail trigger mode
	const allImages = getAllImages();
	const firstImage = allImages[0];

	// Thumbnail trigger preview component (reused in both modes)
	const ThumbnailTriggerPreview = () => (
		<div style={{ position: 'relative' }}>
			<img
				src={firstImage.sizes?.large?.url || firstImage.url}
				alt={firstImage.alt || ''}
				style={{
					width: '100%',
					height: 'auto',
					display: 'block'
				}}
			/>
			<div style={viewGalleryBarStyle}>
				{__('View Gallery', 'caes-gallery')}
			</div>
		</div>
	);

	// Preview Mode - Shows frontend appearance
	if (isPreviewMode) {
		return (
			<>
				<BlockControls>
					<ToolbarGroup>
						<ToolbarButton
							onClick={() => setIsPreviewMode(false)}
							icon="edit"
						>
							{__('Edit', 'caes-gallery')}
						</ToolbarButton>
					</ToolbarGroup>
				</BlockControls>

				<InspectorControls>
					<PanelBody title={__('Gallery Settings', 'caes-gallery')}>
						<ToggleControl
							label={__('Use thumbnail trigger', 'caes-gallery')}
							checked={useThumbnailTrigger}
							onChange={(value) => setAttributes({ useThumbnailTrigger: value })}
							help={__('Show a single image with a "View Gallery" button that opens the full gallery.', 'caes-gallery')}
						/>
						{!useThumbnailTrigger && (
							<ToggleControl
								label={__('Crop images to fit', 'caes-gallery')}
								checked={cropImages}
								onChange={(value) => setAttributes({ cropImages: value })}
								help={__('Images are cropped to maintain a consistent height and eliminate gaps.', 'caes-gallery')}
							/>
						)}
					</PanelBody>
					{!useThumbnailTrigger && (
						<PanelBody title={__('Caption Settings', 'caes-gallery')}>
							<ToggleControl
								label={__('Show captions on thumbnails', 'caes-gallery')}
								checked={showCaptions}
								onChange={(value) => setAttributes({ showCaptions: value })}
								help={__('Display image captions as overlays on the thumbnail images.', 'caes-gallery')}
							/>
							{showCaptions && (
								<VStack spacing={4} style={{ marginTop: '16px' }}>
									<HStack alignment="left">
										<span style={{ minWidth: '120px' }}>{__('Text Color', 'caes-gallery')}</span>
										<div style={{ position: 'relative' }}>
											<ColorSwatchButton
												color={captionTextColor}
												onClick={() => setShowTextColorPicker(!showTextColorPicker)}
												label={__('Select text color', 'caes-gallery')}
											/>
											{showTextColorPicker && (
												<Popover
													position="bottom left"
													onClose={() => setShowTextColorPicker(false)}
												>
													<div style={{ padding: '16px' }}>
														<ColorPicker
															color={captionTextColor}
															onChange={(color) => setAttributes({ captionTextColor: color })}
															enableAlpha={true}
														/>
													</div>
												</Popover>
											)}
										</div>
									</HStack>
									<HStack alignment="left">
										<span style={{ minWidth: '120px' }}>{__('Background Color', 'caes-gallery')}</span>
										<div style={{ position: 'relative' }}>
											<ColorSwatchButton
												color={captionBackgroundColor}
												onClick={() => setShowBgColorPicker(!showBgColorPicker)}
												label={__('Select background color', 'caes-gallery')}
											/>
											{showBgColorPicker && (
												<Popover
													position="bottom left"
													onClose={() => setShowBgColorPicker(false)}
												>
													<div style={{ padding: '16px' }}>
														<ColorPicker
															color={captionBackgroundColor}
															onChange={(color) => setAttributes({ captionBackgroundColor: color })}
															enableAlpha={true}
														/>
													</div>
												</Popover>
											)}
										</div>
									</HStack>
								</VStack>
							)}
						</PanelBody>
					)}
				</InspectorControls>

				<div {...blockProps}>
					{/* Thumbnail Trigger Preview */}
					{useThumbnailTrigger && firstImage ? (
						<ThumbnailTriggerPreview />
					) : (
						/* Standard Gallery Preview */
						<div className="caes-gallery-preview">
							{rows.map((row, rowIndex) => {
								const columns = row.columns ?? 3;
								const images = row.images ?? [];

								if (images.length === 0) {
									return null;
								}

								return (
									<div
										key={rowIndex}
										className={`gallery-row gallery-row-${columns}-cols${cropImages ? ' is-cropped' : ''}${showCaptions ? ' has-captions' : ''}`}
										style={!cropImages ? {
											display: 'grid',
											gridTemplateColumns: `repeat(${columns}, 1fr)`,
											gap: '1rem',
											marginBottom: '1rem'
										} : {
											marginBottom: '1rem'
										}}
									>
										{images.map((image) => (
											<div key={image.id} className="gallery-item">
												<div style={{
													display: 'block',
													overflow: 'hidden',
													cursor: 'pointer',
													position: 'relative'
												}}>
													<img
														src={image.url}
														alt={image.alt || ''}
														style={{
															width: '100%',
															height: cropImages ? '100%' : 'auto',
															display: 'block',
															objectFit: cropImages ? 'cover' : 'initial'
														}}
													/>
													{showCaptions && image.caption && (
														<figcaption style={captionOverlayStyle}>
															{image.caption}
														</figcaption>
													)}
												</div>
											</div>
										))}
									</div>
								);
							})}
						</div>
					)}
				</div>
			</>
		);
	}

	// Edit Mode
	return (
		<>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						onClick={() => setIsPreviewMode(true)}
						icon="visibility"
					>
						{__('Preview', 'caes-gallery')}
					</ToolbarButton>
				</ToolbarGroup>
			</BlockControls>

			<InspectorControls>
				<PanelBody title={__('Gallery Settings', 'caes-gallery')}>
					<ToggleControl
						label={__('Use thumbnail trigger', 'caes-gallery')}
						checked={useThumbnailTrigger}
						onChange={(value) => setAttributes({ useThumbnailTrigger: value })}
						help={__('Show a single image with a "View Gallery" button that opens the full gallery.', 'caes-gallery')}
					/>
					{!useThumbnailTrigger && (
						<ToggleControl
							label={__('Crop images to fit', 'caes-gallery')}
							checked={cropImages}
							onChange={(value) => setAttributes({ cropImages: value })}
							help={__('Images are cropped to maintain a consistent height and eliminate gaps.', 'caes-gallery')}
						/>
					)}
				</PanelBody>
				{!useThumbnailTrigger && (
					<PanelBody title={__('Caption Settings', 'caes-gallery')}>
						<ToggleControl
							label={__('Show captions on thumbnails', 'caes-gallery')}
							checked={showCaptions}
							onChange={(value) => setAttributes({ showCaptions: value })}
							help={__('Display image captions as overlays on the thumbnail images.', 'caes-gallery')}
						/>
						{showCaptions && (
							<VStack spacing={4} style={{ marginTop: '16px' }}>
								<HStack alignment="left">
									<span style={{ minWidth: '120px' }}>{__('Text Color', 'caes-gallery')}</span>
									<div style={{ position: 'relative' }}>
										<ColorSwatchButton
											color={captionTextColor}
											onClick={() => setShowTextColorPicker(!showTextColorPicker)}
											label={__('Select text color', 'caes-gallery')}
										/>
										{showTextColorPicker && (
											<Popover
												position="bottom left"
												onClose={() => setShowTextColorPicker(false)}
											>
												<div style={{ padding: '16px' }}>
													<ColorPicker
														color={captionTextColor}
														onChange={(color) => setAttributes({ captionTextColor: color })}
														enableAlpha={true}
													/>
												</div>
											</Popover>
										)}
									</div>
								</HStack>
								<HStack alignment="left">
									<span style={{ minWidth: '120px' }}>{__('Background Color', 'caes-gallery')}</span>
									<div style={{ position: 'relative' }}>
										<ColorSwatchButton
											color={captionBackgroundColor}
											onClick={() => setShowBgColorPicker(!showBgColorPicker)}
											label={__('Select background color', 'caes-gallery')}
										/>
										{showBgColorPicker && (
											<Popover
												position="bottom left"
												onClose={() => setShowBgColorPicker(false)}
											>
												<div style={{ padding: '16px' }}>
													<ColorPicker
														color={captionBackgroundColor}
														onChange={(color) => setAttributes({ captionBackgroundColor: color })}
														enableAlpha={true}
													/>
												</div>
											</Popover>
										)}
									</div>
								</HStack>
							</VStack>
						)}
					</PanelBody>
				)}
			</InspectorControls>

			<div {...blockProps}>
				<div className="caes-gallery-editor">
					<div className="gallery-header">
						<h3>{__('Gallery (CAES) Setup', 'caes-gallery')}</h3>
						<div style={{ display: 'flex', gap: '8px' }}>
							<Button
								onClick={() => setIsPreviewMode(!isPreviewMode)}
								variant="secondary"
								icon={isPreviewMode ? 'edit' : 'visibility'}
							>
								{isPreviewMode ? __('Edit', 'caes-gallery') : __('Preview', 'caes-gallery')}
							</Button>
							<Button onClick={addRow} variant="primary">
								{__('Add Row', 'caes-gallery')}
							</Button>
						</div>
					</div>

					{/* Live Thumbnail Trigger Preview */}
					{useThumbnailTrigger && firstImage && (
						<>
							<p style={{
								margin: '0 0 12px 0',
								color: '#1e1e1e',
								fontSize: '13px'
							}}>
								{__('Thumbnail trigger is on. The first image will be used to open the gallery.', 'caes-gallery')}
							</p>
							<div style={{ marginBottom: '20px' }}>
								<ThumbnailTriggerPreview />
							</div>
						</>
					)}

					{useThumbnailTrigger && !firstImage && (
						<Notice status="warning" isDismissible={false} style={{ marginBottom: '16px' }}>
							{__('Add images to a row below to see the thumbnail trigger preview.', 'caes-gallery')}
						</Notice>
					)}

					{rows.length === 0 && (
						<Notice status="warning" isDismissible={false}>
							{__('Add a row to start building your gallery.', 'caes-gallery')}
						</Notice>
					)}

					{rows.map((row, rowIndex) => (
						<div key={rowIndex} className="gallery-row-editor" style={{
							border: '1px solid #ddd',
							borderRadius: '4px',
							padding: '16px',
							marginBottom: '16px',
							backgroundColor: '#f9f9f9'
						}}>
							{/* Row Header */}
							<div className="row-header" style={{
								display: 'flex',
								justifyContent: 'space-between',
								alignItems: 'center',
								marginBottom: '16px',
								paddingBottom: '12px',
								borderBottom: '1px solid #ddd'
							}}>
								<div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
									<strong>{__('Row', 'caes-gallery')} {rowIndex + 1}</strong>
									{!useThumbnailTrigger && (
										<SelectControl
											label={__('Columns', 'caes-gallery')}
											value={row.columns}
											options={[
												{ label: '1', value: 1 },
												{ label: '2', value: 2 },
												{ label: '3', value: 3 },
												{ label: '4', value: 4 },
												{ label: '5', value: 5 },
												{ label: '6', value: 6 }
											]}
											onChange={(value) => updateRowColumns(rowIndex, value)}
											style={{ marginBottom: 0 }}
										/>
									)}
								</div>
								<div style={{ display: 'flex', gap: '8px' }}>
									<Button
										onClick={() => moveRowUp(rowIndex)}
										variant="secondary"
										disabled={rowIndex === 0}
										icon="arrow-up-alt2"
										label={__('Move Up', 'caes-gallery')}
									/>
									<Button
										onClick={() => moveRowDown(rowIndex)}
										variant="secondary"
										disabled={rowIndex === rows.length - 1}
										icon="arrow-down-alt2"
										label={__('Move Down', 'caes-gallery')}
									/>
									<Button
										onClick={() => removeRow(rowIndex)}
										variant="secondary"
										isDestructive
										disabled={rows.length === 1}
										icon="trash"
										label={__('Remove Row', 'caes-gallery')}
									/>
								</div>
							</div>

							{/* Media Upload */}
							<div style={{ marginBottom: '16px' }}>
								<MediaUploadCheck>
									<MediaUpload
										onSelect={(media) => onSelectImages(rowIndex, media)}
										allowedTypes={['image']}
										multiple={true}
										gallery={true}
										value={row.images.map(img => img.id)}
										render={({ open }) => (
											<Button onClick={open} variant="secondary">
												{row.images.length > 0
													? __('Edit Images', 'caes-gallery')
													: __('Add Images', 'caes-gallery')
												}
											</Button>
										)}
									/>
								</MediaUploadCheck>
								<span style={{ marginLeft: '12px', color: '#666' }}>
									{row.images.length} {row.images.length === 1 ? __('image', 'caes-gallery') : __('images', 'caes-gallery')}
								</span>
							</div>

							{/* Image Preview Grid - Matches Preview Mode */}
							{row.images.length > 0 && (
								<div
									className={`gallery-row gallery-row-${row.columns}-cols${cropImages ? ' is-cropped' : ''}${showCaptions ? ' has-captions' : ''}`}
									style={!cropImages ? {
										display: 'grid',
										gridTemplateColumns: `repeat(${row.columns}, 1fr)`,
										gap: '1rem',
										marginBottom: '1rem'
									} : {
										marginBottom: '1rem'
									}}
								>
									{row.images.map((image, imageIndex) => (
										<div key={image.id} className="gallery-item" style={{ position: 'relative' }}>
											<div style={{
												display: 'block',
												overflow: 'hidden',
												position: 'relative'
											}}>
												<img
													src={image.url}
													alt={image.alt || ''}
													style={{
														width: '100%',
														height: cropImages ? '100%' : 'auto',
														display: 'block',
														objectFit: cropImages ? 'cover' : 'initial'
													}}
												/>
												{showCaptions && image.caption && !useThumbnailTrigger && (
													<figcaption style={captionOverlayStyle}>
														{image.caption}
													</figcaption>
												)}
											</div>
											<Button
												onClick={() => onRemoveImage(rowIndex, imageIndex)}
												variant="secondary"
												isDestructive
												icon="no"
												label={__('Remove image', 'caes-gallery')}
												style={{
													position: 'absolute',
													top: '4px',
													right: '4px',
													minWidth: 'auto',
													padding: '4px',
													backgroundColor: 'rgba(255, 255, 255, 0.9)',
													zIndex: 10
												}}
											/>
										</div>
									))}
								</div>
							)}

							{/* Image Details Editor */}
							{row.images.length > 0 && (
								<details style={{ marginTop: '16px' }}>
									<summary style={{ cursor: 'pointer', fontWeight: 500, marginBottom: '12px' }}>
										{__('Edit Image Details (Alt Text & Captions)', 'caes-gallery')}
									</summary>
									<div className="images-details-editor">
										{row.images.map((image, imageIndex) => (
											<div key={image.id} className="image-detail-item" style={{
												display: 'flex',
												gap: '12px',
												padding: '12px',
												backgroundColor: '#fff',
												border: '1px solid #ddd',
												borderRadius: '4px',
												marginBottom: '8px'
											}}>
												<div style={{ flexShrink: 0 }}>
													<img
														src={image.sizes?.thumbnail?.url || image.url}
														alt=""
														style={{
															width: '60px',
															height: '60px',
															objectFit: 'cover',
															borderRadius: '4px'
														}}
													/>
												</div>
												<div style={{ flexGrow: 1 }}>
													<label style={{ display: 'block', marginBottom: '8px' }}>
														<strong>{__('Alt Text:', 'caes-gallery')}</strong>
														<input
															type="text"
															value={image.alt || ''}
															onChange={(e) => onUpdateImageField(rowIndex, imageIndex, 'alt', e.target.value)}
															placeholder={__('Describe this image...', 'caes-gallery')}
															style={{
																width: '100%',
																marginTop: '4px',
																padding: '6px 8px'
															}}
														/>
													</label>
													<label style={{ display: 'block' }}>
														<strong>{__('Caption:', 'caes-gallery')}</strong>
														<input
															type="text"
															value={image.caption || ''}
															onChange={(e) => onUpdateImageField(rowIndex, imageIndex, 'caption', e.target.value)}
															placeholder={__('Image caption...', 'caes-gallery')}
															style={{
																width: '100%',
																marginTop: '4px',
																padding: '6px 8px'
															}}
														/>
													</label>
												</div>
											</div>
										))}
									</div>
								</details>
							)}
						</div>
					))}
				</div>
			</div>
		</>
	);
};

export default Edit;