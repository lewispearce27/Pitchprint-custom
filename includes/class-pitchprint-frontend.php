<?php
/**
 * Frontend class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class PitchPrint_Frontend {
    
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
        // Add buttons after add to cart button
        add_action('woocommerce_after_add_to_cart_button', array($this, 'display_pitchprint_buttons'));
        
        // Add hidden fields to cart form
        add_action('woocommerce_before_add_to_cart_button', array($this, 'add_hidden_fields'));
        
        // AJAX handlers
        add_action('wp_ajax_pitchprint_save_project', array($this, 'save_project'));
        add_action('wp_ajax_nopriv_pitchprint_save_project', array($this, 'save_project'));
        
        // Modify add to cart behavior
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_add_to_cart'), 10, 3);
        
        // Display design preview in order
        add_action('woocommerce_order_item_meta_end', array($this, 'display_order_item_design'), 10, 4);
    }
    
    /**
     * Display PitchPrint buttons
     */
    public function display_pitchprint_buttons() {
        global $product;
        
        $button_type = get_post_meta($product->get_id(), '_pitchprint_button_type', true);
        $category_id = get_post_meta($product->get_id(), '_pitchprint_category_id', true);
        $design_id = get_post_meta($product->get_id(), '_pitchprint_design_id', true);
        
        if ($button_type === 'none' || empty($button_type)) {
            return;
        }
        
        ?>
        <div class="pitchprint-buttons-wrapper" style="margin-top: 20px;">
            <?php if ($button_type === 'design_online' || $button_type === 'both') : ?>
                <button type="button" 
                        id="pitchprint-design-btn" 
                        class="button alt pitchprint-button design-online-btn"
                        data-design-id="<?php echo esc_attr($design_id); ?>"
                        data-category-id="<?php echo esc_attr($category_id); ?>"
                        <?php echo empty($design_id) ? 'disabled' : ''; ?>>
                    <span class="dashicons dashicons-edit"></span>
                    <?php _e('Design Online', 'pitchprint-integration'); ?>
                </button>
            <?php endif; ?>
            
            <?php if ($button_type === 'upload_artwork' || $button_type === 'both') : ?>
                <button type="button" 
                        id="pitchprint-upload-btn" 
                        class="button alt pitchprint-button upload-artwork-btn">
                    <span class="dashicons dashicons-upload"></span>
                    <?php _e('Upload Artwork', 'pitchprint-integration'); ?>
                </button>
            <?php endif; ?>
            
            <div id="pitchprint-loader" class="pitchprint-loader" style="display: none;">
                <span class="spinner is-active"></span>
                <?php _e('Loading designer...', 'pitchprint-integration'); ?>
            </div>
            
            <div id="pitchprint-preview" class="pitchprint-preview" style="display: none;">
                <h4><?php _e('Your Design:', 'pitchprint-integration'); ?></h4>
                <div class="preview-images"></div>
                <button type="button" class="button edit-design-btn">
                    <?php _e('Edit Design', 'pitchprint-integration'); ?>
                </button>
            </div>
        </div>
        
        <script type="text/javascript">
            var pitchPrintProductData = {
                productId: <?php echo $product->get_id(); ?>,
                buttonType: '<?php echo esc_js($button_type); ?>',
                designId: '<?php echo esc_js($design_id); ?>',
                categoryId: '<?php echo esc_js($category_id); ?>',
                apiKey: '<?php echo esc_js(get_option('pitchprint_api_key')); ?>'
            };
        </script>
        <?php
    }
    
    /**
     * Add hidden fields to cart form
     */
    public function add_hidden_fields() {
        ?>
        <input type="hidden" id="pitchprint_project_id" name="pitchprint_project_id" value="">
        <input type="hidden" id="pitchprint_project_data" name="pitchprint_project_data" value="">
        <?php
    }
    
    /**
     * Validate add to cart
     */
    public function validate_add_to_cart($passed, $product_id, $quantity) {
        $button_type = get_post_meta($product_id, '_pitchprint_button_type', true);
        
        // If product requires customization
        if ($button_type && $button_type !== 'none') {
            $customization_required = apply_filters('pitchprint_customization_required', false, $product_id);
            
            if ($customization_required && empty($_POST['pitchprint_project_id'])) {
                wc_add_notice(__('Please customize your product before adding to cart.', 'pitchprint-integration'), 'error');
                return false;
            }
        }
        
        return $passed;
    }
    
    /**
     * Save project via AJAX
     */
    public function save_project() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'pitchprint_nonce')) {
            wp_die('Security check failed');
        }
        
        $project_id = isset($_POST['project_id']) ? sanitize_text_field($_POST['project_id']) : '';
        $project_data = isset($_POST['project_data']) ? $_POST['project_data'] : '';
        
        if (empty($project_id)) {
            wp_send_json_error(array(
                'message' => __('Invalid project ID', 'pitchprint-integration')
            ));
        }
        
        // You can add additional processing here if needed
        
        wp_send_json_success(array(
            'message' => __('Project saved successfully', 'pitchprint-integration'),
            'project_id' => $project_id
        ));
    }
    
    /**
     * Display design in order items
     */
    public function display_order_item_design($item_id, $item, $order, $plain_text) {
        $project_id = $item->get_meta('_pitchprint_project_id');
        
        if ($project_id) {
            echo '<div class="pitchprint-order-design">';
            echo '<strong>' . __('Custom Design:', 'pitchprint-integration') . '</strong> ';
            echo '<a href="#" class="view-pitchprint-design" data-project-id="' . esc_attr($project_id) . '">';
            echo __('View Design', 'pitchprint-integration');
            echo '</a>';
            
            // Add download link if admin
            if (current_user_can('manage_woocommerce')) {
                echo ' | ';
                echo '<a href="#" class="download-pitchprint-pdf" data-project-id="' . esc_attr($project_id) . '">';
                echo __('Download PDF', 'pitchprint-integration');
                echo '</a>';
            }
            
            echo '</div>';
        }
    }
    
    /**
     * Get project details
     */
    public function get_project_details($project_id) {
        $api_key = get_option('pitchprint_api_key');
        $secret_key = get_option('pitchprint_secret_key');
        
        if (empty($api_key) || empty($secret_key)) {
            return false;
        }
        
        $api = new PitchPrint_API($api_key, $secret_key);
        return $api->get_project($project_id);
    }
}
