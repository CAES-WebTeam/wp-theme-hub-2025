<?php 
// Custom Header Function to Prevent Site Title from Displaying
function my_custom_header() {
    get_template_part( 'header', 'shorthand_story' );
}
?>

<?php my_custom_header(); ?>

<?php
// Check to see if there is a password set against the post
if ( post_password_required( $post->ID ) ) {
	get_shorthand_password_form();
} else {
	while ( have_posts() ) :
		the_post();
		$meta = get_post_meta( $post->ID );
		?>
		<?php echo get_shorthandinfo( $meta, 'story_body' ); ?>
	<div id="extraHTML">
		<?php echo get_shorthandinfo( $meta, 'extra_html' ); ?>
		</div>
		<style type="text/css">
		<?php echo get_shorthandoption( 'sh_css' ); ?>
		</style>
		<?php
	endwhile;
}
?>

<?php 
// Custom Footer Function to Prevent Footer from Displaying
function my_custom_footer() {
    get_template_part( 'footer', 'shorthand_story' );
}
?>

<?php my_custom_footer(); ?>