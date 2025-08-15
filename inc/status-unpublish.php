<?php
/**
 * CAES Post Sync Tool
 * File: caes-post-sync.php
 * Include this file in your theme
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CAES_Post_Sync {
    
    private static $instance = null;
    private $api_url = 'https://secure.caes.uga.edu/rest/news/getStories';
    
    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_caes_sync_posts', array($this, 'ajax_sync_posts'));
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts($hook) {
        // Only load on the post sync page
        if ($hook !== 'caes-tools_page_post-sync') {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_localize_script('jquery', 'caes_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('caes_sync_nonce')
        ));
    }
    
    /**
     * AJAX handler for syncing posts
     */
    public function ajax_sync_posts() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'caes_sync_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $dry_run = isset($_POST['dry_run']) && $_POST['dry_run'] == '1';
        
        try {
            $result = $this->sync_posts($dry_run);
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Main sync function
     */
    private function sync_posts($dry_run = false) {
        // Fetch API data
        $api_data = $this->fetch_api_data();
        if (!$api_data) {
            throw new Exception('Failed to fetch API data');
        }
        
        // Create lookup array for API data
        $api_lookup = array();
        foreach ($api_data as $story) {
            $api_lookup[$story['ID']] = $story;
        }
        
        // Get all published posts with ACF 'id' field
        $posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_query' => array(
                array(
                    'key' => 'id',
                    'compare' => 'EXISTS'
                )
            )
        ));
        
        $updated_posts = array();
        $errors = array();
        $posts_checked = 0;
        
        foreach ($posts as $post) {
            $posts_checked++;
            
            // Get the ACF ID field
            $acf_id = get_field('id', $post->ID);
            
            if (!$acf_id) {
                continue;
            }
            
            // Check if this ID exists in API data
            if (!isset($api_lookup[$acf_id])) {
                continue;
            }
            
            $api_story = $api_lookup[$acf_id];
            $status_id = $api_story['STATUS_ID'];
            
            // If status is not 3, draft the post
            if ($status_id != 3) {
                if (!$dry_run) {
                    $update_result = wp_update_post(array(
                        'ID' => $post->ID,
                        'post_status' => 'draft',
                        'post_date' => '',
                        'post_date_gmt' => ''
                    ));
                    
                    if (is_wp_error($update_result)) {
                        $errors[] = "Failed to update post ID {$post->ID}: " . $update_result->get_error_message();
                        continue;
                    }
                }
                
                $updated_posts[] = array(
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'api_id' => $acf_id,
                    'api_status' => $status_id
                );
            }
        }
        
        return array(
            'api_count' => count($api_data),
            'posts_checked' => $posts_checked,
            'updated_count' => count($updated_posts),
            'updated_posts' => $updated_posts,
            'errors' => $errors
        );
    }
    
    /**
     * Fetch data from API
     */
    private function fetch_api_data() {
        $response = wp_remote_get($this->api_url, array(
            'timeout' => 30,
            'headers' => array(
                'Accept' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        return $data;
    }
}

// Initialize the class
CAES_Post_Sync::init();

// Add the submenu page
add_action('admin_menu', function () {
    add_submenu_page(
        'caes-tools',           // Parent slug - points to CAES Tools
        'Post Sync',            // Page title
        'Post Sync',            // Menu title
        'manage_options',
        'post-sync',
        'render_post_sync_page'
    );
});

// Render the page
function render_post_sync_page() {
    ?>
    <div class="wrap">
        <h1>Post Status Sync</h1>
        <p>This tool syncs WordPress posts with the CAES API. Posts with STATUS_ID other than 3 will be drafted and unpublished.</p>
        
        <div id="caes-sync-status" style="margin: 20px 0;"></div>
        
        <button id="caes-sync-btn" class="button button-primary">Start Sync</button>
        <button id="caes-preview-btn" class="button">Preview Changes (Dry Run)</button>
        
        <div id="caes-results" style="margin-top: 20px;"></div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#caes-sync-btn').click(function() {
            performSync(false);
        });
        
        $('#caes-preview-btn').click(function() {
            performSync(true);
        });
        
        function performSync(dryRun) {
            var $button = dryRun ? $('#caes-preview-btn') : $('#caes-sync-btn');
            var $status = $('#caes-sync-status');
            var $results = $('#caes-results');
            
            $button.prop('disabled', true);
            $status.html('<div class="notice notice-info"><p>Processing...</p></div>');
            $results.empty();
            
            $.ajax({
                url: caes_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'caes_sync_posts',
                    nonce: caes_ajax.nonce,
                    dry_run: dryRun ? 1 : 0
                },
                success: function(response) {
                    $button.prop('disabled', false);
                    
                    if (response.success) {
                        var messageType = dryRun ? 'notice-info' : 'notice-success';
                        var actionText = dryRun ? 'Preview completed' : 'Sync completed';
                        
                        $status.html('<div class="notice ' + messageType + '"><p>' + actionText + ' successfully!</p></div>');
                        
                        var html = '<h3>' + (dryRun ? 'Preview Results' : 'Sync Results') + '</h3>';
                        html += '<p><strong>API Stories Found:</strong> ' + response.data.api_count + '</p>';
                        html += '<p><strong>WordPress Posts Checked:</strong> ' + response.data.posts_checked + '</p>';
                        html += '<p><strong>Posts ' + (dryRun ? 'Would Be ' : '') + 'Updated:</strong> ' + response.data.updated_count + '</p>';
                        
                        if (response.data.updated_posts.length > 0) {
                            html += '<h4>Posts ' + (dryRun ? 'That Would Be ' : '') + 'Updated:</h4>';
                            html += '<table class="widefat striped">';
                            html += '<thead><tr><th>Post ID</th><th>Title</th><th>API ID</th><th>API Status</th><th>Action</th></tr></thead>';
                            html += '<tbody>';
                            
                            response.data.updated_posts.forEach(function(post) {
                                html += '<tr>';
                                html += '<td>' + post.post_id + '</td>';
                                html += '<td>' + post.title + '</td>';
                                html += '<td>' + post.api_id + '</td>';
                                html += '<td>' + post.api_status + '</td>';
                                html += '<td>Drafted & Unpublished</td>';
                                html += '</tr>';
                            });
                            
                            html += '</tbody></table>';
                        }
                        
                        if (response.data.errors && response.data.errors.length > 0) {
                            html += '<h4>Errors:</h4>';
                            html += '<ul>';
                            response.data.errors.forEach(function(error) {
                                html += '<li style="color: red;">' + error + '</li>';
                            });
                            html += '</ul>';
                        }
                        
                        $results.html(html);
                    } else {
                        $status.html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
                    }
                },
                error: function() {
                    $button.prop('disabled', false);
                    $status.html('<div class="notice notice-error"><p>AJAX request failed</p></div>');
                }
            });
        }
    });
    </script>
    <?php
}
?>