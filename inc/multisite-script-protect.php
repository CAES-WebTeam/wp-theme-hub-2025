<?php
/**
 * Plugin Name: Trusted User HTML Access
 * Description: Grant unfiltered_html capability to specific trusted users
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

class TrustedUserHTML {
    
    private $option_name = 'trusted_html_users';
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_filter('user_has_cap', array($this, 'grant_unfiltered_html'), 10, 4);
        
        // Network admin menu for multisite
        if (is_multisite()) {
            add_action('network_admin_menu', array($this, 'add_network_menu'));
        }
    }
    
    /**
     * Add settings page
     */
    public function add_admin_menu() {
        add_submenu_page(
            'caes-tools',                     // Parent slug - points to CAES Tools
            'Trusted HTML Users',             // Page title
            'Trusted HTML Users',             // Menu title
            'manage_options',
            'trusted-html-users',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Add network admin menu
     */
    public function add_network_menu() {
        add_submenu_page(
            'settings.php',
            'Trusted HTML Users',
            'Trusted HTML Users',
            'manage_network',
            'trusted-html-network',
            array($this, 'network_settings_page')
        );
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting(
            'trusted_html_group',
            $this->option_name,
            array($this, 'sanitize_settings')
        );
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        if (isset($_POST['submit']) && check_admin_referer('trusted_html_settings')) {
            $this->save_settings();
        }
        
        $trusted_users = $this->get_trusted_users();
        $all_users = $this->get_eligible_users();
        
        ?>
        <div class="wrap">
            <h1>Trusted HTML Users</h1>
            <p>Grant unfiltered HTML capability to specific trusted users. These users will be able to add scripts and embed code.</p>
            
            <div class="notice notice-warning">
                <p><strong>Security Warning:</strong> Only grant this capability to users you completely trust. They will be able to execute arbitrary JavaScript and HTML.</p>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('trusted_html_settings'); ?>
                
                <h2>Current Trusted Users</h2>
                <?php if (!empty($trusted_users)): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trusted_users as $user_id): ?>
                                <?php $user = get_userdata($user_id); ?>
                                <?php if ($user): ?>
                                    <tr>
                                        <td><?php echo esc_html($user->user_login); ?></td>
                                        <td><?php echo esc_html($user->user_email); ?></td>
                                        <td><?php echo esc_html(implode(', ', $user->roles)); ?></td>
                                        <td>
                                            <button type="submit" name="remove_user" value="<?php echo esc_attr($user_id); ?>" class="button">Remove</button>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><em>No trusted users configured.</em></p>
                <?php endif; ?>
                
                <h2 style="margin-top: 30px;">Add Trusted User</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Select User</th>
                        <td>
                            <select name="add_user" style="min-width: 300px;">
                                <option value="">-- Select a user --</option>
                                <?php foreach ($all_users as $user): ?>
                                    <?php if (!in_array($user->ID, $trusted_users)): ?>
                                        <option value="<?php echo esc_attr($user->ID); ?>">
                                            <?php echo esc_html($user->user_login); ?> (<?php echo esc_html($user->user_email); ?>)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Select an admin or editor to grant unfiltered HTML capability.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Save Changes'); ?>
            </form>
            
            <hr style="margin: 40px 0;">
            
            <h2>Audit Log</h2>
            <?php $this->display_audit_log(); ?>
        </div>
        
        <style>
            .trusted-user-badge {
                display: inline-block;
                background: #00a32a;
                color: white;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: bold;
            }
        </style>
        <?php
    }
    
    /**
     * Network settings page
     */
    public function network_settings_page() {
        if (!is_multisite()) {
            return;
        }
        
        $sites = get_sites(array('number' => 1000));
        
        ?>
        <div class="wrap">
            <h1>Trusted HTML Users - Network Overview</h1>
            <p>View which sites have trusted HTML users configured.</p>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Site</th>
                        <th>URL</th>
                        <th>Trusted Users</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sites as $site): ?>
                        <?php
                        switch_to_blog($site->blog_id);
                        $trusted_users = $this->get_trusted_users();
                        $site_url = get_site_url();
                        restore_current_blog();
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($site->blogname ?: 'Site ' . $site->blog_id); ?></strong></td>
                            <td><?php echo esc_html($site_url); ?></td>
                            <td>
                                <?php if (!empty($trusted_users)): ?>
                                    <span class="trusted-user-badge"><?php echo count($trusted_users); ?> users</span>
                                <?php else: ?>
                                    <em>None</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(get_admin_url($site->blog_id, 'options-general.php?page=trusted-html-users')); ?>" 
                                   class="button" target="_blank">Manage</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        $trusted_users = $this->get_trusted_users();
        
        // Add user
        if (!empty($_POST['add_user'])) {
            $user_id = intval($_POST['add_user']);
            if (!in_array($user_id, $trusted_users)) {
                $trusted_users[] = $user_id;
                $this->log_action('added', $user_id);
                add_settings_error('trusted_html', 'user_added', 'User added successfully.', 'success');
            }
        }
        
        // Remove user
        if (!empty($_POST['remove_user'])) {
            $user_id = intval($_POST['remove_user']);
            $trusted_users = array_diff($trusted_users, array($user_id));
            $this->log_action('removed', $user_id);
            add_settings_error('trusted_html', 'user_removed', 'User removed successfully.', 'success');
        }
        
        update_option($this->option_name, array_values($trusted_users));
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        if (!is_array($input)) {
            return array();
        }
        return array_map('intval', $input);
    }
    
    /**
     * Get trusted users
     */
    private function get_trusted_users() {
        $users = get_option($this->option_name, array());
        return is_array($users) ? $users : array();
    }
    
    /**
     * Get eligible users (admins and editors only)
     */
    private function get_eligible_users() {
        return get_users(array(
            'role__in' => array('administrator', 'editor'),
            'orderby' => 'display_name',
            'number' => 1000
        ));
    }
    
    /**
     * Grant unfiltered_html capability to trusted users
     */
    public function grant_unfiltered_html($allcaps, $caps, $args, $user) {
        // Only on multisite or if unfiltered_html was removed
        if (!isset($user->ID)) {
            return $allcaps;
        }
        
        $trusted_users = $this->get_trusted_users();
        
        if (in_array($user->ID, $trusted_users)) {
            $allcaps['unfiltered_html'] = true;
        }
        
        return $allcaps;
    }
    
    /**
     * Log actions for audit trail
     */
    private function log_action($action, $user_id) {
        $log = get_option('trusted_html_audit_log', array());
        
        $entry = array(
            'action' => $action,
            'user_id' => $user_id,
            'user_login' => get_userdata($user_id)->user_login,
            'performed_by' => get_current_user_id(),
            'performed_by_login' => wp_get_current_user()->user_login,
            'timestamp' => current_time('mysql'),
            'ip_address' => $_SERVER['REMOTE_ADDR']
        );
        
        array_unshift($log, $entry);
        
        // Keep only last 100 entries
        $log = array_slice($log, 0, 100);
        
        update_option('trusted_html_audit_log', $log);
    }
    
    /**
     * Display audit log
     */
    private function display_audit_log() {
        $log = get_option('trusted_html_audit_log', array());
        
        if (empty($log)) {
            echo '<p><em>No audit log entries.</em></p>';
            return;
        }
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>Date/Time</th>';
        echo '<th>Action</th>';
        echo '<th>User</th>';
        echo '<th>Performed By</th>';
        echo '<th>IP Address</th>';
        echo '</tr></thead><tbody>';
        
        foreach (array_slice($log, 0, 20) as $entry) {
            echo '<tr>';
            echo '<td>' . esc_html($entry['timestamp']) . '</td>';
            echo '<td>' . esc_html(ucfirst($entry['action'])) . '</td>';
            echo '<td>' . esc_html($entry['user_login']) . '</td>';
            echo '<td>' . esc_html($entry['performed_by_login']) . '</td>';
            echo '<td>' . esc_html($entry['ip_address']) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
}

// Initialize
new TrustedUserHTML();

/**
 * Add indicator badge to user list
 */
add_filter('user_row_actions', function($actions, $user) {
    $plugin = new TrustedUserHTML();
    $trusted_users = get_option('trusted_html_users', array());
    
    if (in_array($user->ID, $trusted_users)) {
        $actions['trusted_html'] = '<span style="color: #00a32a; font-weight: bold;">✓ Trusted HTML</span>';
    }
    
    return $actions;
}, 10, 2);