<?php
/**
 * Plugin Name: CAES Topics Import Tool
 * Description: Import and manage Topics taxonomy terms from CAES API endpoints
 * Version: 1.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CAES_Topics_Import_Tool {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_caes_import_parent_terms', array($this, 'ajax_import_parent_terms'));
        add_action('wp_ajax_caes_import_child_terms', array($this, 'ajax_import_child_terms'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Add admin menu under caes-tools
     */
    public function add_admin_menu() {
        add_submenu_page(
            'caes-tools', // Parent slug
            'Topics Import Tool',
            'Topics Import',
            'manage_options',
            'caes-topics-import',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'caes-tools_page_caes-topics-import') {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_localize_script('jquery', 'caes_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('caes_topics_nonce')
        ));
    }
    
    /**
     * Admin page HTML
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>CAES Topics Import Tool</h1>
            
            <div class="card" style="max-width: 800px;">
                <h2>Step 1: Import Parent Terms</h2>
                <p>Import parent terms from the Publications Keyword Types API.</p>
                <p><strong>API Endpoint:</strong> https://secure.caes.uga.edu/rest/publications/getPubsKeywordTypes</p>
                
                <button type="button" id="import-parent-terms" class="button button-primary">
                    Import Parent Terms
                </button>
                
                <div id="parent-import-status" style="margin-top: 15px;"></div>
            </div>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>Step 2: Import Child Terms</h2>
                <p>Import child terms and associate them with their parent terms.</p>
                <p><strong>API Endpoint:</strong> https://secure.caes.uga.edu/rest/publications/getKeywords</p>
                
                <button type="button" id="import-child-terms" class="button button-primary">
                    Import Child Terms
                </button>
                
                <div id="child-import-status" style="margin-top: 15px;"></div>
            </div>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>Import Log</h2>
                <div id="import-log" style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px;"></div>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            
            function logMessage(message) {
                const timestamp = new Date().toLocaleTimeString();
                $('#import-log').append('[' + timestamp + '] ' + message + '\n');
                $('#import-log').scrollTop($('#import-log')[0].scrollHeight);
            }
            
            $('#import-parent-terms').click(function() {
                const button = $(this);
                const status = $('#parent-import-status');
                
                button.prop('disabled', true).text('Importing...');
                status.html('<div class="notice notice-info"><p>Importing parent terms...</p></div>');
                logMessage('Starting parent terms import...');
                
                $.ajax({
                    url: caes_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'caes_import_parent_terms',
                        nonce: caes_ajax.nonce
                    },
                    success: function(response) {
                        button.prop('disabled', false).text('Import Parent Terms');
                        
                        if (response.success) {
                            status.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            logMessage('SUCCESS: ' + response.data.message);
                            if (response.data.details) {
                                response.data.details.forEach(function(detail) {
                                    logMessage('  - ' + detail);
                                });
                            }
                        } else {
                            status.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                            logMessage('ERROR: ' + response.data);
                        }
                    },
                    error: function() {
                        button.prop('disabled', false).text('Import Parent Terms');
                        status.html('<div class="notice notice-error"><p>Ajax request failed</p></div>');
                        logMessage('ERROR: Ajax request failed');
                    }
                });
            });
            
            $('#import-child-terms').click(function() {
                const button = $(this);
                const status = $('#child-import-status');
                
                button.prop('disabled', true).text('Importing...');
                status.html('<div class="notice notice-info"><p>Importing child terms...</p></div>');
                logMessage('Starting child terms import...');
                
                $.ajax({
                    url: caes_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'caes_import_child_terms',
                        nonce: caes_ajax.nonce
                    },
                    success: function(response) {
                        button.prop('disabled', false).text('Import Child Terms');
                        
                        if (response.success) {
                            status.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            logMessage('SUCCESS: ' + response.data.message);
                            if (response.data.details) {
                                response.data.details.forEach(function(detail) {
                                    logMessage('  - ' + detail);
                                });
                            }
                        } else {
                            status.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                            logMessage('ERROR: ' + response.data);
                        }
                    },
                    error: function() {
                        button.prop('disabled', false).text('Import Child Terms');
                        status.html('<div class="notice notice-error"><p>Ajax request failed</p></div>');
                        logMessage('ERROR: Ajax request failed');
                    }
                });
            });
        });
        </script>
        
        <style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .card h2 {
            margin-top: 0;
        }
        #import-log {
            white-space: pre-wrap;
        }
        </style>
        <?php
    }
    
    /**
     * AJAX handler for importing parent terms
     */
    public function ajax_import_parent_terms() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'caes_topics_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        try {
            $api_url = 'https://secure.caes.uga.edu/rest/publications/getPubsKeywordTypes';
            
            // Fetch data from API
            $response = wp_remote_get($api_url, array(
                'timeout' => 30,
                'headers' => array(
                    'Accept' => 'application/json'
                )
            ));
            
            if (is_wp_error($response)) {
                wp_send_json_error('API request failed: ' . $response->get_error_message());
                return;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error('Invalid JSON response from API');
                return;
            }
            
            if (!is_array($data)) {
                wp_send_json_error('API did not return an array');
                return;
            }
            
            $imported_count = 0;
            $updated_count = 0;
            $details = array();
            
            foreach ($data as $item) {
                $term_id = $item['ID'];
                $term_label = sanitize_text_field($item['LABEL']);
                
                // Check if term already exists with this topic_id
                $existing_terms = get_terms(array(
                    'taxonomy' => 'topics',
                    'hide_empty' => false,
                    'meta_query' => array(
                        array(
                            'key' => 'topic_id',
                            'value' => $term_id,
                            'compare' => '='
                        )
                    )
                ));
                
                if (!empty($existing_terms)) {
                    // Update existing term
                    $existing_term = $existing_terms[0];
                    $updated = wp_update_term($existing_term->term_id, 'topics', array(
                        'name' => $term_label
                    ));
                    
                    if (!is_wp_error($updated)) {
                        $updated_count++;
                        $details[] = "Updated: {$term_label} (ID: {$term_id})";
                    }
                } else {
                    // Create new term
                    $created = wp_insert_term($term_label, 'topics');
                    
                    if (!is_wp_error($created)) {
                        // Set the topic_id custom field
                        update_term_meta($created['term_id'], 'topic_id', $term_id);
                        $imported_count++;
                        $details[] = "Created: {$term_label} (ID: {$term_id})";
                    } else {
                        $details[] = "Failed to create: {$term_label} - " . $created->get_error_message();
                    }
                }
            }
            
            $message = "Import completed. Created: {$imported_count}, Updated: {$updated_count}";
            
            wp_send_json_success(array(
                'message' => $message,
                'details' => $details
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Import failed: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler for importing child terms
     */
    public function ajax_import_child_terms() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'caes_topics_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        try {
            $api_url = 'https://secure.caes.uga.edu/rest/publications/getKeywords';
            
            // Fetch data from API
            $response = wp_remote_get($api_url, array(
                'timeout' => 30,
                'headers' => array(
                    'Accept' => 'application/json'
                )
            ));
            
            if (is_wp_error($response)) {
                wp_send_json_error('API request failed: ' . $response->get_error_message());
                return;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error('Invalid JSON response from API');
                return;
            }
            
            if (!is_array($data)) {
                wp_send_json_error('API did not return an array');
                return;
            }
            
            $created_count = 0;
            $updated_count = 0;
            $skipped_count = 0;
            $rearranged_count = 0;
            $details = array();
            
            foreach ($data as $item) {
                $child_id = $item['ID'];
                $child_label = sanitize_text_field($item['LABEL']);
                $parent_type_id = $item['TYPE_ID'];
                
                // Find the parent term with matching topic_id
                $parent_terms = get_terms(array(
                    'taxonomy' => 'topics',
                    'hide_empty' => false,
                    'meta_query' => array(
                        array(
                            'key' => 'topic_id',
                            'value' => $parent_type_id,
                            'compare' => '='
                        )
                    )
                ));
                
                if (empty($parent_terms)) {
                    $details[] = "Skipped {$child_label} (ID: {$child_id}): Parent with topic_id {$parent_type_id} not found";
                    $skipped_count++;
                    continue;
                }
                
                $parent_term = $parent_terms[0];
                $parent_term_id = $parent_term->term_id;
                
                // Check if child term already exists with this topic_id (unique identifier)
                $existing_child_terms = get_terms(array(
                    'taxonomy' => 'topics',
                    'hide_empty' => false,
                    'meta_query' => array(
                        array(
                            'key' => 'topic_id',
                            'value' => $child_id,
                            'compare' => '='
                        )
                    )
                ));
                
                if (!empty($existing_child_terms)) {
                    // Update existing child term (identified by unique topic_id)
                    $existing_child = $existing_child_terms[0];
                    $term_updated = false;
                    
                    // Update the term name if needed
                    if ($existing_child->name !== $child_label) {
                        $update_result = wp_update_term($existing_child->term_id, 'topics', array(
                            'name' => $child_label
                        ));
                        if (!is_wp_error($update_result)) {
                            $updated_count++;
                            $term_updated = true;
                        }
                    }
                    
                    // Update type_id if needed (should match parent's topic_id)
                    $current_type_id = get_term_meta($existing_child->term_id, 'type_id', true);
                    if ($current_type_id != $parent_type_id) {
                        update_term_meta($existing_child->term_id, 'type_id', $parent_type_id);
                        $term_updated = true;
                    }
                    
                    // Rearrange under correct parent if needed
                    if ($existing_child->parent != $parent_term_id) {
                        $rearrange_result = wp_update_term($existing_child->term_id, 'topics', array(
                            'parent' => $parent_term_id
                        ));
                        if (!is_wp_error($rearrange_result)) {
                            $rearranged_count++;
                            $details[] = "Rearranged: {$child_label} (ID: {$child_id}) under {$parent_term->name}";
                        }
                    } else if ($term_updated) {
                        $details[] = "Updated: {$child_label} (ID: {$child_id}) under {$parent_term->name}";
                    }
                    
                } else {
                    // Create new child term (duplicate names are OK under different parents)
                    $created = wp_insert_term($child_label, 'topics', array(
                        'parent' => $parent_term_id
                    ));
                    
                    if (!is_wp_error($created)) {
                        // Set both custom fields for child terms
                        update_term_meta($created['term_id'], 'topic_id', $child_id); // Child's unique ID
                        update_term_meta($created['term_id'], 'type_id', $parent_type_id); // Parent's topic_id
                        $created_count++;
                        $details[] = "Created: {$child_label} (ID: {$child_id}) under {$parent_term->name}";
                    } else {
                        // Handle the case where term might exist with same name under same parent
                        if ($created->get_error_code() === 'term_exists') {
                            // Get the existing term and set its topic_id if it doesn't have one
                            $existing_term_id = $created->get_error_data();
                            $existing_topic_id = get_term_meta($existing_term_id, 'topic_id', true);
                            
                            if (empty($existing_topic_id)) {
                                // This term exists but doesn't have proper IDs, so assign them
                                update_term_meta($existing_term_id, 'topic_id', $child_id);
                                update_term_meta($existing_term_id, 'type_id', $parent_type_id);
                                $updated_count++;
                                $details[] = "Assigned IDs to existing: {$child_label} (ID: {$child_id}) under {$parent_term->name}";
                            } else {
                                $details[] = "Conflict: {$child_label} already exists under {$parent_term->name} with different ID";
                            }
                        } else {
                            $details[] = "Failed to create: {$child_label} (ID: {$child_id}) - " . $created->get_error_message();
                        }
                    }
                }
            }
            
            $message = "Import completed. Created: {$created_count}, Updated: {$updated_count}, Rearranged: {$rearranged_count}";
            if ($skipped_count > 0) {
                $message .= ", Skipped (no parent found): {$skipped_count}";
            }
            
            wp_send_json_success(array(
                'message' => $message,
                'details' => $details
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Import failed: ' . $e->getMessage());
        }
    }
}

// Initialize the plugin
new CAES_Topics_Import_Tool();