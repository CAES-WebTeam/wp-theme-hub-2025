<?php
/**
 * Topics Taxonomy Manager
 *
 * This file creates an admin tool to manage and view topics data with a focus on
 * performance and a clean, hierarchical display. It includes post counts,
 * a status indicator, and a one-time sync with an external API.
 *
 * @package CAESHUB
 */

// Prevent direct file access.
if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// Security & Constants
// =============================================================================
define('CAES_TOPICS_CAPABILITY', 'manage_options');
define('CAES_TOPICS_TAXONOMY', 'topics');
define('CAES_TOPICS_CACHE_KEY', 'caes_topics_data_cache_v13'); // Cache key updated for parent labels
define('CAES_TOPICS_CACHE_TTL', 15 * MINUTE_IN_SECONDS);
define('CAES_TOPICS_API_ENDPOINT', 'https://secure.caes.uga.edu/rest/publications/getKeywords');

// =============================================================================
// Admin Page Setup & Actions
// =============================================================================

/**
 * Adds the Topics Manager submenu page.
 */
add_action('admin_menu', 'caes_add_topics_manager_page');
function caes_add_topics_manager_page() {
    add_submenu_page(
        'caes-tools',
        'Topics Manager',
        'Topics Manager',
        CAES_TOPICS_CAPABILITY,
        'caes-topics-manager',
        'caes_render_topics_manager_page'
    );
}

/**
 * Handles admin actions on admin_init, before headers are sent.
 */
add_action('admin_init', 'caes_handle_topics_admin_actions');
function caes_handle_topics_admin_actions() {
    if (!current_user_can(CAES_TOPICS_CAPABILITY) || !isset($_GET['page']) || $_GET['page'] !== 'caes-topics-manager') {
        return;
    }

    // Handle Cache Refresh Action
    if (isset($_GET['action']) && $_GET['action'] === 'refresh_cache' && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'caes_refresh_cache')) {
        caes_clear_topics_cache();
        wp_safe_redirect(admin_url('admin.php?page=caes-topics-manager&message=cache-cleared'));
        exit;
    }

    // Handle API Sync Action
    if (isset($_GET['action']) && $_GET['action'] === 'sync_status' && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'caes_sync_status')) {
        $updated_count = caes_sync_topic_status_from_api();
        $redirect_url = admin_url('admin.php?page=caes-topics-manager&message=sync-complete&updated=' . (int)$updated_count);
        
        if ($updated_count < 0) {
            $redirect_url = admin_url('admin.php?page=caes-topics-manager&message=sync-error&code=' . abs($updated_count));
        }
        
        wp_safe_redirect($redirect_url);
        exit;
    }
}

/**
 * Renders the HTML for the Topics Manager page.
 */
function caes_render_topics_manager_page() {
    if (!current_user_can(CAES_TOPICS_CAPABILITY)) {
        wp_die(__('You do not have sufficient permissions to access this page.'), 403);
    }

    // Display admin notices
    if (isset($_GET['message'])) {
        if ($_GET['message'] === 'cache-cleared') {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Data cache has been cleared. The view is now up-to-date.</strong></p></div>';
        } elseif ($_GET['message'] === 'sync-complete' && isset($_GET['updated'])) {
            $updated_count = (int)$_GET['updated'];
            echo '<div class="notice notice-success is-dismissible"><p><strong>Synchronization complete. ' . $updated_count . ' topics were updated in the database. Please click "Refresh Data" to see the changes.</strong></p></div>';
        } elseif ($_GET['message'] === 'sync-error') {
            $error_code = isset($_GET['code']) ? (int)$_GET['code'] : 0;
            $error_message = 'An unknown error occurred during synchronization.';
            if ($error_code === 1) $error_message = '<strong>Sync Error:</strong> Could not reach the API endpoint. Please try again later.';
            if ($error_code === 2) $error_message = '<strong>Sync Error:</strong> The API returned invalid data.';
            echo '<div class="notice notice-error is-dismissible"><p>' . $error_message . '</p></div>';
        }
    }

    $data = caes_get_topics_data_with_cache();
    $refresh_nonce = wp_create_nonce('caes_refresh_cache');
    $sync_nonce = wp_create_nonce('caes_sync_status');
    $refresh_url = add_query_arg(['action' => 'refresh_cache', '_wpnonce' => $refresh_nonce], admin_url('admin.php?page=caes-topics-manager'));
    $sync_url = add_query_arg(['action' => 'sync_status', '_wpnonce' => $sync_nonce], admin_url('admin.php?page=caes-topics-manager'));
    
    // Prepare data for display
    $all_topics = $data['topics'] ?? [];
    $full_hierarchy = $data['hierarchy'] ?? [];
    $active_hierarchy = caes_filter_hierarchy_by_status($full_hierarchy, true);
    $inactive_hierarchy = caes_filter_hierarchy_by_status($full_hierarchy, false);

    ?>
    <div class="wrap">
        <h1>
            Topics Manager
            <a href="<?php echo esc_url($sync_url); ?>" class="page-title-action">1. Sync Status from API</a>
            <a href="<?php echo esc_url($refresh_url); ?>" class="page-title-action">2. Refresh Data</a>
        </h1>

        <div class="caes-summary-cards">
            <div class="caes-summary-card">
                <h3>Total Topics</h3>
                <div class="number"><?php echo (int) ($data['summary']['total_topics'] ?? 0); ?></div>
            </div>
            <div class="caes-summary-card">
                <h3>Active Topics</h3>
                <div class="number"><?php echo (int) ($data['summary']['active_topics'] ?? 0); ?></div>
            </div>
            <div class="caes-summary-card">
                <h3>Inactive Topics</h3>
                <div class="number"><?php echo (int) ($data['summary']['inactive_topics'] ?? 0); ?></div>
            </div>
        </div>

        <h2 class="nav-tab-wrapper">
            <a href="#hierarchy" class="nav-tab nav-tab-active">All Items (Hierarchy)</a>
            <a href="#active" class="nav-tab">Active Items</a>
            <a href="#inactive" class="nav-tab">Inactive Items</a>
        </h2>
        
        <div id="hierarchy" class="caes-tab-content active">
            <?php caes_display_topics_hierarchy($full_hierarchy, $all_topics); ?>
        </div>
        
        <div id="active" class="caes-tab-content">
            <?php caes_display_topics_hierarchy($active_hierarchy, $all_topics); ?>
        </div>

        <div id="inactive" class="caes-tab-content">
            <?php caes_display_topics_hierarchy($inactive_hierarchy, $all_topics); ?>
        </div>
    </div>
    <style>
        .caes-summary-cards { display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
        .caes-summary-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; min-width: 200px; flex: 1; }
        .caes-summary-card h3 { margin: 0 0 10px 0; font-size: 14px; text-transform: uppercase; color: #666; }
        .caes-summary-card .number { font-size: 32px; font-weight: bold; color: #135e96; }
        .caes-tab-content { display: none; margin-top: 20px; }
        .caes-tab-content.active { display: block; }
        .caes-topic-item { background: #fff; border: 1px solid #ccd0d4; margin-bottom: 10px; padding: 15px; border-radius: 4px; }
        .caes-topic-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .caes-topic-name { font-weight: bold; font-size: 16px; }
        .caes-topic-parent { font-size: 12px; color: #555; margin-bottom: 10px; }
        .caes-topic-inactive { opacity: 0.7; background-color: #fefefe; }
        .caes-status-badge { padding: 4px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .caes-status-active { background-color: #d4edda; color: #155724; }
        .caes-status-inactive { background-color: #f8d7da; color: #721c24; }
        .caes-counts { display: flex; gap: 20px; margin: 10px 0; flex-wrap: wrap; }
        .caes-count-item { padding: 5px 10px; background-color: #f0f0f1; border-radius: 3px; font-size: 12px; }
        .caes-counts a { color: #135e96; text-decoration: none; }
        .caes-counts a:hover { text-decoration: underline; }
    </style>
    <script>
        jQuery(document).ready(function($) {
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                var target = $(this).attr('href');
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('.caes-tab-content').removeClass('active');
                $(target).addClass('active');
            });
        });
    </script>
    <?php
}

// =============================================================================
// Data Synchronization & Filtering
// =============================================================================

function caes_sync_topic_status_from_api() {
    if (!function_exists('update_field')) return -1;

    $response = wp_remote_get(CAES_TOPICS_API_ENDPOINT, ['timeout' => 30]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return -1;
    }

    $api_data = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($api_data)) {
        return -2;
    }

    $api_map = array_column($api_data, null, 'ID');
    $all_terms = get_terms(['taxonomy' => CAES_TOPICS_TAXONOMY, 'hide_empty' => false]);
    if (is_wp_error($all_terms)) return 0;

    $updated_count = 0;
    foreach ($all_terms as $term) {
        $term_ref = 'term_' . $term->term_id;
        $topic_id = (int)get_field('topic_id', $term_ref);

        if ($topic_id > 0 && isset($api_map[$topic_id])) {
            $api_item = $api_map[$topic_id];
            $current_status = get_field('is_active', $term_ref);
            $new_status = isset($api_item['IS_ACTIVE']) ? (bool)$api_item['IS_ACTIVE'] : false;

            if ($current_status !== $new_status) {
                update_field('is_active', $new_status, $term_ref);
                $updated_count++;
            }
        }
    }

    caes_clear_topics_cache();
    return $updated_count;
}

function caes_filter_hierarchy_by_status(array $nodes, bool $is_active_status) {
    $filtered_nodes = [];
    foreach ($nodes as $node) {
        $children = $node['children'] ?? [];
        $filtered_children = caes_filter_hierarchy_by_status($children, $is_active_status);

        if ((bool)$node['is_active'] === $is_active_status || !empty($filtered_children)) {
            $node['children'] = $filtered_children;
            $filtered_nodes[] = $node;
        }
    }
    return $filtered_nodes;
}

// =============================================================================
// Data Retrieval & Caching
// =============================================================================

function caes_get_topics_data_with_cache() {
    $data = get_transient(CAES_TOPICS_CACHE_KEY);
    if (false === $data) {
        $data = caes_generate_topics_data();
        set_transient(CAES_TOPICS_CACHE_KEY, $data, CAES_TOPICS_CACHE_TTL);
    }
    return $data;
}

function caes_clear_topics_cache() {
    delete_transient(CAES_TOPICS_CACHE_KEY);
}

function caes_generate_topics_data() {
    if (!function_exists('get_fields')) {
        return ['topics' => [], 'summary' => [], 'hierarchy' => []];
    }

    $topics = get_terms(['taxonomy' => CAES_TOPICS_TAXONOMY, 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC']);
    if (is_wp_error($topics)) return ['topics' => [], 'summary' => [], 'hierarchy' => []];

    $post_counts = caes_get_all_topic_post_counts();
    $processed_topics = [];
    $active_count = 0;
    $inactive_count = 0;

    foreach ($topics as $topic) {
        $term_id = (int)$topic->term_id;
        $meta = get_fields('term_' . $term_id);
        $is_active = caes_is_topic_active_from_meta($meta);

        if ($is_active) $active_count++;
        else $inactive_count++;

        $processed_topics[$term_id] = [
            'term'      => $topic,
            'is_active' => $is_active,
            'counts'    => $post_counts[$term_id] ?? [],
            'meta'      => $meta ?: []
        ];
    }

    return [
        'topics'    => $processed_topics,
        'summary'   => ['total_topics' => count($topics), 'active_topics' => $active_count, 'inactive_topics' => $inactive_count],
        'hierarchy' => caes_build_hierarchy($processed_topics)
    ];
}

function caes_is_topic_active_from_meta($meta_array) {
    if (!is_array($meta_array)) return true;
    if (isset($meta_array['is_active'])) return (bool)$meta_array['is_active'];
    if (isset($meta_array['active'])) return (bool)$meta_array['active'];
    return true;
}

function caes_get_all_topic_post_counts() {
    global $wpdb;
    $post_types = ['post', 'publications', 'shorthand_story'];
    $counts = [];

    $sql = $wpdb->prepare("
        SELECT tt.term_id, p.post_type, COUNT(p.ID) AS post_count
        FROM {$wpdb->term_taxonomy} AS tt
        INNER JOIN {$wpdb->term_relationships} AS tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
        INNER JOIN {$wpdb->posts} AS p ON tr.object_id = p.ID
        WHERE tt.taxonomy = %s AND p.post_type IN ('" . implode("','", array_map('esc_sql', $post_types)) . "') AND p.post_status = 'publish'
        GROUP BY tt.term_id, p.post_type
    ", CAES_TOPICS_TAXONOMY);
    
    $results = $wpdb->get_results($sql);
    if (!$results) return [];

    foreach ($results as $result) {
        $term_id = (int)$result->term_id;
        if (!isset($counts[$term_id])) {
            $counts[$term_id] = array_fill_keys($post_types, 0);
        }
        $counts[$term_id][$result->post_type] = (int)$result->post_count;
    }
    return $counts;
}

function caes_build_hierarchy(array $topics) {
    $hierarchy = [];
    $children_of = [];
    foreach ($topics as &$topic) {
        $topic['children'] = [];
        $children_of[$topic['term']->parent][] = &$topic;
    }
    unset($topic);

    foreach ($topics as &$topic) {
        if (isset($children_of[$topic['term']->term_id])) {
            $topic['children'] = $children_of[$topic['term']->term_id];
        }
    }
    unset($topic);

    return $children_of[0] ?? [];
}

// =============================================================================
// Display Functions
// =============================================================================

function caes_display_topics_hierarchy(array $hierarchy, array $all_topics, $level = 0) {
    if (empty($hierarchy)) {
        if ($level === 0) echo '<p>No topics found matching the criteria.</p>';
        return;
    }
    
    foreach ($hierarchy as $topic_data) {
        $topic = $topic_data['term'];
        $is_active = (bool)$topic_data['is_active'];
        $item_class = 'caes-topic-item' . ($is_active ? '' : ' caes-topic-inactive');
        $parent_id = (int)$topic->parent;
        $parent_name = null;

        if ($parent_id > 0 && isset($all_topics[$parent_id])) {
            $parent_name = $all_topics[$parent_id]['term']->name;
        }

        ?>
        <div class="<?php echo esc_attr($item_class); ?>">
            <div class="caes-topic-header">
                <div class="caes-topic-name"><?php echo esc_html(str_repeat('— ', $level) . $topic->name); ?></div>
                <div class="caes-status-badge <?php echo $is_active ? 'caes-status-active' : 'caes-status-inactive'; ?>">
                    <?php echo $is_active ? 'Active' : 'Inactive'; ?>
                </div>
            </div>

            <?php if ($parent_name) : ?>
                <div class="caes-topic-parent">
                    <strong>Parent:</strong> <?php echo esc_html($parent_name); ?>
                </div>
            <?php endif; ?>
            
            <div class="caes-counts"><?php caes_render_post_counts($topic->slug, $topic_data['counts']); ?></div>

            <div class="caes-meta-data" style="font-family: monospace; font-size: 11px; margin-top: 10px; color: #555; background: #f7f7f7; padding: 5px; border-radius: 3px;">
                <strong>Meta Data:</strong>
                <?php if (empty($topic_data['meta'])): ?>
                    <span style="font-style: italic;">(No meta data found)</span>
                <?php else: ?>
                    <pre style="white-space: pre-wrap; word-break: break-all; margin: 0;"><?php echo esc_html(json_encode($topic_data['meta'], JSON_PRETTY_PRINT)); ?></pre>
                <?php endif; ?>
            </div>
            <div class="caes-topic-actions" style="margin-top: 10px;">
                <a href="<?php echo esc_url(get_edit_term_link($topic->term_id, CAES_TOPICS_TAXONOMY)); ?>" class="button button-small">Edit Topic</a>
            </div>
        </div>
        <?php
        if (!empty($topic_data['children'])) {
            caes_display_topics_hierarchy($topic_data['children'], $all_topics, $level + 1);
        }
    }
}

function caes_render_post_counts($topic_slug, array $counts) {
    $post_types_map = ['post' => 'Stories', 'publications' => 'Publications', 'shorthand_story' => 'Features'];
    $total_count = 0;

    foreach ($post_types_map as $post_type => $label) {
        $count = $counts[$post_type] ?? 0;
        $total_count += $count;
        $url = admin_url('edit.php?post_type=' . $post_type . '&' . CAES_TOPICS_TAXONOMY . '=' . $topic_slug);
        
        echo '<div class="caes-count-item">';
        echo '<strong>' . esc_html($label) . ':</strong> ';
        echo ($count > 0) ? '<a href="' . esc_url($url) . '">' . esc_html($count) . '</a>' : esc_html($count);
        echo '</div>';
    }
    
    echo '<div class="caes-count-item"><strong>Total:</strong> ' . esc_html($total_count) . '</div>';
}