import { useBlockProps, InnerBlocks, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, PanelRow, ToggleControl, RangeControl } from '@wordpress/components';
import { useState, useRef, useEffect } from '@wordpress/element';

const TEMPLATE = [
	[ 'core/group', { 
		className: 'flip-card-front',
		metadata: { name: 'Front Side' },
		style: {
			elements: {
				link: {
					color: {
						text: 'var:preset|color|base'
					}
				}
			},
			spacing: {
				blockGap: '0',
				padding: {
					top: 'var:preset|spacing|50',
					bottom: 'var:preset|spacing|50',
					left: 'var:preset|spacing|50',
					right: 'var:preset|spacing|50'
				}
			}
		},
		backgroundColor: 'olympic',
		textColor: 'base',
		layout: {
			type: 'flex',
			orientation: 'vertical',
			verticalAlignment: 'center',
			justifyContent: 'center'
		}
	}, [
		[ 'core/paragraph', { 
			align: 'center',
			placeholder: 'Add your front side content here...',
			content: 'This is the front side. Add content here.'
		} ]
	]],
	[ 'core/group', { 
		className: 'flip-card-back',
		metadata: { name: 'Back Side' },
		style: {
			elements: {
				link: {
					color: {
						text: 'var:preset|color|base'
					}
				}
			},
			spacing: {
				blockGap: '0',
				padding: {
					top: 'var:preset|spacing|50',
					bottom: 'var:preset|spacing|50',
					left: 'var:preset|spacing|50',
					right: 'var:preset|spacing|50'
				}
			}
		},
		backgroundColor: 'olympic',
		textColor: 'base',
		layout: {
			type: 'flex',
			orientation: 'vertical',
			flexWrap: 'wrap',
			verticalAlignment: 'center',
			justifyContent: 'center'
		}
	}, [
		[ 'core/paragraph', { 
			align: 'center',
			placeholder: 'Add your back side content here...',
			content: 'This is the back side. Add content here.'
		} ]
	]]
];

export default function Edit( { attributes, setAttributes } ) {
	const { showPreview, minHeight } = attributes;
	const [ isFlipped, setIsFlipped ] = useState( false );
	const cardRef = useRef( null );

	const blockProps = useBlockProps( {
		className: `flip-card-container ${ showPreview ? 'is-preview-mode' : 'is-edit-mode' } ${ isFlipped ? 'is-flipped' : '' }`,
		style: showPreview ? { minHeight: `${minHeight}px` } : {}
	} );

	// Handle keyboard navigation for accessibility
	const handleKeyDown = ( event ) => {
		if ( showPreview && ( event.code === 'Enter' || event.code === 'Space' ) && !event.repeat ) {
			event.preventDefault();
			toggleFlip();
		}
	};

	// Handle mouse interactions
	const handleMouseEnter = () => {
		if ( showPreview ) {
			setIsFlipped( true );
		}
	};

	const handleMouseLeave = () => {
		if ( showPreview ) {
			setIsFlipped( false );
		}
	};

	// Handle click for accessibility
	const handleClick = () => {
		if ( showPreview ) {
			toggleFlip();
		}
	};

	const toggleFlip = () => {
		setIsFlipped( !isFlipped );
	};

	// Update ARIA attributes for accessibility in preview mode
	useEffect( () => {
		if ( showPreview && cardRef.current ) {
			const card = cardRef.current;
			const frontSide = card.querySelector( '.flip-card-front' );
			const backSide = card.querySelector( '.flip-card-back' );
			
			// Update aria-pressed
			card.setAttribute( 'aria-pressed', String( isFlipped ) );
			
			// Update aria-hidden for sides
			if ( frontSide ) {
				frontSide.setAttribute( 'aria-hidden', String( isFlipped ) );
			}
			if ( backSide ) {
				backSide.setAttribute( 'aria-hidden', String( !isFlipped ) );
			}

			// Manage tabindex for focusable elements
			const frontFocusables = frontSide?.querySelectorAll( 'a, button, input, textarea, select, [tabindex]:not([tabindex="-1"])' ) || [];
			const backFocusables = backSide?.querySelectorAll( 'a, button, input, textarea, select, [tabindex]:not([tabindex="-1"])' ) || [];

			frontFocusables.forEach( el => {
				el.setAttribute( 'tabindex', isFlipped ? '-1' : '0' );
			});

			backFocusables.forEach( el => {
				el.setAttribute( 'tabindex', isFlipped ? '0' : '-1' );
			});
		}
	}, [ showPreview, isFlipped ] );

	const interactionProps = showPreview ? {
		role: "button",
		'aria-pressed': isFlipped ? 'true' : 'false',
		tabIndex: "0",
		'aria-describedby': "flip-card-desc",
		onMouseEnter: handleMouseEnter,
		onMouseLeave: handleMouseLeave,
		onClick: handleClick,
		onKeyDown: handleKeyDown,
	} : {};

	return (
		<>
			<InspectorControls>
				<PanelBody title="Flip Card Settings">
					<PanelRow>
						<ToggleControl
							label="Enable Preview Mode"
							help="When enabled, the card will flip on hover/click in the editor. Disable to edit both sides easily."
							checked={ showPreview }
							onChange={ ( value ) => setAttributes( { showPreview: value } ) }
						/>
					</PanelRow>
					<RangeControl
						label="Card Height"
						value={ minHeight }
						onChange={ ( value ) => setAttributes( { minHeight: value } ) }
						min={ 200 }
						max={ 800 }
						step={ 10 }
						help="Set the minimum height of the flip card in pixels."
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps } ref={ cardRef } { ...interactionProps }>
				<div className="flip-card-inner">
					<InnerBlocks template={ TEMPLATE } />
				</div>
				
				{ showPreview && (
					<span className="sr-only" id="flip-card-desc">
						This is a flip card. Activate by pressing enter or spacebar, or hover with mouse.
					</span>
				) }
			</div>
		</>
	);
}