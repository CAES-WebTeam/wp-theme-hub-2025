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
	ToolbarGroup,
	ToolbarButton,
	Modal,
	DuotonePicker,
	DuotoneSwatch,
	RangeControl,
	ToggleControl,
} from '@wordpress/components';
import { useState, useEffect, useRef } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';

/**
 * Generate duotone SVG filter markup
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
	{ label: __( 'Fade', 'caes-motion-scroll' ), value: 'fade' },
	{ label: __( 'Up', 'caes-motion-scroll' ), value: 'up' },
	{ label: __( 'Down', 'caes-motion-scroll' ), value: 'down' },
	{ label: __( 'Left', 'caes-motion-scroll' ), value: 'left' },
	{ label: __( 'Right', 'caes-motion-scroll' ), value: 'right' },
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
	const { frames, overlayColor, overlayOpacity, contentPosition, mediaWidth } = attributes;
	const [ showFrameManager, setShowFrameManager ] = useState( false );
	const [ showOverlayColorPicker, setShowOverlayColorPicker ] = useState( false );
	
	const isSyncingRef = useRef( false );

	const { replaceInnerBlocks, updateBlockAttributes } = useDispatch( blockEditorStore );
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

	// Sync frames array with inner blocks order
	useEffect( () => {
		if ( frames.length === 0 || isSyncingRef.current ) {
			return;
		}

		const innerBlockFrameIds = innerBlocks
			.filter( ( block ) => block.name === 'caes-hub/motion-scroll-frame' )
			.map( ( block ) => block.attributes.frameId );

		const frameIds = frames.map( ( frame ) => frame.id );

		const hasBeenReordered = innerBlockFrameIds.length === frameIds.length &&
			innerBlockFrameIds.every( ( id ) => frameIds.includes( id ) ) &&
			innerBlockFrameIds.some( ( id, index ) => id !== frameIds[ index ] );

		if ( hasBeenReordered ) {
			isSyncingRef.current = true;
			const reorderedFrames = innerBlockFrameIds.map( ( id ) => 
				frames.find( ( frame ) => frame.id === id )
			).filter( Boolean );
			
			setAttributes( { frames: reorderedFrames } );
			
			innerBlocks.forEach( ( block, index ) => {
				if ( block.name === 'caes-hub/motion-scroll-frame' ) {
					updateBlockAttributes( block.clientId, {
						frameIndex: index,
						frameLabel: `Frame ${ index + 1 }`,
					} );
				}
			} );
			
			setTimeout( () => {
				isSyncingRef.current = false;
			}, 100 );
			return;
		}

		const needsUpdate =
			innerBlocks.length !== frames.length ||
			innerBlocks.some(
				( block, index ) =>
					block.name !== 'caes-hub/motion-scroll-frame' ||
					block.attributes.frameIndex !== index ||
					block.attributes.frameId !== frames[ index ]?.id
			);

		if ( needsUpdate ) {
			isSyncingRef.current = true;
			
			const newInnerBlocks = frames.map( ( frame, index ) => {
				const existingBlock = innerBlocks.find(
					( b ) => b.name === 'caes-hub/motion-scroll-frame' && b.attributes.frameId === frame.id
				) || innerBlocks.find(
					( b ) => b.name === 'caes-hub/motion-scroll-frame' && b.attributes.frameIndex === index
				);

				if ( existingBlock ) {
					return createBlock(
						'caes-hub/motion-scroll-frame',
						{
							...existingBlock.attributes,
							frameIndex: index,
							frameId: frame.id,
							frameLabel: `Frame ${ index + 1 }`,
						},
						existingBlock.innerBlocks
					);
				}

				return createBlock( 'caes-hub/motion-scroll-frame', {
					frameIndex: index,
					frameId: frame.id,
					frameLabel: `Frame ${ index + 1 }`,
				} );
			} );

			replaceInnerBlocks( clientId, newInnerBlocks, false );
			
			setTimeout( () => {
				isSyncingRef.current = false;
			}, 100 );
		}
	}, [ frames, innerBlocks, clientId ] );

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
			captionText: media.caption || '',
			captionLink: '',
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
		className: 'caes-motion-scroll-editor',
	} );

	return (
		<>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						icon="admin-generic"
						label={ __( 'Manage Frames', 'caes-motion-scroll' ) }
						onClick={ () => setShowFrameManager( true ) }
					/>
				</ToolbarGroup>
			</BlockControls>

			<InspectorControls>
				<PanelBody title={ __( 'Layout', 'caes-motion-scroll' ) } initialOpen={ true }>
					<SelectControl
						label={ __( 'Content Position', 'caes-motion-scroll' ) }
						value={ contentPosition }
						options={ [
							{ label: __( 'Left', 'caes-motion-scroll' ), value: 'left' },
							{ label: __( 'Right', 'caes-motion-scroll' ), value: 'right' },
						] }
						onChange={ ( value ) => setAttributes( { contentPosition: value } ) }
					/>
					<RangeControl
						label={ __( 'Media Width', 'caes-motion-scroll' ) }
						value={ mediaWidth }
						onChange={ ( value ) => setAttributes( { mediaWidth: value } ) }
						min={ 30 }
						max={ 70 }
						step={ 5 }
						help={ __( 'Percentage width of the media column', 'caes-motion-scroll' ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Frames', 'caes-motion-scroll' ) } initialOpen={ true }>
					<p style={ { marginBottom: '12px', color: '#757575', fontSize: '13px' } }>
						{ __( 'This block has', 'caes-motion-scroll' ) } { frames.length } { frames.length === 1 ? __( 'frame', 'caes-motion-scroll' ) : __( 'frames', 'caes-motion-scroll' ) }.
					</p>
					<Button
						variant="secondary"
						onClick={ () => setShowFrameManager( true ) }
						style={ { width: '100%' } }
					>
						{ __( 'Manage Frames', 'caes-motion-scroll' ) }
					</Button>
				</PanelBody>

				<PanelBody title={ __( 'Overlay', 'caes-motion-scroll' ) } initialOpen={ false }>
					<div style={ { marginBottom: '16px' } }>
						<label style={ { display: 'block', marginBottom: '8px', fontWeight: 500 } }>
							{ __( 'Overlay Color', 'caes-motion-scroll' ) }
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
						label={ __( 'Overlay Opacity', 'caes-motion-scroll' ) }
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
						<strong>{ __( 'Preview:', 'caes-motion-scroll' ) }</strong>
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
				<div 
					className="motion-scroll-editor-layout"
					data-content-position={ contentPosition }
					style={ {
						display: 'grid',
						gridTemplateColumns: contentPosition === 'left' 
							? `${ 100 - mediaWidth }% ${ mediaWidth }%`
							: `${ mediaWidth }% ${ 100 - mediaWidth }%`,
						gap: '20px',
						minHeight: '400px',
					} }
				>
					<div 
						className="motion-scroll-editor-content"
						style={ { order: contentPosition === 'left' ? 1 : 2 } }
					>
						<InnerBlocks allowedBlocks={ [ 'caes-hub/motion-scroll-frame' ] } />
					</div>
					<div 
						className="motion-scroll-editor-media-preview"
						style={ { 
							order: contentPosition === 'left' ? 2 : 1,
							background: '#f0f0f0',
							borderRadius: '4px',
							display: 'flex',
							alignItems: 'center',
							justifyContent: 'center',
							padding: '20px',
							minHeight: '300px',
						} }
					>
						{ frames.length > 0 && frames[0].desktopImage ? (
							<img 
								src={ frames[0].desktopImage.url } 
								alt="" 
								style={ { 
									maxWidth: '100%', 
									maxHeight: '300px', 
									objectFit: 'contain',
									borderRadius: '4px',
								} } 
							/>
						) : (
							<span style={ { color: '#757575', fontSize: '14px' } }>
								{ __( 'Media preview (first frame)', 'caes-motion-scroll' ) }
							</span>
						) }
					</div>
				</div>
			</div>

			{/* Frame Manager Modal */}
			{ showFrameManager && (
				<Modal
					title={ __( 'Manage Frames', 'caes-motion-scroll' ) }
					onRequestClose={ () => setShowFrameManager( false ) }
					className="motion-scroll-frame-manager-modal"
					style={ { maxWidth: '900px', width: '90vw' } }
				>
					<div style={ { padding: '20px 0' } }>
						<p style={ { marginBottom: '20px', color: '#757575' } }>
							{ __( 'Configure images and transitions for each frame. Add content to frames in the editor.', 'caes-motion-scroll' ) }
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
							{ __( 'Add Frame', 'caes-motion-scroll' ) }
						</Button>

						<div style={ { marginTop: '20px', textAlign: 'right' } }>
							<Button variant="secondary" onClick={ () => setShowFrameManager( false ) }>
								{ __( 'Done', 'caes-motion-scroll' ) }
							</Button>
						</div>
					</div>
				</Modal>
			) }
		</>
	);
};

// Frame Manager Panel Component
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
	const [ isOpen, setIsOpen ] = useState( false );
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
					borderBottom: isOpen ? '1px solid #ddd' : 'none',
					background: '#f9f9f9',
					cursor: 'pointer',
				} }
				onClick={ () => setIsOpen( ! isOpen ) }
			>
				<div style={ { display: 'flex', alignItems: 'center', gap: '12px' } }>
					{/* Thumbnail */}
					{ frame.desktopImage ? (
						<div
							style={ {
								width: '60px',
								height: '40px',
								borderRadius: '4px',
								overflow: 'hidden',
								border: '1px solid #ddd',
								flexShrink: 0,
							} }
						>
							{ ( () => {
								const filterId = `thumbnail-${ clientId }-${ index }`;
								const duotone = frame.desktopDuotone || frame.duotone;
								return (
									<>
										{ duotone && getDuotoneFilter( duotone, filterId ) }
										<img
											src={ frame.desktopImage.url }
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
					) : (
						<div
							style={ {
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
							} }
						>
							No image
						</div>
					) }
					<strong style={ { fontSize: '16px' } }>
						{ __( 'Frame', 'caes-motion-scroll' ) } { index + 1 }
					</strong>
				</div>
				<div style={ { display: 'flex', gap: '8px', alignItems: 'center' } } onClick={ ( e ) => e.stopPropagation() }>
					{ index > 0 && (
						<Button size="small" icon="arrow-up-alt2" onClick={ onMoveUp } label={ __( 'Move up', 'caes-motion-scroll' ) } />
					) }
					{ index < totalFrames - 1 && (
						<Button size="small" icon="arrow-down-alt2" onClick={ onMoveDown } label={ __( 'Move down', 'caes-motion-scroll' ) } />
					) }
					<Button size="small" icon="admin-page" onClick={ onDuplicate } label={ __( 'Duplicate', 'caes-motion-scroll' ) } />
					{ totalFrames > 1 && (
						<Button
							size="small"
							icon="trash"
							onClick={ onRemove }
							label={ __( 'Remove', 'caes-motion-scroll' ) }
							isDestructive
						/>
					) }
					<Button
						size="small"
						icon={ isOpen ? 'arrow-up-alt2' : 'arrow-down-alt2' }
						onClick={ ( e ) => {
							e.stopPropagation();
							setIsOpen( ! isOpen );
						} }
						label={ isOpen ? __( 'Collapse', 'caes-motion-scroll' ) : __( 'Expand', 'caes-motion-scroll' ) }
					/>
				</div>
			</div>

			{/* Content */}
			{ isOpen && (
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
							{ __( 'Transition', 'caes-motion-scroll' ) }
						</label>
						<div style={ { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px' } }>
							<SelectControl
								label={ __( 'Type', 'caes-motion-scroll' ) }
								value={ frame.transition?.type || 'fade' }
								options={ TRANSITION_OPTIONS }
								onChange={ ( value ) =>
									onUpdate( {
										transition: { ...frame.transition, type: value },
									} )
								}
							/>
							<SelectControl
								label={ __( 'Speed', 'caes-motion-scroll' ) }
								value={ frame.transition?.speed || 'normal' }
								options={ [
									{ label: __( 'Slow', 'caes-motion-scroll' ), value: 'slow' },
									{ label: __( 'Normal', 'caes-motion-scroll' ), value: 'normal' },
									{ label: __( 'Fast', 'caes-motion-scroll' ), value: 'fast' },
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
			) }

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
						{ imageType === 'desktop' ? __( 'Large Screens', 'caes-motion-scroll' ) : __( 'Small Screens', 'caes-motion-scroll' ) }
					</h3>
				</div>
				<p style={ { margin: 0, fontSize: '13px', color: '#757575' } }>
					{ imageType === 'desktop' 
						? __( 'Screens 900px or wider', 'caes-motion-scroll' )
						: __( 'Screens under 900px wide', 'caes-motion-scroll' )
					}
				</p>
			</div>

			<label style={ { display: 'block', marginBottom: '8px', fontWeight: 500, fontSize: '13px', color: '#1e1e1e' } }>
				{ __( 'Image (will scale to column width)', 'caes-motion-scroll' ) }
			</label>
			<p style={ { margin: '0 0 12px', fontSize: '12px', color: '#757575' } }>
				{ imageType === 'desktop'
					? __( 'Recommended: JPEG @ 750px wide', 'caes-motion-scroll' )
					: __( 'Recommended: JPEG @ 750 x 422px', 'caes-motion-scroll' )
				}
			</p>

			<MediaUploadCheck>
				{ ! image ? (
					<div>
						<MediaUpload
							onSelect={ ( media ) => onSelectImage( imageType + 'Image', media ) }
							allowedTypes={ [ 'image' ] }
							render={ ( { open } ) => (
								<Button variant="secondary" onClick={ open } style={ { width: '100%', height: '150px' } }>
									{ __( 'Select Image', 'caes-motion-scroll' ) }
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
												maxHeight: '200px',
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
										{ __( 'Replace', 'caes-motion-scroll' ) }
									</Button>
								) }
							/>
							<Button variant="secondary" isDestructive onClick={ () => onRemoveImage( imageType + 'Image' ) }>
								{ __( 'Remove', 'caes-motion-scroll' ) }
							</Button>
						</div>

						{/* Caption */}
						<TextControl
							label={ __( 'Caption', 'caes-motion-scroll' ) + ' (' + __( 'optional', 'caes-motion-scroll' ) + ')' }
							value={ image?.captionText || image?.caption || '' }
							onChange={ ( value ) => {
								const updatedImage = { ...image, captionText: value };
								onUpdate( { [ imageKey ]: updatedImage } );
							} }
							placeholder={ __( 'Add a caption', 'caes-motion-scroll' ) }
						/>

						{/* Caption Link */}
						<div style={ { marginBottom: '16px' } }>
							<TextControl
								label={ __( 'Caption Link URL', 'caes-motion-scroll' ) + ' (' + __( 'optional', 'caes-motion-scroll' ) + ')' }
								value={ image?.captionLink || '' }
								onChange={ ( value ) => {
									const updatedImage = { ...image, captionLink: value };
									onUpdate( { [ imageKey ]: updatedImage } );
								} }
								placeholder={ __( 'https://example.com', 'caes-motion-scroll' ) }
								type="url"
							/>
							{ image?.captionLink && (
								<p style={ { margin: '4px 0 0', fontSize: '12px', color: '#757575' } }>
									{ __( 'The entire caption will be linked.', 'caes-motion-scroll' ) }
								</p>
							) }
						</div>

						{/* Alt Text */}
						<TextControl
							label={ __( 'Alt Text', 'caes-motion-scroll' ) + ' (' + __( 'recommended', 'caes-motion-scroll' ) + ')' }
							value={ image?.alt || '' }
							onChange={ ( value ) => {
								const updatedImage = { ...image, alt: value };
								onUpdate( { [ imageKey ]: updatedImage } );
							} }
							placeholder={ __( 'Describe media for screenreaders', 'caes-motion-scroll' ) }
						/>

						{/* Focus & Filter Buttons */}
						<div style={ { display: 'flex', gap: '8px', marginTop: '16px', flexWrap: 'wrap', alignItems: 'center' } }>
							<Button variant="secondary" onClick={ () => setFocalPointModal( imageType ) } icon="image-crop">
								{ __( 'Set Focus Point', 'caes-motion-scroll' ) }
							</Button>
							<Button variant="secondary" onClick={ () => setDuotoneModal( imageType ) } icon="admin-appearance">
								{ duotone ? __( 'Edit Filter', 'caes-motion-scroll' ) : __( 'Add Filter', 'caes-motion-scroll' ) }
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
					? __( 'Set Focus Point — Large Screens', 'caes-motion-scroll' )
					: __( 'Set Focus Point — Small Screens', 'caes-motion-scroll' )
			}
			onRequestClose={ onClose }
			style={ { maxWidth: '600px', width: '100%' } }
		>
			<div style={ { padding: '8px 0' } }>
				<p style={ { margin: '0 0 16px 0', color: '#757575', fontSize: '13px' } }>
					{ __( 'Click on the image to set the focal point. This determines which part of the image stays visible when cropped.', 'caes-motion-scroll' ) }
				</p>

				<FocalPointPicker
					url={ image.url }
					value={ frame[ focalKey ] || { x: 0.5, y: 0.5 } }
					onChange={ ( value ) => onUpdate( { [ focalKey ]: value } ) }
				/>

				<div style={ { marginTop: '20px', display: 'flex', justifyContent: 'flex-end' } }>
					<Button variant="primary" onClick={ onClose }>
						{ __( 'Done', 'caes-motion-scroll' ) }
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
					? __( 'Duotone Filter — Large Screens', 'caes-motion-scroll' )
					: __( 'Duotone Filter — Small Screens', 'caes-motion-scroll' )
			}
			onRequestClose={ onClose }
			style={ { maxWidth: '400px', width: '100%' } }
		>
			<div style={ { padding: '8px 0' } }>
				<p style={ { margin: '0 0 16px 0', color: '#757575', fontSize: '13px' } }>
					{ __( 'Apply a duotone color filter to this image.', 'caes-motion-scroll' ) }
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
							{ __( 'Remove Filter', 'caes-motion-scroll' ) }
						</Button>
					) }
					<div style={ { marginLeft: 'auto' } }>
						<Button variant="primary" onClick={ onClose }>
							{ __( 'Done', 'caes-motion-scroll' ) }
						</Button>
					</div>
				</div>
			</div>
		</Modal>
	);
};

export default Edit;
