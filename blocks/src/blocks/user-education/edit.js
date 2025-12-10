/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	SelectControl,
	FontSizePicker,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';

/**
 * Editor component for the User Education block.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to set attributes.
 * @return {JSX.Element} Block editor component.
 */
export default function Edit( { attributes, setAttributes } ) {
	const {
		headingText,
		headingLevel,
		headingFontSize,
		headingFontFamily,
	} = attributes;

	const blockProps = useBlockProps( {
		className: 'wp-block-caes-hub-user-education',
	} );

	// Get theme font settings
	const { fontSizes, fontFamilies } = useSelect( ( select ) => {
		const settings = select( 'core/block-editor' ).getSettings();
		return {
			fontSizes: settings.fontSizes || [],
			fontFamilies: settings.__experimentalFontFamilies?.theme || [],
		};
	}, [] );

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
		'wp-block-caes-hub-user-education__heading',
		headingFontFamily ? `has-${ headingFontFamily }-font-family` : '',
	]
		.filter( Boolean )
		.join( ' ' );

	// Render heading element
	const HeadingTag = headingLevel || 'h2';

	// Sample education items for preview
	const sampleDegrees = [
		{
			degree_name: __( 'Doctor of Philosophy', 'theme' ),
			field_of_study: __( 'Biology/Biological Sciences, General', 'theme' ),
			institution: __( 'Utah State University', 'theme' ),
			state: 'UT',
			country: __( 'United States', 'theme' ),
			year: '1992',
		},
		{
			degree_name: __( 'Master of Science', 'theme' ),
			field_of_study: __( 'Plant Pathology', 'theme' ),
			institution: __( 'University of Georgia', 'theme' ),
			state: 'GA',
			country: __( 'United States', 'theme' ),
			year: '1988',
		},
	];

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
			</InspectorControls>

			<div { ...blockProps }>
				{ headingText && (
					<HeadingTag className={ headingClasses } style={ headingStyle }>
						{ headingText }
					</HeadingTag>
				) }
				<div className="wp-block-caes-hub-user-education__list">
					{ sampleDegrees.map( ( degree, index ) => (
						<p key={ index } className="wp-block-caes-hub-user-education__degree">
							<strong>{ degree.degree_name }, { degree.field_of_study }</strong>
							<br />
							{ degree.institution }, { degree.state }, { degree.country } ({ degree.year })
						</p>
					) ) }
				</div>
				<p className="wp-block-caes-hub-user-education__notice">
					{ __(
						'Degrees will be loaded dynamically from the user profile.',
						'theme'
					) }
				</p>
			</div>
		</>
	);
}
