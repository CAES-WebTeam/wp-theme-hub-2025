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
	const overlayOpacity = context[ 'caes-hub/reveal-overlayOpacity' ] || 30;

	// Get this frame's data
	const frame = frames[ frameIndex ] || null;
	const desktopImage = frame?.desktopImage || null;
	const desktopFocalPoint = frame?.desktopFocalPoint || { x: 0.5, y: 0.5 };
	const desktopDuotone = frame?.desktopDuotone || frame?.duotone || null;

	const filterId = `reveal-frame-editor-${ clientId }`;

	const blockProps = useBlockProps( {
		className: 'reveal-frames-editor',
		'data-frame-label': frameLabel,
	} );

	const innerBlocksProps = useInnerBlocksProps(
		{
			className: 'reveal-frames-content',
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
				<div className="reveal-frames-background">
					{ desktopDuotone && getDuotoneFilter( desktopDuotone, filterId ) }
					<img
						src={ desktopImage.url }
						alt=""
						style={ {
							objectPosition: `${ desktopFocalPoint.x * 100 }% ${ desktopFocalPoint.y * 100 }%`,
							filter: desktopDuotone ? `url(#${ filterId })` : undefined,
						} }
					/>
					{/* Overlay */}
					<div
						className="reveal-frames-overlay"
						style={ { background: getOverlayRgba( overlayColor, overlayOpacity ) } }
					/>
				</div>
			) : (
				<div className="reveal-frames-no-image">
					<span>{ __( 'No background image', 'caes-reveal' ) }</span>
				</div>
			) }

			{/* Frame label */}
			<div className="reveal-frames-label">
				{ frameLabel || __( 'Frame Content', 'caes-reveal' ) }
			</div>

			{/* Content area */}
			<div { ...innerBlocksProps } />
		</div>
	);
};

export default Edit;