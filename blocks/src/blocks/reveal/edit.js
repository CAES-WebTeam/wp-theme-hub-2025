import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InnerBlocks,
	InspectorControls,
	BlockControls,
	MediaUpload,
	MediaUploadCheck,
	store as blockEditorStore,
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
import { useSelect, useDispatch } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';

/**
 * Generate duotone SVG filter markup - matches WordPress core implementation.
 */
const getDuotoneFilter = ( duotone, filterId ) => {
	if ( ! duotone || duotone.length < 2 ) {
		return null;
	}

	const parseColor = ( hex ) => {
		let color = hex.replace( '#', '' );
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
		speed: 'normal',
	},
};

const generateFrameId = () => {
	return 'frame-' + Math.random().toString( 36 ).substr( 2, 9 );
};

const Edit = ( { attributes, setAttributes, clientId } ) => {
	const { frames, overlayColor, overlayOpacity } = attributes;
	const [ showFrameManager, setShowFrameManager ] = useState( false );
	const [ showOverlayColorPicker, setShowOverlayColorPicker ] = useState( false );

	const { replaceInnerBlocks } = useDispatch( blockEditorStore );
	const { innerBlocks } = useSelect(
		( select ) => ( {
			innerBlocks: select( blockEditorStore ).getBlocks( clientId ),
		} ),
		[ clientId ]
	);

	// Auto-add first frame when block is inserted
	useEffect( () => {
		if ( frames.length === 0 ) {
			setAttributes( {
				frames: [ { ...DEFAULT_FRAME, id: generateFrameId() } ],
			} );
		}
	}, [] );

	// Sync frame content blocks with frames array
	useEffect( () => {
		if ( frames.length === 0 ) {
			return;
		}

		const needsUpdate =
			innerBlocks.length !== frames.length ||
			innerBlocks.some(
				( block, index ) =>
					block.name !== 'caes-hub/reveal-frames' ||
					block.attributes.frameIndex !== index ||
					block.attributes.frameLabel !== `Frame ${ index + 1 } Content`
			);

		if ( needsUpdate ) {
			const newInnerBlocks = frames.map( ( frame, index ) => {
				const existingBlock = innerBlocks.find(
					( b ) => b.name === 'caes-hub/reveal-frames' && b.attributes.frameIndex === index
				);

				if ( existingBlock ) {
					return {
						...existingBlock,
						attributes: {
							...existingBlock.attributes,
							frameIndex: index,
							frameLabel: `Frame ${ index + 1 } Content`,
						},
					};
				}

				return createBlock( 'caes-hub/reveal-frames', {
					frameIndex: index,
					frameLabel: `Frame ${ index + 1 } Content`,
				} );
			} );

			replaceInnerBlocks( clientId, newInnerBlocks, false );
		}
	}, [ frames.length, clientId ] );

	const addFrame = () => {
		const newFrame = {
			...DEFAULT_FRAME,
			id: generateFrameId(),
		};
		setAttributes( { frames: [ ...frames, newFrame ] } );
	};

	const removeFrame = ( frameIndex ) => {
		if ( frames.length === 1 ) {
			return;
		}
		const newFrames = [ ...frames ];
		newFrames.splice( frameIndex, 1 );
		setAttributes( { frames: newFrames } );
	};

	const updateFrame = ( frameIndex, updates ) => {
		const newFrames = [ ...frames ];
		newFrames[ frameIndex ] = { ...newFrames[ frameIndex ], ...updates };
		setAttributes( { frames: newFrames } );
	};

	const moveFrameUp = ( frameIndex ) => {
		if ( frameIndex === 0 ) return;
		const newFrames = [ ...frames ];
		[ newFrames[ frameIndex - 1 ], newFrames[ frameIndex ] ] = [
			newFrames[ frameIndex ],
			newFrames[ frameIndex - 1 ],
		];
		setAttributes( { frames: newFrames } );
	};

	const moveFrameDown = ( frameIndex ) => {
		if ( frameIndex === frames.length - 1 ) return;
		const newFrames = [ ...frames ];
		[ newFrames[ frameIndex ], newFrames[ frameIndex + 1 ] ] = [
			newFrames[ frameIndex + 1 ],
			newFrames[ frameIndex ],
		];
		setAttributes( { frames: newFrames } );
	};

	const duplicateFrame = ( frameIndex ) => {
		const frameToDuplicate = frames[ frameIndex ];
		const duplicatedFrame = {
			...JSON.parse( JSON.stringify( frameToDuplicate ) ),
			id: generateFrameId(),
		};
		const newFrames = [ ...frames ];
		newFrames.splice( frameIndex + 1, 0, duplicatedFrame );
		setAttributes( { frames: newFrames } );
	};

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

	const onRemoveImage = ( frameIndex, imageType ) => {
		updateFrame( frameIndex, { [ imageType ]: null } );
	};

	const getOverlayRgba = () => {
		const opacity = overlayOpacity / 100;
		const hex = overlayColor.replace( '#', '' );
		const r = parseInt( hex.substring( 0, 2 ), 16 );
		const g = parseInt( hex.substring( 2, 4 ), 16 );
		const b = parseInt( hex.substring( 4, 6 ), 16 );
		return `rgba(${ r }, ${ g }, ${ b }, ${ opacity })`;
	};

	const blockProps = useBlockProps( {
		className: 'caes-reveal-editor',
	} );

	return (
		<>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						icon="admin-generic"
						label={ __( 'Manage Frames', 'caes-reveal' ) }
						onClick={ () => setShowFrameManager( true ) }
					/>
				</ToolbarGroup>
			</BlockControls>

			<InspectorControls>
				<PanelBody title={ __( 'Overlay', 'caes-reveal' ) } initialOpen={ false }>
					<div style={ { marginBottom: '16px' } }>
						<label style={ { display: 'block', marginBottom: '8px', fontWeight: 500 } }>
							{ __( 'Overlay Color', 'caes-reveal' ) }
						</label>
						<Button
							onClick={ () => setShowOverlayColorPicker( ! showOverlayColorPicker ) }
							style={ {
								width: '100%',
								height: '36px',
								background: overlayColor,
								border: '1px solid #ddd',
								cursor: 'pointer',
							} }
						/>
						{ showOverlayColorPicker && (
							<Popover onClose={ () => setShowOverlayColorPicker( false ) }>
								<ColorPicker
									color={ overlayColor }
									onChange={ ( value ) => setAttributes( { overlayColor: value } ) }
									enableAlpha={ false }
								/>
							</Popover>
						) }
					</div>

					<RangeControl
						label={ __( 'Overlay Opacity', 'caes-reveal' ) }
						value={ overlayOpacity }
						onChange={ ( value ) => setAttributes( { overlayOpacity: value } ) }
						min={ 0 }
						max={ 100 }
						step={ 5 }
					/>

					<div
						style={ {
							marginTop: '12px',
							padding: '12px',
							background: '#f0f0f0',
							borderRadius: '4px',
							fontSize: '13px',
						} }
					>
						<strong>{ __( 'Preview:', 'caes-reveal' ) }</strong>
						<div
							style={ {
								marginTop: '8px',
								height: '40px',
								background: getOverlayRgba(),
								borderRadius: '2px',
								border: '1px solid #ddd',
							} }
						/>
					</div>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<div className="reveal-editor-layout">
					{/* Background frames preview */}
					<div className="reveal-background-preview">
						{ frames.length === 0 ? (
							<div className="reveal-placeholder">
								<p>{ __( 'Click "Manage Frames" to add background images', 'caes-reveal' ) }</p>
							</div>
						) : (
							frames.map( ( frame, index ) => {
								if ( ! frame.desktopImage ) {
									return (
										<div key={ frame.id || index } className="reveal-frame-placeholder">
											<p>{ __( 'Frame', 'caes-reveal' ) } { index + 1 }: { __( 'No image', 'caes-reveal' ) }</p>
										</div>
									);
								}

								const filterId = `editor-${ clientId }-${ index }`;
								const desktopDuotone = frame.desktopDuotone || frame.duotone;

								return (
									<div key={ frame.id || index } className="reveal-frame-preview">
										{ desktopDuotone && getDuotoneFilter( desktopDuotone, filterId ) }
										<img
											src={ frame.desktopImage.url }
											alt={ frame.desktopImage.alt || '' }
											style={ {
												objectPosition: `${ ( frame.desktopFocalPoint?.x || 0.5 ) * 100 }% ${ (
													frame.desktopFocalPoint?.y || 0.5
												) * 100 }%`,
												filter: desktopDuotone ? `url(#${ filterId })` : undefined,
											} }
										/>
										<div className="reveal-overlay-preview" style={ { background: getOverlayRgba() } } />
										<div className="reveal-frame-label">
											{ __( 'Frame', 'caes-reveal' ) } { index + 1 }
										</div>
									</div>
								);
							} )
						) }
					</div>

					{/* Content editing area */}
					<div className="reveal-content-editor">
						<Notice status="info" isDismissible={ false }>
							{ __( 'Add content to each frame container below. Content will appear over the background images as users scroll.', 'caes-reveal' ) }
						</Notice>
						<InnerBlocks allowedBlocks={ [ 'caes-hub/reveal-frames' ] } />
					</div>
				</div>
			</div>

			{/* Frame Manager Modal */}
			{ showFrameManager && (
				<Modal
					title={ __( 'Manage Frames', 'caes-reveal' ) }
					onRequestClose={ () => setShowFrameManager( false ) }
					className="reveal-frame-manager-modal"
					style={ { maxWidth: '900px', width: '90vw' } }
				>
					<div style={ { padding: '20px 0' } }>
						<p style={ { marginBottom: '20px', color: '#757575' } }>
							{ __( 'Configure background images and transitions for each frame. Add content to frames in the editor.', 'caes-reveal' ) }
						</p>

						{ frames.map( ( frame, index ) => (
							<FrameManagerPanel
								key={ frame.id || index }
								frame={ frame }
								index={ index }
								totalFrames={ frames.length }
								onUpdate={ ( updates ) => updateFrame( index, updates ) }
								onRemove={ () => removeFrame( index ) }
								onMoveUp={ () => moveFrameUp( index ) }
								onMoveDown={ () => moveFrameDown( index ) }
								onDuplicate={ () => duplicateFrame( index ) }
								onSelectImage={ ( imageType, media ) => onSelectImage( index, imageType, media ) }
								onRemoveImage={ ( imageType ) => onRemoveImage( index, imageType ) }
								clientId={ clientId }
							/>
						) ) }

						<Button variant="primary" onClick={ addFrame } style={ { width: '100%', marginTop: '20px' } }>
							{ __( 'Add Frame', 'caes-reveal' ) }
						</Button>

						<div style={ { marginTop: '20px', textAlign: 'right' } }>
							<Button variant="secondary" onClick={ () => setShowFrameManager( false ) }>
								{ __( 'Done', 'caes-reveal' ) }
							</Button>
						</div>
					</div>
				</Modal>
			) }
		</>
	);
};

// Frame Manager Panel Component (used in modal)
const FrameManagerPanel = ( {
	frame,
	index,
	totalFrames,
	onUpdate,
	onRemove,
	onMoveUp,
	onMoveDown,
	onDuplicate,
	onSelectImage,
	onRemoveImage,
	clientId,
} ) => {
	const [ focalPointModal, setFocalPointModal ] = useState( null );
	const [ duotoneModal, setDuotoneModal ] = useState( null );

	return (
		<div
			style={ {
				border: '1px solid #ddd',
				borderRadius: '4px',
				marginBottom: '20px',
				background: '#fff',
			} }
		>
			{/* Header */}
			<div
				style={ {
					display: 'flex',
					justifyContent: 'space-between',
					alignItems: 'center',
					padding: '16px',
					borderBottom: '1px solid #ddd',
					background: '#f9f9f9',
				} }
			>
				<strong style={ { fontSize: '16px' } }>
					{ __( 'Frame', 'caes-reveal' ) } { index + 1 }
				</strong>
				<div style={ { display: 'flex', gap: '8px' } }>
					{ index > 0 && (
						<Button size="small" icon="arrow-up-alt2" onClick={ onMoveUp } label={ __( 'Move up', 'caes-reveal' ) } />
					) }
					{ index < totalFrames - 1 && (
						<Button size="small" icon="arrow-down-alt2" onClick={ onMoveDown } label={ __( 'Move down', 'caes-reveal' ) } />
					) }
					<Button size="small" icon="admin-page" onClick={ onDuplicate } label={ __( 'Duplicate', 'caes-reveal' ) } />
					{ totalFrames > 1 && (
						<Button
							size="small"
							icon="trash"
							onClick={ onRemove }
							label={ __( 'Remove', 'caes-reveal' ) }
							isDestructive
						/>
					) }
				</div>
			</div>

			{/* Content */}
			<div style={ { padding: '20px' } }>
				{/* Desktop and Mobile Images Side by Side */}
				<div style={ { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px', marginBottom: '20px' } }>
					{/* Desktop Image Column */}
					<div>
						<ImagePanel
							frame={ frame }
							imageType="desktop"
							onSelectImage={ onSelectImage }
							onRemoveImage={ onRemoveImage }
							onUpdate={ onUpdate }
							setFocalPointModal={ setFocalPointModal }
							setDuotoneModal={ setDuotoneModal }
							clientId={ clientId }
							frameIndex={ index }
							isRequired={ true }
						/>
					</div>

					{/* Mobile Image Column */}
					<div>
						<ImagePanel
							frame={ frame }
							imageType="mobile"
							onSelectImage={ onSelectImage }
							onRemoveImage={ onRemoveImage }
							onUpdate={ onUpdate }
							setFocalPointModal={ setFocalPointModal }
							setDuotoneModal={ setDuotoneModal }
							clientId={ clientId }
							frameIndex={ index }
							isRequired={ false }
						/>
					</div>
				</div>

				{/* Transition Settings */}
				<div
					style={ {
						paddingTop: '20px',
						borderTop: '1px solid #ddd',
					} }
				>
					<label style={ { display: 'block', marginBottom: '12px', fontWeight: 500, fontSize: '14px' } }>
						{ __( 'Transition', 'caes-reveal' ) }
					</label>
					<div style={ { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px' } }>
						<SelectControl
							label={ __( 'Type', 'caes-reveal' ) }
							value={ frame.transition.type }
							options={ TRANSITION_OPTIONS }
							onChange={ ( value ) =>
								onUpdate( {
									transition: { ...frame.transition, type: value },
								} )
							}
						/>
						<SelectControl
							label={ __( 'Speed', 'caes-reveal' ) }
							value={ frame.transition.speed || 'normal' }
							options={ [
								{ label: __( 'Slow', 'caes-reveal' ), value: 'slow' },
								{ label: __( 'Normal', 'caes-reveal' ), value: 'normal' },
								{ label: __( 'Fast', 'caes-reveal' ), value: 'fast' },
							] }
							onChange={ ( value ) =>
								onUpdate( {
									transition: { ...frame.transition, speed: value },
								} )
							}
						/>
					</div>
				</div>
			</div>

			{/* Modals */}
			{ focalPointModal && (
				<FocalPointModal
					frame={ frame }
					imageType={ focalPointModal }
					onUpdate={ onUpdate }
					onClose={ () => setFocalPointModal( null ) }
				/>
			) }

			{ duotoneModal && (
				<DuotoneModal
					frame={ frame }
					imageType={ duotoneModal }
					onUpdate={ onUpdate }
					onClose={ () => setDuotoneModal( null ) }
				/>
			) }
		</div>
	);
};

// Image Panel Component
const ImagePanel = ( {
	frame,
	imageType,
	onSelectImage,
	onRemoveImage,
	onUpdate,
	setFocalPointModal,
	setDuotoneModal,
	clientId,
	frameIndex,
	isRequired,
} ) => {
	const imageKey = imageType === 'desktop' ? 'desktopImage' : 'mobileImage';
	const focalKey = imageType === 'desktop' ? 'desktopFocalPoint' : 'mobileFocalPoint';
	const duotoneKey = imageType === 'desktop' ? 'desktopDuotone' : 'mobileDuotone';
	const image = frame[ imageKey ];
	const duotone = imageType === 'desktop' ? ( frame.desktopDuotone || frame.duotone ) : frame.mobileDuotone;

	return (
		<div>
			{/* Header with icon and title */}
			<div style={ { marginBottom: '16px' } }>
				<div style={ { display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '4px' } }>
					<span style={ { fontSize: '20px' } }>
						{ imageType === 'desktop' ? '🖥️' : '📱' }
					</span>
					<h3 style={ { margin: 0, fontSize: '16px', fontWeight: 600 } }>
						{ imageType === 'desktop' ? __( 'Wide Screens', 'caes-reveal' ) : __( 'Tall Screens', 'caes-reveal' ) }
					</h3>
				</div>
				<p style={ { margin: 0, fontSize: '13px', color: '#757575' } }>
					{ imageType === 'desktop' 
						? __( 'Computers, Large Tablets Etc.', 'caes-reveal' )
						: __( 'Devices In Portrait Orientation', 'caes-reveal' )
					}
				</p>
			</div>

			<label style={ { display: 'block', marginBottom: '8px', fontWeight: 500, fontSize: '13px', color: '#1e1e1e' } }>
				{ __( 'Background image (will be cropped to screen)', 'caes-reveal' ) }
			</label>
			<p style={ { margin: '0 0 12px', fontSize: '12px', color: '#757575' } }>
				{ imageType === 'desktop'
					? __( 'Recommended: JPEG @ 2560 x 1440px', 'caes-reveal' )
					: __( 'Recommended: JPEG @ 1080 x 1920px', 'caes-reveal' )
				}
			</p>

			<MediaUploadCheck>
				{ ! image ? (
					<div>
						<MediaUpload
							onSelect={ ( media ) => onSelectImage( imageType + 'Image', media ) }
							allowedTypes={ [ 'image' ] }
							render={ ( { open } ) => (
								<Button variant="secondary" onClick={ open } style={ { width: '100%', height: '200px' } }>
									{ __( 'Select Image', 'caes-reveal' ) }
								</Button>
							) }
						/>
					</div>
				) : (
					<div>
						{/* Image Preview */}
						<div style={ { marginBottom: '16px' } }>
							{ ( () => {
								const filterId = `manager-${ clientId }-${ frameIndex }-${ imageType }`;
								return (
									<>
										{ duotone && getDuotoneFilter( duotone, filterId ) }
										<img
											src={ image.url }
											alt={ image.alt }
											style={ {
												width: '100%',
												height: 'auto',
												maxHeight: '300px',
												objectFit: 'cover',
												borderRadius: '4px',
												filter: duotone ? `url(#${ filterId })` : undefined,
											} }
										/>
									</>
								);
							} )() }
						</div>

						{/* Image Actions */}
						<div style={ { display: 'flex', gap: '8px', marginBottom: '16px' } }>
							<MediaUpload
								onSelect={ ( media ) => onSelectImage( imageType + 'Image', media ) }
								allowedTypes={ [ 'image' ] }
								value={ image?.id }
								render={ ( { open } ) => (
									<Button variant="secondary" onClick={ open }>
										{ __( 'Replace', 'caes-reveal' ) }
									</Button>
								) }
							/>
							<Button variant="secondary" isDestructive onClick={ () => onRemoveImage( imageType + 'Image' ) }>
								{ __( 'Remove', 'caes-reveal' ) }
							</Button>
						</div>

						{/* Caption */}
						<TextControl
							label={ __( 'Caption', 'caes-reveal' ) + ' (' + __( 'optional', 'caes-reveal' ) + ')' }
							value={ image?.caption || '' }
							onChange={ ( value ) => {
								const updatedImage = { ...image, caption: value };
								onUpdate( { [ imageKey ]: updatedImage } );
							} }
							placeholder={ __( 'Add a caption', 'caes-reveal' ) }
						/>

						{/* Alt Text */}
						<TextControl
							label={ __( 'Alt Text', 'caes-reveal' ) + ' (' + __( 'recommended', 'caes-reveal' ) + ')' }
							value={ image?.alt || '' }
							onChange={ ( value ) => {
								const updatedImage = { ...image, alt: value };
								onUpdate( { [ imageKey ]: updatedImage } );
							} }
							placeholder={ __( 'Describe media for screenreaders', 'caes-reveal' ) }
						/>

						{/* Focus & Filter Buttons */}
						<div style={ { display: 'flex', gap: '8px', marginTop: '16px', flexWrap: 'wrap', alignItems: 'center' } }>
							<Button variant="secondary" onClick={ () => setFocalPointModal( imageType ) } icon="image-crop">
								{ __( 'Set Focus Point', 'caes-reveal' ) }
							</Button>
							<Button variant="secondary" onClick={ () => setDuotoneModal( imageType ) } icon="admin-appearance">
								{ duotone ? __( 'Edit Filter', 'caes-reveal' ) : __( 'Add Filter', 'caes-reveal' ) }
							</Button>
							{ duotone && <DuotoneSwatch values={ duotone } /> }
						</div>
					</div>
				) }
			</MediaUploadCheck>
		</div>
	);
};

// Focal Point Modal
const FocalPointModal = ( { frame, imageType, onUpdate, onClose } ) => {
	const imageKey = imageType === 'desktop' ? 'desktopImage' : 'mobileImage';
	const focalKey = imageType === 'desktop' ? 'desktopFocalPoint' : 'mobileFocalPoint';
	const image = frame[ imageKey ];

	if ( ! image ) {
		return null;
	}

	return (
		<Modal
			title={
				imageType === 'desktop'
					? __( 'Set Focus Point — Wide Screens', 'caes-reveal' )
					: __( 'Set Focus Point — Tall Screens', 'caes-reveal' )
			}
			onRequestClose={ onClose }
			style={ { maxWidth: '600px', width: '100%' } }
		>
			<div style={ { padding: '8px 0' } }>
				<p style={ { margin: '0 0 16px 0', color: '#757575', fontSize: '13px' } }>
					{ __( 'Click on the image to set the focal point. This determines which part of the image stays visible when cropped to fit the screen.', 'caes-reveal' ) }
				</p>

				<FocalPointPicker
					url={ image.url }
					value={ frame[ focalKey ] || { x: 0.5, y: 0.5 } }
					onChange={ ( value ) => onUpdate( { [ focalKey ]: value } ) }
				/>

				<div style={ { marginTop: '20px', display: 'flex', justifyContent: 'flex-end' } }>
					<Button variant="primary" onClick={ onClose }>
						{ __( 'Done', 'caes-reveal' ) }
					</Button>
				</div>
			</div>
		</Modal>
	);
};

// Duotone Modal
const DuotoneModal = ( { frame, imageType, onUpdate, onClose } ) => {
	const duotoneKey = imageType === 'desktop' ? 'desktopDuotone' : 'mobileDuotone';
	const duotone = imageType === 'desktop' ? ( frame.desktopDuotone || frame.duotone ) : frame.mobileDuotone;

	return (
		<Modal
			title={
				imageType === 'desktop'
					? __( 'Duotone Filter — Wide Screens', 'caes-reveal' )
					: __( 'Duotone Filter — Tall Screens', 'caes-reveal' )
			}
			onRequestClose={ onClose }
			style={ { maxWidth: '400px', width: '100%' } }
		>
			<div style={ { padding: '8px 0' } }>
				<p style={ { margin: '0 0 16px 0', color: '#757575', fontSize: '13px' } }>
					{ __( 'Apply a duotone color filter to this image. The first color replaces shadows, the second replaces highlights.', 'caes-reveal' ) }
				</p>

				<DuotonePicker
					duotonePalette={ DUOTONE_PALETTE }
					colorPalette={ COLOR_PALETTE }
					value={ duotone || undefined }
					onChange={ ( value ) => {
						if ( imageType === 'desktop' ) {
							onUpdate( { desktopDuotone: value, duotone: null } );
						} else {
							onUpdate( { mobileDuotone: value } );
						}
					} }
				/>

				<div style={ { marginTop: '20px', display: 'flex', justifyContent: 'space-between' } }>
					{ duotone && (
						<Button
							variant="tertiary"
							isDestructive
							onClick={ () => {
								if ( imageType === 'desktop' ) {
									onUpdate( { desktopDuotone: null, duotone: null } );
								} else {
									onUpdate( { mobileDuotone: null } );
								}
								onClose();
							} }
						>
							{ __( 'Remove Filter', 'caes-reveal' ) }
						</Button>
					) }
					<div style={ { marginLeft: 'auto' } }>
						<Button variant="primary" onClick={ onClose }>
							{ __( 'Done', 'caes-reveal' ) }
						</Button>
					</div>
				</div>
			</div>
		</Modal>
	);
};

export default Edit;