import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls, PanelColorSettings } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';

import './editor.scss';

export default function Edit({ attributes, setAttributes }) {
	const { direction, showPublicationNumber, arrowColor } = attributes;

	const blockProps = useBlockProps({
		className: `direction-${direction}`,
	});

	const linkText = showPublicationNumber
		? 'SB 123: "Example Publication Title"'
		: '"Example Publication Title"';

	const arrowStyle = {
		color: arrowColor ? `var(--wp--preset--color--${arrowColor})` : undefined,
	};

	const renderPreview = () => {
		const label = direction === 'previous' ? 'Previous' : 'Next';
		const arrow = direction === 'previous' ? '←' : '→';

		if (direction === 'previous') {
			return (
				<>
					<span
						className="pub-series-nav__arrow"
						style={arrowStyle}
						aria-hidden="true"
					>
						{arrow}
					</span>
					<div className="pub-series-nav__content">
						<span className="pub-series-nav__label">{label}</span>
						<a
							href="#"
							className="pub-series-nav__link"
							onClick={(e) => e.preventDefault()}
						>
							{linkText}
						</a>
					</div>
				</>
			);
		}

		return (
			<>
				<div className="pub-series-nav__content">
					<span className="pub-series-nav__label">{label}</span>
					<a
						href="#"
						className="pub-series-nav__link"
						onClick={(e) => e.preventDefault()}
					>
						{linkText}
					</a>
				</div>
				<span
					className="pub-series-nav__arrow"
					style={arrowStyle}
					aria-hidden="true"
				>
					{arrow}
				</span>
			</>
		);
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Series Nav Settings', 'caes-hub')}>
					<SelectControl
						label={__('Direction', 'caes-hub')}
						value={direction}
						options={[
							{ label: __('Previous', 'caes-hub'), value: 'previous' },
							{ label: __('Next', 'caes-hub'), value: 'next' },
						]}
						onChange={(val) => setAttributes({ direction: val })}
					/>
					<ToggleControl
						label={__('Show publication number', 'caes-hub')}
						checked={showPublicationNumber}
						onChange={(val) => setAttributes({ showPublicationNumber: val })}
						help={__('Display the publication number alongside the title.', 'caes-hub')}
					/>
				</PanelBody>
				<PanelColorSettings
					title={__('Arrow Color', 'caes-hub')}
					colorSettings={[
						{
							value: arrowColor
								? `var(--wp--preset--color--${arrowColor})`
								: undefined,
							onChange: (newColor) => {
								// Extract color slug from CSS variable
								if (newColor && newColor.includes('--wp--preset--color--')) {
									const slug = newColor.match(/--wp--preset--color--([^)]+)/)[1];
									setAttributes({ arrowColor: slug });
								} else {
									setAttributes({ arrowColor: newColor });
								}
							},
							label: __('Arrow', 'caes-hub'),
						},
					]}
				/>
			</InspectorControls>
			<div {...blockProps}>{renderPreview()}</div>
		</>
	);
}
