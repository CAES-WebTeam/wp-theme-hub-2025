<?php
/**
 * Topics Taxonomy Manager
 *
 * This file creates an admin tool to manage and view topics data with a focus on
 * performance and a clean, hierarchical display. It includes post counts and
 * a status indicator based on the 'active' ACF field.
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
define('CAES_TOPICS_CACHE_KEY', 'caes_topics_data_cache_v8'); // Cache key updated for consistency fix
define('CAES_TOPICS_CACHE_TTL', 15 * MINUTE_IN_SECONDS);

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
 * Handles the cache refresh action on admin_init, before headers are sent.
 */
add_action('admin_init', 'caes_handle_topics_cache_refresh');
function caes_handle_topics_cache_refresh() {
    if (
        isset($_GET['page']) && $_GET['page'] === 'caes-topics-manager' &&
        isset($_GET['refresh']) && $_GET['refresh'] === '1' &&
        isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'caes_refresh_cache')
    ) {
        caes_clear_topics_cache();
        wp_safe_redirect(admin_url('admin.php?page=caes-topics-manager'));
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

    $data = caes_get_topics_data_with_cache();
    $refresh_nonce = wp_create_nonce('caes_refresh_cache');
    $refresh_url = add_query_arg(['refresh' => '1', '_wpnonce' => $refresh_nonce], admin_url('admin.php?page=caes-topics-manager'));
    
    ?>
    <div class="wrap">
        <h1>
            Topics Manager
            <a href="<?php echo esc_url($refresh_url); ?>" class="page-title-action">Refresh Data</a>
        </h1>

        <div class="caes-summary-cards">
            <div class="caes-summary-card">
                <h3>Total Topics</h3>
                <div class="number"><?php echo (int) $data['summary']['total_topics']; ?></div>
            </div>
            <div class="caes-summary-card">
                <h3>Active Topics</h3>
                <div class="number"><?php echo (int) $data['summary']['active_topics']; ?></div>
            </div>
            <div class="caes-summary-card">
                <h3>Inactive Topics</h3>
                <div class="number"><?php echo (int) $data['summary']['inactive_topics']; ?></div>
            </div>
        </div>

        <h2 class="nav-tab-wrapper">
            <a href="#hierarchy" class="nav-tab nav-tab-active">Hierarchy View</a>
            <a href="#inactive" class="nav-tab">Inactive Items</a>
        </h2>
        
        <div id="hierarchy" class="caes-tab-content active">
            <?php caes_display_topics_hierarchy($data['hierarchy']); ?>
        </div>

        <div id="inactive" class="caes-tab-content">
            <?php caes_display_topics_inactive($data['topics']); ?>
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
        .caes-topic-inactive { opacity: 0.6; background-color: #f9f9f9; }
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
        return ['topics' => [], 'summary' => ['total_topics' => 'ACF not found'], 'hierarchy' => []];
    }

    $topics = get_terms([
        'taxonomy'   => CAES_TOPICS_TAXONOMY,
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC'
    ]);

    if (is_wp_error($topics)) {
        return ['topics' => [], 'summary' => [], 'hierarchy' => []];
    }

    $post_counts = caes_get_all_topic_post_counts();
    $processed_topics = [];
    $active_count = 0;
    $inactive_count = 0;

    foreach ($topics as $topic) {
        $term_id = (int)$topic->term_id;
        $meta = get_fields('term_' . $term_id);
        $is_active = caes_is_topic_active_from_meta($meta);

        if ($is_active) {
            $active_count++;
        } else {
            $inactive_count++;
        }

        $processed_topics[$term_id] = [
            'term'      => $topic,
            'is_active' => $is_active,
            'counts'    => $post_counts[$term_id] ?? ['post' => 0, 'publications' => 0, 'shorthand_story' => 0],
            'meta'      => $meta ?: []
        ];
    }

    $hierarchy = caes_build_hierarchy($processed_topics);

    return [
        'topics'    => $processed_topics,
        'summary'   => [
            'total_topics'    => count($topics),
            'active_topics'   => $active_count,
            'inactive_topics' => $inactive_count
        ],
        'hierarchy' => $hierarchy
    ];
}

/**
 * Checks if a topic is active based on its meta data, checking for both
 * 'is_active' and 'active' keys for consistency.
 *
 * @param array|false $meta_array The term meta data array from ACF.
 * @return bool True if active, false otherwise.
 */
function caes_is_topic_active_from_meta($meta_array) {
    if (!is_array($meta_array)) {
        return true; // Default to active if no meta fields exist.
    }

    // Check for 'is_active' first.
    if (isset($meta_array['is_active'])) {
        return (bool)$meta_array['is_active'];
    }

    // Fallback to checking for 'active'.
    if (isset($meta_array['active'])) {
        return (bool)$meta_array['active'];
    }

    // Default to active if neither key is found.
    return true;
}


function caes_get_all_topic_post_counts() {
    global $wpdb;
    $post_types = ['post', 'publications', 'shorthand_story'];
    $counts = [];

    foreach ($post_types as $post_type) {
        $sql = $wpdb->prepare("
            SELECT tt.term_id, COUNT(p.ID) AS post_count
            FROM {$wpdb->term_taxonomy} AS tt
            INNER JOIN {$wpdb->term_relationships} AS tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$wpdb->posts} AS p ON tr.object_id = p.ID
            WHERE tt.taxonomy = %s AND p.post_type = %s AND p.post_status = 'publish'
            GROUP BY tt.term_id
        ", CAES_TOPICS_TAXONOMY, $post_type);
        
        $results = $wpdb->get_results($sql);
        if (!is_array($results)) continue;

        foreach ($results as $result) {
            $term_id = (int)$result->term_id;
            $counts[$term_id] = $counts[$term_id] ?? ['post' => 0, 'publications' => 0, 'shorthand_story' => 0];
            $counts[$term_id][$post_type] = (int)$result->post_count;
        }
    }
    return $counts;
}

function caes_build_hierarchy($topics) {
    $hierarchy = [];
    $children_of = [];
    foreach ($topics as $id => &$topic) {
        $topic['children'] = [];
        $children_of[$topic['term']->parent][] = &$topic;
    }
    unset($topic);

    foreach ($topics as $id => &$topic) {
        if (isset($children_of[$id])) {
            $topic['children'] = $children_of[$id];
        }
    }
    unset($topic);

    return $children_of[0] ?? [];
}

// =============================================================================
// Display Functions
// =============================================================================

function caes_display_topics_hierarchy($hierarchy, $level = 0) {
    if (empty($hierarchy)) {
        if ($level === 0) echo '<p>No topics found.</p>';
        return;
    }
    
    foreach ($hierarchy as $topic_data) {
        $topic = $topic_data['term'];
        $is_active = (bool)$topic_data['is_active'];
        $counts = $topic_data['counts'];
        $meta = $topic_data['meta'];
        $indent = str_repeat('— ', $level);
        $item_class = 'caes-topic-item' . ($is_active ? '' : ' caes-topic-inactive');
        ?>
        <div class="<?php echo esc_attr($item_class); ?>">
            <div class="caes-topic-header">
                <div class="caes-topic-name"><?php echo esc_html($indent . $topic->name); ?></div>
                <div class="caes-status-badge <?php echo $is_active ? 'caes-status-active' : 'caes-status-inactive'; ?>">
                    <?php echo $is_active ? 'Active' : 'Inactive'; ?>
                </div>
            </div>
            
            <div class="caes-counts"><?php caes_render_post_counts($topic->slug, $counts); ?></div>

            <div class="caes-meta-data" style="font-family: monospace; font-size: 11px; margin-top: 10px; color: #555; background: #f7f7f7; padding: 5px; border-radius: 3px;">
                <strong>Meta Data:</strong>
                <?php if (empty($meta)): ?>
                    <span style="font-style: italic;">(No meta data found)</span>
                <?php else: ?>
                    <pre style="white-space: pre-wrap; word-break: break-all;"><?php echo esc_html(json_encode($meta, JSON_PRETTY_PRINT)); ?></pre>
                <?php endif; ?>
            </div>
            <div class="caes-topic-actions" style="margin-top: 10px;">
                <a href="<?php echo esc_url(get_edit_term_link($topic->term_id, CAES_TOPICS_TAXONOMY)); ?>" class="button button-small">Edit Topic</a>
            </div>
        </div>
        <?php
        if (!empty($topic_data['children'])) {
            caes_display_topics_hierarchy($topic_data['children'], $level + 1);
        }
    }
}

function caes_display_topics_inactive($topics_data) {
    $inactive_topics = array_filter($topics_data, fn($data) => !(bool)$data['is_active']);

    if (empty($inactive_topics)) {
        echo '<p>No inactive topics found.</p>';
        return;
    }

    foreach ($inactive_topics as $topic_data) {
        $topic = $topic_data['term'];
        $counts = $topic_data['counts'];
        $meta = $topic_data['meta'];
        ?>
        <div class="caes-topic-item caes-topic-inactive">
            <div class="caes-topic-header">
                <div class="caes-topic-name"><?php echo esc_html($topic->name); ?></div>
                <div class="caes-status-badge caes-status-inactive">Inactive</div>
            </div>
            <div class="caes-counts"><?php caes_render_post_counts($topic->slug, $counts); ?></div>
            <div class="caes-meta-data" style="font-family: monospace; font-size: 11px; margin-top: 10px; color: #555; background: #f7f7f7; padding: 5px; border-radius: 3px;">
                <strong>Meta Data:</strong>
                <?php if (empty($meta)): ?>
                    <span style="font-style: italic;">(No meta data found)</span>
                <?php else: ?>
                    <pre style="white-space: pre-wrap; word-break: break-all;"><?php echo esc_html(json_encode($meta, JSON_PRETTY_PRINT)); ?></pre>
                <?php endif; ?>
            </div>
            <div class="caes-topic-actions" style="margin-top: 10px;">
                <a href="<?php echo esc_url(get_edit_term_link($topic->term_id, CAES_TOPICS_TAXONOMY)); ?>" class="button button-small">Edit Topic</a>
            </div>
        </div>
        <?php
    }
}

function caes_render_post_counts($topic_slug, $counts) {
    $post_types_map = [
        'post'              => 'Stories',
        'publications'      => 'Publications',
        'shorthand_story'   => 'Features'
    ];
    $total_count = 0;

    foreach ($post_types_map as $post_type => $label) {
        $count = $counts[$post_type] ?? 0;
        $total_count += $count;
        $url = admin_url('edit.php?post_type=' . $post_type . '&' . CAES_TOPICS_TAXONOMY . '=' . $topic_slug);
        
        echo '<div class="caes-count-item">';
        echo '<strong>' . esc_html($label) . ':</strong> ';
        if ($count > 0) {
            echo '<a href="' . esc_url($url) . '">' . esc_html($count) . '</a>';
        } else {
            echo esc_html($count);
        }
        echo '</div>';
    }
    
    echo '<div class="caes-count-item">';
    echo '<strong>Total:</strong> ' . esc_html($total_count);
    echo '</div>';
}