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
                    <input type="text" 
                           id="pitchprint_category_id" 
                           name="_pitchprint_category_id" 
                           value="<?php echo esc_attr($category_id); ?>" 
                           style="width: 50%;"
                           placeholder="<?php _e('Enter category ID (e.g., cat1)', 'pitchprint-integration'); ?>" />
                    <span class="description">
                        <?php _e('Enter the category ID from your PitchPrint admin. Common IDs: cat1, cat2, cat3, etc.', 'pitchprint-integration'); ?>
                    </span>
                </p>
                
                <p class="form-field pitchprint-design-field">
                    <label for="pitchprint_design_id"><?php _e('Design Template', 'pitchprint-integration'); ?></label>
                    <select id="pitchprint_design_id" name="_pitchprint_design_id" style="width: 50%;">
                        <option value=""><?php _e('Select a design...', 'pitchprint-integration'); ?></option>
                        <?php if ($design_id) : ?>
                            <option value="<?php echo esc_attr($design_id); ?>" selected>
                                <?php echo esc_html($design_id); ?>
                            </option>
                        <?php endif; ?>
                    </select>
                    <button type="button" class="button" id="load-pitchprint-designs" <?php echo empty($category_id) ? 'disabled' : ''; ?>>
                        <?php _e('Load Designs', 'pitchprint-integration'); ?>
                    </button>
                    <span class="description">
                        <?php _e('Enter a category ID above, then click Load Designs', 'pitchprint-integration'); ?>
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
                                    __('PitchPrint API credentials not configured. Please <a href="%s">configure them here</a>.', 'pitchprint-integration'),
                                    admin_url('admin.php?page=pitchprint-settings')
                                );
                                ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="options_group">
                <p class="form-field">
                    <label><?php _e('Instructions', 'pitchprint-integration'); ?></label>
                    <span class="description" style="display: block; margin-left: 0;">
                        <strong><?php _e('Design Online:', 'pitchprint-integration'); ?></strong> 
                        <?php _e('Opens PitchPrint designer with the selected template', 'pitchprint-integration'); ?><br>
                        <strong><?php _e('Upload Artwork:', 'pitchprint-integration'); ?></strong> 
                        <?php _e('Shows upload form, then opens PitchPrint with the uploaded file', 'pitchprint-integration'); ?>
                    </span>
                </p>
            </div>
        </div>
        
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Show/hide fields based on button type
                function togglePitchPrintFields() {
                    var buttonType = $('#pitchprint_button_type').val();
                    
                    if (buttonType === 'none') {
                        $('.pitchprint-category-field, .pitchprint-design-field').hide();
                    } else if (buttonType === 'upload_artwork') {
                        $('.pitchprint-category-field, .pitchprint-design-field').hide();
                    } else {
                        $('.pitchprint-category-field, .pitchprint-design-field').show();
                    }
                }
                
                // Initial toggle
                togglePitchPrintFields();
                
                // Toggle on change
                $('#pitchprint_button_type').on('change', togglePitchPrintFields);
                
                // Enable/disable load designs button when category ID changes
                $('#pitchprint_category_id').on('input', function() {
                    var categoryId = $(this).val().trim();
                    $('#load-pitchprint-designs').prop('disabled', !categoryId);
                });
                
                // Load designs when button clicked
                $('#load-pitchprint-designs').on('click', function() {
                    var categoryId = $('#pitchprint_category_id').val().trim();
                    var $button = $(this);
                    var $designSelect = $('#pitchprint_design_id');
                    
                    if (!categoryId) {
                        alert('<?php _e('Please enter a category ID', 'pitchprint-integration'); ?>');
                        return;
                    }
                    
                    $button.prop('disabled', true).text('<?php _e('Loading...', 'pitchprint-integration'); ?>');
                    $designSelect.empty().append('<option value=""><?php _e('Loading designs...', 'pitchprint-integration'); ?></option>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'pitchprint_fetch_designs',
                            category_id: categoryId,
                            nonce: '<?php echo wp_create_nonce('pitchprint_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $designSelect.empty().append('<option value=""><?php _e('Select a design...', 'pitchprint-integration'); ?></option>');
                                
                                if (response.data && response.data.data && response.data.data.items) {
                                    $.each(response.data.data.items, function(index, design) {
                                        $designSelect.append(
                                            '<option value="' + design.designId + '">' + 
                                            (design.title || design.designId) + 
                                            '</option>'
                                        );
                                    });
                                    
                                    if (response.data.data.items.length === 0) {
                                        alert('<?php _e('No designs found in this category', 'pitchprint-integration'); ?>');
                                    }
                                } else {
                                    alert('<?php _e('No designs found in this category. Please check the category ID.', 'pitchprint-integration'); ?>');
                                }
                            } else {
                                alert(response.data.message || '<?php _e('Error loading designs. Please check the category ID.', 'pitchprint-integration'); ?>');
                                $designSelect.empty().append('<option value=""><?php _e('Error loading designs', 'pitchprint-integration'); ?></option>');
                            }
                        },
                        error: function() {
                            alert('<?php _e('Error loading designs', 'pitchprint-integration'); ?>');
                            $designSelect.empty().append('<option value=""><?php _e('Error loading designs', 'pitchprint-integration'); ?></option>');
                        },
                        complete: function() {
                            $button.prop('disabled', false).text('<?php _e('Load Designs', 'pitchprint-integration'); ?>');
                        }
                    });
                });
                
                // Auto-load designs on page load if category is set
                if ($('#pitchprint_category_id').val()) {
                    $('#load-pitchprint-designs').trigger('click');
                }
            });
        </script>
        <?php
    }
    
    /**
     * Save product data
     */
    public function save_product_data($post_id) {
        if (isset($_POST['_pitchprint_button_type'])) {
            update_post_meta($post_id, '_pitchprint_button_type', sanitize_text_field($_POST['_pitchprint_button_type']));
        }
        
        if (isset($_POST['_pitchprint_category_id'])) {
            update_post_meta($post_id, '_pitchprint_category_id', sanitize_text_field($_POST['_pitchprint_category_id']));
        }
        
        if (isset($_POST['_pitchprint_design_id'])) {
            update_post_meta($post_id, '_pitchprint_design_id', sanitize_text_field($_POST['_pitchprint_design_id']));
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
                    'project_data' => maybe_serialize($values['pitchprint_project_data'])
                )
            );
        }
    }
}
