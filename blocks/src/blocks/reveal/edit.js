import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InnerBlocks,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
	useSetting,
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
	DuotonePicker,
	__experimentalHStack as HStack,
} from '@wordpress/components';
import { useState } from '@wordpress/element';

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
	const [ expandedFrame, setExpandedFrame ] = useState( null );
	const [ showOverlayColorPicker, setShowOverlayColorPicker ] = useState( false );

	// Get theme duotone palettes
	const duotonePalette = useSetting( 'color.duotone' ) || [];

	// Add a new frame
	const addFrame = () => {
		const newFrame = {
			...DEFAULT_FRAME,
			id: generateFrameId(),
		};
		setAttributes( { frames: [ ...frames, newFrame ] } );
		setExpandedFrame( frames.length );
	};

	// Remove a frame
	const removeFrame = ( frameIndex ) => {
		const newFrames = [ ...frames ];
		newFrames.splice( frameIndex, 1 );
		setAttributes( { frames: newFrames } );
		if ( expandedFrame === frameIndex ) {
			setExpandedFrame( null );
		}
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
		setExpandedFrame( frameIndex - 1 );
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
		setExpandedFrame( frameIndex + 1 );
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
		// Parse hex color
		const hex = overlayColor.replace( '#', '' );
		const r = parseInt( hex.substring( 0, 2 ), 16 );
		const g = parseInt( hex.substring( 2, 4 ), 16 );
		const b = parseInt( hex.substring( 4, 6 ), 16 );
		return `rgba(${ r }, ${ g }, ${ b }, ${ opacity })`;
	};

	// Get first frame's image for preview
	const previewImage = frames.length > 0 && frames[ 0 ].desktopImage
		? frames[ 0 ].desktopImage.url
		: null;

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

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Overlay Settings', 'caes-reveal' ) }>
					<div style={ { display: 'flex', flexDirection: 'column', gap: '16px' } }>
						<div style={ { display: 'flex', alignItems: 'center', gap: '12px' } }>
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
					</div>
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

				<PanelBody title={ __( 'Frames', 'caes-reveal' ) } initialOpen={ true }>
					{ frames.length === 0 && (
						<Notice status="warning" isDismissible={ false }>
							{ __( 'Add at least one frame to display a background.', 'caes-reveal' ) }
						</Notice>
					) }

					<div style={ { display: 'flex', flexDirection: 'column', gap: '12px' } }>
						{ frames.map( ( frame, index ) => (
							<div
								key={ frame.id }
								className="reveal-frame-item"
								style={ {
									border: '1px solid #ddd',
									borderRadius: '4px',
									overflow: 'hidden',
									backgroundColor: '#f9f9f9',
								} }
							>
								{ /* Frame Header */ }
								<button
									type="button"
									onClick={ () => setExpandedFrame( expandedFrame === index ? null : index ) }
									style={ {
										display: 'flex',
										alignItems: 'center',
										gap: '12px',
										width: '100%',
										padding: '12px',
										border: 'none',
										background: 'none',
										cursor: 'pointer',
										textAlign: 'left',
									} }
								>
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
									<div style={ { flexGrow: 1 } }>
										<strong>
											{ __( 'Frame', 'caes-reveal' ) } { index + 1 }
										</strong>
										<div style={ { fontSize: '12px', color: '#666' } }>
											{ frame.transition.type !== 'none'
												? `${ frame.transition.type } (${ frame.transition.speed }ms)`
												: __( 'No transition', 'caes-reveal' )
											}
										</div>
									</div>
									<span style={ { fontSize: '20px' } }>
										{ expandedFrame === index ? '−' : '+' }
									</span>
								</button>

								{ /* Expanded Content */ }
								{ expandedFrame === index && (
									<div style={ { padding: '12px', borderTop: '1px solid #ddd' } }>
										<div style={ { display: 'flex', flexDirection: 'column', gap: '16px' } }>
											{ /* Desktop Image */ }
											<div>
												<label style={ { display: 'block', marginBottom: '8px', fontWeight: 500 } }>
													{ __( 'Desktop Image', 'caes-reveal' ) }
													<span style={ { color: '#cc0000' } }> *</span>
												</label>
												<MediaUploadCheck>
													<MediaUpload
														onSelect={ ( media ) => onSelectImage( index, 'desktopImage', media ) }
														allowedTypes={ [ 'image' ] }
														value={ frame.desktopImage?.id }
														render={ ( { open } ) => (
															<>
																{ frame.desktopImage ? (
																	<div style={ { position: 'relative' } }>
																		<img
																			src={ frame.desktopImage.sizes?.medium?.url || frame.desktopImage.url }
																			alt={ frame.desktopImage.alt }
																			style={ {
																				width: '100%',
																				height: 'auto',
																				borderRadius: '4px',
																			} }
																		/>
																		<HStack spacing={ 2 } style={ { marginTop: '8px' } }>
																			<Button variant="secondary" onClick={ open } size="small">
																				{ __( 'Replace', 'caes-reveal' ) }
																			</Button>
																			<Button
																				variant="secondary"
																				isDestructive
																				onClick={ () => onRemoveImage( index, 'desktopImage' ) }
																				size="small"
																			>
																				{ __( 'Remove', 'caes-reveal' ) }
																			</Button>
																		</HStack>
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
															updateFrame( index, { desktopImage: updatedImage } );
														} }
														style={ { marginTop: '8px' } }
													/>
												) }
											</div>

											{ /* Mobile Image */ }
											<div>
												<label style={ { display: 'block', marginBottom: '8px', fontWeight: 500 } }>
													{ __( 'Mobile Image (optional)', 'caes-reveal' ) }
												</label>
												<MediaUploadCheck>
													<MediaUpload
														onSelect={ ( media ) => onSelectImage( index, 'mobileImage', media ) }
														allowedTypes={ [ 'image' ] }
														value={ frame.mobileImage?.id }
														render={ ( { open } ) => (
															<>
																{ frame.mobileImage ? (
																	<div style={ { position: 'relative' } }>
																		<img
																			src={ frame.mobileImage.sizes?.thumbnail?.url || frame.mobileImage.url }
																			alt={ frame.mobileImage.alt }
																			style={ {
																				width: '80px',
																				height: '80px',
																				objectFit: 'cover',
																				borderRadius: '4px',
																			} }
																		/>
																		<HStack spacing={ 2 } style={ { marginTop: '8px' } }>
																			<Button variant="secondary" onClick={ open } size="small">
																				{ __( 'Replace', 'caes-reveal' ) }
																			</Button>
																			<Button
																				variant="secondary"
																				isDestructive
																				onClick={ () => onRemoveImage( index, 'mobileImage' ) }
																				size="small"
																			>
																				{ __( 'Remove', 'caes-reveal' ) }
																			</Button>
																		</HStack>
																	</div>
																) : (
																	<Button variant="secondary" onClick={ open } size="small">
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
															updateFrame( index, { mobileImage: updatedImage } );
														} }
														style={ { marginTop: '8px' } }
													/>
												) }
											</div>

											{ /* Focal Point */ }
											{ frame.desktopImage && (
												<div>
													<label style={ { display: 'block', marginBottom: '8px', fontWeight: 500 } }>
														{ __( 'Focal Point', 'caes-reveal' ) }
													</label>
													<FocalPointPicker
														url={ frame.desktopImage.url }
														value={ frame.focalPoint }
														onChange={ ( value ) => updateFrame( index, { focalPoint: value } ) }
													/>
												</div>
											) }

											{ /* Duotone */ }
											{ duotonePalette.length > 0 && (
												<div>
													<label style={ { display: 'block', marginBottom: '8px', fontWeight: 500 } }>
														{ __( 'Duotone Filter', 'caes-reveal' ) }
													</label>
													<DuotonePicker
														duotonePalette={ duotonePalette }
														disableCustomDuotone={ false }
														value={ frame.duotone }
														onChange={ ( value ) => updateFrame( index, { duotone: value } ) }
													/>
													{ frame.duotone && (
														<Button
															variant="link"
															isDestructive
															onClick={ () => updateFrame( index, { duotone: null } ) }
															style={ { marginTop: '8px' } }
														>
															{ __( 'Clear Duotone', 'caes-reveal' ) }
														</Button>
													) }
												</div>
											) }

											{ /* Transition Settings */ }
											<div>
												<label style={ { display: 'block', marginBottom: '8px', fontWeight: 500 } }>
													{ __( 'Transition', 'caes-reveal' ) }
												</label>
												<SelectControl
													value={ frame.transition.type }
													options={ TRANSITION_OPTIONS }
													onChange={ ( value ) =>
														updateFrame( index, {
															transition: { ...frame.transition, type: value },
														} )
													}
												/>
												{ frame.transition.type !== 'none' && (
													<RangeControl
														label={ __( 'Speed (ms)', 'caes-reveal' ) }
														value={ frame.transition.speed }
														onChange={ ( value ) =>
															updateFrame( index, {
																transition: { ...frame.transition, speed: value },
															} )
														}
														min={ 100 }
														max={ 2000 }
														step={ 50 }
													/>
												) }
											</div>

											{ /* Frame Actions */ }
											<HStack spacing={ 2 } style={ { borderTop: '1px solid #ddd', paddingTop: '12px' } }>
												<Button
													variant="secondary"
													onClick={ () => moveFrameUp( index ) }
													disabled={ index === 0 }
													icon="arrow-up-alt2"
													label={ __( 'Move Up', 'caes-reveal' ) }
													size="small"
												/>
												<Button
													variant="secondary"
													onClick={ () => moveFrameDown( index ) }
													disabled={ index === frames.length - 1 }
													icon="arrow-down-alt2"
													label={ __( 'Move Down', 'caes-reveal' ) }
													size="small"
												/>
												<Button
													variant="secondary"
													isDestructive
													onClick={ () => removeFrame( index ) }
													icon="trash"
													label={ __( 'Remove Frame', 'caes-reveal' ) }
													size="small"
													style={ { marginLeft: 'auto' } }
												/>
											</HStack>
										</div>
									</div>
								) }
							</div>
						) ) }

						<Button variant="primary" onClick={ addFrame } style={ { width: '100%' } }>
							{ __( 'Add Frame', 'caes-reveal' ) }
						</Button>
					</div>
				</PanelBody>
			</InspectorControls>

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
								objectPosition: frames[ 0 ]?.focalPoint
									? `${ frames[ 0 ].focalPoint.x * 100 }% ${ frames[ 0 ].focalPoint.y * 100 }%`
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
							{ __( 'Add frames in the sidebar to set background images', 'caes-reveal' ) }
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
						className="reveal-frame-indicator"
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

				{ /* Inner Content */ }
				<div
					className="reveal-content-editor"
					style={ {
						position: 'relative',
						zIndex: 1,
						minHeight: '200px',
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
};

export default Edit;