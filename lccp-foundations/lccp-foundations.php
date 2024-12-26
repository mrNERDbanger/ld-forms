<?php
/**
 * Plugin Name: LCCP Foundations
 * Plugin URI: https://yourwebsite.com/lccp-foundations
 * Description: Creates custom roles and user fields for the LCCP program
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Text Domain: lccp-foundations
 * Domain Path: /languages
 *
 * @package LCCP_Foundations
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('LCCP_FOUNDATIONS_VERSION', '1.0.0');
define('LCCP_FOUNDATIONS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LCCP_FOUNDATIONS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once LCCP_FOUNDATIONS_PLUGIN_DIR . 'includes/class-lccp-roles.php';
require_once LCCP_FOUNDATIONS_PLUGIN_DIR . 'includes/class-lccp-user-fields.php';
require_once LCCP_FOUNDATIONS_PLUGIN_DIR . 'includes/class-lccp-forms.php';

// Initialize plugin
function lccp_foundations_init() {
    // Initialize roles
    LCCP_Roles::init();
    
    // Initialize user fields
    LCCP_User_Fields::init();
    
    // Initialize forms
    LCCP_Forms::init();
}
add_action('plugins_loaded', 'lccp_foundations_init');

// Activation hook
register_activation_hook(__FILE__, 'lccp_foundations_activate');
function lccp_foundations_activate() {
    // Create roles on activation
    LCCP_Roles::create_roles();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'lccp_foundations_deactivate');
function lccp_foundations_deactivate() {
    // Clean up if needed
    flush_rewrite_rules();
} 