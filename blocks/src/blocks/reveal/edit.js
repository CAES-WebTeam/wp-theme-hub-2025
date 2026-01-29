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
	ColorPicker,
	Popover,
	TextControl,
	Notice,
	ToolbarGroup,
	ToolbarButton,
	Modal,
	DuotonePicker,
	DuotoneSwatch,
	RangeControl,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';

/**
 * Generate duotone SVG filter markup - matches WordPress core implementation.
 * Uses feColorMatrix for grayscale conversion, then feComponentTransfer for color mapping.
 *
 * @param {string[]} duotone - Array of two hex colors [shadow, highlight]
 * @param {string} filterId - Unique ID for the filter
 * @returns {JSX.Element|null} SVG element with filter definition
 */
const getDuotoneFilter = ( duotone, filterId ) => {
	if ( ! duotone || duotone.length < 2 ) {
		return null;
	}

	// Convert hex colors to RGB values (0-1 range)
	const parseColor = ( hex ) => {
		let color = hex.replace( '#', '' );
		// Handle 3-character hex
		if ( color.length === 3 ) {
			color = color[ 0 ] + color[ 0 ] + color[ 1 ] + color[ 1 ] + color[ 2 ] + color[ 2 ];
		}
		return {
			r: parseInt( color.slice( 0, 2 ), 16 ) / 255,
			g: parseInt( color.slice( 2, 4 ), 16 ) / 255,
			b: parseInt( color.slice( 4, 6 ), 16 ) / 255,
		};
	};

	const shadow = parseColor( duotone[ 0 ] );
	const highlight = parseColor( duotone[ 1 ] );

	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 0 0"
			width="0"
			height="0"
			focusable="false"
			role="none"
			style={ { visibility: 'hidden', position: 'absolute', left: '-9999px', overflow: 'hidden' } }
			aria-hidden="true"
		>
			<defs>
				<filter id={ filterId }>
					<feColorMatrix
						colorInterpolationFilters="sRGB"
						type="matrix"
						values=".299 .587 .114 0 0 .299 .587 .114 0 0 .299 .587 .114 0 0 0 0 0 1 0"
					/>
					<feComponentTransfer colorInterpolationFilters="sRGB">
						<feFuncR type="table" tableValues={ `${ shadow.r } ${ highlight.r }` } />
						<feFuncG type="table" tableValues={ `${ shadow.g } ${ highlight.g }` } />
						<feFuncB type="table" tableValues={ `${ shadow.b } ${ highlight.b }` } />
						<feFuncA type="table" tableValues="0 1" />
					</feComponentTransfer>
				</filter>
			</defs>
		</svg>
	);
};

const TRANSITION_OPTIONS = [
	{ label: __( 'None', 'caes-reveal' ), value: 'none' },
	{ label: __( 'Fade', 'caes-reveal' ), value: 'fade' },
	{ label: __( 'Up', 'caes-reveal' ), value: 'up' },
	{ label: __( 'Down', 'caes-reveal' ), value: 'down' },
	{ label: __( 'Left', 'caes-reveal' ), value: 'left' },
	{ label: __( 'Right', 'caes-reveal' ), value: 'right' },
];

const SPEED_OPTIONS = [
	{ label: __( 'Slow', 'caes-reveal' ), value: 'slow' },
	{ label: __( 'Normal', 'caes-reveal' ), value: 'normal' },
	{ label: __( 'Fast', 'caes-reveal' ), value: 'fast' },
];

// Duotone presets - common duotone combinations
const DUOTONE_PALETTE = [
	{ colors: [ '#000000', '#ffffff' ], name: 'Grayscale', slug: 'grayscale' },
	{ colors: [ '#000000', '#7f7f7f' ], name: 'Dark grayscale', slug: 'dark-grayscale' },
	{ colors: [ '#12128c', '#ffcc00' ], name: 'Blue and yellow', slug: 'blue-yellow' },
	{ colors: [ '#8c00b7', '#fcff41' ], name: 'Purple and yellow', slug: 'purple-yellow' },
	{ colors: [ '#000097', '#ff4747' ], name: 'Blue and red', slug: 'blue-red' },
	{ colors: [ '#004b23', '#99e2b4' ], name: 'Green tones', slug: 'green-tones' },
	{ colors: [ '#99154e', '#f7b2d9' ], name: 'Magenta tones', slug: 'magenta-tones' },
	{ colors: [ '#0d3b66', '#faf0ca' ], name: 'Navy and cream', slug: 'navy-cream' },
];

// Color palette for custom duotone creation
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

const DEFAULT_FRAME = {
	id: '',
	desktopImage: null,
	mobileImage: null,
	desktopFocalPoint: { x: 0.5, y: 0.5 },
	mobileFocalPoint: { x: 0.5, y: 0.5 },
	desktopDuotone: null,
	mobileDuotone: null,
	transition: {
		type: 'fade',
		// Speed is now handled globally
	},
};

const generateFrameId = () => {
	return 'frame-' + Math.random().toString( 36 ).substr( 2, 9 );
};

const Edit = ( { attributes, setAttributes } ) => {
	const { frames, overlayColor, overlayOpacity, minHeight, scrollSpeed } = attributes;
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

	// Calculate min-height based on speed for the editor view
	// This mimics the logic in render.php to give an accurate preview of scroll distance
	const getCalculatedMinHeight = () => {
		// If user manually set a minHeight override (not default '100vh' or 'auto'), you might want to respect that.
		// However, the previous code had a specific 'Layout' control for this.
		// If you want the "Scroll Speed" to drive the height, we should use that logic here.
		
		const count = Math.max( 1, frames.length );
		let multiplier = 100; // Normal

		if ( scrollSpeed === 'slow' ) multiplier = 150;
		if ( scrollSpeed === 'fast' ) multiplier = 75;

		return `${ count * multiplier }vh`;
	};

	// Get first frame's image for preview
	const firstFrame = frames.length > 0 ? frames[ 0 ] : null;
	const previewImage = firstFrame?.desktopImage?.url || null;

	const blockProps = useBlockProps( {
		className: 'caes-reveal-block',
		style: {
			'--reveal-min-height': getCalculatedMinHeight(), // Use calculated height based on speed
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
			<PanelBody title={ __( 'Block Settings', 'caes-reveal' ) }>
				<SelectControl
					label={ __( 'Scroll Speed', 'caes-reveal' ) }
					help={ __( 'Determines how much scrolling is required to transition between all frames.', 'caes-reveal' ) }
					value={ scrollSpeed || 'normal' }
					options={ SPEED_OPTIONS }
					onChange={ ( value ) => setAttributes( { scrollSpeed: value } ) }
				/>
			</PanelBody>

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
					help={ __( 'This controls the CSS min-height property directly. The scrollable distance is now automatically calculated based on the Scroll Speed setting.', 'caes-reveal' ) }
				/>
			</PanelBody>
		</InspectorControls>
	);

	// PREVIEW MODE
	if ( isPreviewMode ) {
		const previewDuotoneId = 'preview-duotone-filter';
		const activeDuotone = firstFrame?.desktopDuotone || firstFrame?.duotone;

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
					{ /* SVG Duotone Filter Definition */ }
					{ getDuotoneFilter( activeDuotone, previewDuotoneId ) }

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
									objectPosition: firstFrame?.desktopFocalPoint
										? `${ firstFrame.desktopFocalPoint.x * 100 }% ${ firstFrame.desktopFocalPoint.y * 100 }%`
										: 'center',
									filter: activeDuotone ? `url(#${ previewDuotoneId })` : undefined,
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
	const [ focalPointModal, setFocalPointModal ] = useState( null ); // 'desktop' | 'mobile' | null
	const [ duotoneModal, setDuotoneModal ] = useState( null ); // 'desktop' | 'mobile' | null

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
							position: 'relative',
						} }
					>
						{ frame.desktopImage && ( () => {
							const duotone = frame.desktopDuotone || frame.duotone;
							const filterId = `thumb-duotone-${ frameIndex }`;

							return (
								<>
									{ getDuotoneFilter( duotone, filterId ) }
									<img
										src={ frame.desktopImage.sizes?.thumbnail?.url || frame.desktopImage.url }
										alt=""
										style={ {
											width: '100%',
											height: '100%',
											objectFit: 'cover',
											filter: duotone ? `url(#${ filterId })` : undefined,
										} }
									/>
								</>
							);
						} )() }
					</div>
					<div>
						<strong>{ __( 'Frame', 'caes-reveal' ) } { frameIndex + 1 }</strong>
						<div style={ { fontSize: '12px', color: '#666' } }>
							{ frame.transition.type !== 'none'
								? `${ frame.transition.type }`
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
					{ /* Side-by-side Image Columns */ }
					<div
						style={ {
							display: 'grid',
							gridTemplateColumns: '1fr 1fr',
							gap: '24px',
							marginBottom: '20px',
						} }
					>
						{ /* Desktop Image Column */ }
						<div
							style={ {
								padding: '16px',
								backgroundColor: '#fff',
								border: '1px solid #e0e0e0',
								borderRadius: '4px',
							} }
						>
							<div style={ { display: 'flex', alignItems: 'flex-start', gap: '8px', marginBottom: '12px', lineHeight: '1' } }>
								<span className="dashicons dashicons-desktop" style={ { fontSize: '20px' } }></span>
								<div>
									<strong style={ { display: 'block' } }>{ __( 'Wide Screens', 'caes-reveal' ) }</strong>
									<span style={ { fontSize: '12px', color: '#666' } }>
										{ __( 'Computers, Large Tablets Etc.', 'caes-reveal' ) }
									</span>
								</div>
							</div>

							<p style={ { margin: '0 0 4px 0', fontSize: '13px', color: '#1e1e1e' } }>
								{ __( 'Background image (will be cropped to screen)', 'caes-reveal' ) }
								<span style={ { color: '#cc0000' } }> *</span>
							</p>
							<p style={ { margin: '0 0 12px 0', fontSize: '12px', color: '#757575' } }>
								{ __( 'Recommended: JPEG @ 2560 x 1440px', 'caes-reveal' ) }
							</p>

							<MediaUploadCheck>
								{ ! frame.desktopImage ? (
									<MediaUpload
										onSelect={ ( media ) => onSelectImage( 'desktopImage', media ) }
										allowedTypes={ [ 'image' ] }
										value={ frame.desktopImage?.id }
										render={ ( { open } ) => (
											<div
												onClick={ open }
												style={ {
													border: '2px dashed #c4c4c4',
													borderRadius: '4px',
													padding: '20px',
													textAlign: 'center',
													cursor: 'pointer',
													backgroundColor: '#fafafa',
													marginBottom: '12px',
													minHeight: '150px',
													display: 'flex',
													flexDirection: 'column',
													alignItems: 'center',
													justifyContent: 'center',
												} }
											>
												<span className="dashicons dashicons-upload" style={ { fontSize: '24px', color: '#757575', marginBottom: '8px' } }></span>
												<span style={ { color: '#757575', fontSize: '13px' } }>{ __( 'DRAG & DROP', 'caes-reveal' ) }</span>
												<div style={ { display: 'flex', gap: '8px', marginTop: '12px' } }>
													<Button variant="secondary" onClick={ ( e ) => { e.stopPropagation(); open(); } }>
														{ __( 'Upload', 'caes-reveal' ) }
													</Button>
													<Button variant="secondary" onClick={ ( e ) => { e.stopPropagation(); open(); } }>
														{ __( 'Media Library', 'caes-reveal' ) }
													</Button>
												</div>
											</div>
										) }
									/>
								) : (
									<div style={ { marginBottom: '12px' } }>
										<div
											style={ {
												border: '1px solid #c4c4c4',
												borderRadius: '4px',
												padding: '12px',
												backgroundColor: '#fafafa',
												marginBottom: '8px',
												textAlign: 'center',
												position: 'relative',
											} }
										>
											{ ( () => {
												const duotone = frame.desktopDuotone || frame.duotone;
												const filterId = `desktop-preview-duotone-${ frameIndex }`;

												return (
													<>
														{ getDuotoneFilter( duotone, filterId ) }
														<img
															src={ frame.desktopImage.sizes?.medium?.url || frame.desktopImage.url }
															alt={ frame.desktopImage.alt }
															style={ {
																maxWidth: '100%',
																maxHeight: '150px',
																borderRadius: '4px',
																filter: duotone ? `url(#${ filterId })` : undefined,
															} }
														/>
													</>
												);
											} )() }
										</div>
										<div style={ { display: 'flex', gap: '8px' } }>
											<MediaUpload
												onSelect={ ( media ) => onSelectImage( 'desktopImage', media ) }
												allowedTypes={ [ 'image' ] }
												value={ frame.desktopImage?.id }
												render={ ( { open } ) => (
													<Button variant="secondary" onClick={ open } size="small">
														{ __( 'Replace', 'caes-reveal' ) }
													</Button>
												) }
											/>
											<Button
												variant="secondary"
												isDestructive
												onClick={ () => onRemoveImage( 'desktopImage' ) }
												size="small"
											>
												{ __( 'Remove', 'caes-reveal' ) }
											</Button>
										</div>
									</div>
								) }
							</MediaUploadCheck>

							<TextControl
								label={
									<>
										{ __( 'Caption', 'caes-reveal' ) }
										<span style={ { fontWeight: 'normal', color: '#757575' } }> ({ __( 'optional', 'caes-reveal' ) })</span>
									</>
								}
								value={ frame.desktopImage?.caption || '' }
								onChange={ ( value ) => {
									const updatedImage = { ...frame.desktopImage, caption: value };
									onUpdate( { desktopImage: updatedImage } );
								} }
								placeholder={ __( 'Add a caption', 'caes-reveal' ) }
								disabled={ ! frame.desktopImage }
							/>

							<TextControl
								label={
									<>
										{ __( 'Alt Text', 'caes-reveal' ) }
										<span style={ { fontWeight: 'normal', color: '#757575' } }> ({ __( 'recommended', 'caes-reveal' ) })</span>
									</>
								}
								value={ frame.desktopImage?.alt || '' }
								onChange={ ( value ) => {
									const updatedImage = { ...frame.desktopImage, alt: value };
									onUpdate( { desktopImage: updatedImage } );
								} }
								placeholder={ __( 'Describe media for screenreaders', 'caes-reveal' ) }
								disabled={ ! frame.desktopImage }
							/>

							{ frame.desktopImage && (
								<div style={ { display: 'flex', gap: '8px', marginTop: '12px', flexWrap: 'wrap', alignItems: 'center' } }>
									<Button
										variant="secondary"
										onClick={ () => setFocalPointModal( 'desktop' ) }
										icon="image-crop"
									>
										{ __( 'Set Focus Point', 'caes-reveal' ) }
									</Button>
									<Button
										variant="secondary"
										onClick={ () => setDuotoneModal( 'desktop' ) }
										icon="admin-appearance"
									>
										{ ( frame.desktopDuotone || frame.duotone ) ? __( 'Edit Filter', 'caes-reveal' ) : __( 'Add Filter', 'caes-reveal' ) }
									</Button>
									{ ( frame.desktopDuotone || frame.duotone ) && (
										<DuotoneSwatch values={ frame.desktopDuotone || frame.duotone } />
									) }
								</div>
							) }
						</div>

						{ /* Mobile Image Column */ }
						<div
							style={ {
								padding: '16px',
								backgroundColor: '#fff',
								border: '1px solid #e0e0e0',
								borderRadius: '4px',
							} }
						>
							<div style={ { display: 'flex', alignItems: 'flex-start', gap: '8px', marginBottom: '12px', lineHeight: '1' } }>
								<span className="dashicons dashicons-smartphone" style={ { fontSize: '20px' } }></span>
								<div>
									<strong style={ { display: 'block' } }>{ __( 'Tall Screens', 'caes-reveal' ) }</strong>
									<span style={ { fontSize: '12px', color: '#666' } }>
										{ __( 'Devices In Portrait Orientation', 'caes-reveal' ) }
									</span>
								</div>
							</div>

							<p style={ { margin: '0 0 4px 0', fontSize: '13px', color: '#1e1e1e' } }>
								{ __( 'Background image (will be cropped to screen)', 'caes-reveal' ) }
							</p>
							<p style={ { margin: '0 0 12px 0', fontSize: '12px', color: '#757575' } }>
								{ __( 'Recommended: JPEG @ 1080 x 1920px', 'caes-reveal' ) }
							</p>

							<MediaUploadCheck>
								{ ! frame.mobileImage ? (
									<MediaUpload
										onSelect={ ( media ) => onSelectImage( 'mobileImage', media ) }
										allowedTypes={ [ 'image' ] }
										value={ frame.mobileImage?.id }
										render={ ( { open } ) => (
											<div
												onClick={ open }
												style={ {
													border: '2px dashed #c4c4c4',
													borderRadius: '4px',
													padding: '20px',
													textAlign: 'center',
													cursor: 'pointer',
													backgroundColor: '#fafafa',
													marginBottom: '12px',
													minHeight: '150px',
													display: 'flex',
													flexDirection: 'column',
													alignItems: 'center',
													justifyContent: 'center',
												} }
											>
												<span className="dashicons dashicons-upload" style={ { fontSize: '24px', color: '#757575', marginBottom: '8px' } }></span>
												<span style={ { color: '#757575', fontSize: '13px' } }>{ __( 'DRAG & DROP', 'caes-reveal' ) }</span>
												<div style={ { display: 'flex', gap: '8px', marginTop: '12px' } }>
													<Button variant="secondary" onClick={ ( e ) => { e.stopPropagation(); open(); } }>
														{ __( 'Upload', 'caes-reveal' ) }
													</Button>
													<Button variant="secondary" onClick={ ( e ) => { e.stopPropagation(); open(); } }>
														{ __( 'Media Library', 'caes-reveal' ) }
													</Button>
												</div>
											</div>
										) }
									/>
								) : (
									<div style={ { marginBottom: '12px' } }>
										<div
											style={ {
												border: '1px solid #c4c4c4',
												borderRadius: '4px',
												padding: '12px',
												backgroundColor: '#fafafa',
												marginBottom: '8px',
												textAlign: 'center',
												position: 'relative',
											} }
										>
											{ ( () => {
												const duotone = frame.mobileDuotone;
												const filterId = `mobile-preview-duotone-${ frameIndex }`;

												return (
													<>
														{ getDuotoneFilter( duotone, filterId ) }
														<img
															src={ frame.mobileImage.sizes?.medium?.url || frame.mobileImage.url }
															alt={ frame.mobileImage.alt }
															style={ {
																maxWidth: '100%',
																maxHeight: '150px',
																borderRadius: '4px',
																filter: duotone ? `url(#${ filterId })` : undefined,
															} }
														/>
													</>
												);
											} )() }
										</div>
										<div style={ { display: 'flex', gap: '8px' } }>
											<MediaUpload
												onSelect={ ( media ) => onSelectImage( 'mobileImage', media ) }
												allowedTypes={ [ 'image' ] }
												value={ frame.mobileImage?.id }
												render={ ( { open } ) => (
													<Button variant="secondary" onClick={ open } size="small">
														{ __( 'Replace', 'caes-reveal' ) }
													</Button>
												) }
											/>
											<Button
												variant="secondary"
												isDestructive
												onClick={ () => onRemoveImage( 'mobileImage' ) }
												size="small"
											>
												{ __( 'Remove', 'caes-reveal' ) }
											</Button>
										</div>
									</div>
								) }
							</MediaUploadCheck>

							<TextControl
								label={
									<>
										{ __( 'Caption', 'caes-reveal' ) }
										<span style={ { fontWeight: 'normal', color: '#757575' } }> ({ __( 'optional', 'caes-reveal' ) })</span>
									</>
								}
								value={ frame.mobileImage?.caption || '' }
								onChange={ ( value ) => {
									const updatedImage = { ...frame.mobileImage, caption: value };
									onUpdate( { mobileImage: updatedImage } );
								} }
								placeholder={ __( 'Add a caption', 'caes-reveal' ) }
								disabled={ ! frame.mobileImage }
							/>

							<TextControl
								label={
									<>
										{ __( 'Alt Text', 'caes-reveal' ) }
										<span style={ { fontWeight: 'normal', color: '#757575' } }> ({ __( 'recommended', 'caes-reveal' ) })</span>
									</>
								}
								value={ frame.mobileImage?.alt || '' }
								onChange={ ( value ) => {
									const updatedImage = { ...frame.mobileImage, alt: value };
									onUpdate( { mobileImage: updatedImage } );
								} }
								placeholder={ __( 'Describe media for screenreaders', 'caes-reveal' ) }
								disabled={ ! frame.mobileImage }
							/>

							{ frame.mobileImage && (
								<div style={ { display: 'flex', gap: '8px', marginTop: '12px', flexWrap: 'wrap', alignItems: 'center' } }>
									<Button
										variant="secondary"
										onClick={ () => setFocalPointModal( 'mobile' ) }
										icon="image-crop"
									>
										{ __( 'Set Focus Point', 'caes-reveal' ) }
									</Button>
									<Button
										variant="secondary"
										onClick={ () => setDuotoneModal( 'mobile' ) }
										icon="admin-appearance"
									>
										{ frame.mobileDuotone ? __( 'Edit Filter', 'caes-reveal' ) : __( 'Add Filter', 'caes-reveal' ) }
									</Button>
									{ frame.mobileDuotone && (
										<DuotoneSwatch values={ frame.mobileDuotone } />
									) }
								</div>
							) }
						</div>
					</div>

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
					</div>
				</div>
			) }

			{ /* Focal Point Modal */ }
			{ focalPointModal && (
				<Modal
					title={
						focalPointModal === 'desktop'
							? __( 'Set Focus Point — Wide Screens', 'caes-reveal' )
							: __( 'Set Focus Point — Tall Screens', 'caes-reveal' )
					}
					onRequestClose={ () => setFocalPointModal( null ) }
					style={ { maxWidth: '600px', width: '100%' } }
				>
					<div style={ { padding: '8px 0' } }>
						<p style={ { margin: '0 0 16px 0', color: '#757575', fontSize: '13px' } }>
							{ __( 'Click on the image to set the focal point. This determines which part of the image stays visible when cropped to fit the screen.', 'caes-reveal' ) }
						</p>
						
						{ focalPointModal === 'desktop' && frame.desktopImage && (
							<FocalPointPicker
								url={ frame.desktopImage.url }
								value={ frame.desktopFocalPoint || { x: 0.5, y: 0.5 } }
								onChange={ ( value ) => onUpdate( { desktopFocalPoint: value } ) }
							/>
						) }
						
						{ focalPointModal === 'mobile' && frame.mobileImage && (
							<FocalPointPicker
								url={ frame.mobileImage.url }
								value={ frame.mobileFocalPoint || { x: 0.5, y: 0.5 } }
								onChange={ ( value ) => onUpdate( { mobileFocalPoint: value } ) }
							/>
						) }

						<div style={ { marginTop: '20px', display: 'flex', justifyContent: 'flex-end' } }>
							<Button
								variant="primary"
								onClick={ () => setFocalPointModal( null ) }
							>
								{ __( 'Done', 'caes-reveal' ) }
							</Button>
						</div>
					</div>
				</Modal>
			) }

			{ /* Duotone Modal */ }
			{ duotoneModal && (
				<Modal
					title={
						duotoneModal === 'desktop'
							? __( 'Duotone Filter — Wide Screens', 'caes-reveal' )
							: __( 'Duotone Filter — Tall Screens', 'caes-reveal' )
					}
					onRequestClose={ () => setDuotoneModal( null ) }
					style={ { maxWidth: '400px', width: '100%' } }
				>
					<div style={ { padding: '8px 0' } }>
						<p style={ { margin: '0 0 16px 0', color: '#757575', fontSize: '13px' } }>
							{ __( 'Apply a duotone color filter to this image. The first color replaces shadows, the second replaces highlights.', 'caes-reveal' ) }
						</p>
						
						{ duotoneModal === 'desktop' && (
							<DuotonePicker
								duotonePalette={ DUOTONE_PALETTE }
								colorPalette={ COLOR_PALETTE }
								value={ frame.desktopDuotone || frame.duotone || undefined }
								onChange={ ( value ) => onUpdate( { desktopDuotone: value, duotone: null } ) }
							/>
						) }

						{ duotoneModal === 'mobile' && (
							<DuotonePicker
								duotonePalette={ DUOTONE_PALETTE }
								colorPalette={ COLOR_PALETTE }
								value={ frame.mobileDuotone || undefined }
								onChange={ ( value ) => onUpdate( { mobileDuotone: value } ) }
							/>
						) }

						<div style={ { marginTop: '20px', display: 'flex', justifyContent: 'space-between' } }>
							{ ( ( duotoneModal === 'desktop' && ( frame.desktopDuotone || frame.duotone ) ) || ( duotoneModal === 'mobile' && frame.mobileDuotone ) ) && (
								<Button
									variant="tertiary"
									isDestructive
									onClick={ () => {
										if ( duotoneModal === 'desktop' ) {
											onUpdate( { desktopDuotone: null, duotone: null } );
										} else {
											onUpdate( { mobileDuotone: null } );
										}
										setDuotoneModal( null );
									} }
								>
									{ __( 'Remove Filter', 'caes-reveal' ) }
								</Button>
							) }
							<div style={ { marginLeft: 'auto' } }>
								<Button
									variant="primary"
									onClick={ () => setDuotoneModal( null ) }
								>
									{ __( 'Done', 'caes-reveal' ) }
								</Button>
							</div>
						</div>
					</div>
				</Modal>
			) }
		</div>
	);
};

export default Edit;