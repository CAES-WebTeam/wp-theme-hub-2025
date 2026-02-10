import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';
import './editor.scss';
import Edit from './edit';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: Edit,
	save: ( { attributes } ) => {
		// Output wrapper for PHP to parse and extract content
		return (
			<div 
				className="motion-scroll-frame-content" 
				data-frame-index={ attributes.frameIndex }
			>
				<InnerBlocks.Content />
			</div>
		);
	},
} );
