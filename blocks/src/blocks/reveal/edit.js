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
	RangeControl,
	SelectControl,
	FocalPointPicker,
	ColorPicker,
	Popover,
	TextControl,
	Notice,
	ToolbarGroup,
	ToolbarButton,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';

const TRANSITION_OPTIONS = [
	{ label: __( 'None', 'caes-reveal' ), value: 'none' },
	{ label: __( 'Fade', 'caes-reveal' ), value: 'fade' },
	{ label: __( 'Up', 'caes-reveal' ), value: 'up' },
	{ label: __( 'Down', 'caes-reveal' ), value: 'down' },
	{ label: __( 'Left', 'caes-reveal' ), value: 'left' },
	{ label: __( 'Right', 'caes-reveal' ), value: 'right' },
];

const DEFAULT_FRAME = {
	id: '',
	desktopImage: null,
	mobileImage: null,
	focalPoint: { x: 0.5, y: 0.5 },
	duotone: null,
	transition: {
		type: 'fade',
		speed: 500,
	},
};

const generateFrameId = () => {
	return 'frame-' + Math.random().toString( 36 ).substr( 2, 9 );
};

const Edit = ( { attributes, setAttributes } ) => {
	const { frames, overlayColor, overlayOpacity, minHeight } = attributes;
	const [ isPreviewMode, setIsPreviewMode ] = useState( false );
	const [ showOverlayColorPicker, setShowOverlayColorPicker ] = useState( false );

	// Auto-add first frame when block is inserted
	useEffect( () => {
		if ( frames.length === 0 ) {
			setAttributes( {
				frames: [ { ...DEFAULT_FRAME, id: generateFrameId() } ],
			} );
		}
	}, [] );

	// Add a new frame
	const addFrame = () => {
		const newFrame = {
			...DEFAULT_FRAME,
			id: generateFrameId(),
		};
		setAttributes( { frames: [ ...frames, newFrame ] } );
	};

	// Remove a frame
	const removeFrame = ( frameIndex ) => {
		const newFrames = [ ...frames ];
		newFrames.splice( frameIndex, 1 );
		setAttributes( { frames: newFrames } );
	};

	// Update a frame's properties
	const updateFrame = ( frameIndex, updates ) => {
		const newFrames = [ ...frames ];
		newFrames[ frameIndex ] = { ...newFrames[ frameIndex ], ...updates };
		setAttributes( { frames: newFrames } );
	};

	// Move frame up
	const moveFrameUp = ( frameIndex ) => {
		if ( frameIndex === 0 ) return;
		const newFrames = [ ...frames ];
		[ newFrames[ frameIndex - 1 ], newFrames[ frameIndex ] ] = [
			newFrames[ frameIndex ],
			newFrames[ frameIndex - 1 ],
		];
		setAttributes( { frames: newFrames } );
	};

	// Move frame down
	const moveFrameDown = ( frameIndex ) => {
		if ( frameIndex === frames.length - 1 ) return;
		const newFrames = [ ...frames ];
		[ newFrames[ frameIndex ], newFrames[ frameIndex + 1 ] ] = [
			newFrames[ frameIndex + 1 ],
			newFrames[ frameIndex ],
		];
		setAttributes( { frames: newFrames } );
	};

	// Handle image selection
	const onSelectImage = ( frameIndex, imageType, media ) => {
		const imageData = {
			id: media.id,
			url: media.url,
			alt: media.alt || '',
			caption: media.caption || '',
			sizes: media.sizes || {},
		};
		updateFrame( frameIndex, { [ imageType ]: imageData } );
	};

	// Handle image removal
	const onRemoveImage = ( frameIndex, imageType ) => {
		updateFrame( frameIndex, { [ imageType ]: null } );
	};

	// Calculate overlay rgba
	const getOverlayRgba = () => {
		const opacity = overlayOpacity / 100;
		const hex = overlayColor.replace( '#', '' );
		const r = parseInt( hex.substring( 0, 2 ), 16 );
		const g = parseInt( hex.substring( 2, 4 ), 16 );
		const b = parseInt( hex.substring( 4, 6 ), 16 );
		return `rgba(${ r }, ${ g }, ${ b }, ${ opacity })`;
	};

	// Get first frame's image for preview
	const firstFrame = frames.length > 0 ? frames[ 0 ] : null;
	const previewImage = firstFrame?.desktopImage?.url || null;

	const blockProps = useBlockProps( {
		className: 'caes-reveal-block',
		style: {
			'--reveal-min-height': minHeight,
		},
	} );

	// Color swatch button component
	const ColorSwatchButton = ( { color, onClick, label } ) => (
		<Button
			onClick={ onClick }
			style={ {
				width: '36px',
				height: '36px',
				padding: '0',
				border: '1px solid #949494',
				borderRadius: '4px',
				background: color,
				minWidth: '36px',
			} }
			aria-label={ label }
		/>
	);

	// Shared Inspector Controls (shown in both modes)
	const sharedInspectorControls = (
		<InspectorControls>
			<PanelBody title={ __( 'Overlay Settings', 'caes-reveal' ) }>
				<div style={ { display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '16px' } }>
					<span style={ { minWidth: '100px' } }>
						{ __( 'Overlay Color', 'caes-reveal' ) }
					</span>
					<div style={ { position: 'relative' } }>
						<ColorSwatchButton
							color={ overlayColor }
							onClick={ () => setShowOverlayColorPicker( ! showOverlayColorPicker ) }
							label={ __( 'Select overlay color', 'caes-reveal' ) }
						/>
						{ showOverlayColorPicker && (
							<Popover
								position="bottom left"
								onClose={ () => setShowOverlayColorPicker( false ) }
							>
								<div style={ { padding: '16px' } }>
									<ColorPicker
										color={ overlayColor }
										onChange={ ( color ) => setAttributes( { overlayColor: color } ) }
										enableAlpha={ false }
									/>
								</div>
							</Popover>
						) }
					</div>
				</div>
				<RangeControl
					label={ __( 'Overlay Opacity', 'caes-reveal' ) }
					value={ overlayOpacity }
					onChange={ ( value ) => setAttributes( { overlayOpacity: value } ) }
					min={ 0 }
					max={ 100 }
					step={ 5 }
				/>
			</PanelBody>

			<PanelBody title={ __( 'Layout', 'caes-reveal' ) } initialOpen={ false }>
				<SelectControl
					label={ __( 'Minimum Height', 'caes-reveal' ) }
					value={ minHeight }
					options={ [
						{ label: __( 'Full viewport (100vh)', 'caes-reveal' ), value: '100vh' },
						{ label: __( '75% viewport', 'caes-reveal' ), value: '75vh' },
						{ label: __( '50% viewport', 'caes-reveal' ), value: '50vh' },
						{ label: __( 'Auto (content height)', 'caes-reveal' ), value: 'auto' },
					] }
					onChange={ ( value ) => setAttributes( { minHeight: value } ) }
				/>
			</PanelBody>
		</InspectorControls>
	);

	// PREVIEW MODE
	if ( isPreviewMode ) {
		return (
			<>
				<BlockControls>
					<ToolbarGroup>
						<ToolbarButton
							onClick={ () => setIsPreviewMode( false ) }
							icon="edit"
						>
							{ __( 'Edit', 'caes-reveal' ) }
						</ToolbarButton>
					</ToolbarGroup>
				</BlockControls>

				{ sharedInspectorControls }

				<div { ...blockProps }>
					{ /* Background Preview */ }
					<div
						className="reveal-background-preview"
						style={ {
							position: 'absolute',
							inset: 0,
							zIndex: 0,
							overflow: 'hidden',
						} }
					>
						{ previewImage ? (
							<img
								src={ previewImage }
								alt=""
								style={ {
									width: '100%',
									height: '100%',
									objectFit: 'cover',
									objectPosition: firstFrame?.focalPoint
										? `${ firstFrame.focalPoint.x * 100 }% ${ firstFrame.focalPoint.y * 100 }%`
										: 'center',
								} }
							/>
						) : (
							<div
								style={ {
									width: '100%',
									height: '100%',
									backgroundColor: '#e0e0e0',
									display: 'flex',
									alignItems: 'center',
									justifyContent: 'center',
									color: '#666',
								} }
							>
								{ __( 'No frames added yet', 'caes-reveal' ) }
							</div>
						) }
						{ /* Overlay */ }
						<div
							className="reveal-overlay-preview"
							style={ {
								position: 'absolute',
								inset: 0,
								backgroundColor: getOverlayRgba(),
								pointerEvents: 'none',
							} }
						/>
					</div>

					{ /* Frame Indicator */ }
					{ frames.length > 1 && (
						<div
							style={ {
								position: 'absolute',
								top: '12px',
								right: '12px',
								backgroundColor: 'rgba(0, 0, 0, 0.7)',
								color: '#fff',
								padding: '4px 10px',
								borderRadius: '4px',
								fontSize: '12px',
								zIndex: 10,
							} }
						>
							{ frames.length } { __( 'frames', 'caes-reveal' ) }
						</div>
					) }

					{ /* Inner Content - Editable in Preview Mode */ }
					<div
						className="reveal-content-editor"
						style={ {
							position: 'relative',
							zIndex: 1,
							minHeight: '200px',
							padding: 'var(--wp--preset--spacing--50, 2rem)',
						} }
					>
						<InnerBlocks
							template={ [
								[ 'core/paragraph', { placeholder: __( 'Add content that will scroll over the background...', 'caes-reveal' ) } ],
							] }
							templateLock={ false }
						/>
					</div>
				</div>
			</>
		);
	}

	// EDIT MODE
	return (
		<>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						onClick={ () => setIsPreviewMode( true ) }
						icon="visibility"
					>
						{ __( 'Preview', 'caes-reveal' ) }
					</ToolbarButton>
				</ToolbarGroup>
			</BlockControls>

			{ sharedInspectorControls }

			<div { ...blockProps }>
				<div
					className="caes-reveal-editor"
					style={ {
						backgroundColor: '#fff',
						padding: '20px',
						minHeight: '300px',
					} }
				>
					{ /* Header */ }
					<div
						className="reveal-header"
						style={ {
							display: 'flex',
							justifyContent: 'space-between',
							alignItems: 'center',
							marginBottom: '20px',
							paddingBottom: '12px',
							borderBottom: '1px solid #ddd',
						} }
					>
						<h3 style={ { margin: 0, fontSize: '14px', fontWeight: 600, fontFamily: 'monospace' } }>
							{ __( 'Reveal Block', 'caes-reveal' ) }
						</h3>
						<div style={ { display: 'flex', gap: '8px' } }>
							<Button onClick={ () => setIsPreviewMode( true ) } variant="secondary">
								{ __( 'Preview', 'caes-reveal' ) }
							</Button>
							<Button onClick={ addFrame } variant="primary">
								{ __( 'Add Frame', 'caes-reveal' ) }
							</Button>
						</div>
					</div>

					{ /* Frames List */ }
					{ frames.length === 0 && (
						<Notice status="warning" isDismissible={ false }>
							{ __( 'Add a frame to set a background image.', 'caes-reveal' ) }
						</Notice>
					) }

					{ frames.map( ( frame, frameIndex ) => (
						<FrameEditor
							key={ frame.id }
							frame={ frame }
							frameIndex={ frameIndex }
							totalFrames={ frames.length }
							onUpdate={ ( updates ) => updateFrame( frameIndex, updates ) }
							onRemove={ () => removeFrame( frameIndex ) }
							onMoveUp={ () => moveFrameUp( frameIndex ) }
							onMoveDown={ () => moveFrameDown( frameIndex ) }
							onSelectImage={ ( imageType, media ) => onSelectImage( frameIndex, imageType, media ) }
							onRemoveImage={ ( imageType ) => onRemoveImage( frameIndex, imageType ) }
						/>
					) ) }
				</div>
			</div>
		</>
	);
};

// Frame Editor Component
const FrameEditor = ( {
	frame,
	frameIndex,
	totalFrames,
	onUpdate,
	onRemove,
	onMoveUp,
	onMoveDown,
	onSelectImage,
	onRemoveImage,
} ) => {
	const [ isExpanded, setIsExpanded ] = useState( false );

	return (
		<div
			className="reveal-frame-editor"
			style={ {
				border: '1px solid #ddd',
				borderRadius: '4px',
				padding: '16px',
				marginBottom: '16px',
				backgroundColor: '#f9f9f9',
			} }
		>
			{ /* Frame Header */ }
			<div
				className="frame-header"
				style={ {
					display: 'flex',
					justifyContent: 'space-between',
					alignItems: 'center',
					marginBottom: isExpanded ? '16px' : 0,
					paddingBottom: isExpanded ? '12px' : 0,
					borderBottom: isExpanded ? '1px solid #ddd' : 'none',
				} }
			>
				<div style={ { display: 'flex', alignItems: 'center', gap: '12px' } }>
					{ /* Thumbnail */ }
					<div
						style={ {
							width: '48px',
							height: '48px',
							backgroundColor: '#ddd',
							borderRadius: '4px',
							overflow: 'hidden',
							flexShrink: 0,
						} }
					>
						{ frame.desktopImage && (
							<img
								src={ frame.desktopImage.sizes?.thumbnail?.url || frame.desktopImage.url }
								alt=""
								style={ {
									width: '100%',
									height: '100%',
									objectFit: 'cover',
								} }
							/>
						) }
					</div>
					<div>
						<strong>{ __( 'Frame', 'caes-reveal' ) } { frameIndex + 1 }</strong>
						<div style={ { fontSize: '12px', color: '#666' } }>
							{ frame.transition.type !== 'none'
								? `${ frame.transition.type } (${ frame.transition.speed }ms)`
								: __( 'No transition', 'caes-reveal' )
							}
						</div>
					</div>
				</div>
				<div style={ { display: 'flex', gap: '8px' } }>
					<Button
						onClick={ () => setIsExpanded( ! isExpanded ) }
						variant="secondary"
						icon={ isExpanded ? 'arrow-up-alt2' : 'arrow-down-alt2' }
						label={ isExpanded ? __( 'Collapse', 'caes-reveal' ) : __( 'Expand', 'caes-reveal' ) }
					/>
					<Button
						onClick={ onMoveUp }
						variant="secondary"
						disabled={ frameIndex === 0 }
						icon="arrow-up-alt"
						label={ __( 'Move Up', 'caes-reveal' ) }
					/>
					<Button
						onClick={ onMoveDown }
						variant="secondary"
						disabled={ frameIndex === totalFrames - 1 }
						icon="arrow-down-alt"
						label={ __( 'Move Down', 'caes-reveal' ) }
					/>
					<Button
						onClick={ onRemove }
						variant="secondary"
						isDestructive
						disabled={ totalFrames === 1 }
						icon="trash"
						label={ __( 'Remove Frame', 'caes-reveal' ) }
					/>
				</div>
			</div>

			{ /* Expanded Content */ }
			{ isExpanded && (
				<div className="frame-content">
					{ /* Desktop Image */ }
					<div style={ { marginBottom: '20px' } }>
						<label style={ { display: 'block', marginBottom: '8px', fontWeight: 500 } }>
							{ __( 'Desktop Image', 'caes-reveal' ) }
							<span style={ { color: '#cc0000' } }> *</span>
						</label>
						<MediaUploadCheck>
							<MediaUpload
								onSelect={ ( media ) => onSelectImage( 'desktopImage', media ) }
								allowedTypes={ [ 'image' ] }
								value={ frame.desktopImage?.id }
								render={ ( { open } ) => (
									<>
										{ frame.desktopImage ? (
											<div>
												<img
													src={ frame.desktopImage.sizes?.medium?.url || frame.desktopImage.url }
													alt={ frame.desktopImage.alt }
													style={ {
														maxWidth: '100%',
														maxHeight: '200px',
														borderRadius: '4px',
														marginBottom: '8px',
													} }
												/>
												<div style={ { display: 'flex', gap: '8px' } }>
													<Button variant="secondary" onClick={ open }>
														{ __( 'Replace', 'caes-reveal' ) }
													</Button>
													<Button
														variant="secondary"
														isDestructive
														onClick={ () => onRemoveImage( 'desktopImage' ) }
													>
														{ __( 'Remove', 'caes-reveal' ) }
													</Button>
												</div>
											</div>
										) : (
											<Button variant="secondary" onClick={ open }>
												{ __( 'Select Image', 'caes-reveal' ) }
											</Button>
										) }
									</>
								) }
							/>
						</MediaUploadCheck>
						{ frame.desktopImage && (
							<TextControl
								label={ __( 'Alt Text', 'caes-reveal' ) }
								value={ frame.desktopImage.alt || '' }
								onChange={ ( value ) => {
									const updatedImage = { ...frame.desktopImage, alt: value };
									onUpdate( { desktopImage: updatedImage } );
								} }
								style={ { marginTop: '12px' } }
							/>
						) }
					</div>

					{ /* Mobile Image */ }
					<div style={ { marginBottom: '20px' } }>
						<label style={ { display: 'block', marginBottom: '8px', fontWeight: 500 } }>
							{ __( 'Mobile Image (optional)', 'caes-reveal' ) }
						</label>
						<MediaUploadCheck>
							<MediaUpload
								onSelect={ ( media ) => onSelectImage( 'mobileImage', media ) }
								allowedTypes={ [ 'image' ] }
								value={ frame.mobileImage?.id }
								render={ ( { open } ) => (
									<>
										{ frame.mobileImage ? (
											<div>
												<img
													src={ frame.mobileImage.sizes?.thumbnail?.url || frame.mobileImage.url }
													alt={ frame.mobileImage.alt }
													style={ {
														width: '80px',
														height: '80px',
														objectFit: 'cover',
														borderRadius: '4px',
														marginBottom: '8px',
													} }
												/>
												<div style={ { display: 'flex', gap: '8px' } }>
													<Button variant="secondary" onClick={ open }>
														{ __( 'Replace', 'caes-reveal' ) }
													</Button>
													<Button
														variant="secondary"
														isDestructive
														onClick={ () => onRemoveImage( 'mobileImage' ) }
													>
														{ __( 'Remove', 'caes-reveal' ) }
													</Button>
												</div>
											</div>
										) : (
											<Button variant="secondary" onClick={ open }>
												{ __( 'Select Mobile Image', 'caes-reveal' ) }
											</Button>
										) }
									</>
								) }
							/>
						</MediaUploadCheck>
						{ frame.mobileImage && (
							<TextControl
								label={ __( 'Alt Text', 'caes-reveal' ) }
								value={ frame.mobileImage.alt || '' }
								onChange={ ( value ) => {
									const updatedImage = { ...frame.mobileImage, alt: value };
									onUpdate( { mobileImage: updatedImage } );
								} }
								style={ { marginTop: '12px' } }
							/>
						) }
					</div>

					{ /* Focal Point */ }
					{ frame.desktopImage && (
						<div style={ { marginBottom: '20px' } }>
							<label style={ { display: 'block', marginBottom: '8px', fontWeight: 500 } }>
								{ __( 'Focal Point', 'caes-reveal' ) }
							</label>
							<FocalPointPicker
								url={ frame.desktopImage.url }
								value={ frame.focalPoint }
								onChange={ ( value ) => onUpdate( { focalPoint: value } ) }
							/>
						</div>
					) }

					{ /* Transition Settings */ }
					<div
						style={ {
							paddingTop: '16px',
							borderTop: '1px solid #ddd',
						} }
					>
						<label style={ { display: 'block', marginBottom: '8px', fontWeight: 500 } }>
							{ __( 'Transition', 'caes-reveal' ) }
						</label>
						<SelectControl
							value={ frame.transition.type }
							options={ TRANSITION_OPTIONS }
							onChange={ ( value ) =>
								onUpdate( {
									transition: { ...frame.transition, type: value },
								} )
							}
						/>
						{ frame.transition.type !== 'none' && (
							<RangeControl
								label={ __( 'Speed (ms)', 'caes-reveal' ) }
								value={ frame.transition.speed }
								onChange={ ( value ) =>
									onUpdate( {
										transition: { ...frame.transition, speed: value },
									} )
								}
								min={ 100 }
								max={ 2000 }
								step={ 50 }
							/>
						) }
					</div>
				</div>
			) }
		</div>
	);
};

export default Edit;