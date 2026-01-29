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
		speed: 'normal',
	},
};

const generateFrameId = () => {
	return 'frame-' + Math.random().toString( 36 ).substr( 2, 9 );
};

const Edit = ( { attributes, setAttributes, clientId } ) => {
	const { frames, overlayColor, overlayOpacity, minHeight } = attributes;
	const [ isPreviewMode, setIsPreviewMode ] = useState( false );
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

		// Check if we need to update inner blocks
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
				// Try to preserve existing content if the block exists
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

				// Create new block
				return createBlock( 'caes-hub/reveal-frames', {
					frameIndex: index,
					frameLabel: `Frame ${ index + 1 } Content`,
				} );
			} );

			replaceInnerBlocks( clientId, newInnerBlocks, false );
		}
	}, [ frames.length, clientId ] );

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
		if ( frames.length === 1 ) {
			return; // Don't allow removing the last frame
		}
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

	// Duplicate a frame
	const duplicateFrame = ( frameIndex ) => {
		const frameToDuplicate = frames[ frameIndex ];
		const duplicatedFrame = {
			...JSON.parse( JSON.stringify( frameToDuplicate ) ), // Deep clone
			id: generateFrameId(),
		};
		const newFrames = [ ...frames ];
		newFrames.splice( frameIndex + 1, 0, duplicatedFrame );
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

	// Calculate min-height based on per-frame transition speeds
	const getCalculatedMinHeight = () => {
		const count = Math.max( 1, frames.length );
		const speedMultipliers = { slow: 1.5, normal: 1, fast: 0.5 };
		let totalVh = 100;

		for ( let i = 1; i < count; i++ ) {
			const speed = frames[ i ]?.transition?.speed || 'normal';
			const multiplier = speedMultipliers[ speed ] || 1;
			totalVh += 100 * multiplier;
		}

		return `${ totalVh }vh`;
	};

	const blockProps = useBlockProps( {
		className: 'caes-reveal-editor',
		style: {
			'--reveal-min-height': getCalculatedMinHeight(),
		},
	} );

	return (
		<div { ...blockProps }>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						icon={ isPreviewMode ? 'edit' : 'visibility' }
						label={ isPreviewMode ? __( 'Edit', 'caes-reveal' ) : __( 'Preview', 'caes-reveal' ) }
						onClick={ () => setIsPreviewMode( ! isPreviewMode ) }
					/>
				</ToolbarGroup>
			</BlockControls>

			<InspectorControls>
				<PanelBody title={ __( 'Frames', 'caes-reveal' ) } initialOpen={ true }>
					<p style={ { marginBottom: '12px', fontSize: '13px', color: '#757575' } }>
						{ __( 'Each frame creates a full-window background that transitions as users scroll.', 'caes-reveal' ) }
					</p>

					{ frames.map( ( frame, index ) => (
						<FramePanel
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
						/>
					) ) }

					<Button variant="secondary" onClick={ addFrame } style={ { width: '100%', marginTop: '12px' } }>
						{ __( 'Add Frame', 'caes-reveal' ) }
					</Button>
				</PanelBody>

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

			{ isPreviewMode ? (
				<div className="reveal-preview">
					<Notice status="info" isDismissible={ false }>
						{ __( 'Preview mode: Scroll to see frame transitions', 'caes-reveal' ) }
					</Notice>
					<div className="reveal-background">
						{ frames.map( ( frame, index ) => {
							if ( ! frame.desktopImage ) {
								return null;
							}

							const filterId = `preview-${ clientId }-${ index }`;
							const desktopDuotone = frame.desktopDuotone || frame.duotone;

							return (
								<div key={ frame.id || index } className="reveal-frame">
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
									<div className="reveal-overlay" style={ { background: getOverlayRgba() } } />
								</div>
							);
						} ) }
					</div>
					<div className="reveal-content">
						<InnerBlocks />
					</div>
				</div>
			) : (
				<div className="reveal-editor-mode">
					<Notice status="info" isDismissible={ false }>
						{ __( 'Add content to each frame using the containers below. Content will appear/disappear as users scroll through frames.', 'caes-reveal' ) }
					</Notice>
					<InnerBlocks allowedBlocks={ [ 'caes-hub/reveal-frames' ] } />
				</div>
			) }
		</div>
	);
};

// Frame Panel Component
const FramePanel = ( {
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
} ) => {
	const [ isOpen, setIsOpen ] = useState( index === 0 );
	const [ focalPointModal, setFocalPointModal ] = useState( null );
	const [ duotoneModal, setDuotoneModal ] = useState( null );

	return (
		<div
			style={ {
				border: '1px solid #ddd',
				borderRadius: '4px',
				marginBottom: '12px',
				background: '#fff',
			} }
		>
			<div
				style={ {
					display: 'flex',
					justifyContent: 'space-between',
					alignItems: 'center',
					padding: '12px',
					cursor: 'pointer',
					borderBottom: isOpen ? '1px solid #ddd' : 'none',
				} }
				onClick={ () => setIsOpen( ! isOpen ) }
			>
				<strong>{ __( 'Frame', 'caes-reveal' ) } { index + 1 }</strong>
				<div style={ { display: 'flex', gap: '8px' } } onClick={ ( e ) => e.stopPropagation() }>
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

			{ isOpen && (
				<div style={ { padding: '16px' } }>
					{ /* Desktop Image */ }
					<div style={ { marginBottom: '20px' } }>
						<label style={ { display: 'block', marginBottom: '8px', fontWeight: 500 } }>
							{ __( 'Desktop Image', 'caes-reveal' ) }
							<span style={ { fontWeight: 'normal', color: '#e65054' } }> *</span>
						</label>
						<MediaUploadCheck>
							{ ! frame.desktopImage ? (
								<MediaUpload
									onSelect={ ( media ) => onSelectImage( 'desktopImage', media ) }
									allowedTypes={ [ 'image' ] }
									render={ ( { open } ) => (
										<Button variant="secondary" onClick={ open } style={ { width: '100%' } }>
											{ __( 'Select Image', 'caes-reveal' ) }
										</Button>
									) }
								/>
							) : (
								<div>
									<div style={ { marginBottom: '12px' } }>
										{ ( () => {
											const filterId = `editor-${ frame.id }-desktop`;
											const duotone = frame.desktopDuotone || frame.duotone;
											return (
												<>
													{ duotone && getDuotoneFilter( duotone, filterId ) }
													<img
														src={ frame.desktopImage.url }
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
									<div style={ { display: 'flex', gap: '8px', marginBottom: '12px' } }>
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
										<Button variant="secondary" isDestructive onClick={ () => onRemoveImage( 'desktopImage' ) } size="small">
											{ __( 'Remove', 'caes-reveal' ) }
										</Button>
									</div>

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
									/>

									<div style={ { display: 'flex', gap: '8px', marginTop: '12px', flexWrap: 'wrap', alignItems: 'center' } }>
										<Button variant="secondary" onClick={ () => setFocalPointModal( 'desktop' ) } icon="image-crop">
											{ __( 'Set Focus Point', 'caes-reveal' ) }
										</Button>
										<Button variant="secondary" onClick={ () => setDuotoneModal( 'desktop' ) } icon="admin-appearance">
											{ frame.desktopDuotone || frame.duotone
												? __( 'Edit Filter', 'caes-reveal' )
												: __( 'Add Filter', 'caes-reveal' ) }
										</Button>
										{ ( frame.desktopDuotone || frame.duotone ) && (
											<DuotoneSwatch values={ frame.desktopDuotone || frame.duotone } />
										) }
									</div>
								</div>
							) }
						</MediaUploadCheck>
					</div>

					{ /* Mobile Image */ }
					<div style={ { marginBottom: '20px' } }>
						<label style={ { display: 'block', marginBottom: '8px', fontWeight: 500 } }>
							{ __( 'Mobile Image', 'caes-reveal' ) }
							<span style={ { fontWeight: 'normal', color: '#757575' } }> ({ __( 'optional', 'caes-reveal' ) })</span>
						</label>
						<p style={ { fontSize: '13px', color: '#757575', marginBottom: '12px' } }>
							{ __( 'Provide a different image optimized for portrait/mobile screens.', 'caes-reveal' ) }
						</p>
						<MediaUploadCheck>
							{ ! frame.mobileImage ? (
								<MediaUpload
									onSelect={ ( media ) => onSelectImage( 'mobileImage', media ) }
									allowedTypes={ [ 'image' ] }
									render={ ( { open } ) => (
										<Button variant="secondary" onClick={ open } style={ { width: '100%' } }>
											{ __( 'Select Image', 'caes-reveal' ) }
										</Button>
									) }
								/>
							) : (
								<div>
									<div style={ { marginBottom: '12px' } }>
										{ ( () => {
											const filterId = `editor-${ frame.id }-mobile`;
											const duotone = frame.mobileDuotone;
											return (
												<>
													{ duotone && getDuotoneFilter( duotone, filterId ) }
													<img
														src={ frame.mobileImage.url }
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
										<Button variant="secondary" isDestructive onClick={ () => onRemoveImage( 'mobileImage' ) } size="small">
											{ __( 'Remove', 'caes-reveal' ) }
										</Button>
									</div>

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
											<Button variant="secondary" onClick={ () => setFocalPointModal( 'mobile' ) } icon="image-crop">
												{ __( 'Set Focus Point', 'caes-reveal' ) }
											</Button>
											<Button variant="secondary" onClick={ () => setDuotoneModal( 'mobile' ) } icon="admin-appearance">
												{ frame.mobileDuotone ? __( 'Edit Filter', 'caes-reveal' ) : __( 'Add Filter', 'caes-reveal' ) }
											</Button>
											{ frame.mobileDuotone && <DuotoneSwatch values={ frame.mobileDuotone } /> }
										</div>
									) }
								</div>
							) }
						</MediaUploadCheck>
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
						<div style={ { display: 'flex', gap: '16px' } }>
							<div style={ { flex: 1 } }>
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
							</div>
							<div style={ { flex: 1 } }>
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
							{ __(
								'Click on the image to set the focal point. This determines which part of the image stays visible when cropped to fit the screen.',
								'caes-reveal'
							) }
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
							<Button variant="primary" onClick={ () => setFocalPointModal( null ) }>
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
							{ __(
								'Apply a duotone color filter to this image. The first color replaces shadows, the second replaces highlights.',
								'caes-reveal'
							) }
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
							{ ( ( duotoneModal === 'desktop' && ( frame.desktopDuotone || frame.duotone ) ) ||
								( duotoneModal === 'mobile' && frame.mobileDuotone ) ) && (
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
								<Button variant="primary" onClick={ () => setDuotoneModal( null ) }>
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