
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import './editor.scss';

export default function Edit({ attributes, setAttributes }) {
    return (
        <>
            <p {...useBlockProps()}>
                Department
            </p>
        </>
    );
}
