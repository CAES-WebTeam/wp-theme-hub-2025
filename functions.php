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
require get_template_directory() . '/inc/events-support.php';
require get_template_directory() . '/inc/publications-support.php';
require get_template_directory() . '/inc/user-support.php';
require get_template_directory() . '/inc/news-support.php';
require get_template_directory() . '/block-variations/index.php';


add_action('admin_init', function () {
    if (!current_user_can('manage_options') || !isset($_GET['import_inline_images'])) return;


    $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;

    $all_posts = get_posts([
        'post_type'      => 'publications',
        'post_status'    => 'any',
        'fields'         => 'all',
        'orderby'        => 'ID',
        'order'          => 'ASC',
        'posts_per_page' => -1,
    ]);

    $batch = array_slice($all_posts, $start, $limit);
    $updated = 0;

    foreach ($batch as $post) {
        $original_content = $post->post_content;
        $content = $original_content;

        // Remove unwanted characters and entities
        $content = str_replace(
            ["\r\n", "\r", '&#13;', '&#013;', '&amp;#13;', '&#x0D;', '&#x0d;'],
            '',
            $content
        );

        // Match all <img src="..."> values
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $image_url) {
                // Skip local or already-uploaded images
                if (
                    strpos($image_url, home_url()) !== false ||
                    strpos($image_url, '/wp-content/uploads/') !== false
                ) continue;

                $tmp = download_url($image_url);
                if (is_wp_error($tmp)) continue;

                $file_array = [
                    'name'     => basename(parse_url($image_url, PHP_URL_PATH)),
                    'tmp_name' => $tmp,
                ];

                $attachment_id = media_handle_sideload($file_array, $post->ID);

                if (is_wp_error($attachment_id)) {
                    @unlink($tmp);
                    continue;
                }

                $new_url = wp_get_attachment_url($attachment_id);
                if ($new_url) {
                    $content = str_replace($image_url, $new_url, $content);
                }
            }
        }

        // Update post only if content has changed
        if ($content !== $original_content) {
            wp_update_post([
                'ID'           => $post->ID,
                'post_content' => $content,
            ]);
            $updated++;
        }
    }

    wp_die("Processed posts {$start} to " . ($start + $limit - 1) . ". Updated {$updated} posts (images or character cleanup).");
});

// ...

// Register a simple admin page under Tools
add_action('admin_menu', function () {
    add_submenu_page(
        'tools.php',
        'Backfill Author IDs',
        'Backfill Author IDs',
        'manage_options',
        'backfill-author-ids',
        'render_backfill_page'
    );
});

// Admin page content + batch processor triggered by button
function render_backfill_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $batch_size = 100;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $run = isset($_GET['run']) ? boolval($_GET['run']) : false;

    echo "<div class='wrap'>";
    echo "<h1>Backfill all_author_ids</h1>";

    // Count total publications (all statuses)
    $total_counts = wp_count_posts('publications');
    $total_pubs = array_sum((array) $total_counts);

    // Count publications with all_author_ids meta
    $with_meta_query = new WP_Query([
        'post_type'      => 'publications',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => [
            [
                'key'     => 'all_author_ids',
                'compare' => 'EXISTS',
            ]
        ],
    ]);
    $count_with_meta = count($with_meta_query->posts);

    echo "<p>ℹ️ Found <strong>$count_with_meta</strong> publications with <code>all_author_ids</code> out of <strong>$total_pubs</strong> total.</p>";

    if (!$run) {
        // Show the button to start processing
        $url = admin_url('tools.php?page=backfill-author-ids&run=1&offset=0');
        echo "<p><a href='" . esc_url($url) . "' class='button button-primary'>Start Backfill Process</a></p>";
    } else {
        // Run batch process
        $args = [
            'post_type'      => 'publications',
            'posts_per_page' => $batch_size,
            'offset'         => $offset,
            'post_status'    => 'any',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ];
        $post_ids = get_posts($args);

        foreach ($post_ids as $post_id) {
            update_flat_author_ids_meta($post_id);
        }

        $next_offset = $offset + $batch_size;
        $total_processed = $offset + count($post_ids);

        if (count($post_ids) === 0) {
            echo "<p>✅ All done! No more publications found to process.</p>";
            echo "<p><a href='" . esc_url(admin_url('tools.php?page=backfill-author-ids')) . "' class='button'>Back to start</a></p>";
        } else {
            echo "<p>🔄 Processed <strong>$total_processed</strong> publications so far... continuing in 2 seconds.</p>";
            echo "<p><em>If the page stops refreshing, you can <a href='" . esc_url(admin_url("tools.php?page=backfill-author-ids&run=1&offset={$next_offset}")) . "'>click here to continue manually</a>.</em></p>";
            echo "<meta http-equiv='refresh' content='2;url=" . esc_url(admin_url("tools.php?page=backfill-author-ids&run=1&offset={$next_offset}")) . "'>";
        }
    }

    // Show recent publications with backfilled all_author_ids for verification
    echo "<h2>🔍 Recent Publications with Backfilled Authors</h2>";
    echo "<table class='widefat fixed' style='max-width: 100%;'>";
    echo "<thead><tr><th>Title</th><th>all_author_ids</th></tr></thead>";
    echo "<tbody>";

    $recent_args = [
        'post_type'      => 'publications',
        'posts_per_page' => 5,
        'post_status'    => 'any',
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];
    $recent_posts = get_posts($recent_args);

    foreach ($recent_posts as $post) {
        $author_ids = get_post_meta($post->ID, 'all_author_ids', true);
        if (is_array($author_ids)) {
            $author_ids = implode(', ', $author_ids);
        } elseif (empty($author_ids)) {
            $author_ids = '<em>None</em>';
        }
        echo "<tr>";
        echo "<td>" . esc_html(get_the_title($post)) . "</td>";
        echo "<td>" . esc_html($author_ids) . "</td>";
        echo "</tr>";
    }

    echo "</tbody></table>";
    echo "</div>";
}
