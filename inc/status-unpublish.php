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
        $batch = isset($_POST['batch']) ? intval($_POST['batch']) : 0;
        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 25;
        
        try {
            $result = $this->sync_posts($dry_run, $batch, $batch_size);
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Main sync function - Progressive batch version
     */
    private function sync_posts($dry_run = false, $batch = 0, $batch_size = 25) {
        // Set execution time limit
        set_time_limit(120); // 2 minutes for each batch
        
        // Fetch API data (only once, cache it)
        $api_data = $this->get_cached_api_data();
        if (!$api_data) {
            throw new Exception('Failed to fetch API data');
        }
        
        // Create lookup array for API data
        $api_lookup = array();
        foreach ($api_data as $story) {
            $api_lookup[$story['ID']] = $story;
        }
        
        $updated_posts = array();
        $errors = array();
        $posts_checked = 0;
        
        // Calculate offset based on batch
        $offset = $batch * $batch_size;
        
        // Get posts for this batch
        $posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => $batch_size,
            'offset' => $offset,
            'meta_query' => array(
                array(
                    'key' => 'id',
                    'compare' => 'EXISTS'
                )
            )
        ));
        
        foreach ($posts as $post) {
            $posts_checked++;
            
            // Get the ACF ID field
            $acf_id = get_post_meta($post->ID, 'id', true);
            
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
        
        // Free up memory
        unset($posts);
        wp_cache_flush();
        
        // Check if there are more posts to process
        $has_more = (count($posts) === $batch_size);
        
        return array(
            'api_count' => count($api_data),
            'posts_checked' => $posts_checked,
            'updated_count' => count($updated_posts),
            'updated_posts' => $updated_posts,
            'errors' => $errors,
            'has_more' => $has_more,
            'batch' => $batch,
            'offset' => $offset
        );
    }
    
    /**
     * Cached API data to avoid multiple API calls
     */
    private function get_cached_api_data() {
        $cache_key = 'caes_api_data_' . date('Y-m-d-H');
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $api_data = $this->fetch_api_data();
        if ($api_data) {
            // Cache for 1 hour
            set_transient($cache_key, $api_data, HOUR_IN_SECONDS);
        }
        
        return $api_data;
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
        
        <!-- Progress Bar -->
        <div id="caes-progress-container" style="display: none; margin: 20px 0;">
            <div style="background: #f1f1f1; border-radius: 10px; padding: 3px;">
                <div id="caes-progress-bar" style="width: 0%; background: #0073aa; height: 20px; border-radius: 8px; transition: width 0.3s;"></div>
            </div>
            <div id="caes-progress-text" style="margin-top: 10px; font-weight: bold;"></div>
        </div>
        
        <button id="caes-sync-btn" class="button button-primary">Start Sync</button>
        <button id="caes-preview-btn" class="button">Preview Changes (Dry Run)</button>
        <button id="caes-stop-btn" class="button" style="display: none; margin-left: 10px;">Stop Sync</button>
        
        <div id="caes-results" style="margin-top: 20px;"></div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        var syncInProgress = false;
        var stopRequested = false;
        var allResults = {
            api_count: 0,
            posts_checked: 0,
            updated_count: 0,
            updated_posts: [],
            errors: []
        };
        
        $('#caes-sync-btn').click(function() {
            performProgressiveSync(false);
        });
        
        $('#caes-preview-btn').click(function() {
            performProgressiveSync(true);
        });
        
        $('#caes-stop-btn').click(function() {
            stopRequested = true;
            $(this).prop('disabled', true).text('Stopping...');
        });
        
        function performProgressiveSync(dryRun) {
            if (syncInProgress) return;
            
            syncInProgress = true;
            stopRequested = false;
            
            // Reset results
            allResults = {
                api_count: 0,
                posts_checked: 0,
                updated_count: 0,
                updated_posts: [],
                errors: []
            };
            
            var $syncBtn = $('#caes-sync-btn');
            var $previewBtn = $('#caes-preview-btn');
            var $stopBtn = $('#caes-stop-btn');
            var $status = $('#caes-sync-status');
            var $results = $('#caes-results');
            var $progressContainer = $('#caes-progress-container');
            var $progressBar = $('#caes-progress-bar');
            var $progressText = $('#caes-progress-text');
            
            // Disable buttons and show progress
            $syncBtn.prop('disabled', true);
            $previewBtn.prop('disabled', true);
            $stopBtn.show().prop('disabled', false).text('Stop Sync');
            $progressContainer.show();
            $results.empty();
            
            var batch = 0;
            var batchSize = 25;
            var totalBatches = 0; // We'll estimate this
            
            function processBatch() {
                if (stopRequested) {
                    finishSync('Sync stopped by user', 'notice-warning');
                    return;
                }
                
                $status.html('<div class="notice notice-info"><p>Processing batch ' + (batch + 1) + '...</p></div>');
                $progressText.text('Processing batch ' + (batch + 1) + '...');
                
                $.ajax({
                    url: caes_ajax.ajax_url,
                    type: 'POST',
                    timeout: 150000, // 2.5 minutes timeout per batch
                    data: {
                        action: 'caes_sync_posts',
                        nonce: caes_ajax.nonce,
                        dry_run: dryRun ? 1 : 0,
                        batch: batch,
                        batch_size: batchSize
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            
                            // Accumulate results
                            allResults.api_count = data.api_count;
                            allResults.posts_checked += data.posts_checked;
                            allResults.updated_count += data.updated_count;
                            allResults.updated_posts = allResults.updated_posts.concat(data.updated_posts);
                            allResults.errors = allResults.errors.concat(data.errors);
                            
                            // Update progress
                            if (totalBatches === 0 && data.posts_checked > 0) {
                                // Estimate total batches based on first batch
                                totalBatches = Math.ceil(data.api_count / batchSize) || 10;
                            }
                            
                            var progress = totalBatches > 0 ? Math.min(((batch + 1) / totalBatches) * 100, 100) : 0;
                            $progressBar.css('width', progress + '%');
                            $progressText.text('Batch ' + (batch + 1) + ' complete - ' + allResults.posts_checked + ' posts checked, ' + allResults.updated_count + ' posts updated');
                            
                            batch++;
                            
                            // Continue if there are more posts
                            if (data.has_more && !stopRequested) {
                                setTimeout(processBatch, 500); // Small delay between batches
                            } else {
                                // All done
                                var messageType = dryRun ? 'notice-info' : 'notice-success';
                                var actionText = dryRun ? 'Preview completed' : 'Sync completed';
                                finishSync(actionText + ' successfully!', messageType);
                            }
                        } else {
                            finishSync('Error: ' + response.data, 'notice-error');
                        }
                    },
                    error: function(xhr, status, error) {
                        var errorMsg = 'AJAX request failed';
                        if (status === 'timeout') {
                            errorMsg = 'Request timed out - try reducing batch size';
                        }
                        finishSync(errorMsg + ': ' + error, 'notice-error');
                    }
                });
            }
            
            function finishSync(message, messageType) {
                syncInProgress = false;
                
                // Re-enable buttons
                $syncBtn.prop('disabled', false);
                $previewBtn.prop('disabled', false);
                $stopBtn.hide();
                $progressContainer.hide();
                
                // Show final status
                $status.html('<div class="notice ' + messageType + '"><p>' + message + '</p></div>');
                
                // Show final results
                displayResults(allResults, dryRun);
            }
            
            // Start processing
            processBatch();
        }
        
        function displayResults(results, dryRun) {
            var $results = $('#caes-results');
            
            var html = '<h3>' + (dryRun ? 'Preview Results' : 'Sync Results') + '</h3>';
            html += '<p><strong>API Stories Found:</strong> ' + results.api_count + '</p>';
            html += '<p><strong>WordPress Posts Checked:</strong> ' + results.posts_checked + '</p>';
            html += '<p><strong>Posts ' + (dryRun ? 'Would Be ' : '') + 'Updated:</strong> ' + results.updated_count + '</p>';
            
            if (results.updated_posts.length > 0) {
                html += '<h4>Posts ' + (dryRun ? 'That Would Be ' : '') + 'Updated:</h4>';
                html += '<table class="widefat striped">';
                html += '<thead><tr><th>Post ID</th><th>Title</th><th>API ID</th><th>API Status</th><th>Action</th></tr></thead>';
                html += '<tbody>';
                
                results.updated_posts.forEach(function(post) {
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
            
            if (results.errors && results.errors.length > 0) {
                html += '<h4>Errors:</h4>';
                html += '<ul>';
                results.errors.forEach(function(error) {
                    html += '<li style="color: red;">' + error + '</li>';
                });
                html += '</ul>';
            }
            
            $results.html(html);
        }
    });
    </script>
    <?php
}
?>