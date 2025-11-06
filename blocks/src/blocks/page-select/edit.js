import { useBlockProps } from '@wordpress/block-editor';
import './editor.scss';

export default function Edit() {
    const blockProps = useBlockProps();

    return (
        <div {...blockProps}>
            <label htmlFor="page-select-preview">
                Jump to page:
            </label>
            <div className="select-wrapper">
                <select id="page-select-preview" disabled>
                    <option>Page 1</option>
                    <option>Page 2</option>
                    <option>Page 3</option>
                    <option>Page 4</option>
                </select>
            </div>
        </div>
    );
}