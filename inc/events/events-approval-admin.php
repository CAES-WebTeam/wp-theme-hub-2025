<?php
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
    // Handle form submissions for updating approvers
    if (isset($_POST['update_calendar_approvers']) && wp_verify_nonce($_POST['_wpnonce'], 'update_calendar_approvers')) {
        if (isset($_POST['calendar_approvers']) && is_array($_POST['calendar_approvers'])) {
            foreach ($_POST['calendar_approvers'] as $term_id => $approver_id) {
                $term_id = intval($term_id);
                $approver_id = intval($approver_id);
                
                if ($approver_id > 0) {
                    update_field('calendar_approver', $approver_id, 'event_caes_departments_' . $term_id);
                } else {
                    update_field('calendar_approver', '', 'event_caes_departments_' . $term_id);
                }
            }
            
            echo '<div class="notice notice-success"><p>Calendar approvers updated successfully!</p></div>';
        }
    }
    
    // Get all calendars
    $calendars = get_terms(array(
        'taxonomy' => 'event_caes_departments',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC'
    ));
    
    // Get all users who can be approvers
    $potential_approvers = get_users(array(
        'role__in' => array('administrator', 'editor', 'event_approver'),
        'orderby' => 'display_name',
        'order' => 'ASC'
    ));
    
    ?>
    <div class="wrap">
        <h1>Calendar Management</h1>
        <p>Manage calendar approvers and view user permissions for each calendar.</p>
        
        <?php if (is_wp_error($calendars) || empty($calendars)): ?>
            <div class="notice notice-warning">
                <p>No calendars found. Please create some calendars in the Event Departments taxonomy first.</p>
            </div>
        <?php else: ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('update_calendar_approvers'); ?>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Calendar Name</th>
                            <th style="width: 20%;">Assigned Approver</th>
                            <th style="width: 25%;">Users Who Can Submit</th>
                            <th style="width: 25%;">Users Who Can Approve</th>
                            <th style="width: 5%;">Events</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($calendars as $calendar): ?>
                            <?php
                            $term_id = $calendar->term_id;
                            $assigned_approver_id = get_field('calendar_approver', 'event_caes_departments_' . $term_id);
                            
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
                            
                            // Get users who can approve this calendar (via permissions, not ACF field)
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
                                    <select name="calendar_approvers[<?php echo $term_id; ?>]" class="regular-text">
                                        <option value="">-- No Assigned Approver --</option>
                                        <?php foreach ($potential_approvers as $user): ?>
                                            <option value="<?php echo $user->ID; ?>" 
                                                    <?php selected($assigned_approver_id, $user->ID); ?>>
                                                <?php echo esc_html($user->display_name); ?> 
                                                (<?php echo esc_html($user->user_login); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                    <?php if ($assigned_approver_id): ?>
                                        <?php $approver = get_userdata($assigned_approver_id); ?>
                                        <?php if ($approver): ?>
                                            <br><small>Current: <?php echo esc_html($approver->display_name); ?></small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <?php if (!empty($submitters)): ?>
                                        <ul style="margin: 0; padding-left: 15px;">
                                            <?php foreach ($submitters as $submitter): ?>
                                                <li style="font-size: 13px;">
                                                    <?php echo esc_html($submitter->display_name); ?>
                                                    <small>(<?php echo esc_html($submitter->user_login); ?>)</small>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <em style="color: #666;">None assigned</em>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <?php
                                    $all_approvers = array();
                                    
                                    // Add assigned approver
                                    if ($assigned_approver_id) {
                                        $assigned_approver = get_userdata($assigned_approver_id);
                                        if ($assigned_approver) {
                                            $all_approvers[] = $assigned_approver->display_name . ' <small>(ACF Assigned)</small>';
                                        }
                                    }
                                    
                                    // Add permission-based approvers
                                    foreach ($permission_approvers as $approver) {
                                        $all_approvers[] = $approver->display_name . ' <small>(Permission)</small>';
                                    }
                                    
                                    // Add admins/editors (they can always approve)
                                    $admin_editors = get_users(array(
                                        'role__in' => array('administrator', 'editor'),
                                        'fields' => array('display_name')
                                    ));
                                    foreach ($admin_editors as $admin) {
                                        $all_approvers[] = $admin->display_name . ' <small>(Admin/Editor)</small>';
                                    }
                                    
                                    if (!empty($all_approvers)): ?>
                                        <ul style="margin: 0; padding-left: 15px;">
                                            <?php foreach (array_unique($all_approvers) as $approver): ?>
                                                <li style="font-size: 13px;"><?php echo $approver; ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <em style="color: #d63638;">⚠️ No approvers!</em>
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
                
                <div style="margin-top: 20px;">
                    <input type="submit" name="update_calendar_approvers" class="button button-primary" 
                           value="Update Assigned Approvers" />
                    <p class="description">
                        <strong>Note:</strong> Changes to "Users Who Can Submit/Approve" must be made in individual user profiles. 
                        This page only updates the primary assigned approver for each calendar.
                    </p>
                </div>
            </form>
            
            <div style="margin-top: 30px; padding: 15px; background: #f0f6fc; border-left: 4px solid #0073aa;">
                <h3>How Calendar Permissions Work:</h3>
                <ul>
                    <li><strong>Assigned Approver (ACF):</strong> Set above, gets approval emails for this calendar</li>
                    <li><strong>Permission-Based:</strong> Set in user profiles under "Event Calendar Permissions"</li>
                    <li><strong>Admin/Editor:</strong> Can always submit to and approve any calendar</li>
                </ul>
                
                <p><strong>⚠️ Important:</strong> Calendars without any approvers won't send notification emails!</p>
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

// Add a warning notice if calendars have no approvers
add_action('admin_notices', 'show_calendar_approver_warnings');

function show_calendar_approver_warnings() {
    global $pagenow, $typenow;
    
    // Only show on events pages
    if ($typenow !== 'events' && $pagenow !== 'admin.php') {
        return;
    }
    
    // Check for calendars without approvers
    $calendars = get_terms(array(
        'taxonomy' => 'event_caes_departments',
        'hide_empty' => false,
    ));
    
    $calendars_without_approvers = array();
    
    if (!is_wp_error($calendars)) {
        foreach ($calendars as $calendar) {
            $assigned_approver = get_field('calendar_approver', 'event_caes_departments_' . $calendar->term_id);
            
            // Check for permission-based approvers
            $permission_approvers = get_users(array(
                'meta_query' => array(
                    array(
                        'key' => 'calendar_approve_permissions',
                        'value' => '"' . $calendar->term_id . '"',
                        'compare' => 'LIKE'
                    )
                ),
                'fields' => 'ID'
            ));
            
            // If no assigned approver AND no permission-based approvers
            if (!$assigned_approver && empty($permission_approvers)) {
                $calendars_without_approvers[] = $calendar->name;
            }
        }
    }
    
    if (!empty($calendars_without_approvers)) {
        echo '<div class="notice notice-warning">';
        echo '<p><strong>Calendar Approver Warning:</strong> The following calendars have no assigned approvers and won\'t send notification emails:</p>';
        echo '<ul style="margin-left: 20px;">';
        foreach ($calendars_without_approvers as $calendar_name) {
            echo '<li>' . esc_html($calendar_name) . '</li>';
        }
        echo '</ul>';
        echo '<p><a href="' . admin_url('edit.php?post_type=events&page=calendar-management') . '" class="button">Manage Calendar Approvers</a></p>';
        echo '</div>';
    }
}