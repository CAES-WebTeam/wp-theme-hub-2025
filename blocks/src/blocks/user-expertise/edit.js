/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	ColorPalette,
	BaseControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';

/**
 * Editor component for the User Expertise block.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to set attributes.
 * @param {Object}   props.context       Block context.
 * @return {JSX.Element} Block editor component.
 */
export default function Edit( { attributes, setAttributes, context } ) {
	const {
		backgroundColor,
		textColor,
		customBackgroundColor,
		customTextColor,
	} = attributes;

	const { postId, postType } = context;

	// Get theme color palette
	const colors = useSelect( ( select ) => {
		const settings = select( 'core/block-editor' ).getSettings();
		return settings.colors || [];
	}, [] );

	// Find the color object for named colors
	const getColorValue = ( colorSlug, customColor ) => {
		if ( customColor ) {
			return customColor;
		}
		const colorObj = colors.find( ( c ) => c.slug === colorSlug );
		return colorObj ? colorObj.color : undefined;
	};

	const bgColorValue = getColorValue( backgroundColor, customBackgroundColor );
	const txtColorValue = getColorValue( textColor, customTextColor );

	// Handle color changes
	const onChangeBackgroundColor = ( color ) => {
		const colorObj = colors.find( ( c ) => c.color === color );
		if ( colorObj ) {
			setAttributes( {
				backgroundColor: colorObj.slug,
				customBackgroundColor: undefined,
			} );
		} else {
			setAttributes( {
				backgroundColor: undefined,
				customBackgroundColor: color,
			} );
		}
	};

	const onChangeTextColor = ( color ) => {
		const colorObj = colors.find( ( c ) => c.color === color );
		if ( colorObj ) {
			setAttributes( {
				textColor: colorObj.slug,
				customTextColor: undefined,
			} );
		} else {
			setAttributes( {
				textColor: undefined,
				customTextColor: color,
			} );
		}
	};

	// Sample expertise items for preview
	const sampleExpertise = [
		__( 'Biochemistry', 'theme' ),
		__( 'Cell Biology', 'theme' ),
		__( 'Plant Biology', 'theme' ),
		__( 'Genetics', 'theme' ),
	];

	const blockProps = useBlockProps( {
		className: 'wp-block-theme-user-expertise',
	} );

	// Build pill styles
	const pillStyle = {
		backgroundColor: bgColorValue,
		color: txtColorValue,
	};

	// Build class names for pills
	const pillClasses = [
		'wp-block-theme-user-expertise__pill',
		backgroundColor && ! customBackgroundColor
			? `has-${ backgroundColor }-background-color`
			: '',
		textColor && ! customTextColor
			? `has-${ textColor }-color`
			: '',
		( backgroundColor || customBackgroundColor ) ? 'has-background' : '',
		( textColor || customTextColor ) ? 'has-text-color' : '',
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Color Settings', 'theme' ) }
					initialOpen={ true }
				>
					<BaseControl
						label={ __( 'Background Color', 'theme' ) }
						id="expertise-bg-color"
					>
						<ColorPalette
							colors={ colors }
							value={ bgColorValue }
							onChange={ onChangeBackgroundColor }
							clearable={ true }
						/>
					</BaseControl>
					<BaseControl
						label={ __( 'Text Color', 'theme' ) }
						id="expertise-text-color"
					>
						<ColorPalette
							colors={ colors }
							value={ txtColorValue }
							onChange={ onChangeTextColor }
							clearable={ true }
						/>
					</BaseControl>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<div className="wp-block-theme-user-expertise__list">
					{ sampleExpertise.map( ( item, index ) => (
						<span
							key={ index }
							className={ pillClasses }
							style={ pillStyle }
						>
							{ item }
						</span>
					) ) }
				</div>
				<p className="wp-block-theme-user-expertise__notice">
					{ __(
						'Expertise areas will be loaded dynamically from the user profile.',
						'theme'
					) }
				</p>
			</div>
		</>
	);
}