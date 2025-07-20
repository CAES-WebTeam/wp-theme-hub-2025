<?php
/** * Renders Relevanssi search results HTML using block syntax. 
 */
if (! function_exists('caes_hub_render_relevanssi_search_results')) {
    function caes_hub_render_relevanssi_search_results($search_query, $orderby, $order, $post_type, $taxonomy_slug, $topic_terms, $paged = 1, $allowed_post_types_from_block = array()) // Add this new parameter
    {
        // error_log('RENDER: caes_hub_render_relevanssi_search_results function called.');
        // error_log('RENDER: Incoming Params: s=' . $search_query . ', orderby=' . $orderby . ', order=' . $order . ', post_type=' . $post_type . ', taxonomy_slug=' . $taxonomy_slug . ', topic_terms=' . print_r($topic_terms, true) . ', paged=' . $paged);
        // error_log('RENDER: allowed_post_types_from_block: ' . print_r($allowed_post_types_from_block, true)); // DEBUG

        $args = array(
            's'              => $search_query,
            'posts_per_page' => 10,
            'paged'          => $paged,
            'post_status'    => 'publish'
        );

        // Handle post_type: if empty, search all allowed post types instead of defaulting to 'post' 
        if (! empty($post_type)) {
            $args['post_type'] = $post_type;
        } else {
            // When no specific post type is selected, search all configured post types 
            // Use the allowed_post_types passed from the block's attributes
            $all_allowed_post_types_for_query = empty($allowed_post_types_from_block) ? array('post', 'page') : $allowed_post_types_from_block; // Fallback if block doesn't provide
            $filtered_post_types = array_filter($all_allowed_post_types_for_query, function ($type) {
                return $type !== 'shorthand_story';
            });
            $args['post_type'] = $filtered_post_types;
        }

        if ($orderby === 'post_date') {
            $args['orderby'] = 'date';
            $args['order']   = $order;
        } else {
            $args['orderby'] = 'relevance';
        }

        if (! empty($topic_terms) && $topic_terms[0] !== '') {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => $taxonomy_slug,
                    'field'    => 'slug',
                    'terms'    => $topic_terms,
                    'operator' => 'IN',
                ),
            );
        }

        // error_log('RENDER: Final WP_Query Args: ' . print_r($args, true));

        if (function_exists('relevanssi_do_query')) {
            // error_log('RENDER: Relevanssi is active. Preparing WP_Query for relevanssi_do_query.');
            $query = new WP_Query($args);
            relevanssi_do_query($query);
        } else {
            // error_log('RENDER: Relevanssi not active. Using standard WP_Query.');
            $query = new WP_Query($args);
        }

        // Store the global $wp_query to restore later 
        global $wp_query;
        $original_query = $wp_query;

        // Temporarily replace the global $wp_query with our custom query 
        $wp_query = $query;

        ob_start();

        // Define the block markup template for search results
        $search_result_block_template = '
<!-- wp:group {"metadata":{"name":"caes-hub-post-list-grid-item"},"className":"caes-hub-post-list-grid-item caes-hub-post-list-grid-horizontal","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group caes-hub-post-list-grid-item caes-hub-post-list-grid-horizontal"><!-- wp:post-featured-image {"aspectRatio":"3/2","metadata":{"name":"caes-hub-post-list-img-container"},"className":"caes-hub-post-list-img-container"} /-->
<!-- wp:group {"metadata":{"name":"caes-hub-post-list-grid-info"},"className":"caes-hub-post-list-grid-info","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|60","right":"var:preset|spacing|60"}}},"backgroundColor":"base","layout":{"type":"flex","orientation":"vertical","justifyContent":"left","verticalAlignment":"space-between"}} -->
<div class="wp-block-group caes-hub-post-list-grid-info has-base-background-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--60)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"},"typography":{"fontSize":"1.1rem","lineHeight":"1"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group" style="font-size:1.1rem;line-height:1"><!-- wp:caes-hub/primary-topic {"showCategoryIcon":true,"enableLinks":false,"name":"caes-hub/primary-topic","mode":"preview","className":"is-style-caes-hub-oswald-uppercase","style":{"border":{"right":{"color":"var:preset|color|contrast","width":"1px"},"top":[],"bottom":[],"left":[]},"spacing":{"padding":{"right":"var:preset|spacing|30"}}}} /-->
<!-- wp:post-date {"format":"M j, Y","style":{"typography":{"fontStyle":"light","fontWeight":"300","textTransform":"uppercase"}},"fontFamily":"oswald"} /--></div>
<!-- /wp:group -->
<!-- wp:post-title {"level":3,"isLink":true,"className":"caes-hub-post-list-grid-title","style":{"elements":{"link":{"color":{"text":"var:preset|color|contrast"}}}},"textColor":"contrast","fontSize":"large"} /-->
<!-- wp:post-excerpt {"excerptLength":35} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->';
?>
        <div class="relevanssi-search-results" data-results-count="<?php echo esc_attr($query->found_posts); ?>">
            <?php
            if (have_posts()) {
                $post_count = 0;
                
                // Create query loop structure like post-template
                echo '<div class="wp-block-query caes-hub-post-list-grid">';
                echo '<ul class="wp-block-post-template" style="gap: var(--wp--preset--spacing--50);padding: 0;display: flex;flex-direction: column;">';
                
                // Parse the block template once
                $parsed_blocks = parse_blocks($search_result_block_template);
                
                while (have_posts()) {
                    the_post();
                    $post_count++;

                    // Each post wrapped in <li> like post-template does
                    echo '<li class="wp-block-post post-' . get_the_ID() . ' ' . implode(' ', get_post_class()) . '">';
                    
                    // Render the blocks for each post
                    foreach ($parsed_blocks as $block) {
                        echo render_block($block);
                    }
                    
                    echo '</li>';
                }
                
                echo '</ul>';
                echo '</div>';
                // error_log('RENDER: Results found: ' . $post_count . ' posts.');
                the_posts_pagination(
                    array(
                        'prev_text'          => __('Previous', 'caes-hub'),
                        'next_text'          => __('Next', 'caes-hub'),
                        'screen_reader_text' => __('Posts navigation', 'caes-hub'),
                    )
                );
            } else {
                // error_log('RENDER: No results found by WP_Query/Relevanssi.');
                ?>
                <p><?php esc_html_e('No results found.', 'caes-hub'); ?></p>
            <?php
            }

            // Restore the original global $wp_query 
            $wp_query = $original_query;
            wp_reset_postdata();
            // error_log('RENDER: wp_reset_postdata() called and global $wp_query restored.');
            ?>
        </div>
<?php
        return ob_get_clean();
    }
}

/** * Register AJAX handler for Relevanssi Search. 
 */
function caes_hub_register_relevanssi_ajax_action()
{
    add_action('wp_ajax_caes_hub_search_results', 'caes_hub_handle_relevanssi_ajax_search');
    add_action('wp_ajax_nopriv_caes_hub_search_results', 'caes_hub_handle_relevanssi_ajax_search');
}
add_action('init', 'caes_hub_register_relevanssi_ajax_action');

/** * Handles Relevanssi AJAX search requests. 
 */
function caes_hub_handle_relevanssi_ajax_search()
{
    // error_log('AJAX: caes_hub_handle_relevanssi_ajax_search function called.');

    if (! defined('DOING_AJAX') || ! DOING_AJAX) {
        error_log('AJAX: Not an AJAX request. Exiting.');
        wp_die('Not an AJAX request.', 403);
    }

    // error_log('AJAX: $_POST Data: ' . print_r($_POST, true));

    $taxonomy_slug = 'category';
    if (isset($_POST['taxonomySlug'])) {
        $taxonomy_slug = sanitize_text_field(wp_unslash($_POST['taxonomySlug']));
    }
    // error_log('AJAX: Determined taxonomy_slug: ' . $taxonomy_slug);

    $ajax_s           = isset($_POST['s']) ? sanitize_text_field(wp_unslash($_POST['s'])) : '';
    $ajax_orderby     = isset($_POST['orderby']) ? sanitize_text_field(wp_unslash($_POST['orderby'])) : '';
    $ajax_order       = isset($_POST['order']) ? sanitize_text_field(wp_unslash($_POST['order'])) : '';
    $ajax_post_type   = isset($_POST['post_type']) ? sanitize_text_field(wp_unslash($_POST['post_type'])) : '';
    $ajax_topic_terms = isset($_POST[$taxonomy_slug]) ? array_map('sanitize_text_field', wp_unslash($_POST[$taxonomy_slug])) : array();
    $ajax_paged       = isset($_POST['paged']) ? intval(wp_unslash($_POST['paged'])) : 1;

    // Get allowed_post_types from the AJAX request if passed, or default.
    // You'll need to pass this from view.js in the AJAX request.
    $ajax_allowed_post_types = array();
    if (isset($_POST['allowedPostTypes'])) {
        $decoded_post_types = json_decode(wp_unslash($_POST['allowedPostTypes']), true); // Decode JSON string to an array
        if (is_array($decoded_post_types)) {
            $ajax_allowed_post_types = array_map('sanitize_text_field', $decoded_post_types); // Sanitize each element of the array
        }
    }

    // error_log('AJAX: Sanitized Query Params for render function:');
    // error_log('  s: ' . $ajax_s);
    // error_log('  orderby: ' . $ajax_orderby);
    // error_log('  order: ' . $ajax_order);
    // error_log('  post_type: ' . $ajax_post_type);
    // error_log('  topic_terms: ' . print_r($ajax_topic_terms, true));
    // error_log('  paged: ' . $ajax_paged);
    // error_log('  allowedPostTypes: ' . print_r($ajax_allowed_post_types, true)); // DEBUG

    // Pass the allowed post types to the render function for AJAX requests
    echo caes_hub_render_relevanssi_search_results($ajax_s, $ajax_orderby, $ajax_order, $ajax_post_type, $taxonomy_slug, $ajax_topic_terms, $ajax_paged, $ajax_allowed_post_types);

    // error_log('AJAX: wp_die() called.');
    wp_die();
}

/** * Enqueue script with AJAX URL. 
 */
function caes_hub_enqueue_ajax_url()
{
    // Do nothing - we'll add the AJAX URL directly in render.php 
}
add_action('wp_enqueue_scripts', 'caes_hub_enqueue_ajax_url');