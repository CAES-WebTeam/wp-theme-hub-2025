<?php
/**
 * Custom Admin Plugin
 * Adds settings to allow script bypass for admin/editor users
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CustomAdminPlugin {
    
    private $option_name = 'custom_admin_settings';
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        
        // Hook into script filtering if enabled
        if ($this->is_script_bypass_enabled()) {
            add_action('init', array($this, 'setup_script_bypass'));
        }
    }
    
    /**
     * Add menu item under CAES Tools
     */
    public function add_admin_menu() {
        add_submenu_page(
            'caes-tools',                    // Parent slug - points to CAES Tools
            'Custom Admin Settings',         // Page title
            'Custom Admin',                  // Menu title
            'manage_options',
            'custom-admin-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting(
            'custom_admin_settings_group',
            $this->option_name,
            array($this, 'sanitize_settings')
        );
        
        add_settings_section(
            'custom_admin_main_section',
            'Script Settings',
            array($this, 'section_callback'),
            'custom-admin-settings'
        );
        
        add_settings_field(
            'allow_scripts_bypass',
            'Allow Script Bypass',
            array($this, 'checkbox_callback'),
            'custom-admin-settings',
            'custom_admin_main_section'
        );
    }
    
    /**
     * Settings section description
     */
    public function section_callback() {
        echo '<p>Configure script handling for admin and editor users.</p>';
    }
    
    /**
     * Checkbox field callback
     */
    public function checkbox_callback() {
        $options = get_option($this->option_name);
        $checked = isset($options['allow_scripts_bypass']) && $options['allow_scripts_bypass'] == 1;
        
        echo '<label>';
        echo '<input type="checkbox" name="' . $this->option_name . '[allow_scripts_bypass]" value="1"' . checked(1, $checked, false) . ' />';
        echo ' Allow admins and editors to use scripts without stripping';
        echo '</label>';
        echo '<p class="description">When enabled, users with admin or editor roles can use scripts that would normally be stripped out.</p>';
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        if (isset($input['allow_scripts_bypass'])) {
            $sanitized['allow_scripts_bypass'] = intval($input['allow_scripts_bypass']);
        }
        
        return $sanitized;
    }
    
    /**
     * Settings page HTML
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>Custom Admin Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('custom_admin_settings_group');
                do_settings_sections('custom-admin-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Check if script bypass is enabled
     */
    public function is_script_bypass_enabled() {
        $options = get_option($this->option_name);
        return isset($options['allow_scripts_bypass']) && $options['allow_scripts_bypass'] == 1;
    }
    
    /**
     * Setup script bypass functionality
     */
    public function setup_script_bypass() {
        // Only apply for admin and editor users
        if ($this->current_user_can_bypass_scripts()) {
            // Remove script stripping filters
            remove_filter('content_save_pre', 'wp_filter_post_kses');
            remove_filter('excerpt_save_pre', 'wp_filter_post_kses');
            remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');
            
            // Allow unfiltered HTML for these users
            add_filter('wp_kses_allowed_html', array($this, 'allow_script_tags'), 10, 2);
            
            // Remove script stripping from content output
            remove_filter('the_content', 'wp_kses_post');
            remove_filter('the_excerpt', 'wp_kses_post');
        }
    }
    
    /**
     * Check if current user can bypass script restrictions
     */
    private function current_user_can_bypass_scripts() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        return current_user_can('edit_others_posts') || current_user_can('manage_options');
    }
    
    /**
     * Allow script tags in KSES
     */
    public function allow_script_tags($allowed_tags, $context) {
        if ($context === 'post' && $this->current_user_can_bypass_scripts()) {
            $allowed_tags['script'] = array(
                'type' => true,
                'src' => true,
                'async' => true,
                'defer' => true,
                'charset' => true,
                'crossorigin' => true,
                'integrity' => true,
            );
            
            $allowed_tags['noscript'] = array();
            
            // Also allow other potentially restricted tags
            $allowed_tags['iframe'] = array(
                'src' => true,
                'width' => true,
                'height' => true,
                'frameborder' => true,
                'allowfullscreen' => true,
                'sandbox' => true,
                'loading' => true,
            );
            
            $allowed_tags['embed'] = array(
                'src' => true,
                'type' => true,
                'width' => true,
                'height' => true,
            );
            
            $allowed_tags['object'] = array(
                'data' => true,
                'type' => true,
                'width' => true,
                'height' => true,
            );
        }
        
        return $allowed_tags;
    }
    
    /**
     * Add custom CSS for admin
     */
    public function admin_styles() {
        ?>
        <style>
        .custom-admin-notice {
            background: #d1ecf1;
            border-left: 4px solid #bee5eb;
            padding: 12px;
            margin: 20px 0;
        }
        </style>
        <?php
    }
}

// Initialize the plugin
new CustomAdminPlugin();

/**
 * Add notice on admin dashboard if script bypass is enabled
 */
add_action('admin_notices', function() {
    $plugin = new CustomAdminPlugin();
    if ($plugin->is_script_bypass_enabled() && current_user_can('manage_options')) {
        echo '<div class="notice notice-info custom-admin-notice">';
        echo '<p><strong>Custom Admin:</strong> Script bypass is enabled for admin and editor users on this site.</p>';
        echo '</div>';
    }
});

// Network admin integration for multisite
if (is_multisite()) {
    add_action('network_admin_menu', function() {
        add_submenu_page(
            'sites.php',
            'Custom Admin Network Settings',
            'Custom Admin',
            'manage_network',
            'custom-admin-network',
            'custom_admin_network_page'
        );
    });
    
    function custom_admin_network_page() {
        if (isset($_GET['id'])) {
            $blog_id = intval($_GET['id']);
            switch_to_blog($blog_id);
            
            $plugin = new CustomAdminPlugin();
            $is_enabled = $plugin->is_script_bypass_enabled();
            
            restore_current_blog();
            
            echo '<div class="wrap">';
            echo '<h1>Custom Admin - Site ID: ' . $blog_id . '</h1>';
            echo '<p>Script bypass status: ' . ($is_enabled ? '<strong style="color: green;">Enabled</strong>' : '<strong style="color: red;">Disabled</strong>') . '</p>';
            echo '<p><a href="' . get_admin_url($blog_id, 'options-general.php?page=custom-admin-settings') . '" target="_blank">Manage Settings</a></p>';
            echo '</div>';
        }
    }
}
