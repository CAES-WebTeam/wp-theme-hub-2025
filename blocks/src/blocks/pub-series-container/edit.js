import { __ } from '@wordpress/i18n';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

import './editor.scss';

export default function Edit() {
	const blockProps = useBlockProps();

	return (
		<div {...blockProps}>
			<div className="pub-series-container__label">
				{__('Series Container', 'caes-hub')}
				<span className="pub-series-container__label-hint">
					{__('Only visible when publication is in a series', 'caes-hub')}
				</span>
			</div>
			<InnerBlocks renderAppender={InnerBlocks.ButtonBlockAppender} />
		</div>
	);
}
