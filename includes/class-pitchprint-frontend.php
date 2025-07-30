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
        
        add_action('wp_ajax_pitchprint_upload_file', array($this, 'handle_file_upload'));
        add_action('wp_ajax_nopriv_pitchprint_upload_file', array($this, 'handle_file_upload'));
        
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
            
            <!-- Upload Form Modal -->
            <div id="pitchprint-upload-modal" class="pitchprint-modal" style="display: none;">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h2><?php _e('Upload Your Artwork', 'pitchprint-integration'); ?></h2>
                    
                    <form id="pitchprint-upload-form" enctype="multipart/form-data">
                        <div class="upload-area">
                            <input type="file" 
                                   id="pitchprint-file-input" 
                                   name="artwork_file" 
                                   accept=".pdf,.jpg,.jpeg,.png,.ai,.eps,.svg" 
                                   required>
                            <label for="pitchprint-file-input" class="upload-label">
                                <span class="dashicons dashicons-cloud-upload"></span>
                                <span><?php _e('Choose a file or drag it here', 'pitchprint-integration'); ?></span>
                            </label>
                            <div class="file-info" style="display: none;">
                                <span class="file-name"></span>
                                <button type="button" class="remove-file"><?php _e('Remove', 'pitchprint-integration'); ?></button>
                            </div>
                        </div>
                        
                        <div class="upload-requirements">
                            <h4><?php _e('File Requirements:', 'pitchprint-integration'); ?></h4>
                            <ul>
                                <li><?php _e('Accepted formats: PDF, JPG, PNG, AI, EPS, SVG', 'pitchprint-integration'); ?></li>
                                <li><?php _e('Maximum file size: 50MB', 'pitchprint-integration'); ?></li>
                                <li><?php _e('Recommended: High resolution (300 DPI)', 'pitchprint-integration'); ?></li>
                            </ul>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="button alt">
                                <?php _e('Continue to Designer', 'pitchprint-integration'); ?>
                            </button>
                            <button type="button" class="button cancel-upload">
                                <?php _e('Cancel', 'pitchprint-integration'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div id="pitchprint-loader" class="pitchprint-loader" style="display: none;">
                <span class="spinner is-active"></span>
                <span class="loader-text"><?php _e('Loading designer...', 'pitchprint-integration'); ?></span>
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
                apiKey: '<?php echo esc_js(get_option('pitchprint_api_key')); ?>',
                ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
                uploadNonce: '<?php echo wp_create_nonce('pitchprint_upload_nonce'); ?>'
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
        <input type="hidden" id="pitchprint_uploaded_file" name="pitchprint_uploaded_file" value="">
        <?php
    }
    
    /**
     * Handle file upload via AJAX
     */
    public function handle_file_upload() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'pitchprint_upload_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'pitchprint-integration')));
        }
        
        if (empty($_FILES['artwork_file'])) {
            wp_send_json_error(array('message' => __('No file uploaded', 'pitchprint-integration')));
        }
        
        $file = $_FILES['artwork_file'];
        
        // Validate file type
        $allowed_types = array('pdf', 'jpg', 'jpeg', 'png', 'ai', 'eps', 'svg');
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $allowed_types)) {
            wp_send_json_error(array('message' => __('Invalid file type', 'pitchprint-integration')));
        }
        
        // Validate file size (50MB max)
        $max_size = 50 * 1024 * 1024; // 50MB in bytes
        if ($file['size'] > $max_size) {
            wp_send_json_error(array('message' => __('File size exceeds 50MB limit', 'pitchprint-integration')));
        }
        
        // Upload file using WordPress functions
        $upload_overrides = array('test_form' => false);
        $uploaded_file = wp_handle_upload($file, $upload_overrides);
        
        if (isset($uploaded_file['error'])) {
            wp_send_json_error(array('message' => $uploaded_file['error']));
        }
        
        // Store file info in session or temporary option
        $file_id = 'pp_upload_' . wp_generate_password(12, false);
        set_transient($file_id, array(
            'url' => $uploaded_file['url'],
            'file' => $uploaded_file['file'],
            'type' => $uploaded_file['type'],
            'name' => $file['name']
        ), HOUR_IN_SECONDS); // Store for 1 hour
        
        wp_send_json_success(array(
            'file_id' => $file_id,
            'file_url' => $uploaded_file['url'],
            'file_name' => $file['name'],
            'message' => __('File uploaded successfully', 'pitchprint-integration')
        ));
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
        
        // Clean up uploaded file if it was temporary
        if (isset($_POST['uploaded_file_id'])) {
            $file_id = sanitize_text_field($_POST['uploaded_file_id']);
            $file_data = get_transient($file_id);
            if ($file_data && isset($file_data['file']) && file_exists($file_data['file'])) {
                // Optionally delete the temporary file
                // wp_delete_file($file_data['file']);
            }
            delete_transient($file_id);
        }
        
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
