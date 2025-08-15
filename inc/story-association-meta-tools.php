<?php

// Remove the enqueue script action, as JS will be inline
// This line might not be strictly necessary if you never had a function
// named 'story_meta_association_tools_enqueue_scripts' hooked to admin_enqueue_scripts,
// but it's harmless to keep if you previously tested with it.
remove_action('admin_enqueue_scripts', 'story_meta_association_tools_enqueue_scripts');

// Add admin menu page
add_action('admin_menu', 'story_meta_association_tools_menu_page');
function story_meta_association_tools_menu_page()
{
    add_submenu_page(
        'caes-tools',                     // Parent slug - points to CAES Tools
        'Story Meta Association Tools',   // Page title
        'Story Meta Tools',              // Menu title
        'manage_options',
        'story-meta-association-tools',
        'story_meta_association_tools_render_page'
    );
}

// Render the admin page content with inline JavaScript
function story_meta_association_tools_render_page()
{
    // Generate nonce here for use in JS
    $nonce = wp_create_nonce('story_meta_association_tools_nonce');
    $ajax_url = admin_url('admin-ajax.php');
?>
    <div class="wrap">
        <h1>Story Meta Association Tools</h1>
        <p>Use the buttons below to run various meta-data association processes for your news stories and publications.</p>

        <hr>

        <h2>News Stories</h2>

        <h3>1. Sync Keywords to News Stories</h3>
        <p>Accesses an API and associates keywords (topics) with posts based on story and keyword IDs. This process runs in batches of 10 records.</p>
        <button class="button button-primary" id="sync-keywords-btn">Run Keyword Sync</button>
        <div id="sync-keywords-log" class="log-area"></div>

        <hr>

        <h3>2. Link Story Images</h3>
        <p>Accesses an API and links images to posts via the 'image_id' ACF field. This process runs in batches of 500 records.</p>
        <button class="button button-primary" id="link-story-images-btn">Run Image Linking</button>
        <div id="link-story-images-log" class="log-area"></div>

        <hr>

        <h3>3. Assign Web Image Filenames</h3>
        <p>Reads `news-image.json` and assigns web version filenames to posts that have a matching 'image_id' ACF field. This process runs in batches of 500 records.</p>
        <button class="button button-primary" id="assign-filenames-btn">Run Filename Assignment</button>
        <div id="assign-filenames-log" class="log-area"></div>

        <hr>

        <h3>4. Import Featured Images</h3>
        <p>Downloads and sets featured images for posts based on 'image_id' and 'web_version_file_name' ACF fields. This process runs in batches of 100 posts.</p>
        <button class="button button-primary" id="import-featured-images-btn">Run Featured Image Import</button>
        <div id="import-featured-images-log" class="log-area"></div>

        <hr>

        <h2>Publications</h2>

        <h3>5. Sync Keywords to Publications</h3>
        <p>Accesses an API and associates keywords (topics) with publication posts based on publication and keyword IDs. This process runs in batches of 10 records.</p>
        <button class="button button-primary" id="sync-pub-keywords-btn">Run Publication Keyword Sync</button>
        <div id="sync-pub-keywords-log" class="log-area"></div>

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

        .log-area.success {
            background-color: #e6ffe6;
            border-color: #00cc00;
        }

        .log-area.error {
            background-color: #ffe6e6;
            border-color: #cc0000;
        }

        .log-area.info {
            background-color: #e6f7ff;
            border-color: #0099ff;
        }

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
            // Updated event listener for Sync Keywords to News Stories (replace the existing one)
            $('#sync-keywords-btn').on('click', function() {
                $('#sync-keywords-log').empty(); // Clear previous logs
                runBatchedProcess('sync-keywords-btn', 'sync-keywords-log', 'sync_keywords');
            });

            // Sync Keywords to Publications (Batched)
            $('#sync-pub-keywords-btn').on('click', function() {
                $('#sync-pub-keywords-log').empty(); // Clear previous logs
                runBatchedProcess('sync-pub-keywords-btn', 'sync-pub-keywords-log', 'sync_publication_keywords');
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
function story_meta_association_sync_keywords()
{
    check_ajax_referer('story_meta_association_tools_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have sufficient permissions.');
    }

    // Get cached API data or fetch it
    $transient_key = 'story_keywords_api_data';
    $records = get_transient($transient_key);

    if (false === $records) {
        // Begin API call
        $api_url = 'https://secure.caes.uga.edu/rest/news/getAssociationStoryKeyword';
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

            // Cache for 1 hour
            set_transient($transient_key, $records, HOUR_IN_SECONDS);
        } catch (Exception $e) {
            error_log('News Association Story Keyword API Error: ' . $e->getMessage());
            wp_send_json_error('API Error for News Association Story Keyword: ' . $e->getMessage());
        }
        // End API call
    }

    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $limit = 10; // Batch limit

    $total_records = count($records);
    $batch_records = array_slice($records, $start, $limit);

    $linked = 0;
    $log = [];

    if (empty($batch_records)) {
        // Clear the cached API data when done
        delete_transient($transient_key);
        wp_send_json_success([
            'message' => "Keyword linking complete. No more records to process.",
            'finished' => true,
            'log' => $log,
        ]);
    }

    foreach ($batch_records as $index => $pair) {
        $story_id = intval($pair['STORY_ID']);
        $topic_id = intval($pair['KEYWORD_ID']);
        $current_index = $start + $index;

        $posts = get_posts([
            'post_type' => 'post',
            'meta_key' => 'id',
            'meta_value' => $story_id,
            'numberposts' => 1,
            'fields' => 'ids',
            'post_status' => 'any'
        ]);

        if (empty($posts)) {
            $log[] = "Record " . ($current_index + 1) . "/{$total_records}: Story ID {$story_id} not found.";
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
            $log[] = "Record " . ($current_index + 1) . "/{$total_records}: Topic ID {$topic_id} not found or error: " . (is_wp_error($terms) ? $terms->get_error_message() : 'Unknown error');
            continue;
        }
        $term_id = $terms[0]->term_id;

        $existing_terms = wp_get_object_terms($post_id, 'topics', ['fields' => 'ids']);

        if (!in_array($term_id, $existing_terms)) {
            $existing_terms[] = $term_id;
            wp_set_object_terms($post_id, $existing_terms, 'topics');
            $linked++;
            $log[] = "Record " . ($current_index + 1) . "/{$total_records}: Linked Story ID {$story_id} to Topic ID {$topic_id} (Post ID: {$post_id}, Term ID: {$term_id}).";
        } else {
            $log[] = "Record " . ($current_index + 1) . "/{$total_records}: Story ID {$story_id} already linked to Topic ID {$topic_id}.";
        }
    }

    $next_start = $start + count($batch_records);
    $finished = ($next_start >= $total_records);

    wp_send_json_success([
        'message' => "Batch processed from index {$start} to " . ($next_start - 1) . ". Topics linked in this batch: {$linked}. Total records: {$total_records}",
        'start'    => $next_start,
        'finished' => $finished,
        'log'      => $log,
    ]);
}

// AJAX handler for Sync Publication Keywords
add_action('wp_ajax_sync_publication_keywords', 'story_meta_association_sync_publication_keywords');
function story_meta_association_sync_publication_keywords()
{
    check_ajax_referer('story_meta_association_tools_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have sufficient permissions.');
    }

    // Get cached API data or fetch it
    $transient_key = 'pub_keywords_api_data';
    $records = get_transient($transient_key);

    if (false === $records) {
        // Begin API call
        $api_url = 'https://secure.caes.uga.edu/rest/publications/getPubsKeywordAssociations';

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

            // Cache for 1 hour
            set_transient($transient_key, $records, HOUR_IN_SECONDS);
        } catch (Exception $e) {
            error_log('Publications Keyword Association API Error: ' . $e->getMessage());
            wp_send_json_error('API Error for Publications Keyword Association: ' . $e->getMessage());
        }
    }

    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $limit = 10; // Batch limit

    $total_records = count($records);
    $batch_records = array_slice($records, $start, $limit);

    $linked = 0;
    $log = [];

    if (empty($batch_records)) {
        // Clear the cached API data when done
        delete_transient($transient_key);
        wp_send_json_success([
            'message' => "Publication keyword linking complete. No more records to process.",
            'finished' => true,
            'log' => $log,
        ]);
    }

    foreach ($batch_records as $index => $pair) {
        $publication_id = intval($pair['PUBLICATION_ID']);
        $keyword_id = intval($pair['KEYWORD_ID']);
        $keyword_label = trim($pair['KEYWORD_LABEL'] ?? '');
        $current_index = $start + $index;

        $posts = get_posts([
            'post_type' => 'publications',
            'meta_key' => 'publication_id',
            'meta_value' => $publication_id,
            'numberposts' => 1,
            'fields' => 'ids',
            'post_status' => 'any'
        ]);

        if (empty($posts)) {
            $log[] = "Record " . ($current_index + 1) . "/{$total_records}: Publication ID {$publication_id} not found.";
            continue;
        }
        $post_id = $posts[0];

        $terms = get_terms([
            'taxonomy' => 'topics',
            'hide_empty' => false,
            'meta_query' => [[
                'key' => 'topic_id',
                'value' => $keyword_id,
                'compare' => '='
            ]]
        ]);

        if (empty($terms) || is_wp_error($terms)) {
            $log[] = "Record " . ($current_index + 1) . "/{$total_records}: Topic ID {$keyword_id} ('{$keyword_label}') not found or error: " . (is_wp_error($terms) ? $terms->get_error_message() : 'Unknown error');
            continue;
        }
        $term_id = $terms[0]->term_id;

        $existing_terms = wp_get_object_terms($post_id, 'topics', ['fields' => 'ids']);

        if (!in_array($term_id, $existing_terms)) {
            $existing_terms[] = $term_id;
            wp_set_object_terms($post_id, $existing_terms, 'topics');
            $linked++;
            $log[] = "Record " . ($current_index + 1) . "/{$total_records}: Linked Publication ID {$publication_id} to Topic '{$keyword_label}' (ID: {$keyword_id}) - Post ID: {$post_id}, Term ID: {$term_id}.";
        } else {
            $log[] = "Record " . ($current_index + 1) . "/{$total_records}: Publication ID {$publication_id} already linked to Topic '{$keyword_label}' (ID: {$keyword_id}).";
        }
    }

    $next_start = $start + count($batch_records);
    $finished = ($next_start >= $total_records);

    wp_send_json_success([
        'message' => "Batch processed from index {$start} to " . ($next_start - 1) . ". Topics linked in this batch: {$linked}. Total records: {$total_records}",
        'start'    => $next_start,
        'finished' => $finished,
        'log'      => $log,
    ]);
}

// AJAX handler for Link Story Images - OPTIMIZED VERSION
add_action('wp_ajax_link_story_images', 'story_meta_association_link_story_images');
function story_meta_association_link_story_images()
{
    check_ajax_referer('story_meta_association_tools_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have sufficient permissions.');
    }

    // Get cached API data or fetch it
    $transient_key = 'story_images_api_data';
    $api_records = get_transient($transient_key);

    if (false === $api_records) {
        $api_url = 'https://secure.caes.uga.edu/rest/news/getAssociationStoryImage';

        try {
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

            $api_records = $decoded_API_response;
            
            // Cache for 1 hour
            set_transient($transient_key, $api_records, HOUR_IN_SECONDS);
        } catch (Exception $e) {
            error_log('News Association Story Image API Error: ' . $e->getMessage());
            wp_send_json_error('API Error for News Association Story Image: ' . $e->getMessage());
        }
    }

    // Convert API data to lookup array for faster searching
    $transient_lookup_key = 'story_images_lookup_data';
    $image_lookup = get_transient($transient_lookup_key);
    
    if (false === $image_lookup) {
        $image_lookup = [];
        foreach ($api_records as $record) {
            $story_id = intval($record['STORY_ID']);
            $image_id = intval($record['IMAGE_ID']);
            if ($story_id && $image_id) {
                $image_lookup[$story_id] = $image_id;
            }
        }
        set_transient($transient_lookup_key, $image_lookup, HOUR_IN_SECONDS);
    }

    // Get posts that need image_id updates (cached)
    $transient_posts_key = 'posts_needing_image_ids';
    $posts_needing_updates = get_transient($transient_posts_key);
    
    if (false === $posts_needing_updates) {
        // Find all posts that don't have image_id set or have it empty
        $posts_needing_updates = get_posts([
            'post_type'      => 'post',
            'post_status'    => 'any',
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => 'image_id',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key'     => 'image_id',
                    'value'   => '',
                    'compare' => '='
                ],
                [
                    'key'     => 'image_id',
                    'value'   => '0',
                    'compare' => '='
                ]
            ]
        ]);
        
        // Cache for 1 hour
        set_transient($transient_posts_key, $posts_needing_updates, HOUR_IN_SECONDS);
    }

    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $limit = 50; // Smaller batch size for better performance

    $total_posts = count($posts_needing_updates);
    $batch_posts = array_slice($posts_needing_updates, $start, $limit);

    $updated = 0;
    $log = [];

    if (empty($batch_posts)) {
        // Clear all cached data when done
        delete_transient($transient_key);
        delete_transient($transient_lookup_key);
        delete_transient($transient_posts_key);
        
        wp_send_json_success([
            'message' => "Image linking complete. No more posts to process.",
            'finished' => true,
            'log' => $log,
        ]);
    }

    foreach ($batch_posts as $index => $post_id) {
        $current_index = $start + $index;
        
        // Get the story ID for this post
        $story_id = get_field('id', $post_id);
        
        if (!$story_id) {
            $log[] = "Post " . ($current_index + 1) . "/{$total_posts}: Post ID {$post_id} has no 'id' field.";
            continue;
        }

        // Look up image_id for this story_id
        if (isset($image_lookup[$story_id])) {
            $image_id = $image_lookup[$story_id];
            
            update_field('image_id', $image_id, $post_id);
            $updated++;
            $log[] = "Post " . ($current_index + 1) . "/{$total_posts}: Updated Post ID {$post_id} (Story ID: {$story_id}) with Image ID {$image_id}.";
        } else {
            $log[] = "Post " . ($current_index + 1) . "/{$total_posts}: No image found for Post ID {$post_id} (Story ID: {$story_id}).";
        }
    }

    $next_start = $start + count($batch_posts);
    $finished = ($next_start >= $total_posts);

    wp_send_json_success([
        'message' => "Batch processed from post {$start} to " . ($next_start - 1) . ". Total updated in this batch: {$updated}. Total posts needing updates: {$total_posts}",
        'start'    => $next_start,
        'finished' => $finished,
        'log'      => $log,
    ]);
}

// AJAX handler for Assign Web Image Filenames - OPTIMIZED VERSION
add_action('wp_ajax_assign_web_image_filenames', 'story_meta_association_assign_web_image_filenames');
function story_meta_association_assign_web_image_filenames()
{
    check_ajax_referer('story_meta_association_tools_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have sufficient permissions.');
    }

    // Get cached API data or fetch it
    $transient_key = 'image_filenames_api_data';
    $api_records = get_transient($transient_key);

    if (false === $api_records) {
        // Begin API call (replaces JSON ingestion)
        $api_url = 'https://secure.caes.uga.edu/rest/news/getImage';
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

            $api_records = $decoded_API_response;
            
            // Cache for 1 hour
            set_transient($transient_key, $api_records, HOUR_IN_SECONDS);
        } catch (Exception $e) {
            error_log('News Image API Error: ' . $e->getMessage());
            wp_send_json_error('API Error for News Image: ' . $e->getMessage());
        }
        // End API call
    }

    // Convert API data to lookup array for faster searching
    $transient_lookup_key = 'image_filenames_lookup_data';
    $filename_lookup = get_transient($transient_lookup_key);
    
    if (false === $filename_lookup) {
        $filename_lookup = [];
        foreach ($api_records as $record) {
            $image_id = intval($record['ID']);
            $filename = trim($record['WEB_VERSION_FILE_NAME'] ?? '');
            if ($image_id && $filename) {
                $filename_lookup[$image_id] = $filename;
            }
        }
        set_transient($transient_lookup_key, $filename_lookup, HOUR_IN_SECONDS);
    }

    // Get posts that need web_version_file_name updates (cached)
    $transient_posts_key = 'posts_needing_filenames';
    $posts_needing_updates = get_transient($transient_posts_key);
    
    if (false === $posts_needing_updates) {
        // Find all posts that have image_id but don't have web_version_file_name set or have it empty
        $posts_needing_updates = get_posts([
            'post_type'      => 'post',
            'post_status'    => 'any', // Ensures we check all post statuses, not just 'publish'.
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => 'image_id',
                    'compare' => 'EXISTS'
                ],
                [
                    'key'     => 'image_id',
                    'value'   => '',
                    'compare' => '!='
                ],
                [
                    'key'     => 'image_id',
                    'value'   => '0',
                    'compare' => '!='
                ],
                [
                    'relation' => 'OR',
                    [
                        'key'     => 'web_version_file_name',
                        'compare' => 'NOT EXISTS'
                    ],
                    [
                        'key'     => 'web_version_file_name',
                        'value'   => '',
                        'compare' => '='
                    ]
                ]
            ]
        ]);
        
        // Cache for 1 hour
        set_transient($transient_posts_key, $posts_needing_updates, HOUR_IN_SECONDS);
    }

    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $limit = 50; // Smaller batch limit

    $total_posts = count($posts_needing_updates);
    $batch_posts = array_slice($posts_needing_updates, $start, $limit);

    $updated = 0;
    $log = [];

    if (empty($batch_posts)) {
        // Clear all cached data when done
        delete_transient($transient_key);
        delete_transient($transient_lookup_key);
        delete_transient($transient_posts_key);
        
        wp_send_json_success([
            'message' => "Filename assignment complete. No more posts to process.",
            'finished' => true,
            'log' => $log,
        ]);
    }

    foreach ($batch_posts as $index => $post_id) {
        $current_index = $start + $index;
        
        // Get the image_id for this post
        $image_id = get_field('image_id', $post_id);
        
        if (!$image_id) {
            $log[] = "Post " . ($current_index + 1) . "/{$total_posts}: Post ID {$post_id} has no 'image_id' field.";
            continue;
        }

        // Look up filename for this image_id
        if (isset($filename_lookup[$image_id])) {
            $filename = $filename_lookup[$image_id];
            
            update_field('web_version_file_name', $filename, $post_id);
            $updated++;
            $log[] = "Post " . ($current_index + 1) . "/{$total_posts}: Updated Post ID {$post_id} with filename '{$filename}' for Image ID {$image_id}.";
        } else {
            $log[] = "Post " . ($current_index + 1) . "/{$total_posts}: No filename found for Post ID {$post_id} (Image ID: {$image_id}).";
        }
    }

    $next_start = $start + count($batch_posts);
    $finished = ($next_start >= $total_posts);

    wp_send_json_success([
        'message' => "Batch processed from post {$start} to " . ($next_start - 1) . ". Total posts updated in this batch: {$updated}. Total posts needing updates: {$total_posts}",
        'start'    => $next_start,
        'finished' => $finished,
        'log'      => $log,
    ]);
}

// AJAX handler for Import Featured Images - OPTIMIZED VERSION
add_action('wp_ajax_import_featured_images', 'story_meta_association_import_featured_images');
function story_meta_association_import_featured_images()
{
    check_ajax_referer('story_meta_association_tools_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have sufficient permissions.');
    }

    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $limit = 25; // Smaller batch limit for posts since image downloading is intensive

    // Get posts that need featured images (cached)
    $transient_posts_key = 'posts_needing_featured_images';
    $posts_needing_images = get_transient($transient_posts_key);
    
    if (false === $posts_needing_images) {
        // Find posts that have both image_id and web_version_file_name but don't have featured images
        $all_posts_with_meta = get_posts([
            'post_type'      => 'post',
            'post_status'    => 'any',
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => 'image_id',
                    'compare' => 'EXISTS'
                ],
                [
                    'key'     => 'image_id',
                    'value'   => '',
                    'compare' => '!='
                ],
                [
                    'key'     => 'image_id',
                    'value'   => '0',
                    'compare' => '!='
                ],
                [
                    'key'     => 'web_version_file_name',
                    'compare' => 'EXISTS'
                ],
                [
                    'key'     => 'web_version_file_name',
                    'value'   => '',
                    'compare' => '!='
                ]
            ]
        ]);
        
        // Filter out posts that already have featured images
        $posts_needing_images = [];
        foreach ($all_posts_with_meta as $post_id) {
            if (!has_post_thumbnail($post_id)) {
                $posts_needing_images[] = $post_id;
            }
        }
        
        // Cache for 24 hours
        set_transient($transient_posts_key, $posts_needing_images, DAY_IN_SECONDS);
    }

    $total_posts = count($posts_needing_images);
    $batch_posts = array_slice($posts_needing_images, $start, $limit);

    $updated = 0;
    $log = [];

    if (empty($batch_posts)) {
        // Clear the cached post IDs when done
        delete_transient($transient_posts_key);
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
        'message' => "Processed posts {$start} to " . ($next_start - 1) . ". Featured images assigned in this batch: {$updated}. Total posts needing images: {$total_posts}",
        'start'    => $next_start,
        'finished' => $finished,
        'log'      => $log,
    ]);
}