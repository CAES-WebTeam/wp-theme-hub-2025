<?php
/**
 * Calendar Management Admin Page - Simplified Version
 * Only uses user permission system (no ACF dropdowns)
 */

// Add admin menu item
add_action('admin_menu', 'add_calendar_management_page');

function add_calendar_management_page() {
    add_submenu_page(
        'edit.php?post_type=events',
        'Calendar Management',
        'Calendar Management',
        'manage_options',
        'calendar-management',
        'render_calendar_management_page'
    );
}

// Render the admin page
function render_calendar_management_page() {
    // Get all calendars
    $calendars = get_terms(array(
        'taxonomy' => 'event_caes_departments',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC'
    ));
    
    ?>
    <div class="wrap">
        <h1>Calendar Management</h1>
        <p>View calendar permissions and event counts. To modify permissions, edit individual user profiles.</p>
        
        <?php if (is_wp_error($calendars) || empty($calendars)): ?>
            <div class="notice notice-warning">
                <p>No calendars found. Please create some calendars in the Event Departments taxonomy first.</p>
            </div>
        <?php else: ?>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 30%;">Calendar Name</th>
                        <th style="width: 30%;">Users Who Can Submit</th>
                        <th style="width: 30%;">Users Who Can Approve</th>
                        <th style="width: 10%;">Events</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($calendars as $calendar): ?>
                        <?php
                        $term_id = $calendar->term_id;
                        
                        // Get users who can submit to this calendar
                        $submitters = get_users(array(
                            'meta_query' => array(
                                array(
                                    'key' => 'calendar_submit_permissions',
                                    'value' => '"' . $term_id . '"',
                                    'compare' => 'LIKE'
                                )
                            ),
                            'fields' => array('ID', 'display_name'),
                            'orderby' => 'display_name'
                        ));
                        
                        // Get users who can approve this calendar
                        $permission_approvers = get_users(array(
                            'meta_query' => array(
                                array(
                                    'key' => 'calendar_approve_permissions',
                                    'value' => '"' . $term_id . '"',
                                    'compare' => 'LIKE'
                                )
                            ),
                            'fields' => array('ID', 'display_name'),
                            'orderby' => 'display_name'
                        ));
                        
                        // Count events in this calendar
                        $event_count = get_posts(array(
                            'post_type' => 'events',
                            'post_status' => array('publish', 'pending', 'draft'),
                            'posts_per_page' => -1,
                            'fields' => 'ids',
                            'tax_query' => array(
                                array(
                                    'taxonomy' => 'event_caes_departments',
                                    'field' => 'term_id',
                                    'terms' => $term_id
                                )
                            )
                        ));
                        
                        $event_count = count($event_count);
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($calendar->name); ?></strong>
                                <br><small>ID: <?php echo $term_id; ?></small>
                                <?php if (!empty($calendar->description)): ?>
                                    <br><small><?php echo esc_html($calendar->description); ?></small>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <?php if (!empty($submitters)): ?>
                                    <ul style="margin: 0; padding-left: 15px; max-height: 120px; overflow-y: auto;">
                                        <?php foreach ($submitters as $submitter): ?>
                                            <li style="font-size: 13px;">
                                                <?php echo esc_html($submitter->display_name); ?>
                                                <small>(<?php echo esc_html($submitter->user_login); ?>)</small>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <small style="color: #666;">Plus all Admins & Editors</small>
                                <?php else: ?>
                                    <em style="color: #666;">Only Admins & Editors</em>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <?php if (!empty($permission_approvers)): ?>
                                    <ul style="margin: 0; padding-left: 15px; max-height: 120px; overflow-y: auto;">
                                        <?php foreach ($permission_approvers as $approver): ?>
                                            <li style="font-size: 13px;">
                                                <?php echo esc_html($approver->display_name); ?>
                                                <small>(<?php echo esc_html($approver->user_login); ?>)</small>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <small style="color: #666;">Plus all Admins & Editors</small>
                                <?php else: ?>
                                    <em style="color: #d63638;">⚠️ Only Admins & Editors (no specific approvers)</em>
                                <?php endif; ?>
                            </td>
                            
                            <td style="text-align: center;">
                                <strong><?php echo $event_count; ?></strong>
                                <?php if ($event_count > 0): ?>
                                    <br><a href="<?php echo admin_url('edit.php?post_type=events&event_caes_departments=' . $term_id); ?>" 
                                           style="font-size: 12px;">View</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div style="margin-top: 30px; padding: 15px; background: #f0f6fc; border-left: 4px solid #0073aa;">
                <h3>How to Manage Permissions:</h3>
                <ul>
                    <li><strong>Submit Permissions:</strong> Go to Users → Edit User → "Event Calendar Permissions"</li>
                    <li><strong>Approve Permissions:</strong> Go to Users → Edit User → "Event Calendar Permissions"</li>
                    <li><strong>Admin/Editor:</strong> Automatically have access to all calendars</li>
                </ul>
                
                <p><strong>⚠️ Note:</strong> Calendars with no specific approvers will only notify Admins & Editors.</p>
            </div>
            
        <?php endif; ?>
    </div>
    
    <style>
    .wp-list-table th, .wp-list-table td {
        vertical-align: top;
        padding: 12px 8px;
    }
    .wp-list-table ul {
        max-height: 120px;
        overflow-y: auto;
    }
    .wp-list-table small {
        color: #666;
    }
    </style>
    <?php
}