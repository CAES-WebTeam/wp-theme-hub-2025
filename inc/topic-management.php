<?php
/**
 * Topics Taxonomy Admin Tool - Secured Version
 * Add this to your theme's functions.php or create as a plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Security constants
define('CAES_TOPICS_NONCE_ACTION', 'caes_topics_admin_action');
define('CAES_TOPICS_CAPABILITY', 'manage_options');

// Add admin menu under CAES Tools
add_action('admin_menu', 'caes_topics_admin_menu');
function caes_topics_admin_menu() {
    add_submenu_page(
        'caes-tools', // Parent slug (assuming CAES Tools exists)
        'Topics Manager',
        'Topics Manager',
        CAES_TOPICS_CAPABILITY,
        'caes-topics-manager',
        'caes_topics_manager_page'
    );
}

// Security check function
function caes_topics_security_check() {
    if (!current_user_can(CAES_TOPICS_CAPABILITY)) {
        wp_die(__('You do not have sufficient permissions to access this page.'), 403);
    }
}

// Main admin page callback
function caes_topics_manager_page() {
    // Security check
    caes_topics_security_check();
    
    // Handle AJAX requests with nonce verification
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_topic_status') {
        caes_handle_topic_status_toggle();
        return;
    }
    
    // Check for cache refresh with nonce
    $force_refresh = false;
    if (isset($_GET['refresh']) && $_GET['refresh'] === '1') {
        // Verify nonce for cache refresh
        if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'caes_refresh_cache')) {
            $force_refresh = true;
            caes_clear_topics_cache();
        } else {
            wp_die(__('Security check failed. Please try again.'), 403);
        }
    }
    
    // Generate nonces for frontend use
    $refresh_nonce = wp_create_nonce('caes_refresh_cache');
    $ajax_nonce = wp_create_nonce(CAES_TOPICS_NONCE_ACTION);
    
    ?>
    <div class="wrap">
        <h1>
            Topics Taxonomy Manager 
            <a href="<?php echo esc_url(wp_nonce_url(
                admin_url('admin.php?page=caes-topics-manager&refresh=1'), 
                'caes_refresh_cache'
            )); ?>" class="page-title-action">Refresh Data</a>
        </h1>
        
        <div id="caes-topics-dashboard">
            <div id="caes-loading" style="display: none;">
                <p>Loading topic data... <span class="spinner is-active"></span></p>
            </div>
            
            <div class="caes-summary-cards">
                <?php caes_display_summary_cards(); ?>
            </div>
            
            <?php caes_display_performance_info(); ?>
            
            <h2 class="nav-tab-wrapper">
                <a href="#overview" class="nav-tab nav-tab-active">Overview</a>
                <a href="#hierarchy" class="nav-tab">Hierarchy View</a>
                <a href="#duplicates" class="nav-tab">Potential Duplicates</a>
                <a href="#inactive" class="nav-tab">Inactive Items</a>
            </h2>
            
            <div id="overview" class="caes-tab-content active">
                <?php caes_display_topics_overview(); ?>
            </div>
            
            <div id="hierarchy" class="caes-tab-content">
                <?php caes_display_hierarchy_view(); ?>
            </div>
            
            <div id="duplicates" class="caes-tab-content">
                <?php caes_display_duplicates(); ?>
            </div>
            
            <div id="inactive" class="caes-tab-content">
                <?php caes_display_inactive_items(); ?>
            </div>
        </div>
        
        <input type="hidden" id="caes-ajax-nonce" value="<?php echo esc_attr($ajax_nonce); ?>" />
    </div>
    
    <style>
    .caes-summary-cards {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    
    .caes-summary-card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 20px;
        min-width: 200px;
        flex: 1;
    }
    
    .caes-summary-card h3 {
        margin: 0 0 10px 0;
        font-size: 14px;
        text-transform: uppercase;
        color: #666;
    }
    
    .caes-summary-card .number {
        font-size: 32px;
        font-weight: bold;
        color: #135e96;
    }
    
    .caes-performance-info {
        background: #f0f0f1;
        border-left: 4px solid #135e96;
        padding: 10px 15px;
        margin-bottom: 20px;
        font-size: 12px;
        color: #666;
    }
    
    .caes-tab-content {
        display: none;
        margin-top: 20px;
    }
    
    .caes-tab-content.active {
        display: block;
    }
    
    .caes-topic-item {
        background: #fff;
        border: 1px solid #ccd0d4;
        margin-bottom: 10px;
        padding: 15px;
        border-radius: 4px;
    }
    
    .caes-topic-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    
    .caes-topic-name {
        font-weight: bold;
        font-size: 16px;
    }
    
    .caes-topic-inactive {
        opacity: 0.6;
        background-color: #f9f9f9;
    }
    
    .caes-status-badge {
        padding: 4px 8px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: bold;
        text-transform: uppercase;
    }
    
    .caes-status-active {
        background-color: #d4edda;
        color: #155724;
    }
    
    .caes-status-inactive {
        background-color: #f8d7da;
        color: #721c24;
    }
    
    .caes-counts {
        display: flex;
        gap: 20px;
        margin: 10px 0;
        flex-wrap: wrap;
    }
    
    .caes-count-item {
        padding: 5px 10px;
        background-color: #f0f0f1;
        border-radius: 3px;
        font-size: 12px;
    }
    
    .caes-counts a {
        color: #135e96;
        text-decoration: none;
    }
    
    .caes-counts a:hover {
        text-decoration: underline;
    }
    
    .caes-hierarchy {
        margin-left: 20px;
        border-left: 2px solid #ddd;
        padding-left: 15px;
    }
    
    .caes-duplicate-warning {
        background-color: #fff3cd;
        border: 1px solid #ffeaa7;
        border-radius: 4px;
        padding: 15px;
        margin: 10px 0;
    }
    
    .caes-parent-path {
        font-size: 12px;
        color: #666;
        margin-bottom: 5px;
    }
    
    .nav-tab-wrapper {
        margin-bottom: 0;
    }
    
    .caes-search-box {
        margin-bottom: 20px;
    }
    
    .caes-search-box input[type="text"] {
        width: 300px;
        margin-right: 10px;
    }
    
    .caes-topic-actions {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid #ddd;
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // Tab switching
        $('.nav-tab').click(function(e) {
            e.preventDefault();
            var target = $(this).attr('href');
            
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            $('.caes-tab-content').removeClass('active');
            $(target).addClass('active');
        });
        
        // Secure search functionality
        $('#caes-topic-search').on('keyup', function() {
            var searchTerm = $(this).val().toLowerCase();
            
            // Basic XSS prevention - only allow alphanumeric, spaces, and basic punctuation
            searchTerm = searchTerm.replace(/[^a-zA-Z0-9\s\-_.]/g, '');
            
            $('.caes-topic-item').each(function() {
                var topicName = $(this).find('.caes-topic-name').text().toLowerCase();
                var parentPath = $(this).find('.caes-parent-path').text().toLowerCase();
                
                if (topicName.indexOf(searchTerm) > -1 || parentPath.indexOf(searchTerm) > -1) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });
    });
    </script>
    <?php
}

// Display performance info
function caes_display_performance_info() {
    caes_topics_security_check();
    
    $cache_key = 'caes_topics_performance_info';
    $perf_info = get_transient($cache_key);
    
    if (!$perf_info) {
        $perf_info = caes_calculate_performance_info();
        set_transient($cache_key, $perf_info, 15 * MINUTE_IN_SECONDS);
    }
    
    ?>
    <div class="caes-performance-info">
        <strong>Performance:</strong> 
        Data cached for 15 minutes | 
        Queries: <?php echo (int) $perf_info['query_count']; ?> | 
        Load time: <?php echo number_format((float) $perf_info['load_time'], 2); ?>ms | 
        Memory: <?php echo number_format((float) $perf_info['memory_usage'], 2); ?>MB |
        Last updated: <?php echo esc_html($perf_info['last_updated']); ?>
    </div>
    <?php
}

// Calculate performance metrics
function caes_calculate_performance_info() {
    caes_topics_security_check();
    
    $start_time = microtime(true);
    $start_queries = get_num_queries();
    $start_memory = memory_get_usage(true);
    
    // Simulate data loading
    caes_get_cached_topics_data();
    
    $end_time = microtime(true);
    $end_queries = get_num_queries();
    $end_memory = memory_get_usage(true);
    
    return array(
        'query_count' => max(0, $end_queries - $start_queries),
        'load_time' => max(0, round(($end_time - $start_time) * 1000, 2)),
        'memory_usage' => max(0, round(($end_memory - $start_memory) / 1024 / 1024, 2)),
        'last_updated' => sanitize_text_field(current_time('H:i:s')),
    );
}

// Get cached topics data (main optimization)
function caes_get_cached_topics_data() {
    caes_topics_security_check();
    
    $cache_key = 'caes_topics_data_v3_secure';
    $cached_data = get_transient($cache_key);
    
    if ($cached_data !== false && is_array($cached_data)) {
        return $cached_data;
    }
    
    // If no cache, build the data
    $data = caes_build_topics_data();
    
    // Cache for 15 minutes
    set_transient($cache_key, $data, 15 * MINUTE_IN_SECONDS);
    
    return $data;
}

// Build comprehensive topics data in optimized way
function caes_build_topics_data() {
    caes_topics_security_check();
    
    // Get all topics in one query with proper sanitization
    $topics = get_terms(array(
        'taxonomy' => 'topics',
        'hide_empty' => false,
    ));
    
    if (is_wp_error($topics)) {
        return array(
            'topics' => array(),
            'summary' => array(
                'total_topics' => 0,
                'active_topics' => 0,
                'inactive_topics' => 0,
                'potential_duplicates' => 0,
            ),
            'duplicates' => array(),
            'hierarchy' => array(),
        );
    }
    
    // Get all post counts in single optimized query
    $post_counts = caes_get_all_topic_post_counts_optimized();
    
    // Get all term meta in one query
    $term_meta = caes_get_all_term_meta_optimized();
    
    // Build parent paths efficiently
    $parent_paths = caes_build_all_parent_paths($topics);
    
    // Process all data
    $processed_data = array(
        'topics' => array(),
        'summary' => array(
            'total_topics' => count($topics),
            'active_topics' => 0,
            'inactive_topics' => 0,
            'potential_duplicates' => 0,
        ),
        'duplicates' => array(),
        'hierarchy' => array(),
    );
    
    $names_by_parent = array();
    
    foreach ($topics as $topic) {
        if (!is_object($topic) || !isset($topic->term_id)) {
            continue;
        }
        
        $term_id = (int) $topic->term_id;
        
        // Check active status
        $is_active = isset($term_meta[$term_id]) ? 
            caes_is_topic_active_from_meta($term_meta[$term_id]) : true;
        
        if ($is_active) {
            $processed_data['summary']['active_topics']++;
        } else {
            $processed_data['summary']['inactive_topics']++;
        }
        
        // Get post counts with defaults
        $counts = isset($post_counts[$term_id]) ? $post_counts[$term_id] : 
            array('post' => 0, 'publications' => 0, 'shorthand_story' => 0);
        
        // Ensure all counts are integers
        $counts = array_map('intval', $counts);
        
        // Store processed topic
        $processed_data['topics'][$term_id] = array(
            'term' => $topic,
            'is_active' => $is_active,
            'counts' => $counts,
            'parent_path' => isset($parent_paths[$term_id]) ? 
                sanitize_text_field($parent_paths[$term_id]) : '',
        );
        
        // Check for duplicates
        $parent_key = (int) $topic->parent;
        $topic_name = sanitize_text_field($topic->name);
        
        if (!isset($names_by_parent[$parent_key])) {
            $names_by_parent[$parent_key] = array();
        }
        
        if (in_array($topic_name, $names_by_parent[$parent_key], true)) {
            $processed_data['summary']['potential_duplicates']++;
            
            // Store duplicate info
            $key = $parent_key . '|' . $topic_name;
            if (!isset($processed_data['duplicates'][$key])) {
                $processed_data['duplicates'][$key] = array();
            }
            $processed_data['duplicates'][$key][] = $topic;
        } else {
            $names_by_parent[$parent_key][] = $topic_name;
        }
    }
    
    return $processed_data;
}

// Optimized: Get all topic post counts in minimal queries with SQL injection protection
function caes_get_all_topic_post_counts_optimized() {
    global $wpdb;
    
    caes_topics_security_check();
    
    $post_types = array('post', 'publications', 'shorthand_story');
    $counts = array();
    
    // Validate taxonomy exists
    if (!taxonomy_exists('topics')) {
        return array();
    }
    
    // Single query per post type instead of per topic
    foreach ($post_types as $post_type) {
        // Validate post type
        if (!post_type_exists($post_type)) {
            continue;
        }
        
        $sql = $wpdb->prepare("
            SELECT tt.term_id, COUNT(DISTINCT p.ID) as post_count
            FROM {$wpdb->term_taxonomy} tt
            LEFT JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            LEFT JOIN {$wpdb->posts} p ON tr.object_id = p.ID 
                AND p.post_type = %s 
                AND p.post_status IN ('publish', 'private', 'draft', 'future')
            WHERE tt.taxonomy = 'topics'
            GROUP BY tt.term_id
        ", $post_type);
        
        $results = $wpdb->get_results($sql);
        
        if (!is_wp_error($results) && is_array($results)) {
            foreach ($results as $result) {
                $term_id = (int) $result->term_id;
                if (!isset($counts[$term_id])) {
                    $counts[$term_id] = array();
                }
                $counts[$term_id][$post_type] = max(0, (int) $result->post_count);
            }
        }
    }
    
    return $counts;
}

// Optimized: Get all term meta in one query with sanitization
function caes_get_all_term_meta_optimized() {
    global $wpdb;
    
    caes_topics_security_check();
    
    // Whitelist of allowed meta keys for security
    $allowed_meta_keys = array('active', 'status', 'inactive');
    $meta_key_placeholders = implode(',', array_fill(0, count($allowed_meta_keys), '%s'));
    
    $sql = $wpdb->prepare("
        SELECT term_id, meta_key, meta_value
        FROM {$wpdb->termmeta}
        WHERE meta_key IN ($meta_key_placeholders)
    ", ...$allowed_meta_keys);
    
    $results = $wpdb->get_results($sql);
    $meta_data = array();
    
    if (!is_wp_error($results) && is_array($results)) {
        foreach ($results as $result) {
            $term_id = (int) $result->term_id;
            $meta_key = sanitize_key($result->meta_key);
            $meta_value = sanitize_text_field($result->meta_value);
            
            if (!isset($meta_data[$term_id])) {
                $meta_data[$term_id] = array();
            }
            $meta_data[$term_id][$meta_key] = $meta_value;
        }
    }
    
    return $meta_data;
}

// Build all parent paths efficiently with sanitization
function caes_build_all_parent_paths($topics) {
    caes_topics_security_check();
    
    if (!is_array($topics)) {
        return array();
    }
    
    // Create lookup array
    $topics_by_id = array();
    foreach ($topics as $topic) {
        if (is_object($topic) && isset($topic->term_id)) {
            $topics_by_id[(int) $topic->term_id] = $topic;
        }
    }
    
    $paths = array();
    
    foreach ($topics as $topic) {
        if (!is_object($topic) || !isset($topic->term_id)) {
            continue;
        }
        
        $term_id = (int) $topic->term_id;
        $parent_id = (int) $topic->parent;
        
        if (!$parent_id) {
            $paths[$term_id] = '';
            continue;
        }
        
        $path = array();
        $current_id = $parent_id;
        
        // Prevent infinite loops
        $max_depth = 10;
        $depth = 0;
        $visited = array();
        
        while ($current_id && $depth < $max_depth && !in_array($current_id, $visited, true)) {
            $visited[] = $current_id;
            
            if (isset($topics_by_id[$current_id])) {
                $parent_name = sanitize_text_field($topics_by_id[$current_id]->name);
                array_unshift($path, $parent_name);
                $current_id = (int) $topics_by_id[$current_id]->parent;
            } else {
                break;
            }
            $depth++;
        }
        
        $paths[$term_id] = implode(' → ', $path);
    }
    
    return $paths;
}

// Check if topic is active from meta array with sanitization
function caes_is_topic_active_from_meta($meta_array) {
    if (!is_array($meta_array)) {
        return true;
    }
    
    // START ENHANCED DEBUG BLOCK
    error_log('*** DEBUG: caes_is_topic_active_from_meta called ***');
    error_log('Input meta array: ' . print_r($meta_array, true));
    // END ENHANCED DEBUG BLOCK

    // Check various possible meta keys
    if (isset($meta_array['active'])) {
        $value = sanitize_text_field($meta_array['active']);
        
        // Fix: Explicitly check for '0' or 0 to be considered inactive.
        if (in_array($value, array('0', 'no', 'false', 'inactive'), true)) {
            error_log('*** DEBUG: active field value is ' . $value . '. Returning FALSE.');
            return false;
        }
        $is_active = in_array($value, array('1', 'yes', 'true', 'active'), true);
        error_log('*** DEBUG: active field value is ' . $value . '. Returning ' . ($is_active ? 'TRUE' : 'FALSE') . '.');
        return $is_active;
    }
    
    if (isset($meta_array['status'])) {
        $value = sanitize_text_field($meta_array['status']);
        $is_active = $value === 'active';
        error_log('*** DEBUG: status field value is ' . $value . '. Returning ' . ($is_active ? 'TRUE' : 'FALSE') . '.');
        return $is_active;
    }
    
    if (isset($meta_array['inactive'])) {
        $value = sanitize_text_field($meta_array['inactive']);
        $is_active = !in_array($value, array('1', 'yes', 'true'), true);
        error_log('*** DEBUG: inactive field value is ' . $value . '. Returning ' . ($is_active ? 'TRUE' : 'FALSE') . '.');
        return $is_active;
    }
    
    // Default to active if no meta
    error_log('*** DEBUG: No relevant meta key found. Returning default TRUE.');
    return true;
}

// Clear topics cache with security check
function caes_clear_topics_cache() {
    caes_topics_security_check();
    
    delete_transient('caes_topics_data_v3_secure');
    delete_transient('caes_topics_performance_info');
}

// Handle topic status toggle with proper security
function caes_handle_topic_status_toggle() {
    caes_topics_security_check();
    
    // Verify nonce
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], CAES_TOPICS_NONCE_ACTION)) {
        wp_die(__('Security check failed.'), 403);
    }
    
    // Validate and sanitize input
    if (!isset($_POST['term_id']) || !is_numeric($_POST['term_id'])) {
        wp_die(__('Invalid term ID.'), 400);
    }
    
    $term_id = (int) $_POST['term_id'];
    $term = get_term($term_id, 'topics');
    
    if (is_wp_error($term) || !$term) {
        wp_die(__('Term not found.'), 404);
    }
    
    // Toggle status logic would go here
    // Clear cache after changes
    caes_clear_topics_cache();
    
    wp_safe_redirect(admin_url('admin.php?page=caes-topics-manager'));
    exit;
}

// Legacy function for backwards compatibility with security
function caes_is_topic_active($term_id) {
    if (!is_numeric($term_id)) {
        return true;
    }
    
    $term_id = (int) $term_id;
    $status = get_term_meta($term_id, 'active', true);
    $status = sanitize_text_field($status);
    
    if (empty($status)) {
        return true;
    }
    
    return in_array($status, array('1', 'active', 'yes'), true);
}

// Display functions using cached data with security checks
function caes_display_summary_cards() {
    caes_topics_security_check();
    
    $data = caes_get_cached_topics_data();
    if (!is_array($data) || !isset($data['summary'])) {
        echo '<p>Error loading data.</p>';
        return;
    }
    
    $summary = $data['summary'];
    ?>
    <div class="caes-summary-card">
        <h3>Total Topics</h3>
        <div class="number"><?php echo (int) $summary['total_topics']; ?></div>
    </div>
    <div class="caes-summary-card">
        <h3>Active Topics</h3>
        <div class="number"><?php echo (int) $summary['active_topics']; ?></div>
    </div>
    <div class="caes-summary-card">
        <h3>Inactive Topics</h3>
        <div class="number"><?php echo (int) $summary['inactive_topics']; ?></div>
    </div>
    <div class="caes-summary-card">
        <h3>Potential Duplicates</h3>
        <div class="number"><?php echo (int) $summary['potential_duplicates']; ?></div>
    </div>
    <?php
}

function caes_display_topics_overview() {
    caes_topics_security_check();
    
    $data = caes_get_cached_topics_data();
    if (!is_array($data) || !isset($data['topics'])) {
        echo '<p>Error loading topics data.</p>';
        return;
    }
    
    // Add search box
    ?>
    <div class="caes-search-box">
        <input type="text" id="caes-topic-search" placeholder="Search topics..." maxlength="100" />
        <button type="button" class="button">Search</button>
    </div>
    <?php
    
    foreach ($data['topics'] as $topic_data) {
        if (!is_array($topic_data) || !isset($topic_data['term'])) {
            continue;
        }
        
        $topic = $topic_data['term'];
        $is_active = (bool) $topic_data['is_active'];
        $counts = is_array($topic_data['counts']) ? $topic_data['counts'] : array();
        $parent_path = sanitize_text_field($topic_data['parent_path'] ?? '');
        
        $item_class = $is_active ? 'caes-topic-item' : 'caes-topic-item caes-topic-inactive';
        ?>
        <div class="<?php echo esc_attr($item_class); ?>">
            <div class="caes-topic-header">
                <div>
                    <div class="caes-topic-name"><?php echo esc_html($topic->name); ?></div>
                    <?php if ($parent_path): ?>
                        <div class="caes-parent-path"><?php echo esc_html($parent_path); ?></div>
                    <?php endif; ?>
                </div>
                <div class="caes-status-badge <?php echo $is_active ? 'caes-status-active' : 'caes-status-inactive'; ?>">
                    <?php echo $is_active ? 'Active' : 'Inactive'; ?>
                </div>
            </div>
            
            <div class="caes-counts">
                <div class="caes-count-item">
                    <strong>Stories:</strong> 
                    <?php if ($counts['post'] ?? 0 > 0): ?>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=post&topics=' . $topic->slug)); ?>">
                            <?php echo (int) ($counts['post'] ?? 0); ?>
                        </a>
                    <?php else: ?>
                        <?php echo (int) ($counts['post'] ?? 0); ?>
                    <?php endif; ?>
                </div>
                <div class="caes-count-item">
                    <strong>Publications:</strong> 
                    <?php if ($counts['publications'] ?? 0 > 0): ?>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=publications&topics=' . $topic->slug)); ?>">
                            <?php echo (int) ($counts['publications'] ?? 0); ?>
                        </a>
                    <?php else: ?>
                        <?php echo (int) ($counts['publications'] ?? 0); ?>
                    <?php endif; ?>
                </div>
                <div class="caes-count-item">
                    <strong>Features:</strong> 
                    <?php if ($counts['shorthand_story'] ?? 0 > 0): ?>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=shorthand_story&topics=' . $topic->slug)); ?>">
                            <?php echo (int) ($counts['shorthand_story'] ?? 0); ?>
                        </a>
                    <?php else: ?>
                        <?php echo (int) ($counts['shorthand_story'] ?? 0); ?>
                    <?php endif; ?>
                </div>
                <div class="caes-count-item">
                    <strong>Total:</strong> <?php echo (int) array_sum($counts); ?>
                </div>
            </div>
            
            <div class="caes-topic-actions">
                <a href="<?php echo esc_url(admin_url('term.php?taxonomy=topics&tag_ID=' . (int) $topic->term_id)); ?>" class="button button-small">Edit Topic</a>
            </div>
        </div>
        <?php
    }
}

function caes_display_hierarchy_view() {
    caes_topics_security_check();
    
    $data = caes_get_cached_topics_data();
    if (!is_array($data) || !isset($data['topics'])) {
        echo '<p>Error loading hierarchy data.</p>';
        return;
    }
    
    // Group by parent for hierarchy display
    $hierarchy = array();
    foreach ($data['topics'] as $topic_data) {
        if (!is_array($topic_data) || !isset($topic_data['term'])) {
            continue;
        }
        
        $topic = $topic_data['term'];
        $parent_id = (int) $topic->parent;
        
        if (!isset($hierarchy[$parent_id])) {
            $hierarchy[$parent_id] = array();
        }
        
        $hierarchy[$parent_id][] = $topic_data;
    }
    
    // Display top-level topics first
    if (isset($hierarchy[0]) && is_array($hierarchy[0])) {
        foreach ($hierarchy[0] as $topic_data) {
            caes_display_topic_with_children_optimized($topic_data, $hierarchy, 0);
        }
    }
}

function caes_display_topic_with_children_optimized($topic_data, $hierarchy, $level = 0) {
    if (!is_array($topic_data) || !isset($topic_data['term'])) {
        return;
    }
    
    $topic = $topic_data['term'];
    $is_active = (bool) $topic_data['is_active'];
    $counts = is_array($topic_data['counts']) ? $topic_data['counts'] : array();
    
    // Prevent excessive nesting for security
    if ($level > 10) {
        return;
    }
    
    $indent_style = $level > 0 ? 'margin-left: ' . ((int) $level * 30) . 'px;' : '';
    $item_class = $is_active ? 'caes-topic-item' : 'caes-topic-item caes-topic-inactive';
    ?>
    <div class="<?php echo esc_attr($item_class); ?>" style="<?php echo esc_attr($indent_style); ?>">
        <div class="caes-topic-header">
            <div class="caes-topic-name">
                <?php echo esc_html(str_repeat('└─ ', $level) . $topic->name); ?>
            </div>
            <div class="caes-status-badge <?php echo $is_active ? 'caes-status-active' : 'caes-status-inactive'; ?>">
                <?php echo $is_active ? 'Active' : 'Inactive'; ?>
            </div>
        </div>
        
        <div class="caes-counts">
            <div class="caes-count-item">
                <strong>Stories:</strong> 
                <?php if ($counts['post'] ?? 0 > 0): ?>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=post&topics=' . $topic->slug)); ?>">
                        <?php echo (int) ($counts['post'] ?? 0); ?>
                    </a>
                <?php else: ?>
                    <?php echo (int) ($counts['post'] ?? 0); ?>
                <?php endif; ?>
            </div>
            <div class="caes-count-item">
                <strong>Publications:</strong> 
                <?php if ($counts['publications'] ?? 0 > 0): ?>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=publications&topics=' . $topic->slug)); ?>">
                        <?php echo (int) ($counts['publications'] ?? 0); ?>
                    </a>
                <?php else: ?>
                    <?php echo (int) ($counts['publications'] ?? 0); ?>
                <?php endif; ?>
            </div>
            <div class="caes-count-item">
                <strong>Features:</strong> 
                <?php if ($counts['shorthand_story'] ?? 0 > 0): ?>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=shorthand_story&topics=' . $topic->slug)); ?>">
                        <?php echo (int) ($counts['shorthand_story'] ?? 0); ?>
                    </a>
                <?php else: ?>
                    <?php echo (int) ($counts['shorthand_story'] ?? 0); ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="caes-topic-actions">
            <a href="<?php echo esc_url(admin_url('term.php?taxonomy=topics&tag_ID=' . (int) $topic->term_id)); ?>" class="button button-small">Edit Topic</a>
        </div>
    </div>
    <?php
    
    // Display children if they exist
    $term_id = (int) $topic->term_id;
    if (isset($hierarchy[$term_id]) && is_array($hierarchy[$term_id])) {
        foreach ($hierarchy[$term_id] as $child_data) {
            caes_display_topic_with_children_optimized($child_data, $hierarchy, $level + 1);
        }
    }
}

function caes_display_duplicates() {
    caes_topics_security_check();
    
    $data = caes_get_cached_topics_data();
    if (!is_array($data) || !isset($data['duplicates'])) {
        echo '<p>Error loading duplicates data.</p>';
        return;
    }
    
    if (empty($data['duplicates'])) {
        echo '<p>No duplicate topics found!</p>';
        return;
    }
    
    foreach ($data['duplicates'] as $key => $group) {
        if (!is_array($group) || empty($group)) {
            continue;
        }
        
        $key_parts = explode('|', $key, 2);
        if (count($key_parts) !== 2) {
            continue;
        }
        
        $parent_id = (int) $key_parts[0];
        $name = sanitize_text_field($key_parts[1]);
        
        $parent_name = '';
        if ($parent_id > 0) {
            $parent_term = get_term($parent_id, 'topics');
            $parent_name = $parent_term && !is_wp_error($parent_term) ? 
                sanitize_text_field($parent_term->name) : 'Unknown Parent';
        }
        ?>
        <div class="caes-duplicate-warning">
            <h4>Duplicate Name: "<?php echo esc_html($name); ?>"</h4>
            <?php if ($parent_name): ?>
                <p><strong>Parent:</strong> <?php echo esc_html($parent_name); ?></p>
            <?php else: ?>
                <p><strong>Parent:</strong> None (Top Level)</p>
            <?php endif; ?>
            
            <p><strong>Found <?php echo count($group); ?> topics with this name and parent:</strong></p>
            <ul>
                <?php foreach ($group as $duplicate): ?>
                    <?php if (is_object($duplicate) && isset($duplicate->term_id)): ?>
                        <li>
                            ID: <?php echo (int) $duplicate->term_id; ?> 
                            - <?php echo esc_html($duplicate->name); ?>
                            - <a href="<?php echo esc_url(admin_url('term.php?taxonomy=topics&tag_ID=' . (int) $duplicate->term_id)); ?>" target="_blank">Edit</a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }
}

function caes_display_inactive_items() {
    caes_topics_security_check();
    
    $data = caes_get_cached_topics_data();
    if (!is_array($data) || !isset($data['topics'])) {
        echo '<p>Error loading inactive items data.</p>';
        return;
    }
    
    $inactive_topics = array_filter($data['topics'], function($topic_data) {
        return is_array($topic_data) && !($topic_data['is_active'] ?? true);
    });
    
    if (empty($inactive_topics)) {
        echo '<p>No inactive topics found!</p>';
        return;
    }
    
    foreach ($inactive_topics as $topic_data) {
        if (!is_array($topic_data) || !isset($topic_data['term'])) {
            continue;
        }
        
        $topic = $topic_data['term'];
        $counts = is_array($topic_data['counts']) ? $topic_data['counts'] : array();
        $parent_path = sanitize_text_field($topic_data['parent_path'] ?? '');
        ?>
        <div class="caes-topic-item caes-topic-inactive">
            <div class="caes-topic-header">
                <div>
                    <div class="caes-topic-name"><?php echo esc_html($topic->name); ?></div>
                    <?php if ($parent_path): ?>
                        <div class="caes-parent-path"><?php echo esc_html($parent_path); ?></div>
                    <?php endif; ?>
                </div>
                <div class="caes-status-badge caes-status-inactive">Inactive</div>
            </div>
            
            <div class="caes-counts">
                <div class="caes-count-item">
                    <strong>Stories:</strong> 
                    <?php if ($counts['post'] ?? 0 > 0): ?>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=post&topics=' . $topic->slug)); ?>">
                            <?php echo (int) ($counts['post'] ?? 0); ?>
                        </a>
                    <?php else: ?>
                        <?php echo (int) ($counts['post'] ?? 0); ?>
                    <?php endif; ?>
                </div>
                <div class="caes-count-item">
                    <strong>Publications:</strong> 
                    <?php if ($counts['publications'] ?? 0 > 0): ?>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=publications&topics=' . $topic->slug)); ?>">
                            <?php echo (int) ($counts['publications'] ?? 0); ?>
                        </a>
                    <?php else: ?>
                        <?php echo (int) ($counts['publications'] ?? 0); ?>
                    <?php endif; ?>
                </div>
                <div class="caes-count-item">
                    <strong>Features:</strong> 
                    <?php if ($counts['shorthand_story'] ?? 0 > 0): ?>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=shorthand_story&topics=' . $topic->slug)); ?>">
                            <?php echo (int) ($counts['shorthand_story'] ?? 0); ?>
                        </a>
                    <?php else: ?>
                        <?php echo (int) ($counts['shorthand_story'] ?? 0); ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="caes-topic-actions">
                <a href="<?php echo esc_url(admin_url('term.php?taxonomy=topics&tag_ID=' . (int) $topic->term_id)); ?>" class="button button-small">Edit Topic</a>
            </div>
        </div>
        <?php
    }
}