<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Get the current post ID
$post_id = get_the_ID();
?>
<a href="<?php echo esc_url( admin_url('admin-post.php?action=generate_pdf&post_id=' . $post_id) ); ?>" class="button generate-pdf">
	Generate PDF
</a>
