<?php
/**
 * CAES Event Import Tool
 * 
 * Add this to your theme's functions.php:
 * require_once get_template_directory() . '/inc/event-import-tool.php';
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CAES_Event_Import_Tool {
    
    private $batch_size = 25;
    private $api_endpoint = 'https://secure.caes.uga.edu/rest/caes-calendar/getEvents';
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_caes_event_preflight', array($this, 'ajax_preflight_check'));
        add_action('wp_ajax_caes_event_import_batch', array($this, 'ajax_import_batch'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Add admin menu item under CAES Tools
     */
    public function add_admin_menu() {
        add_submenu_page(
            'caes-tools', // Parent slug
            'Event Import Tool',
            'Event Import',
            'manage_options',
            'caes-event-import',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'caes-tools_page_caes-event-import') {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_localize_script('jquery', 'caes_import_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('caes_event_import_nonce')
        ));
    }
    
    /**
     * Admin page HTML
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>CAES Event Import Tool</h1>
            
            <div class="card" style="max-width: 800px;">
                <h2>Import Events from API</h2>
                
                <form id="caes-import-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label>API Endpoint</label>
                            </th>
                            <td>
                                <code>https://secure.caes.uga.edu/rest/caes-calendar/getEvents</code>
                                <p class="description">Using the CAES calendar API endpoint</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="start_date_filter">Active From Date</label>
                            </th>
                            <td>
                                <input type="date" 
                                       id="start_date_filter" 
                                       name="start_date_filter" 
                                       required>
                                <p class="description">Import events that are active from this date onwards</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="end_date_filter">Active Until Date</label>
                            </th>
                            <td>
                                <input type="date" 
                                       id="end_date_filter" 
                                       name="end_date_filter" 
                                       required>
                                <p class="description">Import events that are still active until this date</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="batch_size">Batch Size</label>
                            </th>
                            <td>
                                <select id="batch_size" name="batch_size">
                                    <option value="10">10 events per batch</option>
                                    <option value="25" selected>25 events per batch</option>
                                    <option value="50">50 events per batch</option>
                                </select>
                                <p class="description">Number of events to process at once (smaller = less memory usage)</p>
                            </td>
                        </tr>
                    </table>
                    
                    <div style="margin-top: 20px;">
                        <button type="button" id="preflight-btn" class="button button-secondary">
                            Check What Will Be Imported
                        </button>
                        <button type="button" id="import-btn" class="button button-primary" disabled>
                            Start Import
                        </button>
                    </div>
                </form>
                
                <!-- Results Area -->
                <div id="results-area" style="margin-top: 30px; display: none;">
                    <div id="preflight-results"></div>
                    <div id="import-progress"></div>
                    <div id="import-log"></div>
                </div>
            </div>
        </div>
        
        <style>
        .import-progress {
            background: #f1f1f1;
            border-radius: 3px;
            padding: 3px;
            margin: 10px 0;
        }
        .import-progress-bar {
            background: #0073aa;
            height: 20px;
            border-radius: 2px;
            transition: width 0.3s ease;
            color: white;
            text-align: center;
            line-height: 20px;
            font-size: 12px;
        }
        .event-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            background: #f9f9f9;
            margin: 10px 0;
        }
        .event-item {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        .event-item:last-child {
            border-bottom: none;
        }
        .log-entry {
            margin: 5px 0;
            padding: 8px;
            border-left: 4px solid #ddd;
        }
        .log-success { border-left-color: #46b450; background: #f0f8f0; }
        .log-error { border-left-color: #dc3232; background: #f8f0f0; }
        .log-warning { border-left-color: #ffb900; background: #f8f6f0; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            let preflightData = null;
            
            // Preflight check
            $('#preflight-btn').on('click', function() {
                const btn = $(this);
                const form = $('#caes-import-form');
                
                if (!form[0].checkValidity()) {
                    form[0].reportValidity();
                    return;
                }
                
                btn.prop('disabled', true).text('Checking...');
                $('#results-area').show();
                $('#preflight-results').html('<p>Checking API endpoint and filtering events...</p>');
                
                $.ajax({
                    url: caes_import_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'caes_event_preflight',
                        nonce: caes_import_ajax.nonce,
                        start_date: $('#start_date_filter').val(),
                        end_date: $('#end_date_filter').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            preflightData = response.data;
                            displayPreflightResults(response.data);
                            $('#import-btn').prop('disabled', false);
                        } else {
                            $('#preflight-results').html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
                        }
                    },
                    error: function() {
                        $('#preflight-results').html('<div class="notice notice-error"><p>Failed to connect to API endpoint</p></div>');
                    },
                    complete: function() {
                        btn.prop('disabled', false).text('Check What Will Be Imported');
                    }
                });
            });
            
            // Import process
            $('#import-btn').on('click', function() {
                if (!preflightData) return;
                
                const btn = $(this);
                btn.prop('disabled', true).text('Importing...');
                $('#preflight-btn').prop('disabled', true);
                
                startImport();
            });
            
            function displayPreflightResults(data) {
                let html = '<h3>Preflight Check Results</h3>';
                
                if (data.total_events === 0) {
                    html += '<div class="notice notice-warning"><p>No events found matching your date criteria.</p></div>';
                } else {
                    html += '<div class="notice notice-success"><p><strong>' + data.total_events + ' events</strong> will be imported</p></div>';
                    
                    if (data.events && data.events.length > 0) {
                        html += '<h4>Events to be imported:</h4>';
                        html += '<div class="event-list">';
                        data.events.forEach(function(event) {
                            html += '<div class="event-item">';
                            html += '<strong>' + event.TITLE + '</strong><br>';
                            html += '<small>Display: ' + event.DISPLAY_START + ' - ' + event.DISPLAY_END + '</small>';
                            html += '</div>';
                        });
                        html += '</div>';
                    }
                }
                
                $('#preflight-results').html(html);
            }
            
            function startImport() {
                const batchSize = parseInt($('#batch_size').val());
                const totalEvents = preflightData.total_events;
                const totalBatches = Math.ceil(totalEvents / batchSize);
                
                $('#import-progress').html(
                    '<h3>Import Progress</h3>' +
                    '<div class="import-progress">' +
                        '<div class="import-progress-bar" style="width: 0%">0%</div>' +
                    '</div>' +
                    '<p>Processing batch 0 of ' + totalBatches + '</p>'
                );
                
                $('#import-log').html('<h4>Import Log</h4>');
                
                processBatch(0, batchSize, totalBatches);
            }
            
            function processBatch(currentBatch, batchSize, totalBatches) {
                const startIndex = currentBatch * batchSize;
                const progress = Math.round((currentBatch / totalBatches) * 100);
                
                // Update progress
                $('.import-progress-bar').css('width', progress + '%').text(progress + '%');
                $('#import-progress p').text('Processing batch ' + (currentBatch + 1) + ' of ' + totalBatches);
                
                $.ajax({
                    url: caes_import_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'caes_event_import_batch',
                        nonce: caes_import_ajax.nonce,
                        start_date: $('#start_date_filter').val(),
                        end_date: $('#end_date_filter').val(),
                        batch_start: startIndex,
                        batch_size: batchSize
                    },
                    success: function(response) {
                        if (response.success) {
                            // Log results
                            response.data.results.forEach(function(result) {
                                const logClass = result.success ? 'log-success' : 'log-error';
                                $('#import-log').append(
                                    '<div class="log-entry ' + logClass + '">' + result.message + '</div>'
                                );
                            });
                            
                            // Continue with next batch or finish
                            if (currentBatch + 1 < totalBatches) {
                                processBatch(currentBatch + 1, batchSize, totalBatches);
                            } else {
                                // Import complete
                                $('.import-progress-bar').css('width', '100%').text('100%');
                                $('#import-progress p').text('Import completed!');
                                $('#import-btn').prop('disabled', false).text('Start Import');
                                $('#preflight-btn').prop('disabled', false);
                                $('#import-log').append(
                                    '<div class="log-entry log-success"><strong>Import process completed!</strong></div>'
                                );
                            }
                        } else {
                            $('#import-log').append(
                                '<div class="log-entry log-error">Batch failed: ' + response.data + '</div>'
                            );
                        }
                    },
                    error: function() {
                        $('#import-log').append(
                            '<div class="log-entry log-error">Network error processing batch ' + (currentBatch + 1) + '</div>'
                        );
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for preflight check
     */
    public function ajax_preflight_check() {
        check_ajax_referer('caes_event_import_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        
        try {
            $events = $this->fetch_events_from_api($this->api_endpoint);
            $filtered_events = $this->filter_events_by_date($events, $start_date, $end_date);
            
            wp_send_json_success(array(
                'total_events' => count($filtered_events),
                'events' => $filtered_events
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX handler for batch import
     */
    public function ajax_import_batch() {
        check_ajax_referer('caes_event_import_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $batch_start = intval($_POST['batch_start']);
        $batch_size = intval($_POST['batch_size']);
        
        try {
            $events = $this->fetch_events_from_api($this->api_endpoint);
            $filtered_events = $this->filter_events_by_date($events, $start_date, $end_date);
            $batch_events = array_slice($filtered_events, $batch_start, $batch_size);
            
            $results = array();
            foreach ($batch_events as $event_data) {
                $result = $this->import_single_event($event_data);
                $results[] = $result;
            }
            
            wp_send_json_success(array(
                'batch_start' => $batch_start,
                'batch_size' => $batch_size,
                'processed' => count($batch_events),
                'results' => $results
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Fetch events from API endpoint
     */
    private function fetch_events_from_api($endpoint) {
        $response = wp_remote_get($endpoint, array(
            'timeout' => 30,
            'headers' => array(
                'Accept' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('Failed to fetch from API: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            throw new Exception('API returned error code: ' . $response_code);
        }
        
        $body = wp_remote_retrieve_body($response);
        $events = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from API');
        }
        
        if (!is_array($events)) {
            throw new Exception('API response is not an array of events');
        }
        
        return $events;
    }
    
    /**
     * Filter events by date range
     */
    private function filter_events_by_date($events, $start_date, $end_date) {
        $filtered = array();
        
        $start_timestamp = strtotime($start_date);
        $end_timestamp = strtotime($end_date . ' 23:59:59');
        
        foreach ($events as $event) {
            // Parse API date format: "July, 17 2025 00:00:00"
            $display_start = isset($event['DISPLAY_START']) ? $this->parse_api_date($event['DISPLAY_START']) : null;
            $display_end = isset($event['DISPLAY_END']) ? $this->parse_api_date($event['DISPLAY_END']) : null;
            
            // Include event if:
            // - It starts before or on our end date AND
            // - It ends after or on our start date
            if ($display_start && $display_end) {
                if ($display_start <= $end_timestamp && $display_end >= $start_timestamp) {
                    $filtered[] = $event;
                }
            }
        }
        
        return $filtered;
    }
    
    /**
     * Parse API date format to timestamp
     */
    private function parse_api_date($date_string) {
        // API format: "July, 17 2025 00:00:00"
        // Remove comma after month
        $cleaned = str_replace(',', '', $date_string);
        return strtotime($cleaned);
    }
    
    /**
     * Import a single event
     */
    private function import_single_event($event_data) {
        try {
            // Check if event already exists by API ID
            $existing_posts = get_posts(array(
                'post_type' => 'events',
                'meta_query' => array(
                    array(
                        'key' => 'event_id',
                        'value' => $event_data['ID'],
                        'compare' => '='
                    )
                ),
                'posts_per_page' => 1
            ));
            
            if (!empty($existing_posts)) {
                return array(
                    'success' => false,
                    'message' => 'Event "' . $event_data['TITLE'] . '" already exists (ID: ' . $event_data['ID'] . ')'
                );
            }
            
            // Create new event post
            $post_data = array(
                'post_title' => sanitize_text_field($event_data['TITLE']),
                'post_content' => '', // We'll use ACF description field instead
                'post_status' => 'publish',
                'post_type' => 'events',
                'post_author' => get_current_user_id()
            );
            
            $post_id = wp_insert_post($post_data);
            
            if (is_wp_error($post_id)) {
                throw new Exception('Failed to create post: ' . $post_id->get_error_message());
            }
            
            // Import ACF fields
            $this->import_acf_fields($post_id, $event_data);
            
            return array(
                'success' => true,
                'message' => 'Successfully imported "' . $event_data['TITLE'] . '" (ID: ' . $post_id . ')'
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Failed to import "' . $event_data['TITLE'] . '": ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Import ACF fields for an event
     */
    private function import_acf_fields($post_id, $event_data) {
        // Basic Event Info
        update_field('event_type', 'CAES', $post_id);
        update_field('description', wp_kses_post($event_data['LONG_DESCRIPTION']), $post_id);
        update_field('event_id', $event_data['ID'], $post_id);
        
        // External page override
        if (!empty($event_data['WEB_ADDRESS'])) {
            update_field('event_page_external_address', esc_url($event_data['WEB_ADDRESS']), $post_id);
        }
        
        // Date & Time
        $this->import_dates($post_id, $event_data);
        
        // Location
        $this->import_location($post_id, $event_data);
        
        // Contact
        $this->import_contact($post_id, $event_data);
        
        // Documents (files and buttons)
        $this->import_documents($post_id, $event_data);
        
        // Legacy fields
        $this->import_legacy_fields($post_id, $event_data);
    }
    
    /**
     * Import date fields
     */
    private function import_dates($post_id, $event_data) {
        // Parse start date/time
        if (!empty($event_data['FIRST_START_DATE'])) {
            $start_timestamp = $this->parse_api_date($event_data['FIRST_START_DATE']);
            update_field('start_date', date('Ymd', $start_timestamp), $post_id);
            update_field('start_time', date('g:i a', $start_timestamp), $post_id);
        }
        
        // Parse end date/time
        if (!empty($event_data['FIRST_END_DATE'])) {
            $end_timestamp = $this->parse_api_date($event_data['FIRST_END_DATE']);
            update_field('end_date', date('Ymd', $end_timestamp), $post_id);
            update_field('end_time', date('g:i a', $end_timestamp), $post_id);
        }
        
        // Display date
        if (!empty($event_data['DISPLAY_START'])) {
            $display_timestamp = $this->parse_api_date($event_data['DISPLAY_START']);
            update_field('publish_display_date', date('Ymd', $display_timestamp), $post_id);
        }
    }
    
    /**
     * Import location fields
     */
    private function import_location($post_id, $event_data) {
        $has_online = !empty($event_data['ONLINE_WEB_ADDRESS']) || !empty($event_data['IS_ONLINE']);
        $has_physical = !empty($event_data['STREET_ADDRESS1']) || !empty($event_data['LOCATION_NAME']);
        
        // Determine location type
        if ($has_online && $has_physical) {
            update_field('event_location_type', 'Both', $post_id);
        } elseif ($has_online) {
            update_field('event_location_type', 'Online', $post_id);
        } else {
            update_field('event_location_type', 'At a physical location', $post_id);
        }
        
        // Online location
        if (!empty($event_data['ONLINE_WEB_ADDRESS'])) {
            update_field('online_location_web_address', esc_url($event_data['ONLINE_WEB_ADDRESS']), $post_id);
            update_field('online_location_web_address_label', 'Event Link', $post_id);
        }
        
        // Physical location - combine address fields
        if ($has_physical) {
            $address_parts = array_filter(array(
                $event_data['LOCATION_NAME'],
                $event_data['BUILDING'],
                $event_data['STREET_ADDRESS1'],
                $event_data['STREET_ADDRESS2'],
                $event_data['BUILDING_ADDRESS1'],
                $event_data['BUILDING_ADDRESS2'],
                $event_data['CITY'] ?: $event_data['BUILDING_CITY'],
                $event_data['STATE'] ?: $event_data['BUILDING_STATE']
            ));
            
            $full_address = implode(', ', $address_parts);
            
            // For Google Maps field, we need lat/lng - for now just store address
            if (!empty($full_address)) {
                update_field('location_google_map', array(
                    'address' => $full_address,
                    'lat' => '',
                    'lng' => ''
                ), $post_id);
            }
        }
    }
    
    /**
     * Import contact information
     */
    private function import_contact($post_id, $event_data) {
        // Try to find user by personnel_id
        $contact_user = null;
        if (!empty($event_data['CONTACT_PERSONNEL_ID'])) {
            $users = get_users(array(
                'meta_key' => 'personnel_id',
                'meta_value' => $event_data['CONTACT_PERSONNEL_ID'],
                'number' => 1
            ));
            
            if (!empty($users)) {
                $contact_user = $users[0];
            }
        }
        
        if ($contact_user) {
            // Use default contact (WordPress user)
            update_field('contact_type', 'default', $post_id);
            update_field('contact', $contact_user->ID, $post_id);
        } else {
            // Use custom contact
            $has_contact_info = !empty($event_data['CONTACT_NAME']) || 
                              !empty($event_data['CONTACT_EMAIL']) || 
                              !empty($event_data['CONTACT_PHONE']);
            
            if ($has_contact_info) {
                update_field('contact_type', 'custom', $post_id);
                update_field('custom_contact', array(
                    'contact_name' => sanitize_text_field($event_data['CONTACT_NAME']),
                    'contact_email' => sanitize_email($event_data['CONTACT_EMAIL']),
                    'contact_phone' => sanitize_text_field($event_data['CONTACT_PHONE'])
                ), $post_id);
            }
        }
    }
    
    /**
     * Import documents (files and buttons)
     */
    private function import_documents($post_id, $event_data) {
        $documents = array();
        
        // Add files
        for ($i = 1; $i <= 5; $i++) {
            $file_key = 'FILE' . $i;
            $file_name_key = 'FILE' . $i . '_NAME';
            
            if (!empty($event_data[$file_key])) {
                $documents[] = array(
                    'document_type' => 'file',
                    'file' => esc_url($event_data[$file_key]),
                    'file_label_text' => sanitize_text_field($event_data[$file_name_key] ?: 'Document ' . $i)
                );
            }
        }
        
        // Add buttons as links
        for ($i = 1; $i <= 5; $i++) {
            $button_link_key = 'BUTTON' . $i . '_LINK';
            $button_text_key = 'BUTTON' . $i . '_TEXT';
            
            if (!empty($event_data[$button_link_key])) {
                $documents[] = array(
                    'document_type' => 'link',
                    'lin' => esc_url($event_data[$button_link_key]),
                    'link_label_text' => sanitize_text_field($event_data[$button_text_key] ?: 'Link ' . $i)
                );
            }
        }
        
        if (!empty($documents)) {
            update_field('documents', $documents, $post_id);
        }
    }
    
    /**
     * Import legacy fields
     */
    private function import_legacy_fields($post_id, $event_data) {
        // Store original API data in legacy fields
        update_field('submitted_by', sanitize_text_field($event_data['SUBMITTED_BY']), $post_id);
        update_field('contact_personnel_id', intval($event_data['CONTACT_PERSONNEL_ID']), $post_id);
        update_field('image', esc_url($event_data['IMAGE']), $post_id);
        update_field('image_caption', sanitize_text_field($event_data['IMAGE_CAPTION']), $post_id);
        
        // Store first file and button in legacy fields
        update_field('file1', esc_url($event_data['FILE1']), $post_id);
        update_field('file1_name', sanitize_text_field($event_data['FILE1_NAME']), $post_id);
        
        for ($i = 1; $i <= 4; $i++) {
            update_field('button' . $i . '_text', sanitize_text_field($event_data['BUTTON' . $i . '_TEXT']), $post_id);
            update_field('button' . $i . '_link', esc_url($event_data['BUTTON' . $i . '_LINK']), $post_id);
        }
    }
}

// Initialize the tool
new CAES_Event_Import_Tool();