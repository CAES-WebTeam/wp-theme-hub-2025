<?php

// Load ACF Field Groups
//include_once( get_template_directory() . '/inc/acf-fields/publications-field-group.php' );

// Set ACF field 'state_issue' with options from json
function populate_acf_state_issue_field($field)
{
    // Set path to json file
    $json_file = get_template_directory() . '/json/publication-state-issue.json';

    if (file_exists($json_file)) {
        // Get the contents of the json file
        $json_data = file_get_contents($json_file);
        $issues = json_decode($json_data, true);

        // Clear existing choices
        $field['choices'] = array();

        // Check if there are issues in the json
        if (isset($issues['issues']) && is_array($issues['issues'])) {
            // Loop through the issues and add each name as a select option
            foreach ($issues['issues'] as $issue) {
                if (isset($issue['name'])) {
                    $field['choices'][sanitize_text_field($issue['name'])] = sanitize_text_field($issue['name']);
                }
            }
        }
    }

    // Return the field to ACF
    return $field;
}
add_filter('acf/load_field/name=state_issue', 'populate_acf_state_issue_field');

// ===================
// PUBLICATION SUNSET DATE UNPUBLISHING
// ===================

// Schedule the cron job for sunsetting publications
add_action('wp', function () {
    if (! wp_next_scheduled('unpublish_expired_publications')) {
        wp_schedule_event(time(), 'daily', 'unpublish_expired_publications');
    }
});

add_action('unpublish_expired_publications', 'unpublish_expired_publications_callback');
function unpublish_expired_publications_callback()
{
    // Get today's date in Ymd format
    $today = date('Ymd');

    // Query publications with sunset_date on or before today
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
            // --- ADDED SAFETY CHECK ---
            // Before unpublishing, double-check that the sunset date meta field is not empty.
            // This prevents errors if a post is somehow included in the query incorrectly.
            $sunset_date = get_post_meta($post->ID, 'sunset_date', true);
            if (empty($sunset_date)) {
                // Log an error if this happens, as it would be unexpected behavior.
                error_log("CRON ANOMALY: Post ID {$post->ID} was targeted for unpublishing but has no sunset date.");
                continue; // Skip to the next post.
            }
            // --- END SAFETY CHECK ---

            // Unpublish the post by setting its status to 'draft'
            wp_update_post([
                'ID'          => $post->ID,
                'post_status' => 'draft',
            ]);
        }
    }

    wp_reset_postdata();
}

// Clear scheduled event on theme deactivation
register_deactivation_hook(__FILE__, function () {
    $timestamp = wp_next_scheduled('unpublish_expired_publications');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'unpublish_expired_publications');
    }
});

// ===================
// HELPERS FOR IMPORT
// ===================

function clean_wysiwyg_content($content)
{
    if (empty($content)) {
        return $content;
    }

    // Clean unwanted characters
    $content = str_replace(["\r\n", "\r", '&#13;', '&#013;', '&amp;#13;', '&#x0D;', '&#x0d;'], '', $content);

    // Fix escaped forward slashes from JSON
    $content = str_replace(['<\/'], ['</'], $content);

    // Convert HTML entities
    $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Remove empty paragraphs
    $content = preg_replace('/<p>\s*<\/p>/', '', $content);

    // Trim whitespace
    $content = trim($content);

    return $content;
}

// ===================
// PUBLICATIONS IMPORT ACTIONS
// ===================

add_action('pmxi_saved_post', function ($post_id, $xml, $is_update) {
    if (get_post_type($post_id) !== 'publications') return;

    // Authors Repeater
    $raw_data = get_field('raw_author_ids', $post_id);
    $repeater = [];
    $lead_author_user_id = null;

    if (!empty($raw_data)) {
        $rows = explode('|', rtrim($raw_data, '|'));

        foreach ($rows as $row) {
            $row = trim($row);
            if (empty($row)) continue;

            $json_str = '{' . $row . '}';
            $data = json_decode($json_str, true);
            if (!isset($data['college_id'])) continue;

            $cid = trim($data['college_id']);
            $is_lead = !empty($data['lead']) && $data['lead'] == '1';
            $is_co = !empty($data['co']) && $data['co'] == '1';

            $users = get_users([
                'meta_key' => 'college_id',
                'meta_value' => $cid,
                'number' => 1
            ]);

            if (!empty($users)) {
                $user_id = $users[0]->ID;

                if ($is_lead && !$lead_author_user_id) {
                    $lead_author_user_id = $user_id;
                }

                $repeater[] = [
                    'user' => $user_id,
                    'lead_author' => $is_lead,
                    'co_author' => $is_co,
                ];
            }
        }

        if (!empty($repeater)) update_field('authors', $repeater, $post_id);
        if ($lead_author_user_id) wp_update_post(['ID' => $post_id, 'post_author' => $lead_author_user_id]);
    }

    // Clean Content and Replace Inline Images
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $original_content = get_post_field('post_content', $post_id);
    $content = $original_content;

    // Clean unwanted characters from main content using the same function
    $content = clean_wysiwyg_content($content);

    // Then you could use it for multiple fields like this:

    $fields_to_clean = ['summary']; // Add your field names here
    foreach ($fields_to_clean as $field_name) {
        $field_value = get_field($field_name, $post_id);
        if (!empty($field_value)) {
            $cleaned_value = clean_wysiwyg_content($field_value);
            if ($cleaned_value !== $field_value) {
                update_field($field_name, $cleaned_value, $post_id);
            }
        }
    }


    // Replace inline images
    preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches);
    if (!empty($matches[1])) {
        foreach ($matches[1] as $img_url) {
            if (strpos($img_url, home_url()) !== false || strpos($img_url, '/wp-content/uploads/') !== false) continue;

            $tmp = download_url($img_url);
            if (is_wp_error($tmp)) continue;

            $file_array = [
                'name'     => basename(parse_url($img_url, PHP_URL_PATH)),
                'tmp_name' => $tmp,
            ];

            $attachment_id = media_handle_sideload($file_array, $post_id);
            if (is_wp_error($attachment_id)) {
                @unlink($tmp);
                continue;
            }

            $new_url = wp_get_attachment_url($attachment_id);
            if ($new_url) $content = str_replace($img_url, $new_url, $content);
        }
    }

    if ($content !== $original_content) {
        wp_update_post(['ID' => $post_id, 'post_content' => $content]);
    }

    // --- Type Field Injection into Repeater Fields ---
    $authors = get_field('authors', $post_id);
    if (is_array($authors)) {
        foreach ($authors as &$row) $row['type'] = 'User';
        update_field('authors', $authors, $post_id);
    }

    $experts = get_field('experts', $post_id);
    if (is_array($experts)) {
        foreach ($experts as &$row) $row['type'] = 'User';
        update_field('experts', $experts, $post_id);
    }

    //  Assign Keywords from API 
    static $keyword_map = null;
    if ($keyword_map === null) {

        // $json_path = get_stylesheet_directory() . '/json/pub-keywords.json';

        // if (file_exists($json_path)) {
        //     $json_data = file_get_contents($json_path);
        //     $json_data = preg_replace('/^\xEF\xBB\xBF/', '', $json_data);
        //     $json_data = mb_convert_encoding($json_data, 'UTF-8', 'UTF-8');
        //     $pairs = json_decode($json_data, true);
        //     if (is_array($pairs)) {
        //         foreach ($pairs as $pair) {
        //             $pub_id = $pair['PUBLICATION_ID'] ?? null;
        //             $kw_id  = $pair['KEYWORD_ID'] ?? null;
        //             if ($pub_id && $kw_id) {
        //                 $keyword_map[$pub_id][] = $kw_id;
        //             }
        //         }
        //     }
        // }

        $api_url = 'https://secure.caes.uga.edu/rest/publications/getKeywords';
        $json_data = null; // Initialize to null

        $response = wp_remote_get($api_url);
        $keyword_map = [];

        if (!is_wp_error($response)) {

            $json_data = wp_remote_retrieve_body($response);
            $json_data = preg_replace('/^\xEF\xBB\xBF/', '', $json_data);
            $json_data = mb_convert_encoding($json_data, 'UTF-8', 'UTF-8');
            $pairs = json_decode($json_data, true);

            if (is_array($pairs)) {
                foreach ($pairs as $pair) {
                    $pub_id = $pair['PUBLICATION_ID'] ?? null;
                    $kw_id  = $pair['KEYWORD_ID'] ?? null;
                    if ($pub_id && $kw_id) {
                        $keyword_map[$pub_id][] = $kw_id;
                    }
                }
            }
        }
    }

    $pub_id = get_field('publication_id', $post_id);
    if ($pub_id && !empty($keyword_map[$pub_id])) {
        foreach ($keyword_map[$pub_id] as $kw_id) {
            $terms = get_terms([
                'taxonomy' => 'topics',
                'hide_empty' => false,
                'meta_query' => [
                    ['key' => 'topic_id', 'value' => $kw_id]
                ]
            ]);
            if (!empty($terms) && !is_wp_error($terms)) {
                wp_set_object_terms($post_id, intval($terms[0]->term_id), 'topics', true);
            }
        }
    }
}, 10, 3);


function clean_html($html)
{
    $html = preg_replace('/\r\n|\n|\r/', '', $html); // Remove newlines
    return trim($html);
}


// Set Image URL for thumbnail
function get_full_image_url($relative_path)
{
    $base_url = "https://secure.caes.uga.edu/extension/publications/images/thumbnail-pub-images/";
    $relative_path = ltrim($relative_path, '/');
    $full_url = $base_url . '/' . $relative_path;

    // Check if the image exists
    $headers = @get_headers($full_url);
    if ($headers && strpos($headers[0], '200')) {
        return $full_url; // Return valid URL
    } else {
        return ''; // Return empty to avoid import errors
    }
}

// ===================
// QUERY VARS SECTION
// ===================

/**
 * Allow a custom query var for publications.
 */
function custom_publications_query_vars($query_vars)
{
    $query_vars[] = 'publication_number';
    return $query_vars;
}
add_filter('query_vars', 'custom_publications_query_vars');

// ===================
// QUERY PARSING SECTION
// ===================

// Handle the query for post-type specific topic archives
function handle_topic_archive_query($query)
{
    if (!is_admin() && $query->is_main_query()) {
        // Check if this is a topic archive with post_type specified
        if (get_query_var('topics') && get_query_var('post_type')) {
            $post_type = get_query_var('post_type');

            // Handle the special case where post_type=post for news URLs
            if ($post_type === 'news') {
                $post_type = 'post';
            }

            $query->set('post_type', $post_type);
            $query->is_tax = true;
            $query->is_archive = true;
        }
    }
}
add_action('pre_get_posts', 'handle_topic_archive_query');

/**
 * Modify the query for publications if a custom publication_number query variable is present.
 */
function custom_publications_parse_request($query)
{
    if (!is_admin() && isset($query->query_vars['publication_number'])) {
        $publication_number = sanitize_title($query->query_vars['publication_number']);
        $query->set('meta_query', array(
            array(
                'key'     => 'publication_number',
                'value'   => $publication_number,
                'compare' => '='
            )
        ));
    }
}
add_action('pre_get_posts', 'custom_publications_parse_request');

// ===================
// CLONING FOR REVIEW SECTION
// ===================

// Add "Clone for Review" link to Publications row actions
add_filter('post_row_actions', function ($actions, $post) {
    if ($post->post_type === 'publications') {
        $url = wp_nonce_url(admin_url('admin-post.php?action=clone_publication_for_review&post_id=' . $post->ID), 'clone_publication_' . $post->ID);
        $actions['clone_review'] = '<a href="' . esc_url($url) . '">Clone for Review</a>';
    }
    return $actions;
}, 10, 2);

// Handle cloning the post and ACF fields
add_action('admin_post_clone_publication_for_review', function () {
    if (!current_user_can('edit_posts')) wp_die('Unauthorized');

    $post_id = intval($_GET['post_id']);
    check_admin_referer('clone_publication_' . $post_id);

    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'publications') wp_die('Invalid post');

    // Clone post without "(In Review)" in the title
    $new_post_id = wp_insert_post([
        'post_title'   => $post->post_title,
        'post_content' => $post->post_content,
        'post_type'    => 'publications',
        'post_status'  => 'draft',
        'post_author'  => get_current_user_id(),
        'meta_input'   => [
            'original_publication_id' => $post_id,
        ],
    ]);

    // Copy all ACF fields
    $fields = get_fields($post_id);
    if ($fields && is_array($fields)) {
        foreach ($fields as $key => $value) {
            update_field($key, $value, $new_post_id);
        }
    }

    wp_redirect(admin_url('post.php?post=' . $new_post_id . '&action=edit'));
    exit;
});

// Add "Replace Original" button in Classic Editor (fallback)
add_action('post_submitbox_misc_actions', function () {
    global $post;
    if ($post->post_type === 'publications' && $post->post_status === 'draft') {
        $original_id = get_post_meta($post->ID, 'original_publication_id', true);
        if ($original_id) {
            $url = wp_nonce_url(admin_url('admin-post.php?action=publish_review_copy&draft_id=' . $post->ID), 'publish_review_' . $post->ID);
            echo '<div class="misc-pub-section"><a href="' . esc_url($url) . '" class="button">Replace Original with Review</a></div>';
        }
    }
});

// Handle publishing the review version
add_action('admin_post_publish_review_copy', function () {
    if (!current_user_can('edit_posts')) wp_die('Unauthorized');

    $draft_id = intval($_GET['draft_id']);
    check_admin_referer('publish_review_' . $draft_id);

    $draft = get_post($draft_id);
    $original_id = get_post_meta($draft_id, 'original_publication_id', true);
    if (!$draft || !$original_id) wp_die('Missing data');

    // Update original post title/content
    wp_update_post([
        'ID'           => $original_id,
        'post_title'   => $draft->post_title,
        'post_content' => $draft->post_content,
    ]);

    // Copy all ACF fields back to original
    $fields = get_fields($draft_id);
    if ($fields && is_array($fields)) {
        foreach ($fields as $key => $value) {
            update_field($key, $value, $original_id);
        }
    }

    // Delete the draft
    wp_delete_post($draft_id, true);

    wp_redirect(admin_url('post.php?post=' . $original_id . '&action=edit&replaced=1'));
    exit;
});

// Enqueue editor script and hide default publish button for review drafts
add_action('enqueue_block_editor_assets', function () {
    global $post;

    // Only load for publications in the Block Editor
    if (!isset($post->post_type) || $post->post_type !== 'publications') return;

    $original_id = get_post_meta($post->ID, 'original_publication_id', true);
    if ($post->post_status !== 'draft' || !$original_id) return;

    // Load the JS for the custom sidebar button
    wp_enqueue_script(
        'pub-review-button',
        get_stylesheet_directory_uri() . '/src/js/publication-review.js',
        ['wp-edit-post', 'wp-plugins', 'wp-element', 'wp-components'],
        null,
        true
    );

    wp_localize_script('pub-review-button', 'pubReviewData', [
        'draftId' => $post->ID,
        'originalId' => $original_id,
        'nonce' => wp_create_nonce('publish_review_' . $post->ID),
        'url' => admin_url('admin-post.php?action=publish_review_copy&draft_id=' . $post->ID),
    ]);
});

// ===================
// SUBTITLE FOR PUB TITLES
// ===================

// Add subtitle to publications title if it is used
function append_subtitle_to_title($title, $id)
{
    if (is_admin()) {
        return $title;
    }
    if (get_post_type($id) === 'publications') {
        $subtitle = get_post_meta($id, 'subtitle', true);
        if (!empty($subtitle) && is_singular('publications')) {
            $title .= ': <br/><span style="font-size:0.8em;display:inline-block;margin-top:var(--wp--preset--spacing--30)"
>' . esc_html($subtitle) . '</span>';
        } elseif (!empty($subtitle)) {
            $title .= ': ' . esc_html($subtitle);
        }
    }

    return $title;
}
add_filter('the_title', 'append_subtitle_to_title', 10, 2);

// Get all the unqiue authors from publications
function get_unique_author_users_from_publications()
{
    $user_ids = [];

    $posts = get_posts([
        'post_type'      => 'publications',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'fields'         => 'ids',
    ]);

    foreach ($posts as $post_id) {
        if (have_rows('authors', $post_id)) {
            while (have_rows('authors', $post_id)) {
                the_row();
                $user = get_sub_field('user');

                if (is_array($user) && isset($user['ID'])) {
                    $user_ids[] = $user['ID'];
                } elseif (is_numeric($user)) {
                    $user_ids[] = (int) $user;
                }
            }
        }
    }

    $unique_ids = array_unique($user_ids);

    $users = array_map('get_userdata', $unique_ids);

    // Sort by last name
    usort($users, function ($a, $b) {
        return strcasecmp($a->last_name, $b->last_name);
    });

    return $users;
}

// ===================
// PUBLICATIONS SEARCH
// ===================

function publications_search_form()
{
    $topics = get_terms(array( // Changed variable name for clarity
        'taxonomy' => 'topics',
        'hide_empty' => false,
    ));
    $search = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';

    ob_start(); ?>
    <form method="get" action="<?php echo esc_url(home_url('/publications-results/')); ?>" class="publications-search-form">
        <div class="wp-block-search__inside-wrapper ">
            <input class="wp-block-search__input" id="wp-block-search__input-2" placeholder="Search Here" value="<?php echo $search; ?>" type="search" name="q"><button aria-label="Search" class="wp-block-search__button wp-element-button" type="submit">Search</button>
        </div>
        <div style="display:flex; gap:20px; margin-top:25px;">
            <div>
                <div class="wp-block-button is-style-caes-hub-red-border"><a class="wp-block-button__link wp-element-button" href="#topicsModal"><strong>Topics</strong></a></div>
                <div id="topicsModal" class="modal">
                    <div class="modal-content">
                        <a href="#" class="close">&times;</a>
                        <h3 style="margin:0 0 5px;">Topics</h3>
                        <input type="text" id="inputTopics" onkeyup="filterCheckboxList('inputTopics', 'listTopics')" placeholder="Search for topics..." style="width:100%; font-size:15px; border:1px solid #ddd; padding:5px 15px; box-sizing:border-box;">
                        <div id="listTopics" class="scroller">
                            <?php foreach ($topics as $term): // Changed variable name to $topics 
                            ?><div><input type="checkbox" name="topics[]" value="<?php echo esc_attr($term->slug); ?>" <?php if (!empty($_GET['topics']) && in_array($term->slug, $_GET['topics'])) echo 'checked'; ?>> <?php echo esc_html($term->name); ?></div><?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div>
                <?php $authors = get_unique_author_users_from_publications(); ?>
                <div class="wp-block-button is-style-caes-hub-red-border"><a class="wp-block-button__link wp-element-button" href="#authorsModal"><strong>Authors</strong></a></div>
                <div id="authorsModal" class="modal">
                    <div class="modal-content">
                        <a href="#" class="close">&times;</a>
                        <h3 style="margin:0 0 5px;">Authors</h3>
                        <input type="text" id="inputAuthors" onkeyup="filterCheckboxList('inputAuthors', 'listAuthors')" placeholder="Search for topics..." style="width:100%; font-size:15px; border:1px solid #ddd; padding:5px 15px; box-sizing:border-box;" />
                        <div id="listAuthors" class="scroller">
                            <?php foreach ($authors as $user): ?><div><input type="checkbox" name="authors[]" value="<?php echo esc_attr($user->ID); ?>" <?php if (!empty($_GET['authors']) && in_array($user->ID, $_GET['authors'])) echo 'checked'; ?>> <?php echo esc_html($user->last_name); ?>, <?php echo esc_html($user->first_name); ?></div><?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div>
                <div class="wp-block-button is-style-caes-hub-red-border"><a class="wp-block-button__link wp-element-button" href="#languageModal"><strong>Language</strong></a></div>
                <div id="languageModal" class="modal">
                    <div class="modal-content">
                        <a href="#" class="close">&times;</a>
                        <h3 style="margin:0 0 5px;">Language</h3>
                        <label><input type="checkbox" name="language[]" value="1" <?php if (!empty($_GET['language']) && in_array(1, $_GET['language'])) echo 'checked'; ?>> English</label>
                        <label><input type="checkbox" name="language[]" value="2" <?php if (!empty($_GET['language']) && in_array(2, $_GET['language'])) echo 'checked'; ?>>Spanish</label>
                        <label><input type="checkbox" name="language[]" value="3" <?php if (!empty($_GET['language']) && in_array(3, $_GET['language'])) echo 'checked'; ?>> Chinese</label>
                        <label><input type="checkbox" name="language[]" value="4" <?php if (!empty($_GET['language']) && in_array(4, $_GET['language'])) echo 'checked'; ?>> Other</label>
                    </div>
                </div>
            </div>
            <div style="width:50%;">
                <div id="selectedFilters" style="display: flex; flex-wrap: wrap; gap: 10px;"></div>
            </div>
        </div>
        <style>
            .wp-block-search__inside-wrapper {
                display: flex;
                flex: auto;
                flex-wrap: nowrap;
                max-width: 100%;
            }

            .wp-block-search__input {
                appearance: none;
                border: 1px solid #949494;
                flex-grow: 1;
                margin-left: 0;
                margin-right: 0;
                min-width: 3rem;
                padding: 8px;
                text-decoration: unset !important;
            }

            .modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.6);
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.3s ease;
                z-index: 999;
            }

            .modal:target {
                opacity: 1;
                pointer-events: auto;
            }

            .modal-content {
                position: relative;
                margin: 10% auto;
                padding: 20px;
                background: #fff;
                width: 90%;
                max-width: 400px;
                height: 425px;
                border-radius: 8px;
            }

            .scroller {
                overflow: auto;
                height: 325px;
            }

            .close {
                position: absolute;
                top: 10px;
                right: 15px;
                text-decoration: none;
                font-size: 24px;
                color: #333;
            }

            .filter-pill {
                display: flex;
                align-items: center;
                background: #eaeaea;
                border-radius: 30px;
                padding: 5px 12px;
                font-size: 14px;
                line-height: 1;
            }

            .filter-pill span {
                margin-right: 8px;
            }

            .filter-pill button {
                background: none;
                border: none;
                font-size: 16px;
                cursor: pointer;
                color: #888;
            }
        </style>
        <script>
            function filterCheckboxList(inputId, listId) {
                var input = document.getElementById(inputId);
                var filter = input.value.toUpperCase();
                var container = document.getElementById(listId);
                var items = container.querySelectorAll('label, div');
                items.forEach(function(item) {
                    var text = item.textContent || item.innerText;
                    if (text.toUpperCase().indexOf(filter) > -1) {
                        item.style.display = "";
                    } else {
                        item.style.display = "none";
                    }
                });
            }

            function updateSelectedFilters() {
                const output = document.getElementById('selectedFilters');
                output.innerHTML = '';
                const checkedInputs = document.querySelectorAll('input[type="checkbox"]:checked');
                checkedInputs.forEach(function(input) {
                    const label = input.closest('label') || input.parentElement;
                    const text = label.textContent.trim();
                    const pill = document.createElement('div');
                    pill.className = 'filter-pill';
                    pill.innerHTML = `<span>${text}</span><button type="button" aria-label="Remove">&times;</button>`;
                    pill.querySelector('button').addEventListener('click', function() {
                        input.checked = false;
                        updateSelectedFilters();
                    });
                    output.appendChild(pill);
                });
            }
            document.addEventListener('DOMContentLoaded', () => {
                updateSelectedFilters();
                document.querySelectorAll('input[type="checkbox"]').forEach(input => {
                    input.addEventListener('change', updateSelectedFilters);
                });
            });
        </script>
    </form>
<?php return ob_get_clean();
}
add_shortcode('publications_search', 'publications_search_form');


// Show Publication Search Results
function shortcode_publication_results()
{
    $search = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    $paged = get_query_var('paged') ?: (get_query_var('page') ?: 1);

    $args = [
        'post_type' => 'publications',
        'posts_per_page' => 10,
        'paged' => $paged,
        's' => $search,
        'tax_query' => [],
        'meta_query' => [],
    ];

    // Topics
    if (!empty($_GET['topics']) && is_array($_GET['topics'])) {
        $args['tax_query'][] = [
            'taxonomy' => 'topics',
            'field'    => 'slug',
            'terms'    => array_map('sanitize_text_field', $_GET['topics']),
        ];
    }

    // Language
    if (!empty($_GET['language']) && is_array($_GET['language'])) {
        $args['meta_query'][] = [
            'key'     => 'language',
            'value'   => array_map('sanitize_text_field', $_GET['language']),
            'compare' => 'IN',
        ];
    }

    // Authors
    if (!empty($_GET['authors']) && is_array($_GET['authors'])) {
        $author_ids = array_map('intval', $_GET['authors']);

        $author_meta_query = ['relation' => 'OR'];

        for ($i = 0; $i <= 10; $i++) {
            foreach ($author_ids as $author_id) {
                $author_meta_query[] = [
                    'key'     => 'authors_' . $i . '_user',
                    'value'   => $author_id,
                    'compare' => '=',
                ];
            }
        }

        $args['meta_query'][] = $author_meta_query;
    }

    $query = new WP_Query($args);
    ob_start();

    if ($query->have_posts()) {
        echo '<div class="wp-block-query alignwide caes-hub-post-list-grid">';
        echo '<div class="wp-block-post-template">';

        echo '<h1 style="text-transform:uppercase;" class="wp-block-query-title has-x-large-font-size has-oswald-font-family">Search results for: “' . $search . '”</h1>';

        while ($query->have_posts()) {
            $query->the_post();

            $block_markup = <<<HTML
            <!-- wp:columns {"className":"caes-hub-post-list-grid-item height-100","style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"blockGap":{"top":"0","left":"0"},"margin":{"top":"0","bottom":"25"}}}} -->
            <div class="wp-block-columns caes-hub-post-list-grid-item height-100" style="margin-top:0;margin-bottom:25px;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0">
                <!-- wp:column {"width":"33.33%"} -->
                <div class="wp-block-column" style="flex-basis:33.33%">
                    <!-- wp:post-featured-image {"aspectRatio":"3/2","metadata":{"name":"caes-hub-post-list-img-container"},"className":"caes-hub-post-list-img-container"} /-->
                </div>
                <!-- /wp:column -->

                <!-- wp:column {"width":"66.66%"} -->
                <div class="wp-block-column" style="flex-basis:66.66%">
                    <!-- wp:group {"metadata":{"name":"caes-hub-post-list-grid-info"},"className":"caes-hub-post-list-grid-info","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|60","right":"var:preset|spacing|60"}}},"backgroundColor":"base","layout":{"type":"flex","orientation":"vertical","justifyContent":"left","verticalAlignment":"space-between"}} -->
                    <div class="wp-block-group caes-hub-post-list-grid-info has-base-background-color has-background" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--60)">
                        <!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"default"}} -->
                        <div class="wp-block-group">
                            <!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
                            <div class="wp-block-group">
                                <!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"top"}} -->
                                <div class="wp-block-group">
                                    <!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"constrained"}} -->
                                    <div class="wp-block-group">
                                        <!-- wp:caes-hub/pub-details-number {"fontSize":"small"} /-->
                                        <!-- wp:post-title {"isLink":true,"className":"caes-hub-post-list-grid-title","style":{"elements":{"link":{"color":{"text":"var:preset|color|contrast"}}}},"textColor":"contrast","fontSize":"large"} /-->
                                    </div>
                                    <!-- /wp:group -->
                                    <!-- wp:caes-hub/pub-details-status /-->
                                </div>
                                <!-- /wp:group -->
                            </div>
                            <!-- /wp:group -->
                            <!-- wp:caes-hub/pub-details-summary /-->
                        </div>
                        <!-- /wp:group -->

                        <!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
                        <div class="wp-block-group">
                            <!-- wp:caes-hub/pub-details-authors {"authorsAsSnippet":true} /-->
                            <!-- wp:paragraph -->
                            <p>|</p>
                            <!-- /wp:paragraph -->
                            <!-- wp:post-date {"format":"M j, Y"} /-->
                        </div>
                        <!-- /wp:group -->
                    </div>
                    <!-- /wp:group -->
                </div>
                <!-- /wp:column -->
            </div>
            <!-- /wp:columns -->
            HTML;

            echo do_blocks($block_markup);
        }

        echo '</div>'; // Close .wp-block-post-template

        // Pagination
        $pagination_links = paginate_links([
            'base'      => esc_url_raw(add_query_arg('paged', '%#%')),
            'format'    => '',
            'current'   => max(1, $paged),
            'total'     => $query->max_num_pages,
            'type'      => 'array',
            'prev_next' => false,
        ]);

        $next_link = get_next_posts_page_link($query->max_num_pages);

        if (!empty($pagination_links)) {
            echo '<nav class="wp-block-query-pagination is-content-justification-center is-layout-flex wp-block-query-pagination-is-layout-flex" aria-label="Pagination" style="justify-content:center;">';
            echo '<div class="wp-block-query-pagination-numbers" style="display:flex; gap:3px;">';
            foreach ($pagination_links as $link) {
                echo $link;
            }
            echo '</div>';
            if ($next_link) {
                echo '<a href="' . esc_url($next_link) . '" class="wp-block-query-pagination-next">Next Page</a>';
            }
            echo '</nav>';
        }
        echo '</div>'; // Close .wp-block-query
    } else {
        echo '<p>No results found.</p>';
    }

    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('publication_results', 'shortcode_publication_results');


//** Update all_author_ids meta field when saving a post  */
add_action('acf/save_post', 'update_flat_author_ids_meta', 20);
function update_flat_author_ids_meta($post_id)
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!in_array(get_post_type($post_id), ['publications', 'post', 'shorthand_story'])) {
        return;
    }

    // Get the ACF field object for 'authors' to find its key dynamically.
    $authors_field_object = get_field_object('authors', $post_id, false);

    // If we can't find the field object or its key, we can't proceed.
    if (!$authors_field_object || !isset($authors_field_object['key'])) {
        return;
    }
    $authors_field_key = $authors_field_object['key'];

    // Check if the repeater data exists in the submitted POST data.
    if (empty($_POST['acf']) || empty($_POST['acf'][$authors_field_key])) {
        delete_post_meta($post_id, 'all_author_ids');
        return;
    }

    $author_rows = $_POST['acf'][$authors_field_key];
    $author_ids = [];

    // Find the subfield key for the 'user' field dynamically.
    $user_sub_field_key = '';
    foreach ($authors_field_object['sub_fields'] as $sub_field) {
        if ($sub_field['name'] === 'user') {
            $user_sub_field_key = $sub_field['key'];
            break;
        }
    }

    // If we couldn't find the 'user' subfield, we can't proceed.
    if (empty($user_sub_field_key)) {
        return;
    }

    // Loop through the submitted repeater data.
    foreach ($author_rows as $row) {
        // Check if the user subfield exists in the row, has a value, and is numeric.
        if (isset($row[$user_sub_field_key]) && is_numeric($row[$user_sub_field_key])) {
            $author_ids[] = (int) $row[$user_sub_field_key];
        }
    }

    // Save the final array of valid author IDs.
    update_post_meta($post_id, 'all_author_ids', $author_ids);
}

/**
 * When a publication is saved, calculate and store the latest revision date
 * in a separate, queryable meta field for performance.
 *
 * This version reads directly from the $_POST data to get the incoming values
 * before they are saved to the database, making it reliable and portable.
 */
function update_latest_revision_date_on_save($post_id)
{
    // Only run for our post type and not on autosaves
    if (get_post_type($post_id) !== 'publications' || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
        return;
    }

    // Use ACF's function to get the repeater field object by its NAME.
    // This is the portable way to get the field's unique KEY.
    $history_field_object = get_field_object('history', $post_id, false);

    // If we can't find the field object or its key, we can't proceed.
    if (!$history_field_object || !isset($history_field_object['key'])) {
        return;
    }
    $history_field_key = $history_field_object['key'];

    // Check if the repeater data exists in the submitted POST data.
    if (empty($_POST['acf']) || empty($_POST['acf'][$history_field_key])) {
        delete_post_meta($post_id, '_publication_latest_revision_date');
        return;
    }

    $history_rows = $_POST['acf'][$history_field_key];
    $latest_revision_date = 0;
    $revision_status_keys = [4, 5, 6]; // The revision statuses we care about.

    // Loop through the submitted repeater data.
    foreach ($history_rows as $row) {
        // The subfield keys are fixed relative to the repeater.
        // We can find them by looking at the repeater's subfield definitions.
        $status_field_key = $history_field_object['sub_fields'][0]['key']; // Assumes 'status' is the first subfield
        $date_field_key   = $history_field_object['sub_fields'][1]['key']; // Assumes 'date' is the second subfield

        if (isset($row[$status_field_key], $row[$date_field_key])) {
            $status   = (int) $row[$status_field_key];
            $date_str = $row[$date_field_key];

            if (in_array($status, $revision_status_keys) && !empty($date_str)) {
                $current_date = (int) $date_str;
                if ($current_date > $latest_revision_date) {
                    $latest_revision_date = $current_date;
                }
            }
        }
    }

    // Save the final calculated date to our hidden field.
    if ($latest_revision_date > 0) {
        update_post_meta($post_id, '_publication_latest_revision_date', $latest_revision_date);
    } else {
        delete_post_meta($post_id, '_publication_latest_revision_date');
    }
}
add_action('acf/save_post', 'update_latest_revision_date_on_save', 20);

// --- NEW FUNCTION TO ADD ---
/**
 * When a publication is saved, calculate and store the latest "Published" date.
 */
function update_latest_publish_date_on_save($post_id)
{
    // Only run for publications and not on autosaves
    if (get_post_type($post_id) !== 'publications' || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
        return;
    }

    $history_field_object = get_field_object('history', $post_id, false);

    if (!$history_field_object || !isset($history_field_object['key'])) {
        return;
    }
    $history_field_key = $history_field_object['key'];

    // If there's no history data submitted, delete the meta field.
    if (empty($_POST['acf']) || empty($_POST['acf'][$history_field_key])) {
        delete_post_meta($post_id, '_publication_latest_publish_date');
        return;
    }

    $history_rows = $_POST['acf'][$history_field_key];
    $latest_publish_date = 0;
    $publish_status_key = [2]; // The "Published" status.

    foreach ($history_rows as $row) {
        $status_field_key = $history_field_object['sub_fields'][0]['key'];
        $date_field_key   = $history_field_object['sub_fields'][1]['key'];

        if (isset($row[$status_field_key], $row[$date_field_key])) {
            $status   = (int) $row[$status_field_key];
            $date_str = $row[$date_field_key];

            // Check if the status is "Published" and a date exists.
            if (in_array($status, $publish_status_key) && !empty($date_str)) {
                $current_date = (int) $date_str;
                if ($current_date > $latest_publish_date) {
                    $latest_publish_date = $current_date;
                }
            }
        }
    }

    // Save the final calculated date to our new hidden field.
    if ($latest_publish_date > 0) {
        update_post_meta($post_id, '_publication_latest_publish_date', $latest_publish_date);
    } else {
        delete_post_meta($post_id, '_publication_latest_publish_date');
    }
}
add_action('acf/save_post', 'update_latest_publish_date_on_save', 20);

/**
 * Automatically sorts publication series archives by their publication number.
 *
 * This function hooks into the main WordPress query and applies custom sorting
 * only on the front-end for 'publication_series' taxonomy archives.
 *
 * @param WP_Query $query The main WP_Query object.
 */
function custom_sort_publication_series_archives($query)
{
    // Run only on the front-end, for the main query, on a publication series archive.
    if (! is_admin() && $query->is_main_query() && $query->is_tax('publication_series')) {

        // Set the meta key to ensure the postmeta table is joined correctly.
        $query->set('meta_key', 'publication_number');

        // Add our temporary filter to modify the SQL's ORDER BY clause.
        add_filter('posts_orderby', 'custom_series_alphanumeric_orderby', 10, 2);
    }
}
add_action('pre_get_posts', 'custom_sort_publication_series_archives');

/**
 * Helper function to apply the custom alphanumeric SQL sorting logic.
 *
 * This handles two primary formats:
 * 1. Series publications (e.g., "SB 28-19")
 * 2. Non-series publications (e.g., "C 1039")
 *
 * It sorts by the letter prefix first, then by the primary number.
 *
 * @param string   $orderby The original ORDER BY clause.
 * @param WP_Query $query   The WP_Query object (unused, but required by the filter).
 * @return string The modified ORDER BY clause.
 */
function custom_series_alphanumeric_orderby($orderby, $query)
{
    global $wpdb;

    // Remove this filter immediately so it doesn't affect other queries.
    remove_filter(current_filter(), __FUNCTION__, 10);

    // 1. Sort by the alphabetical prefix (e.g., 'C', 'SB').
    $prefix_sort = "SUBSTRING_INDEX({$wpdb->postmeta}.meta_value, ' ', 1)";

    // 2. Sort by the primary number. This correctly extracts '1039' from 'C 1039'
    //    and '28' from 'SB 28-19'.
    $primary_num_sort = "CAST(SUBSTRING_INDEX(SUBSTRING_INDEX({$wpdb->postmeta}.meta_value, '-', 1), ' ', -1) AS UNSIGNED)";

    // 3. Sort by the series number (after the dash), if it exists.
    $series_num_sort = "CAST(SUBSTRING_INDEX({$wpdb->postmeta}.meta_value, '-', -1) AS UNSIGNED)";

    // Combine the sorting criteria for a full alphanumeric sort.
    $orderby = "{$prefix_sort} ASC, {$primary_num_sort} ASC, {$series_num_sort} ASC";


    return $orderby;
}

/**
 * Excludes the "Departments" topic and its children from the topics list
 * on single publication pages (High-performance version).
 *
 * @param array|WP_Error $terms     Array of WP_Term objects or a WP_Error object.
 * @param int            $post_id   The ID of the post.
 * @param string         $taxonomy  The taxonomy name.
 * @return array|WP_Error The filtered list of terms.
 */

function caes_hub_exclude_department_topics_from_publications($terms, $post_id, $taxonomy)
{
    // Bail out early if it's not the right context.
    if (!is_singular('publications') || $taxonomy !== 'topics' || is_admin()) {
        return $terms;
    }

    // Use a static variable to cache the IDs for the duration of a single page load.
    static $exclude_ids = null;

    // Only run the database queries if our static variable hasn't been populated yet.
    if ($exclude_ids === null) {
        $exclude_ids = [];

        // Terms to exclude (parent terms)
        $terms_to_exclude = ['Departments', 'Departments and Units'];

        foreach ($terms_to_exclude as $term_name) {
            $department_term = get_term_by('name', $term_name, 'topics');

            if ($department_term) {
                $exclude_ids[] = $department_term->term_id;
                $child_term_ids = get_term_children($department_term->term_id, 'topics');

                if (!is_wp_error($child_term_ids) && !empty($child_term_ids)) {
                    $exclude_ids = array_merge($exclude_ids, $child_term_ids);
                }
            }
        }
    }

    // If there are no IDs to exclude or no terms to filter, return early.
    if (empty($exclude_ids) || is_wp_error($terms) || empty($terms)) {
        return $terms;
    }

    // Filter the terms using our cached list of IDs.
    return array_filter($terms, function ($term) use ($exclude_ids) {
        return !in_array($term->term_id, $exclude_ids);
    });
}
add_filter('get_the_terms', 'caes_hub_exclude_department_topics_from_publications', 10, 3);

/**
 * Add page number to the Title block for paginated publications
 * Adds the page number inside the subtitle if one exists
 */
function caes_add_page_number_to_title_block($block_content, $block)
{
    // Only process core/post-title blocks
    if ($block['blockName'] !== 'core/post-title') {
        return $block_content;
    }

    // Only run on publications
    if (!is_singular('publications')) {
        return $block_content;
    }

    // Get the current page number and ensure it's a positive integer
    $page = absint(get_query_var('page'));

    // Only modify if we're on page 2 or higher
    if ($page < 2) {
        return $block_content;
    }

    // Page number text to insert
    $page_number = ' - Page ' . absint($page);

    // Check if there's a subtitle element inside the heading
    if (preg_match('/<h[1-6][^>]*>(.*?)(<[^>]+>)(.*?)(<\/[^>]+>)(.*?)<\/h[1-6]>/is', $block_content, $matches)) {
        // Title has a subtitle element - add page number inside the subtitle
        $before_subtitle = $matches[1];
        $subtitle_open = $matches[2];
        $subtitle_text = $matches[3];
        $subtitle_close = $matches[4];
        $after_subtitle = $matches[5];

        // Insert page number at the end of the subtitle text
        $new_subtitle_text = $subtitle_text . $page_number;

        $block_content = preg_replace(
            '/<h([1-6][^>]*)>(.*?)(<[^>]+>)(.*?)(<\/[^>]+>)(.*?)<\/h[1-6]>/is',
            '<h$1>' . $before_subtitle . $subtitle_open . $new_subtitle_text . $subtitle_close . $after_subtitle . '</h$1>',
            $block_content,
            1
        );
    } else {
        // No subtitle found - just append page number to title
        $block_content = preg_replace(
            '/(<\/h[1-6]>)$/i',
            $page_number . '$1',
            $block_content,
            1
        );
    }

    return $block_content;
}
add_filter('render_block', 'caes_add_page_number_to_title_block', 10, 2);

/**
 * Add print CSS with dynamic footer for publications
 */

function normalize_hyphens_for_pdf($content)
{
    $replacements = [
        "\u{2010}" => '-', // Hyphen
        "\u{2013}" => '-', // En Dash (replaces 'ÃƒÂ¢Ã¢â€šÂ¬')
        "\u{2014}" => '-', // Em Dash (replaces 'ÃƒÆ'Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬')
    ];
    $content = str_replace(array_keys($replacements), array_values($replacements), $content);
    return $content;
}

function format_publication_number_for_display($publication_number)
{
    $originalPubNumber = $publication_number;
    $displayPubNumber = $originalPubNumber;
    $pubType = '';

    if ($originalPubNumber) {
        $prefix = strtoupper(substr($originalPubNumber, 0, 2));
        $firstChar = strtoupper(substr($originalPubNumber, 0, 1));

        switch ($prefix) {
            case 'AP':
                $pubType = 'Annual Publication';
                $displayPubNumber = substr($originalPubNumber, 2);
                break;
            case 'TP':
                $pubType = 'Temporary Publication';
                $displayPubNumber = substr($originalPubNumber, 2);
                break;
            default:
                switch ($firstChar) {
                    case 'B':
                        $pubType = 'Bulletin';
                        $displayPubNumber = substr($originalPubNumber, 1);
                        break;
                    case 'C':
                        $pubType = 'Circular';
                        $displayPubNumber = substr($originalPubNumber, 1);
                        break;
                    default:
                        $pubType = 'Publication';
                        break;
                }
                break;
        }
    }

    $displayPubNumber = trim($displayPubNumber);
    $formatted_pub_number_string = '';
    if (!empty($pubType) && !empty($displayPubNumber)) {
        $formatted_pub_number_string = $pubType . ' ' . $displayPubNumber;
    } elseif (!empty($displayPubNumber)) {
        $formatted_pub_number_string = $displayPubNumber;
    }

    return $formatted_pub_number_string;
}


/**
 * Add print support for publications
 */
/**
 * Add print support for publications
 */
add_action('wp_head', function() {
    if (!is_singular('publications')) {
        return;
    }

    $is_print_view = isset($_GET['print']) && $_GET['print'] === 'true';
    $post_id = get_the_ID();
    $publication_number = get_field('publication_number', $post_id);
    $publication_title = get_the_title();
    $subtitle = get_post_meta($post_id, 'subtitle', true);
    
    if (!empty($subtitle)) {
        $publication_title .= ': ' . $subtitle;
    }

    $formatted_pub_number = format_publication_number_for_display($publication_number);
    $footer_text = 'UGA Cooperative Extension ' . esc_attr($formatted_pub_number) . ' | ' . esc_attr($publication_title);

    if ($is_print_view) {
        ?>
        <script src="https://unpkg.com/pagedjs/dist/paged.polyfill.js"></script>
        <style>
        @page {
            size: 8.5in 11in;
            margin: 0.75in 0.75in 1in 0.75in;

            @bottom-left {
                content: "<?php echo $footer_text; ?>";
                font-size: 10px;
                font-family: Georgia, serif;
            }

            @bottom-right {
                content: counter(page);
                font-size: 10px;
                font-family: Georgia, serif;
            }
        }

        @page :first {
            @bottom-right { content: none; }
            @bottom-left { content: none; }
        }

        .caes-hub-content-meta-wrap {
            counter-reset: page;
        }
        </style>
        <?php
    } else {
        ?>
        <style>
        @media print {
            body::before {
                content: "Please use the print button or Ctrl+P to print this publication.";
                display: block;
                font-size: 24px;
                text-align: center;
                padding: 100px;
            }
            body > *:not(#print-redirect-notice) {
                display: none !important;
            }
        }
        </style>
        <script>
        (function() {
            const printUrl = '<?php echo esc_url(add_query_arg('print', 'true', get_permalink())); ?>';
            
            function openPrintView(e) {
                if (e) e.preventDefault();
                window.open(printUrl, '_blank');
            }

            // Catch Ctrl+P / Cmd+P
            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                    openPrintView(e);
                }
            });

            // Catch browser menu print
            window.addEventListener('beforeprint', function() {
                window.open(printUrl, '_blank');
            });
        })();
        </script>
        <?php
    }
});

/**
 * Dequeue scripts that break Paged.js on print view
 */
add_action('wp_enqueue_scripts', function() {
    if (!is_singular('publications')) {
        return;
    }

    $is_print_view = isset($_GET['print']) && $_GET['print'] === 'true';

    if ($is_print_view) {
        // Remove emoji script
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
        
        // Dequeue theme scripts that might interfere
        wp_dequeue_script('main');
        wp_dequeue_script('jquery');
    }
}, 100);

/**
 * Add print view banner
 */
add_action('wp_body_open', function() {
    if (!is_singular('publications')) {
        return;
    }

    $is_print_view = isset($_GET['print']) && $_GET['print'] === 'true';

    if ($is_print_view) {
        ?>
        <div id="print-view-banner" style="position:fixed; top:0; left:0; right:0; background:#ba0c2f; color:#fff; padding:12px 20px; z-index:999999; font-family: Georgia, serif; text-align:center;">
            <span style="margin-right: 20px;">Print-formatted view</span>
            <button onclick="window.close()" style="color:#fff; background:rgba(0,0,0,0.3); padding:8px 16px; border:none; border-radius:4px; cursor:pointer;">✕ Close</button>
            <button onclick="window.print()" style="color:#fff; background:rgba(0,0,0,0.3); padding:8px 16px; border:none; border-radius:4px; margin-left:10px; cursor:pointer;">Print</button>
        </div>
        <style>
        body { padding-top: 60px !important; }
        @media print {
            #print-view-banner { display: none !important; }
            body { padding-top: 0 !important; }
        }
        </style>
        <?php
    }
});

add_filter('the_content', function ($content) {
    if (!is_singular('publications') || is_admin()) {
        return $content;
    }

    $post_id = get_the_ID();
    $publication_number = get_field('publication_number', $post_id);
    $formatted_pub_number = format_publication_number_for_display($publication_number);
    $latest_published_info = get_latest_published_date($post_id);
    $permalink = get_permalink($post_id);

    $status_labels = [
        2 => 'Published',
        4 => 'Published with Minor Revisions',
        5 => 'Published with Major Revisions',
        6 => 'Published with Full Review',
    ];

    $publish_date_text = '';
    if (!empty($latest_published_info['date']) && !empty($latest_published_info['status'])) {
        $status_label = $status_labels[$latest_published_info['status']] ?? 'Published';
        $publish_date_text = $status_label . ' on ' . date('F j, Y', strtotime($latest_published_info['date']));
    }

    $footer_html = '
    <div class="print-last-page-footer">
        <p class="print-permalink">The permalink for this UGA Extension publication is <a href="' . esc_url($permalink) . '">' . esc_html($permalink) . '</a></p>
        <hr>
        <div class="print-pub-meta">
            <span class="print-pub-number">' . esc_html($formatted_pub_number) . '</span>
            <span class="print-pub-date">' . esc_html($publish_date_text) . '</span>
        </div>
        <hr>
        <p class="print-disclaimer">Published by University of Georgia Cooperative Extension. For more information or guidance, contact your local Extension office. <em>The University of Georgia
College of Agricultural and Environmental Sciences (working cooperatively with Fort Valley State University, the U.S. Department of Agriculture, and the
counties of Georgia) offers its educational programs, assistance, and materials to all people without regard to age, color, disability, genetic information,
national origin, race, religion, sex, or veteran status, and is an Equal Opportunity Institution.</em></p>
    </div>';

    return $content . $footer_html;
}, 20);
/* End print-only LAST PAGE footer to publications */
