import { __ } from '@wordpress/i18n';

import './editor.scss';

import { SelectControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { Fragment } from '@wordpress/element';

const Edit = ({ attributes, setAttributes }) => {

    const categories = useSelect((select) => {
        return select('core').getEntityRecords('taxonomy', 'category', { per_page: -1 });
    }, []);

    const handleCategoryChange = (value) => {
        setAttributes({ selectedCategory: value });
    };

    return (
        <Fragment>
            <SelectControl
                label="Filter by Category"
                value={attributes.selectedCategory}
                options={
                    categories
                        ? categories.map((cat) => ({ label: cat.name, value: cat.id }))
                        : []
                }
                onChange={handleCategoryChange} // Set the selected category
            />
        </Fragment>
    );
}
export default Edit;