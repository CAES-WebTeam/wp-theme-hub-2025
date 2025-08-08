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
        'post_type'   => 'publications',
        'meta_key'    => 'sunset_date',
        'meta_value'  => $today,
        'meta_compare' => '<=',
        'post_status' => 'publish',
        'posts_per_page' => -1, // Retrieve all matching posts
    ]);

    if ($query->have_posts()) {
        foreach ($query->posts as $post) {
            // Unpublish each post by setting its status to 'draft'
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
        // error_log("🔍 Author array: " . print_r($author, true));

        if (!empty($author['user']) && is_numeric($author['user'])) {
            $author_ids[] = (int) $author['user'];
        } else {
            error_log("⚠️ Invalid or missing 'user' field in author entry");
        }
    }

    update_post_meta($post_id, 'all_author_ids', $author_ids);
}
