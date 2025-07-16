<?php

// Remove the enqueue script action, as JS will be inline
// This line might not be strictly necessary if you never had a function
// named 'story_meta_association_tools_enqueue_scripts' hooked to admin_enqueue_scripts,
// but it's harmless to keep if you previously tested with it.
remove_action('admin_enqueue_scripts', 'story_meta_association_tools_enqueue_scripts');

// Add admin menu page
add_action('admin_menu', 'story_meta_association_tools_menu_page');
function story_meta_association_tools_menu_page() {
    add_management_page(
        'Story Meta Association Tools',
        'Story Meta Tools',
        'manage_options',
        'story-meta-association-tools',
        'story_meta_association_tools_render_page'
    );
}

// Render the admin page content with inline JavaScript
function story_meta_association_tools_render_page() {
    // Generate nonce here for use in JS
    $nonce = wp_create_nonce('story_meta_association_tools_nonce');
    $ajax_url = admin_url('admin-ajax.php');
    ?>
    <div class="wrap">
        <h1>Story Meta Association Tools</h1>
        <p>Use the buttons below to run various meta-data association processes for your news stories.</p>

        <hr>

        <h2>1. Sync Keywords to News Stories</h2>
        <p>Reads `NEWS_ASSOCIATION_STORY_KEYWORD.json` and associates keywords (topics) with posts based on story and keyword IDs.</p>
        <button class="button button-primary" id="sync-keywords-btn">Run Keyword Sync</button>
        <div id="sync-keywords-log" class="log-area"></div>

        <hr>

        <h2>2. Link Story Images</h2>
        <p>Reads `news-image-association.json` and links images to posts via the 'image_id' ACF field. This process runs in batches of 500 records.</p>
        <button class="button button-primary" id="link-story-images-btn">Run Image Linking</button>
        <div id="link-story-images-log" class="log-area"></div>

        <hr>

        <h2>3. Assign Web Image Filenames</h2>
        <p>Reads `news-image.json` and assigns web version filenames to posts that have a matching 'image_id' ACF field. This process runs in batches of 500 records.</p>
        <button class="button button-primary" id="assign-filenames-btn">Run Filename Assignment</button>
        <div id="assign-filenames-log" class="log-area"></div>

        <hr>

        <h2>4. Import Featured Images</h2>
        <p>Downloads and sets featured images for posts based on 'image_id' and 'web_version_file_name' ACF fields. This process runs in batches of 100 posts.</p>
        <button class="button button-primary" id="import-featured-images-btn">Run Featured Image Import</button>
        <div id="import-featured-images-log" class="log-area"></div>

    </div>
    <style>
        .log-area {
            margin-top: 15px;
            padding: 10px;
            background-color: #f0f0f0;
            border: 1px solid #ccc;
            max-height: 300px;
            overflow-y: auto;
            white-space: pre-wrap;
            font-family: monospace;
            font-size: 0.9em;
        }
        .log-area.success { background-color: #e6ffe6; border-color: #00cc00; }
        .log-area.error { background-color: #ffe6e6; border-color: #cc0000; }
        .log-area.info { background-color: #e6f7ff; border-color: #0099ff; }
        .log-area div {
            padding: 2px 0;
        }
        .log-area .log-detail {
            color: #555;
            font-size: 0.85em;
        }
        .log-area .log-error {
            color: #cc0000;
            font-weight: bold;
        }
        .log-area .log-success {
            color: #008000;
            font-weight: bold;
        }
        .log-area .log-info {
            color: #000080;
        }
    </style>
    <script type="text/javascript">
        jQuery(document).ready(function($) {

            // Helper function to append log messages
            function appendLog(logElement, message, type = 'info') {
                const timestamp = new Date().toLocaleTimeString();
                logElement.append(`<div class="log-${type}">[${timestamp}] ${message}</div>`);
                logElement.scrollTop(logElement[0].scrollHeight); // Scroll to bottom
            }

            // Helper function to set log area class
            function setLogAreaClass(logElement, type) {
                logElement.removeClass('info success error').addClass(type);
            }

            // Function to handle sequential AJAX requests for batched processes
            function runBatchedProcess(buttonId, logId, actionName, startParam = 0) {
                const $button = $(`#${buttonId}`);
                const $logArea = $(`#${logId}`);

                $button.prop('disabled', true).text('Processing...');
                setLogAreaClass($logArea, 'info');

                appendLog($logArea, `Starting batch process '${actionName}'...`);

                function processBatch(start) {
                    $.ajax({
                        url: '<?php echo esc_js($ajax_url); ?>',
                        type: 'POST',
                        data: {
                            action: actionName,
                            nonce: '<?php echo esc_js($nonce); ?>',
                            start: start,
                        },
                        success: function(response) {
                            if (response.success) {
                                appendLog($logArea, response.data.message);
                                if (response.data.log && response.data.log.length > 0) {
                                    response.data.log.forEach(msg => appendLog($logArea, msg, 'detail'));
                                }

                                if (!response.data.finished) {
                                    appendLog($logArea, `Moving to next batch (start: ${response.data.start})...`);
                                    processBatch(response.data.start); // Call next batch
                                } else {
                                    $button.prop('disabled', false).text('Run Again');
                                    setLogAreaClass($logArea, 'success');
                                    appendLog($logArea, `Process '${actionName}' completed successfully!`, 'success');
                                }
                            } else {
                                $button.prop('disabled', false).text('Run Again');
                                setLogAreaClass($logArea, 'error');
                                appendLog($logArea, `Error: ${response.data}`, 'error');
                                appendLog($logArea, `Process '${actionName}' failed!`, 'error');
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            $button.prop('disabled', false).text('Run Again');
                            setLogAreaClass($logArea, 'error');
                            appendLog($logArea, `AJAX Error for ${actionName}: ${textStatus} - ${errorThrown}`, 'error');
                            appendLog($logArea, `Process '${actionName}' failed!`, 'error');
                        }
                    });
                }

                processBatch(startParam); // Start the first batch
            }

            // --- Button Event Listeners ---

            // Sync Keywords to News Stories
            $('#sync-keywords-btn').on('click', function() {
                const $button = $(this);
                const $logArea = $('#sync-keywords-log');
                $logArea.empty(); // Clear previous logs
                $button.prop('disabled', true).text('Processing...');
                setLogAreaClass($logArea, 'info');
                appendLog($logArea, 'Starting Keyword Sync...');

                $.ajax({
                    url: '<?php echo esc_js($ajax_url); ?>',
                    type: 'POST',
                    data: {
                        action: 'sync_keywords',
                        nonce: '<?php echo esc_js($nonce); ?>',
                    },
                    success: function(response) {
                        if (response.success) {
                            appendLog($logArea, response.data.message, 'success');
                            if (response.data.log && response.data.log.length > 0) {
                                response.data.log.forEach(msg => appendLog($logArea, msg, 'detail'));
                            }
                            setLogAreaClass($logArea, 'success');
                        } else {
                            appendLog($logArea, `Error: ${response.data}`, 'error');
                            setLogAreaClass($logArea, 'error');
                        }
                        $button.prop('disabled', false).text('Run Keyword Sync');
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        appendLog($logArea, `AJAX Error: ${textStatus} - ${errorThrown}`, 'error');
                        setLogAreaClass($logArea, 'error');
                        $button.prop('disabled', false).text('Run Keyword Sync');
                    }
                });
            });

            // Link Story Images (Batched)
            $('#link-story-images-btn').on('click', function() {
                $('#link-story-images-log').empty(); // Clear previous logs
                runBatchedProcess('link-story-images-btn', 'link-story-images-log', 'link_story_images');
            });

            // Assign Web Image Filenames (Batched)
            $('#assign-filenames-btn').on('click', function() {
                $('#assign-filenames-log').empty(); // Clear previous logs
                runBatchedProcess('assign-filenames-btn', 'assign-filenames-log', 'assign_web_image_filenames');
            });

            // Import Featured Images (Batched)
            $('#import-featured-images-btn').on('click', function() {
                $('#import-featured-images-log').empty(); // Clear previous logs
                runBatchedProcess('import-featured-images-btn', 'import-featured-images-log', 'import_featured_images');
            });
        });
    </script>
    <?php
}

// AJAX handler for Sync Keywords
add_action('wp_ajax_sync_keywords', 'story_meta_association_sync_keywords');
function story_meta_association_sync_keywords() {
    check_ajax_referer('story_meta_association_tools_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have sufficient permissions.');
    }

    $json_file = get_template_directory() . '/json/NEWS_ASSOCIATION_STORY_KEYWORD.json';

    if (!file_exists($json_file)) {
        wp_send_json_error('Data file not found: ' . $json_file);
    }

    $json_data = file_get_contents($json_file);
    $json_data = preg_replace('/^\xEF\xBB\xBF/', '', $json_data);
    $json_data = mb_convert_encoding($json_data, 'UTF-8', 'UTF-8');
    $records = json_decode($json_data, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error('JSON decode error: ' . json_last_error_msg());
    }

    $linked = 0;
    $total_records = count($records);
    $log = [];

    foreach ($records as $index => $pair) {
        $story_id = intval($pair['STORY_ID']);
        $topic_id = intval($pair['KEYWORD_ID']);

        $posts = get_posts([
            'post_type' => 'any',
            'meta_key' => 'id',
            'meta_value' => $story_id,
            'numberposts' => 1,
            'fields' => 'ids',
        ]);

        if (empty($posts)) {
            $log[] = "Record " . ($index + 1) . "/{$total_records}: Story ID {$story_id} not found.";
            continue;
        }
        $post_id = $posts[0];

        $terms = get_terms([
            'taxonomy' => 'topics',
            'hide_empty' => false,
            'meta_query' => [[
                'key' => 'topic_id',
                'value' => $topic_id,
                'compare' => '='
            ]]
        ]);

        if (empty($terms) || is_wp_error($terms)) {
            $log[] = "Record " . ($index + 1) . "/{$total_records}: Topic ID {$topic_id} not found or error: " . (is_wp_error($terms) ? $terms->get_error_message() : 'Unknown error');
            continue;
        }
        $term_id = $terms[0]->term_id;

        $existing_terms = wp_get_object_terms($post_id, 'topics', ['fields' => 'ids']);

        if (!in_array($term_id, $existing_terms)) {
            $existing_terms[] = $term_id;
            wp_set_object_terms($post_id, $existing_terms, 'topics');
            $linked++;
            $log[] = "Record " . ($index + 1) . "/{$total_records}: Linked Story ID {$story_id} to Topic ID {$topic_id} (Post ID: {$post_id}, Term ID: {$term_id}).";
        } else {
            $log[] = "Record " . ($index + 1) . "/{$total_records}: Story ID {$story_id} already linked to Topic ID {$topic_id}.";
        }
    }

    wp_send_json_success([
        'message' => "Topic linking complete. Topics linked to posts: {$linked}",
        'log'     => $log,
    ]);
}

// AJAX handler for Link Story Images
add_action('wp_ajax_link_story_images', 'story_meta_association_link_story_images');
// Registers the AJAX action 'link_story_images' to call the 'story_meta_association_link_story_images' function.
// This allows JavaScript on the frontend (or an admin script) to trigger this PHP function.

function story_meta_association_link_story_images() {
    // Verifies the nonce to protect against CSRF attacks.
    // 'story_meta_association_tools_nonce' is the action name used when creating the nonce,
    // and 'nonce' is the name of the POST variable where the nonce is expected.
    check_ajax_referer('story_meta_association_tools_nonce', 'nonce');

    // Checks if the current user has 'manage_options' capability (typically administrators).
    // If not, it sends a JSON error response and terminates the script, ensuring only authorized users can run this.
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have sufficient permissions.');
    }

    // Defines the path to the JSON file containing the story-image association data.
    // get_template_directory() gets the absolute path to the current theme's directory.
    $json_file = get_template_directory() . '/json/news-image-association.json';

    // Checks if the specified JSON file exists.
    // If not, it sends a JSON error response with the file path and terminates.
    if (!file_exists($json_file)) {
        wp_send_json_error('JSON file not found: ' . $json_file);
    }

    // // Reads the entire content of the JSON file into a string.
    // $json_data = file_get_contents($json_file);
    // // Removes a UTF-8 Byte Order Mark (BOM) if present at the beginning of the string.
    // // BOMs can interfere with json_decode().
    // $json_data = preg_replace('/^\xEF\xBB\xBF/', '', $json_data);
    // // Ensures the data is correctly UTF-8 encoded. While the previous line handles BOM,
    // // this provides a robust conversion, though it might be redundant if the file is already UTF-8.
    // $json_data = mb_convert_encoding($json_data, 'UTF-8', 'UTF-8');

     // Begin development feature: API call
    $api_url = 'https://secure.caes.uga.edu/rest/news/getAssociationStoryImage';
    $decoded_API_response = null; // Initialize to null

    try {
        // Fetch data from the API.
        $response = wp_remote_get($api_url);

        if (is_wp_error($response)) {
            throw new Exception('API Request Failed: ' . $response->get_error_message());
        }

        $raw_JSON = wp_remote_retrieve_body($response);
        $decoded_API_response = json_decode($raw_JSON, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON decode error from API: ' . json_last_error_msg());
        }

        if (!is_array($decoded_API_response)) {
            throw new Exception('Invalid API response format: Expected an array.');
        }

        $records = $decoded_API_response;

    } catch (Exception $e) {
        error_log('News Association Story Image API Error: ' . $e->getMessage());
        wp_send_json_error('API Error for News Association Story Image: ' . $e->getMessage());
    }

    // End development feature: API call




    // --- Start of modifications ---
    // Instead of using echo and die, which break the AJAX response, we'll send a JSON response
    // that includes the $json_data in the log, and mark it as finished so the frontend stops.
    wp_send_json_success([
        'message' => 'Execution stopped at line 338. Contents of $raw_JSON:',
        'log' => [json_encode(json_decode($raw_JSON, true), JSON_PRETTY_PRINT)], // Pretty print the JSON for readability
        'finished' => true, // Mark as finished so the frontend stops polling
        'start' => 0, // Reset start to prevent further batches
    ]);
    // --- End of modifications ---

    // Decodes the JSON string into a PHP associative array.
    // 'true' ensures objects are returned as associative arrays.
    // $records = json_decode($json_data, true);

    // Checks for JSON decoding errors.
    // If an error occurred during decoding, it sends a JSON error response with the error message.
    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error('JSON decode error: ' . json_last_error_msg());
    }

    // Retrieves the 'start' index from the POST request, or defaults to 0.
    // This parameter is used for batch processing to determine which record to start from.
    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $limit = 500; // Batch limit

    $total_records = count($records);
    // Extracts a subset of records for the current batch, starting from '$start' for '$limit' records.
    $batch_records = array_slice($records, $start, $limit);

    $updated = 0; // Counter for records successfully updated in the current batch.
    $log = [];    // Array to store log messages for the current batch.

    // Checks if the current batch of records is empty.
    // This typically means all records have been processed.
    if (empty($batch_records)) {
        // Sends a success JSON response indicating completion.
        wp_send_json_success([
            'message' => "Image linking complete. No more records to process.",
            'finished' => true,
            'log' => $log,
        ]);
    }

    // Loops through each record in the current batch.
    foreach ($batch_records as $index => $record) {
        // Extracts and sanitizes 'STORY_ID' and 'IMAGE_ID' from the current record.
        $story_id = intval($record['STORY_ID']);
        $image_id = intval($record['IMAGE_ID']);
        // Calculates the absolute index of the current record within the total records.
        $current_index = $start + $index;

        // Skips the record if either 'STORY_ID' or 'IMAGE_ID' is missing or invalid (0 after intval).
        if (!$story_id || !$image_id) {
            $log[] = "Record " . ($current_index + 1) . "/{$total_records}: Skipping due to missing Story ID or Image ID.";
            continue; // Move to the next record in the batch.
        }

        // Queries WordPress posts to find a 'post' type post with a custom field 'id' matching '$story_id'.
        // 'numberposts' => 1 ensures only one post is returned, as we expect a unique match.
        // 'fields' => 'ids' returns only the post ID, optimizing the query.
        $posts = get_posts([
            'post_type'  => 'post',
            'meta_key'   => 'id',
            'meta_value' => $story_id,
            'numberposts' => 1,
            'fields'     => 'ids',
        ]);

        // If no post is found for the given 'STORY_ID', logs a message and skips to the next record.
        if (empty($posts)) {
            $log[] = "Record " . ($current_index + 1) . "/{$total_records}: Post with Story ID {$story_id} not found.";
            continue; // Move to the next record in the batch.
        }
        // Retrieves the ID of the found post.
        $post_id = $posts[0];

        // Updates a custom field named 'image_id' for the found post with the '$image_id'.
        // This assumes 'image_id' is a custom field registered, probably by ACF.
        update_field('image_id', $image_id, $post_id);
        $updated++; // Increments the counter for successfully updated records.
        // Logs the successful update.
        $log[] = "Record " . ($current_index + 1) . "/{$total_records}: Updated Post ID {$post_id} with Image ID {$image_id}.";
    }

    // Calculates the starting index for the *next* batch.
    $next_start = $start + count($batch_records);
    // Determines if all records have been processed.
    $finished = ($next_start >= $total_records);

    // Sends a JSON success response with details about the processed batch.
    wp_send_json_success([
        'message' => "Batch processed from index {$start} to " . ($next_start - 1) . ". Total updated in this batch: {$updated}. Total records: {$total_records}", // Summary message.
        'start'    => $next_start, // The starting index for the next AJAX call.
        'finished' => $finished,   // Boolean indicating if all processing is complete.
        'log'      => $log,        // Array of log messages for this batch.
    ]);
}

// AJAX handler for Assign Web Image Filenames
add_action('wp_ajax_assign_web_image_filenames', 'story_meta_association_assign_web_image_filenames');
function story_meta_association_assign_web_image_filenames() {
    check_ajax_referer('story_meta_association_tools_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have sufficient permissions.');
    }

    $json_file = get_template_directory() . '/json/news-image.json';

    if (!file_exists($json_file)) {
        wp_send_json_error('JSON file not found: ' . $json_file);
    }

    $json_data = file_get_contents($json_file);
    $json_data = preg_replace('/^\xEF\xBB\xBF/', '', $json_data);
    $json_data = mb_convert_encoding($json_data, 'UTF-8', 'UTF-8');
    $records = json_decode($json_data, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error('JSON decode error: ' . json_last_error_msg());
    }

    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $limit = 500; // Batch limit

    $total_records = count($records);
    $batch_records = array_slice($records, $start, $limit);

    $updated = 0;
    $log = [];

    if (empty($batch_records)) {
        wp_send_json_success([
            'message' => "Filename assignment complete. No more records to process.",
            'finished' => true,
            'log' => $log,
        ]);
    }

    foreach ($batch_records as $index => $record) {
        $image_id = intval($record['ID']);
        $filename = trim($record['WEB_VERSION_FILE_NAME'] ?? '');
        $current_index = $start + $index;

        if (!$image_id || !$filename) {
            $log[] = "Record " . ($current_index + 1) . "/{$total_records}: Skipping due to missing Image ID or Filename.";
            continue;
        }

        $posts = get_posts([
            'post_type'  => 'post',
            'meta_key'   => 'image_id',
            'meta_value' => $image_id,
            'numberposts' => -1,
            'fields'     => 'ids',
        ]);

        if (empty($posts)) {
            $log[] = "Record " . ($current_index + 1) . "/{$total_records}: No posts found with Image ID {$image_id}.";
            continue;
        }

        foreach ($posts as $post_id) {
            update_field('web_version_file_name', $filename, $post_id);
            $updated++;
            $log[] = "Record " . ($current_index + 1) . "/{$total_records}: Updated Post ID {$post_id} with filename '{$filename}' for Image ID {$image_id}.";
        }
    }

    $next_start = $start + count($batch_records);
    $finished = ($next_start >= $total_records);

    wp_send_json_success([
        'message' => "Batch processed from index {$start} to " . ($next_start - 1) . ". Total posts updated in this batch: {$updated}. Total records: {$total_records}",
        'start'    => $next_start,
        'finished' => $finished,
        'log'      => $log,
    ]);
}

// AJAX handler for Import Featured Images
add_action('wp_ajax_import_featured_images', 'story_meta_association_import_featured_images');
function story_meta_association_import_featured_images() {
    check_ajax_referer('story_meta_association_tools_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have sufficient permissions.');
    }

    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $limit = 100; // Batch limit for posts

    // Get all post IDs only once for consistent batching
    $all_posts = get_transient('story_meta_association_all_post_ids');
    if (false === $all_posts) {
        $all_posts = get_posts([
            'post_type'      => 'post',
            'post_status'    => 'any',
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'posts_per_page' => -1,
        ]);
        set_transient('story_meta_association_all_post_ids', $all_posts, DAY_IN_SECONDS); // Cache for 24 hours
    }

    $total_posts = count($all_posts);
    $batch_posts = array_slice($all_posts, $start, $limit);

    $updated = 0;
    $log = [];

    if (empty($batch_posts)) {
        // Clear the cached post IDs when done
        delete_transient('story_meta_association_all_post_ids');
        wp_send_json_success([
            'message' => "Featured image import complete. No more posts to process.",
            'finished' => true,
            'log' => $log,
        ]);
    }

    // Include necessary WordPress media functions
    if (!function_exists('media_handle_sideload')) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
    }

    foreach ($batch_posts as $index => $post_id) {
        $current_index = $start + $index;

        $image_id  = get_field('image_id', $post_id);
        $filename  = get_field('web_version_file_name', $post_id);

        if (!$image_id || !$filename) {
            $log[] = "Post ID {$post_id} (" . ($current_index + 1) . "/{$total_posts}): Skipping due to missing 'image_id' or 'web_version_file_name'.";
            continue;
        }
        if (has_post_thumbnail($post_id)) {
            $log[] = "Post ID {$post_id} (" . ($current_index + 1) . "/{$total_posts}): Already has a featured image.";
            continue;
        }

        $image_url = "https://secure.caes.uga.edu/news/multimedia/images/{$image_id}/{$filename}";

        $tmp = download_url($image_url);
        if (is_wp_error($tmp)) {
            $log[] = "Post ID {$post_id} (" . ($current_index + 1) . "/{$total_posts}): Failed to download image from '{$image_url}'. Error: " . $tmp->get_error_message();
            continue;
        }

        $file_array = [
            'name'     => basename($filename),
            'tmp_name' => $tmp,
        ];

        $attachment_id = media_handle_sideload($file_array, $post_id);

        if (is_wp_error($attachment_id)) {
            @unlink($tmp); // Clean up temp file
            $log[] = "Post ID {$post_id} (" . ($current_index + 1) . "/{$total_posts}): Failed to sideload image. Error: " . $attachment_id->get_error_message();
            continue;
        }

        set_post_thumbnail($post_id, $attachment_id);
        $updated++;
        $log[] = "Post ID {$post_id} (" . ($current_index + 1) . "/{$total_posts}): Featured image assigned from '{$image_url}'. Attachment ID: {$attachment_id}.";
    }

    $next_start = $start + count($batch_posts);
    $finished = ($next_start >= $total_posts);

    wp_send_json_success([
        'message' => "Processed posts {$start} to " . ($next_start - 1) . ". Featured images assigned in this batch: {$updated}. Total posts: {$total_posts}",
        'start'    => $next_start,
        'finished' => $finished,
        'log'      => $log,
    ]);
}