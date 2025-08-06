<?php
/**
 * events-approval-metabox.php
 *
 * This file handles the creation and management of a custom meta box
 * on the 'events' post edit screen. This meta box displays the approval status
 * for each selected calendar and allows designated approvers to approve
 * events for their specific calendars.
 *
 * This version ONLY uses the user permission system - no ACF dependencies.
 *
 * @package YourThemeName/Events
 */

// Hook to add the meta box to the 'events' post type.
add_action('add_meta_boxes', 'add_event_approval_metabox');

/**
 * Adds the "Event Approval Status" meta box to the 'events' post type edit screen.
 */
function add_event_approval_metabox() {
    add_meta_box(
        'event_approval_status',
        __('Event Approval Status', 'caes-hub'),
        'render_event_approval_metabox',
        'events',
        'side',
        'high'
    );
}

/**
 * Renders the content of the "Event Approval Status" meta box.
 *
 * This function displays the approval status for each calendar associated
 * with the event and provides a button for a user to approve the event
 * for their specific calendar if they are the designated approver.
 *
 * @param WP_Post $post The current post object.
 */
function render_event_approval_metabox($post) {
    // We'll need the current user to check for approval permissions.
    $current_user_id = get_current_user_id();
    $current_user = new WP_User($current_user_id);
    $current_user_roles = (array) $current_user->roles;

    // Get the saved approval status for all calendars.
    $calendar_approval_status = get_post_meta($post->ID, '_calendar_approval_status', true);
    if (!is_array($calendar_approval_status)) {
        $calendar_approval_status = array();
    }

    // Get submission status
    $has_been_submitted = get_post_meta($post->ID, '_submitted_for_approval', true);
    $post_status = get_post_status($post->ID);

    // Get all calendars associated with the event.
    $event_calendars = get_the_terms($post->ID, 'event_caes_departments');

    // Display the approval status.
    echo '<div id="event-approval-status-container">';
    echo '<p><strong>' . __('Approval status per calendar:', 'caes-hub') . '</strong></p>';

    if ($event_calendars && !is_wp_error($event_calendars)) {
        foreach ($event_calendars as $term) {
            $is_approved = isset($calendar_approval_status[$term->term_id]) && $calendar_approval_status[$term->term_id] === 'approved';
            
            // Determine status text based on submission state and user permissions
            if ($is_approved) {
                $status_text = '<span style="color: green;">' . __('Approved', 'caes-hub') . '</span>';
            } elseif ($has_been_submitted || $post_status === 'pending') {
                $status_text = '<span style="color: orange;">' . __('Pending Approval', 'caes-hub') . '</span>';
            } else {
                // Check if current user can approve this calendar
                $current_user_can_approve = false;
                if (in_array('administrator', $current_user_roles) || in_array('editor', $current_user_roles)) {
                    $current_user_can_approve = true;
                } else {
                    $current_user_can_approve = user_can_approve_calendar($current_user_id, $term->term_id);
                }
                
                // DEBUG OUTPUT
                echo '<!-- DEBUG: Calendar ' . $term->name . ' (ID: ' . $term->term_id . ') -->';
                echo '<!-- Current User ID: ' . $current_user_id . ' -->';
                echo '<!-- Current User Roles: ' . implode(', ', $current_user_roles) . ' -->';
                echo '<!-- Can Approve This Calendar: ' . ($current_user_can_approve ? 'YES' : 'NO') . ' -->';
                echo '<!-- Has Been Submitted: ' . ($has_been_submitted ? 'YES' : 'NO') . ' -->';
                echo '<!-- Post Status: ' . $post_status . ' -->';
                
                if ($current_user_can_approve) {
                    $status_text = '<span style="color: blue;">' . __('Ready to Publish', 'caes-hub') . '</span>';
                    echo '<!-- STATUS SET TO: Ready to Publish -->';
                } else {
                    $status_text = '<span style="color: gray;">' . __('Not Submitted', 'caes-hub') . '</span>';
                    echo '<!-- STATUS SET TO: Not Submitted -->';
                }
            }
            
            echo '<p><strong>' . esc_html($term->name) . ':</strong> ' . $status_text;
            
            // Show who can approve this calendar
            $approvers = get_users(array(
                'meta_query' => array(
                    array(
                        'key' => 'calendar_approve_permissions',
                        'value' => $term->term_id,
                        'compare' => 'LIKE'
                    )
                ),
                'fields' => array('display_name')
            ));
            
            if (!empty($approvers)) {
                $approver_names = wp_list_pluck($approvers, 'display_name');
                echo '<br><small>Approvers: ' . implode(', ', $approver_names) . ', Site Administrators/Editors</small>';
            } else {
                echo '<br><small>Approvers: Site Administrators/Editors only</small>';
            }
            echo '</p>';

            // Check if the current user can approve this calendar.
            $can_approve = false;
            // Admins and Editors can approve everything.
            if (in_array('administrator', $current_user_roles) || in_array('editor', $current_user_roles)) {
                $can_approve = true;
            } else {
                // Check if user has approval permission for this calendar
                $can_approve = user_can_approve_calendar($current_user_id, $term->term_id);
            }

            // Display the approval button only if not yet approved, user has permission, and event has been submitted
            if (!$is_approved && $can_approve && ($has_been_submitted || $post_status === 'pending')) {
                echo '<button class="button button-primary approve-event-button" data-post-id="' . esc_attr($post->ID) . '" data-term-id="' . esc_attr($term->term_id) . '">' . sprintf(__('Approve for %s', 'caes-hub'), esc_html($term->name)) . '</button>';
            }
        }
    } else {
        // Simple message when no calendars are selected
        echo '<p style="color: #666; font-style: italic;">' . __('No calendars selected for this event.', 'caes-hub') . '</p>';
    }
    echo '</div>';
    
    // Nonce for security.
    wp_nonce_field('event_approval_nonce', 'event_approval_nonce_field');
}

// Hook to enqueue the necessary JavaScript file for the AJAX button.
add_action('admin_enqueue_scripts', 'event_approval_metabox_scripts', 10);

/**
 * Enqueues the JavaScript for the approval meta box.
 *
 * This script handles the AJAX call to approve an event for a specific calendar.
 */
function event_approval_metabox_scripts($hook_suffix) {
    global $typenow, $pagenow, $post;

    // Only load on events edit screen
    if ('events' !== $typenow || !in_array($pagenow, ['post.php', 'post-new.php'])) {
        return;
    }
    
    // Double-check we're on the right screen
    if (!in_array($hook_suffix, ['post.php', 'post-new.php'])) {
        return;
    }
    
    // Define the script handle
    $script_handle = 'event-approval-metabox-script';
    
    // Define file paths
    $js_file_path = get_template_directory() . '/inc/events/event-approval.js';
    $js_file_url = get_template_directory_uri() . '/inc/events/event-approval.js';
    
    // Check if file exists
    if (!file_exists($js_file_path)) {
        return;
    }
    
    // Enqueue the script
    wp_enqueue_script(
        $script_handle,
        $js_file_url,
        array('jquery'),
        filemtime($js_file_path),
        true
    );

    // Prepare localization data
    $ajax_data = array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('event_approval_nonce'),
        'post_id'  => isset($post->ID) ? $post->ID : 0
    );

    // Localize the script
    wp_localize_script(
        $script_handle,
        'eventApprovalAjax',
        $ajax_data
    );
}

// Alternative method: Ensure script loads if normal enqueuing fails
add_action('admin_head', 'ensure_approval_script_loaded');

function ensure_approval_script_loaded() {
    global $typenow, $pagenow;
    
    if ('events' === $typenow && in_array($pagenow, ['post.php', 'post-new.php'])) {
        // Check if our script was enqueued
        if (!wp_script_is('event-approval-metabox-script', 'enqueued')) {
            // Backup method: inline script with direct enqueuing
            $js_file_url = get_template_directory_uri() . '/inc/events/event-approval.js';
            $ajax_url = admin_url('admin-ajax.php');
            $nonce = wp_create_nonce('event_approval_nonce');
            
            ?>
            <script>
            window.eventApprovalAjax = {
                ajax_url: '<?php echo esc_js($ajax_url); ?>',
                nonce: '<?php echo esc_js($nonce); ?>'
            };
            </script>
            <script src="<?php echo esc_url($js_file_url); ?>?ver=<?php echo time(); ?>"></script>
            <?php
        }
    }
}

// Hook to handle the AJAX request for approving a calendar.
add_action('wp_ajax_approve_event_calendar', 'handle_ajax_event_approval');

/**
 * Handles the AJAX request to approve an event for a specific calendar.
 *
 * This function checks permissions and updates the post meta to reflect
 * the approval status for a single calendar.
 */
function handle_ajax_event_approval() {
    // Verify nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'event_approval_nonce')) {
        wp_send_json_error('Security check failed.');
        return;
    }

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $term_id = isset($_POST['term_id']) ? intval($_POST['term_id']) : 0;
    
    // Validate input data
    if (!$post_id || !$term_id) {
        wp_send_json_error('Missing required data.');
        return;
    }
    
    // Verify the post exists and is an event
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'events') {
        wp_send_json_error('Invalid event.');
        return;
    }
    
    // Verify the term exists
    $term = get_term($term_id, 'event_caes_departments');
    if (!$term || is_wp_error($term)) {
        wp_send_json_error('Invalid calendar.');
        return;
    }
    
    // Check user permissions
    $current_user_id = get_current_user_id();
    if (!$current_user_id || !current_user_can('edit_post', $post_id)) {
        wp_send_json_error('Permission denied.');
        return;
    }
    
    $current_user = new WP_User($current_user_id);
    $current_user_roles = (array) $current_user->roles;
    
    // Check if the current user can approve this specific calendar
    $can_approve = false;
    
    if (in_array('administrator', $current_user_roles) || in_array('editor', $current_user_roles)) {
        $can_approve = true;
    } else {
        // Check user's approval permissions for this calendar
        $can_approve = user_can_approve_calendar($current_user_id, $term_id);
    }

    if (!$can_approve) {
        wp_send_json_error('Not authorized for this calendar.');
        return;
    }

    // Get current approval status
    $calendar_approval_status = get_post_meta($post_id, '_calendar_approval_status', true);
    if (!is_array($calendar_approval_status)) {
        $calendar_approval_status = array();
    }

    // Check if already approved
    if (isset($calendar_approval_status[$term_id]) && $calendar_approval_status[$term_id] === 'approved') {
        wp_send_json_error('This calendar is already approved.');
        return;
    }

    // Update the approval status
    $calendar_approval_status[$term_id] = 'approved';
    $update_result = update_post_meta($post_id, '_calendar_approval_status', $calendar_approval_status);
    
    if (!$update_result) {
        wp_send_json_error('Failed to save approval.');
        return;
    }

    // DEBUG: Log what's happening
    error_log("=== AJAX APPROVAL DEBUG ===");
    error_log("Post ID: $post_id, Term ID: $term_id");
    error_log("Current approval status: " . print_r($calendar_approval_status, true));

    // Check if ANY calendar is now approved for auto-publishing
    $event_calendars = get_the_terms($post_id, 'event_caes_departments');
    $any_approved = false;
    
    error_log("Event calendars: " . print_r($event_calendars, true));
    
    if ($event_calendars && !is_wp_error($event_calendars)) {
        foreach ($event_calendars as $calendar_term) {
            $is_this_calendar_approved = isset($calendar_approval_status[$calendar_term->term_id]) && 
                                        $calendar_approval_status[$calendar_term->term_id] === 'approved';
            error_log("Calendar {$calendar_term->term_id} ({$calendar_term->name}) approved: " . ($is_this_calendar_approved ? 'YES' : 'NO'));
            
            if ($is_this_calendar_approved) {
                $any_approved = true;
                break; // Found at least one approved calendar
            }
        }
    }
    
    error_log("Any calendar approved: " . ($any_approved ? 'YES' : 'NO'));
    
    // Auto-publish if any calendar is approved and post is still pending
    $current_status = get_post_status($post_id);
    error_log("Current post status: $current_status");
    
    if ($any_approved && $current_status === 'pending') {
        error_log("CONDITIONS MET - Publishing post...");
        
        $update_post_result = wp_update_post(array(
            'ID' => $post_id,
            'post_status' => 'publish'
        ));
        
        error_log("wp_update_post result: " . print_r($update_post_result, true));
        
        // Check if status actually changed
        $new_status = get_post_status($post_id);
        error_log("New post status after update: $new_status");
        
        // Send notification to original submitter (only on first approval/publishing)
        $event_submitter = get_post_field('post_author', $post_id);
        $current_user_id = get_current_user_id();
        
        error_log("Event submitter ID: $event_submitter, Current user ID: $current_user_id");
        
        // Only send email if submitter is different from current user (don't email yourself)
        if ($event_submitter != $current_user_id) {
            error_log("Sending notification to submitter...");
            send_submitter_notification_email($post_id, $event_submitter);
        } else {
            error_log("Skipping email - user approved their own event");
        }
    } else {
        error_log("CONDITIONS NOT MET - Not publishing");
        error_log("- Any approved: " . ($any_approved ? 'YES' : 'NO'));
        error_log("- Current status is pending: " . ($current_status === 'pending' ? 'YES' : 'NO'));
    }
    
    error_log("=== END AJAX APPROVAL DEBUG ===");

    wp_send_json_success('Calendar approved successfully.');
}

// Add client-side validation for calendar selection
add_action('admin_footer', 'add_calendar_validation_script');

function add_calendar_validation_script() {
    global $typenow, $pagenow;
    
    if ('events' === $typenow && ('post.php' === $pagenow || 'post-new.php' === $pagenow)) {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Track if an approval button was clicked to avoid form validation
            var approvalButtonClicked = false;
            
            // Monitor approval button clicks
            $(document).on('click', '.approve-event-button', function() {
                approvalButtonClicked = true;
                
                // Re-enable form validation after 5 seconds
                setTimeout(function() {
                    approvalButtonClicked = false;
                }, 5000);
            });
            
            // Function to check if calendars are selected
            function checkCalendarsSelected() {
                var hasSelection = false;
                
                // Check ACF field
                var acfSelectors = [
                    'select[data-name="caes_department"]',
                    'select[name*="caes_department"]',
                    'input[name*="caes_department"]:checked',
                    '.acf-field[data-name="caes_department"] select',
                    '.acf-field[data-name="caes_department"] input:checked'
                ];
                
                for (var i = 0; i < acfSelectors.length; i++) {
                    var $field = $(acfSelectors[i]);
                    if ($field.length > 0) {
                        if ($field.is('select')) {
                            var val = $field.val();
                            if (val && (Array.isArray(val) ? val.length > 0 : val !== '')) {
                                hasSelection = true;
                                break;
                            }
                        } else if ($field.is('input:checked')) {
                            if ($field.length > 0) {
                                hasSelection = true;
                                break;
                            }
                        }
                    }
                }
                
                // Check WordPress native taxonomy checkboxes as fallback
                if (!hasSelection) {
                    var $taxonomyChecks = $('input[name="tax_input[event_caes_departments][]"]:checked');
                    if ($taxonomyChecks.length > 0) {
                        hasSelection = true;
                    }
                }
                
                return hasSelection;
            }
            
            // Function to show/hide warning
            function updateCalendarWarning() {
                if (approvalButtonClicked) {
                    return;
                }
                
                var hasCalendars = checkCalendarsSelected();
                var warningId = 'calendar-selection-warning';
                var existingWarning = $('#' + warningId);
                
                if (!hasCalendars) {
                    if (existingWarning.length === 0) {
                        var warningHtml = '<div id="' + warningId + '" style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; border-radius: 4px;">' +
                                         '<p style="margin: 0; color: #856404;"><strong>⚠️ Calendar Selection Required</strong></p>' +
                                         '<p style="margin: 5px 0 0 0; color: #856404; font-size: 13px;">Please select at least one calendar before submitting for approval.</p>' +
                                         '</div>';
                        
                        if ($('#submitdiv').length > 0) {
                            $('#submitdiv').before(warningHtml);
                        } else {
                            $('.wrap h1').after(warningHtml);
                        }
                    }
                } else {
                    existingWarning.remove();
                }
            }
            
            // Check on page load
            setTimeout(function() {
                updateCalendarWarning();
            }, 1000);
            
            // Monitor for changes to calendar selection
            $(document).on('change', 'select[name*="caes_department"], input[name*="caes_department"], input[name="tax_input[event_caes_departments][]"], select[data-name="caes_department"], .acf-field[data-name="caes_department"] select, .acf-field[data-name="caes_department"] input', function() {
                setTimeout(updateCalendarWarning, 100);
            });
            
            // Monitor ACF events if available
            if (typeof acf !== 'undefined') {
                acf.addAction('ready', function() {
                    setTimeout(updateCalendarWarning, 100);
                });
                
                acf.addAction('change', function() {
                    setTimeout(updateCalendarWarning, 100);
                });
            }
            
            // Intercept form submission
            $('#post').on('submit', function(e) {
                if (approvalButtonClicked) {
                    return true;
                }
                
                var hasCalendars = checkCalendarsSelected();
                var $submitButton = $('#publish, #save-post');
                var buttonText = $submitButton.val() || $submitButton.text();
                
                // Only prevent submission if trying to submit for review/approval
                if (!hasCalendars && (buttonText.includes('Submit') || buttonText.includes('Review') || buttonText.includes('Publish'))) {
                    e.preventDefault();
                    alert('Please select at least one calendar before submitting your event for approval.');
                    
                    var $calendarField = $('.acf-field[data-name="caes_department"]');
                    if ($calendarField.length > 0) {
                        $('html, body').animate({
                            scrollTop: $calendarField.offset().top - 100
                        }, 500);
                    }
                    
                    return false;
                }
                
                return true;
            });
        });
        </script>
        <?php
    }
}