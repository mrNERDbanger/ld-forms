<?php
/**
 * Plugin Name: LCCP Foundations
 * Plugin URI: https://fearlessliving.org/
 * Description: Core functionality for the LCCP platform, including custom roles, user fields, and forms.
 * Version: 1.0.0
 * Author: Fearless Living Institute
 * Author URI: https://fearlessliving.org/
 * Text Domain: lccp-foundations
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('LCCP_FOUNDATIONS_VERSION', '1.0.0');
define('LCCP_FOUNDATIONS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LCCP_FOUNDATIONS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
class LCCP_Foundations {
    /**
     * Instance of this class
     *
     * @var LCCP_Foundations
     */
    private static $instance = null;

    /**
     * Get instance of this class
     *
     * @return LCCP_Foundations
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->includes();
        $this->init();

        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
    }

    /**
     * Include required files
     */
    private function includes() {
        require_once LCCP_FOUNDATIONS_PLUGIN_DIR . 'includes/class-lccp-roles.php';
        require_once LCCP_FOUNDATIONS_PLUGIN_DIR . 'includes/class-lccp-user-fields.php';
        require_once LCCP_FOUNDATIONS_PLUGIN_DIR . 'includes/class-lccp-forms.php';
        require_once LCCP_FOUNDATIONS_PLUGIN_DIR . 'includes/class-lccp-form-decoder.php';
    }

    /**
     * Initialize plugin components
     */
    private function init() {
        // Initialize roles
        new LCCP_Roles();

        // Initialize user fields
        new LCCP_User_Fields();

        // Initialize forms
        new LCCP_Forms();

        // Load text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'lccp-foundations',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'lccp-forms',
            LCCP_FOUNDATIONS_PLUGIN_URL . 'assets/css/lccp-forms.css',
            array(),
            LCCP_FOUNDATIONS_VERSION
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts() {
        wp_enqueue_style(
            'lccp-forms-admin',
            LCCP_FOUNDATIONS_PLUGIN_URL . 'assets/css/lccp-forms.css',
            array(),
            LCCP_FOUNDATIONS_VERSION
        );
    }

    /**
     * Activation hook
     */
    public static function activate() {
        // Ensure roles are created on activation
        require_once LCCP_FOUNDATIONS_PLUGIN_DIR . 'includes/class-lccp-roles.php';
        $roles = new LCCP_Roles();
        $roles->create_roles();

        // Create necessary database tables if needed
        // Flush rewrite rules for custom post types
        flush_rewrite_rules();
    }

    /**
     * Deactivation hook
     */
    public static function deactivate() {
        // Clean up if necessary
        flush_rewrite_rules();
    }
}

// Initialize the plugin
function lccp_foundations() {
    return LCCP_Foundations::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'lccp_foundations');

// Register activation and deactivation hooks
register_activation_hook(__FILE__, array('LCCP_Foundations', 'activate'));
register_deactivation_hook(__FILE__, array('LCCP_Foundations', 'deactivate')); 