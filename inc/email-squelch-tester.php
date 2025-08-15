<?php
/**
 * WordPress ACF User Email Notification Test
 * 
 * This test page finds a user by ACF field 'personnel_id' and tests
 * email notification suppression by changing their email address
 * with notifications off, then on again.
 * 
 * Place this file in your WordPress root directory and access it via browser.
 * Make sure to remove it after testing for security purposes.
 */

// Load WordPress
// require_once('wp-config.php');
// require_once('wp-load.php');

// Check if ACF is active
if (!function_exists('get_field')) {
    die('Error: Advanced Custom Fields (ACF) plugin is not active.');
}

// Functions to control email notifications
function suppress_email_notifications() {
    add_filter('send_email_change_email', '__return_false');
    add_filter('send_new_user_notifications', '__return_false');
    add_filter('send_password_change_email', '__return_false');
    add_filter('send_recovery_mode_email', '__return_false');
}

function enable_email_notifications() {
    remove_filter('send_email_change_email', '__return_false');
    remove_filter('send_new_user_notifications', '__return_false');
    remove_filter('send_password_change_email', '__return_false');
    remove_filter('send_recovery_mode_email', '__return_false');
}

function check_email_filters_status() {
    return array(
        'send_email_change_email' => has_filter('send_email_change_email', '__return_false'),
        'send_new_user_notifications' => has_filter('send_new_user_notifications', '__return_false'),
        'send_password_change_email' => has_filter('send_password_change_email', '__return_false'),
        'send_recovery_mode_email' => has_filter('send_recovery_mode_email', '__return_false')
    );
}

// Function to find user by ACF personnel_id
function find_user_by_personnel_id($personnel_id) {
    $users = get_users(array(
        'meta_query' => array(
            array(
                'key' => 'personnel_id',
                'value' => $personnel_id,
                'compare' => '='
            )
        )
    ));
    
    if (empty($users)) {
        return false;
    }
    
    return $users[0];
}

// Function to update user email and log the change
function update_user_email_with_log($user_id, $new_email, $notifications_enabled) {
    $old_email = get_userdata($user_id)->user_email;
    
    $result = wp_update_user(array(
        'ID' => $user_id,
        'user_email' => $new_email
    ));
    
    if (is_wp_error($result)) {
        return array(
            'success' => false, 
            'error' => $result->get_error_message(),
            'old_email' => $old_email,
            'new_email' => $new_email,
            'notifications' => $notifications_enabled ? 'ENABLED' : 'DISABLED'
        );
    }
    
    return array(
        'success' => true, 
        'user_id' => $result,
        'old_email' => $old_email,
        'new_email' => $new_email,
        'notifications' => $notifications_enabled ? 'ENABLED' : 'DISABLED'
    );
}

// Handle form submission
$action = isset($_POST['action']) ? $_POST['action'] : '';
$results = array();
$target_personnel_id = 11287;
$test_email = 'jesse.kuzy25@uga.edu';

if ($action === 'run_test') {
    $results['steps'] = array();
    
    // Step 1: Find user by personnel_id
    $user = find_user_by_personnel_id($target_personnel_id);
    
    if (!$user) {
        $results['error'] = "User with personnel_id '{$target_personnel_id}' not found.";
    } else {
        $results['user'] = array(
            'id' => $user->ID,
            'login' => $user->user_login,
            'email' => $user->user_email,
            'personnel_id' => get_field('personnel_id', 'user_' . $user->ID)
        );
        
        $original_email = $user->user_email;
        
        // Step 2: Turn OFF email notifications
        suppress_email_notifications();
        $results['steps'][] = array(
            'step' => 'Suppress email notifications',
            'status' => 'completed',
            'filters' => check_email_filters_status()
        );
        
        // Step 3: Change email TO jesse.kuzy25@uga.edu (with notifications OFF)
        $change1 = update_user_email_with_log($user->ID, $test_email, false);
        $results['steps'][] = array(
            'step' => 'Change email TO ' . $test_email . ' (notifications OFF)',
            'result' => $change1
        );
        
        // Step 4: Change email FROM jesse.kuzy25@uga.edu back to original (with notifications OFF)
        $change2 = update_user_email_with_log($user->ID, $original_email, false);
        $results['steps'][] = array(
            'step' => 'Change email FROM ' . $test_email . ' back to original (notifications OFF)',
            'result' => $change2
        );
        
        // Step 5: Turn ON email notifications
        enable_email_notifications();
        $results['steps'][] = array(
            'step' => 'Enable email notifications',
            'status' => 'completed',
            'filters' => check_email_filters_status()
        );
        
        // Step 6: Change email again (with notifications ON) - this should send an email
        $change3 = update_user_email_with_log($user->ID, $test_email, true);
        $results['steps'][] = array(
            'step' => 'Change email TO ' . $test_email . ' again (notifications ON)',
            'result' => $change3
        );
        
        // Step 7: Change back to original (with notifications ON) - this should also send an email
        $change4 = update_user_email_with_log($user->ID, $original_email, true);
        $results['steps'][] = array(
            'step' => 'Change email back to original (notifications ON)',
            'result' => $change4
        );
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>WordPress ACF User Email Notification Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 40px auto; padding: 20px; }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #0c5460; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .filter-status { display: inline-block; padding: 3px 8px; border-radius: 3px; margin-left: 10px; font-size: 12px; }
        .active { background: #28a745; color: white; }
        .inactive { background: #dc3545; color: white; }
        .step { background: #f8f9fa; border-left: 4px solid #007cba; padding: 15px; margin: 10px 0; }
        .step h4 { margin: 0 0 10px 0; color: #007cba; }
        button { background: #007cba; color: white; border: none; padding: 12px 24px; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { opacity: 0.9; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        th { background: #f2f2f2; }
        .email-change { font-family: monospace; background: #f8f9fa; padding: 2px 4px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>WordPress ACF User Email Notification Test</h1>
    
    <div class="info">
        <strong>Test Purpose:</strong> This script will find the user with ACF field 'personnel_id' = <?php echo $target_personnel_id; ?>, 
        then test email notification suppression by changing their email address multiple times with notifications both OFF and ON.
    </div>
    
    <div class="warning">
        <strong>Target:</strong> Looking for user with ACF field 'personnel_id' = <?php echo $target_personnel_id; ?><br>
        <strong>Test Email:</strong> <?php echo $test_email; ?>
    </div>
    
    <h2>Current Filter Status</h2>
    <?php 
    $current_filters = check_email_filters_status();
    $active_count = array_sum($current_filters);
    ?>
    <div class="<?php echo ($active_count > 0) ? 'warning' : 'success'; ?>">
        <?php echo $active_count; ?> out of 4 email suppression filters are currently active
    </div>
    
    <table>
        <tr><th>Filter Name</th><th>Status</th></tr>
        <?php foreach ($current_filters as $filter_name => $is_active): ?>
        <tr>
            <td><?php echo esc_html($filter_name); ?></td>
            <td>
                <span class="filter-status <?php echo $is_active ? 'active' : 'inactive'; ?>">
                    <?php echo $is_active ? 'ACTIVE' : 'INACTIVE'; ?>
                </span>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <h2>Run Test</h2>
    <form method="post">
        <input type="hidden" name="action" value="run_test">
        <button type="submit">Run Email Notification Test</button>
    </form>
    
    <?php if (!empty($results)): ?>
        <h2>Test Results</h2>
        
        <?php if (isset($results['error'])): ?>
            <div class="error">
                <?php echo esc_html($results['error']); ?>
            </div>
        <?php else: ?>
            
            <?php if (isset($results['user'])): ?>
                <h3>Found User</h3>
                <table>
                    <tr><th>Property</th><th>Value</th></tr>
                    <tr><td>User ID</td><td><?php echo esc_html($results['user']['id']); ?></td></tr>
                    <tr><td>Username</td><td><?php echo esc_html($results['user']['login']); ?></td></tr>
                    <tr><td>Current Email</td><td><?php echo esc_html($results['user']['email']); ?></td></tr>
                    <tr><td>Personnel ID (ACF)</td><td><?php echo esc_html($results['user']['personnel_id']); ?></td></tr>
                </table>
            <?php endif; ?>
            
            <h3>Test Steps</h3>
            <?php foreach ($results['steps'] as $index => $step): ?>
                <div class="step">
                    <h4>Step <?php echo $index + 1; ?>: <?php echo esc_html($step['step']); ?></h4>
                    
                    <?php if (isset($step['result'])): ?>
                        <?php $result = $step['result']; ?>
                        <div class="<?php echo $result['success'] ? 'success' : 'error'; ?>">
                            <?php if ($result['success']): ?>
                                ✓ Email changed successfully
                            <?php else: ?>
                                ✗ Email change failed: <?php echo esc_html($result['error']); ?>
                            <?php endif; ?>
                        </div>
                        
                        <table style="max-width: 600px;">
                            <tr><td><strong>From:</strong></td><td class="email-change"><?php echo esc_html($result['old_email']); ?></td></tr>
                            <tr><td><strong>To:</strong></td><td class="email-change"><?php echo esc_html($result['new_email']); ?></td></tr>
                            <tr><td><strong>Notifications:</strong></td><td>
                                <span class="filter-status <?php echo ($result['notifications'] === 'ENABLED') ? 'inactive' : 'active'; ?>">
                                    <?php echo esc_html($result['notifications']); ?>
                                </span>
                            </td></tr>
                        </table>
                        
                    <?php elseif (isset($step['filters'])): ?>
                        <div class="success">✓ Filter status updated</div>
                        <table style="max-width: 400px;">
                            <?php foreach ($step['filters'] as $filter => $status): ?>
                            <tr>
                                <td><?php echo esc_html($filter); ?></td>
                                <td>
                                    <span class="filter-status <?php echo $status ? 'active' : 'inactive'; ?>">
                                        <?php echo $status ? 'ACTIVE' : 'INACTIVE'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <div class="info">
                <strong>Expected Results:</strong><br>
                • Steps 3 & 4 (notifications OFF): No emails should be sent<br>
                • Steps 6 & 7 (notifications ON): Email change notifications should be sent<br>
                <strong>Check your email inbox and spam folder to verify!</strong>
            </div>
            
        <?php endif; ?>
    <?php endif; ?>
    
    <h2>Implementation Code</h2>
    <p>Here's the code to suppress email notifications in your import script:</p>
    <pre><code>// Turn OFF email notifications
add_filter('send_email_change_email', '__return_false');
add_filter('send_new_user_notifications', '__return_false');

// Your user import/update code here...

// Turn ON email notifications (if needed)
remove_filter('send_email_change_email', '__return_false');
remove_filter('send_new_user_notifications', '__return_false');</code></pre>
    
    <div class="error">
        <strong>Security Notice:</strong> Remove this test file from your server after testing is complete.
    </div>
    
</body>
</html>