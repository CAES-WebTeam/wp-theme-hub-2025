<?php
/**
 * CAES Publication Sunset Tool
 *
 * This file creates an admin page under "CAES Tools" to help manage
 * the automated unpublishing of publications.
 */

// Hook into the admin menu to add our tool page.
add_action('admin_menu', 'pub_sunset_tool_add_admin_page');

function pub_sunset_tool_add_admin_page() {
    add_submenu_page(
        'caes-tools',                         // Parent slug
        'Publication Sunset Tool',            // Page title
        'Pub Sunset Tool',                    // Menu title
        'manage_options',                     // Capability required: Ensures only Admins can see this.
        'pub-sunset-tool',                    // Menu slug
        'pub_sunset_tool_render_page'         // Function to render the page
    );
}

/**
 * Renders the HTML content for the Sunset Tool admin page.
 */
function pub_sunset_tool_render_page() {
    ?>
    <div class="wrap">
        <h1>Publication Sunset Tool</h1>
        <p>This tool helps you manage publications related to the automated "Sunset Date" unpublishing feature.</p>

        <?php
        // Process the manual run action only if the form was submitted and the nonce is valid.
        if (isset($_POST['pub_run_unpublish_script']) && check_admin_referer('pub_run_unpublish_nonce')) {
            $unpublished_count = pub_sunset_tool_run_script_manually();
            echo '<div class="notice notice-success is-dismissible"><p>Successfully ran the unpublishing script. <strong>' . absint($unpublished_count) . '</strong> publications were moved to draft.</p></div>';
        }
        ?>

        <div class="card">
            <h2 class="title">Manually Run Unpublish Script</h2>
            <p>This will immediately run the function that unpublishes all published posts whose "Sunset Date" is in the past. This is useful for testing or if you believe the automated cron job has missed something.</p>
            <form method="post" action="">
                <?php wp_nonce_field('pub_run_unpublish_nonce'); // Security: Generate the nonce token. ?>
                <input type="hidden" name="pub_run_unpublish_script" value="1">
                <input type="submit" class="button button-primary" value="Run Unpublish Script Now" onclick="return confirm('Are you sure you want to run the unpublish script? This will immediately move any expired publications to draft status.');">
            </form>
        </div>

        <div class="card">
            <h2 class="title">Publications Without a Sunset Date</h2>
            <p>The following publications do not have a <code>sunset_date</code> set. The automated unpublishing script will ignore these posts.</p>
            
            <?php pub_sunset_tool_display_publications_list(); ?>

        </div>
    </div>
    <?php
}

/**
 * Displays a paginated list of publications that do not have a sunset_date.
 */
function pub_sunset_tool_display_publications_list() {
    $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;

    $query = new WP_Query([
        'post_type'      => 'publications',
        'posts_per_page' => 20,
        'paged'          => $paged,
        'post_status'    => ['publish', 'draft', 'pending', 'future', 'private'],
        'meta_query'     => [
            [
                'key'     => 'sunset_date',
                'compare' => 'NOT EXISTS',
            ],
        ],
    ]);

    if ($query->have_posts()) {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th style="width: 60%;">Title</th><th>Status</th><th>Actions</th></tr></thead>';
        echo '<tbody>';

        foreach ($query->posts as $post) {
            $edit_link = get_edit_post_link($post->ID);
            $post_status_obj = get_post_status_object(get_post_status($post->ID));
            $status_label = $post_status_obj ? $post_status_obj->label : 'Unknown';
            
            echo '<tr>';
            echo '<td><strong>' . esc_html($post->post_title) . '</strong></td>';
            echo '<td>' . esc_html($status_label) . '</td>';
            echo '<td><a href="' . esc_url($edit_link) . '" class="button button-small">Edit</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        // Pagination controls
        $total_pages = $query->max_num_pages;
        if ($total_pages > 1) {
            $current_page = max(1, $paged);
            echo '<div class="tablenav"><div class="tablenav-pages">';
            echo paginate_links([
                'base'    => add_query_arg('paged', '%#%'),
                'format'  => '',
                'current' => $current_page,
                'total'   => $total_pages,
                'prev_text' => '&laquo; Previous',
                'next_text' => 'Next &raquo;',
            ]);
            echo '</div></div>';
        }

    } else {
        echo '<p>All publications have a sunset date set.</p>';
    }

    wp_reset_postdata();
}

/**
 * A modified version of the cron callback that returns a count.
 * This is used for the manual trigger.
 *
 * @return int The number of posts that were unpublished.
 */
function pub_sunset_tool_run_script_manually() {
    $today = date('Ymd');
    $unpublished_count = 0;

    $query = new WP_Query([
        'post_type'      => 'publications',
        'meta_key'       => 'sunset_date',
        'meta_value'     => $today,
        'meta_compare'   => '<=',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    ]);

    if ($query->have_posts()) {
        foreach ($query->posts as $post) {
            // Security: Final safety check to ensure the post has a sunset date.
            $sunset_date = get_post_meta($post->ID, 'sunset_date', true);
            if (empty($sunset_date)) {
                continue;
            }

            wp_update_post([
                'ID'          => $post->ID,
                'post_status' => 'draft',
            ]);
            $unpublished_count++;
        }
    }
    wp_reset_postdata();

    return $unpublished_count;
}