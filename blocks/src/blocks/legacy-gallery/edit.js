import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

export default function Edit({ attributes, setAttributes, clientId }) {
    const blockProps = useBlockProps({
        className: 'legacy-gallery-block editor-preview'
    });

    // Placeholder data for editor preview using theme stock image
    const stockImageUrl = `${window.location.origin}/wp-content/themes/wp-theme-hub-2025/assets/images/cat-stock.jpg`;
    
    const placeholderGallery = [
        {
            id: 1,
            url: stockImageUrl,
            thumbnail: stockImageUrl,
            caption: 'This is a sample caption for the first gallery image. The actual content will come from the ACF legacy_gallery field.'
        },
        {
            id: 2,
            url: stockImageUrl,
            thumbnail: stockImageUrl,
            caption: 'Another sample caption showing how longer text will be displayed in the gallery.'
        },
        {
            id: 3,
            url: stockImageUrl,
            thumbnail: stockImageUrl,
            caption: 'Third image caption example.'
        }
    ];

    return (
        <div {...blockProps}>
            {/* Gallery main display area */}
            <div className="gallery-main">
                <figure className="gallery-figure">
                    <img 
                        className="gallery-main-image"
                        src={placeholderGallery[0].url}
                        alt={__('Main gallery image preview', 'caes-hub')}
                    />
                    <figcaption className="gallery-caption">
                        {placeholderGallery[0].caption}
                    </figcaption>
                </figure>
            </div>

            {/* Gallery filmstrip navigation */}
            <nav className="gallery-filmstrip" aria-label="Gallery navigation preview">
                <div className="filmstrip-container">
                    <ul className="filmstrip-list">
                        {placeholderGallery.map((item, index) => (
                            <li key={item.id} className="filmstrip-item">
                                <button 
                                    className={`filmstrip-thumb ${index === 0 ? 'active' : ''}`}
                                    disabled
                                    style={{ pointerEvents: 'none' }}
                                >
                                    <img 
                                        src={item.thumbnail}
                                        alt=""
                                        className="thumb-image"
                                    />
                                </button>
                            </li>
                        ))}
                    </ul>
                </div>
            </nav>
        </div>
    );
}