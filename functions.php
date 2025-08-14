<?php
/*
 *  Author: UGA - CAES OIT, Frankel Agency
 *  URL: hub.caes.uga.edu
 *  Custom functions, support, custom post types and more.
 */

/*------------------------------------*\
	Load files
\*------------------------------------*/

require get_template_directory() . '/inc/theme-support.php';
require get_template_directory() . '/inc/post-types.php';
require get_template_directory() . '/inc/blocks.php';
require get_template_directory() . '/inc/acf.php';
require get_template_directory() . '/inc/caes-tools.php';
require get_template_directory() . '/inc/publications-support.php';
require get_template_directory() . '/inc/user-support.php';
require get_template_directory() . '/inc/news-support.php';
require get_template_directory() . '/block-variations/index.php';
require get_template_directory() . '/inc/custom-rewrites.php';
require get_template_directory() . '/inc/date-sync-tool.php';
require get_template_directory() . '/inc/rss-support.php';

// Publications PDF generation
require get_template_directory() . '/inc/publications-pdf/publications-pdf.php';
require get_template_directory() . '/inc/publications-pdf/pdf-queue.php';
require get_template_directory() . '/inc/publications-pdf/pdf-cron.php';
require get_template_directory() . '/inc/publications-pdf/pdf-admin.php';

// Events
require get_template_directory() . '/inc/events-support.php';
require get_template_directory() . '/inc/events/events-main.php';


// Temp include
require get_template_directory() . '/inc/release-date-migration.php';
require get_template_directory() . '/inc/release-date-clear.php';
require get_template_directory() . '/inc/detect-duplicates.php';
require get_template_directory() . '/inc/link-users.php';
require get_template_directory() . '/inc/pub-history-update.php';
require get_template_directory() . '/inc/story-association-meta-tools.php';
require get_template_directory() . '/inc/pub-main-import.php';
require get_template_directory() . '/inc/topic-term-fixer.php';

// Plugin overrides
require get_template_directory() . '/inc/plugin-overrides/relevanssi-search.php';

/**
 * ===================================================================
 * Part 1: The Original Function to Update a Single Post's Meta
 * ===================================================================
 * This function is called by the AJAX handler for each post.
 */
//** Update all_author_ids meta field when saving a post  */
add_action('acf/save_post', 'update_flat_author_ids_meta', 20);
function update_flat_author_ids_meta($post_id)
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!in_array(get_post_type($post_id), ['publications', 'post'])) return;

    // Get ACF repeater field called 'authors'
    $authors = get_field('authors', $post_id);

    if (!$authors || !is_array($authors)) {
        delete_post_meta($post_id, 'all_author_ids');
        return;
    }
    $author_ids = [];

    foreach ($authors as $author) {
        if (!empty($author['user']) && is_numeric($author['user'])) {
            $author_ids[] = (int) $author['user'];
        }
    }

    update_post_meta($post_id, 'all_author_ids', $author_ids);
}

/**
 * ===================================================================
 * Part 2: Create the Admin Page for the Tool
 * ===================================================================
 * This adds a new page under "Tools" -> "Update Author Meta".
 */
add_action('admin_menu', 'author_meta_updater_add_page');
function author_meta_updater_add_page()
{
    add_management_page(
        'Update Author Meta',          // Page Title
        'Update Author Meta',          // Menu Title
        'manage_options',              // Capability required
        'author-meta-updater',         // Menu Slug
        'author_meta_updater_page_html' // Function to display the page content
    );
}

/**
 * ===================================================================
 * Part 3: The HTML and JavaScript for the Admin Page
 * ===================================================================
 * This function renders the button, the progress log, and the
 * JavaScript needed to communicate with the server.
 */
function author_meta_updater_page_html()
{
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <p>This tool will re-process all 'Posts' and 'Publications' to update the 'all_author_ids' custom field.</p>
        <p>This is useful after changing the ACF field settings or importing old content. The process will run in batches to prevent server timeouts.</p>

        <button id="start-update-button" class="button button-primary">Start Update</button>

        <div id="updater-progress" style="display:none; margin-top: 20px; padding: 10px; border: 1px solid #ccc; background: #f7f7f7; max-height: 300px; overflow-y: auto;">
            <h3>Progress:</h3>
            <div id="progress-log"></div>
            <p id="final-message" style="font-weight: bold;"></p>
        </div>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#start-update-button').on('click', function() {
            // Disable button and show progress area
            $(this).prop('disabled', true).text('Processing...');
            $('#updater-progress').show();
            $('#progress-log').html('');
            $('#final-message').html('');

            // Start the process from the beginning (offset 0)
            processBatch(0);
        });

        function processBatch(offset) {
            $.ajax({
                url: ajaxurl, // WordPress's global AJAX URL
                type: 'POST',
                data: {
                    action: 'run_author_update',
                    nonce: '<?php echo wp_create_nonce("author_update_nonce"); ?>',
                    offset: offset
                },
                success: function(response) {
                    if (response.success) {
                        $('#progress-log').append('<div>' + response.data.message + '</div>');

                        if (response.data.status === 'continue') {
                            // If there are more posts, call the next batch
                            processBatch(response.data.new_offset);
                        } else {
                            // We are done!
                            $('#final-message').text('✅ Update complete! All posts have been processed.');
                            $('#start-update-button').text('Done').prop('disabled', true);
                        }
                    } else {
                        // Handle errors
                        $('#progress-log').append('<div style="color: red;">Error: ' + response.data.message + '</div>');
                        $('#start-update-button').text('Error Occurred. Retry?').prop('disabled', false);
                    }
                },
                error: function() {
                    $('#progress-log').append('<div style="color: red;">A critical server error occurred. Check the browser console or server logs.</div>');
                    $('#start-update-button').text('Error Occurred. Retry?').prop('disabled', false);
                }
            });
        }
    });
    </script>
    <?php
}

/**
 * ===================================================================
 * Part 4: The AJAX Handler (Server-Side Logic)
 * ===================================================================
 * This function runs on the server to process one batch of posts
 * and sends a status report back to the JavaScript.
 */
add_action('wp_ajax_run_author_update', 'author_meta_updater_ajax_handler');
function author_meta_updater_ajax_handler()
{
    // Security check
    check_ajax_referer('author_update_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied.']);
        wp_die();
    }

    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $batch_size = 25; // Process 25 posts at a time

    // Query for a batch of posts
    $args = [
        'post_type'      => ['publications', 'post'],
        'posts_per_page' => $batch_size,
        'offset'         => $offset,
        'post_status'    => 'any',
        'fields'         => 'ids', // More efficient, we only need the IDs
    ];
    $post_ids = get_posts($args);

    if (!empty($post_ids)) {
        // Process each post in the batch
        foreach ($post_ids as $post_id) {
            update_flat_author_ids_meta($post_id);
        }

        $new_offset = $offset + count($post_ids);
        wp_send_json_success([
            'status'     => 'continue',
            'new_offset' => $new_offset,
            'message'    => "Processed " . count($post_ids) . " posts (starting from #" . $offset . ")..."
        ]);
    } else {
        // No more posts found, we are done
        wp_send_json_success([
            'status'  => 'done',
            'message' => 'Finished processing.'
        ]);
    }

    wp_die();
}