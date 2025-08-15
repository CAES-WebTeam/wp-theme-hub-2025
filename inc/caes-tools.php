<?php

// First, create the main "CAES Tools" menu
function add_caes_tools_menu()
{
    add_menu_page(
        'CAES Tools', // Page title
        'CAES Tools', // Menu title
        'manage_options', // Capability required
        'caes-tools', // Menu slug (this will be the parent for submenus)
        'caes_tools_main_page', // Callback function for main page
        'dashicons-admin-tools', // Icon (optional - you can use any dashicon or custom icon)
        76 // Position after tools
    );
}
add_action('admin_menu', 'add_caes_tools_menu');

// Main page content (this will show when someone clicks "CAES Tools")
function caes_tools_main_page()
{
    ?>
    <div class="wrap">
        <h1>CAES Tools</h1>
        <p>Welcome to the CAES Tools dashboard. Please select a tool from the submenu.</p>
        
        <div class="card">
            <h2>Tools for publications and stories</h2>
            <ul>
                <li><a href="<?php echo admin_url('admin.php?page=user-data-management'); ?>">User Data Management</a></li>
                <li><a href="<?php echo admin_url('admin.php?page=link-content-admin'); ?>">Link Writers, Experts, and Publication Authors</a></li>
            </ul>
            <h2>Tools for publications</h2>
            <ul>
                <li><a href="<?php echo admin_url('admin.php?page=publication-api-tool'); ?>">Publication Import Tool</a></li>
                <li><a href="<?php echo admin_url('admin.php?page=update-publication-history'); ?>">Update Publication History</a></li>
            </ul>
            <h2>Tools for stories</h2>
            <ul>
                <li><a href="<?php echo admin_url('admin.php?page=story-meta-association-tools'); ?>">Story Meta Association Tools</a></li>
                <li><a href="<?php echo admin_url('admin.php?page=release-date-migration'); ?>">Story Release Date Migration</a></li>
                <li><a href="<?php echo admin_url('admin.php?page=clear-release-date'); ?>">Story Clear Release Date Field</a></li>
                <li><a href="<?php echo admin_url('admin.php?page=duplicate-post-checker'); ?>">Story Duplicate Checker</a></li>
            </ul>
        </div>
    </div>
    <?php
}