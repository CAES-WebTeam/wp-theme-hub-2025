<?php

// Make regular posts show as /news/post-name/
add_filter('post_type_link', function($link, $post) {
    if ($post->post_type === 'post') {
        return home_url('/news/' . $post->post_name . '/');
    }
    return $link;
}, 10, 2);

// Add rewrite rule for /news/ posts
add_action('init', function() {
    add_rewrite_rule('^news/([^/]+)/?$', 'index.php?name=$matches[1]', 'top');
});

// Apply to REST API responses (used in block editor, feeds, etc.)
add_filter('rest_prepare_post', function ($response, $post, $request) {
    $external_url = get_post_meta($post->ID, 'external_story_url', true);

    if ($external_url && filter_var($external_url, FILTER_VALIDATE_URL)) {
        $response->data['link'] = esc_url($external_url);
    }

    return $response;
}, 10, 3);

add_filter('default_content', 'story_default_content', 10, 2);

// Insert default content into new posts
function story_default_content($content, $post)
{
    // Only work on posts (stories)
    if ($post->post_type !== 'post') {
        return $content;
    }

    // Featured image block (keeping the texture.jpg placeholder)
    $image_url = get_template_directory_uri() . '/assets/images/texture.jpg';

    $default_content = '<!-- wp:image {"sizeSlug":"full","linkDestination":"none","align":"wide"} -->
<figure class="wp-block-image alignwide size-full"><img src="' . esc_url($image_url) . '" alt=""/><figcaption class="wp-element-caption">Replace this image and caption. Don\'t forget to write alt text in the image block settings!</figcaption></figure>
<!-- /wp:image -->

<!-- wp:group {"metadata":{"categories":["content_patterns"],"patternName":"caes-hub/takeaways-1","name":"Takeaways"},"className":"caes-hub-takeaways","style":{"shadow":"var:preset|shadow|small","spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|60","right":"var:preset|spacing|60"},"blockGap":"var:preset|spacing|60"},"border":{"left":{"color":"var:preset|color|hedges","width":"5px"}}},"backgroundColor":"base","layout":{"type":"default"}} -->
<div class="wp-block-group caes-hub-takeaways has-base-background-color has-background" style="border-left-color:var(--wp--preset--color--hedges);border-left-width:5px;padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--60);box-shadow:var(--wp--preset--shadow--small)"><!-- wp:group {"metadata":{"name":"caes-hub-takeaways__header"},"className":"caes-hub-takeaways__header","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group caes-hub-takeaways__header"><!-- wp:heading {"style":{"typography":{"textTransform":"uppercase"}},"fontSize":"large","fontFamily":"oswald"} -->
<h2 class="wp-block-heading has-oswald-font-family has-large-font-size" style="text-transform:uppercase">Takeaways</h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:list {"className":"is-style-default"} -->
<ul class="wp-block-list is-style-default"><!-- wp:list-item {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|60"}}}} -->
<li style="margin-bottom:var(--wp--preset--spacing--60)"><strong>Takeaway:</strong> Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris ut turpis neque. Duis a nisi placerat, scelerisque sapien eu, sollicitudin enim. Maecenas egestas quam et est venenatis, ut congue sapien porttitor.</li>
<!-- /wp:list-item -->

<!-- wp:list-item {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|60"}}}} -->
<li style="margin-bottom:var(--wp--preset--spacing--60)"><strong>Takeaway:</strong> Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris ut turpis neque. Duis a nisi placerat, scelerisque sapien eu, sollicitudin enim. Maecenas egestas quam et est venenatis, ut congue sapien porttitor.</li>
<!-- /wp:list-item -->

<!-- wp:list-item {"style":{"spacing":{"margin":{"bottom":"0"}}}} -->
<li style="margin-bottom:0"><strong>Takeaway:</strong> Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris ut turpis neque. Duis a nisi placerat, scelerisque sapien eu, sollicitudin enim. Maecenas egestas quam et est venenatis, ut congue sapien porttitor.</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:group -->

<!-- wp:paragraph -->
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nam faucibus nibh ex, eu cursus orci faucibus quis. Nunc ut feugiat dui. Praesent congue sit amet felis in blandit. In tristique odio ut nisi auctor consectetur. Nunc nunc sapien, luctus et orci a, imperdiet aliquam nisi. Integer efficitur lacus at purus molestie, in auctor nunc fermentum. Nulla pharetra felis sed tincidunt pharetra.</p>
<!-- /wp:paragraph -->

<!-- wp:caes-hub/pub-details-authors {"displayVersion":"names-and-titles","type":"sources","grid":false,"className":"is-style-caes-hub-compact","style":{"typography":{"lineHeight":"1.3"}}} /-->

<!-- wp:paragraph -->
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nam faucibus nibh ex, eu cursus orci faucibus quis. Nunc ut feugiat dui. Praesent congue sit amet felis in blandit. In tristique odio ut nisi auctor consectetur. Nunc nunc sapien, luctus et orci a, imperdiet aliquam nisi. Integer efficitur lacus at purus molestie, in auctor nunc fermentum. Nulla pharetra felis sed tincidunt pharetra.</p>
<!-- /wp:paragraph -->

<!-- wp:group {"metadata":{"name":"Related Content","categories":["story_feeds","pub_feeds"],"patternName":"caes-hub/related-content"},"className":"is-style-caes-hub-align-left-40","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|50","right":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|30","margin":{"bottom":"0"}},"border":{"left":{"color":"var:preset|color|hedges","width":"5px"},"top":[],"right":[],"bottom":[]}},"layout":{"type":"default"}} -->
<div class="wp-block-group is-style-caes-hub-align-left-40" style="border-left-color:var(--wp--preset--color--hedges);border-left-width:5px;margin-bottom:0;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--50)"><!-- wp:heading {"style":{"typography":{"textTransform":"uppercase"}},"fontSize":"regular","fontFamily":"oswald"} -->
<h2 class="wp-block-heading has-oswald-font-family has-regular-font-size" style="text-transform:uppercase">Related Content</h2>
<!-- /wp:heading -->

<!-- wp:caes-hub/hand-picked-post {"postType":["post","publications","shorthand_story"],"style":{"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<!-- wp:post-title {"level":3,"isLink":true,"style":{"elements":{"link":{"color":{"text":"var:preset|color|contrast"}}},"typography":{"textDecoration":"underline"},"spacing":{"margin":{"top":"var:preset|spacing|30","bottom":"0"}}},"fontSize":"regular"} /-->
<!-- /wp:caes-hub/hand-picked-post --></div>
<!-- /wp:group -->

<!-- wp:paragraph -->
<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nam faucibus nibh ex, eu cursus orci faucibus quis. Nunc ut feugiat dui. Praesent congue sit amet felis in blandit. In tristique odio ut nisi auctor consectetur. Nunc nunc sapien, luctus et orci a, imperdiet aliquam nisi. Integer efficitur lacus at purus molestie, in auctor nunc fermentum. Nulla pharetra felis sed tincidunt pharetra.</p>
<!-- /wp:paragraph -->';

    return $default_content . "\n\n" . $content;
}

add_filter( 'render_block', function( $block_content, $block ) {
	if ( $block['blockName'] !== 'core/post-date' ) {
		return $block_content;
	}

	$post_id = get_the_ID();
	if ( ! $post_id || get_post_type( $post_id ) !== 'post' ) {
		return $block_content;
	}

	// Use ACF release_date_new if it exists
	$acf_date = get_field( 'release_date_new', $post_id );
	$timestamp = $acf_date ? strtotime( $acf_date ) : get_post_time( 'U', false, $post_id );

	if ( ! $timestamp ) {
		return $block_content;
	}

	// Format the date in APA style
	$apa_date = format_date_apa_style( $timestamp );

	// Replace only the content inside the <time> tag
	$block_content = preg_replace_callback(
		'|<time([^>]*)>(.*?)</time>|i',
		function ( $matches ) use ( $apa_date ) {
			return '<time' . $matches[1] . '>' . esc_html( $apa_date ) . '</time>';
		},
		$block_content
	);

	return $block_content;
}, 10, 2 );

function update_flat_expert_ids_meta($post_id)
{
    // Check if it's an autosave to prevent unnecessary processing
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Ensure this only runs for 'post' post type
    if (get_post_type($post_id) !== 'post') {
        return;
    }

    // Get the ACF repeater field called 'experts'
    $experts = get_field('experts', $post_id);

    // If no experts are selected or the field is empty, delete the meta key
    if (!$experts || !is_array($experts)) {
        delete_post_meta($post_id, 'all_expert_ids');
        return;
    }

    $expert_ids = [];

    foreach ($experts as $expert_row) {
        // Ensure the 'user' sub-field exists and is a valid user ID
        if (!empty($expert_row['user']) && is_numeric($expert_row['user'])) {
            $expert_ids[] = (int) $expert_row['user'];
        } else {
            // Log an error if an expert entry is malformed
            error_log("⚠️ Invalid or missing 'user' field in expert entry for post ID: {$post_id}");
        }
    }

    // Store the array of expert IDs as a single meta value (serialized by WordPress)
    update_post_meta($post_id, 'all_expert_ids', $expert_ids);
}
add_action('acf/save_post', 'update_flat_expert_ids_meta', 20);