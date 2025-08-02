<?php
/**
 * Plugin Name: Eseabasi Inventory Management
 * Plugin URI: https://github.com/Officialese/eseabasi
 * Description: A comprehensive inventory management system for import, stock, and chopped items with real-time integration and analytics.
 * Version: 1.0.0
 * Author: Eseabasi Team
 * Text Domain: eseabasi-inventory
 * Domain Path: /languages
 * License: GPL v2 or later
 * 
 * @package EseabasiInventory
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ESEABASI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ESEABASI_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('ESEABASI_VERSION', '1.0.0');
define('ESEABASI_DB_VERSION', '1.0');

/**
 * Main plugin class
 */
class EseabasiInventory {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Load required files
        $this->load_dependencies();
        
        // Initialize components
        $this->init_hooks();
        
        // Load textdomain for translations
        load_plugin_textdomain('eseabasi-inventory', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        require_once ESEABASI_PLUGIN_PATH . 'includes/class-database.php';
        require_once ESEABASI_PLUGIN_PATH . 'includes/class-admin.php';
        require_once ESEABASI_PLUGIN_PATH . 'includes/class-frontend.php';
        require_once ESEABASI_PLUGIN_PATH . 'includes/class-integration.php';
        require_once ESEABASI_PLUGIN_PATH . 'includes/class-analytics.php';
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Initialize database
        new Eseabasi_Database();
        
        // Initialize admin functionality
        if (is_admin()) {
            new Eseabasi_Admin();
        }
        
        // Initialize frontend functionality
        new Eseabasi_Frontend();
        
        // Initialize integration logic
        new Eseabasi_Integration();
        
        // Initialize analytics
        new Eseabasi_Analytics();
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $database = new Eseabasi_Database();
        $database->create_tables();
        
        // Insert default products
        $database->insert_default_products();
        
        // Set default options
        add_option('eseabasi_db_version', ESEABASI_DB_VERSION);
        add_option('eseabasi_low_stock_threshold', 10);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up scheduled events if any
        wp_clear_scheduled_hook('eseabasi_daily_reset');
    }
}

// Initialize the plugin
new EseabasiInventory();