<?php
/**
 * Plugin Name: PitchPrint Integration
 * Plugin URI: https://your-website.com/pitchprint-integration
 * Description: Integrate PitchPrint web-to-print solution with WooCommerce
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: pitchprint-integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PITCHPRINT_VERSION', '1.0.0');
define('PITCHPRINT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PITCHPRINT_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Include required files
require_once PITCHPRINT_PLUGIN_PATH . 'includes/class-pitchprint-admin.php';
require_once PITCHPRINT_PLUGIN_PATH . 'includes/class-pitchprint-product.php';
require_once PITCHPRINT_PLUGIN_PATH . 'includes/class-pitchprint-frontend.php';
require_once PITCHPRINT_PLUGIN_PATH . 'includes/class-pitchprint-api.php';

/**
 * Main plugin class
 */
class PitchPrint_Integration {
    
    private static $instance = null;
    
    /**
     * Get single instance of the plugin
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
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Initialize components
        add_action('init', array($this, 'init'));
        
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Load plugin textdomain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
    }
    
    /**
     * Initialize plugin components
     */
    public function init() {
        // Initialize admin
        if (is_admin()) {
            PitchPrint_Admin::get_instance();
            PitchPrint_Product::get_instance();
        }
        
        // Initialize frontend
        if (!is_admin()) {
            PitchPrint_Frontend::get_instance();
        }
    }
    
    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('PitchPrint Integration requires WooCommerce to be installed and active.', 'pitchprint-integration'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('pitchprint-integration', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        if (is_product()) {
            // jQuery is required for PitchPrint
            wp_enqueue_script('jquery');
            
            // PitchPrint client script
            wp_enqueue_script('pitchprint-client', 'https://pitchprint.io/rsc/js/client.js', array('jquery'), PITCHPRINT_VERSION, true);
            
            // Our custom script
            wp_enqueue_script('pitchprint-frontend', PITCHPRINT_PLUGIN_URL . 'assets/js/frontend.js', array('jquery', 'pitchprint-client'), PITCHPRINT_VERSION, true);
            
            // Localize script
            wp_localize_script('pitchprint-frontend', 'pitchprint_vars', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('pitchprint_nonce'),
                'api_key' => get_option('pitchprint_api_key', ''),
                'is_customizable' => $this->is_product_customizable()
            ));
            
            // Add custom CSS
            wp_enqueue_style('pitchprint-frontend', PITCHPRINT_PLUGIN_URL . 'assets/css/frontend.css', array(), PITCHPRINT_VERSION);
        }
    }
    
    /**
     * Enqueue admin scripts
     */
    public function admin_enqueue_scripts($hook) {
        // Only load on our admin pages and product edit page
        if ('toplevel_page_pitchprint-settings' === $hook || 
            'post.php' === $hook || 
            'post-new.php' === $hook) {
            
            wp_enqueue_script('pitchprint-admin', PITCHPRINT_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), PITCHPRINT_VERSION, true);
            
            // Localize script
            wp_localize_script('pitchprint-admin', 'pitchprint_admin_vars', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('pitchprint_admin_nonce'),
                'api_key' => get_option('pitchprint_api_key', ''),
                'secret_key' => get_option('pitchprint_secret_key', '')
            ));
            
            wp_enqueue_style('pitchprint-admin', PITCHPRINT_PLUGIN_URL . 'assets/css/admin.css', array(), PITCHPRINT_VERSION);
        }
    }
    
    /**
     * Check if current product is customizable
     */
    private function is_product_customizable() {
        global $product;
        
        if (!$product) {
            return false;
        }
        
        $button_type = get_post_meta($product->get_id(), '_pitchprint_button_type', true);
        return !empty($button_type) && $button_type !== 'none';
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables if needed
        $this->create_tables();
        
        // Set default options
        add_option('pitchprint_api_key', '');
        add_option('pitchprint_secret_key', '');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Cleanup tasks
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'pitchprint_projects';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            project_id varchar(255) NOT NULL,
            project_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY product_id (product_id),
            KEY project_id (project_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Initialize the plugin
PitchPrint_Integration::get_instance();
