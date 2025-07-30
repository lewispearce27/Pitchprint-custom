<?php
/**
 * Admin settings class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class PitchPrint_Admin {
    
    private static $instance = null;
    
    /**
     * Get single instance
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
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // AJAX handlers
        add_action('wp_ajax_pitchprint_test_connection', array($this, 'test_connection'));
        add_action('wp_ajax_pitchprint_fetch_categories', array($this, 'fetch_categories'));
        add_action('wp_ajax_pitchprint_fetch_designs', array($this, 'fetch_designs'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('PitchPrint Settings', 'pitchprint-integration'),
            __('PitchPrint', 'pitchprint-integration'),
            'manage_options',
            'pitchprint-settings',
            array($this, 'settings_page'),
            'dashicons-admin-generic',
            30
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('pitchprint_settings_group', 'pitchprint_api_key');
        register_setting('pitchprint_settings_group', 'pitchprint_secret_key');
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Settings saved successfully.', 'pitchprint-integration'); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php settings_fields('pitchprint_settings_group'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="pitchprint_api_key"><?php _e('API Key', 'pitchprint-integration'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="pitchprint_api_key" 
                                   name="pitchprint_api_key" 
                                   value="<?php echo esc_attr(get_option('pitchprint_api_key')); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php _e('Enter your PitchPrint API Key. You can find this in your PitchPrint admin panel.', 'pitchprint-integration'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="pitchprint_secret_key"><?php _e('Secret Key', 'pitchprint-integration'); ?></label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="pitchprint_secret_key" 
                                   name="pitchprint_secret_key" 
                                   value="<?php echo esc_attr(get_option('pitchprint_secret_key')); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php _e('Enter your PitchPrint Secret Key. This is used for API authentication.', 'pitchprint-integration'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <?php submit_button(__('Save Settings', 'pitchprint-integration'), 'primary', 'submit', false); ?>
                    <button type="button" id="test-connection" class="button button-secondary">
                        <?php _e('Test Connection', 'pitchprint-integration'); ?>
                    </button>
                </p>
            </form>
            
            <div id="connection-status" style="margin-top: 20px;"></div>
            
            <div class="pitchprint-info" style="margin-top: 40px; padding: 20px; background: #f0f0f0; border-radius: 5px;">
                <h3><?php _e('Getting Started', 'pitchprint-integration'); ?></h3>
                <ol>
                    <li><?php _e('Enter your API Key and Secret Key above', 'pitchprint-integration'); ?></li>
                    <li><?php _e('Save the settings and test the connection', 'pitchprint-integration'); ?></li>
                    <li><?php _e('Go to any product and configure PitchPrint settings', 'pitchprint-integration'); ?></li>
                    <li><?php _e('Select button types and design templates for each product', 'pitchprint-integration'); ?></li>
                </ol>
                
                <h3><?php _e('Documentation', 'pitchprint-integration'); ?></h3>
                <p>
                    <?php 
                    printf(
                        __('For more information, visit the <a href="%s" target="_blank">PitchPrint Documentation</a>.', 'pitchprint-integration'),
                        'https://docs.pitchprint.com'
                    ); 
                    ?>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Test connection AJAX handler
     */
    public function test_connection() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'pitchprint_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        $api_key = get_option('pitchprint_api_key');
        $secret_key = get_option('pitchprint_secret_key');
        
        if (empty($api_key) || empty($secret_key)) {
            wp_send_json_error(array(
                'message' => __('Please enter both API Key and Secret Key.', 'pitchprint-integration')
            ));
        }
        
        // Test the connection using PitchPrint API
        $api = new PitchPrint_API($api_key, $secret_key);
        $result = $api->test_connection();
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => __('Connection successful! Your API credentials are valid.', 'pitchprint-integration')
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['message']
            ));
        }
    }
    
    /**
     * Fetch categories AJAX handler
     */
    public function fetch_categories() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'pitchprint_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        $api_key = get_option('pitchprint_api_key');
        $secret_key = get_option('pitchprint_secret_key');
        
        if (empty($api_key) || empty($secret_key)) {
            wp_send_json_error(array(
                'message' => __('API credentials not configured.', 'pitchprint-integration')
            ));
        }
        
        $api = new PitchPrint_API($api_key, $secret_key);
        $result = $api->get_categories();
        
        if ($result['success']) {
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error(array(
                'message' => $result['message']
            ));
        }
    }
    
    /**
     * Fetch designs AJAX handler
     */
    public function fetch_designs() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'pitchprint_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        $category_id = isset($_POST['category_id']) ? sanitize_text_field($_POST['category_id']) : '';
        
        if (empty($category_id)) {
            wp_send_json_error(array(
                'message' => __('Category ID is required.', 'pitchprint-integration')
            ));
        }
        
        $api_key = get_option('pitchprint_api_key');
        $secret_key = get_option('pitchprint_secret_key');
        
        if (empty($api_key) || empty($secret_key)) {
            wp_send_json_error(array(
                'message' => __('API credentials not configured.', 'pitchprint-integration')
            ));
        }
        
        $api = new PitchPrint_API($api_key, $secret_key);
        $result = $api->get_designs($category_id);
        
        if ($result['success']) {
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error(array(
                'message' => $result['message']
            ));
        }
    }
}
