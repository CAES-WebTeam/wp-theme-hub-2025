import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

import './editor.scss';

export default function Edit({ attributes, setAttributes }) {
	const { eventRange, series, events, numberOfPosts } = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Event Block Settings', 'caes-hub')}>
					<SelectControl
						label={__('Event Range', 'caes-hub')}
						value={eventRange}
						options={[
							{ label: 'All upcoming', value: '' },
							{ label: 'All', value: 'all' },
							{ label: 'Limited (by series)', value: 'limited' },
							{ label: 'Specific (pick events)', value: 'specific' },
							{ label: 'For You (location-based)', value: 'for_you' },
						]}
						onChange={(val) => setAttributes({ eventRange: val })}
					/>
					{eventRange !== 'specific' && (
						<TextControl
							label={__('Number of posts', 'caes-hub')}
							type="number"
							value={numberOfPosts}
							onChange={(val) => setAttributes({ numberOfPosts: parseInt(val, 10) || 0 })}
						/>
					)}
					{eventRange === 'limited' && (
						<TextControl
							label={__('Series term ID', 'caes-hub')}
							help={__('Enter the series taxonomy term ID. A proper taxonomy picker should replace this if the block is activated.', 'caes-hub')}
							type="number"
							value={series || ''}
							onChange={(val) => setAttributes({ series: parseInt(val, 10) || 0 })}
						/>
					)}
					{eventRange === 'specific' && (
						<TextControl
							label={__('Event post IDs (comma-separated)', 'caes-hub')}
							help={__('Enter event post IDs separated by commas. A proper post picker should replace this if the block is activated.', 'caes-hub')}
							value={Array.isArray(events) ? events.join(',') : ''}
							onChange={(val) => {
								const ids = val.split(',').map((s) => parseInt(s.trim(), 10)).filter((n) => !isNaN(n) && n > 0);
								setAttributes({ events: ids });
							}}
						/>
					)}
				</PanelBody>
			</InspectorControls>
			<div {...useBlockProps()}>
				<ServerSideRender
					block="caes-hub/event-block"
					attributes={attributes}
				/>
			</div>
		</>
	);
}
