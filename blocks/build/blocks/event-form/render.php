<?php
// Call acf_form_head to load necessary ACF scripts and styles
acf_form_head();


// Check if the user is logged in and has the correct roles
if ( is_user_logged_in() ) {
	$user = wp_get_current_user();
	$allowed_roles = array('administrator', 'caes-staff', 'extension-staff');
	$current_page_id = get_the_ID();

	if ( array_intersect($allowed_roles, $user->roles ) ) {
		// Display existing events submitted by the user
		$user_events = new WP_Query(array(
			'post_type'      => 'events',
			'post_status'    => array('draft', 'pending', 'publish'),
			'author'         => $user->ID,
			'posts_per_page' => -1
		));

		if ( $user_events->have_posts() ) {
			echo '<h2>Your Submitted Events</h2>';
			echo '<ul>';
			while ( $user_events->have_posts() ) {
				$user_events->the_post();
				echo '<li>';
				echo '<a href="' . esc_url(add_query_arg('edit_event', get_the_ID(), get_permalink($current_page_id))) . '">';
				echo get_the_title();
				echo '</a>';
				echo ' - <em>' . ucfirst(get_post_status()) . '</em>';
				echo '</li>';
			}
			echo '</ul>';
			wp_reset_postdata();
		} else {
			echo '<p>You haven\'t submitted any events yet.</p>';
		}

		// Check if the user wants to edit an event
		if ( isset($_GET['edit_event']) && is_numeric($_GET['edit_event']) ) {
			$event_id = intval($_GET['edit_event']);
			$post = get_post($event_id);

			// Ensure the post exists and the current user is the author
			if ( $post && $post->post_type === 'events' && $post->post_author == $user->ID ) {
				echo '<h2>Edit Event: ' . esc_html(get_the_title($event_id)) . '</h2>';
				acf_form(array(
					'post_id'       => $event_id,
					'post_title'    => false,
					'post_content'  => false,
					'submit_value'  => 'Update Event',
					'updated_message' => 'Your event has been updated successfully.',
				));
			} else {
				echo '<p>You are not allowed to edit this event.</p>';
			}
		} else {
			// Render the ACF form to submit a new event
			echo '<h2>Submit a New Event</h2>';
			acf_form(array(
				'post_id'       => 'new_post',
				'post_title'    => false,
				'post_content'  => false,
				'new_post'      => array(
					'post_type'     => 'events',
					'post_status'   => 'draft'
				),
				'submit_value'  => 'Submit Event',
				'updated_message' => 'Your event has been submitted and is awaiting admin approval.',
			));
		}
	} else {
		echo '<p>You do not have permission to submit or view events.</p>';
	}

} else {
	// Display a login form for users who are not logged in
	echo '<div style="text-align:center;"><p>You need to be logged in to submit or view your events. Please log in below:</p>';

	// Get the current page URL to redirect back to after login
	$redirect_url = esc_url( home_url( add_query_arg( null, null ) ) );

	// Display login form and set redirect after login
	wp_login_form(array(
		'redirect' => $redirect_url
	));

	// Add lost password link
	echo '<p><a href="' . wp_lostpassword_url() . '">Lost your password?</a></p></div>';
}


// Load TinyMCE styles and scripts
/*
function enqueue_tinymce_for_acf() {
	if ( function_exists('acf_form_head') ) {
		wp_enqueue_editor();
		wp_enqueue_script('wp-tinymce');
		wp_enqueue_style('editor-styles', includes_url('css/editor.min.css'), array(), null);
	}
}
add_action('wp_enqueue_scripts', 'enqueue_tinymce_for_acf');

echo"
<script>
(function($) {
	$(document).ready(function() {
		if (typeof tinymce !== 'undefined' && $('.acf-editor-wrap').length) {
			tinymce.init({
				selector: '.acf-editor-wrap textarea',
				menubar: false,
				toolbar: 'formatselect fontselect fontsizeselect bold italic underline strikethrough alignleft aligncenter alignright alignjustify bullist numlist outdent indent blockquote link unlink image undo redo forecolor backcolor removeformat hr subscript superscript charmap fullscreen ltr rtl',
				plugins: 'lists link image paste fullscreen colorpicker textcolor hr charmap',
			});
		}
	});
})(jQuery);
</script>";
*/
