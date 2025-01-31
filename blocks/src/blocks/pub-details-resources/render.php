<?php

// Get the current post ID
$post_id = get_the_ID();

// Attributes for wrapper
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

// Get the resources ACF fields
$resources = get_field('resources', $post_id);


// Check if resources is set
if ( $resources ) {
    echo '<div ' . $attrs . '>';
    echo '<h2>Resources</h2>';

	// Loop through resources
    foreach ( $resources as $item ) {
        echo '<div class="resource">';

		$document_type = $item['document_type'] ?? '';

		if ( $document_type === 'file' && !empty( $item['file'] ) ) {
			// File
			$file = $item['file'];
			$file_url = is_array($file) ? $file['url'] : $file;
			$file_name = is_array($file) ? ($file['title'] ?? basename($file_url)) : basename($file_url);
			echo '<a href="' . esc_url( $file_url ) . '" class="resource-file" download>' . esc_html( $file_name ) . '</a>';
		} elseif ( $document_type === 'image' && !empty( $item['image'] ) ) {
			// Image
			$image = $item['image']; // Assuming this contains the image array
			$image_url = is_array($image) ? $image['url'] : $image;
			echo '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $image['alt'] ?? 'Resource Image' ) . '" class="resource-image">';
		} elseif ( $document_type === 'link' && !empty( $item['link'] ) ) {
			// Link
			$link = $item['link'];
			$link_url = is_array($link) ? $link['url'] : $link;
			$link_text = is_array($link) ? ($link['title'] ?? $link_url) : $link_url;
			echo '<a href="' . esc_url( $link_url ) . '" class="resource-link" target="outside" rel="noopener noreferrer">' . esc_html( $link_text ) . '</a>';
		}

        echo '</div>';
    }
    echo '</div>';
}
?>
