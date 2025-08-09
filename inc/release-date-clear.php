<?php
/**
 * One-time admin tool to clear `release_date_new` ACF field across all posts.
 */

add_action('admin_menu', function () {
    add_submenu_page(
        'caes-tools',                     // Parent slug - points to CAES Tools
        'Story Clear Release Date Field', // Page title
        'Story Clear Release Date Field', // Menu title
        'manage_options',
        'clear-release-date',
        'render_clear_release_date_page'
    );
});

function render_clear_release_date_page()
{
    if (!current_user_can('manage_options')) return;

    $post_type  = 'post'; // Change if needed
    $field_name = 'release_date_new';
    $per_page   = 100;
    $paged      = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset     = ($paged - 1) * $per_page;

    echo '<div class="wrap"><h1>Clear Release Date Field</h1>';

    if (isset($_POST['clear_field']) && check_admin_referer('clear_release_date')) {
        $cleared = 0;

        $posts = get_posts([
            'post_type' => $post_type,
            'posts_per_page' => $per_page,
            'offset' => $offset,
            'post_status' => 'any',
            'meta_query' => [
                [
                    'key' => $field_name,
                    'compare' => 'EXISTS'
                ]
            ]
        ]);

        foreach ($posts as $post) {
            delete_field($field_name, $post->ID);
            $cleared++;
        }

        echo "<div class='notice notice-success'><p><strong>Cleared $cleared fields on this page.</strong></p></div>";
    }

    // Preview table
    $query = new WP_Query([
        'post_type' => $post_type,
        'post_status' => 'any',
        'posts_per_page' => $per_page,
        'offset' => $offset,
        'meta_query' => [
            [
                'key' => $field_name,
                'compare' => 'EXISTS'
            ]
        ]
    ]);

    echo '<form method="post">';
    wp_nonce_field('clear_release_date');
    echo '<table class="widefat"><thead><tr><th>Post</th><th>Value</th></tr></thead><tbody>';

    foreach ($query->posts as $post) {
        $value = get_field($field_name, $post->ID);
        echo "<tr>
            <td><a href='" . get_edit_post_link($post->ID) . "'>{$post->post_title}</a></td>
            <td><code>{$value}</code></td>
        </tr>";
    }

    echo '</tbody></table>';
    echo '<p><input type="submit" name="clear_field" class="button button-danger" value="Clear These ' . count($query->posts) . ' Fields"></p>';
    echo '</form>';

    // Pagination
    $total_query = new WP_Query([
        'post_type' => $post_type,
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => [
            [
                'key' => $field_name,
                'compare' => 'EXISTS'
            ]
        ]
    ]);
    $total_pages = ceil(count($total_query->posts) / $per_page);

    if ($total_pages > 1) {
        echo '<div class="tablenav"><div class="tablenav-pages">';
        echo paginate_links([
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'current' => $paged,
            'total' => $total_pages,
        ]);
        echo '</div></div>';
    }

    echo '</div>';
}
