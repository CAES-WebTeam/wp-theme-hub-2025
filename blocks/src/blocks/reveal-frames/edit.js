import { __ } from '@wordpress/i18n';
import { useBlockProps, InnerBlocks, useInnerBlocksProps } from '@wordpress/block-editor';

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

/**
 * Convert overlay color and opacity to rgba string.
 */
const getOverlayRgba = ( overlayColor, overlayOpacity ) => {
	if ( ! overlayColor ) {
		return 'transparent';
	}
	const opacity = ( overlayOpacity || 0 ) / 100;
	const hex = overlayColor.replace( '#', '' );
	const r = parseInt( hex.substring( 0, 2 ), 16 );
	const g = parseInt( hex.substring( 2, 4 ), 16 );
	const b = parseInt( hex.substring( 4, 6 ), 16 );
	return `rgba(${ r }, ${ g }, ${ b }, ${ opacity })`;
};

const Edit = ( { attributes, context, clientId } ) => {
	const { frameIndex, frameLabel } = attributes;

	// Get frame data from parent context
	const frames = context[ 'caes-hub/reveal-frames' ] || [];
	const overlayColor = context[ 'caes-hub/reveal-overlayColor' ] || '#000000';
	const overlayOpacity = context[ 'caes-hub/reveal-overlayOpacity' ] ?? 30;

	// Get this frame's data
	const frame = frames[ frameIndex ] || null;
	const desktopImage = frame?.desktopImage || null;
	const desktopFocalPoint = frame?.desktopFocalPoint || { x: 0.5, y: 0.5 };
	const desktopDuotone = frame?.desktopDuotone || frame?.duotone || null;

	const filterId = `reveal-frame-editor-${ clientId }`;

	const blockProps = useBlockProps( {
		className: 'reveal-frames-editor',
		'data-frame-label': frameLabel,
		style: {
			position: 'relative',
			minHeight: '100vh',
			borderRadius: '4px',
			overflow: 'hidden',
			marginBottom: '8px',
		},
	} );

	const innerBlocksProps = useInnerBlocksProps(
		{
			className: 'reveal-frames-content',
			style: {
				position: 'relative',
				zIndex: 5,
				minHeight: '100vh',
				padding: '60px 40px 40px',
				display: 'flex',
				flexDirection: 'column',
			},
		},
		{
			templateLock: false,
			renderAppender: InnerBlocks.ButtonBlockAppender,
		}
	);

	return (
		<div { ...blockProps }>
			{/* Background image layer */}
			{ desktopImage ? (
				<div
					className="reveal-frames-background"
					style={ {
						position: 'absolute',
						top: 0,
						left: 0,
						right: 0,
						bottom: 0,
						zIndex: 0,
						overflow: 'hidden',
					} }
				>
					{ desktopDuotone && getDuotoneFilter( desktopDuotone, filterId ) }
					<img
						src={ desktopImage.url }
						alt=""
						style={ {
							width: '100%',
							height: '100%',
							objectFit: 'cover',
							objectPosition: `${ desktopFocalPoint.x * 100 }% ${ desktopFocalPoint.y * 100 }%`,
							filter: desktopDuotone ? `url(#${ filterId })` : undefined,
						} }
					/>
					{/* Overlay */}
					<div
						className="reveal-frames-overlay"
						style={ {
							position: 'absolute',
							top: 0,
							left: 0,
							right: 0,
							bottom: 0,
							background: getOverlayRgba( overlayColor, overlayOpacity ),
							pointerEvents: 'none',
						} }
					/>
				</div>
			) : (
				<div
					className="reveal-frames-no-image"
					style={ {
						position: 'absolute',
						top: 0,
						left: 0,
						right: 0,
						bottom: 0,
						zIndex: 0,
						display: 'flex',
						alignItems: 'center',
						justifyContent: 'center',
						background: 'linear-gradient(135deg, #e0e0e0 0%, #f5f5f5 100%)',
						border: '2px dashed #ccc',
						borderRadius: '4px',
					} }
				>
					<span
						style={ {
							color: '#757575',
							fontSize: '14px',
							fontWeight: 500,
							padding: '12px 20px',
							background: 'rgba(255, 255, 255, 0.9)',
							borderRadius: '4px',
						} }
					>
						{ __( 'No background image', 'caes-reveal' ) }
					</span>
				</div>
			) }

			{/* Frame label */}
			<div
				className="reveal-frames-label"
				style={ {
					position: 'absolute',
					top: '12px',
					left: '12px',
					zIndex: 10,
					background: 'rgba(0, 0, 0, 0.75)',
					color: '#fff',
					padding: '6px 12px',
					fontSize: '12px',
					fontWeight: 600,
					borderRadius: '4px',
					textTransform: 'uppercase',
					letterSpacing: '0.5px',
					pointerEvents: 'none',
				} }
			>
				{ frameLabel || __( 'Frame Content', 'caes-reveal' ) }
			</div>

			{/* Content area */}
			<div { ...innerBlocksProps } />
		</div>
	);
};

export default Edit;