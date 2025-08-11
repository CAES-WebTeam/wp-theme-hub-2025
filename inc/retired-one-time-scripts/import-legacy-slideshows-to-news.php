<?php

// add_action('admin_menu', function () {
//     add_management_page(
//         'Import Legacy Slider Images to News Stories',
//         'Import Legacy Slider Images to News Stories',
//         'manage_options',
//         'legacy-slider-import',
//         'render_import_json_acf'
//     );
// });

// function render_import_json_acf()
// {
//     $per_page = 100;
//     $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

//     echo '<div class="wrap"><h1>Import Legacy Slider Images to News Stories</h1>';

//     if (isset($_GET['import'])) {
//         if ($_GET['import'] === 'cleared') {
//             $cleared_count = isset($_GET['cleared_count']) ? intval($_GET['cleared_count']) : 0;
//             echo '<div class="notice notice-success is-dismissible"><p>Cleared carousel fields from ' . $cleared_count . ' posts.</p></div>';
//         } else {
//             echo '<div class="notice notice-' . ($_GET['import'] === 'success' ? 'success' : 'error') . ' is-dismissible"><p>' .
//                 ($_GET['import'] === 'success' ? 'Images successfully imported.' : 'No images were imported. All were already present or failed.') .
//                 '</p></div>';
//         }
//     }

//     $json_path = get_template_directory() . '/json/FrankelNewsImagesJoined.json';
//     if (!file_exists($json_path)) {
//         echo '<p><strong>Error:</strong> JSON file not found at: ' . esc_html($json_path) . '</p></div>';
//         return;
//     }

//     $json_raw = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($json_path));
//     $json_data = json_decode($json_raw, true);
//     if (json_last_error() !== JSON_ERROR_NONE || empty($json_data)) {
//         echo '<p><strong>Error:</strong> Failed to decode JSON or no data found.</p></div>';
//         return;
//     }

//     $show_unimported_only = isset($_GET['show_unimported']) && $_GET['show_unimported'] === '1';

//     echo '<form method="get">';
//     echo '<input type="hidden" name="page" value="legacy-slider-import">';
//     echo '<label><input type="checkbox" name="show_unimported" value="1"' . checked($show_unimported_only, true, false) . '> Show only posts with missing images</label> ';
//     submit_button('Filter', 'secondary', '', false);
//     echo '</form>';

//     $grouped_data = [];
//     foreach ($json_data as $item) {
//         $grouped_data[$item['STORY_ID']][] = $item;
//     }

//     // Get all story IDs and find matching posts in one query
//     $story_ids = array_keys($grouped_data);
//     $all_matching_posts = [];
    
//     if (!empty($story_ids)) {
//         global $wpdb;
//         $story_ids_placeholders = implode(',', array_fill(0, count($story_ids), '%s'));
//         $results = $wpdb->get_results($wpdb->prepare("
//             SELECT p.ID, pm.meta_value as story_id
//             FROM {$wpdb->posts} p
//             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
//             WHERE p.post_type = 'post'
//             AND pm.meta_key = 'id'
//             AND pm.meta_value IN ($story_ids_placeholders)
//         ", ...$story_ids));
        
//         foreach ($results as $result) {
//             $all_matching_posts[$result->story_id] = $result->ID;
//         }
//     }

//     $carousel_field_key = 'field_683a19a810dd8'; // legacy_gallery field
    
//     // Helper function to check if an image is already in carousel
//     function is_image_in_carousel($post_id, $image_url, $filename, $carousel_field_key) {
//         if (!$post_id) return false;
        
//         if (have_rows($carousel_field_key, $post_id) || have_rows('legacy_gallery', $post_id)) {
//             $field_ref = have_rows($carousel_field_key, $post_id) ? $carousel_field_key : 'legacy_gallery';
            
//             while (have_rows($field_ref, $post_id)) {
//                 the_row();
//                 $existing_image_data = get_sub_field('image');
//                 $existing_image_id = is_array($existing_image_data) ? $existing_image_data['ID'] : $existing_image_data;
                
//                 if ($existing_image_id) {
//                     // Check by source URL first (most reliable)
//                     $existing_source_url = get_post_meta($existing_image_id, '_source_image_url', true);
//                     if ($existing_source_url === $image_url) {
//                         return true;
//                     }
                    
//                     // Fallback: check by filename
//                     if (!$existing_source_url) {
//                         $existing_filename = is_array($existing_image_data) ? $existing_image_data['filename'] : basename(get_attached_file($existing_image_id));
//                         $clean_file_name = preg_replace('/\.+/', '.', $filename);
//                         $clean_file_name = sanitize_file_name($clean_file_name);
                        
//                         // Check for exact match or WordPress auto-renamed versions
//                         $base_name = pathinfo($clean_file_name, PATHINFO_FILENAME);
//                         $extension = pathinfo($clean_file_name, PATHINFO_EXTENSION);
                        
//                         if ($existing_filename === $clean_file_name || 
//                             preg_match('/^' . preg_quote($base_name, '/') . '(-\d+)?\.' . preg_quote($extension, '/') . '$/i', $existing_filename)) {
//                             return true;
//                         }
//                     }
//                 }
//             }
//         }
//         return false;
//     }

//     // Filter data BEFORE pagination
//     $filtered_grouped_data = [];
//     foreach ($grouped_data as $story_id => $images) {
//         $post_id = $all_matching_posts[$story_id] ?? 0;
        
//         // Skip posts without matching WordPress post
//         if (!$post_id) {
//             continue;
//         }
        
//         // If filtering for unimported only, check if any images are missing from carousel
//         if ($show_unimported_only) {
//             $has_unimported_images = false;
            
//             foreach ($images as $item) {
//                 $item_image_id = $item['IMAGE_ID'] ?? '';
//                 $item_file_name = $item['IMAGE'][0]['WEB_VERSION_FILE_NAME'] ?? '';
//                 if (!$item_image_id || !$item_file_name) continue;
                
//                 $item_image_url = "https://secure.caes.uga.edu/news/multimedia/images/{$item_image_id}/{$item_file_name}";
                
//                 if (!is_image_in_carousel($post_id, $item_image_url, $item_file_name, $carousel_field_key)) {
//                     $has_unimported_images = true;
//                     break;
//                 }
//             }
            
//             // Only include if it has unimported images
//             if ($has_unimported_images) {
//                 $filtered_grouped_data[$story_id] = $images;
//             }
//         } else {
//             // Include all posts that have matching WordPress posts
//             $filtered_grouped_data[$story_id] = $images;
//         }
//     }

//     // Calculate pagination based on filtered results
//     $total_items = count($filtered_grouped_data);
//     $total_pages = ceil($total_items / $per_page);
    
//     // Apply pagination to filtered data
//     $paged_data = [];
//     if ($total_items > 0) {
//         $offset = ($current_page - 1) * $per_page;
//         $paged_data = array_slice($filtered_grouped_data, $offset, $per_page, true);
//     }

//     // Display pagination info and controls
//     if ($total_items > 0) {
//         echo '<p>Showing ' . count($paged_data) . ' of ' . $total_items . ' stories';
//         if ($show_unimported_only) {
//             echo ' <strong>with unimported images</strong>';
//         }
//         echo '.</p>';
        
//         if ($total_pages > 1) {
//             echo '<div class="tablenav"><div class="tablenav-pages"><span class="pagination-links">';
            
//             // Previous page link
//             if ($current_page > 1) {
//                 echo '<a class="page-numbers prev" href="' . esc_url(add_query_arg('paged', $current_page - 1)) . '">‹ Previous</a> ';
//             }
            
//             // Page number links
//             for ($i = 1; $i <= $total_pages; $i++) {
//                 if ($i === $current_page) {
//                     echo '<span class="page-numbers current">' . $i . '</span> ';
//                 } else {
//                     echo '<a class="page-numbers" href="' . esc_url(add_query_arg('paged', $i)) . '">' . $i . '</a> ';
//                 }
//             }
            
//             // Next page link
//             if ($current_page < $total_pages) {
//                 echo '<a class="page-numbers next" href="' . esc_url(add_query_arg('paged', $current_page + 1)) . '">Next ›</a>';
//             }
            
//             echo '</span></div></div>';
//         }
//     } else {
//         echo '<p><strong>No stories found</strong>';
//         if ($show_unimported_only) {
//             echo ' with unimported images';
//         }
//         echo '.</p>';
//     }

//     echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
//     wp_nonce_field('import_json_acf_images_bulk_action', 'import_json_acf_images_bulk_nonce');

//     if (count($paged_data) > 0) {
//         submit_button('Import Selected');
//     }
//     echo '<input type="hidden" name="action" value="import_json_acf_images_bulk">';
//     echo '<input type="hidden" name="paged" value="' . esc_attr($current_page) . '">';
    
//     if (!empty($paged_data)) {
//         echo '<table class="widefat fixed striped"><thead><tr>
//             <th><input type="checkbox" onclick="jQuery(\'.select-post\').prop(\'checked\', this.checked);"></th>
//             <th>Post Title</th><th>Story ID</th><th>Images</th>
//             <th>In Carousel</th>
//         </tr></thead><tbody>';

//         foreach ($paged_data as $story_id => $images) {
//             $post_id = $all_matching_posts[$story_id] ?? 0;
            
//             if (!$post_id) continue;

//             // Check which images are in carousel
//             $image_statuses = [];
//             $has_unassigned = false;
            
//             foreach ($images as $item) {
//                 $item_image_id = $item['IMAGE_ID'] ?? '';
//                 $item_file_name = $item['IMAGE'][0]['WEB_VERSION_FILE_NAME'] ?? '';
//                 $caption = sanitize_text_field($item['IMAGE'][0]['DESCRIPTION'] ?? '');
                
//                 $in_carousel = false;
//                 if ($item_image_id && $item_file_name) {
//                     $item_image_url = "https://secure.caes.uga.edu/news/multimedia/images/{$item_image_id}/{$item_file_name}";
//                     $in_carousel = is_image_in_carousel($post_id, $item_image_url, $item_file_name, $carousel_field_key);
//                 }
                
//                 if (!$in_carousel) {
//                     $has_unassigned = true;
//                 }
                
//                 $image_statuses[] = [
//                     'caption' => $caption,
//                     'filename' => $item_file_name,
//                     'in_carousel' => $in_carousel
//                 ];
//             }
            
//             echo '<tr>';
//             echo '<td>';
//             if ($has_unassigned) {
//                 echo '<input type="checkbox" class="select-post" name="bulk_items[' . esc_attr($post_id) . '][images]" value="' . esc_attr(json_encode($images)) . '">';
//             } else {
//                 echo '&mdash;';
//             }
//             echo '</td>';
//             echo '<td><a href="' . esc_url(get_edit_post_link($post_id)) . '">' . esc_html(get_the_title($post_id)) . '</a></td>';
//             echo '<td>' . esc_html($story_id) . '</td>';
            
//             // Display images info
//             echo '<td>';
//             foreach ($image_statuses as $i => $status) {
//                 if ($i > 0) echo '<br>';
//                 echo '<strong>' . ($i + 1) . ':</strong> ' . esc_html(substr($status['caption'], 0, 80));
//                 if (strlen($status['caption']) > 80) echo '...';
//                 echo '<br><small>' . esc_html($status['filename']) . '</small>';
//             }
//             echo '</td>';
            
//             // Display carousel status
//             echo '<td>';
//             foreach ($image_statuses as $i => $status) {
//                 if ($i > 0) echo '<br>';
//                 echo '<strong>' . ($i + 1) . ':</strong> ';
//                 if ($status['in_carousel']) {
//                     echo '<span style="color: green;">✓ Yes</span>';
//                 } else {
//                     echo '<span style="color: red;">✗ No</span>';
//                 }
//                 echo '<br><small>&nbsp;</small>'; // spacing to match other column
//             }
//             echo '</td>';
            
//             echo '</tr>';
//         }

//         echo '</tbody></table>';
//     }
//     echo '</form>';

//     // Show pagination again at bottom if needed
//     if ($total_pages > 1) {
//         echo '<div class="tablenav bottom"><div class="tablenav-pages"><span class="pagination-links">';
        
//         if ($current_page > 1) {
//             echo '<a class="page-numbers prev" href="' . esc_url(add_query_arg('paged', $current_page - 1)) . '">‹ Previous</a> ';
//         }
        
//         for ($i = 1; $i <= $total_pages; $i++) {
//             if ($i === $current_page) {
//                 echo '<span class="page-numbers current">' . $i . '</span> ';
//             } else {
//                 echo '<a class="page-numbers" href="' . esc_url(add_query_arg('paged', $i)) . '">' . $i . '</a> ';
//             }
//         }
        
//         if ($current_page < $total_pages) {
//             echo '<a class="page-numbers next" href="' . esc_url(add_query_arg('paged', $current_page + 1)) . '">Next ›</a>';
//         }
        
//         echo '</span></div></div>';
//     }

//     echo '<hr style="margin-top:2em; margin-bottom:2em;">';
//     echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'Are you sure you want to clear all carousel fields? This cannot be undone.\');">';
//     wp_nonce_field('clear_all_carousels_action', 'clear_all_carousels_nonce');
//     echo '<input type="hidden" name="action" value="clear_all_carousels">';
//     submit_button('Clear All Carousel Fields', 'delete');
//     echo '</form>';

//     echo '</div>'; // .wrap
// }

// // ... rest of your functions remain the same ...
// add_action('admin_post_import_json_acf_images', 'handle_import_json_acf_images');

// function import_images_to_post($post_id, $images)
// {
//     $assigned = 0;
//     $carousel_field_key = 'field_683a19a810dd8';

//     // Get current post for debugging
//     $post_meta_id = get_post_meta($post_id, 'id', true);
//     $debug_mode = ($post_meta_id == '7955' || $post_meta_id == '8018' || $post_meta_id == '8159'); // Enable debugging for story IDs 7955, 8018, and 8159
    
//     if ($debug_mode) {
//         error_log("=== DEBUGGING Story ID {$post_meta_id} → WordPress Post ID {$post_id} ===");
//         error_log("Total images to process: " . count($images));
//     }

//     // Optional: wipe current carousel
//     delete_field($carousel_field_key, $post_id);
//     delete_field('legacy_gallery', $post_id); // fallback

//     $raw_acf_date = get_field('date_created', $post_id);
//     $acf_timestamp = strtotime($raw_acf_date);
//     $upload_subdir = $acf_timestamp ? date('Y/m', $acf_timestamp) : (get_the_date('Y/m', $post_id) ?: date('Y/m'));

//     require_once ABSPATH . 'wp-admin/includes/image.php';
//     require_once ABSPATH . 'wp-admin/includes/file.php';
//     require_once ABSPATH . 'wp-admin/includes/media.php';

//     foreach ($images as $index => $item) {
//         if ($debug_mode) {
//             error_log("--- Processing image " . ($index + 1) . " ---");
//             error_log("Raw item data: " . print_r($item, true));
//         }
        
//         $image_id = $item['IMAGE_ID'] ?? '';
//         $caption = sanitize_text_field($item['IMAGE'][0]['DESCRIPTION'] ?? '');
//         $original_file_name = $item['IMAGE'][0]['WEB_VERSION_FILE_NAME'] ?? '';
        
//         if ($debug_mode) {
//             error_log("Image ID: '{$image_id}'");
//             error_log("Caption: '{$caption}'");
//             error_log("Original filename: '{$original_file_name}'");
//         }
        
//         if (!$image_id || !$original_file_name) {
//             if ($debug_mode) {
//                 error_log("SKIPPED: Missing image_id or filename");
//             }
//             continue;
//         }

//         // Use original file name in the URL
//         $image_url = "https://secure.caes.uga.edu/news/multimedia/images/{$image_id}/{$original_file_name}";
        
//         if ($debug_mode) {
//             error_log("Image URL: {$image_url}");
//         }

//         // Check if already assigned to carousel (simple approach)
//         $already_in_carousel = false;
//         if (have_rows($carousel_field_key, $post_id)) {
//             while (have_rows($carousel_field_key, $post_id)) {
//                 the_row();
//                 $existing_image_id = get_sub_field('image');
//                 if ($existing_image_id) {
//                     // Check by source URL first (most reliable)
//                     $existing_source_url = get_post_meta($existing_image_id, '_source_image_url', true);
//                     if ($existing_source_url === $image_url) {
//                         $already_in_carousel = true;
//                         if ($debug_mode) {
//                             error_log("SKIPPED: Already in carousel (source URL match)");
//                         }
//                         break;
//                     }
                    
//                     // Fallback: check by filename if no source URL
//                     if (!$existing_source_url) {
//                         $existing_filename = basename(get_attached_file($existing_image_id));
//                         $clean_file_name = preg_replace('/\.+/', '.', $original_file_name);
//                         $clean_file_name = sanitize_file_name($clean_file_name);
                        
//                         if ($existing_filename === $clean_file_name) {
//                             $already_in_carousel = true;
//                             if ($debug_mode) {
//                                 error_log("SKIPPED: Already in carousel (filename match)");
//                             }
//                             break;
//                         }
//                     }
//                 }
//             }
//         }
//         if ($already_in_carousel) continue;

//         // Check for existing attachment by URL first
//         $existing = get_posts([
//             'post_type'   => 'attachment',
//             'meta_key'    => '_source_image_url',
//             'meta_value'  => esc_url_raw($image_url),
//             'numberposts' => 1,
//             'post_status' => 'inherit',
//         ]);

//         if ($debug_mode) {
//             error_log("Existing attachments found by URL: " . count($existing));
//         }

//         // If no existing attachment found by URL, check by filename + filesize as fallback
//         if (empty($existing)) {
//             // Get the remote file size first
//             $headers = wp_remote_head($image_url);
//             $remote_filesize = 0;
//             if (!is_wp_error($headers)) {
//                 $remote_filesize = wp_remote_retrieve_header($headers, 'content-length');
//             }
            
//             if ($debug_mode) {
//                 error_log("Remote file size: {$remote_filesize} bytes");
//             }
            
//             if ($remote_filesize > 0) {
//                 // Clean the filename for comparison
//                 $clean_file_name = preg_replace('/\.+/', '.', $original_file_name);
//                 $clean_file_name = sanitize_file_name($clean_file_name);
                
//                 // Search for existing attachments with same filename
//                 $existing_by_filename = get_posts([
//                     'post_type'   => 'attachment',
//                     'meta_key'    => '_wp_attached_file',
//                     'meta_compare' => 'LIKE',
//                     'meta_value'  => '%' . $clean_file_name,
//                     'numberposts' => -1,
//                     'post_status' => 'inherit',
//                 ]);
                
//                 if ($debug_mode) {
//                     error_log("Found " . count($existing_by_filename) . " attachments with similar filename");
//                 }
                
//                 // Check filesize of matches
//                 foreach ($existing_by_filename as $potential_match) {
//                     $file_path = get_attached_file($potential_match->ID);
//                     if ($file_path && file_exists($file_path)) {
//                         $local_filesize = filesize($file_path);
//                         if ($debug_mode) {
//                             error_log("Checking {$potential_match->ID}: local={$local_filesize}, remote={$remote_filesize}");
//                         }
                        
//                         // If filesizes match, consider it the same file
//                         if ($local_filesize == $remote_filesize) {
//                             $existing = [$potential_match];
//                             if ($debug_mode) {
//                                 error_log("Found existing attachment by filename+size match: ID {$potential_match->ID}");
//                             }
//                             // Tag this attachment with the source URL for future reference
//                             update_post_meta($potential_match->ID, '_source_image_url', esc_url_raw($image_url));
//                             break;
//                         }
//                     }
//                 }
//             }
//         }

//         if (empty($existing)) {
//             if ($debug_mode) {
//                 error_log("Downloading image from: {$image_url}");
//             }
            
//             $tmp = download_url($image_url);
//             if (is_wp_error($tmp)) {
//                 if ($debug_mode) {
//                     error_log("DOWNLOAD FAILED: " . $tmp->get_error_message());
//                 }
//                 continue;
//             }
            
//             if ($debug_mode) {
//                 error_log("Download successful, temp file: {$tmp}");
//             }

//             // Sanitize *after* download, for WordPress purposes
//             $clean_file_name = preg_replace('/\.+/', '.', $original_file_name);
//             $clean_file_name = sanitize_file_name($clean_file_name);
            
//             if ($debug_mode) {
//                 error_log("Clean filename: '{$clean_file_name}'");
//             }

//             $file_array = [
//                 'name'     => $clean_file_name,
//                 'tmp_name' => $tmp,
//             ];

//             $custom_upload_dir = fn($dirs) => [
//                 ...$dirs,
//                 'subdir' => '/' . $upload_subdir,
//                 'path'   => $dirs['basedir'] . '/' . $upload_subdir,
//                 'url'    => $dirs['baseurl'] . '/' . $upload_subdir,
//             ];

//             add_filter('upload_dir', $custom_upload_dir);
//             $upload = wp_handle_sideload($file_array, ['test_form' => false]);
//             remove_filter('upload_dir', $custom_upload_dir);

//             if (isset($upload['error'])) {
//                 if ($debug_mode) {
//                     error_log("UPLOAD FAILED: " . $upload['error']);
//                 }
//                 @unlink($tmp);
//                 continue;
//             }
            
//             if ($debug_mode) {
//                 error_log("Upload successful: " . print_r($upload, true));
//             }

//             $attachment = [
//                 'post_mime_type' => $upload['type'],
//                 'post_title'     => $clean_file_name,
//                 'post_content'   => '',
//                 'post_status'    => 'inherit',
//             ];

//             $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);
//             if ($debug_mode) {
//                 error_log("Created attachment ID: {$attachment_id}");
//             }
            
//             wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));
//             update_post_meta($attachment_id, '_source_image_url', esc_url_raw($image_url));
//         } else {
//             $attachment_id = $existing[0]->ID;
//             if ($debug_mode) {
//                 error_log("Using existing attachment ID: {$attachment_id}");
//             }
//         }

//         if (!empty($attachment_id)) {
//             $row_added = add_row($carousel_field_key, [
//                 'image'   => $attachment_id,
//                 'caption' => $caption,
//             ], $post_id);
            
//             // If field key didn't work, try field name
//             if (!$row_added) {
//                 $row_added = add_row('legacy_gallery', [
//                     'image'   => $attachment_id,
//                     'caption' => $caption,
//                 ], $post_id);
//             }
            
//             if ($debug_mode) {
//                 error_log("ACF row added: " . ($row_added ? 'SUCCESS' : 'FAILED'));
//             }
            
//             if ($row_added) {
//                 $assigned++;
//             }
//         } else {
//             if ($debug_mode) {
//                 error_log("ERROR: No attachment_id available");
//             }
//         }
//     }

//     if ($debug_mode) {
//         error_log("=== FINAL RESULT: {$assigned} images assigned ===");
//     }

//     return $assigned;
// }

// function handle_import_json_acf_images()
// {
//     if (
//         !current_user_can('manage_options') ||
//         empty($_POST['post_id']) ||
//         empty($_POST['image_ids']) ||
//         empty($_POST['file_names']) ||
//         empty($_POST['captions'])
//     ) {
//         wp_die('Invalid request');
//     }

//     $post_id = (int) $_POST['post_id'];
//     $images = [];

//     foreach ($_POST['image_ids'] as $index => $image_id) {
//         $images[] = [
//             'IMAGE_ID' => $image_id,
//             'CAPTION' => $_POST['captions'][$index] ?? '',
//             'IMAGE' => [
//                 ['WEB_VERSION_FILE_NAME' => $_POST['file_names'][$index] ?? '']
//             ]
//         ];
//     }

//     $assigned = import_images_to_post($post_id, $images);

//     wp_redirect(admin_url('admin.php?page=legacy-slider-import&import=' . ($assigned > 0 ? 'success' : 'fail')));
//     exit;
// }

// function handle_import_json_acf_images_bulk()
// {
//     if (
//         !current_user_can('manage_options') ||
//         empty($_POST['bulk_items']) ||
//         !isset($_POST['import_json_acf_images_bulk_nonce']) ||
//         !wp_verify_nonce($_POST['import_json_acf_images_bulk_nonce'], 'import_json_acf_images_bulk_action')
//     ) {
//         wp_die('Invalid request.');
//     }

//     if (!current_user_can('manage_options') || empty($_POST['bulk_items'])) {
//         wp_die('Invalid request.');
//     }

//     $total_assigned = 0;

//     foreach ($_POST['bulk_items'] as $post_id => $data) {
//         $post_id = (int) $post_id;
//         $images = json_decode(stripslashes($data['images']), true);
//         if (!$post_id || !$images) continue;

//         $total_assigned += import_images_to_post($post_id, $images);
//     }

//     // Get the paged value from POST or default to 1
//     $paged = isset($_POST['paged']) ? max(1, intval($_POST['paged'])) : 1;

//     // Redirect including paged param to stay on the same page
//     wp_redirect(admin_url('admin.php?page=legacy-slider-import&import=' . ($total_assigned > 0 ? 'success' : 'fail') . '&paged=' . $paged));
//     exit;
// }

// add_action('admin_post_import_json_acf_images_bulk', 'handle_import_json_acf_images_bulk');

// function handle_clear_all_carousels()
// {
// 	if (
// 		!current_user_can('manage_options') ||
// 		!isset($_POST['clear_all_carousels_nonce']) ||
// 		!wp_verify_nonce($_POST['clear_all_carousels_nonce'], 'clear_all_carousels_action')
// 	) {
// 		wp_die('Invalid request.');
// 	}

//     $carousel_field_key = 'field_683a19a810dd8';
//     $cleared_count = 0;

//     // Get all posts - we'll check each one individually since ACF repeater meta queries are unreliable
//     $posts = get_posts([
//         'post_type'      => 'post',
//         'posts_per_page' => -1,
//         'post_status'    => 'any'
//     ]);

//     foreach ($posts as $post) {
//         // Check if this post has any carousel rows
//         if (have_rows($carousel_field_key, $post->ID)) {
//             // Delete the field completely
//             delete_field($carousel_field_key, $post->ID);
//             $cleared_count++;
//         }
//     }

//     // Also clean up any orphaned ACF meta that might be left behind
//     global $wpdb;
//     $wpdb->query($wpdb->prepare("
//         DELETE FROM {$wpdb->postmeta} 
//         WHERE meta_key LIKE %s
//     ", $carousel_field_key . '%'));

//     wp_redirect(admin_url('admin.php?page=legacy-slider-import&import=cleared&cleared_count=' . $cleared_count));
//     exit;
// }
// add_action('admin_post_clear_all_carousels', 'handle_clear_all_carousels');