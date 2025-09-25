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
define('CAES_TOPICS_CACHE_KEY', 'caes_topics_data_cache_v21'); // Cache key updated for clearer content type breakdowns
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
            echo '<div class="notice notice-success is-dismissible"><p><strong>Synchronization complete. ' . $updated_count . ' topics were updated. Click "Refresh Data" to see the changes.</strong></p></div>';
        } elseif ($_GET['message'] === 'sync-error') {
            $error_code = isset($_GET['code']) ? (int)$_GET['code'] : 0;
            $error_message = 'An unknown error occurred during synchronization.';
            if ($error_code === 1) $error_message = '<strong>Sync Error:</strong> Could not reach the API endpoint.';
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
    $duplicates = $data['duplicates'] ?? [];
    $parent_topics = $data['parent_topics'] ?? [];

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
            <div class="caes-summary-card">
                <h3>Parent Topics</h3>
                <div class="number"><?php echo (int) ($data['summary']['parent_topics'] ?? 0); ?></div>
            </div>
            <div class="caes-summary-card">
                <h3>Duplicate Groups</h3>
                <div class="number"><?php echo (int) ($data['summary']['duplicate_groups'] ?? 0); ?></div>
            </div>
        </div>

        <h2 class="nav-tab-wrapper">
            <a href="#active" class="nav-tab nav-tab-active">Active Items</a>
            <a href="#inactive" class="nav-tab">Inactive Items</a>
            <a href="#parents" class="nav-tab">Parent Topics</a>
            <a href="#duplicates" class="nav-tab">Duplicates <?php if ($data['summary']['duplicate_groups'] > 0) echo '<span class="caes-duplicate-badge">' . $data['summary']['duplicate_groups'] . '</span>'; ?></a>
            <a href="#all" class="nav-tab">All Items (Hierarchy)</a>
        </h2>
        
        <div id="active" class="caes-tab-content active">
            <?php caes_display_topics_hierarchy($active_hierarchy, $all_topics); ?>
        </div>

        <div id="inactive" class="caes-tab-content">
            <?php caes_display_topics_hierarchy($inactive_hierarchy, $all_topics); ?>
        </div>

        <div id="parents" class="caes-tab-content">
            <?php caes_display_parent_topics($parent_topics, $all_topics); ?>
        </div>

        <div id="duplicates" class="caes-tab-content">
            <?php caes_display_duplicates($duplicates, $all_topics); ?>
        </div>

        <div id="all" class="caes-tab-content">
            <?php caes_display_topics_hierarchy($full_hierarchy, $all_topics); ?>
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
        .caes-topic-header { display: flex; justify-content: space-between; align-items: center; }
        .caes-topic-name { font-weight: bold; font-size: 16px; margin-bottom: 10px; }
        .caes-topic-parent { font-size: 14px; font-weight: bold; color: #135e96; margin-bottom: 12px; padding: 5px 8px; background-color: #f0f0f1; border-radius: 3px; display: inline-block; }
        .caes-topic-new-note { font-size: 12px; color: #787c82; margin-bottom: 15px; padding: 8px; background-color: #f0f6fc; border-left: 4px solid #72aee6; }
        .caes-topic-inactive { opacity: 0.7; background-color: #fefefe; }
        .caes-status-badge { padding: 4px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .caes-status-active { background-color: #d4edda; color: #155724; }
        .caes-status-inactive { background-color: #f8d7da; color: #721c24; }
        .caes-counts { margin-top: 15px; }
        .caes-counts-header { font-size: 13px; color: #666; margin-bottom: 8px; }
        .caes-counts-items { display: flex; gap: 20px; flex-wrap: wrap; }
        .caes-count-item { padding: 5px 10px; background-color: #f0f0f1; border-radius: 3px; font-size: 12px; }
        .caes-counts a { color: #135e96; text-decoration: none; }
        .caes-counts a:hover { text-decoration: underline; }
        
        /* Parent topics styles */
        .caes-parent-topic-item { background: #fff; border: 1px solid #2271b1; margin-bottom: 20px; padding: 15px; border-radius: 4px; }
        .caes-children-count { font-size: 14px; color: #666; font-weight: normal; margin-left: 10px; }
        .caes-children-summary { margin-top: 15px; }
        .caes-toggle-children { display: flex; align-items: center; gap: 8px; }
        .caes-children-summary-text { font-size: 12px; color: #666; font-weight: normal; }
        .caes-children-list { margin-top: 15px; padding-top: 15px; border-top: 1px solid #e9ecef; }
        .caes-children-grid { display: grid; gap: 10px; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); }
        .caes-child-topic { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 3px; padding: 10px; display: flex; justify-content: space-between; align-items: center; }
        .caes-child-active { border-left: 4px solid #28a745; }
        .caes-child-inactive { border-left: 4px solid #dc3545; opacity: 0.8; }
        .caes-child-name { flex: 1; }
        .caes-child-breakdown { font-size: 12px; color: #666; font-weight: normal; margin-top: 3px; }
        .caes-child-status { display: flex; gap: 8px; align-items: center; }
        .caes-status-small { padding: 2px 6px; font-size: 10px; }
        
        /* Duplicate-specific styles */
        .caes-duplicate-group { background: #fff; border: 1px solid #dc7e00; margin-bottom: 20px; border-radius: 4px; overflow: hidden; }
        .caes-duplicate-group-header { background-color: #fef7e6; padding: 15px; border-bottom: 1px solid #dc7e00; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .caes-duplicate-group-title { font-size: 18px; font-weight: bold; color: #dc7e00; margin: 0; }
        .caes-duplicate-group-count { font-size: 14px; color: #666; margin: 5px 0 0 0; }
        .caes-duplicate-item { padding: 15px; border-bottom: 1px solid #f0f0f1; background: #fff; }
        .caes-duplicate-item:last-child { border-bottom: none; }
        .caes-duplicate-item-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
        .caes-duplicate-item-details { display: flex; gap: 15px; flex-wrap: wrap; align-items: center; }
        .caes-duplicate-badge { background-color: #dc7e00; color: white; padding: 2px 6px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .caes-duplicate-id { font-size: 12px; color: #666; margin-left: 10px; }
        .caes-no-duplicates { text-align: center; padding: 40px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; color: #666; }
        
        /* Content Comparison styles */
        .caes-content-comparison { background-color: #f8f9fa; border-top: 1px solid #dc7e00; padding: 20px; }
        .caes-content-comparison-header { margin-bottom: 20px; }
        .caes-content-comparison-header h4 { margin: 0 0 5px 0; color: #dc7e00; }
        .caes-content-comparison-header p { margin: 0; font-size: 14px; color: #666; }
        .caes-content-analysis-section { background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin-bottom: 15px; }
        .caes-content-analysis-title { margin: 0 0 15px 0; font-size: 16px; color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .caes-content-analysis-summary { display: flex; gap: 20px; margin-bottom: 15px; flex-wrap: wrap; }
        .caes-analysis-stat { padding: 5px 10px; background-color: #f0f0f1; border-radius: 3px; font-size: 12px; }
        .caes-stat-shared { background-color: #fff3cd; color: #856404; }
        .caes-stat-unique { background-color: #d1ecf1; color: #0c5460; }
        .caes-shared-content, .caes-unique-content { margin-bottom: 15px; }
        .caes-shared-content h6, .caes-unique-content h6 { margin: 0 0 10px 0; font-size: 14px; color: #333; }
        .caes-post-list { max-height: 200px; overflow-y: auto; border: 1px solid #eee; border-radius: 3px; }
        .caes-post-item { padding: 8px 12px; border-bottom: 1px solid #f0f0f1; display: flex; justify-content: space-between; align-items: center; }
        .caes-post-item:last-child { border-bottom: none; }
        .caes-post-item a { text-decoration: none; color: #135e96; font-weight: 500; }
        .caes-post-item a:hover { text-decoration: underline; }
        .caes-post-date { font-size: 11px; color: #666; }
        .caes-shared-post { background-color: #fff3cd; }
        .caes-unique-post { background-color: #d1ecf1; }
        .caes-toggle-content-comparison { margin-top: 10px; }
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
            
            // Handle content comparison toggle
            $('.caes-toggle-content-comparison').on('click', function(e) {
                e.preventDefault();
                var target = '#' + $(this).data('target');
                var $button = $(this);
                var $content = $(target);
                
                if ($content.is(':visible')) {
                    $content.slideUp();
                    $button.text('Show Content Comparison');
                } else {
                    $content.slideDown();
                    $button.text('Hide Content Comparison');
                }
            });
            
            // Handle children topics toggle
            $('.caes-toggle-children').on('click', function(e) {
                e.preventDefault();
                var target = '#' + $(this).data('target');
                var $button = $(this);
                var $content = $(target);
                var $toggleText = $button.find('.caes-toggle-text');
                
                if ($content.is(':visible')) {
                    $content.slideUp();
                    $toggleText.text('Show Children');
                } else {
                    $content.slideDown();
                    $toggleText.text('Hide Children');
                }
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
            $current_status = get_field('active', $term_ref);
            $new_status = isset($api_item['IS_ACTIVE']) ? (bool)$api_item['IS_ACTIVE'] : false;

            if ($current_status !== $new_status) {
                update_field('active', $new_status, $term_ref);
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
        return ['topics' => [], 'summary' => [], 'hierarchy' => [], 'duplicates' => [], 'parent_topics' => []];
    }

    $topics = get_terms(['taxonomy' => CAES_TOPICS_TAXONOMY, 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC']);
    if (is_wp_error($topics)) return ['topics' => [], 'summary' => [], 'hierarchy' => [], 'duplicates' => [], 'parent_topics' => []];

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

    // Find duplicates
    $duplicates = caes_find_duplicates($processed_topics);
    
    // Find parent topics
    $parent_topics = caes_find_parent_topics($processed_topics);

    return [
        'topics'    => $processed_topics,
        'summary'   => [
            'total_topics' => count($topics), 
            'active_topics' => $active_count, 
            'inactive_topics' => $inactive_count,
            'parent_topics' => count($parent_topics),
            'duplicate_groups' => count($duplicates)
        ],
        'hierarchy' => caes_build_hierarchy($processed_topics),
        'duplicates' => $duplicates,
        'parent_topics' => $parent_topics
    ];
}

function caes_is_topic_active_from_meta($meta_array) {
    if (!is_array($meta_array)) return true;
    return isset($meta_array['active']) ? (bool)$meta_array['active'] : true;
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

/**
 * Find parent topics (topics that have children)
 */
function caes_find_parent_topics(array $topics) {
    $parent_ids = [];
    $parent_topics = [];
    
    // First, collect all parent IDs
    foreach ($topics as $topic_data) {
        $parent_id = (int)$topic_data['term']->parent;
        if ($parent_id > 0) {
            $parent_ids[$parent_id] = true;
        }
    }
    
    // Now build parent topics with their children
    foreach ($topics as $topic_data) {
        $term_id = (int)$topic_data['term']->term_id;
        if (isset($parent_ids[$term_id])) {
            // This topic is a parent
            $children = [];
            foreach ($topics as $child_data) {
                if ((int)$child_data['term']->parent === $term_id) {
                    $children[] = $child_data;
                }
            }
            
            $parent_topics[$term_id] = [
                'parent_data' => $topic_data,
                'children' => $children,
                'children_count' => count($children)
            ];
        }
    }
    
    // Sort by parent name
    uasort($parent_topics, function($a, $b) {
        return strcmp($a['parent_data']['term']->name, $b['parent_data']['term']->name);
    });
    
    return $parent_topics;
}

/**
 * Display parent topics with their children
 */
function caes_display_parent_topics(array $parent_topics, array $all_topics) {
    if (empty($parent_topics)) {
        echo '<p>No parent topics found. All topics are at the root level.</p>';
        return;
    }
    
    foreach ($parent_topics as $parent_id => $parent_info) {
        $parent_data = $parent_info['parent_data'];
        $parent_term = $parent_data['term'];
        $children = $parent_info['children'];
        $is_active = (bool)$parent_data['is_active'];
        $item_class = 'caes-parent-topic-item' . ($is_active ? '' : ' caes-topic-inactive');
        
        // Count active/inactive children
        $active_children = 0;
        $inactive_children = 0;
        foreach ($children as $child_data) {
            if ($child_data['is_active']) {
                $active_children++;
            } else {
                $inactive_children++;
            }
        }
        
        // Get parent's own parent if it exists
        $grandparent_name = null;
        if ($parent_term->parent > 0 && isset($all_topics[$parent_term->parent])) {
            $grandparent_name = $all_topics[$parent_term->parent]['term']->name;
        }
        
        $is_new_topic = empty($parent_data['meta']['topic_id']);
        $children_id = 'children-' . $parent_id;
        
        ?>
        <div class="<?php echo esc_attr($item_class); ?>">
            <div class="caes-topic-header">
                <div class="caes-topic-name">
                    <?php echo esc_html($parent_term->name); ?>
                    <span class="caes-children-count">(<?php echo count($children); ?> children)</span>
                </div>
                <div class="caes-status-badge <?php echo $is_active ? 'caes-status-active' : 'caes-status-inactive'; ?>">
                    <?php echo $is_active ? 'Active' : 'Inactive'; ?>
                </div>
            </div>
            
            <?php if ($grandparent_name) : ?>
                <div class="caes-topic-parent">
                    Parent: <?php echo esc_html($grandparent_name); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($is_new_topic) : ?>
                <div class="caes-topic-new-note">
                    <strong>Note:</strong> This is a new topic and was not imported from the previous system.
                </div>
            <?php endif; ?>
            
            <div class="caes-counts">
                <div class="caes-counts-header">
                    <strong>Content tagged directly to this parent topic:</strong>
                </div>
                <div class="caes-counts-items">
                    <?php caes_render_post_counts($parent_term->slug, $parent_data['counts']); ?>
                </div>
            </div>
            
            <div class="caes-children-summary">
                <button class="caes-toggle-children button button-secondary" data-target="<?php echo esc_attr($children_id); ?>">
                    <span class="caes-toggle-text">Show Children</span>
                    <span class="caes-children-summary-text">
                        (<?php echo $active_children; ?> active, <?php echo $inactive_children; ?> inactive)
                    </span>
                </button>
            </div>
            
            <div id="<?php echo esc_attr($children_id); ?>" class="caes-children-list" style="display: none;">
                <div class="caes-children-grid">
                    <?php foreach ($children as $child_data) : 
                        $child_term = $child_data['term'];
                        $child_is_active = (bool)$child_data['is_active'];
                        $child_counts = $child_data['counts'];
                        $child_breakdown = caes_get_content_breakdown($child_counts);
                    ?>
                        <div class="caes-child-topic <?php echo $child_is_active ? 'caes-child-active' : 'caes-child-inactive'; ?>">
                            <div class="caes-child-name">
                                <strong><?php echo esc_html($child_term->name); ?></strong>
                                <div class="caes-child-breakdown"><?php echo esc_html($child_breakdown); ?></div>
                            </div>
                            <div class="caes-child-status">
                                <span class="caes-status-badge caes-status-small <?php echo $child_is_active ? 'caes-status-active' : 'caes-status-inactive'; ?>">
                                    <?php echo $child_is_active ? 'Active' : 'Inactive'; ?>
                                </span>
                                <a href="<?php echo esc_url(get_edit_term_link($child_term->term_id, CAES_TOPICS_TAXONOMY)); ?>" class="button button-small">Edit</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="caes-topic-actions" style="margin-top: 15px;">
                <a href="<?php echo esc_url(get_edit_term_link($parent_term->term_id, CAES_TOPICS_TAXONOMY)); ?>" class="button button-small">Edit Parent Topic</a>
            </div>
        </div>
        <?php
    }
}
function caes_find_duplicates(array $topics) {
    $name_groups = [];
    
    // Group topics by name (case-insensitive)
    foreach ($topics as $topic_data) {
        $name_key = strtolower(trim($topic_data['term']->name));
        $name_groups[$name_key][] = $topic_data;
    }
    
    // Filter to only groups with more than one item
    $duplicates = [];
    foreach ($name_groups as $name_key => $group) {
        if (count($group) > 1) {
            $duplicates[$name_key] = $group;
        }
    }
    
    return $duplicates;
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

        $is_new_topic = empty($topic_data['meta']['topic_id']);

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
                    Parent: <?php echo esc_html($parent_name); ?>
                </div>
            <?php endif; ?>

            <?php if ($is_new_topic) : ?>
                <div class="caes-topic-new-note">
                    <strong>Note:</strong> This is a new topic and was not imported from the previous system.
                </div>
            <?php endif; ?>
            
            <div class="caes-counts"><?php caes_render_post_counts($topic->slug, $topic_data['counts']); ?></div>

            <div class="caes-topic-actions" style="margin-top: 15px;">
                <a href="<?php echo esc_url(get_edit_term_link($topic->term_id, CAES_TOPICS_TAXONOMY)); ?>" class="button button-small">Edit Topic</a>
            </div>
        </div>
        <?php
        if (!empty($topic_data['children'])) {
            caes_display_topics_hierarchy($topic_data['children'], $all_topics, $level + 1);
        }
    }
}

/**
 * Display duplicate groups
 */
function caes_display_duplicates(array $duplicates, array $all_topics) {
    if (empty($duplicates)) {
        echo '<div class="caes-no-duplicates">';
        echo '<h3>No Duplicate Topics Found</h3>';
        echo '<p>Great! All your topics have unique names.</p>';
        echo '</div>';
        return;
    }
    
    foreach ($duplicates as $name_key => $duplicate_group) {
        $example_name = $duplicate_group[0]['term']->name;
        $group_id = 'duplicate-group-' . sanitize_title($name_key);
        ?>
        <div class="caes-duplicate-group">
            <div class="caes-duplicate-group-header">
                <h3 class="caes-duplicate-group-title"><?php echo esc_html($example_name); ?></h3>
                <p class="caes-duplicate-group-count"><?php echo count($duplicate_group); ?> duplicates found</p>
                <button class="button button-secondary caes-toggle-content-comparison" data-target="<?php echo esc_attr($group_id); ?>">
                    Show Content Comparison
                </button>
            </div>
            
            <?php foreach ($duplicate_group as $topic_data) : 
                $topic = $topic_data['term'];
                $is_active = (bool)$topic_data['is_active'];
                $parent_id = (int)$topic->parent;
                $parent_name = 'None (Root Level)';
                
                if ($parent_id > 0 && isset($all_topics[$parent_id])) {
                    $parent_name = $all_topics[$parent_id]['term']->name;
                }
                
                $is_new_topic = empty($topic_data['meta']['topic_id']);
                ?>
                <div class="caes-duplicate-item">
                    <div class="caes-duplicate-item-header">
                        <div>
                            <strong><?php echo esc_html($topic->name); ?></strong>
                            <span class="caes-duplicate-id">(ID: <?php echo $topic->term_id; ?>)</span>
                        </div>
                        <div class="caes-status-badge <?php echo $is_active ? 'caes-status-active' : 'caes-status-inactive'; ?>">
                            <?php echo $is_active ? 'Active' : 'Inactive'; ?>
                        </div>
                    </div>
                    
                    <div class="caes-duplicate-item-details">
                        <div class="caes-topic-parent">
                            Parent: <?php echo esc_html($parent_name); ?>
                        </div>
                        
                        <?php if ($is_new_topic) : ?>
                            <span class="caes-duplicate-badge">New Topic</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="caes-counts"><?php caes_render_post_counts($topic->slug, $topic_data['counts']); ?></div>
                    
                    <div class="caes-topic-actions" style="margin-top: 15px;">
                        <a href="<?php echo esc_url(get_edit_term_link($topic->term_id, CAES_TOPICS_TAXONOMY)); ?>" class="button button-small">Edit Topic</a>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Content Comparison Section -->
            <div id="<?php echo esc_attr($group_id); ?>" class="caes-content-comparison" style="display: none;">
                <div class="caes-content-comparison-header">
                    <h4>Content Analysis</h4>
                    <p>This shows which content is unique to each duplicate and which content is shared.</p>
                </div>
                <?php caes_display_duplicate_content_analysis($duplicate_group); ?>
            </div>
        </div>
        <?php
    }
}

/**
 * Display content analysis for duplicate terms
 */
function caes_display_duplicate_content_analysis(array $duplicate_group) {
    $post_types = ['post' => 'Stories', 'publications' => 'Publications', 'shorthand_story' => 'Features'];
    $term_posts = [];
    
    // Get all posts for each duplicate term
    foreach ($duplicate_group as $index => $topic_data) {
        $term_id = $topic_data['term']->term_id;
        $term_posts[$index] = caes_get_posts_for_term($term_id);
    }
    
    foreach ($post_types as $post_type => $label) {
        $analysis = caes_analyze_duplicate_content($term_posts, $post_type);
        
        if ($analysis['total_posts'] == 0) {
            continue; // Skip if no posts of this type
        }
        
        ?>
        <div class="caes-content-analysis-section">
            <h5 class="caes-content-analysis-title"><?php echo esc_html($label); ?> Analysis</h5>
            
            <div class="caes-content-analysis-summary">
                <span class="caes-analysis-stat">
                    <strong>Total <?php echo esc_html($label); ?>:</strong> <?php echo $analysis['total_posts']; ?>
                </span>
                <span class="caes-analysis-stat caes-stat-shared">
                    <strong>Shared:</strong> <?php echo $analysis['shared_count']; ?>
                </span>
                <span class="caes-analysis-stat caes-stat-unique">
                    <strong>Unique:</strong> <?php echo $analysis['unique_count']; ?>
                </span>
            </div>
            
            <?php if (!empty($analysis['shared_posts'])) : ?>
                <div class="caes-shared-content">
                    <h6>Content Tagged with BOTH Terms (<?php echo count($analysis['shared_posts']); ?> items)</h6>
                    <div class="caes-post-list">
                        <?php foreach ($analysis['shared_posts'] as $post) : ?>
                            <div class="caes-post-item caes-shared-post">
                                <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>" target="_blank">
                                    <?php echo esc_html($post->post_title); ?>
                                </a>
                                <span class="caes-post-date">(<?php echo get_the_date('M j, Y', $post); ?>)</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php foreach ($duplicate_group as $index => $topic_data) : 
                if (empty($analysis['unique_posts'][$index])) continue;
                
                // Get parent and status information for this specific term
                $parent_id = (int)$topic_data['term']->parent;
                $parent_info = '';
                if ($parent_id > 0) {
                    // Try to get parent name from all_topics first, fallback to direct query
                    if (isset($all_topics[$parent_id])) {
                        $parent_name = $all_topics[$parent_id]['term']->name;
                    } else {
                        $parent_term = get_term($parent_id);
                        $parent_name = $parent_term && !is_wp_error($parent_term) ? $parent_term->name : 'Unknown Parent';
                    }
                    $parent_info = ' (Parent: ' . $parent_name . ')';
                } else {
                    $parent_info = ' (Root Level)';
                }
                
                $status = $topic_data['is_active'] ? 'Active' : 'Inactive';
                $status_info = ' - ' . $status;
                ?>
                <div class="caes-unique-content">
                    <h6>Content ONLY in "<?php echo esc_html($topic_data['term']->name); ?>"<?php echo esc_html($parent_info . $status_info); ?> (<?php echo count($analysis['unique_posts'][$index]); ?> items)</h6>
                    <div class="caes-post-list">
                        <?php foreach ($analysis['unique_posts'][$index] as $post) : ?>
                            <div class="caes-post-item caes-unique-post">
                                <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>" target="_blank">
                                    <?php echo esc_html($post->post_title); ?>
                                </a>
                                <span class="caes-post-date">(<?php echo get_the_date('M j, Y', $post); ?>)</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
}

/**
 * Get all posts for a specific term
 */
function caes_get_posts_for_term($term_id) {
    $post_types = ['post', 'publications', 'shorthand_story'];
    $all_posts = [];
    
    foreach ($post_types as $post_type) {
        $posts = get_posts([
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'tax_query' => [
                [
                    'taxonomy' => CAES_TOPICS_TAXONOMY,
                    'field' => 'term_id',
                    'terms' => $term_id,
                ],
            ],
        ]);
        
        foreach ($posts as $post) {
            $all_posts[$post_type][] = $post;
        }
    }
    
    return $all_posts;
}

/**
 * Analyze content overlap between duplicate terms for a specific post type
 */
function caes_analyze_duplicate_content($term_posts, $post_type) {
    $all_post_ids = [];
    $term_post_ids = [];
    
    // Collect all post IDs for this post type from all terms
    foreach ($term_posts as $index => $posts_by_type) {
        $term_post_ids[$index] = [];
        if (isset($posts_by_type[$post_type])) {
            foreach ($posts_by_type[$post_type] as $post) {
                $post_id = $post->ID;
                $all_post_ids[$post_id] = $post;
                $term_post_ids[$index][] = $post_id;
            }
        }
    }
    
    if (empty($all_post_ids)) {
        return [
            'total_posts' => 0,
            'shared_count' => 0,
            'unique_count' => 0,
            'shared_posts' => [],
            'unique_posts' => [],
        ];
    }
    
    // Find shared posts (posts that appear in multiple terms)
    $shared_post_ids = [];
    $unique_posts = [];
    
    foreach ($all_post_ids as $post_id => $post_obj) {
        $appears_in_terms = [];
        foreach ($term_post_ids as $term_index => $post_ids) {
            if (in_array($post_id, $post_ids)) {
                $appears_in_terms[] = $term_index;
            }
        }
        
        if (count($appears_in_terms) > 1) {
            // Shared post
            $shared_post_ids[] = $post_id;
        } else {
            // Unique to one term
            $unique_posts[$appears_in_terms[0]][] = $post_obj;
        }
    }
    
    $shared_posts = [];
    foreach ($shared_post_ids as $post_id) {
        $shared_posts[] = $all_post_ids[$post_id];
    }
    
    $unique_count = 0;
    foreach ($unique_posts as $posts) {
        $unique_count += count($posts);
    }
    
    return [
        'total_posts' => count($all_post_ids),
        'shared_count' => count($shared_posts),
        'unique_count' => $unique_count,
        'shared_posts' => $shared_posts,
        'unique_posts' => $unique_posts,
    ];
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