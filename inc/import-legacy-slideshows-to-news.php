<?php

add_action('admin_menu', function () {
    add_management_page(
        'Import Legacy Slider Images to News Stories',
        'Import Legacy Slider Images to News Stories',
        'manage_options',
        'legacy-slider-import',
        'render_import_json_acf'
    );
});

function render_import_json_acf()
{
    $per_page = 100;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

    echo '<div class="wrap"><h1>Import Legacy Slider Images to News Stories</h1>';

    if (isset($_GET['import'])) {
        if ($_GET['import'] === 'cleared') {
            $cleared_count = isset($_GET['cleared_count']) ? intval($_GET['cleared_count']) : 0;
            echo '<div class="notice notice-success is-dismissible"><p>Cleared carousel fields from ' . $cleared_count . ' posts.</p></div>';
        } else {
            echo '<div class="notice notice-' . ($_GET['import'] === 'success' ? 'success' : 'error') . ' is-dismissible"><p>' .
                ($_GET['import'] === 'success' ? 'Images successfully imported.' : 'No images were imported. All were already present or failed.') .
                '</p></div>';
        }
    }

    $json_path = get_template_directory() . '/json/FrankelNewsImagesJoined.json';
    if (!file_exists($json_path)) {
        echo '<p><strong>Error:</strong> JSON file not found at: ' . esc_html($json_path) . '</p></div>';
        return;
    }

    $json_raw = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($json_path));
    $json_data = json_decode($json_raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($json_data)) {
        echo '<p><strong>Error:</strong> Failed to decode JSON or no data found.</p></div>';
        return;
    }

    $show_unimported_only = isset($_GET['show_unimported']) && $_GET['show_unimported'] === '1';

    echo '<form method="get">';
    echo '<input type="hidden" name="page" value="legacy-slider-import">';
    echo '<input type="hidden" name="paged" value="' . esc_attr($current_page) . '">';
    echo '<label><input type="checkbox" name="show_unimported" value="1"' . checked($show_unimported_only, true, false) . '> Show only posts with missing images</label> ';
    submit_button('Filter', 'secondary', '', false);
    echo '</form>';

    $grouped_data = [];
    foreach ($json_data as $item) {
        $grouped_data[$item['STORY_ID']][] = $item;
    }

    // Get all story IDs and find matching posts in one query
    $story_ids = array_keys($grouped_data);
    $all_matching_posts = [];
    
    if (!empty($story_ids)) {
        // Use IN query instead of individual queries
        global $wpdb;
        $story_ids_placeholders = implode(',', array_fill(0, count($story_ids), '%s'));
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, pm.meta_value as story_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'post'
            AND pm.meta_key = 'id'
            AND pm.meta_value IN ($story_ids_placeholders)
        ", ...$story_ids));
        
        foreach ($results as $result) {
            $all_matching_posts[$result->story_id] = $result->ID;
        }
    }

    // Filter and paginate BEFORE doing expensive operations
    $hide_missing = !isset($_GET['hide_missing']) || $_GET['hide_missing'] == '1';
    
    $filtered_grouped_data = [];
    foreach ($grouped_data as $story_id => $images) {
        $post_id = $all_matching_posts[$story_id] ?? 0;
        
        if ($hide_missing && !$post_id) {
            continue;
        }
        
        $filtered_grouped_data[$story_id] = $images;
    }

    // Paginate first
    $total_items = count($filtered_grouped_data);
    $total_pages = ceil($total_items / $per_page);
    
    $paged_data = $total_pages > 1
        ? array_slice($filtered_grouped_data, ($current_page - 1) * $per_page, $per_page, true)
        : $filtered_grouped_data;

    // NOW apply the unimported filter only to the current page's data
    if ($show_unimported_only) {
        $final_paged_data = [];
        foreach ($paged_data as $story_id => $images) {
            $post_id = $all_matching_posts[$story_id] ?? 0;
            if (!$post_id) continue;
            
            $imported_count = 0;
            $carousel_field_key = 'field_683a19a810dd8';
            
            if (have_rows($carousel_field_key, $post_id)) {
                while (have_rows($carousel_field_key, $post_id)) {
                    the_row();
                    $existing_caption = trim(get_sub_field('caption'));
                    
                    foreach ($images as $item) {
                        $caption = sanitize_text_field($item['IMAGE'][0]['DESCRIPTION'] ?? '');
                        if ($existing_caption === trim($caption)) {
                            $imported_count++;
                            break;
                        }
                    }
                }
            }
            
            $all_imported = $imported_count === count($images);
            if (!$all_imported) {
                $final_paged_data[$story_id] = $images;
            }
        }
        $paged_data = $final_paged_data;
    }

    if ($total_pages > 1) {
        echo '<div class="tablenav"><div class="tablenav-pages"><span class="pagination-links">';
        for ($i = 1; $i <= $total_pages; $i++) {
            if ($i === $current_page) {
                echo '<span class="page-numbers current">' . $i . '</span> ';
            } else {
                echo '<a class="page-numbers" href="' . esc_url(add_query_arg('paged', $i)) . '">' . $i . '</a> ';
            }
        }
        echo '</span></div></div>';
    }

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('import_json_acf_images_bulk_action', 'import_json_acf_images_bulk_nonce');

    if (count($paged_data) > 0) {
        submit_button('Import Selected');
    }
    echo '<input type="hidden" name="action" value="import_json_acf_images_bulk">';
    echo '<input type="hidden" name="paged" value="' . esc_attr($current_page) . '">';
    echo '<table class="widefat fixed striped"><thead><tr>
		<th><input type="checkbox" onclick="jQuery(\'.select-post\').prop(\'checked\', this.checked);"></th>
		<th>Post Title</th><th>Story ID</th><th>Captions</th>
		<th>Image Labels</th><th>Sequence</th><th>Imported</th>
	</tr></thead><tbody>';

    foreach ($paged_data as $story_id => $images) {
        $post_id = $all_matching_posts[$story_id] ?? 0;
        
        if ($hide_missing && !$post_id) continue;

        $captions = $labels = $sequences = [];
        $imported_count = 0;
        $carousel_field_key = 'field_683a19a810dd8';
        
        foreach ($images as $item) {
            $caption = sanitize_text_field($item['IMAGE'][0]['DESCRIPTION'] ?? '');
            $image_info = $item['IMAGE'][0] ?? [];
            $captions[] = $caption;
            $labels[] = $image_info['IMAGE_LABEL'] ?? '';
            $sequences[] = $item['SEQUENCE_NUMBER'] ?? '';
        }
        
        // Check imported status only for the posts we're displaying
        if ($post_id && have_rows($carousel_field_key, $post_id)) {
            while (have_rows($carousel_field_key, $post_id)) {
                the_row();
                $existing_caption = trim(get_sub_field('caption'));
                
                foreach ($captions as $caption) {
                    if ($existing_caption === trim($caption)) {
                        $imported_count++;
                        break;
                    }
                }
            }
        }
        
        $all_imported = $imported_count === count($images);
        
        echo '<tr>';
        echo '<td>';
        if ($post_id && !$all_imported) {
            echo '<input type="checkbox" class="select-post" name="bulk_items[' . esc_attr($post_id) . '][images]" value="' . esc_attr(json_encode($images)) . '">';
        } else {
            echo '&mdash;';
        }
        echo '</td>';
        echo '<td>' . ($post_id ? '<a href="' . esc_url(get_edit_post_link($post_id)) . '">' . esc_html(get_the_title($post_id)) . '</a>' : '<em>Not found</em>') . '</td>';
        echo '<td>' . esc_html($story_id) . '</td>';
        echo '<td>' . implode('<br>', array_map('esc_html', $captions)) . '</td>';
        echo '<td>' . implode('<br>', array_map('esc_html', $labels)) . '</td>';
        echo '<td>' . implode('<br>', array_map('esc_html', $sequences)) . '</td>';
        echo '<td>' . ($all_imported ? '<span style="color: green;">Yes</span>' : '<span style="color: red;">No</span>') . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</form>';

    echo '<hr style="margin-top:2em; margin-bottom:2em;">';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'Are you sure you want to clear all carousel fields? This cannot be undone.\');">';
    wp_nonce_field('clear_all_carousels_action', 'clear_all_carousels_nonce');
    echo '<input type="hidden" name="action" value="clear_all_carousels">';
    submit_button('Clear All Carousel Fields', 'delete');
    echo '</form>';

    echo '</div>'; // .wrap
}


add_action('admin_post_import_json_acf_images', 'handle_import_json_acf_images');

function import_images_to_post($post_id, $images)
{
    $assigned = 0;
    $carousel_field_key = 'field_683a19a810dd8';

    // Optional: wipe current carousel
    delete_field($carousel_field_key, $post_id);

    $raw_acf_date = get_field('date_created', $post_id);
    $acf_timestamp = strtotime($raw_acf_date);
    $upload_subdir = $acf_timestamp ? date('Y/m', $acf_timestamp) : (get_the_date('Y/m', $post_id) ?: date('Y/m'));

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    foreach ($images as $item) {
        $image_id = $item['IMAGE_ID'] ?? '';
        $caption = sanitize_text_field($item['IMAGE'][0]['DESCRIPTION'] ?? '');
        $original_file_name = $item['IMAGE'][0]['WEB_VERSION_FILE_NAME'] ?? '';
        if (!$image_id || !$original_file_name) continue;

        // Use original file name in the URL
        $image_url = "https://secure.caes.uga.edu/news/multimedia/images/{$image_id}/{$original_file_name}";

        // Check if already imported
        $already_imported = false;
        if (have_rows($carousel_field_key, $post_id)) {
            while (have_rows($carousel_field_key, $post_id)) {
                the_row();
                if (trim(get_sub_field('caption')) === $caption) {
                    $already_imported = true;
                    break;
                }
            }
        }
        if ($already_imported) continue;

        // Check for existing attachment by URL
        $existing = get_posts([
            'post_type'   => 'attachment',
            'meta_key'    => '_source_image_url',
            'meta_value'  => esc_url_raw($image_url),
            'numberposts' => 1,
            'post_status' => 'inherit',
        ]);

        if (empty($existing)) {
            $tmp = download_url($image_url);
            if (is_wp_error($tmp)) continue;

            // Sanitize *after* download, for WordPress purposes
            $clean_file_name = preg_replace('/\.+/', '.', $original_file_name);
            $clean_file_name = sanitize_file_name($clean_file_name);

            $file_array = [
                'name'     => $clean_file_name,
                'tmp_name' => $tmp,
            ];

            $custom_upload_dir = fn($dirs) => [
                ...$dirs,
                'subdir' => '/' . $upload_subdir,
                'path'   => $dirs['basedir'] . '/' . $upload_subdir,
                'url'    => $dirs['baseurl'] . '/' . $upload_subdir,
            ];

            add_filter('upload_dir', $custom_upload_dir);
            $upload = wp_handle_sideload($file_array, ['test_form' => false]);
            remove_filter('upload_dir', $custom_upload_dir);

            if (isset($upload['error'])) {
                @unlink($tmp);
                continue;
            }

            $attachment = [
                'post_mime_type' => $upload['type'],
                'post_title'     => $clean_file_name,
                'post_content'   => '',
                'post_status'    => 'inherit',
            ];

            $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);
            wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));
            update_post_meta($attachment_id, '_source_image_url', esc_url_raw($image_url));
        } else {
            $attachment_id = $existing[0]->ID;
        }

        if (!empty($attachment_id)) {
            add_row($carousel_field_key, [
                'image'   => $attachment_id,
                'caption' => $caption,
            ], $post_id);
            $assigned++;
        }
    }


    return $assigned;
}

function handle_import_json_acf_images()
{
    if (
        !current_user_can('manage_options') ||
        empty($_POST['post_id']) ||
        empty($_POST['image_ids']) ||
        empty($_POST['file_names']) ||
        empty($_POST['captions'])
    ) {
        wp_die('Invalid request');
    }

    $post_id = (int) $_POST['post_id'];
    $images = [];

    foreach ($_POST['image_ids'] as $index => $image_id) {
        $images[] = [
            'IMAGE_ID' => $image_id,
            'CAPTION' => $_POST['captions'][$index] ?? '',
            'IMAGE' => [
                ['WEB_VERSION_FILE_NAME' => $_POST['file_names'][$index] ?? '']
            ]
        ];
    }

    $assigned = import_images_to_post($post_id, $images);

    wp_redirect(admin_url('admin.php?page=legacy-slider-import&import=' . ($assigned > 0 ? 'success' : 'fail')));
    exit;
}

function handle_import_json_acf_images_bulk()
{
    if (
        !current_user_can('manage_options') ||
        empty($_POST['bulk_items']) ||
        !isset($_POST['import_json_acf_images_bulk_nonce']) ||
        !wp_verify_nonce($_POST['import_json_acf_images_bulk_nonce'], 'import_json_acf_images_bulk_action')
    ) {
        wp_die('Invalid request.');
    }

    if (!current_user_can('manage_options') || empty($_POST['bulk_items'])) {
        wp_die('Invalid request.');
    }

    $total_assigned = 0;

    foreach ($_POST['bulk_items'] as $post_id => $data) {
        $post_id = (int) $post_id;
        $images = json_decode(stripslashes($data['images']), true);
        if (!$post_id || !$images) continue;

        $total_assigned += import_images_to_post($post_id, $images);
    }

    // Get the paged value from POST or default to 1
    $paged = isset($_POST['paged']) ? max(1, intval($_POST['paged'])) : 1;

    // Redirect including paged param to stay on the same page
    wp_redirect(admin_url('admin.php?page=legacy-slider-import&import=' . ($total_assigned > 0 ? 'success' : 'fail') . '&paged=' . $paged));
    exit;
}


add_action('admin_post_import_json_acf_images_bulk', 'handle_import_json_acf_images_bulk');

function handle_clear_all_carousels()
{
	if (
		!current_user_can('manage_options') ||
		!isset($_POST['clear_all_carousels_nonce']) ||
		!wp_verify_nonce($_POST['clear_all_carousels_nonce'], 'clear_all_carousels_action')
	) {
		wp_die('Invalid request.');
	}

    $carousel_field_key = 'field_683a19a810dd8';
    $cleared_count = 0;

    // Get all posts - we'll check each one individually since ACF repeater meta queries are unreliable
    $posts = get_posts([
        'post_type'      => 'post',
        'posts_per_page' => -1,
        'post_status'    => 'any'
    ]);

    foreach ($posts as $post) {
        // Check if this post has any carousel rows
        if (have_rows($carousel_field_key, $post->ID)) {
            // Delete the field completely
            delete_field($carousel_field_key, $post->ID);
            $cleared_count++;
        }
    }

    // Also clean up any orphaned ACF meta that might be left behind
    global $wpdb;
    $wpdb->query($wpdb->prepare("
        DELETE FROM {$wpdb->postmeta} 
        WHERE meta_key LIKE %s
    ", $carousel_field_key . '%'));

    wp_redirect(admin_url('admin.php?page=legacy-slider-import&import=cleared&cleared_count=' . $cleared_count));
    exit;
}
add_action('admin_post_clear_all_carousels', 'handle_clear_all_carousels');
