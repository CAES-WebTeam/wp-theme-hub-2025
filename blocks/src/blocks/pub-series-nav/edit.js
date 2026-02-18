import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';

import './editor.scss';

export default function Edit({ attributes, setAttributes }) {
	const { direction, showPublicationNumber } = attributes;

	const blockProps = useBlockProps();

	const linkText = showPublicationNumber
		? 'SB 123 — Example Publication Title'
		: 'Example Publication Title';

	const renderPreview = () => {
		if (direction === 'previous') {
			return (
				<>
					<span className="pub-series-nav__arrow">&larr;</span>
					<a
						href="#"
						className="pub-series-nav__link"
						onClick={(e) => e.preventDefault()}
					>
						{linkText}
					</a>
				</>
			);
		}

		return (
			<>
				<a
					href="#"
					className="pub-series-nav__link"
					onClick={(e) => e.preventDefault()}
				>
					{linkText}
				</a>
				<span className="pub-series-nav__arrow">&rarr;</span>
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
			</InspectorControls>
			<div {...blockProps}>{renderPreview()}</div>
		</>
	);
}
