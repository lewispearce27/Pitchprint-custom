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
        
        // AJAX handler for loading designs
        add_action('wp_ajax_pitchprint_load_design_options', array($this, 'ajax_load_design_options'));
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
        $design_value = get_post_meta($post->ID, '_pitchprint_design_value', true);
        $display_mode = get_post_meta($post->ID, '_pitchprint_display_mode', true);
        $enable_upload = get_post_meta($post->ID, '_pitchprint_enable_upload', true);
        
        // Default display mode
        if (empty($display_mode)) {
            $display_mode = 'Full Window';
        }
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
                
                <div class="pitchprint-design-section" <?php echo ($button_type === 'none' || $button_type === 'upload_artwork') ? 'style="display:none;"' : ''; ?>>
                    <p class="form-field">
                        <label for="pitchprint_design"><?php _e('PitchPrint Design', 'pitchprint-integration'); ?></label>
                        <select id="pitchprint_design" 
                                name="_pitchprint_design_value" 
                                style="width: 50%;"
                                data-saved-value="<?php echo esc_attr($design_value); ?>">
                            <option value=""><?php _e('Loading...', 'pitchprint-integration'); ?></option>
                            <?php if ($design_value) : ?>
                                <option value="<?php echo esc_attr($design_value); ?>" selected>
                                    <?php echo esc_html($design_value); ?>
                                </option>
                            <?php endif; ?>
                        </select>
                        <span class="description">
                            <?php _e('Select a category or specific design', 'pitchprint-integration'); ?>
                        </span>
                    </p>
                    
                    <p class="form-field">
                        <label for="pitchprint_enable_upload">
                            <input type="checkbox" 
                                   id="pitchprint_enable_upload" 
                                   name="_pitchprint_enable_upload" 
                                   value="yes" 
                                   <?php checked($enable_upload, 'yes'); ?> />
                            <?php _e('Check this to enable clients to upload their files', 'pitchprint-integration'); ?>
                        </label>
                    </p>
                </div>
                
                <p class="form-field">
                    <label for="pitchprint_display_mode"><?php _e('Display Mode', 'pitchprint-integration'); ?></label>
                    <select id="pitchprint_display_mode" name="_pitchprint_display_mode" style="width: 50%;">
                        <option value="Full Window" <?php selected($display_mode, 'Full Window'); ?>>
                            <?php _e('Full Window', 'pitchprint-integration'); ?>
                        </option>
                        <option value="Inline" <?php selected($display_mode, 'Inline'); ?>>
                            <?php _e('Inline', 'pitchprint-integration'); ?>
                        </option>
                        <option value="Popup" <?php selected($display_mode, 'Popup'); ?>>
                            <?php _e('Popup', 'pitchprint-integration'); ?>
                        </option>
                        <option value="Mini" <?php selected($display_mode, 'Mini'); ?>>
                            <?php _e('Mini', 'pitchprint-integration'); ?>
                        </option>
                    </select>
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
                                    __('PitchPrint API credentials not configured. Please <a href="%s">configure them here</a>.', 'pitchprint-integration'),
                                    admin_url('admin.php?page=pitchprint-settings')
                                );
                                ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var savedValue = $('#pitchprint_design').data('saved-value');
                console.log('Saved design value:', savedValue);
                
                // Show/hide design section based on button type
                function toggleDesignSection() {
                    var buttonType = $('#pitchprint_button_type').val();
                    if (buttonType === 'none' || buttonType === 'upload_artwork') {
                        $('.pitchprint-design-section').hide();
                    } else {
                        $('.pitchprint-design-section').show();
                    }
                }
                
                // Load design options
                function loadDesignOptions() {
                    var $select = $('#pitchprint_design');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'pitchprint_load_design_options',
                            nonce: '<?php echo wp_create_nonce('pitchprint_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            $select.empty();
                            $select.append('<option value=""><?php _e('-- Select --', 'pitchprint-integration'); ?></option>');
                            
                            if (response.success && response.data) {
                                $.each(response.data, function(index, item) {
                                    if (item.type === 'category') {
                                        // Category option
                                        $select.append(
                                            '<option value="CAT:' + item.id + '">' + 
                                            item.title + 
                                            '</option>'
                                        );
                                        
                                        // Add designs under this category
                                        if (item.designs && item.designs.length > 0) {
                                            $.each(item.designs, function(idx, design) {
                                                var selected = (savedValue === 'DES:' + design.id) ? ' selected' : '';
                                                $select.append(
                                                    '<option value="DES:' + design.id + '"' + selected + '>' + 
                                                    '&nbsp;&nbsp;&nbsp;&nbsp;» ' + design.title + 
                                                    '</option>'
                                                );
                                            });
                                        }
                                    }
                                });
                                
                                // Set saved value if exists
                                if (savedValue) {
                                    $select.val(savedValue);
                                }
                            }
                        },
                        error: function() {
                            $select.empty().append('<option value=""><?php _e('Error loading options', 'pitchprint-integration'); ?></option>');
                        }
                    });
                }
                
                // Initial load
                toggleDesignSection();
                loadDesignOptions();
                
                // Toggle on button type change
                $('#pitchprint_button_type').on('change', toggleDesignSection);
            });
        </script>
        <?php
    }
    
    /**
     * AJAX handler to load design options
     */
    public function ajax_load_design_options() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'pitchprint_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        $api_key = get_option('pitchprint_api_key');
        $secret_key = get_option('pitchprint_secret_key');
        
        if (empty($api_key) || empty($secret_key)) {
            wp_send_json_error(array('message' => 'API credentials not configured'));
        }
        
        $api = new PitchPrint_API($api_key, $secret_key);
        
        // Get categories
        $categories_result = $api->get_categories();
        
        if (!$categories_result['success']) {
            wp_send_json_error(array('message' => 'Failed to load categories'));
        }
        
        $options = array();
        
        // Process each category
        foreach ($categories_result['data'] as $category) {
            $category_item = array(
                'type' => 'category',
                'id' => $category['id'],
                'title' => $category['title'],
                'designs' => array()
            );
            
            // Get designs for this category
            $designs_result = $api->get_designs($category['id']);
            
            if ($designs_result['success'] && isset($designs_result['data']['data']['items'])) {
                foreach ($designs_result['data']['data']['items'] as $design) {
                    $category_item['designs'][] = array(
                        'id' => $design['designId'],
                        'title' => isset($design['title']) ? $design['title'] : $design['designId']
                    );
                }
            }
            
            $options[] = $category_item;
        }
        
        wp_send_json_success($options);
    }
    
    /**
     * Save product data
     */
    public function save_product_data($post_id) {
        // Save button type
        if (isset($_POST['_pitchprint_button_type'])) {
            update_post_meta($post_id, '_pitchprint_button_type', sanitize_text_field($_POST['_pitchprint_button_type']));
        }
        
        // Save design value (category or design)
        if (isset($_POST['_pitchprint_design_value'])) {
            $design_value = sanitize_text_field($_POST['_pitchprint_design_value']);
            update_post_meta($post_id, '_pitchprint_design_value', $design_value);
            
            // Parse the value to store category and design separately for frontend use
            if (strpos($design_value, 'CAT:') === 0) {
                // It's a category
                $category_id = substr($design_value, 4);
                update_post_meta($post_id, '_pitchprint_category_id', $category_id);
                delete_post_meta($post_id, '_pitchprint_design_id');
            } elseif (strpos($design_value, 'DES:') === 0) {
                // It's a design
                $design_id = substr($design_value, 4);
                update_post_meta($post_id, '_pitchprint_design_id', $design_id);
                // We should also store the category ID for this design
                // This would require looking it up from the API or storing it during selection
            }
        }
        
        // Save display mode
        if (isset($_POST['_pitchprint_display_mode'])) {
            update_post_meta($post_id, '_pitchprint_display_mode', sanitize_text_field($_POST['_pitchprint_display_mode']));
        }
        
        // Save enable upload
        if (isset($_POST['_pitchprint_enable_upload'])) {
            update_post_meta($post_id, '_pitchprint_enable_upload', 'yes');
        } else {
            update_post_meta($post_id, '_pitchprint_enable_upload', 'no');
        }
    }
    
    /**
     * Add cart item data
     */
    public function add_cart_item_data($cart_item_data, $product_id) {
        if (isset($_POST['pitchprint_project_id'])) {
            $cart_item_data['pitchprint_project_id'] = sanitize_text_field($_POST['pitchprint_project_id']);
        }
        
        if (isset($_POST['pitchprint_project_data'])) {
            $cart_item_data['pitchprint_project_data'] = $_POST['pitchprint_project_data'];
        }
        
        return $cart_item_data;
    }
    
    /**
     * Display cart item data
     */
    public function display_cart_item_data($item_data, $cart_item) {
        if (isset($cart_item['pitchprint_project_id'])) {
            $item_data[] = array(
                'key' => __('Design', 'pitchprint-integration'),
                'value' => __('Custom Design Added', 'pitchprint-integration')
            );
        }
        
        return $item_data;
    }
    
    /**
     * Save order item meta
     */
    public function save_order_item_meta($item, $cart_item_key, $values, $order) {
        if (isset($values['pitchprint_project_id'])) {
            $item->add_meta_data('_pitchprint_project_id', $values['pitchprint_project_id']);
        }
        
        if (isset($values['pitchprint_project_data'])) {
            $item->add_meta_data('_pitchprint_project_data', $values['pitchprint_project_data']);
            
            // Save to custom table for easy retrieval
            global $wpdb;
            $table_name = $wpdb->prefix . 'pitchprint_projects';
            
            $wpdb->insert(
                $table_name,
                array(
                    'order_id' => $order->get_id(),
                    'product_id' => $item->get_product_id(),
                    'project_id' => $values['pitchprint_project_id'],
                    'project_data' => maybe_serialize($values['pitchprint_project_data']),
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%d', '%s', '%s', '%s')
            );
        }
    }
}
