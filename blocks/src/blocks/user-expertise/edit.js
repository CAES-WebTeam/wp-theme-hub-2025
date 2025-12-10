/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	ColorPalette,
	BaseControl,
	TextControl,
	SelectControl,
	FontSizePicker,
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
		headingText,
		headingLevel,
		headingFontSize,
		headingFontFamily,
	} = attributes;

	const { postId, postType } = context;

	// Get theme color palette and font settings
	const { colors, fontSizes, fontFamilies } = useSelect( ( select ) => {
		const settings = select( 'core/block-editor' ).getSettings();
		return {
			colors: settings.colors || [],
			fontSizes: settings.fontSizes || [],
			fontFamilies: settings.__experimentalFontFamilies?.theme || [],
		};
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
		className: 'wp-block-caes-hub-user-expertise',
	} );

	// Build pill styles
	const pillStyle = {
		backgroundColor: bgColorValue,
		color: txtColorValue,
	};

	// Build class names for pills
	const pillClasses = [
		'wp-block-caes-hub-user-expertise__pill',
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

	// Heading level options
	const headingLevelOptions = [
		{ label: __( 'Heading 1', 'theme' ), value: 'h1' },
		{ label: __( 'Heading 2', 'theme' ), value: 'h2' },
		{ label: __( 'Heading 3', 'theme' ), value: 'h3' },
		{ label: __( 'Heading 4', 'theme' ), value: 'h4' },
		{ label: __( 'Heading 5', 'theme' ), value: 'h5' },
		{ label: __( 'Heading 6', 'theme' ), value: 'h6' },
		{ label: __( 'Paragraph', 'theme' ), value: 'p' },
	];

	// Build font family options from theme settings
	const fontFamilyOptions = [
		{ label: __( 'Default', 'theme' ), value: '' },
		...fontFamilies.map( ( font ) => ( {
			label: font.name,
			value: font.slug,
		} ) ),
	];

	// Get font family CSS value
	const getFontFamilyStyle = ( slug ) => {
		if ( ! slug ) return undefined;
		const font = fontFamilies.find( ( f ) => f.slug === slug );
		return font ? font.fontFamily : undefined;
	};

	// Build heading styles
	const headingStyle = {
		fontSize: headingFontSize || undefined,
		fontFamily: getFontFamilyStyle( headingFontFamily ),
	};

	// Build heading class
	const headingClasses = [
		'wp-block-caes-hub-user-expertise__heading',
		headingFontFamily ? `has-${ headingFontFamily }-font-family` : '',
	]
		.filter( Boolean )
		.join( ' ' );

	// Render heading element
	const HeadingTag = headingLevel || 'h2';

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Heading Settings', 'theme' ) }
					initialOpen={ true }
				>
					<TextControl
						label={ __( 'Heading Text', 'theme' ) }
						value={ headingText }
						onChange={ ( value ) =>
							setAttributes( { headingText: value } )
						}
						placeholder={ __( 'Enter heading text…', 'theme' ) }
					/>
					<SelectControl
						label={ __( 'Heading Level', 'theme' ) }
						value={ headingLevel }
						options={ headingLevelOptions }
						onChange={ ( value ) =>
							setAttributes( { headingLevel: value } )
						}
					/>
					<FontSizePicker
						fontSizes={ fontSizes }
						value={ headingFontSize }
						onChange={ ( value ) =>
							setAttributes( { headingFontSize: value } )
						}
						fallbackFontSize={ 20 }
						withSlider
					/>
					{ fontFamilies.length > 0 && (
						<SelectControl
							label={ __( 'Font Family', 'theme' ) }
							value={ headingFontFamily }
							options={ fontFamilyOptions }
							onChange={ ( value ) =>
								setAttributes( { headingFontFamily: value } )
							}
						/>
					) }
				</PanelBody>
				<PanelBody
					title={ __( 'Pill Color Settings', 'theme' ) }
					initialOpen={ false }
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
				{ headingText && (
					<HeadingTag className={ headingClasses } style={ headingStyle }>
						{ headingText }
					</HeadingTag>
				) }
				<div className="wp-block-caes-hub-user-expertise__list">
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
			</div>
		</>
	);
}