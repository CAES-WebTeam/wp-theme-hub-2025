import { __ } from '@wordpress/i18n';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

export default function Edit({ context }) {
    const flyoutId = context['fieldReport/flyoutId'];
    const parentNavItem = context['fieldReport/parentNavItem'] || 'submenu';

    const blockProps = useBlockProps({
        className: 'submenu show', // Show in editor for visibility
        id: flyoutId,
        'aria-label': sprintf(__('%s submenu', 'caes-hub'), parentNavItem)
    });

    return (
        <div {...blockProps}>
            <InnerBlocks
                allowedBlocks={[
                    'core/heading',
                    'core/paragraph',
                    'core/list',
                    'core/group',
                    'core/columns',
                    'core/column',
                    'core/image',
                    'core/separator',
                    'core/button',
                    'core/buttons',
                    'core/spacer',
                ]}
                template={[
                    ['core/heading', { level: 3, content: __('Submenu Section', 'caes-hub') }],
                    ['core/paragraph', { content: __('Add your submenu content here.', 'caes-hub') }]
                ]}
                renderAppender={InnerBlocks.ButtonBlockAppender}
            />
        </div>
    );
}
