import { __ } from '@wordpress/i18n';
import { useBlockProps, MediaUpload, MediaUploadCheck, BlockControls, InspectorControls } from '@wordpress/block-editor';
import { Button, Flex, FlexItem, PanelBody, SelectControl, Notice, ToolbarGroup, ToolbarButton, ToggleControl } from '@wordpress/components';
import { useState } from '@wordpress/element';

const Edit = ({ attributes, setAttributes }) => {
	const { rows, cropImages } = attributes;
	const [isPreviewMode, setIsPreviewMode] = useState(false);

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

	const blockProps = useBlockProps({
		className: 'caes-gallery-block'
	});

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
							label={__('Crop images to fit', 'caes-gallery')}
							checked={cropImages}
							onChange={(value) => setAttributes({ cropImages: value })}
							help={__('Images are cropped to maintain a consistent height and eliminate gaps.', 'caes-gallery')}
						/>
					</PanelBody>
				</InspectorControls>

				<div {...blockProps}>
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
									className={`gallery-row gallery-row-${columns}-cols${cropImages ? ' is-cropped' : ''}`}
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
												cursor: 'pointer'
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
											</div>
										</div>
									))}
								</div>
							);
						})}
					</div>
				</div>
			</>
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
						{__('Preview', 'caes-gallery')}
					</ToolbarButton>
				</ToolbarGroup>
			</BlockControls>

			<InspectorControls>
				<PanelBody title={__('Gallery Settings', 'caes-gallery')}>
					<ToggleControl
						label={__('Crop images to fit', 'caes-gallery')}
						checked={cropImages}
						onChange={(value) => setAttributes({ cropImages: value })}
						help={__('Images are cropped to maintain a consistent height and eliminate gaps.', 'caes-gallery')}
					/>
					<p style={{ marginTop: '12px', fontSize: '13px', color: '#757575' }}>
						{__('Configure columns per row in the block editor.', 'caes-gallery')}
					</p>
				</PanelBody>
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

							{/* Image Preview Grid */}
							{row.images.length > 0 && (
								<div className="row-images-preview" style={{
									display: 'grid',
									gridTemplateColumns: `repeat(${row.columns}, 1fr)`,
									gap: '12px',
									marginBottom: '16px'
								}}>
									{row.images.map((image, imageIndex) => (
										<div key={image.id} className="image-preview-item" style={{
											position: 'relative',
											backgroundColor: '#fff',
											border: '1px solid #ddd',
											borderRadius: '4px',
											overflow: 'hidden',
											display: 'flex',
											alignItems: 'flex-start'
										}}>
											<img
												src={image.url}
												alt={image.alt || ''}
												style={{
													width: '100%',
													height: cropImages ? '200px' : 'auto',
													objectFit: cropImages ? 'cover' : 'initial',
													display: 'block'
												}}
											/>
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
													backgroundColor: 'rgba(255, 255, 255, 0.9)'
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