<?php
// Get tags and series terms
$tags = wp_get_post_tags( $pub, [ 'fields' => 'names' ] );
$series = wp_get_post_terms( $pub, 'series', [ 'fields' => 'names' ] );
?>

<div class="pub" <?php if ( ! empty( $tags ) ) { echo 'data-tags="' . esc_attr( implode( ',', $tags ) ) . '" '; }  if ( ! empty( $series ) ) {
	 echo ' data-series="' . esc_attr( implode( ',', $series ) ) . '" '; } ?>>
	<h3><?php echo get_the_title($pub); ?></h3>
	<a href="<?php echo get_permalink($pub); ?>" class="pub-link"></a>
</div>

