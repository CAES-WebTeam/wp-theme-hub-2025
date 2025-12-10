/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Editor component for the User About block.
 *
 * @return {JSX.Element} Block editor component.
 */
export default function Edit() {
	const blockProps = useBlockProps( {
		className: 'wp-block-caes-hub-user-about',
	} );

	return (
		<div { ...blockProps }>
			<div className="wp-block-caes-hub-user-about__content">
				<p>
					{ __(
						'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.',
						'theme'
					) }
				</p>
				<p>
					{ __(
						'Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident.',
						'theme'
					) }
				</p>
			</div>
			<p className="wp-block-caes-hub-user-about__notice">
				{ __(
					'About text will be loaded dynamically from the user profile.',
					'theme'
				) }
			</p>
		</div>
	);
}