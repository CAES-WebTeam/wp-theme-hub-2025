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
        <p>Welcome to the CAES Tools dashboard. Please select a tool from the submenu on the left.</p>
    </div>
    <?php
}