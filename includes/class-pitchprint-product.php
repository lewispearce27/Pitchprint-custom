<?php
/**
 * Product settings class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class PitchPrint_Product {
    
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
        // Add product data tab
        add_filter('woocommerce_product_data_tabs', array($this, 'add_product_data_tab'));
        
        // Add product data panel
        add_action('woocommerce_product_data_panels', array($this, 'add_product_data_panel'));
        
        // Save product data
        add_action('woocommerce_process_product_meta', array($this, 'save_product_data'));
        
        // Add to cart handling
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 2);
        add_filter('woocommerce_get_item_data', array($this, 'display_cart_item_data'), 10, 2);
        
        // Save order item meta
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_order_item_meta'), 10, 4);
    }
    
    /**
     * Add product data tab
     */
    public function add_product_data_tab($tabs) {
        $tabs['pitchprint'] = array(
            'label' => __('PitchPrint', 'pitchprint-integration'),
            'target' => 'pitchprint_product_data',
            'class' => array('show_if_simple', 'show_if_variable'),
            'priority' => 21
        );
        
        return $tabs;
    }
    
    /**
     * Add product data panel
     */
    public function add_product_data_panel() {
        global $post;
        
        $button_type = get_post_meta($post->ID, '_pitchprint_button_type', true);
        $category_id = get_post_meta($post->ID, '_pitchprint_category_id', true);
        $design_id = get_post_meta($post->ID, '_pitchprint_design_id', true);
        ?>
        
        <div id="pitchprint_product_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <p class="form-field">
                    <label for="pitchprint_button_type"><?php _e('PitchPrint Buttons', 'pitchprint-integration'); ?></label>
                    <select id="pitchprint_button_type" name="_pitchprint_button_type" style="width: 50%;">
                        <option value="none" <?php selected($button_type, 'none'); ?>>
                            <?php _e('None', 'pitchprint-integration'); ?>
                        </option>
                        <option value="design_online" <?php selected($button_type, 'design_online'); ?>>
                            <?php _e('Design Online', 'pitchprint-integration'); ?>
                        </option>
                        <option value="upload_artwork" <?php selected($button_type, 'upload_artwork'); ?>>
                            <?php _e('Upload Artwork', 'pitchprint-integration'); ?>
                        </option>
                        <option value="both" <?php selected($button_type, 'both'); ?>>
                            <?php _e('Both Buttons', 'pitchprint-integration'); ?>
                        </option>
                    </select>
                    <span class="description">
                        <?php _e('Select which buttons to show on the frontend', 'pitchprint-integration'); ?>
                    </span>
                </p>
                
                <p class="form-field pitchprint-category-field">
                    <label for="pitchprint_category_id"><?php _e('Design Category', 'pitchprint-integration'); ?></label>
                    <select id="pitchprint_category_id" 
                            name="_pitchprint_category_id" 
                            style="width: 50%;"
                            data-saved-value="<?php echo esc_attr($category_id); ?>">
                        <option value=""><?php _e('Loading categories...', 'pitchprint-integration'); ?></option>
                        <?php if ($category_id) : ?>
                            <option value="<?php echo esc_attr($category_id); ?>" selected>
                                <?php echo esc_html($category_id); ?>
                            </option>
                        <?php endif; ?>
                    </select>
                    <span class="description">
                        <?php _e('Select a design category from PitchPrint', 'pitchprint-integration'); ?>
                    </span>
                </p>
                
                <p class="form-field pitchprint-design-field">
                    <label for="pitchprint_design_id"><?php _e('Design Template', 'pitchprint-integration'); ?></label>
                    <select id="pitchprint_design_id" 
                            name="_pitchprint_design_id" 
                            style="width: 50%;"
                            data-saved-value="<?php echo esc_attr($design_id); ?>">
                        <option value=""><?php _e('Select a design...', 'pitchprint-integration'); ?></option>
                        <?php if ($design_id) : ?>
                            <option value="<?php echo esc_attr($design_id); ?>" selected>
                                <?php echo esc_html($design_id); ?>
                            </option>
                        <?php endif; ?>
                    </select>
                    <span class="description">
                        <?php _e('Select a specific design template', 'pitchprint-integration'); ?>
                    </span>
                </p>
                
                <div class="pitchprint-connection-status">
                    <?php
                    $api_key = get_option('pitchprint_api_key');
                    $secret_key = get_option('pitchprint_secret_key');
                    
                    if (empty($api_key) || empty($secret_key)) :
                    ?>
                        <div class="notice notice-warning inline">
                            <p>
                                <?php 
                                printf(
