import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

export default function Edit() {
    return (
        <ul {...useBlockProps()}>
            <li><a href="#">Personal website</a></li>
        </ul>
    );
}
